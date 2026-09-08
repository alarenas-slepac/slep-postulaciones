<?php

namespace Tests\Feature;

use App\Http\Controllers\Reemplazos\PersonalImportController;
use App\Models\PadronRevision;
use App\Services\Padron\PadronExcelReader;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PadronRevisionTest extends TestCase
{
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id')->nullable();
            foreach (['rut', 'nombre', 'tipocontrato', 'financiamiento', 'estatuto', 'escalafon'] as $column) {
                $t->string($column)->nullable();
            }
            foreach (['rbd', 'anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media', 'bienios'] as $column) {
                $t->integer($column)->nullable();
            }
            foreach (['fecha_nacimiento', 'fecha_ingreso', 'fecha_termino'] as $column) {
                $t->date($column)->nullable();
            }
            $t->boolean('vigente')->default(true);
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->integer('establecimiento_id');
            $t->foreignId('reemplazos_personal_id')->nullable()->constrained('reemplazos_personal');
            foreach (['docente_rut', 'docente_rut_normalizado', 'tipo_asignacion', 'asignatura_nombre', 'estado'] as $column) {
                $t->string($column)->nullable();
            }
            $t->integer('horas_contrato');
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->integer('horas_contratadas');
        });
        (require base_path('database/migrations/2026_09_08_120000_create_padron_revisiones.php'))->up();
        DB::table('establecimientos')->insert([['id' => 1, 'rbd' => 99999], ['id' => 2, 'rbd' => 99998]]);
        DB::table('reemplazos_personal')->insert(['id' => 101, 'establecimiento_id' => 1] + $this->data(['mes' => 8]));
        DB::table('dotacion_docente_asignaciones')->insert([
            'id' => 501, 'anio' => 2026, 'establecimiento_id' => 1, 'reemplazos_personal_id' => 101,
            'docente_rut' => '11111111-1', 'estado' => 'activa', 'horas_contrato' => 20,
        ]);
        DB::table('declaracion_sostenedores')->insert(['rut' => '11111111-1', 'horas_contratadas' => 40]);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function data(array $changes = []): array
    {
        return array_replace([
            'rut' => '111111111', 'nombre' => 'Persona sintética', 'fecha_nacimiento' => '1980-01-01',
            'fecha_ingreso' => '2020-03-01', 'fecha_termino' => null, 'tipocontrato' => 'CONTRATA',
            'financiamiento' => 'REGULAR', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA',
            'anio' => 2026, 'mes' => 9, 'jornada' => 44, 'jornada_basica' => 44,
            'jornada_media' => 0, 'rbd' => 99999, 'bienios' => 3, 'fecha_antiguedad' => '2010-01-01',
        ], $changes);
    }

    private function excel(array $rows): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray(array_keys($rows[0]), null, 'A1');
        $sheet->fromArray(array_map('array_values', $rows), null, 'A2');
        $file = tempnam(sys_get_temp_dir(), 'padron_test_');
        $this->files[] = $file;
        (new Xlsx($book))->save($file);
        $book->disconnectWorksheets();
        return $file;
    }

    private function revision(array $rows = []): PadronRevision
    {
        return app(PadronRevisionService::class)->create($this->excel($rows ?: [$this->data()]), 'padron-sintetico.xlsx', 1);
    }

    public function test_preview_and_authorization_never_modify_existing_personnel_or_assignments(): void
    {
        $before = DB::table('reemplazos_personal')->get()->toJson();
        $assignments = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        $revision = $this->revision([$this->data(['jornada' => 45, 'fecha_antiguedad' => '2011-01-01'])]);
        $this->assertSame(101, $revision->filas->first()->personal_id);
        $this->assertSame('2011-01-01', $revision->filas->first()->datos['fecha_antiguedad']);
        $this->assertSame(501, $revision->filas->first()->asignaciones[0]['id']);
        app(PadronRevisionService::class)->authorize($revision, '111111111', 'Excepción sintética justificada para prueba.', 1);
        $this->assertDatabaseHas('padron_revision_autorizaciones', ['jornada_total' => 45, 'autorizado_por' => 1, 'padron_revision_id' => $revision->id]);
        $this->assertSame($before, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertSame($assignments, DB::table('dotacion_docente_asignaciones')->get()->toJson());
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
    }

    public function test_reactivation_finds_historical_id_missing_from_latest_establishment_month(): void
    {
        DB::table('reemplazos_personal')->insert(['id' => 102, 'establecimiento_id' => 1] + $this->data(['rut' => '222222222']));
        $revision = $this->revision();
        $row = $revision->filas->firstWhere('fila_excel', 2);
        $this->assertSame(101, $row->personal_id);
        $this->assertSame('reactivacion_propuesta', $row->accion);
    }

    public function test_stale_base_rejects_authorization(): void
    {
        $revision = $this->revision([$this->data(['jornada' => 45])]);
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['horas_contrato' => 21]);
        $this->assertTrue(app(PadronRevisionService::class)->stale($revision));
        $this->expectException(ValidationException::class);
        app(PadronRevisionService::class)->authorize($revision, '111111111', 'Excepción sintética justificada.', 1);
    }

    public function test_authorization_requires_reason_and_cannot_be_overwritten(): void
    {
        $revision = $this->revision([$this->data(['jornada' => 45])]);
        $service = app(PadronRevisionService::class);
        try {
            $service->authorize($revision, '111111111', ' ', 1);
            $this->fail('Debe exigir justificación.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('justificacion', $e->errors());
        }
        $service->authorize($revision, '111111111', 'Justificación original sintética.', 1);
        try {
            $service->authorize($revision, '111111111', 'Segunda justificación sintética.', 2);
            $this->fail('No debe sobrescribir.');
        } catch (ValidationException $e) {
            $this->assertDatabaseHas('padron_revision_autorizaciones', ['autorizado_por' => 1, 'justificacion' => 'Justificación original sintética.']);
            $this->assertSame(1, DB::table('padron_revision_autorizaciones')->count());
        }
    }

    public function test_historical_load_cannot_propose_deactivation_or_authorize(): void
    {
        $revision = $this->revision([$this->data(['mes' => 7, 'jornada' => 45])]);
        $this->assertNotEmpty($revision->errores);
        $this->assertFalse($revision->filas->contains('accion', 'baja_propuesta'));
        $this->expectException(ValidationException::class);
        app(PadronRevisionService::class)->authorize($revision, '111111111', 'Justificación sintética histórica.', 1);
    }

    public function test_reader_supports_old_template_and_rejects_missing_headers(): void
    {
        $old = $this->data(); unset($old['fecha_antiguedad']);
        $rows = (new PadronExcelReader)->read($this->excel([$old]));
        $this->assertSame([], $rows[0]['observaciones']);
        $this->assertArrayNotHasKey('fecha_antiguedad', $rows[0]['datos']);
        unset($old['rut']);
        $this->expectException(ValidationException::class);
        (new PadronExcelReader)->read($this->excel([$old]));
    }

    public function test_reader_handles_chunk_boundary_and_excel_dates(): void
    {
        $rows = array_fill(0, 501, $this->data(['fecha_antiguedad' => 43831]));
        $read = (new PadronExcelReader)->read($this->excel($rows));
        $this->assertCount(501, $read);
        $this->assertSame(502, $read[500]['fila_excel']);
        $this->assertSame('2020-01-01', $read[500]['datos']['fecha_antiguedad']);
    }

    public function test_preview_view_renders_without_blade_or_missing_route_errors(): void
    {
        $revision = $this->revision([$this->data(['jornada' => 45])]);
        view()->share('errors', new ViewErrorBag);
        $view = app(PersonalImportController::class)->create(Request::create('/', 'GET', ['revision' => $revision->id]));
        // Render the complete view, including the application layout.
        $html = $view->render();
        $this->assertStringContainsString('Solo previsualización.', $html);
        $this->assertStringContainsString('Registrar autorización de 45 horas', $html);
        $this->assertStringContainsString('ID: 101', $html);
        $this->assertStringNotContainsString('importar_legacy', $html);
    }

    public function test_old_post_cannot_bypass_preview_and_admin_middleware_is_preserved(): void
    {
        $controller = app(PersonalImportController::class);
        $this->assertContains('ensure.role:admin', array_column($controller->getMiddleware(), 'middleware'));
        $this->expectException(ValidationException::class);
        $controller->store(Request::create('/', 'POST', []));
    }

    public function test_upload_endpoint_requires_full_roster_confirmation_then_redirects_to_preview(): void
    {
        $this->withoutMiddleware();
        $user = new \App\Models\User;
        $user->id = 1;
        $this->actingAs($user);
        $before = DB::table('reemplazos_personal')->get()->toJson();
        $path = $this->excel([$this->data()]);
        $this->post(route('reemplazos.personal.import.store'), [
            'accion' => 'previsualizar', 'excel' => new UploadedFile($path, 'sintetico.xlsx', null, null, true),
        ])->assertSessionHasErrors('padron_completo');
        $this->assertSame(0, PadronRevision::count());
        $this->post(route('reemplazos.personal.import.store'), [
            'accion' => 'previsualizar', 'padron_completo' => 1,
            'excel' => new UploadedFile($path, 'sintetico.xlsx', null, null, true),
        ])->assertRedirect(route('reemplazos.personal.import', ['revision' => PadronRevision::firstOrFail()->id]));
        $this->assertSame($before, DB::table('reemplazos_personal')->get()->toJson());
    }

    public function test_actual_excel_formulas_and_duplicate_normalized_headers_are_rejected(): void
    {
        $rows = (new PadronExcelReader)->read($this->excel([$this->data(['nombre' => '=1+1'])]));
        $this->assertContains('No se aceptan fórmulas: nombre.', $rows[0]['observaciones']);
        $this->expectException(ValidationException::class);
        (new PadronExcelReader)->read($this->excel([$this->data(['tramo' => 'Inicial', 'TRAMO DOCENTE' => 'Avanzado'])]));
    }

    public function test_generated_template_contains_seniority_header(): void
    {
        $response = app(PersonalImportController::class)->plantilla();
        $this->files[] = $response->getFile()->getPathname();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($response->getFile()->getPathname());
        $this->assertSame('fecha_antiguedad', $book->getSheet(0)->getCell('R1')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_non_admin_cannot_reach_controller_through_role_middleware(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => new class {
            public function hasAnyRole($roles): bool { return false; }
        });
        try {
            (new \App\Http\Middleware\EnsureRole)->handle($request, fn () => response('No debe ejecutar'), 'admin');
            $this->fail('Debe rechazar usuarios sin rol admin.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_installed_application_tables_do_not_enable_personnel_writes(): void
    {
        (require base_path('database/migrations/2026_09_08_130000_add_padron_aplicacion_segura.php'))->up();
        $revision = $this->revision();
        $before = DB::table('reemplazos_personal')->get()->toJson();
        $assignments = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        $service = app(\App\Services\Padron\PadronAplicacionService::class);
        $this->assertFalse($service->disponible());

        // El bloqueo se aplica también al servicio, no solo al botón.
        foreach (['aplicar', 'resolver'] as $action) {
            try {
                if ($action === 'aplicar') {
                    $service->aplicar($revision, 1);
                } else {
                    $service->resolver($revision, $revision->filas->first()->id, 101, 'Justificación sintética de prueba.', 1);
                }
                $this->fail('No debe habilitar escrituras en esta entrega.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('revision', $exception->errors());
            }
        }

        $this->withoutMiddleware();
        $user = new \App\Models\User;
        $user->id = 1;
        $this->actingAs($user);
        foreach (['aplicar', 'resolver'] as $action) {
            $this->post(route('reemplazos.personal.import.store'), [
                'accion' => $action, 'revision' => $revision->id,
                'confirmar_aplicacion' => 1, 'fila' => $revision->filas->first()->id,
                'personal_id' => 101, 'justificacion' => 'Justificación sintética de prueba.',
            ])->assertSessionHasErrors('revision');
        }

        app('auth')->forgetGuards();
        view()->share('errors', new ViewErrorBag);
        $html = app(PersonalImportController::class)->create(Request::create('/', 'GET', ['revision' => $revision->id]))->render();
        $this->assertStringContainsString('No habilitada en esta etapa.', $html);
        $this->assertStringNotContainsString('name="accion" value="aplicar"', $html);
        $this->assertStringNotContainsString('name="accion" value="resolver"', $html);
        $this->assertSame($before, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertSame($assignments, DB::table('dotacion_docente_asignaciones')->get()->toJson());
        $this->assertDatabaseCount('padron_personal_cambios', 0);
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
        $this->assertNull($revision->fresh()->aplicada_at);
    }

    public function test_authorization_reloads_persisted_revision_under_lock(): void
    {
        (require base_path('database/migrations/2026_09_08_130000_add_padron_aplicacion_segura.php'))->up();
        $revision = $this->revision([$this->data(['jornada' => 45])]);
        DB::table('padron_revisiones')->where('id', $revision->id)->update(['aplicada_at' => now()]);
        $this->assertNull($revision->aplicada_at); // Instancia anterior al cierre.
        try {
            app(PadronRevisionService::class)->authorize($revision, '111111111', 'Justificación sintética de prueba.', 1);
            $this->fail('No debe autorizar revisiones cerradas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('revision', $exception->errors());
        }
        $this->assertDatabaseCount('padron_revision_autorizaciones', 0);
    }

    public function test_explicit_scopes_exclude_replacements_and_supplies_but_preserve_historical_reads(): void
    {
        $records = [
            ['id' => 102, 'tipocontrato' => 'REEMPLAZO'],
            ['id' => 103, 'tipocontrato' => 'Suplencia', 'estatuto' => 'ASISTENTE EDUCACION'],
            ['id' => 104, 'tipocontrato' => 'TITULAR', 'vigente' => false],
            ['id' => 105, 'tipocontrato' => 'CONTRATA', 'estatuto' => 'ASISTENTE EDUCACION'],
        ];
        foreach ($records as $record) {
            DB::table('reemplazos_personal')->insert(array_replace(
                $this->data(['mes' => 8]), ['establecimiento_id' => 1], $record,
            ));
        }
        // Otro establecimiento con un período más reciente no elimina al primero.
        DB::table('reemplazos_personal')->insert(['id' => 106, 'establecimiento_id' => 2] + $this->data(['rbd' => 99998]));
        $this->assertSame([101, 105, 106], \App\Models\ReemplazoPersonal::query()
            ->padronVigente()->sinReemplazoSuplencia()->orderBy('id')->pluck('id')->all());
        $this->assertSame(6, \App\Models\ReemplazoPersonal::count());
        $this->assertNotNull(\App\Models\ReemplazoPersonal::find(103));
        $this->assertNotNull(\App\Models\ReemplazoPersonal::find(104));

        // Una versión vieja regular no reaparece al excluir un reemplazo reciente.
        DB::table('reemplazos_personal')->insert(['id' => 107, 'establecimiento_id' => 1] + $this->data(['tipocontrato' => 'REEMPLAZO']));
        $this->assertSame([106], \App\Models\ReemplazoPersonal::query()
            ->padronVigente(2026)->sinReemplazoSuplencia()->orderBy('id')->pluck('id')->all());
    }
}
