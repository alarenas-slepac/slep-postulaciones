<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionDocenteAsignacionesDeleteTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 30));
        $this->withoutMiddleware(CoordinarEscrituraPadron::class);
        $this->withoutVite();
        $this->resetCaches();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        Schema::table('users', fn (Blueprint $t) => $t->integer('establecimiento_id')->nullable());
        DB::table('roles')->insert(['id' => 4, 'name' => 'funcionario_directivo_estab', 'guard_name' => 'web']);
        Schema::create('modules', function (Blueprint $t): void { $t->id(); $t->string('key'); });
        Schema::create('module_role', function (Blueprint $t): void { $t->integer('module_id'); $t->integer('role_id'); });
        Schema::create('establecimientos', function (Blueprint $t): void { $t->id(); });
        DB::table('establecimientos')->insert([['id' => 1], ['id' => 2]]);
        (require database_path('migrations/2026_05_28_210000_create_dotacion_docente_asignaciones_table.php'))->up();
        Schema::table('dotacion_docente_asignaciones', fn (Blueprint $t) => $t->string('estamento_cobertura')->default('docente'));
        (require database_path('migrations/2026_05_25_183000_create_docente_horas_proporciones_table.php'))->up();
        (require database_path('migrations/2026_07_23_170000_sync_docente_horas_proporciones_cpeip.php'))->up();
        foreach ([
            ['id' => 1, 'tipo_asignacion' => 'plan_estudio', 'horas_plan_pedagogicas' => 15, 'horas_contrato' => 17],
            ['id' => 2, 'tipo_asignacion' => 'otra_funcion', 'horas_contrato' => 3],
            ['id' => 3, 'tipo_asignacion' => 'plan_estudio', 'horas_plan_pedagogicas' => 15, 'horas_contrato' => 18],
            ['id' => 4, 'tipo_asignacion' => 'reserva_no_normativa', 'horas_contrato' => 2],
            ['id' => 5, 'establecimiento_id' => 2], ['id' => 6, 'anio' => 2026],
            ['id' => 7, 'docente_rut' => '99000002K', 'docente_rut_normalizado' => '99000002K'],
            ['id' => 8, 'estado' => 'no_renovada'], ['id' => 9, 'estado' => 'inactiva'],
            ['id' => 10, 'estamento_cobertura' => 'asistente'],
        ] as $row) {
            DotacionDocenteAsignacion::create(array_replace([
                'establecimiento_id' => 1, 'anio' => 2027, 'docente_rut' => '99000001-K',
                'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente sintético',
                'tipo_asignacion' => 'otra_funcion', 'asignatura_nombre' => 'Asignación sintética',
                'horas_contrato' => 3, 'proporcion_aplicada' => '65/35',
            ], $row));
        }
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        $this->travelBack();
        parent::tearDown();
    }

    private function resetCaches(): void
    {
        foreach (['schemaTableCache', 'schemaColumnCache'] as $field) {
            (new ReflectionProperty(DotacionAsignacionCalculator::class, $field))->setValue(null, []);
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
    }

    private function request(array $ids): Request
    {
        $request = Request::create('/asignaciones/docente', 'DELETE', $this->payload($ids));
        $request->setUserResolver(fn () => new class {
            public function activeRoleName(): string { return 'admin'; }
        });
        $request->setLaravelSession($this->app['session.store']);

        return $request;
    }

    private function payload(array $ids): array
    {
        return ['anio' => 2027, 'docente_rut' => '99.000.001-k', 'asignaciones' => $ids, 'detalle_asignaciones' => '1'];
    }

    public function test_elimina_solo_la_seleccion_del_docente_y_recalcula_el_contrato_restante(): void
    {
        $response = app(DotacionAsignacionController::class)->destroyDocenteAssignments($this->request([1, 2]), Establecimiento::findOrFail(1));
        $this->assertTrue($response->isRedirect());
        $this->assertSame([3, 4, 5, 6, 7, 8, 9, 10], DotacionDocenteAsignacion::orderBy('id')->pluck('id')->all());
        $this->assertSame(17.0, (float) DotacionDocenteAsignacion::findOrFail(3)->horas_contrato);
        $this->assertSame(2.0, (float) DotacionDocenteAsignacion::findOrFail(4)->horas_contrato);
    }

    public function test_acepta_eliminacion_individual_y_reservas_sin_borrar_otras_filas(): void
    {
        app(DotacionAsignacionController::class)->destroyDocenteAssignments($this->request([4]), Establecimiento::findOrFail(1));
        $this->assertDatabaseMissing('dotacion_docente_asignaciones', ['id' => 4]);
        $this->assertSame(9, DotacionDocenteAsignacion::count());
    }

    public function test_rechaza_todo_el_lote_si_incluye_otro_rbd_anio_docente_estamento_historia_o_id_inexistente(): void
    {
        $before = DotacionDocenteAsignacion::orderBy('id')->get()->toJson();
        foreach ([5, 6, 7, 8, 9, 10, 999] as $invalidId) {
            try {
                app(DotacionAsignacionController::class)->destroyDocenteAssignments($this->request([1, $invalidId]), Establecimiento::findOrFail(1));
                $this->fail('Debe rechazar el lote completo.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('asignaciones', $e->errors());
            }
            $this->assertSame($before, DotacionDocenteAsignacion::orderBy('id')->get()->toJson());
        }
    }

    public function test_revierte_la_eliminacion_y_los_recalculos_si_falla_una_fila_del_grupo(): void
    {
        $before = DotacionDocenteAsignacion::orderBy('id')->get()->toJson();
        $dispatcher = DotacionDocenteAsignacion::getEventDispatcher();
        DotacionDocenteAsignacion::setEventDispatcher(clone $dispatcher);
        DotacionDocenteAsignacion::deleting(function ($row): void {
            if ((int) $row->id === 2) throw ValidationException::withMessages(['asignaciones' => 'Fallo simulado al retirar la segunda asignación.']);
        });
        try {
            app(DotacionAsignacionController::class)->destroyDocenteAssignments($this->request([1, 2]), Establecimiento::findOrFail(1));
            $this->fail('Debe revertir toda la transacción.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('asignaciones', $e->errors());
        } finally {
            DotacionDocenteAsignacion::setEventDispatcher($dispatcher);
        }
        $this->assertSame($before, DotacionDocenteAsignacion::orderBy('id')->get()->toJson());
        $this->assertFalse(session()->has('success'));
    }

    public function test_peticion_http_conserva_la_pestana_y_valida_seleccion_y_rut(): void
    {
        $this->actingAs($this->testUser(1, 3))->withSession(['active_role' => 'admin']);
        $url = route('admin.dotacion-establecimiento.asignaciones.docente.destroy', 1);
        $returnUrl = route('admin.dotacion-establecimiento.show', [1, 'anio' => 2027, 'tab' => 'docentes', 'revision_docentes_q' => 'Docente']);
        $this->from($returnUrl)->delete($url, $this->payload([]))->assertRedirect($returnUrl)->assertSessionHasErrors('asignaciones');
        $this->deleteJson($url, $this->payload([1, 1]))->assertUnprocessable()->assertJsonValidationErrors('asignaciones.0');
        $this->from($returnUrl)->delete($url, $this->payload([2]))->assertRedirect($returnUrl)->assertSessionHas('success');
        $this->assertDatabaseMissing('dotacion_docente_asignaciones', ['id' => 2]);
    }

    public function test_directivo_no_elimina_otro_establecimiento_y_respeta_suspension_y_anio_real(): void
    {
        $user = $this->testUser(1, 4);
        $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->deleteJson(route('admin.dotacion-establecimiento.asignaciones.docente.destroy', 2), $this->payload([5]))->assertForbidden();
        config(['dotacion_asignacion.suspension_temporal.desde' => '2026-10-29 00:00:00', 'dotacion_asignacion.suspension_temporal.hasta' => '2026-11-02 00:00:00']);
        $url = route('admin.dotacion-establecimiento.asignaciones.docente.destroy', 1);
        $this->deleteJson($url, $this->payload([1]))->assertUnprocessable()->assertJsonValidationErrors('asignaciones');
        $this->deleteJson($url, array_replace($this->payload([1]), ['anio' => 2026]))->assertUnprocessable()->assertJsonValidationErrors('asignaciones');
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 10);
    }

    public function test_roles_sin_permiso_y_usuarios_no_autenticados_no_eliminan(): void
    {
        $url = route('admin.dotacion-establecimiento.asignaciones.docente.destroy', 1);
        $this->deleteJson($url, $this->payload([1]))->assertUnauthorized();
        $this->actingAs($this->testUser(1, 1))->withSession(['active_role' => 'postulante']);
        $this->deleteJson($url, $this->payload([1]))->assertForbidden();
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 10);
    }

    public function test_detalle_muestra_filas_y_acciones_antes_de_situaciones_y_no_consulta_relaciones_por_fila(): void
    {
        $data = $this->dataVista();
        DB::flushQueryLog(); DB::enableQueryLog();
        $html = view('admin.dotacion-establecimiento.partials._docentes', $data)->render();
        $this->assertCount(0, DB::getQueryLog()); DB::disableQueryLog();
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$html); $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//section[@data-docente-horas]/following-sibling::section[contains(@aria-labelledby,"situaciones-titulo")]')->length);
        $this->assertSame(4, $xpath->query('//input[@data-horas-selection]')->length);
        $this->assertSame(4, $xpath->query('//form[@data-horas-delete="single"]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-horas-delete="bulk"]')->length);
        $this->assertSame(4, $xpath->query('//input[@data-horas-selection and @form="docente-detalle-1-eliminar-horas"]')->length);
        $this->assertStringContainsString('Horas contrato', $html);
        $this->assertStringNotContainsString('value="8"', $html);
    }

    public function test_solo_consulta_no_muestra_botones_y_escapa_textos(): void
    {
        $data = $this->dataVista(); $data['activeRole'] = 'funcionario_slep';
        $data['docentes']->first()['asignaciones']->first()->asignatura_nombre = '<img src=x onerror=alert(1)>';
        $html = view('admin.dotacion-establecimiento.partials._docentes', $data)->render();
        $this->assertStringNotContainsString('data-horas-delete=', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function test_error_reabre_el_docente_y_conserva_solo_las_selecciones_vigentes(): void
    {
        $data = $this->dataVista();
        $data['errors'] = (new ViewErrorBag)->put('default', new MessageBag(['asignaciones' => 'Actualice el detalle.']));
        $this->app['session.store']->flashInput(['anio' => 2027, 'docente_rut' => '99000001-K', 'detalle_asignaciones' => '1', 'asignaciones' => [1, 3, 8]]);
        $html = view('admin.dotacion-establecimiento.partials._docentes', $data)->render();
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$html); $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//tr[@id="docente-detalle-1" and contains(@class,"show")]')->length);
        $this->assertSame(2, $xpath->query('//input[@data-horas-selection and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@data-horas-selection and @checked and @value="1"]')->length);
        $this->assertStringContainsString('Actualice el detalle.', $html);
    }

    public function test_el_detalle_admite_vacio_y_no_ofrece_eliminar_asignaciones_automaticas(): void
    {
        $data = $this->dataVista();
        $docente = $data['docentes']->first();
        $docente['asignaciones'] = collect();
        $data['docentes'] = collect([$docente]);
        $html = view('admin.dotacion-establecimiento.partials._docentes', $data)->render();
        $this->assertStringContainsString('Este docente no tiene asignaciones activas', $html);
        $this->assertStringNotContainsString('data-horas-delete=', $html);
        $docente['asignaciones'] = collect([(object) ['id' => null, 'tipo_asignacion' => 'funcion_directiva',
            'asignatura_nombre' => 'Asignación automática sintética', 'horas_contrato' => 44, 'asignacion_automatica' => true]]);
        $data['docentes'] = collect([$docente]);
        $html = view('admin.dotacion-establecimiento.partials._docentes', $data)->render();
        $this->assertStringContainsString('Asignación automática sintética', $html);
        $this->assertStringNotContainsString('data-horas-delete=', $html);
        $this->assertStringNotContainsString('data-horas-selection>', $html);
    }

    public function test_suspension_oculta_acciones_y_no_se_borran_situaciones_al_retirar_todo(): void
    {
        $data = $this->dataVista(); $data['activeRole'] = 'funcionario_directivo_estab';
        config(['dotacion_asignacion.suspension_temporal.desde' => '2026-10-29 00:00:00', 'dotacion_asignacion.suspension_temporal.hasta' => '2026-11-02 00:00:00']);
        $html = view('admin.dotacion-establecimiento.partials._docentes', $data)->render();
        $this->assertStringNotContainsString('data-horas-delete=', $html);
        $this->assertStringContainsString('temporalmente en modo sólo consulta', $html);
        Schema::create('dotacion_docente_exclusiones', function (Blueprint $t): void { $t->id(); $t->string('motivo'); });
        DB::table('dotacion_docente_exclusiones')->insert(['motivo' => 'horas_gremiales']);
        app(DotacionAsignacionController::class)->destroyDocenteAssignments($this->request([1, 2, 3, 4]), Establecimiento::findOrFail(1));
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 6);
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['motivo' => 'horas_gremiales']);
    }

    private function dataVista(): array
    {
        $rows = DotacionDocenteAsignacion::whereIn('id', [1, 2, 3, 4])->get();
        return ['establecimiento' => Establecimiento::findOrFail(1), 'anio' => 2027, 'activeRole' => 'admin',
            'errors' => new ViewErrorBag, 'canManageDocenteExclusiones' => false,
            'docentes' => collect([[
                'rut' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Pedagogía en Educación Básica',
                'horas_contrato' => 44, 'horas_aula' => 30, 'horas_asignadas_total' => 40, 'horas_reservadas_no_normativas' => 2,
                'diferencia' => 4, 'funcion' => 'DOCENTE', 'estamento' => 'DOCENTE', 'niveles_declarados' => 'Básica',
                'tipo_contrato' => 'PLANTA', 'financiamiento' => 'General', 'mes' => 8, 'anio' => 2027, 'asignaciones' => $rows,
            ]])];
    }
}
