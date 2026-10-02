<?php

namespace Tests\Feature;

use App\Http\Controllers\Gestion\SolicitudReemplazoGestionController;
use App\Models\SolicitudReemplazo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiniquitosRendimientoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Carbon::setTestNow('2026-10-02 09:00:00');

        Schema::create('solicitudes_reemplazo', function (Blueprint $table) {
            $table->id();
            foreach ([
                'establecimiento_id', 'reemplazo_personal_id', 'postulant_profile_id',
                'contrato_trabajo_postulant_profile_id', 'solicitud_anterior_id',
                'numero_solicitud', 'estado', 'tipo_reemplazo', 'aaee_categoria',
                'fecha_inicio_trabajo', 'fecha_termino', 'rut_titular_normalizado',
                'rut_reemplazo_normalizado', 'finiquito_estado', 'finiquito_monto',
                'finiquito_fecha_emision', 'finiquito_observacion', 'finiquito_pdf_path',
                'finiquito_generado_por_user_id', 'finiquito_generado_at',
                'finiquito_firmado_pdf_path', 'finiquito_firmado_observacion',
                'finiquito_firmado_cargado_por_user_id', 'finiquito_firmado_cargado_at',
                'horas_aula_cronologicas_reemplazo', 'horas_aula_pedagogicas_reemplazo',
                'padron_personal_snapshot',
                'finiquito_firmante_nombre', 'finiquito_firmante_rut',
                'finiquito_firmante_cargo', 'finiquito_firmante_es_subrogante',
                'finiquito_firmado_nombre_original', 'finiquito_firmado_mime',
                'finiquito_firmado_size',
            ] as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('reemplazos_personal', function (Blueprint $table) {
            $table->id(); $table->string('rut'); $table->string('nombre'); $table->string('estatuto');
        });
        Schema::create('postulant_profiles', function (Blueprint $table) {
            $table->id(); $table->integer('user_id');
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('rut'); $table->string('nombres')->nullable();
            $table->string('apellido_paterno')->nullable(); $table->string('apellido_materno')->nullable();
            $table->string('email')->nullable(); $table->softDeletes();
        });
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id(); $table->integer('rbd'); $table->string('nombre_establecimiento');
            $table->string('comuna'); $table->boolean('sala_cuna');
        });
        Schema::create('solicitud_reemplazo_jornadas', function (Blueprint $table) {
            $table->id(); $table->integer('solicitud_reemplazo_id');
            $table->integer('reemplazo_basica')->nullable(); $table->integer('reemplazo_media')->nullable();
            $table->integer('reemplazo_total')->nullable();
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento de prueba', 'comuna' => 'Comuna de prueba', 'sala_cuna' => false],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Sala cuna de prueba', 'comuna' => 'Comuna de prueba', 'sala_cuna' => true],
        ]);

        foreach (range(1, 38) as $person) {
            DB::table('users')->insert(['id' => $person, 'rut' => '900000'.$person.'K', 'nombres' => 'Reemplazante de prueba']);
            DB::table('postulant_profiles')->insert(['id' => $person, 'user_id' => $person]);
            DB::table('reemplazos_personal')->insert([
                'id' => $person, 'rut' => '800000'.$person.'K', 'nombre' => 'Titular vigente de prueba', 'estatuto' => 'DOCENTE',
            ]);
            foreach ([['2026-06-01', '2026-06-30'], ['2026-07-01', '2026-07-31'], ['2026-08-01', '2026-08-31']] as $period => [$start, $end]) {
                $id = $person * 10 + $period + 1;
                DB::table('solicitudes_reemplazo')->insert([
                    'id' => $id, 'establecimiento_id' => $person > 32 && $person <= 35 ? 2 : 1,
                    'reemplazo_personal_id' => $person, 'postulant_profile_id' => $person,
                    'contrato_trabajo_postulant_profile_id' => $person,
                    'solicitud_anterior_id' => $period > 0 ? $id - 1 : null,
                    'numero_solicitud' => 'PRUEBA-'.$id, 'estado' => $period < 2 ? 'cerrado' : 'aceptada',
                    'fecha_inicio_trabajo' => $start, 'fecha_termino' => $end,
                    'rut_titular_normalizado' => '800000'.$person.'K',
                    'rut_reemplazo_normalizado' => '900000'.$person.'K',
                    'finiquito_estado' => $period === 2 && $person === 32 ? 'generado' : ($period === 2 && $person === 31 ? 'completado' : null),
                    'finiquito_generado_por_user_id' => 1, 'finiquito_firmado_cargado_por_user_id' => 1,
                    // El padrón vigente cambió; la categoría y el RUT del titular
                    // deben seguir saliendo del historial de cada solicitud.
                    'padron_personal_snapshot' => json_encode(['version' => 1, 'personal' => [
                        'id' => $person, 'rut' => '700000'.$person.'K', 'nombre' => 'Titular histórico de prueba',
                        'estatuto' => $person <= 32 ? 'ASISTENTE' : 'DOCENTE',
                    ]], JSON_THROW_ON_ERROR),
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_listing_batches_historical_and_signer_reads_and_keeps_pagination(): void
    {
        $queries = [];
        DB::listen(function ($event) use (&$queries) { $queries[] = $event->sql; });

        $data = $this->listing([]);
        $paginator = $data['finiquitos'];
        $this->assertSame('todos', $data['estadoFiniquito']);
        $this->assertSame(32, $paginator->total());
        $this->assertCount(25, $paginator->items());
        $this->assertSame(32, $data['resumenFiniquitos']['asistentes']);
        $this->assertSame(1, $data['resumenFiniquitos']['generados']);
        $this->assertSame(1, $data['resumenFiniquitos']['completados']);
        $last = $paginator->getCollection()->first();
        $this->assertSame(323, $last->id);
        $this->assertSame([321, 322, 323], $last->finiquito_cadena_ids);
        $this->assertSame('2026-06-01', $last->finiquito_periodo_inicio->format('Y-m-d'));
        $this->assertSame('2026-08-31', $last->finiquito_periodo_termino->format('Y-m-d'));
        $this->assertSame('Titular histórico de prueba', $last->funcionarioTitular->nombre);
        $this->assertTrue($last->relationLoaded('finiquitoGeneradoPor'));
        $this->assertTrue($last->relationLoaded('finiquitoFirmadoCargadoPor'));
        $this->assertLessThanOrEqual(25, count($queries), 'Las consultas no deben crecer por cada solicitud o comparación de continuidad.');
        $this->assertEmpty(array_filter($queries, fn ($sql) => str_starts_with($sql, 'select "padron_personal_snapshot"')));

        $second = $this->listing(['estado_finiquito' => 'todos', 'page' => 2])['finiquitos'];
        $this->assertSame(32, $second->total());
        $this->assertCount(7, $second->items());
        $this->assertSame(73, $second->getCollection()->first()->id);
    }

    public function test_future_continuities_and_category_and_state_filters_still_apply(): void
    {
        $previous = (array) DB::table('solicitudes_reemplazo')->where('id', 303)->first();
        DB::table('solicitudes_reemplazo')->insert(array_replace($previous, [
            'id' => 304, 'solicitud_anterior_id' => 303, 'fecha_inicio_trabajo' => '2026-09-01', 'fecha_termino' => '2026-10-15',
        ]));
        $this->assertSame(31, $this->listing([])['finiquitos']->total());
        $this->assertSame(29, $this->listing(['estado_finiquito' => 'pendientes'])['finiquitos']->total());
        $this->assertSame('todos', $this->listing(['estado_finiquito' => 'invalido'])['estadoFiniquito']);
        $this->assertSame(1, $this->listing(['estado_finiquito' => 'generados'])['finiquitos']->total());
        $this->assertSame(1, $this->listing(['estado_finiquito' => 'completados'])['finiquitos']->total());
        $this->assertSame(3, $this->listing(['categoria' => 'junji'])['finiquitos']->total());
        $this->assertSame(3, $this->listing(['categoria' => 'docentes'])['finiquitos']->total());
    }

    public function test_legacy_requests_and_deployments_without_snapshot_remain_supported(): void
    {
        DB::table('solicitudes_reemplazo')->update(['padron_personal_snapshot' => null, 'tipo_reemplazo' => 'ASISTENTE']);
        $this->assertSame(35, $this->listing([])['finiquitos']->total());
        Schema::table('solicitudes_reemplazo', fn (Blueprint $table) => $table->dropColumn('padron_personal_snapshot'));
        $this->assertSame(35, $this->listing([])['finiquitos']->total());
    }

    public function test_excel_export_keeps_historical_data_without_individual_snapshot_reads(): void
    {
        $queries = [];
        DB::listen(function ($event) use (&$queries) { $queries[] = $event->sql; });
        $request = Request::create('/gestion/solicitudes-reemplazo/finiquitos/exportar-excel');
        $request->setUserResolver(fn () => new class {
            public function hasAnyRole(array $roles): bool { return true; }
        });
        $response = app(SolicitudReemplazoGestionController::class)->exportarFiniquitosExcel($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Estado: todos', $response->getContent());
        $this->assertStringContainsString('Titular histórico de prueba', $response->getContent());
        $this->assertStringContainsString('01-06-2026', $response->getContent());
        $this->assertStringContainsString('PRUEBA-321, PRUEBA-322, PRUEBA-323', $response->getContent());
        $this->assertLessThanOrEqual(35, count($queries));
        $this->assertEmpty(array_filter($queries, fn ($sql) => str_starts_with($sql, 'select "padron_personal_snapshot"')));
    }

    public function test_calendar_continuity_handles_month_year_and_santiago_clock_changes(): void
    {
        $controller = app(SolicitudReemplazoGestionController::class);
        $method = new \ReflectionMethod($controller, 'fechasConectanContinuidad');
        foreach ([
            ['2026-08-31', '2026-09-01', true],
            ['2026-12-31', '2027-01-01', true],
            ['2024-02-28', '2024-02-29', true],
            ['2026-09-06', '2026-09-07', true],
            ['2026-04-04', '2026-04-05', true],
            ['2026-09-06', '2026-09-06', true],
            ['2026-09-06', '2026-09-08', false],
            ['2026-09-06', '2026-09-05', false],
        ] as [$end, $start, $expected]) {
            $end = Carbon::parse($end, 'America/Santiago');
            $start = Carbon::parse($start, 'America/Santiago');
            $this->assertSame($expected, $method->invoke($controller, $end, $start));
        }
        $this->assertFalse($method->invoke($controller, null, Carbon::now()));
    }

    public function test_document_actions_preserve_continuity_and_signed_file_lifecycle(): void
    {
        Storage::fake('public');
        $controller = app(SolicitudReemplazoGestionController::class);
        Schema::create('funcionarios_ac_autorizados', function (Blueprint $table) {
            $table->id();
            foreach (['run', 'dv', 'nombres', 'apellido_paterno', 'apellido_materno'] as $column) { $table->text($column)->nullable(); }
        });
        Schema::create('funcionarios_ac_jefaturas_dependencias', function (Blueprint $table) {
            $table->id(); $table->string('subdireccion_dependencia'); $table->boolean('activo'); $table->integer('jefatura_funcionario_ac_id');
        });
        DB::table('funcionarios_ac_autorizados')->insert(['id' => 1, 'run' => '99999999', 'dv' => 'K', 'nombres' => 'Director de prueba']);
        DB::table('funcionarios_ac_jefaturas_dependencias')->insert(['subdireccion_dependencia' => 'Direccion Ejecutiva', 'activo' => true, 'jefatura_funcionario_ac_id' => 1]);

        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) {
            $this->assertSame('pdf.finiquito-reemplazo', $view);
            $this->assertSame('2026-06-01', $data['periodoInicioFiniquito']->format('Y-m-d'));
            $this->assertSame('2026-08-31', $data['periodoTerminoFiniquito']->format('Y-m-d'));
            $this->assertSame([11, 12, 13], $data['solicitudesCadenaFiniquito']->pluck('id')->all());
            $this->assertSame('Titular histórico de prueba', $data['s']->funcionarioTitular->nombre);
            return true;
        })->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->andReturnSelf();
        Pdf::shouldReceive('output')->once()->andReturn("%PDF-1.4\nDocumento de prueba");

        $request = $this->documentRequest(['finiquito_fecha_emision' => '2026-10-02', 'finiquito_monto' => 123456, 'firmante_key' => 'director_1']);
        $response = $controller->generarFiniquitoPdf($request, SolicitudReemplazo::findOrFail(13));
        $this->assertSame(302, $response->getStatusCode());
        $solicitud = SolicitudReemplazo::findOrFail(13);
        $this->assertSame('generado', $solicitud->finiquito_estado);
        $this->assertSame(123456.0, (float) $solicitud->finiquito_monto);
        Storage::disk('public')->assertExists($solicitud->finiquito_pdf_path);
        $this->assertSame(200, $controller->descargarFiniquitoPdf($request, $solicitud)->getStatusCode());

        $request = $this->documentRequest();
        $request->files->set('finiquito_firmado_pdf', UploadedFile::fake()->create('firmado.pdf', 20, 'application/pdf'));
        $this->assertSame(302, $controller->cargarFiniquitoFirmado($request, $solicitud)->getStatusCode());
        $solicitud->refresh();
        $this->assertSame('completado', $solicitud->finiquito_estado);
        $signedPath = $solicitud->finiquito_firmado_pdf_path;
        Storage::disk('public')->assertExists($signedPath);
        $this->assertSame(200, $controller->descargarFiniquitoFirmado($request, $solicitud)->getStatusCode());
        $this->assertSame(302, $controller->eliminarFiniquitoFirmado($request, $solicitud)->getStatusCode());
        $solicitud->refresh();
        $this->assertSame('generado', $solicitud->finiquito_estado);
        $this->assertNull($solicitud->finiquito_firmado_pdf_path);
        Storage::disk('public')->assertMissing($signedPath);
        Storage::disk('public')->assertExists($solicitud->finiquito_pdf_path);
    }

    private function documentRequest(array $data = []): Request
    {
        $request = Request::create('/gestion/solicitudes-reemplazo/finiquitos', 'POST', $data);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => new class {
            public int $id = 1;
            public function hasAnyRole(array $roles): bool { return true; }
        });
        return $request;
    }

    private function listing(array $filters): array
    {
        $request = Request::create('/gestion/solicitudes-reemplazo/finiquitos', 'GET', $filters);
        $request->setUserResolver(fn () => new class {
            public function hasAnyRole(array $roles): bool { return true; }
        });

        return app(SolicitudReemplazoGestionController::class)->finiquitos($request)->getData();
    }
}
