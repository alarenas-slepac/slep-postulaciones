<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\JornadaVotacion;
use App\Models\PadronDefinitivoVotacion;
use App\Models\User;
use App\Services\Votaciones\BitacoraVotacionService;
use App\Services\Votaciones\PadronConsultaExcel;
use App\Services\Votaciones\PadronConsultaService;
use App\Services\Votaciones\PadronDefinitivoService;
use App\Services\Votaciones\ResultadoConsolidadoService;
use App\Services\Votaciones\ResultadoDiarioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\IsolatedSecurityTestCase;

class PadronDefinitivoVotacionTest extends IsolatedSecurityTestCase
{
    private JornadaVotacion $jornada;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(EnsureModuleAccess::class);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-09 18:00:00', 'America/Santiago'));
        Schema::create('establecimientos', fn (Blueprint $t) => $t->id());
        foreach (['model_has_permissions', 'role_has_permissions'] as $table) {
            Schema::create($table, function (Blueprint $t): void {
                $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id')->nullable();
                $t->unsignedBigInteger('model_id')->nullable(); $t->string('model_type')->nullable();
            });
        }
        foreach (['2026_08_27_210000_create_votaciones_module_tables.php', '2026_10_21_210000_create_consulta_local_votacion.php',
            '2026_10_22_010000_create_votacion_resultados_diarios.php', '2026_10_22_020000_create_votacion_resultados_consolidados.php',
            '2026_11_09_230000_create_votacion_padrones_definitivos.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->admin = $this->testUser(1, 3);
        $this->jornada = JornadaVotacion::create(['nombre' => 'Jornada sintética', 'slug' => 'padron-definitivo-prueba', 'fecha' => '2026-10-28']);
        $this->jornada->procesos()->attach(DB::table('procesos_votacion')->pluck('id'));
        app(PadronConsultaService::class)->importar($this->jornada, $this->datos(100), $this->admin, false);
        app(PadronConsultaService::class)->publicar($this->jornada, true, $this->admin);
        foreach (['2026-10-28', '2026-10-29'] as $fecha) {
            app(ResultadoDiarioService::class)->guardar($this->jornada, ['fecha' => $fecha, 'version' => 0, 'conteos' => [
                'desafiliacion' => ['si' => 20, 'no' => 10, 'nulos' => 3, 'blancos' => 2],
                'afiliacion' => ['los_heroes' => 20, '18_septiembre' => 10, 'la_araucana' => 5, 'nulos' => 3, 'blancos' => 2],
                'mutualidad' => ['achs' => 15, 'isl' => 5, 'ist' => 5, 'mutual' => 5, 'nulos' => 2, 'blancos' => 1],
            ]], $this->admin);
        }
        $service = app(ResultadoConsolidadoService::class);
        $consolidado = $service->consolidar($this->jornada, $service->huella($this->jornada->resultadosDiarios()->get()), $this->admin);
        $service->publicar($this->jornada, $consolidado, true, $this->admin);
        Storage::disk('local')->put('test-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head>@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('test-views'));
    }

    private function datos(int $cantidad): array
    {
        $padron = [];
        for ($i = 1; $i <= $cantidad; $i++) {
            $voter = ['rut' => (99000000 + $i).'K', 'nombre' => 'PERSONA SINTÉTICA '.$i, 'rbd' => 99999,
                'establecimiento' => 'Escuela sintética', 'tipo_contrato' => 'Contrata', 'grupo' => '1', 'local_key' => '99999|99999'];
            $padron[] = $voter + ['fila_hash' => hash('sha256', json_encode($voter))];
        }
        return ['padron' => $padron, 'locales' => ['99999|99999' => ['rbd_origen' => 99999, 'establecimiento' => 'Escuela sintética',
            'rbd_local' => 99999, 'lugar_votacion' => 'Local sintético', 'dia' => '2026-10-28', 'hora_inicio' => '09:00:00']],
            'resumen' => ['errores' => 0, 'votantes' => $cantidad, 'locales' => 1]];
    }

    private function preparar(bool $certificado = false, int $personas = 80): PadronDefinitivoVotacion
    {
        return app(PadronDefinitivoService::class)->preparar($this->jornada, $this->datos($personas), $this->admin,
            $certificado ? UploadedFile::fake()->createWithContent('certificado.pdf', "%PDF-1.4\n1 0 obj <</Type/Catalog>> endobj\n%%EOF") : null);
    }

    private function publico(array $params = []): string
    {
        return route('public.resultados-votacion.index', ['jornada' => $this->jornada->slug] + $params);
    }

    public function test_preparar_conserva_padron_porcentajes_mensaje_y_certificado_publicos(): void
    {
        $antes = $this->get($this->publico())->assertOk()->assertSee('carácter referencial')->assertDontSee('del padrón definitivo');
        $filas = $this->jornada->padronConsulta()->get()->toJson();
        $version = $this->preparar(true);
        $despues = $this->get($this->publico())->assertOk()->assertSee('carácter referencial')->assertDontSee('del padrón definitivo')->assertDontSee('Ver certificado');
        $this->assertSame($antes->viewData('participacion'), $despues->viewData('participacion'));
        $this->assertSame($filas, $this->jornada->padronConsulta()->get()->toJson());
        $this->assertTrue($this->jornada->refresh()->consulta_publica);
        $this->assertNull($version->publicado_at);
        $this->get(route('public.consulta-votacion.certificado', [$this->jornada, $version]))->assertNotFound();
        $this->assertArrayNotHasKey('datos', $version->toArray());
        $this->assertArrayNotHasKey('certificado_path', $version->toArray());
    }

    public function test_revision_privada_muestra_comparacion_diaria_y_acumulada_solo_de_escrutinios_publicados(): void
    {
        $version = $this->preparar(true);
        // Una corrección sin republicar no debe alterar la comparación con el público.
        $dia = $this->jornada->resultadosDiarios()->first();
        $dia->forceFill(['conteos' => ['afiliacion' => ['los_heroes' => 999]]])->save();
        $this->actingAs($this->admin);
        $revision = $this->get(route('votaciones.admin.padrones.definitivo.revision', [$this->jornada, $version]))
            ->assertOk()->assertSee('Vista previa privada')->assertSee('80,0 %')->assertSee('100,0 %')->assertSee('57,1 %')->assertSee('50,0 %')->assertSee('Ver certificado');
        $this->assertSame(80, $revision->viewData('participacion')['votantes']);
        $this->assertSame(100, $revision->viewData('actual')['padron']);
        $diario = $this->get(route('votaciones.admin.padrones.definitivo.revision', [$this->jornada, $version, 'fecha' => '2026-10-28']))->assertOk();
        $this->assertSame(50.0, $diario->viewData('participacion')['porcentaje']);
        $this->get(route('votaciones.admin.padrones.definitivo.certificado', [$this->jornada, $version]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_publicacion_explicita_activa_todo_a_la_vez_y_conserva_escrutinios_y_sus_porcentajes_validos(): void
    {
        $version = $this->preparar(true);
        $votos = $this->jornada->resultadosConsolidados()->get()->toJson();
        $this->actingAs($this->admin)->post(route('votaciones.admin.padrones.definitivo.publicar', [$this->jornada, $version]))->assertSessionHasErrors('confirmar_definitivo');
        $this->assertNull($version->refresh()->publicado_at);
        $this->post(route('votaciones.admin.padrones.definitivo.publicar', [$this->jornada, $version]), ['confirmar_definitivo' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $resultado = $this->get($this->publico())->assertOk()->assertSee('padrón definitivo de personas habilitadas')->assertDontSee('carácter referencial')
            ->assertSee('100,0 %')->assertSee('57,1 %')->assertSee('50,0 %')->assertSee('Descargar certificado')
            ->assertDontSee('PERSONA SINTÉTICA')->assertDontSee('99000001K');
        $this->assertSame(['votantes' => 80, 'padron' => 80, 'porcentaje' => 100.0], $resultado->viewData('participacion'));
        $this->assertSame(50.0, $this->get($this->publico(['fecha' => '2026-10-28']))->viewData('participacion')['porcentaje']);
        $this->assertSame($votos, $this->jornada->resultadosConsolidados()->get()->toJson());
        $this->assertSame(80, $this->jornada->padronConsulta()->count());
        $this->assertSame($this->admin->id, $version->refresh()->publicado_por);
        $this->get(route('public.consulta-votacion.certificado', [$this->jornada, $version]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('public.consulta-votacion.certificado', [$this->jornada, $version, 'descargar' => 1]))->assertDownload('certificado-dotacion-padron-'.$version->id.'.pdf');
    }

    public function test_certificado_es_opcional_y_nueva_preparacion_no_cambia_definitivo_ya_publicado(): void
    {
        $primero = $this->preparar();
        app(PadronDefinitivoService::class)->publicar($this->jornada, $primero, $this->admin);
        $this->get($this->publico())->assertOk()->assertSee('padrón definitivo de personas habilitadas')->assertDontSee('certificado de dotación asociado');
        $this->preparar(true, 60);
        $this->assertSame(80, $this->get($this->publico())->viewData('participacion')['padron']);
        $this->assertSame($primero->id, app(PadronDefinitivoService::class)->publicado($this->jornada)->id);
    }

    public function test_rechaza_version_antigua_otro_padron_y_jornada_suspendida_sin_modificar_la_publicacion(): void
    {
        $anterior = $this->preparar(); $ultima = $this->preparar();
        $this->actingAs($this->admin);
        $this->post(route('votaciones.admin.padrones.definitivo.publicar', [$this->jornada, $anterior]), ['confirmar_definitivo' => 1])->assertSessionHasErrors('definitivo');
        $this->jornada->forceFill(['estado' => 'suspendida'])->save();
        $this->post(route('votaciones.admin.padrones.definitivo.publicar', [$this->jornada, $ultima]), ['confirmar_definitivo' => 1])->assertSessionHasErrors('publicacion');
        $this->assertNull($ultima->refresh()->publicado_at);
        $this->assertSame(100, $this->jornada->padronConsulta()->count());
        $this->jornada->forceFill(['estado' => 'borrador'])->save();
        app(PadronConsultaService::class)->importar($this->jornada, $this->datos(90), $this->admin, true);
        $this->post(route('votaciones.admin.padrones.definitivo.publicar', [$this->jornada, $ultima]), ['confirmar_definitivo' => 1])->assertSessionHasErrors('definitivo');
        $this->assertSame(90, $this->jornada->padronConsulta()->count());
    }

    public function test_fallo_de_bitacora_revierte_toda_la_publicacion(): void
    {
        $version = $this->preparar();
        $this->mock(BitacoraVotacionService::class)->shouldReceive('registrar')->andThrow(new \RuntimeException('Fallo sintético'));
        $this->actingAs($this->admin)->post(route('votaciones.admin.padrones.definitivo.publicar', [$this->jornada, $version]), ['confirmar_definitivo' => 1])->assertSessionHasErrors('definitivo');
        $this->assertNull($version->refresh()->publicado_at);
        $this->assertSame(100, $this->jornada->padronConsulta()->count());
        $this->assertTrue($this->jornada->refresh()->consulta_publica);
    }

    public function test_no_se_puede_modificar_o_eliminar_un_definitivo_publicado(): void
    {
        $version = $this->preparar();
        app(PadronDefinitivoService::class)->publicar($this->jornada, $version, $this->admin);
        try { app(PadronConsultaService::class)->importar($this->jornada, $this->datos(20), $this->admin, true); $this->fail('Debió impedir la carga directa.'); }
        catch (ValidationException) { $this->assertSame(80, $this->jornada->padronConsulta()->count()); }
        $this->expectException(\LogicException::class);
        $version->refresh()->update(['personas' => 20]);
    }

    public function test_rutas_privadas_exigen_admin_y_el_certificado_no_se_filtra_entre_jornadas(): void
    {
        $version = $this->preparar(true);
        $ruta = route('votaciones.admin.padrones.definitivo.revision', [$this->jornada, $version]);
        $this->get($ruta)->assertRedirect(route('login'));
        $this->actingAs($this->testUser(2, 1))->get($ruta)->assertForbidden();
        $otra = JornadaVotacion::create(['nombre' => 'Otra jornada sintética', 'slug' => 'otra-jornada', 'fecha' => '2026-10-28']);
        $this->actingAs($this->admin)->get(route('votaciones.admin.padrones.definitivo.certificado', [$otra, $version]))->assertNotFound();
        $this->get(route('public.consulta-votacion.certificado', [$otra, $version]))->assertNotFound();
    }

    public function test_carga_http_valida_certificado_y_prepara_sin_publicar(): void
    {
        $this->actingAs($this->admin);
        $this->mock(PadronConsultaExcel::class)->shouldReceive('analizar')->once()->andReturn($this->datos(80));
        $this->post(route('votaciones.admin.padrones.definitivo.preparar', $this->jornada), [
            'archivo_definitivo' => UploadedFile::fake()->create('padron.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'certificado_dotacion' => UploadedFile::fake()->createWithContent('certificado.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, PadronDefinitivoVotacion::count());
        $this->assertSame(100, $this->jornada->padronConsulta()->count());
        $this->post(route('votaciones.admin.padrones.definitivo.preparar', $this->jornada), [
            'archivo_definitivo' => UploadedFile::fake()->create('padron.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            'certificado_dotacion' => UploadedFile::fake()->create('falso.pdf', 10, 'text/html'),
        ])->assertSessionHasErrors('certificado_dotacion');
        $this->assertSame(1, PadronDefinitivoVotacion::count());
    }

    public function test_certificado_publico_solo_entrega_ultima_version_y_respeta_suspension_y_procesos(): void
    {
        $service = app(PadronDefinitivoService::class);
        $primero = $this->preparar(true);
        $service->publicar($this->jornada, $primero, $this->admin);
        $segundo = $this->preparar(true, 60);
        $service->publicar($this->jornada, $segundo, $this->admin);
        $this->get(route('public.consulta-votacion.certificado', [$this->jornada, $primero]))->assertNotFound();
        $ruta = route('public.consulta-votacion.certificado', [$this->jornada, $segundo]);
        $this->get($ruta)->assertOk();
        $this->jornada->forceFill(['estado' => 'suspendida'])->save();
        $this->get($ruta)->assertNotFound();
        $this->jornada->forceFill(['estado' => 'borrador'])->save();
        DB::table('procesos_votacion')->update(['activo' => false]);
        $this->get($ruta)->assertNotFound();
    }

    public function test_parciales_bloqueadas_y_certificado_perdido_conservan_datos_publicados(): void
    {
        $primero = $this->preparar();
        $service = app(PadronDefinitivoService::class);
        $service->publicar($this->jornada, $primero, $this->admin);
        try {
            app(\App\Services\Votaciones\ActualizarVotantesService::class)->aplicar($this->jornada, [], ['nombre'], false, $this->admin);
            $this->fail('Debió impedir la actualización parcial.');
        } catch (ValidationException) { $this->assertSame(80, $this->jornada->padronConsulta()->count()); }
        $this->mock(\App\Services\Votaciones\ActualizarVotantesExcel::class)->shouldReceive('leerActualizacion')->once()->andReturn([]);
        $this->actingAs($this->admin)->post(route('votaciones.admin.padrones.actualizar', $this->jornada), [
            'campos' => ['nombre'], 'confirmar_actualizacion' => 1,
            'archivo_actualizacion' => UploadedFile::fake()->create('actualizacion.xlsx', 10, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ])->assertSessionHasErrors(['archivo_actualizacion' => 'El padrón definitivo publicado se conserva. Prepare una nueva versión definitiva para corregirlo.']);
        $segundo = $this->preparar(true, 60);
        Storage::disk('local')->delete($segundo->certificado_path);
        $this->actingAs($this->admin)->post(route('votaciones.admin.padrones.definitivo.publicar', [$this->jornada, $segundo]), ['confirmar_definitivo' => 1])->assertSessionHasErrors('definitivo');
        $this->assertSame(80, $this->get($this->publico())->viewData('participacion')['padron']);
        $this->assertNull($segundo->refresh()->publicado_at);
    }

    public function test_versiones_cuentan_rut_una_vez_y_una_revision_vacia_no_modifica_vigente(): void
    {
        $datos = $this->datos(80);
        $extra = $datos['padron'][0];
        $extra['grupo'] = '2';
        $extra['fila_hash'] = hash('sha256', json_encode($extra));
        $datos['padron'][] = $extra;
        $version = app(PadronDefinitivoService::class)->preparar($this->jornada, $datos, $this->admin, null);
        $this->assertSame(80, $version->personas);
        $this->assertSame(100, $this->jornada->padronConsulta()->count());
        $this->expectException(ValidationException::class);
        app(PadronDefinitivoService::class)->preparar($this->jornada, $this->datos(0), $this->admin, null);
    }

    public function test_administracion_ofrece_carga_privada_y_evitar_correcciones_directas_del_publicado(): void
    {
        $ruta = route('votaciones.admin.padrones.index', ['jornada' => $this->jornada->slug]);
        $this->actingAs($this->admin)->get($ruta)->assertOk()->assertSee('Preparar revisión privada')->assertSee('Certificado de dotación');
        $version = $this->preparar();
        $this->get($ruta)->assertOk()->assertSee('Revisar carga pendiente');
        app(PadronDefinitivoService::class)->publicar($this->jornada, $version, $this->admin);
        $this->get($ruta)->assertOk()->assertSee('Padrón definitivo publicado')->assertDontSee('Validar e importar Excel')->assertDontSee('Revisar carga pendiente');
    }
}
