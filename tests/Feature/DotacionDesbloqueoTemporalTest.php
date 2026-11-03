<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionFuncionesController;
use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\User;
use App\Services\Dotacion\EdicionBloqueoService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionDesbloqueoTemporalTest extends IsolatedSecurityTestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['dotacion_asignacion.suspension_temporal.habilitada' => false, 'app.display_timezone' => 'America/Santiago']);
        $this->travelTo(Carbon::parse('2026-11-03 10:00:30', 'America/Santiago'));
        $this->withoutMiddleware([EnsureModuleAccess::class, CoordinarEscrituraPadron::class]);
        $this->withoutVite();
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
        (require database_path('migrations/2026_11_02_140000_create_dotacion_edicion_bloqueos.php'))->up();
        (require database_path('migrations/2026_11_03_210000_add_desbloqueo_temporal_to_dotacion_edicion_bloqueos.php'))->up();
        $this->admin = $this->testUser(90, 3);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    protected function service(): EdicionBloqueoService { return app(EdicionBloqueoService::class); }

    protected function autorizar(string $hasta = '2026-11-03 10:02', int $version = 0): void
    {
        $this->service()->guardar(1, false, $version, 'Autorización sintética para completar antecedentes.', $this->admin, $hasta);
    }

    protected function directivo(int $establecimiento = 1): User
    {
        $user = $this->testUser(40 + $establecimiento, 4);
        $user->forceFill(['establecimiento_id' => $establecimiento])->save();
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        return $user;
    }

    public function test_solo_la_autorizacion_explicita_del_establecimiento_exceptua_el_general_y_vence_en_el_instante_fijado(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        DB::table('dotacion_edicion_bloqueos')->insert(['clave' => 'establecimiento:2', 'establecimiento_id' => 2, 'bloqueado' => false, 'version' => 1]);
        $this->autorizar();
        $this->assertFalse($this->service()->estado(1)['bloqueado']);
        $this->assertTrue($this->service()->estado(1)['desbloqueo_activo']);
        $this->assertTrue($this->service()->estado(2)['bloqueado']);
        $this->assertTrue($this->service()->estado()['bloqueado']);
        $this->travelTo(Carbon::parse('2026-11-03 10:01:59', 'America/Santiago'));
        $this->assertFalse($this->service()->estado(1)['bloqueado']);
        $this->travelTo(Carbon::parse('2026-11-03 10:02:00', 'America/Santiago'));
        $this->assertTrue($this->service()->estado(1)['bloqueado']);
        $this->assertTrue($this->service()->estado(1)['desbloqueo_vencido']);
        $this->assertStringContainsString('venció el 03/11/2026 10:02', $this->service()->estado(1)['mensaje']);
    }

    public static function years(): array { return [[2026], [2027], [2030]]; }

    #[DataProvider('years')]
    public function test_el_servidor_permite_editar_durante_el_plazo_y_rechaza_formularios_previos_al_vencer(int $year): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        $this->autorizar(); $this->directivo();
        $this->partialMock(DotacionFuncionesController::class)->shouldReceive('updateConfig')->once()->andReturn(response('Permitido'));
        $url = route('admin.dotacion-funciones.config', 1);
        $this->postJson($url, ['anio' => $year])->assertOk();
        $this->travelTo(Carbon::parse('2026-11-03 10:02:00', 'America/Santiago'));
        $this->postJson($url, ['anio' => $year])->assertUnprocessable()->assertJsonValidationErrors('dotacion_bloqueo');
        $this->from('/formulario-abierto')->post($url, ['anio' => $year])->assertRedirect('/formulario-abierto')->assertSessionHasErrors('dotacion_bloqueo');
    }

    public function test_otro_directivo_no_usa_la_excepcion_enviando_el_id_autorizado(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        $this->autorizar(); $this->directivo(2);
        $this->partialMock(DotacionFuncionesController::class)->shouldNotReceive('updateConfig');
        $this->postJson(route('admin.dotacion-funciones.config', 1), ['anio' => 2027, 'establecimiento_id' => 1])->assertUnprocessable()->assertJsonValidationErrors('dotacion_bloqueo');
    }

    public static function roles(): array { return [[3, 'admin'], [5, 'coordinador_uatp'], [6, 'coordinador_gdp'], [7, 'supervisor_plani']]; }

    #[DataProvider('roles')]
    public function test_los_cuatro_roles_autorizan_y_el_historial_registra_usuario_rol_y_plazo(int $id, string $rol): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        $user = $this->testUser($id, $id);
        $this->actingAs($user)->withSession(['active_role' => $rol]);
        $this->put(route('admin.dotacion-establecimiento.bloqueos.establecimiento.update', 1), [
            'bloqueado' => 0, 'version' => 0, 'motivo' => 'Autorización sintética', 'desbloqueo_hasta' => '2026-11-03 10:02', 'anio' => 2027,
        ])->assertRedirect(route('admin.dotacion-establecimiento.show', [1, 'anio' => 2027]))->assertSessionHas('dotacion_bloqueo_status', fn ($v) => str_contains($v, 'RBD 99999 hasta el 03/11/2026 10:02'));
        $this->assertFalse($this->service()->estado(1)['bloqueado']);
        $this->assertTrue($this->service()->estado(2)['bloqueado']);
        $this->assertDatabaseHas('dotacion_edicion_bloqueo_historial', ['clave' => 'establecimiento:1', 'estado_nuevo' => 0,
            'usuario_id' => $user->id, 'rol_activo' => $rol, 'version' => 1, 'desbloqueo_hasta_anterior' => null,
            'desbloqueo_hasta_nuevo' => $this->service()->estado(1)['individual']['desbloqueo_hasta']]);
        // El bloqueo sigue siendo exclusivo del directivo: GDP/UATP/Planificación/admin pueden escribir tras el vencimiento.
        $this->travelTo(Carbon::parse('2026-11-03 10:03:00', 'America/Santiago'));
        $this->partialMock(DotacionAsignacionController::class)->shouldReceive('store')->once()->andReturn(redirect('/otro-rol-autorizado'));
        $this->postJson(route('admin.dotacion-establecimiento.asignaciones.store', 1), ['anio' => 2027])->assertRedirect('/otro-rol-autorizado');
    }

    public function test_directivo_no_autoriza_ni_renueva_incluso_si_tiene_admin_inactivo(): void
    {
        $this->autorizar(); $user = $this->directivo();
        DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => User::class, 'model_id' => $user->id]); $user->unsetRelation('roles');
        $this->putJson(route('admin.dotacion-establecimiento.bloqueos.establecimiento.update', 1), [
            'bloqueado' => 0, 'version' => 1, 'motivo' => 'Intento sintético', 'desbloqueo_hasta' => '2026-11-03 11:00', 'active_role' => 'admin',
        ])->assertForbidden();
        $this->assertSame(1, $this->service()->estado(1)['individual']['version']);
    }

    public static function invalidDates(): array
    {
        return [[null], [''], ['2026-11-03 09:59'], ['2026-11-03 10:00'], ['2026-11-03 24:00'],
            ['2026-11-31 10:00'], ['2026-11-03T10:02'], ['03-11-2026 10:02'], ['texto'], ["2026-11-03\0 10:02"], ['2027-09-05 00:30']];
    }

    #[DataProvider('invalidDates')]
    public function test_rechaza_plazos_ausentes_pasados_imposibles_o_con_hora_inexistente_sin_alterar_el_bloqueo(?string $date): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin']);
        $this->putJson(route('admin.dotacion-establecimiento.bloqueos.establecimiento.update', 1), [
            'bloqueado' => 0, 'version' => 0, 'motivo' => 'Solicitud sintética', 'desbloqueo_hasta' => $date,
        ])->assertUnprocessable()->assertJsonValidationErrors('desbloqueo_hasta');
        $this->assertTrue($this->service()->estado(1)['bloqueado']);
        $this->assertDatabaseCount('dotacion_edicion_bloqueos', 1);
        $this->assertDatabaseCount('dotacion_edicion_bloqueo_historial', 1);
    }

    public function test_el_motivo_es_obligatorio_y_no_se_admiten_plazos_en_bloqueos_o_control_general(): void
    {
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin']);
        foreach (['', '   ', str_repeat('x', 1001)] as $motivo) {
            $this->putJson(route('admin.dotacion-establecimiento.bloqueos.establecimiento.update', 1), [
                'bloqueado' => 0, 'version' => 0, 'motivo' => $motivo, 'desbloqueo_hasta' => '2026-11-03 11:00',
            ])->assertUnprocessable()->assertJsonValidationErrors('motivo');
        }
        foreach (['admin.dotacion-establecimiento.bloqueos.general.update', 'admin.dotacion-establecimiento.bloqueos.establecimiento.update'] as $route) {
            $this->putJson(route($route, $route === 'admin.dotacion-establecimiento.bloqueos.general.update' ? [] : [1]), [
                'bloqueado' => 1, 'version' => 0, 'desbloqueo_hasta' => '2026-11-03 11:00',
            ])->assertUnprocessable()->assertJsonValidationErrors('desbloqueo_hasta');
        }
        $this->assertDatabaseCount('dotacion_edicion_bloqueo_historial', 0);
    }

    public function test_renovar_el_plazo_incrementa_version_conserva_el_anterior_y_rechaza_formularios_desactualizados(): void
    {
        $this->autorizar(); $primero = $this->service()->estado(1)['individual']['desbloqueo_hasta'];
        $this->autorizar('2026-11-03 10:03', 1);
        $this->assertDatabaseHas('dotacion_edicion_bloqueo_historial', ['clave' => 'establecimiento:1', 'version' => 2, 'desbloqueo_hasta_anterior' => $primero,
            'desbloqueo_hasta_nuevo' => $this->service()->estado(1)['individual']['desbloqueo_hasta']]);
        try { $this->autorizar('2026-11-03 11:00', 1); $this->fail('No debe sobrescribir una renovación posterior.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('dotacion_bloqueo', $e->errors()); }
        $this->travelTo(Carbon::parse('2026-11-03 10:02:30', 'America/Santiago'));
        $this->assertFalse($this->service()->estado(1)['bloqueado']);
        $this->travelTo(Carbon::parse('2026-11-03 10:03:00', 'America/Santiago'));
        $this->assertTrue($this->service()->estado(1)['bloqueado']);
    }

    public function test_revocacion_anticipada_cierra_el_establecimiento_y_borra_solo_el_plazo_actual(): void
    {
        $this->autorizar();
        $this->service()->guardar(1, true, 1, 'Revocación sintética', $this->admin);
        $estado = $this->service()->estado(1);
        $this->assertTrue($estado['bloqueado']); $this->assertFalse($estado['desbloqueo_activo']);
        $this->assertNull($estado['individual']['desbloqueo_hasta']);
        $this->assertDatabaseHas('dotacion_edicion_bloqueo_historial', ['clave' => 'establecimiento:1', 'version' => 2, 'estado_nuevo' => 1, 'desbloqueo_hasta_nuevo' => null]);
    }

    public function test_desactivar_el_general_no_extiende_la_autorizacion_y_el_vencimiento_no_requiere_cron_ni_escribe_al_consultar(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin); $this->autorizar();
        $this->service()->guardar(null, false, 1, null, $this->admin);
        $this->travelTo(Carbon::parse('2026-11-03 10:02:00', 'America/Santiago'));
        $writes = [];
        DB::listen(function (QueryExecuted $event) use (&$writes) { if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $event->sql)) { $writes[] = $event->sql; } });
        $this->assertTrue($this->service()->estado(1)['bloqueado']);
        $this->assertSame('establecimiento', $this->service()->estado(1)['origen']);
        $this->assertFalse($this->service()->estado(2)['bloqueado']);
        $this->assertSame([], $writes);
        $this->assertSame(1, $this->service()->estado(1)['individual']['version']);
    }

    public function test_zona_de_captura_chile_se_convierte_a_la_zona_de_almacenamiento_sin_cambiar_el_instante(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->autorizar();
        $this->assertDatabaseHas('dotacion_edicion_bloqueos', ['clave' => 'establecimiento:1', 'desbloqueo_hasta' => '2026-11-03 13:02:00']);
        $estado = $this->service()->estado(1);
        $this->assertSame('03/11/2026 10:02', $estado['desbloqueo_hasta_texto']);
        $this->assertSame('2026-11-03 10:02', $estado['desbloqueo_hasta_formulario']);
        $this->assertTrue($estado['desbloqueo_activo']);
    }

    public function test_sin_actualizacion_se_conserva_el_bloqueo_general_y_no_se_autoriza_reapertura(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin);
        Schema::table('dotacion_edicion_bloqueos', fn (Blueprint $t) => $t->renameColumn('desbloqueo_hasta', 'plazo_temporal_no_disponible'));
        $this->assertTrue($this->service()->estado(1)['bloqueado']);
        $this->assertFalse($this->service()->desbloqueoDisponible());
        try { $this->autorizar(); $this->fail('No debe autorizar sin el esquema actualizado.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('dotacion_bloqueo', $e->errors()); }
    }

    public function test_fallo_de_auditoria_revierte_la_renovacion_y_conserva_el_plazo_previo(): void
    {
        $this->autorizar(); $antes = $this->service()->estado(1)['individual'];
        DB::listen(function (QueryExecuted $event) { if (str_starts_with($event->sql, 'insert into "dotacion_edicion_bloqueo_historial"')) { throw new \RuntimeException('Fallo sintético de auditoría temporal'); } });
        try { $this->autorizar('2026-11-03 11:00', 1); $this->fail('Debe revertir la renovación.'); }
        catch (\RuntimeException $e) { $this->assertSame('Fallo sintético de auditoría temporal', $e->getMessage()); }
        $this->assertSame($antes, $this->service()->estado(1)['individual']);
    }

    public function test_compositor_conserva_permisos_durante_el_plazo_y_los_retira_al_vencer(): void
    {
        $this->service()->guardar(null, true, 0, null, $this->admin); $this->autorizar(); $this->directivo();
        foreach ([false, true] as $vencido) {
            if ($vencido) { $this->travelTo(Carbon::parse('2026-11-03 10:02:00', 'America/Santiago')); }
            $view = view('admin.dotacion-establecimiento.show', ['activeRole' => 'funcionario_directivo_estab', 'canManageCursosCombinados' => true, 'canManageJustificacionesSobredotacion' => true]);
            app('events')->dispatch('composing: admin.dotacion-establecimiento.show', [$view]);
            $this->assertSame($vencido, $view->getData()['dotacionEdicionBloqueada']);
            $this->assertSame(!$vencido, $view->getData()['canManageCursosCombinados']);
            $this->assertSame(!$vencido, $view->getData()['canManageJustificacionesSobredotacion']);
        }
    }
}
