<?php

namespace Tests\Feature;

use App\Models\GrupoVotacion;
use App\Models\JornadaVotacion;
use App\Models\LocalConsultaVotacion;
use App\Models\RutaVotacion;
use App\Models\User;
use App\Services\Votaciones\BitacoraVotacionService;
use App\Services\Votaciones\ConfiguracionGrupoVotacionService;
use App\Services\Votaciones\ConvocadosLocalVotacionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\Support\IsolatedSecurityTestCase;

class ConvocadosLocalVotacionTest extends IsolatedSecurityTestCase
{
    private User $operador;
    private JornadaVotacion $jornada;
    private GrupoVotacion $grupo;
    private RutaVotacion $ruta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(\App\Http\Middleware\EnsureModuleAccess::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-24 12:00:00', 'America/Santiago'));
        Schema::table('permissions', fn (Blueprint $t) => $t->timestamps());
        foreach (['model_has_permissions', 'role_has_permissions'] as $name) {
            Schema::create($name, function (Blueprint $t) {
                $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id')->nullable();
                $t->unsignedBigInteger('model_id')->nullable(); $t->string('model_type')->nullable();
            });
        }
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->unsignedInteger('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna');
            $t->decimal('latitud', 10, 7)->nullable(); $t->decimal('longitud', 10, 7)->nullable();
        });
        Schema::create('admision_establecimientos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->string('logo_path')->nullable();
        });
        foreach (['2026_08_27_210000_create_votaciones_module_tables.php',
            '2026_10_21_210000_create_consulta_local_votacion.php',
            '2026_10_22_200000_add_periodos_to_votaciones.php',
            '2026_10_22_210000_create_ruta_votacion_convocados.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->operador = $this->testUser(1, 3);
        foreach (['manage-jornadas', 'manage-grupos', 'manage-rutas', 'operate-group', 'report-incidents'] as $permission) {
            $this->operador->givePermissionTo(Permission::findOrCreate('votaciones.'.$permission, 'web'));
        }
        Permission::findOrCreate('votaciones.admin', 'web');
        $this->actingAs($this->operador);
        $this->jornada = $this->nuevaJornada('actual');
        $this->grupo = $this->jornada->grupos()->create(['numero' => 1, 'nombre' => 'Grupo de prueba', 'encargado_id' => $this->operador->id, 'fecha_inicio' => '2026-10-24', 'fecha_fin' => '2026-10-26']);
        foreach ([10 => [9501, 'Local principal'], 20 => [9502, 'Segundo local'], 101 => [9001, 'Escuela Uno'],
            102 => [9002, 'Escuela Dos'], 103 => [9003, 'Escuela Tres'], 104 => [9004, 'Escuela Cuatro']] as $id => [$rbd, $nombre]) {
            DB::table('establecimientos')->insert(['id' => $id, 'rbd' => $rbd, 'nombre_establecimiento' => $nombre, 'comuna' => 'Comuna de prueba', 'latitud' => -36.9, 'longitud' => -73.1]);
        }
        $this->ruta = $this->grupo->rutas()->create(['establecimiento_id' => 10, 'orden' => 1, 'activa' => true]);
        $this->filaPadron(9001, 9501, 'Escuela Uno', '2026-10-24');
        $this->filaPadron(9002, 9501, 'Escuela Dos', '2026-10-25');
        $this->filaPadron(9003, 9502, 'Escuela Tres', '2026-10-24');
        $this->filaPadron(9004, 9501, 'Escuela Cuatro', '2026-10-27');
        Storage::disk('local')->put('test-views/layouts/app.blade.php', '<!doctype html><html><head>@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('test-views'));
    }

    private function nuevaJornada(string $slug): JornadaVotacion
    {
        return JornadaVotacion::create(['nombre' => 'Jornada '.$slug, 'slug' => $slug, 'fecha' => '2026-10-23', 'fecha_fin' => '2026-10-29', 'estado' => 'borrador']);
    }

    private function filaPadron(int $origen, int $destino, string $nombre, string $dia, ?JornadaVotacion $jornada = null): LocalConsultaVotacion
    {
        return LocalConsultaVotacion::create(['jornada_votacion_id' => ($jornada ?? $this->jornada)->id,
            'rbd_origen' => $origen, 'establecimiento' => $nombre, 'rbd_local' => $destino,
            'lugar_votacion' => 'Local de prueba', 'dia' => $dia, 'hora_inicio' => '09:30:00']);
    }

    private function convocatoria(?GrupoVotacion $grupo = null, ?RutaVotacion $ruta = null): array
    {
        return app(ConvocadosLocalVotacionService::class)->paraGrupo(($grupo ?? $this->grupo)->fresh())[($ruta ?? $this->ruta)->id];
    }

    private function guardar(array $rbds, string $modo = 'manual')
    {
        return $this->put(route('votaciones.admin.rutas.convocados.update', $this->ruta), ['modo' => $modo, 'establecimientos' => $rbds]);
    }

    public function test_precarga_por_jornada_local_y_periodo_del_grupo_sin_escrituras(): void
    {
        $otra = $this->nuevaJornada('otra');
        $this->filaPadron(9099, 9501, 'Solo otra jornada', '2026-10-24', $otra);
        $antes = DB::table('rutas_votacion')->get()->toJson();
        $data = $this->convocatoria();
        $this->assertFalse($data['personalizada']);
        $this->assertSame(['9002', '9001'], $data['rbds']);
        $this->assertSame('24-10-2026', $data['establecimientos']->firstWhere('rbd', 9001)['dia']);
        $this->assertSame('09:30', $data['establecimientos']->firstWhere('rbd', 9001)['hora']);
        $this->assertSame($antes, DB::table('rutas_votacion')->get()->toJson());
        $this->assertDatabaseCount('ruta_votacion_convocados', 0);
        $this->assertDatabaseCount('bitacora_votacion', 0);
    }

    public function test_el_local_puede_ser_tambien_un_establecimiento_convocado_si_lo_indica_el_padron(): void
    {
        $this->filaPadron(9501, 9501, 'Personal del propio local', '2026-10-24');
        $this->assertContains('9501', $this->convocatoria()['rbds']);
        $this->assertDatabaseCount('rutas_votacion', 1);
    }

    public function test_ajuste_manual_agrega_y_quita_establecimientos_sin_alterar_padron_ni_ruta(): void
    {
        $padron = DB::table('votacion_locales_consulta')->get()->toJson();
        $this->guardar([9001, 9003])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($this->ruta->fresh()->convocados_personalizados);
        $this->assertSame(['9003', '9001'], $this->convocatoria()['rbds']);
        $this->assertSame($padron, DB::table('votacion_locales_consulta')->get()->toJson());
        $this->assertSame(10, $this->ruta->fresh()->establecimiento_id);
        $this->assertDatabaseCount('rutas_votacion', 1);
        $this->assertDatabaseCount('votacion_padron_consulta', 0);
        $this->assertDatabaseHas('bitacora_votacion', ['evento' => 'convocados_local_actualizados', 'ruta_votacion_id' => $this->ruta->id]);
    }

    public function test_ajustes_manuales_se_conservan_y_restablecer_recupera_el_padron_actual(): void
    {
        $this->guardar([9001])->assertSessionHasNoErrors();
        LocalConsultaVotacion::where('rbd_origen', 9001)->delete();
        $this->filaPadron(9099, 9501, 'Escuela nueva sin catálogo', '2026-10-24');
        $this->assertSame(['9001'], $this->convocatoria()['rbds']);
        $this->guardar([], 'padron')->assertSessionHasNoErrors();
        $this->assertFalse($this->ruta->fresh()->convocados_personalizados);
        $this->assertSame(['9002', '9099'], $this->convocatoria()['rbds']);
        $this->assertDatabaseCount('ruta_votacion_convocados', 0);
        $this->assertDatabaseHas('bitacora_votacion', ['evento' => 'convocados_local_desde_padron']);
    }

    public function test_sin_ajuste_manual_la_precarga_sigue_los_cambios_del_padron(): void
    {
        $this->filaPadron(9099, 9501, 'Escuela nueva sin catálogo', '2026-10-24');
        $this->assertContains('9099', $this->convocatoria()['rbds']);
        LocalConsultaVotacion::where('rbd_origen', 9001)->delete();
        $this->assertNotContains('9001', $this->convocatoria()['rbds']);
    }

    public function test_seleccion_manual_vacia_no_se_rellena_automaticamente(): void
    {
        $this->guardar([])->assertSessionHasNoErrors();
        $this->assertTrue($this->ruta->fresh()->convocados_personalizados);
        $this->assertSame([], $this->convocatoria()['rbds']);
        $this->assertSame(2, $this->convocatoria()['desde_padron']);
    }

    public function test_conserva_rbd_del_padron_que_no_existe_en_catalogo_incluso_si_cambia_el_padron(): void
    {
        $this->filaPadron(9099, 9501, 'Escuela histórica', '2026-10-24');
        $this->guardar([9099])->assertSessionHasNoErrors();
        LocalConsultaVotacion::where('rbd_origen', 9099)->delete();
        $this->guardar([9099])->assertSessionHasNoErrors();
        $data = $this->convocatoria();
        $this->assertSame(['9099'], $data['rbds']);
        $this->assertSame('Escuela histórica', $data['establecimientos']->sole()['nombre']);
        $this->assertNull($data['establecimientos']->sole()['dia']);
    }

    public static function seleccionesInvalidas(): array
    {
        return [[['modo' => 'manual', 'establecimientos' => [7777777]]],
            [['modo' => 'manual', 'establecimientos' => [9001, 9001]]],
            [['modo' => 'manual', 'establecimientos' => [-1]]],
            [['modo' => 'manual', 'establecimientos' => ['texto']]],
            [['modo' => 'invalido', 'establecimientos' => []]]];
    }

    #[DataProvider('seleccionesInvalidas')]
    public function test_rechaza_selecciones_invalidas_sin_cambios(array $data): void
    {
        $this->put(route('votaciones.admin.rutas.convocados.update', $this->ruta), $data)->assertSessionHasErrors();
        $this->assertFalse($this->ruta->fresh()->convocados_personalizados);
        $this->assertDatabaseCount('ruta_votacion_convocados', 0);
        $this->assertDatabaseCount('bitacora_votacion', 0);
    }

    public function test_ajuste_de_una_jornada_no_afecta_el_mismo_local_en_otra(): void
    {
        $otra = $this->nuevaJornada('otra');
        $grupo = $otra->grupos()->create(['numero' => 1, 'nombre' => 'Otro grupo', 'encargado_id' => $this->operador->id]);
        $ruta = $grupo->rutas()->create(['establecimiento_id' => 10, 'orden' => 1]);
        $this->filaPadron(9004, 9501, 'Escuela Cuatro', '2026-10-24', $otra);
        $this->guardar([9003])->assertSessionHasNoErrors();
        $this->assertSame(['9004'], $this->convocatoria($grupo, $ruta)['rbds']);
        $this->assertFalse($ruta->fresh()->convocados_personalizados);
    }

    public function test_publicada_no_permite_ajustes_y_la_operacion_solo_consulta(): void
    {
        $this->jornada->update(['estado' => 'publicada']);
        $this->guardar([9003])->assertSessionHasErrors('convocados');
        $this->get(route('votaciones.operacion.show', $this->grupo))->assertOk()
            ->assertSee('Establecimientos convocados (2)')->assertSee('Escuela Uno')->assertSee('Escuela Dos')
            ->assertDontSee('Guardar establecimientos convocados');
        $this->assertFalse($this->ruta->fresh()->convocados_personalizados);
    }

    public function test_permisos_autenticacion_y_csrf_protegen_la_escritura(): void
    {
        auth()->logout();
        $this->guardar([9003])->assertRedirect(route('login'));
        $otro = $this->testUser(2, 2);
        $otro->givePermissionTo('votaciones.manage-jornadas');
        $this->actingAs($otro);
        $this->guardar([9003])->assertForbidden();
        $this->actingAs($this->operador)->withMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $this->app['env'] = 'local';
        $this->guardar([9003])->assertStatus(419);
        $this->assertDatabaseCount('ruta_votacion_convocados', 0);
    }

    public function test_guardado_es_atomico_si_falla_la_bitacora(): void
    {
        $this->guardar([9001])->assertSessionHasNoErrors();
        $this->mock(BitacoraVotacionService::class)->shouldReceive('registrar')->once()->andThrow(new \RuntimeException('Fallo simulado'));
        try {
            app(ConvocadosLocalVotacionService::class)->actualizar($this->ruta, 'manual', [9002, 9003], $this->operador);
            $this->fail('Debió fallar la bitácora.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado', $e->getMessage());
        }
        $this->assertSame(['9001'], $this->convocatoria()['rbds']);
        $this->assertTrue($this->ruta->fresh()->convocados_personalizados);
        $this->assertDatabaseCount('ruta_votacion_convocados', 1);
    }

    public function test_permiso_de_ruta_no_omite_el_control_de_acceso_al_modulo(): void
    {
        Schema::create('modules', function (Blueprint $t) { $t->id(); $t->string('key'); });
        Schema::create('module_role', function (Blueprint $t) { $t->unsignedBigInteger('module_id'); $t->unsignedBigInteger('role_id'); });
        DB::table('modules')->insert(['id' => 1, 'key' => 'votaciones']);
        $otro = $this->testUser(2, 2);
        $otro->givePermissionTo(['votaciones.manage-jornadas', 'votaciones.manage-rutas']);
        $this->actingAs($otro)->withMiddleware(\App\Http\Middleware\EnsureModuleAccess::class);
        $this->guardar([9003])->assertForbidden();
        DB::table('module_role')->insert(['module_id' => 1, 'role_id' => 2]);
        $this->guardar([9003])->assertSessionHasNoErrors();
        $this->assertSame(['9003'], $this->convocatoria()['rbds']);
    }

    public function test_vista_administrativa_distingue_local_y_convocados_con_controles_etiquetados(): void
    {
        $this->get(route('votaciones.admin.jornadas.show', $this->jornada))->assertOk()
            ->assertSee('Locales de votación disponibles')->assertSee('Establecimientos convocados (2)')
            ->assertSee('Escuela Uno')->assertSee('Escuela Dos')->assertSee('Guardar establecimientos convocados')
            ->assertSee('for="convocados-'.$this->ruta->id.'"', false);
    }

    public function test_sin_migracion_de_ajustes_se_puede_consultar_precarga_y_el_guardado_informa_actualizacion(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::drop('ruta_votacion_convocados');
        $this->assertSame(['9002', '9001'], $this->convocatoria()['rbds']);
        $this->guardar([9003])->assertSessionHasErrors('convocados');
        $this->get(route('votaciones.admin.jornadas.show', $this->jornada))->assertOk()
            ->assertSee('Los ajustes manuales estarán disponibles')->assertDontSee('Guardar establecimientos convocados');
    }

    public function test_incorporar_grupo_a_otra_jornada_precarga_convocados_del_destino(): void
    {
        $this->guardar([9001])->assertSessionHasNoErrors();
        $destino = $this->nuevaJornada('destino');
        $this->filaPadron(9004, 9501, 'Escuela Cuatro', '2026-10-24', $destino);
        $copia = app(ConfiguracionGrupoVotacionService::class)->crear($destino,
            ['modo' => 'asociar', 'grupo_origen_id' => $this->grupo->id, 'numero' => 1], $this->operador);
        $rutaNueva = $copia->rutas()->sole();
        $this->assertSame(['9004'], $this->convocatoria($copia, $rutaNueva)['rbds']);
        $this->assertFalse($rutaNueva->convocados_personalizados);
        $this->assertSame(['9001'], $this->convocatoria()['rbds']);
    }
}
