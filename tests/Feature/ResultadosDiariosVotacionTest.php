<?php

namespace Tests\Feature;

use App\Models\JornadaVotacion;
use App\Models\ResultadoDiarioVotacion;
use App\Models\User;
use App\Services\Votaciones\BitacoraVotacionService;
use App\Services\Votaciones\ResultadoDiarioService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedSecurityTestCase;

class ResultadosDiariosVotacionTest extends IsolatedSecurityTestCase
{
    protected JornadaVotacion $jornada;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-10-30 18:00:00', 'America/Santiago'));
        Schema::create('establecimientos', fn (Blueprint $t) => $t->id());
        Schema::create('modules', function (Blueprint $t): void { $t->id(); $t->string('key'); });
        DB::table('modules')->insert(['key' => 'votaciones']);
        foreach (['model_has_permissions', 'role_has_permissions'] as $table) {
            Schema::create($table, function (Blueprint $t): void {
                $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id')->nullable();
                $t->unsignedBigInteger('model_id')->nullable(); $t->string('model_type')->nullable();
            });
        }
        (require database_path('migrations/2026_08_27_210000_create_votaciones_module_tables.php'))->up();
        (require database_path('migrations/2026_10_22_010000_create_votacion_resultados_diarios.php'))->up();
        (require database_path('migrations/2026_10_22_020000_create_votacion_resultados_consolidados.php'))->up();
        $this->jornada = $this->crearJornada('jornada-prueba');
        $this->admin = $this->testUser(1, 3);
        Storage::disk('local')->put('test-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head>@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('test-views'));
    }

