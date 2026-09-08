<?php

namespace Tests\Feature;

use App\Http\Controllers\FuncionarioEstab\SolicitudReemplazoController;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronConsumidoresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->resetSchemaCaches();
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id');
            foreach (['rut', 'nombre', 'tipocontrato', 'financiamiento', 'estatuto', 'escalafon'] as $column) {
                $t->string($column)->nullable();
            }
            foreach (['rbd', 'anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media'] as $column) {
                $t->integer($column)->nullable();
            }
            $t->boolean('vigente')->default(true);
            $t->timestamps();
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->integer('rbd');
            $t->string('estamento'); $t->integer('horas_contratadas');
        });
        Schema::create('reemplazos_personal_bloqueos', function (Blueprint $t) {
            $t->id(); $t->integer('reemplazo_personal_id'); $t->boolean('activo');
        });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']);
        foreach ([
            [101, '111111111', 'CONTRATA', 'DOCENTE', true, 9],
            [102, '222222222', 'REEMPLAZO', 'DOCENTE', true, 9],
            [103, '333333333', 'SUPLENCIA', 'ASISTENTE EDUCACION', true, 9],
            [104, '444444444', 'TITULAR', 'DOCENTE', false, 9],
            [105, '555555555', 'CONTRATA', 'ASISTENTE EDUCACION', true, 9],
            [106, '222222222', 'TITULAR', 'DOCENTE', true, 8],
        ] as [$id, $rut, $tipo, $estatuto, $vigente, $mes]) {
            DB::table('reemplazos_personal')->insert([
                'id' => $id, 'rut' => $rut, 'establecimiento_id' => 1, 'rbd' => 99999,
                'nombre' => 'Persona sintética '.$id, 'tipocontrato' => $tipo,
                'estatuto' => $estatuto, 'escalafon' => $estatuto, 'financiamiento' => 'REGULAR',
                'vigente' => $vigente, 'mes' => $mes, 'anio' => 2026,
                'jornada' => 44, 'jornada_basica' => 44, 'jornada_media' => 0,
            ]);
        }
        DB::table('declaracion_sostenedores')->insert([
            'rut' => '111111111', 'rbd' => 99999, 'estamento' => 'DOCENTE', 'horas_contratadas' => 40,
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetSchemaCaches();
        parent::tearDown();
    }

    private function resetSchemaCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) {
                (new \ReflectionProperty($class, $property))->setValue(null, []);
            }
        }
    }

    private function loginAtEstablishment(): void
    {
        $user = new User;
        $user->id = 1;
        $user->setRelation('establecimiento', Establecimiento::findOrFail(1));
        $this->actingAs($user);
    }

    public function test_dotacion_filters_contracts_without_changing_declaration_priority(): void
    {
        $establishment = Establecimiento::findOrFail(1);
        $teachers = DotacionEstablecimientoCalculator::docentes($establishment, 2026);
        $assistants = DotacionEstablecimientoCalculator::asistentes($establishment, 2026);
        $this->assertSame(['111111111'], $teachers->pluck('rut_normalizado')->all());
        $this->assertSame(40.0, $teachers->first()['horas_contrato']);
        $this->assertSame('declaracion_sostenedor', $teachers->first()['fuente_contrato']);
        $this->assertSame(['555555555'], $assistants->pluck('rut_normalizado')->all());
        $this->assertSame(44.0, $assistants->first()['horas_contrato']);
        $this->assertDatabaseCount('reemplazos_personal', 6);
    }

    public function test_titular_selector_excludes_docente_and_asistente_replacements_and_old_periods(): void
    {
        $this->loginAtEstablishment();
        $response = app(SolicitudReemplazoController::class)->ajaxFuncionarios(Request::create('/', 'GET'));
        $this->assertSame([101, 105], array_column($response->getData(true)['results'], 'id'));
        $response = app(SolicitudReemplazoController::class)->ajaxFuncionarios(Request::create('/', 'GET', ['term' => '22.222.222-2']));
        $this->assertSame([], $response->getData(true)['results']);
    }

    public function test_direct_request_cannot_use_replacement_inactive_or_old_titular(): void
    {
        $this->loginAtEstablishment();
        foreach ([102, 103, 104, 106] as $id) {
            try {
                app(SolicitudReemplazoController::class)->store(Request::create('/', 'POST', ['reemplazo_personal_id' => $id]));
                $this->fail('Debe rechazar al titular no seleccionable.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('No se admiten contratos de reemplazo o suplencia', $exception->errors()['reemplazo_personal_id'][0]);
            }
        }
    }

    public function test_historical_relationship_and_schedule_still_read_original_titular(): void
    {
        $request = new SolicitudReemplazo(['reemplazo_personal_id' => 106]);
        $this->assertSame(106, $request->funcionarioTitular->id);
        $this->assertSame(8, $request->funcionarioTitular->mes);
        $method = new \ReflectionMethod(SolicitudReemplazoController::class, 'distribucionJornadaTitular');
        $schedule = $method->invoke(app(SolicitudReemplazoController::class), 1, ReemplazoPersonal::findOrFail(106));
        $this->assertSame(44.0, $schedule[0]['total']);
        $this->assertDatabaseCount('reemplazos_personal', 6);
    }

    private function historicalSolicitud(): SolicitudReemplazo
    {
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) {
            $t->id();
            foreach ((new SolicitudReemplazo)->getFillable() as $column) {
                if (str_ends_with($column, '_id')) {
                    $t->integer($column)->nullable();
                } else {
                    $t->text($column)->nullable();
                }
            }
            $t->timestamps();
        });
        Schema::create('solicitud_reemplazo_jornadas', function (Blueprint $t) {
            $t->id(); $t->integer('solicitud_reemplazo_id'); $t->string('financiamiento');
            foreach (['titular_basica', 'titular_media', 'titular_total', 'reemplazo_basica', 'reemplazo_media', 'reemplazo_total'] as $column) {
                $t->decimal($column, 8, 2)->default(0);
            }
            $t->timestamps();
        });
        Schema::create('permiso_sin_goce_excepciones', function (Blueprint $t) {
            $t->id(); $t->string('rut_normalizado'); $t->boolean('activo');
        });
        Schema::create('areas_desempeno', function (Blueprint $t) {
            $t->id(); $t->string('nombre'); $t->string('estamento'); $t->string('slug');
        });
        DB::table('areas_desempeno')->insert(['id' => 1, 'nombre' => 'Área sintética', 'estamento' => 'docente', 'slug' => 'prueba']);
        (require base_path('database/migrations/2026_09_08_150000_add_padron_snapshot_to_documentos.php'))->up();
        $solicitud = SolicitudReemplazo::create([
            'establecimiento_id' => 1, 'reemplazo_personal_id' => 101, 'area_desempeno_id' => 1,
            'estado' => 'pendiente_uatp', 'tipo_reemplazo' => 'Licencia Médica (General)',
            'horario_titular_pdf_path' => 'sintetico-no-se-lee.pdf',
        ]);
        foreach (['REGULAR' => 30, 'PIE' => 14] as $fin => $hours) {
            $solicitud->jornadas()->create([
                'financiamiento' => $fin, 'titular_basica' => $hours, 'titular_media' => 0, 'titular_total' => $hours,
                'reemplazo_basica' => $hours, 'reemplazo_media' => 0, 'reemplazo_total' => $hours,
            ]);
        }
        DB::table('reemplazos_personal')->where('id', 101)->update([
            'establecimiento_id' => 2, 'rbd' => 99998, 'jornada' => 20, 'jornada_basica' => 20,
            'nombre' => 'Nombre cambiado sintético', 'estatuto' => 'ASISTENTE EDUCACION', 'vigente' => false,
        ]);
        $this->loginAtEstablishment();
        return $solicitud;
    }

    private function editRequest(array $overrides = []): Request
    {
        $request = Request::create('/solicitud/prueba', 'PUT', array_replace([
            'reemplazo_personal_id' => 101, 'contacto_fono' => '000000000', 'area_desempeno_id' => 1,
            'tipo_reemplazo' => 'Licencia Médica (General)', 'fecha_inicio' => '01/09/2026', 'fecha_termino' => '10/09/2026',
            'propone_reemplazo' => '0', 'declaracion_responsabilidad_aceptada' => '1', 'action' => 'guardar',
            'horas_aula_cronologicas_titular' => 0, 'horas_aula_pedagogicas_titular' => 0,
            'horas_aula_cronologicas_reemplazo' => 0, 'horas_aula_pedagogicas_reemplazo' => 0,
            'jornadas' => ['REGULAR' => ['basica' => 30, 'media' => 0], 'PIE' => ['basica' => 14, 'media' => 0]],
        ], $overrides));
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        return $request;
    }

    public function test_edit_keeps_historical_contract_and_financing_after_transfer(): void
    {
        $solicitud = $this->historicalSolicitud();
        $copy = $solicitud->padron_personal_snapshot;
        $response = app(SolicitudReemplazoController::class)->update($this->editRequest(), $solicitud);
        $this->assertTrue($response->isRedirect());
        $this->assertNull(session('errors'));
        $this->assertSame($copy, $solicitud->fresh()->padron_personal_snapshot);
        $this->assertSame(101, $solicitud->fresh()->reemplazo_personal_id);
        $this->assertSame(44.0, (float) $solicitud->jornadas()->sum('titular_total'));
        $this->assertSame(44.0, (float) $solicitud->jornadas()->sum('reemplazo_total'));
        $this->assertDatabaseHas('solicitud_reemplazo_jornadas', ['financiamiento' => 'PIE', 'titular_total' => 14]);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'establecimiento_id' => 2, 'jornada' => 20]);
        $this->assertDatabaseCount('reemplazos_personal', 6);
    }

    public function test_edit_rejects_hours_over_historical_financing_limit(): void
    {
        $solicitud = $this->historicalSolicitud();
        app(SolicitudReemplazoController::class)->update($this->editRequest([
            'jornadas' => ['REGULAR' => ['basica' => 31, 'media' => 0], 'PIE' => ['basica' => 13, 'media' => 0]],
        ]), $solicitud);
        $this->assertStringContainsString('no puede exceder 30', session('errors')->first('jornadas'));
        $this->assertSame(44.0, (float) $solicitud->jornadas()->sum('reemplazo_total'));
    }

    public function test_switching_to_valid_titular_uses_new_schedule_and_preserves_previous_copy(): void
    {
        $solicitud = $this->historicalSolicitud();
        $previous = $solicitud->padron_personal_snapshot;
        DB::table('areas_desempeno')->insert(['id' => 2, 'nombre' => 'Área asistente sintética', 'estamento' => 'asistente', 'slug' => 'asistente_prueba']);
        app(SolicitudReemplazoController::class)->update($this->editRequest([
            'reemplazo_personal_id' => 105, 'area_desempeno_id' => 2,
            'jornadas' => ['REGULAR' => ['basica' => 44, 'media' => 0]],
        ]), $solicitud);
        $this->assertNull(session('errors'));
        $this->assertSame(105, $solicitud->fresh()->funcionarioTitular->id);
        $this->assertSame($previous, $solicitud->fresh()->padron_personal_snapshot['anteriores'][0]);
        $this->assertSame(1, $solicitud->jornadas()->count());
        $this->assertSame(44.0, (float) $solicitud->jornadas()->first()->titular_total);
    }

    public function test_ajax_detail_and_rule_use_historical_titular_but_cannot_select_it_for_new_request(): void
    {
        $solicitud = $this->historicalSolicitud();
        $request = Request::create('/', 'GET', ['solicitud_id' => $solicitud->id]);
        $this->app->instance('request', $request);
        $controller = app(SolicitudReemplazoController::class);
        $data = $controller->ajaxFuncionarioDetalle(ReemplazoPersonal::findOrFail(101))->getData(true);
        $this->assertSame('DOCENTE', $data['funcionario']['estatuto']);
        $this->assertSame('Persona sintética 101', $data['funcionario']['nombre_full']);
        $this->assertSame(44, $data['totales']['total']);
        $this->assertSame(14, $data['distribucion'][1]['total']);
        $request = Request::create('/', 'GET', [
            'solicitud_id' => $solicitud->id, 'reemplazo_personal_id' => 101,
            'fecha_inicio' => '01/09/2026', 'fecha_termino' => '07/09/2026',
        ]);
        $rule = $controller->ajaxReglaMinima($request)->getData(true);
        $this->assertFalse($rule['permitido']); // Docente histórico exige 8 días; asistente actual permite 7.
        $this->assertSame(\App\Support\ReemplazoSolicitudReglaMinima::REGLA_DOCENTE, $rule['regla_minima_aplicada']);
        $this->expectException(ValidationException::class);
        $controller->store(Request::create('/', 'POST', ['reemplazo_personal_id' => 101]));
    }

    public function test_foreign_solicitud_context_cannot_expose_another_establishment_history(): void
    {
        $solicitud = $this->historicalSolicitud();
        DB::table('solicitudes_reemplazo')->where('id', $solicitud->id)->update(['establecimiento_id' => 2]);
        $this->app->instance('request', Request::create('/', 'GET', ['solicitud_id' => $solicitud->id]));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(SolicitudReemplazoController::class)->ajaxFuncionarioDetalle(ReemplazoPersonal::findOrFail(101));
    }

    public function test_changing_titular_still_rejects_replacements_supplies_and_inactive_contracts(): void
    {
        $solicitud = $this->historicalSolicitud();
        foreach ([102, 103, 104, 106] as $id) {
            try {
                app(SolicitudReemplazoController::class)->update($this->editRequest(['reemplazo_personal_id' => $id]), $solicitud);
                $this->fail('Cambiar titular no permite omitir vigencia ni tipo contractual.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('reemplazo_personal_id', $exception->errors());
            }
        }
        $this->assertSame(101, $solicitud->fresh()->reemplazo_personal_id);
    }

    public function test_missing_historical_schedule_does_not_fall_back_to_current_roster(): void
    {
        $solicitud = $this->historicalSolicitud();
        $solicitud->jornadas()->delete(); // Solo datos sintéticos en SQLite :memory:.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no tiene jornadas históricas');
        app(SolicitudReemplazoController::class)->update($this->editRequest(), $solicitud);
    }
}
