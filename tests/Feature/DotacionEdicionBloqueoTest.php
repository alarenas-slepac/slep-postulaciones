<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Controllers\Admin\DotacionCursoCombinadoController;
use App\Http\Controllers\Admin\DotacionFuncionesController;
use App\Http\Controllers\Admin\DotacionProceso2027Controller;
use App\Http\Controllers\Admin\EstablecimientoPlanEstudioController;
use App\Http\Middleware\BloquearEdicionDotacion;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\User;
use App\Services\Dotacion\EdicionBloqueoService;
use App\Support\DotacionAsignacionSuspension;
use App\Support\DotacionEdicionAcceso;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionEdicionBloqueoTest extends IsolatedSecurityTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dotacion_asignacion.suspension_temporal.habilitada' => false]);
        $this->withoutMiddleware([EnsureModuleAccess::class, CoordinarEscrituraPadron::class]);
        app('request')->setLaravelSession(app('session.store'));
        Schema::table('users', fn (Blueprint $t) => $t->integer('establecimiento_id')->nullable());
        foreach ([4 => 'funcionario_directivo_estab', 5 => 'coordinador_uatp', 6 => 'coordinador_gdp', 7 => 'supervisor_plani'] as $id => $name) {
            DB::table('roles')->insert(['id' => $id, 'name' => $name, 'guard_name' => 'web']);
        }
        Schema::create('establecimientos', function (Blueprint $t) { $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Establecimiento sintético B'],
        ]);
        foreach (['dotacion_docente_asignaciones', 'establecimiento_cursos', 'establecimiento_planes_estudio'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); });
            DB::table($table)->insert(['id' => 1, 'establecimiento_id' => 1, 'anio' => 2027]);
        }
        (require base_path('database/migrations/2026_11_02_140000_create_dotacion_edicion_bloqueos.php'))->up();
        $this->admin = $this->testUser(90, 3);
    }

    private function service(): EdicionBloqueoService { return app(EdicionBloqueoService::class); }

    private function entrar(int $rolId = 4, string $rol = 'funcionario_directivo_estab', int $establecimientoId = 1): User
    {
        $user = $this->testUser($rolId, $rolId);
        $user->forceFill(['establecimiento_id' => $establecimientoId])->save();
        $this->actingAs($user)->withSession(['active_role' => $rol]);

        return $user;
    }

    public function test_comienza_desactivado_y_conserva_las_escrituras_existentes(): void
    {
        $this->assertFalse($this->service()->estado(1)['bloqueado']);
        $this->entrar();
        $this->partialMock(DotacionAsignacionController::class)->shouldReceive('store')->once()->andReturn(redirect('/disponible'));
        $this->post(route('admin.dotacion-establecimiento.asignaciones.store', 1), ['anio' => 2030])->assertRedirect('/disponible');
        $this->assertDatabaseCount('dotacion_edicion_bloqueo_historial', 0);
    }

    public function test_general_e_individual_son_independientes_y_general_prevalece(): void
    {
        $this->service()->guardar(1, true, 0, 'Bloqueo sintético individual', $this->admin);
        $this->assertTrue($this->service()->estado(1)['bloqueado']);
        $this->assertFalse($this->service()->estado(2)['bloqueado']);
        $this->service()->guardar(null, true, 0, 'Bloqueo sintético general', $this->admin);
        $this->assertSame('general', $this->service()->estado(1)['origen']);
        $this->assertTrue($this->service()->estado(2)['bloqueado']);
        $this->service()->guardar(null, false, 1, 'Reapertura sintética general', $this->admin);
        $this->assertTrue($this->service()->estado(1)['bloqueado']);
        $this->assertFalse($this->service()->estado(2)['bloqueado']);
        $this->assertDatabaseCount('dotacion_edicion_bloqueo_historial', 3);
        $this->assertDatabaseHas('dotacion_edicion_bloqueo_historial', ['clave' => 'general', 'estado_anterior' => 1, 'estado_nuevo' => 0, 'usuario_id' => $this->admin->id, 'rol_activo' => 'admin', 'version' => 2]);
    }

    public function test_todas_las_rutas_de_escritura_y_pantallas_editoras_quedan_cubiertas_sin_confiar_en_el_anio(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        $user = $this->entrar();
        $protegidas = [];
        foreach (app('router')->getRoutes() as $route) {
            if (!$route->getName()) { continue; }
            $url = route($route->getName(), array_fill_keys($route->parameterNames(), 2));
            $request = Request::create($url, $route->methods()[0], ['anio' => 1999, 'establecimiento_id' => 2]);
            $copia = clone $route; $copia->bind($request);
            $request->setRouteResolver(fn () => $copia); $request->setUserResolver(fn () => $user);
            if (!DotacionEdicionAcceso::aplica($request) || !DotacionEdicionAcceso::esEdicion($request)) { continue; }
            $protegidas[] = $route->getName();
            try {
                app(BloquearEdicionDotacion::class)->handle($request, function () { $this->fail('No debe llegar al controlador.'); });
                $this->fail('Debe rechazar la operación.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('dotacion_bloqueo', $exception->errors(), $route->getName());
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode(), $route->getName());
            }
        }
        $this->assertGreaterThan(35, count($protegidas));
        foreach (['admin.establecimiento-planes.configure', 'admin.dotacion-establecimiento.docentes-subsector.sync', 'admin.dotacion-establecimiento.sobredotacion.justificaciones.destroy', 'admin.establecimiento-curso-pie.import.store'] as $name) { $this->assertContains($name, $protegidas); }
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 1);
    }

    public static function anios(): array { return [[2026], [2027], [2030]]; }

    #[DataProvider('anios')]
    public function test_bloqueo_individual_rechaza_formularios_previos_y_no_afecta_otro_establecimiento(int $anio): void
    {
        $this->service()->guardar(1, true, 0, null, $this->admin);
        $user = $this->entrar();
        $this->partialMock(DotacionFuncionesController::class)->shouldReceive('updateConfig')->once()->andReturn(response('Otro establecimiento'));
        $url = route('admin.dotacion-funciones.config', 1);
        $this->postJson($url, ['anio' => $anio])->assertUnprocessable()->assertJsonValidationErrors('dotacion_bloqueo');
        $this->from('/consulta-anterior')->post($url, ['anio' => $anio])->assertRedirect('/consulta-anterior')->assertSessionHasErrors('dotacion_bloqueo');
        // El ID del cuerpo o de la URL no permite evadir el bloqueo de su establecimiento.
        $this->postJson(route('admin.dotacion-funciones.config', 2), ['anio' => $anio, 'establecimiento_id' => 2])->assertUnprocessable();
        $user->forceFill(['establecimiento_id' => 2])->save();
        $this->post(route('admin.dotacion-funciones.config', 2), ['anio' => $anio])->assertOk();
    }

    public function test_configurar_plan_por_get_no_crea_datos_y_otras_etapas_tampoco_escriben(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        $this->entrar();
        $this->partialMock(EstablecimientoPlanEstudioController::class)->shouldReceive('configure')->never();
        $this->get(route('admin.establecimiento-planes.configure', 1))->assertForbidden();
        $this->partialMock(DotacionProceso2027Controller::class)->shouldReceive('update')->never();
        $this->postJson(route('admin.dotacion-establecimiento.proceso-2027.update', 1), ['anio' => 2027])->assertUnprocessable();
        $this->partialMock(DotacionCursoCombinadoController::class)->shouldReceive('store')->never();
        $this->postJson(route('admin.dotacion-establecimiento.cursos-combinados.store', 1), ['anio' => 2026])->assertUnprocessable();
        $this->assertDatabaseCount('establecimiento_planes_estudio', 1);
    }

    public static function roles(): array { return [[3, 'admin'], [5, 'coordinador_uatp'], [6, 'coordinador_gdp'], [7, 'supervisor_plani']]; }

    #[DataProvider('roles')]
    public function test_otros_roles_siguen_editando_y_pueden_administrar_bloqueos(int $id, string $rol): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        $user = $this->entrar($id, $rol);
        DB::table('model_has_roles')->insert(['role_id' => 4, 'model_type' => User::class, 'model_id' => $user->id]);
        $user->unsetRelation('roles');
        $this->partialMock(DotacionAsignacionController::class)->shouldReceive('update')->once()->andReturn(redirect('/editado'));
        $this->put(route('admin.dotacion-establecimiento.asignaciones.update', [1, 1]), ['anio' => 2027])->assertRedirect('/editado');
        $this->put(route('admin.dotacion-establecimiento.bloqueos.general.update'), ['bloqueado' => 0, 'version' => 1, 'anio' => 2026])->assertRedirect(route('admin.dotacion-establecimiento.index', ['anio' => 2026]));
        $this->put(route('admin.dotacion-establecimiento.bloqueos.establecimiento.update', 1), ['bloqueado' => 1, 'version' => 0, 'anio' => 2027])->assertRedirect(route('admin.dotacion-establecimiento.show', [1, 'anio' => 2027]));
        $this->assertTrue($this->service()->estado(1)['bloqueado']);
        $this->assertDatabaseHas('dotacion_edicion_bloqueo_historial', ['clave' => 'establecimiento:1', 'rol_activo' => $rol, 'usuario_id' => $user->id]);
        $this->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->putJson(route('admin.dotacion-establecimiento.asignaciones.update', [1, 1]), [])->assertUnprocessable();
        $this->putJson(route('admin.dotacion-establecimiento.bloqueos.general.update'), ['bloqueado' => 0, 'version' => 2])->assertForbidden();
    }

    public function test_directivo_no_puede_quitar_bloqueos_ni_mediante_rol_en_el_cuerpo_o_sesion_no_asignada(): void
    {
        $this->entrar();
        $this->withSession(['active_role' => 'admin'])->putJson(route('admin.dotacion-establecimiento.bloqueos.establecimiento.update', 1), ['bloqueado' => 0, 'version' => 0, 'active_role' => 'admin'])->assertForbidden();
        $this->assertDatabaseCount('dotacion_edicion_bloqueo_historial', 0);
    }

    public function test_rechaza_configuracion_desactualizada_y_fallo_de_auditoria_revierte_el_estado(): void
    {
        $this->service()->guardar(null, true, 0, 'Primer cambio sintético', $this->admin);
        try { $this->service()->guardar(null, false, 0, null, $this->admin); $this->fail('Debe rechazar la versión antigua.'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('dotacion_bloqueo', $exception->errors()); }
        $this->assertTrue($this->service()->estado()['bloqueado']);
        DB::listen(function (QueryExecuted $event) { if (str_starts_with($event->sql, 'insert into "dotacion_edicion_bloqueo_historial"')) { throw new \RuntimeException('Fallo sintético de auditoría'); } });
        try { $this->service()->guardar(null, false, 1, 'Reapertura sintética', $this->admin); $this->fail('Debe revertir.'); }
        catch (\RuntimeException $exception) { $this->assertSame('Fallo sintético de auditoría', $exception->getMessage()); }
        $this->assertTrue($this->service()->estado()['bloqueado']);
        $this->assertSame(1, $this->service()->estado()['general']['version']);
        $this->assertDatabaseCount('dotacion_edicion_bloqueo_historial', 1);
    }

    public function test_lecturas_exportaciones_y_modulos_ajenos_no_son_bloqueados(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        $user = $this->entrar();
        foreach ([['admin.dotacion-establecimiento.show', 'GET'], ['admin.dotacion-establecimiento.pdf', 'GET'], ['admin.establecimiento-planes.index', 'GET'], ['centro-operaciones.reportes.store', 'POST']] as [$name, $method]) {
            $route = app('router')->getRoutes()->getByName($name); $copy = clone $route;
            $request = Request::create(route($name, array_fill_keys($route->parameterNames(), 1)), $method);
            $copy->bind($request); $request->setRouteResolver(fn () => $copy); $request->setUserResolver(fn () => $user);
            $response = app(BloquearEdicionDotacion::class)->handle($request, fn () => response('Consulta'));
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_vistas_quitan_permisos_de_edicion_y_suspension_de_asignaciones_recibe_el_bloqueo_general(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        $this->entrar();
        $view = view('admin.dotacion-establecimiento.show', ['activeRole' => 'funcionario_directivo_estab', 'canManageCursosCombinados' => true, 'canManageJustificacionesSobredotacion' => true, 'canManageProceso2027Normativas' => true]);
        app('events')->dispatch('composing: admin.dotacion-establecimiento.show', [$view]);
        $this->assertTrue($view->getData()['dotacionEdicionBloqueada']);
        foreach (['canManageCursosCombinados', 'canManageJustificacionesSobredotacion', 'canManageProceso2027Normativas'] as $key) { $this->assertFalse($view->getData()[$key]); }
        foreach ([2026, 2027, 2030] as $anio) { $this->assertTrue(DotacionAsignacionSuspension::bloqueada($anio, 'funcionario_directivo_estab')); }
        $this->assertStringContainsString('todos los años', DotacionAsignacionSuspension::mensaje());
        $this->assertFalse(DotacionAsignacionSuspension::bloqueada(2027, 'admin'));
    }

    public function test_auditoria_no_disponible_no_levanta_un_bloqueo_existente(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        Schema::rename('dotacion_edicion_bloqueo_historial', 'auditoria_sintetica_temporal');
        $this->assertFalse($this->service()->disponible());
        $this->assertTrue($this->service()->estado(1)['bloqueado']);
        $this->entrar();
        $this->postJson(route('admin.dotacion-funciones.config', 1), ['anio' => 2026])->assertUnprocessable()->assertJsonValidationErrors('dotacion_bloqueo');
    }

    public function test_controles_rechazan_valores_invalidos_sin_modificar_estado(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin']);
        foreach ([['bloqueado' => 'invalido', 'version' => 0], ['bloqueado' => 1], ['bloqueado' => 1, 'version' => -1], ['bloqueado' => 1, 'version' => 0, 'motivo' => str_repeat('x', 1001)]] as $data) {
            $this->putJson(route('admin.dotacion-establecimiento.bloqueos.general.update'), $data)->assertUnprocessable();
        }
        $this->assertFalse($this->service()->estado()['bloqueado']);
        $this->assertDatabaseCount('dotacion_edicion_bloqueo_historial', 0);
    }

    public function test_asociacion_historica_del_directivo_tambien_recibe_el_bloqueo_individual(): void
    {
        $this->service()->guardar(1, true, 0, null, $this->admin);
        $user = $this->entrar();
        $user->forceFill(['establecimiento_id' => null, 'establecimiento_asociado_id' => 1]);
        $this->postJson(route('admin.dotacion-funciones.config', 1), ['anio' => 2027])->assertUnprocessable()->assertJsonValidationErrors('dotacion_bloqueo');
    }

    public function test_layout_expone_los_controles_generales_e_individuales_al_entrar_al_establecimiento(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin']);
        $request = Request::create(route('admin.dotacion-establecimiento.show', [1, 'anio' => 2030]));
        $route = clone app('router')->getRoutes()->getByName('admin.dotacion-establecimiento.show');
        $route->bind($request); $route->setParameter('establecimiento', \App\Models\Establecimiento::findOrFail(1));
        $request->setRouteResolver(fn () => $route); $request->setUserResolver(fn () => $this->admin);
        $request->setLaravelSession(app('session.store')); app()->instance('request', $request);
        $view = view('layouts.app'); app('events')->dispatch('composing: layouts.app', [$view]);
        $panel = $view->getData()['dotacionEdicionPanel'];
        $this->assertTrue($panel['gestiona']); $this->assertFalse($panel['soloConsulta']);
        $this->assertSame(2030, $panel['anio']); $this->assertSame(1, $panel['establecimiento']->id);
        $html = view('partials.dotacion-bloqueo-edicion', ['panelBloqueo' => $panel, 'errors' => new \Illuminate\Support\ViewErrorBag])->render();
        $this->assertStringContainsString('Activar bloqueo general', $html);
        $this->assertStringContainsString('Activar bloqueo individual', $html);
        $this->assertStringContainsString('Todos los años', $html);
    }
}