    public function test_solo_admin_puede_ingresar_y_publicar_incluso_si_otro_rol_tiene_permiso_operativo(): void
    {
        $dia = $this->guardar();
        $this->get(route('votaciones.admin.resultados.index'))->assertRedirect(route('login'));
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $this->datos())->assertRedirect(route('login'));
        $this->patch($this->rutaPublicacion($dia), ['publicar' => 1, 'version' => 1])->assertRedirect(route('login'));
        $usuario = $this->testUser(2, 2);
        $permissionId = DB::table('permissions')->insertGetId(['name' => 'votaciones.admin', 'guard_name' => 'web']);
        $permiso = \Spatie\Permission\Models\Permission::findOrFail($permissionId);
        $usuario->givePermissionTo($permiso);
        $this->actingAs($usuario)->get(route('votaciones.admin.resultados.index'))->assertForbidden();
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $this->datos())->assertForbidden();
        $this->patch($this->rutaPublicacion($dia), ['publicar' => 1, 'version' => 1])->assertForbidden();
        $this->assertNull($dia->refresh()->publicado_at);
    }

    public function test_ingreso_y_publicacion_exigen_csrf(): void
    {
        $dia = $this->guardar();
        $this->actingAs($this->admin);
        $this->app['env'] = 'local';
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $this->datos())->assertStatus(419);
        $this->patch($this->rutaPublicacion($dia), ['publicar' => 1, 'version' => 1])->assertStatus(419);
        $this->assertSame(1, ResultadoDiarioVotacion::count());
        $this->assertNull($dia->refresh()->publicado_at);
    }

    public function test_guardar_muestra_las_tres_papeletas_y_deja_borrador_sin_modificar_la_jornada(): void
    {
        $this->actingAs($this->admin)->post(route('votaciones.admin.resultados.guardar', $this->jornada), $this->datos())
            ->assertRedirect()->assertSessionHas('success');
        $dia = ResultadoDiarioVotacion::firstOrFail();
        $this->assertSame($this->datos()['conteos'], $dia->conteos);
        $this->assertNull($dia->publicado_at);
        $this->assertSame(1, $dia->version);
        $this->assertSame('borrador', $this->jornada->refresh()->estado);
        $this->assertFalse($this->jornada->publica);
        $this->get(route('votaciones.admin.resultados.index', ['jornada' => $this->jornada->slug, 'fecha' => '2026-10-23']))
            ->assertOk()->assertSee('Desafiliación CCAF')->assertSee('Afiliación CCAF')->assertSee('Consulta Mutual')
            ->assertSee('Los Héroes')->assertSee('18 de Septiembre')->assertSee('La Araucana')->assertSee('Mutual')->assertSee('Consolidar todos los días')->assertDontSee('Publicar día');
        $this->assertSame('resultado_diario_creado', DB::table('bitacora_votacion')->value('evento'));
    }

    public function test_cantidades_invalidas_fechas_futuras_y_campos_faltantes_no_guardan_nada(): void
    {
        $this->actingAs($this->admin);
        foreach ([-1, 1.5, '1e3', '', null, 1000001, 'texto'] as $valor) {
            $data = $this->datos(); $data['conteos']['desafiliacion']['si'] = $valor;
            $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasErrors('conteos.desafiliacion.si');
        }
        foreach (['2026-10-31', '2026-02-30', 'no-fecha'] as $fecha) {
            $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $this->datos($fecha))->assertSessionHasErrors('fecha');
        }
        $data = $this->datos(); unset($data['conteos']['afiliacion']['nulos']);
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasErrors('conteos.afiliacion.nulos');
        $this->assertSame(0, ResultadoDiarioVotacion::count());
        $this->assertSame(0, DB::table('bitacora_votacion')->count());
    }

    public function test_no_acepta_opciones_inventadas_ni_publicacion_o_usuario_desde_el_formulario(): void
    {
        $this->actingAs($this->admin);
        $data = $this->datos(); $data['conteos']['desafiliacion']['otra'] = 999;
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasErrors('conteos.desafiliacion');
        $data = $this->datos(); $data['conteos']['inventada'] = ['si' => 100];
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasErrors('conteos');
        $data = $this->datos() + ['publicado_at' => now(), 'creado_por' => 999, 'jornada_votacion_id' => 999];
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasNoErrors();
        $dia = ResultadoDiarioVotacion::firstOrFail();
        $this->assertNull($dia->publicado_at);
        $this->assertSame($this->admin->id, $dia->creado_por);
        $this->assertSame($this->jornada->id, $dia->jornada_votacion_id);
    }

    public function test_repetir_alta_no_duplica_y_una_version_antigua_no_sobrescribe(): void
    {
        $this->actingAs($this->admin);
        $dia = $this->guardar();
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $this->datos())->assertSessionHasErrors('version');
        $data = $this->datos(); $data['version'] = $dia->version; $data['conteos']['desafiliacion']['si'] = 12;
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasNoErrors();
        $data['conteos']['desafiliacion']['si'] = 99;
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasErrors('version');
        $this->assertSame(1, ResultadoDiarioVotacion::count());
        $this->assertSame(12, $dia->refresh()->conteos['desafiliacion']['si']);
        $this->assertSame(2, $dia->version);
    }

    public function test_indice_unico_impide_dos_registros_de_la_misma_jornada_y_fecha(): void
    {
        $dia = $this->guardar();
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('votacion_resultados_diarios')->insert(['jornada_votacion_id' => $dia->jornada_votacion_id, 'fecha' => $dia->getRawOriginal('fecha'), 'conteos' => '{}']);
    }

    public function test_guardado_identico_no_retira_una_publicacion_ni_duplica_bitacora(): void
    {
        $dia = $this->guardar(); $this->publicar($dia);
        $data = $this->datos(); $data['version'] = $dia->refresh()->version;
        $this->actingAs($this->admin)->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasNoErrors();
        $this->assertNotNull($dia->refresh()->publicado_at);
        $this->assertSame(2, $dia->version);
        $this->assertSame(2, DB::table('bitacora_votacion')->count());
    }

    public function test_borradores_son_privados_hasta_publicacion_explicita_y_retiro_es_inmediato(): void
    {
        $dia = $this->guardar();
        $this->get(route('public.resultados-votacion.index'))->assertOk()->assertSee('Aún no hay resultados publicados')->assertDontSee('Jornada jornada-prueba');
        $this->get($this->rutaPublica())->assertNotFound();
        $this->actingAs($this->admin)->patch($this->rutaPublicacion($dia), ['publicar' => 1, 'version' => 1])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        $response = $this->get($this->rutaPublica())->assertOk()->assertSee('Jornada jornada-prueba')->assertSee('Resultados parciales');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->actingAs($this->admin)->patch($this->rutaPublicacion($dia), ['publicar' => 0, 'version' => $dia->refresh()->version])->assertSessionHasNoErrors();
        $this->get($this->rutaPublica())->assertNotFound();
    }

    public function test_publicar_version_antigua_y_resultado_de_otra_jornada_no_es_posible(): void
    {
        $dia = $this->guardar();
        $this->actingAs($this->admin)->patch($this->rutaPublicacion($dia), ['publicar' => 1, 'version' => 99])->assertSessionHasErrors('version');
        $otra = $this->crearJornada('otra-jornada');
        $this->patch(route('votaciones.admin.resultados.publicacion', [$otra, $dia]), ['publicar' => 1, 'version' => 1])->assertNotFound();
        $this->assertNull($dia->refresh()->publicado_at);
    }

    public function test_corregir_un_publicado_lo_retira_y_conserva_antes_y_despues_en_bitacora(): void
    {
        $dia = $this->guardar(); $this->publicar($dia);
        $data = $this->datos(); $data['version'] = $dia->refresh()->version; $data['conteos']['desafiliacion']['si'] = 31;
        $this->actingAs($this->admin)->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasNoErrors();
        $this->assertNull($dia->refresh()->publicado_at);
        $this->get($this->rutaPublica())->assertNotFound();
        $metadata = json_decode(DB::table('bitacora_votacion')->where('evento', 'resultado_diario_corregido')->value('metadata'), true);
        $this->assertSame(10, $metadata['anterior']['desafiliacion']['si']);
        $this->assertSame(31, $metadata['nuevo']['desafiliacion']['si']);
        $this->assertTrue($metadata['publicacion_retirada']);
    }

    public function test_acumulado_y_filtro_diario_suman_solo_dias_publicados_de_su_jornada(): void
    {
        $primero = $this->guardar('2026-10-23'); $this->publicar($primero);
        $segundo = $this->guardar('2026-10-26'); $this->publicar($segundo);
        $this->guardar('2026-10-27');
        $otra = $this->crearJornada('otra-jornada');
        $ajeno = app(ResultadoDiarioService::class)->guardar($otra, $this->datos('2026-10-23'), $this->admin);
        app(ResultadoDiarioService::class)->publicar($otra, $ajeno, true, 1, $this->admin);
        $response = $this->get($this->rutaPublica())->assertOk()->assertDontSee('27-10-2026');
        $resumen = $response->viewData('resumen');
        $this->assertSame(20, $resumen['desafiliacion']['conteos']['si']);
        $this->assertSame(30, $resumen['desafiliacion']['validos']);
        $this->assertSame(36, $resumen['desafiliacion']['emitidos']);
        $this->assertSame(42, $resumen['afiliacion']['emitidos']);
        $this->assertSame(2, $response->viewData('dias')->count());
        $dia = $this->get($this->rutaPublica(['fecha' => '2026-10-23']))->assertOk();
        $this->assertSame(10, $dia->viewData('resumen')['desafiliacion']['conteos']['si']);
        $this->get($this->rutaPublica(['fecha' => '2026-10-27']))->assertNotFound();
        $this->get($this->rutaPublica(['fecha' => '2026-10-29']))->assertNotFound();
    }

    public function test_porcentajes_excluyen_nulos_y_blancos_y_cero_votos_no_divide_por_cero(): void
    {
        $dia = $this->guardar(); $this->publicar($dia);
        $this->get($this->rutaPublica())->assertOk()->assertSee('66,7 %');
        $data = $this->datos('2026-10-26');
        foreach ($data['conteos'] as &$papeleta) $papeleta = array_fill_keys(array_keys($papeleta), 0);
        unset($papeleta);
        $cero = app(ResultadoDiarioService::class)->guardar($this->jornada, $data, $this->admin); $this->publicar($cero);
        $response = $this->get($this->rutaPublica(['fecha' => '2026-10-26']))->assertOk()->assertSee('0,0 %');
        $this->assertSame(0, $response->viewData('resumen')['desafiliacion']['emitidos']);
    }

    public function test_no_expone_observaciones_usuarios_padron_o_datos_privados_en_resultados_publicos(): void
    {
        $data = $this->datos(); $data['observacion_interna'] = 'NOTA_INTERNA_NO_PUBLICAR';
        $dia = app(ResultadoDiarioService::class)->guardar($this->jornada, $data, $this->admin); $this->publicar($dia);
        $response = $this->get($this->rutaPublica())->assertOk()->assertDontSee('NOTA_INTERNA_NO_PUBLICAR')->assertDontSee($this->admin->email)->assertDontSee($this->admin->rut);
        foreach ($response->viewData('dias') as $publico) {
            $this->assertArrayNotHasKey('observacion_interna', $publico->getAttributes());
            $this->assertArrayNotHasKey('creado_por', $publico->getAttributes());
        }
    }

    public function test_jornada_suspendida_no_publica_y_oculta_publicaciones_anteriores(): void
    {
        $dia = $this->guardar(); $this->publicar($dia);
        $this->jornada->update(['estado' => 'suspendida']);
        $this->get($this->rutaPublica())->assertNotFound();
        app(ResultadoDiarioService::class)->publicar($this->jornada, $dia, false, $dia->refresh()->version, $this->admin);
        $this->actingAs($this->admin)->patch($this->rutaPublicacion($dia), ['publicar' => 1, 'version' => $dia->refresh()->version])->assertSessionHasErrors('publicacion');
        $this->get(route('votaciones.admin.resultados.index', ['fecha' => '2026-10-23']))->assertOk()->assertSee('La jornada está suspendida');
    }

    public function test_la_fecha_limite_respeta_el_dia_de_chile_y_no_el_utc(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 01:00:00', 'UTC'));
        $this->actingAs($this->admin)->post(route('votaciones.admin.resultados.guardar', $this->jornada), $this->datos('2026-10-24'))->assertSessionHasErrors('fecha');
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $this->datos('2026-10-23'))->assertSessionHasNoErrors();
    }

    public function test_un_fallo_en_bitacora_revierte_guardado_y_publicacion(): void
    {
        $dia = $this->guardar();
        $this->mock(BitacoraVotacionService::class)->shouldReceive('registrar')->twice()->andThrow(new \RuntimeException('Fallo simulado'));
        $service = app(ResultadoDiarioService::class);
        try { $service->guardar($this->jornada, $this->datos('2026-10-26'), $this->admin); $this->fail('Debe revertir.'); }
        catch (\RuntimeException) { $this->assertSame(1, ResultadoDiarioVotacion::count()); }
        try { $service->publicar($this->jornada, $dia, true, 1, $this->admin); $this->fail('Debe revertir.'); }
        catch (\RuntimeException) { $this->assertNull($dia->refresh()->publicado_at); $this->assertSame(1, $dia->version); }
    }

    public function test_jornada_con_un_solo_proceso_no_exige_las_otras_papeletas(): void
    {
        $this->jornada->procesos()->sync(DB::table('procesos_votacion')->where('codigo', 'CONSULTA_MUTUALIDADES')->pluck('id'));
        $data = $this->datos(); unset($data['conteos']['desafiliacion'], $data['conteos']['afiliacion']);
        $this->actingAs($this->admin)->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasNoErrors();
        $dia = ResultadoDiarioVotacion::firstOrFail(); $this->publicar($dia);
        $this->get($this->rutaPublica())->assertOk()->assertSee('Consulta Mutual')->assertDontSee('Desafiliación CCAF');
        $this->assertSame(['mutualidad'], array_keys($dia->conteos));
    }

    public function test_cambiar_procesos_de_la_jornada_no_oculta_ni_descarta_las_papeletas_de_un_dia_historico(): void
    {
        $dia = $this->guardar(); $this->publicar($dia);
        $this->jornada->procesos()->sync(DB::table('procesos_votacion')->where('codigo', 'CONSULTA_MUTUALIDADES')->pluck('id'));
        $this->get($this->rutaPublica())->assertOk()->assertSee('Desafiliación CCAF')->assertSee('Afiliación CCAF')->assertSee('Consulta Mutual');
        $this->actingAs($this->admin)->get(route('votaciones.admin.resultados.index', ['jornada' => $this->jornada->slug, 'fecha' => '2026-10-23']))
            ->assertOk()->assertSee('Deseo desafiliarme de CCAF Los Andes');
        $data = $this->datos(); $data['version'] = $dia->refresh()->version; $data['conteos']['afiliacion']['los_heroes'] = 99;
        $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $data)->assertSessionHasNoErrors();
        $this->assertSame(99, $dia->refresh()->conteos['afiliacion']['los_heroes']);
        $this->assertCount(3, $dia->conteos);
    }

    protected function crearJornada(string $slug): JornadaVotacion
    {
        $jornada = JornadaVotacion::create(['slug' => $slug, 'nombre' => 'Jornada '.$slug, 'fecha' => '2026-10-23']);
        $jornada->procesos()->attach(DB::table('procesos_votacion')->pluck('id'));

        return $jornada;
    }

    protected function datos(string $fecha = '2026-10-23'): array
    {
        return ['fecha' => $fecha, 'version' => 0, 'observacion_interna' => null, 'conteos' => [
            'desafiliacion' => ['si' => 10, 'no' => 5, 'nulos' => 1, 'blancos' => 2],
            'afiliacion' => ['los_heroes' => 8, '18_septiembre' => 6, 'la_araucana' => 4, 'nulos' => 2, 'blancos' => 1],
            'mutualidad' => ['achs' => 7, 'isl' => 5, 'ist' => 3, 'mutual' => 2, 'nulos' => 1, 'blancos' => 0],
        ]];
    }

    protected function guardar(string $fecha = '2026-10-23'): ResultadoDiarioVotacion
    {
        return app(ResultadoDiarioService::class)->guardar($this->jornada, $this->datos($fecha), $this->admin);
    }

    protected function publicar(ResultadoDiarioVotacion $dia): void
    {
        app(ResultadoDiarioService::class)->publicar($this->jornada, $dia, true, $dia->version, $this->admin);
    }

    protected function rutaPublicacion(ResultadoDiarioVotacion $dia): string
    {
        return route('votaciones.admin.resultados.publicacion', [$this->jornada, $dia]);
    }

    protected function rutaPublica(array $params = []): string
    {
        return route('public.resultados-votacion.index', ['jornada' => $this->jornada->slug] + $params);
    }
}
