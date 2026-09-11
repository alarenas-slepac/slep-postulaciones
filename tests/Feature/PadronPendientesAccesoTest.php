<?php

namespace Tests\Feature;

use App\Http\Controllers\Reemplazos\PersonalImportController;
use App\Models\PadronRevision;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronConflictosAsignacionService;
use App\Services\Padron\PadronDependenciasService;
use App\Services\Padron\PadronReemplazosVigentes;
use App\Services\Padron\PadronResolucionService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PadronPendientesAccesoTest extends TestCase
{
    private PadronRevision $revision;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->integer('anio')->default(2026);
        });
        foreach (['2026_09_08_120000_create_padron_revisiones', '2026_09_08_130000_add_padron_aplicacion_segura'] as $migration) {
            (require base_path('database/migrations/'.$migration.'.php'))->up();
        }
        $this->revision = PadronRevision::create(['created_by' => 1, 'archivo' => 'sintetico.xlsx',
            'archivo_hash' => str_repeat('a', 64), 'base_hash' => str_repeat('b', 64),
            'anio' => 2026, 'mes' => 8, 'resumen' => [], 'errores' => [], 'excesos' => []]);
        $this->mock(PadronRevisionService::class, function ($mock) {
            $mock->shouldReceive('assertInstalled')->andReturnNull();
            $mock->shouldReceive('stale')->andReturnFalse();
        });
        $this->mock(PadronDependenciasService::class, function ($mock) {
            $mock->shouldReceive('paraPersonal')->andReturn([]);
            $mock->shouldReceive('snapshot')->andReturn(['hash' => str_repeat('c', 64)]);
        });
        view()->share('errors', new ViewErrorBag);
    }

    private function fila(int $numero, array $changes = [])
    {
        return $this->revision->filas()->create(array_replace([
            'fila_excel' => $numero, 'rut' => '111111111', 'nombre' => 'Persona sintética',
            'accion' => 'revision_manual', 'personal_id' => null,
            'datos' => ['tipocontrato' => 'PLANTA', 'rut' => '111111111', 'anio' => 2026, 'mes' => 8,
                'jornada' => 1, 'fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2026-08-31'],
            'candidatos' => [], 'observaciones' => [], 'asignaciones' => [],
        ], $changes));
    }

    private function page(array $params = [])
    {
        $request = Request::create(route('reemplazos.personal.import'), 'GET', ['revision' => $this->revision->id] + $params);
        $request->setLaravelSession(session()->driver());
        $this->app->instance('request', $request);
        return app(PersonalImportController::class)->create($request);
    }

    private function mockPlan(array $errores = []): void
    {
        $this->mock(PadronAplicacionService::class, function ($mock) use ($errores) {
            $mock->shouldReceive('disponible')->andReturnFalse();
            $mock->shouldReceive('plan')->andReturn(['errores' => $errores, 'conflictos' => null]);
        });
    }

    public function test_pending_navigation_is_available_without_dotacion_conflicts_and_is_paginated(): void
    {
        foreach (range(1, 12) as $n) { $this->fila($n); }
        $this->mockPlan(['Fila 1: seleccione un ID candidato o confirme nueva línea.']);
        $view = $this->page();
        $data = $view->getData();
        $this->assertFalse($data['mostrarFilas']);
        $this->assertCount(10, $data['pendientesCorrespondencia']);
        $this->assertSame(12, $data['pendientesCorrespondencia']->total());
        $html = $view->render();
        $this->assertStringContainsString('Ver registro y resolver', $html);
        $this->assertStringContainsString('fila_revision=1', $data['enlacesBloqueos'][0]);
        $this->assertStringContainsString('pendientes_page=2', $html);
        $this->assertCount(2, $this->page(['pendientes_page' => 2])->getData()['pendientesCorrespondencia']);
        $this->assertSame(2, $this->page(['pendientes_page' => 99])->getData()['pendientesCorrespondencia']->currentPage());
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
    }

    public function test_direct_access_opens_the_target_page_and_same_rut_without_stale_filters_or_global_plan(): void
    {
        foreach (range(1, 51) as $n) { $last = $this->fila($n); }
        $this->fila(52, ['rut' => '222222222']);
        $this->mock(PadronAplicacionService::class)->shouldNotReceive('plan');
        $html = $this->page(['fila_revision' => $last->id, 'q' => 'incorrecto', 'accion_filtro' => 'error', 'solo_filas' => 1])->getContent();
        $this->assertStringContainsString('51 registros.', $html);
        $this->assertStringContainsString('name="fila" value="'.$last->id.'"', $html);
        $this->assertStringContainsString('name="page" value="2"', $html);
        $this->assertStringNotContainsString('222222222', $html);
    }

    public function test_direct_link_supports_absences_and_rejects_rows_from_another_review(): void
    {
        $fila = $this->fila(1, ['fila_excel' => null, 'accion' => 'ausencia_por_revisar', 'personal_id' => 101]);
        $this->mockPlan(['ID 101: confirme su baja.']);
        $this->assertStringContainsString('fila_revision='.$fila->id, $this->page()->getData()['enlacesBloqueos'][0]);
        $this->assertStringContainsString('Resolver ausencia', $this->page(['fila_revision' => $fila->id, 'solo_filas' => 1])->getContent());
        $other = $this->revision->replicate(); $other->save();
        $fila->update(['padron_revision_id' => $other->id]);
        $this->expectException(ModelNotFoundException::class);
        $this->page(['fila_revision' => $fila->id, 'solo_filas' => 1]);
    }

    public function test_resolving_one_row_keeps_other_pending_rows_accessible(): void
    {
        $first = $this->fila(1); $second = $this->fila(2);
        app(PadronResolucionService::class)->resolver($this->revision, $first->id, null, 'Nueva línea sintética verificada.', 1);
        $this->mockPlan();
        $pending = $this->page()->getData()['pendientesCorrespondencia'];
        $this->assertSame([$second->id], $pending->pluck('id')->all());
        $this->assertDatabaseCount('padron_revision_decisiones', 1);
    }

    public function test_pending_replacement_is_a_new_effective_line_without_changing_saved_review_or_decisions(): void
    {
        $fila = $this->fila(1);
        $fila->update(['datos' => array_replace($fila->datos, ['tipocontrato' => ' reemplazo '])]);
        $before = $fila->fresh()->toArray();
        $service = app(PadronResolucionService::class);
        $summary = $service->resumen(collect([$fila]), collect());
        $this->assertSame('nueva_linea_reemplazo', $summary['estados'][$fila->id]);
        $this->assertArrayHasKey($fila->id, $summary['selecciones']);
        $this->assertNull($summary['selecciones'][$fila->id]);
        $effective = $service->entrantes($this->revision, collect([$fila]), collect())->first();
        $this->assertSame('nueva_incorporacion', $effective->accion);
        $this->assertNull($effective->personal_id);
        $this->assertSame($before, $fila->fresh()->toArray());
        $this->mockPlan();
        $this->assertSame(0, $this->page()->getData()['pendientesCorrespondencia']->total());
        $html = $this->page(['fila_revision' => $fila->id, 'solo_filas' => 1])->getContent();
        $this->assertStringContainsString('ID nuevo', $html);
        $this->assertStringNotContainsString('data-padron-decision-form', $html);
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
    }

    public function test_automatic_rule_does_not_override_decisions_matches_errors_omissions_or_other_contract_types(): void
    {
        $service = app(PadronResolucionService::class);
        $fila = $this->fila(1);
        foreach (['PLANTA', 'SUPLENCIA', 'DESCONOCIDO'] as $tipo) {
            $fila->datos = ['tipocontrato' => $tipo];
            $this->assertFalse($service->nuevoReemplazoAutomatico($fila, collect()));
        }
        $fila->datos = ['tipocontrato' => 'REEMPLAZO'];
        foreach (['error', PadronReemplazosVigentes::OMITIDO, 'actualizacion_propuesta'] as $accion) {
            $fila->accion = $accion;
            $this->assertFalse($service->nuevoReemplazoAutomatico($fila, collect()));
        }
        $fila->accion = 'revision_manual';
        foreach ([101, null] as $seleccion) {
            $decisiones = collect([$fila->id => (object) ['personal_id' => $seleccion]]);
            $this->assertFalse($service->nuevoReemplazoAutomatico($fila, $decisiones));
            $this->assertSame($seleccion, $service->selecciones(collect([$fila]), $decisiones)[$fila->id]);
        }
    }

    public function test_real_plan_uses_new_replacement_without_waiving_pending_absence_or_hour_authorization(): void
    {
        $fila = $this->fila(1);
        $fila->update(['datos' => array_replace($fila->datos, ['tipocontrato' => 'REEMPLAZO'])]);
        $this->fila(2, ['fila_excel' => null, 'accion' => 'ausencia_por_revisar', 'personal_id' => 101]);
        $this->mock(PadronConflictosAsignacionService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn(['hash' => str_repeat('d', 64)]);
            $mock->shouldReceive('analizar')->andReturn(['errores' => []]);
        });
        $this->revision->update(['excesos' => ['111111111' => ['total' => 45]]]);
        $plan = app(PadronAplicacionService::class)->plan($this->revision);
        $this->assertCount(1, $plan['destinos']);
        $this->assertNull($plan['destinos'][0]['id']);
        $this->assertSame('nueva_incorporacion', $plan['destinos'][0]['fila']->accion);
        $this->assertCount(2, $plan['errores']);
        $this->assertStringStartsWith('ID 101:', $plan['errores'][0]);
        $this->assertStringContainsString('faltan autorización', $plan['errores'][1]);
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
        $this->assertDatabaseCount('reemplazos_personal', 0);
    }

    public function test_writer_creates_replacement_with_new_id_preserves_old_links_and_is_idempotent(): void
    {
        Schema::table('reemplazos_personal', function (Blueprint $t) {
            foreach (['establecimiento_id', 'rbd', 'mes', 'jornada', 'jornada_basica', 'jornada_media'] as $c) { $t->integer($c)->nullable(); }
            foreach (['nombre', 'tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'source_filename'] as $c) { $t->string($c)->nullable(); }
            $t->date('fecha_ingreso')->nullable(); $t->date('fecha_termino')->nullable();
            $t->boolean('vigente')->default(true); $t->string('row_hash')->unique(); $t->integer('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('establecimientos', function (Blueprint $t) { $t->id(); $t->integer('rbd'); });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999]);
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) { $t->id(); $t->integer('reemplazo_personal_id'); });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) { $t->id(); $t->integer('reemplazos_personal_id'); });
        foreach (['2026_09_08_150000_add_padron_snapshot_to_documentos', '2026_09_08_160000_create_padron_periodo_versiones'] as $migration) {
            (require base_path('database/migrations/'.$migration.'.php'))->up();
        }
        $replacement = $this->fila(1);
        $replacement->update(['datos' => array_replace($replacement->datos, [
            'tipocontrato' => 'REEMPLAZO', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'estatuto' => 'AAEE', 'escalafon' => 'PROFESIONAL', 'financiamiento' => 'REGULAR', 'jornada' => 4,
        ])]);
        $regular = array_replace($replacement->datos, ['tipocontrato' => 'PLANTA', 'jornada' => 40]);
        DB::table('reemplazos_personal')->insert(array_replace($regular, [
            'id' => 101, 'establecimiento_id' => 1, 'row_hash' => 'sintetico-101', 'mes' => 7,
        ]));
        $this->fila(2, ['datos' => $regular, 'personal_id' => 101, 'accion' => 'actualizacion_propuesta']);
        DB::table('solicitudes_reemplazo')->insert(['id' => 1, 'reemplazo_personal_id' => 101]);
        DB::table('dotacion_docente_asignaciones')->insert(['id' => 1, 'reemplazos_personal_id' => 101]);
        $this->mock(PadronConflictosAsignacionService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn(['hash' => str_repeat('d', 64)]);
            $mock->shouldReceive('analizar')->andReturn(['errores' => []]);
        });
        $writer = new class(app(PadronRevisionService::class)) extends PadronAplicacionService {
            public function disponible(): bool { return app()->environment('testing') && DB::connection()->getDatabaseName() === ':memory:'; }
        };
        $plan = $writer->plan($this->revision);
        $this->assertSame([], $plan['errores']);
        $writer->aplicar($this->revision, 1, $plan['confirmacion_hash']);
        $new = DB::table('reemplazos_personal')->where('tipocontrato', 'REEMPLAZO')->first();
        $this->assertNotNull($new);
        $this->assertNotSame(101, $new->id);
        $this->assertSame(4, $new->jornada);
        $this->assertSame(101, DB::table('solicitudes_reemplazo')->value('reemplazo_personal_id'));
        $this->assertSame(101, DB::table('dotacion_docente_asignaciones')->value('reemplazos_personal_id'));
        $this->assertNull(DB::table('padron_personal_cambios')->where('personal_id', $new->id)->value('antes'));
        $writer->aplicar($this->revision, 1, $plan['confirmacion_hash']);
        $this->assertDatabaseCount('reemplazos_personal', 2);
        $this->assertDatabaseCount('padron_personal_cambios', 2);
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
    }
}
