<?php

namespace Tests\Feature;

use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Mail\SolicitudReemplazoCreada;
use App\Models\AreaDesempeno;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use App\Support\TipoReemplazo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class SolicitudReemplazoTipoValidationTest extends IsolatedSecurityTestCase
{
    private ReemplazoPersonal $titular;
    private AreaDesempeno $area;

    protected function setUp(): void
    {
        parent::setUp();
        // La base, el almacenamiento y el correo son de pruebas. Se ejercitan
        // las rutas y el controlador sin el catálogo de módulos ni el lock del padrón.
        $this->withoutMiddleware([EnsureModuleAccess::class, CoordinarEscrituraPadron::class]);
        Mail::fake();

        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->integer('rbd');
            $table->string('nombre_establecimiento');
            $table->boolean('sala_cuna')->default(false);
            $table->timestamps();
        });
        Schema::create('areas_desempeno', function (Blueprint $table) {
            $table->id();
            $table->string('estamento');
            $table->string('nombre');
            $table->string('slug');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('postulant_profiles', fn (Blueprint $table) => $table->id());
        foreach ([
            '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_01_29_192443_create_solicitudes_reemplazo_tables.php',
            '2026_02_05_155127_add_workflow_fields_to_solicitudes_reemplazo_table.php',
            '2026_04_08_210000_add_horas_aula_y_declaracion_to_solicitudes_reemplazo.php',
            '2026_04_09_010000_add_horario_titular_pdf_to_solicitudes_reemplazo.php',
            '2026_05_27_200000_add_reglas_minimas_to_reemplazos_tables.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::create('reemplazos_personal_bloqueos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reemplazo_personal_id');
            $table->boolean('activo');
        });
        Schema::create('permiso_sin_goce_excepciones', function (Blueprint $table) {
            $table->id();
            $table->string('rut_normalizado');
            $table->boolean('activo');
        });

        $establecimiento = Establecimiento::create([
            'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento de prueba',
        ]);
        $this->titular = ReemplazoPersonal::create([
            'establecimiento_id' => $establecimiento->id, 'rbd' => 99999,
            'rut' => '99000000-K', 'nombre' => 'Titular de prueba',
            'estatuto' => 'AAEE', 'tipocontrato' => 'PLANTA',
            'anio' => 2026, 'mes' => 10, 'financiamiento' => 'SUBV GRAL',
            'jornada' => 44, 'jornada_basica' => 44, 'jornada_media' => 0,
            'row_hash' => hash('sha256', 'titular-de-prueba'),
        ]);
        $this->area = AreaDesempeno::create([
            'estamento' => 'asistente', 'nombre' => 'Área de prueba', 'slug' => 'area_prueba',
        ]);
        $user = $this->testUser();
        $user->setRelation('establecimiento', $establecimiento);
        $this->actingAs($user);
    }

    public function test_it_creates_a_request_with_the_complete_mutualidad_option(): void
    {
        $this->post(route('funcionario.solicitudes-reemplazo.store'), $this->payload(TipoReemplazo::REPOSO_MUTUALIDAD))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('funcionario.solicitudes-reemplazo.index'));

        $solicitud = SolicitudReemplazo::sole();
        $this->assertSame(TipoReemplazo::REPOSO_MUTUALIDAD, $solicitud->tipo_reemplazo);
        $this->assertSame('pendiente_uatp', $solicitud->estado);
        $this->assertTrue($solicitud->declaracion_responsabilidad_aceptada);
        $this->assertSame('44.00', $solicitud->jornadas->sole()->reemplazo_total);
        Storage::disk('local')->assertExists([$solicitud->oficio_pdf_path, $solicitud->respaldo_pdf_path]);
        Mail::assertSent(SolicitudReemplazoCreada::class);
    }

    public function test_it_updates_a_request_to_mutualidad_without_changing_its_documents(): void
    {
        $solicitud = $this->existingRequest(TipoReemplazo::ENFERMEDAD_ACCIDENTE_COMUN);
        $this->put(route('funcionario.solicitudes-reemplazo.update', $solicitud), $this->payload(TipoReemplazo::REPOSO_MUTUALIDAD, false))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('funcionario.solicitudes-reemplazo.index'));

        $this->assertSame(TipoReemplazo::REPOSO_MUTUALIDAD, $solicitud->fresh()->tipo_reemplazo);
        $this->assertSame('historico/oficio.pdf', $solicitud->fresh()->oficio_pdf_path);
        $this->assertDatabaseCount('solicitudes_reemplazo', 1);
    }

    public function test_the_historical_deportista_option_with_a_comma_remains_editable(): void
    {
        $tipo = 'Permiso especial para deportistas (Art 74, Ley 19.712)';
        $solicitud = $this->existingRequest($tipo);
        $this->put(route('funcionario.solicitudes-reemplazo.update', $solicitud), $this->payload($tipo, false))
            ->assertSessionHasNoErrors();
        $this->assertSame($tipo, $solicitud->fresh()->tipo_reemplazo);
    }

    public function test_deportista_remains_unavailable_for_new_requests(): void
    {
        $this->post(route('funcionario.solicitudes-reemplazo.store'), $this->payload('Permiso especial para deportistas (Art 74, Ley 19.712)'))
            ->assertSessionHasErrors(['tipo_reemplazo' => 'El tipo de reemplazo seleccionado no se encuentra disponible para nuevas solicitudes.']);
        $this->assertDatabaseCount('solicitudes_reemplazo', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Mail::assertNothingSent();
    }

    #[DataProvider('invalidOptions')]
    public function test_option_fragments_and_unknown_values_are_rejected_on_create_and_update(string $tipo): void
    {
        $this->post(route('funcionario.solicitudes-reemplazo.store'), $this->payload($tipo))
            ->assertSessionHasErrors(['tipo_reemplazo' => 'El tipo de reemplazo seleccionado no es válido. Seleccione una opción de la lista.']);
        $this->assertDatabaseCount('solicitudes_reemplazo', 0);

        $solicitud = $this->existingRequest(TipoReemplazo::ENFERMEDAD_ACCIDENTE_COMUN);
        $this->put(route('funcionario.solicitudes-reemplazo.update', $solicitud), $this->payload($tipo, false))
            ->assertSessionHasErrors(['tipo_reemplazo' => 'El tipo de reemplazo seleccionado no es válido. Seleccione una opción de la lista.']);
        $this->assertSame(TipoReemplazo::ENFERMEDAD_ACCIDENTE_COMUN, $solicitud->fresh()->tipo_reemplazo);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public static function invalidOptions(): array
    {
        return [
            'fragmento mutualidad' => ['Reposo Mutualidad (ACHS'],
            'fragmento interno' => [' MUTUAL'],
            'opción inexistente' => ['Tipo inexistente'],
        ];
    }

    #[DataProvider('historicalOptions')]
    public function test_historical_license_names_are_still_normalized_on_creation(string $historical, string $expected): void
    {
        $this->post(route('funcionario.solicitudes-reemplazo.store'), $this->payload($historical))
            ->assertSessionHasNoErrors();
        $this->assertSame($expected, SolicitudReemplazo::sole()->tipo_reemplazo);
    }

    public static function historicalOptions(): array
    {
        return [
            'general' => [TipoReemplazo::LICENCIA_MEDICA_GENERAL_ANTERIOR, TipoReemplazo::ENFERMEDAD_ACCIDENTE_COMUN],
            'maternal' => [TipoReemplazo::LICENCIA_MEDICA_MATERNAL_ANTERIOR, TipoReemplazo::LICENCIA_MATERNAL],
        ];
    }

    #[DataProvider('allOptions')]
    public function test_each_catalog_option_can_be_created_or_returns_its_expected_restriction(string $tipo): void
    {
        $response = $this->post(route('funcionario.solicitudes-reemplazo.store'), $this->teacherPayload($tipo));
        if (in_array($tipo, TipoReemplazo::opcionesDeshabilitadasParaNuevasSolicitudes(), true)) {
            $response->assertSessionHasErrors(['tipo_reemplazo' => 'El tipo de reemplazo seleccionado no se encuentra disponible para nuevas solicitudes.']);
            $this->assertDatabaseCount('solicitudes_reemplazo', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
            Mail::assertNothingSent();

            return;
        }

        $response->assertSessionHasNoErrors()->assertRedirect(route('funcionario.solicitudes-reemplazo.index'));
        $solicitud = SolicitudReemplazo::sole();
        $this->assertSame($tipo, $solicitud->tipo_reemplazo);
        $this->assertSame(33.0, (float) $solicitud->jornadas->sum('reemplazo_total'));
        Storage::disk('local')->assertExists([
            $solicitud->oficio_pdf_path, $solicitud->respaldo_pdf_path, $solicitud->horario_titular_pdf_path,
        ]);
        Mail::assertSent(SolicitudReemplazoCreada::class);
    }

    #[DataProvider('allOptions')]
    public function test_each_catalog_option_remains_editable_in_an_existing_request(string $tipo): void
    {
        $payload = $this->teacherPayload($tipo, false);
        $solicitud = $this->existingRequest($tipo);
        $solicitud->update(['horario_titular_pdf_path' => 'historico/horario.pdf']);

        $this->put(route('funcionario.solicitudes-reemplazo.update', $solicitud), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('funcionario.solicitudes-reemplazo.index'));
        $this->assertSame($tipo, $solicitud->fresh()->tipo_reemplazo);
        $this->assertSame('historico/horario.pdf', $solicitud->fresh()->horario_titular_pdf_path);
    }

    public static function allOptions(): array
    {
        $cases = [];
        foreach (TipoReemplazo::opciones() as $tipo) {
            $cases[$tipo] = [$tipo];
        }

        return $cases;
    }

    public function test_the_teacher_scenario_saves_dates_hours_and_all_three_documents(): void
    {
        $this->post(route('funcionario.solicitudes-reemplazo.store'), $this->teacherPayload(TipoReemplazo::REPOSO_MUTUALIDAD))
            ->assertSessionHasNoErrors()->assertRedirect(route('funcionario.solicitudes-reemplazo.index'));
        $solicitud = SolicitudReemplazo::sole();
        $this->assertSame('2026-10-06', $solicitud->fecha_inicio->toDateString());
        $this->assertSame('2026-10-29', $solicitud->fecha_termino->toDateString());
        $this->assertFalse($solicitud->propone_reemplazo);
        $this->assertSame(33.0, $solicitud->horas_aula_cronologicas_titular);
        $this->assertSame(29.0, $solicitud->horas_aula_pedagogicas_titular);
        $this->assertSame(33.0, $solicitud->horas_aula_cronologicas_reemplazo);
        $this->assertSame(29.0, $solicitud->horas_aula_pedagogicas_reemplazo);
        $this->assertSame(40.0, (float) $solicitud->jornadas->sum('titular_total'));
        $this->assertSame(33.0, (float) $solicitud->jornadas->sum('reemplazo_total'));
        $this->assertCount(3, $solicitud->jornadas);
        $this->assertTrue($solicitud->declaracion_responsabilidad_aceptada);
        Storage::disk('local')->assertExists([
            $solicitud->oficio_pdf_path, $solicitud->respaldo_pdf_path, $solicitud->horario_titular_pdf_path,
        ]);
    }

    public function test_permiso_sin_goce_still_requires_gdp_authorization(): void
    {
        $payload = $this->teacherPayload('Permiso sin goce de sueldo');
        DB::table('permiso_sin_goce_excepciones')->update(['activo' => false]);
        $this->post(route('funcionario.solicitudes-reemplazo.store'), $payload)
            ->assertSessionHasErrors(['tipo_reemplazo' => 'Permiso sin goce de sueldo sólo está habilitado para titulares docentes autorizados por GDP.']);
        $this->assertDatabaseCount('solicitudes_reemplazo', 0);
    }

    public function test_teacher_requests_still_require_a_pdf_schedule(): void
    {
        $payload = $this->teacherPayload(TipoReemplazo::REPOSO_MUTUALIDAD);
        unset($payload['horario_titular_pdf']);
        $this->post(route('funcionario.solicitudes-reemplazo.store'), $payload)
            ->assertSessionHasErrors('horario_titular_pdf');
        $this->assertDatabaseCount('solicitudes_reemplazo', 0);
    }

    #[DataProvider('invalidDocuments')]
    public function test_each_document_still_requires_pdf_format_and_a_maximum_of_ten_mb(string $field, bool $oversized): void
    {
        $payload = $this->teacherPayload(TipoReemplazo::REPOSO_MUTUALIDAD);
        $payload[$field] = $oversized
            ? UploadedFile::fake()->create('documento.pdf', 10241, 'application/pdf')
            : UploadedFile::fake()->createWithContent('documento.txt', 'Archivo de texto de prueba');
        $this->post(route('funcionario.solicitudes-reemplazo.store'), $payload)->assertSessionHasErrors($field);
        $this->assertDatabaseCount('solicitudes_reemplazo', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        Mail::assertNothingSent();
    }

    public static function invalidDocuments(): array
    {
        $cases = [];
        foreach (['oficio_pdf', 'respaldo_pdf', 'horario_titular_pdf'] as $field) {
            $cases[$field.' formato inválido'] = [$field, false];
            $cases[$field.' supera 10 MB'] = [$field, true];
        }

        return $cases;
    }

    protected function teacherPayload(string $tipo, bool $files = true): array
    {
        $this->titular->update([
            'estatuto' => 'DOCENTE', 'financiamiento' => 'SUB.GENERAL', 'jornada' => 34, 'jornada_basica' => 34,
        ]);
        foreach (['PIE' => 2, 'SEP' => 4] as $financiamiento => $horas) {
            $line = $this->titular->replicate();
            $line->fill([
                'financiamiento' => $financiamiento, 'jornada' => $horas, 'jornada_basica' => $horas,
                'row_hash' => hash('sha256', 'titular-de-prueba-'.$financiamiento),
            ])->save();
        }
        $this->area->update(['estamento' => 'docente', 'nombre' => 'Educación Física y Salud']);
        if ($tipo === 'Permiso sin goce de sueldo') {
            DB::table('permiso_sin_goce_excepciones')->insert(['rut_normalizado' => '99000000K', 'activo' => true]);
        }
        $payload = array_merge($this->payload($tipo, $files), [
            'fecha_termino' => '29/10/2026', 'tipo_reemplazo_otro' => 'Caso de prueba',
            'horas_aula_cronologicas_titular' => 33, 'horas_aula_pedagogicas_titular' => 29,
            'horas_aula_cronologicas_reemplazo' => 33, 'horas_aula_pedagogicas_reemplazo' => 29,
            'jornadas' => [
                'SUB_GENERAL' => ['basica' => 33, 'media' => 0],
                'PIE' => ['basica' => 0, 'media' => 0],
                'SEP' => ['basica' => 0, 'media' => 0],
            ],
        ]);
        if ($files) {
            $payload['horario_titular_pdf'] = UploadedFile::fake()->createWithContent('horario.pdf', "%PDF-1.4\nDocumento de prueba\n%%EOF");
        }

        return $payload;
    }

    private function payload(string $tipo, bool $files = true): array
    {
        $payload = [
            'contacto_fono' => '000000000', 'reemplazo_personal_id' => $this->titular->id,
            'area_desempeno_id' => $this->area->id, 'tipo_reemplazo' => $tipo,
            'fecha_inicio' => '06/10/2026', 'fecha_termino' => '15/10/2026',
            'propone_reemplazo' => '0', 'declaracion_responsabilidad_aceptada' => '1',
            'jornadas' => ['SUBV_GRAL' => ['basica' => 44, 'media' => 0]],
        ];
        if ($files) {
            $payload['oficio_pdf'] = UploadedFile::fake()->createWithContent('oficio.pdf', "%PDF-1.4\nDocumento de prueba\n%%EOF");
            $payload['respaldo_pdf'] = UploadedFile::fake()->createWithContent('respaldo.pdf', "%PDF-1.4\nDocumento de prueba\n%%EOF");
        }
        return $payload;
    }

    private function existingRequest(string $tipo): SolicitudReemplazo
    {
        return SolicitudReemplazo::create([
            'establecimiento_id' => $this->titular->establecimiento_id,
            'reemplazo_personal_id' => $this->titular->id, 'area_desempeno_id' => $this->area->id,
            'anio' => 2026, 'correlativo' => 1, 'numero_solicitud' => '00001-2026',
            'contacto_nombre' => 'Contacto de prueba', 'contacto_fono' => '000000000',
            'contacto_email' => 'contacto@example.test', 'tipo_reemplazo' => $tipo,
            'fecha_inicio' => '2026-10-06', 'fecha_termino' => '2026-10-15',
            'estado' => 'pendiente_uatp', 'oficio_pdf_path' => 'historico/oficio.pdf',
            'respaldo_pdf_path' => 'historico/respaldo.pdf',
        ]);
    }
}
