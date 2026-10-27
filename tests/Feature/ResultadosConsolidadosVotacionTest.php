<?php

namespace Tests\Feature;

use App\Models\JornadaVotacion;
use App\Models\ResultadoConsolidadoVotacion;
use App\Models\ResultadoDiarioVotacion;
use App\Models\User;
use App\Services\Votaciones\BitacoraVotacionService;
use App\Services\Votaciones\ResultadoConsolidadoService;
use App\Services\Votaciones\ResultadoDiarioService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedSecurityTestCase;

class ResultadosConsolidadosVotacionTest extends IsolatedSecurityTestCase
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
        $this->jornada = JornadaVotacion::create(['nombre' => 'Jornada de prueba', 'slug' => 'consolidado-prueba', 'fecha' => '2026-10-23']);
        $this->jornada->procesos()->attach(DB::table('procesos_votacion')->pluck('id'));
        $this->admin = $this->testUser(1, 3);
        Storage::disk('local')->put('test-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head>@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('test-views'));
    }

    public function test_registra_varios_dias_consolida_todos_los_borradores_y_publica_con_una_sola_accion(): void
    {
        $this->actingAs($this->admin);
        foreach (['2026-10-23', '2026-10-26', '2026-10-27'] as $fecha) {
            $this->post(route('votaciones.admin.resultados.guardar', $this->jornada), $this->datos($fecha))->assertSessionHasNoErrors();
        }
        $this->assertSame(3, ResultadoDiarioVotacion::count());
        $this->assertSame(0, ResultadoDiarioVotacion::whereNotNull('publicado_at')->count());
        $revision = $this->get($this->rutaAdmin())->assertOk()->assertSee('Consolidar todos los días')->assertDontSee('Publicar día');
        $this->assertSame(30, $revision->viewData('resumen')['desafiliacion']['conteos']['si']);
        $this->assertSame(54, $revision->viewData('resumen')['desafiliacion']['emitidos']);
        $this->assertSame(63, $revision->viewData('resumen')['afiliacion']['emitidos']);
        $this->get($this->rutaPublica())->assertNotFound();
        $this->post(route('votaciones.admin.resultados.consolidar', $this->jornada), ['huella' => $revision->viewData('huella')])->assertSessionHasNoErrors();
        $this->get($this->rutaPublica())->assertNotFound();
        $consolidado = ResultadoConsolidadoVotacion::firstOrFail();
        $this->assertCount(3, $consolidado->dias);
        $this->patch($this->rutaPublicacion($consolidado), ['publicar' => 1])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        $publico = $this->get($this->rutaPublica())->assertOk()->assertSee('Resultado consolidado de todos los días')->assertSee('3 días incluidos');
        $this->assertSame(30, $publico->viewData('resumen')['desafiliacion']['conteos']['si']);
        $this->assertSame(45, $publico->viewData('resumen')['desafiliacion']['validos']);
        $this->assertSame(3, $publico->viewData('dias')->count());
        $this->assertSame(0, ResultadoDiarioVotacion::whereNotNull('publicado_at')->count());
    }

    public function test_rechaza_consolidar_una_pantalla_antigua_si_se_agrega_o_corrige_un_dia(): void
    {
        $dia = $this->guardar(); $huella = $this->huella();
        $this->guardar('2026-10-26');
        $this->actingAs($this->admin)->post(route('votaciones.admin.resultados.consolidar', $this->jornada), ['huella' => $huella])->assertSessionHasErrors('consolidacion');
        $this->assertSame(0, ResultadoConsolidadoVotacion::count());
        $huella = $this->huella(); $this->corregir($dia, 99);
        $this->post(route('votaciones.admin.resultados.consolidar', $this->jornada), ['huella' => $huella])->assertSessionHasErrors('consolidacion');
        $this->assertSame(0, ResultadoConsolidadoVotacion::count());
    }

    public function test_consolidado_preparado_no_publica_si_cambiaron_los_dias(): void
    {
        $this->guardar(); $consolidado = $this->consolidar(); $this->guardar('2026-10-26');
        $this->actingAs($this->admin)->patch($this->rutaPublicacion($consolidado), ['publicar' => 1])->assertSessionHasErrors('consolidacion');
        $this->assertNull($consolidado->refresh()->publicado_at);
        $this->get($this->rutaPublica())->assertNotFound();
    }

    public function test_nuevos_dias_y_correcciones_conservan_la_version_publica_hasta_republicar(): void
    {
        $dia = $this->guardar(); $this->guardar('2026-10-26');
        $primero = $this->consolidar(); $this->publicar($primero);
        $this->corregir($dia, 15); $this->guardar('2026-10-27');
        $response = $this->get($this->rutaPublica())->assertOk()->assertSee('2 días incluidos')->assertDontSee('27-10-2026');
        $this->assertSame(20, $response->viewData('resumen')['desafiliacion']['conteos']['si']);
        $this->actingAs($this->admin)->get($this->rutaAdmin())->assertOk()->assertSee('El público sigue viendo la última versión publicada');
        $nuevo = $this->consolidar();
        $this->assertSame(2, $nuevo->version);
        $this->publicar($nuevo);
        $response = $this->get($this->rutaPublica())->assertOk()->assertSee('3 días incluidos');
        $this->assertSame(35, $response->viewData('resumen')['desafiliacion']['conteos']['si']);
        $this->assertNotNull($primero->refresh()->retirado_at);
        $this->assertSame(20, $primero->resumen['desafiliacion']['conteos']['si']);
        $this->assertSame(1, ResultadoConsolidadoVotacion::publicados()->count());
    }

    public function test_retiro_no_resucita_publicaciones_diarias_historicas(): void
    {
        $dia = $this->guardar();
        app(ResultadoDiarioService::class)->publicar($this->jornada, $dia, true, 1, $this->admin);
        $this->get($this->rutaPublica())->assertOk();
        $consolidado = $this->consolidar();
        $this->get($this->rutaPublica())->assertOk(); // Preparar no retira lo previamente público.
        $this->publicar($consolidado);
        $this->actingAs($this->admin)->patch($this->rutaPublicacion($consolidado), ['publicar' => 0])->assertSessionHasNoErrors();
        $this->assertNotNull($dia->refresh()->publicado_at);
        $this->get($this->rutaPublica())->assertNotFound();
        $this->get(route('public.resultados-votacion.index'))->assertOk()->assertSee('Aún no hay resultados publicados');
    }

    public function test_solo_administradores_pueden_consolidar_y_publicar(): void
    {
        $this->guardar(); $consolidado = $this->consolidar();
        $this->post(route('votaciones.admin.resultados.consolidar', $this->jornada), ['huella' => $this->huella()])->assertRedirect(route('login'));
        $this->patch($this->rutaPublicacion($consolidado), ['publicar' => 1])->assertRedirect(route('login'));
        $this->actingAs($this->testUser(2, 2))->post(route('votaciones.admin.resultados.consolidar', $this->jornada), ['huella' => $this->huella()])->assertForbidden();
        $this->patch($this->rutaPublicacion($consolidado), ['publicar' => 1])->assertForbidden();
        $this->patch($this->rutaPublicacion($consolidado), ['publicar' => 0])->assertForbidden();
        $this->assertNull($consolidado->refresh()->publicado_at);
    }

    public function test_ambas_acciones_requieren_csrf(): void
    {
        $this->guardar(); $consolidado = $this->consolidar();
        $this->actingAs($this->admin); $this->app['env'] = 'local';
        $this->post(route('votaciones.admin.resultados.consolidar', $this->jornada), ['huella' => $this->huella()])->assertStatus(419);
        $this->patch($this->rutaPublicacion($consolidado), ['publicar' => 1])->assertStatus(419);
        $this->assertNull($consolidado->refresh()->publicado_at);
    }

    public function test_publicacion_de_consolidado_de_otra_jornada_es_rechazada(): void
    {
        $this->guardar(); $consolidado = $this->consolidar();
        $otra = JornadaVotacion::create(['nombre' => 'Otra jornada', 'slug' => 'otra', 'fecha' => '2026-10-23']);
        $this->actingAs($this->admin)->patch(route('votaciones.admin.resultados.consolidados.publicacion', [$otra, $consolidado]), ['publicar' => 1])->assertNotFound();
        $this->assertNull($consolidado->refresh()->publicado_at);
    }

    public function test_repetir_consolidacion_y_publicacion_es_idempotente(): void
    {
        $this->guardar(); $primero = $this->consolidar(); $segundo = $this->consolidar();
        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, ResultadoConsolidadoVotacion::count());
        $this->publicar($primero); $this->publicar($primero);
        $this->assertSame(1, DB::table('bitacora_votacion')->where('evento', 'resultados_consolidados')->count());
        $this->assertSame(1, DB::table('bitacora_votacion')->where('evento', 'consolidado_publicado')->count());
    }

    public function test_copia_publica_y_filtro_diario_no_exponen_datos_privados_ni_suman_otros_dias(): void
    {
        $this->guardar(); $this->guardar('2026-10-26');
        $consolidado = $this->consolidar(); $this->publicar($consolidado);
        $response = $this->get($this->rutaPublica(['fecha' => '2026-10-23']))->assertOk()->assertDontSee('OBSERVACION_INTERNA')->assertDontSee($this->admin->email);
        $this->assertSame(10, $response->viewData('resumen')['desafiliacion']['conteos']['si']);
        $this->assertSame(18, $response->viewData('resumen')['desafiliacion']['emitidos']);
        $snapshot = $response->viewData('publicado');
        $this->assertArrayNotHasKey('creado_por', $snapshot->getAttributes());
        $this->assertArrayNotHasKey('huella', $snapshot->getAttributes());
        foreach ($snapshot->dias as $dia) $this->assertSame(['fecha', 'conteos'], array_keys($dia));
        $this->get($this->rutaPublica(['fecha' => '2026-10-29']))->assertNotFound();
    }

    public function test_cambiar_catalogo_no_modifica_etiquetas_ni_sumas_de_un_consolidado_publicado(): void
    {
        $this->guardar(); $consolidado = $this->consolidar(); $this->publicar($consolidado);
        config(['votaciones.papeletas.desafiliacion.titulo' => 'TITULO_CAMBIADO']);
        $this->get($this->rutaPublica())->assertOk()->assertSee('Desafiliación CCAF')->assertDontSee('TITULO_CAMBIADO');
        $response = $this->get($this->rutaPublica(['fecha' => '2026-10-23']))->assertOk()->assertSee('Desafiliación CCAF');
        $this->assertSame(10, $response->viewData('resumen')['desafiliacion']['conteos']['si']);
    }

    public function test_no_consolida_sin_dias_y_no_publica_jornada_suspendida(): void
    {
        $this->actingAs($this->admin)->post(route('votaciones.admin.resultados.consolidar', $this->jornada), ['huella' => $this->huella()])->assertSessionHasErrors('consolidacion');
        $this->guardar(); $consolidado = $this->consolidar(); $this->jornada->update(['estado' => 'suspendida']);
        $this->patch($this->rutaPublicacion($consolidado), ['publicar' => 1])->assertSessionHasErrors('consolidacion');
        $this->get($this->rutaPublica())->assertNotFound();
    }

    public function test_contenido_de_las_versiones_es_inmutable(): void
    {
        $this->guardar(); $consolidado = $this->consolidar();
        $this->expectException(\LogicException::class);
        $consolidado->update(['dias' => []]);
    }

    public function test_fallo_en_bitacora_revierte_consolidacion_y_no_retira_la_publicacion_anterior(): void
    {
        $this->guardar(); $primero = $this->consolidar(); $this->publicar($primero);
        $this->guardar('2026-10-26'); $segundo = $this->consolidar();
        $this->mock(BitacoraVotacionService::class)->shouldReceive('registrar')->twice()->andThrow(new \RuntimeException('Fallo simulado'));
        $this->guardar('2026-10-27', auditar: false);
        try { $this->consolidar(); $this->fail('Debe revertir.'); }
        catch (\RuntimeException) { $this->assertSame(2, ResultadoConsolidadoVotacion::count()); }
        // Se usa la versión todavía vigente para comprobar rollback de la sustitución.
        DB::table('votacion_resultados_diarios')->whereDate('fecha', '2026-10-27')->delete();
        try { $this->publicar($segundo); $this->fail('Debe revertir.'); }
        catch (\RuntimeException) {
            $this->assertNull($segundo->refresh()->publicado_at);
            $this->assertNull($primero->refresh()->retirado_at);
            $this->assertSame(1, ResultadoConsolidadoVotacion::publicados()->count());
        }
    }

    public function test_orden_de_claves_json_no_cambia_revision_ni_republica_un_guardado_identico(): void
    {
        $dia = $this->guardar(); $huella = $this->huella();
        $conteos = array_reverse($dia->conteos, true);
        foreach ($conteos as &$papeleta) $papeleta = array_reverse($papeleta, true);
        unset($papeleta);
        DB::table('votacion_resultados_diarios')->where('id', $dia->id)->update(['conteos' => json_encode($conteos)]);
        $this->assertSame($huella, $this->huella());
        $data = $this->datos(); $data['version'] = 1;
        app(ResultadoDiarioService::class)->guardar($this->jornada, $data, $this->admin);
        $this->assertSame(1, $dia->refresh()->version);
    }

    public function test_publicacion_diaria_no_puede_sustituir_un_consolidado(): void
    {
        $dia = $this->guardar(); $this->consolidar();
        $this->actingAs($this->admin)->patch(route('votaciones.admin.resultados.publicacion', [$this->jornada, $dia]), ['publicar' => 1, 'version' => 1])->assertSessionHasErrors('publicacion');
        $this->assertNull($dia->refresh()->publicado_at);
    }

    public function test_participacion_usa_mayor_total_incluidos_nulos_y_blancos_y_padron_unico_de_la_jornada(): void
    {
        $this->padronParticipacion(100);
        DB::table('votacion_padron_consulta')->insert([
            ['jornada_votacion_id' => $this->jornada->id, 'rut' => '99000001K', 'nombre' => 'DUPLICADO_PRIVADO'],
            ['jornada_votacion_id' => 999, 'rut' => '99000999K', 'nombre' => 'OTRA_JORNADA_PRIVADA'],
        ]);
        $this->guardar(); $consolidado = $this->consolidar(); $this->publicar($consolidado);
        $respuesta = $this->get($this->rutaPublica())->assertOk()->assertSee('Avance de participación')
            ->assertSee('21,0 %')->assertSee('21 de 100')
            ->assertDontSee('99000001K')->assertDontSee('PADRON_PRIVADO')->assertDontSee('DUPLICADO_PRIVADO')->assertDontSee('OTRA_JORNADA_PRIVADA');
        $this->assertSame(['votantes' => 21, 'padron' => 100, 'porcentaje' => 21.0], $respuesta->viewData('participacion'));
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent());
        $xpath = new \DOMXPath($dom);
        $barra = $xpath->query('//*[@aria-labelledby="participacion-padron" and @role="progressbar"]')->item(0);
        $this->assertSame('21', $barra->getAttribute('aria-valuenow'));
        $this->assertStringContainsString('21 de 100', $barra->getAttribute('aria-valuetext'));
        $this->assertGreaterThan(10, $xpath->query('//*[@data-slep-count]')->length);
        $this->assertGreaterThan(3, $xpath->query('//*[@data-slep-fill]')->length);
    }

    public function test_participacion_diaria_y_acumulada_conservan_la_copia_publicada_sin_borradores(): void
    {
        $this->padronParticipacion(100);
        $primero = $this->guardar(); $this->guardar('2026-10-26');
        $consolidado = $this->consolidar(); $this->publicar($consolidado);
        $copia = $consolidado->fresh()->getAttributes();
        $this->guardar('2026-10-27'); $this->corregir($primero, 100);
        $publico = $this->get($this->rutaPublica())->assertOk();
        $this->assertSame(['votantes' => 42, 'padron' => 100, 'porcentaje' => 42.0], $publico->viewData('participacion'));
        $dia = $this->get($this->rutaPublica(['fecha' => '2026-10-23']))->assertOk();
        $this->assertSame(21, $dia->viewData('participacion')['votantes']);
        $this->assertSame(21.0, $dia->viewData('participacion')['porcentaje']);
        $admin = $this->actingAs($this->admin)->get($this->rutaAdmin())->assertOk();
        $this->assertSame(144, $admin->viewData('participacion')['votantes']);
        $this->assertSame($copia, $consolidado->fresh()->getAttributes());
    }

    public function test_acumulado_toma_la_mayor_papeleta_acumulada_y_no_suma_maximos_diarios(): void
    {
        $this->padronParticipacion(100); $this->guardar();
        $datos = $this->datos('2026-10-26');
        foreach ($datos['conteos'] as &$papeleta) $papeleta = array_fill_keys(array_keys($papeleta), 0);
        unset($papeleta);
        $datos['conteos']['mutualidad']['achs'] = 30;
        app(ResultadoDiarioService::class)->guardar($this->jornada, $datos, $this->admin);
        $this->publicar($this->consolidar());
        $this->assertSame(48, $this->get($this->rutaPublica())->assertOk()->viewData('participacion')['votantes']);
        $this->assertSame(30, $this->get($this->rutaPublica(['fecha' => '2026-10-26']))->assertOk()->viewData('participacion')['votantes']);
    }

    public function test_administracion_distingue_participacion_del_dia_guardado_y_del_consolidado(): void
    {
        $this->padronParticipacion(100); $this->guardar(); $this->guardar('2026-10-26');
        $respuesta = $this->actingAs($this->admin)->get($this->rutaAdmin())->assertOk();
        $this->assertSame(21, $respuesta->viewData('participacionDia')['votantes']);
        $this->assertSame(42, $respuesta->viewData('participacion')['votantes']);
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$respuesta->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@id="participacion-dia-padron"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="participacion-padron"]')->length);
    }

    public function test_sin_padron_conserva_los_votantes_y_no_inventa_un_porcentaje(): void
    {
        $this->guardar(); $this->publicar($this->consolidar());
        $respuesta = $this->get($this->rutaPublica())->assertOk()->assertSee('No hay padrón cargado');
        $this->assertSame(['votantes' => 21, 'padron' => 0, 'porcentaje' => null], $respuesta->viewData('participacion'));
        $this->assertStringNotContainsString('role="progressbar" aria-labelledby="participacion-padron"', $respuesta->getContent());
    }

    public function test_cero_votantes_con_padron_conocido_muestra_cero_por_ciento(): void
    {
        $this->padronParticipacion(100); $datos = $this->datos();
        foreach ($datos['conteos'] as &$papeleta) $papeleta = array_fill_keys(array_keys($papeleta), 0);
        unset($papeleta);
        app(ResultadoDiarioService::class)->guardar($this->jornada, $datos, $this->admin);
        $this->publicar($this->consolidar());
        $respuesta = $this->get($this->rutaPublica())->assertOk();
        $this->assertSame(['votantes' => 0, 'padron' => 100, 'porcentaje' => 0.0], $respuesta->viewData('participacion'));
        $this->assertStringContainsString('data-slep-fill="0" style="width: 0%"', $respuesta->getContent());
    }

    public function test_mas_votantes_que_padron_conserva_porcentaje_real_y_limita_la_barra(): void
    {
        $this->padronParticipacion(10); $this->guardar(); $this->publicar($this->consolidar());
        $respuesta = $this->get($this->rutaPublica())->assertOk()->assertSee('210,0 %')->assertSee('El total de votantes supera el padrón vigente');
        $this->assertSame(210.0, $respuesta->viewData('participacion')['porcentaje']);
        $this->assertStringContainsString('data-slep-fill="100" style="width: 100%"', $respuesta->getContent());
    }

    public function test_participacion_se_adapta_a_una_jornada_de_solo_mutualidad(): void
    {
        $this->padronParticipacion(100);
        $this->jornada->procesos()->sync(DB::table('procesos_votacion')->where('codigo', 'CONSULTA_MUTUALIDADES')->pluck('id'));
        $datos = $this->datos(); $datos['conteos'] = ['mutualidad' => $datos['conteos']['mutualidad']];
        app(ResultadoDiarioService::class)->guardar($this->jornada, $datos, $this->admin);
        $this->publicar($this->consolidar());
        $respuesta = $this->get($this->rutaPublica())->assertOk();
        $this->assertSame(18, $respuesta->viewData('participacion')['votantes']);
        $this->assertSame(18.0, $respuesta->viewData('participacion')['porcentaje']);
    }

    public function test_participacion_legacy_solo_cuenta_los_dias_publicados_y_el_padron_vigente(): void
    {
        $this->padronParticipacion(100);
        foreach (['2026-10-23', '2026-10-26'] as $fecha) {
            $dia = $this->guardar($fecha);
            app(ResultadoDiarioService::class)->publicar($this->jornada, $dia, true, 1, $this->admin);
        }
        $this->guardar('2026-10-27');
        $this->assertSame(42, $this->get($this->rutaPublica())->assertOk()->viewData('participacion')['votantes']);
        $this->assertSame(21, $this->get($this->rutaPublica(['fecha' => '2026-10-23']))->assertOk()->viewData('participacion')['votantes']);
        DB::table('votacion_padron_consulta')->insert(['jornada_votacion_id' => $this->jornada->id, 'rut' => '99001000K', 'nombre' => 'PADRON_PRIVADO']);
        $this->assertSame(101, $this->get($this->rutaPublica())->assertOk()->viewData('participacion')['padron']);
    }

    private function padronParticipacion(int $cantidad): void
    {
        Schema::create('votacion_padron_consulta', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('jornada_votacion_id'); $t->string('rut'); $t->string('nombre');
        });
        foreach (range(1, $cantidad) as $numero) {
            DB::table('votacion_padron_consulta')->insert([
                'jornada_votacion_id' => $this->jornada->id, 'rut' => (string) (99000000 + $numero).'K', 'nombre' => 'PADRON_PRIVADO',
            ]);
        }
    }

    protected function datos(string $fecha = '2026-10-23'): array
    {
        return ['fecha' => $fecha, 'version' => 0, 'observacion_interna' => 'OBSERVACION_INTERNA', 'conteos' => [
            'desafiliacion' => ['si' => 10, 'no' => 5, 'nulos' => 1, 'blancos' => 2],
            'afiliacion' => ['los_heroes' => 8, '18_septiembre' => 6, 'la_araucana' => 4, 'nulos' => 2, 'blancos' => 1],
            'mutualidad' => ['achs' => 7, 'isl' => 5, 'ist' => 3, 'mutual' => 2, 'nulos' => 1, 'blancos' => 0],
        ]];
    }

    protected function guardar(string $fecha = '2026-10-23', bool $auditar = true): ResultadoDiarioVotacion
    {
        if (!$auditar) return ResultadoDiarioVotacion::create(['jornada_votacion_id' => $this->jornada->id, 'fecha' => $fecha, 'conteos' => $this->datos($fecha)['conteos'], 'version' => 1]);

        return app(ResultadoDiarioService::class)->guardar($this->jornada, $this->datos($fecha), $this->admin);
    }

    protected function corregir(ResultadoDiarioVotacion $dia, int $cantidad): void
    {
        $data = $this->datos($dia->fecha->toDateString()); $data['version'] = $dia->refresh()->version; $data['conteos']['desafiliacion']['si'] = $cantidad;
        app(ResultadoDiarioService::class)->guardar($this->jornada, $data, $this->admin);
    }

    protected function huella(): string
    {
        return app(ResultadoConsolidadoService::class)->huella($this->jornada->resultadosDiarios()->get());
    }

    protected function consolidar(): ResultadoConsolidadoVotacion
    {
        return app(ResultadoConsolidadoService::class)->consolidar($this->jornada, $this->huella(), $this->admin);
    }

    protected function publicar(ResultadoConsolidadoVotacion $consolidado): void
    {
        app(ResultadoConsolidadoService::class)->publicar($this->jornada, $consolidado, true, $this->admin);
    }

    protected function rutaPublicacion(ResultadoConsolidadoVotacion $consolidado): string
    {
        return route('votaciones.admin.resultados.consolidados.publicacion', [$this->jornada, $consolidado]);
    }

    protected function rutaAdmin(): string
    {
        return route('votaciones.admin.resultados.index', ['jornada' => $this->jornada->slug, 'fecha' => '2026-10-23']);
    }

    protected function rutaPublica(array $data = []): string
    {
        return route('public.resultados-votacion.index', ['jornada' => $this->jornada->slug] + $data);
    }
}
