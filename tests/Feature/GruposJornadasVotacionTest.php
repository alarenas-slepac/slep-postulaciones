<?php

namespace Tests\Feature;

use App\Models\GrupoVotacion;
use App\Models\JornadaVotacion;
use App\Models\User;
use App\Models\VisitaVotacion;
use App\Services\Votaciones\BitacoraVotacionService;
use App\Services\Votaciones\ConfiguracionGrupoVotacionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\Support\IsolatedSecurityTestCase;

class GruposJornadasVotacionTest extends IsolatedSecurityTestCase
{
    private User $operador;
    private JornadaVotacion $jornada;

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
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna');
            $t->decimal('latitud', 10, 7)->nullable(); $t->decimal('longitud', 10, 7)->nullable();
        });
        Schema::create('admision_establecimientos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->string('logo_path')->nullable();
        });
        (require database_path('migrations/2026_08_27_210000_create_votaciones_module_tables.php'))->up();
        (require database_path('migrations/2026_10_22_200000_add_periodos_to_votaciones.php'))->up();
        $this->operador = $this->testUser(1, 3);
        foreach (['manage-jornadas', 'manage-grupos', 'manage-rutas', 'operate-group', 'report-incidents'] as $permiso) {
            $this->operador->givePermissionTo(Permission::findOrCreate('votaciones.'.$permiso, 'web'));
        }
        Permission::findOrCreate('votaciones.admin', 'web');
        $this->actingAs($this->operador);
        $this->jornada = $this->crearJornada('destino', '2026-10-23', '2026-10-29');
        Storage::disk('local')->put('test-views/layouts/app.blade.php', '<!doctype html><html><head>@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('test-views'));
    }

    private function crearJornada(string $slug, string $inicio, ?string $fin): JornadaVotacion
    {
        $jornada = JornadaVotacion::create(['nombre' => 'Jornada '.$slug, 'slug' => $slug, 'fecha' => $inicio, 'fecha_fin' => $fin, 'estado' => 'borrador']);
        $jornada->procesos()->attach(DB::table('procesos_votacion')->pluck('id'));

        return $jornada;
    }

    private function datosGrupo(array $cambios = []): array
    {
        return array_replace(['numero' => 1, 'nombre' => 'Equipo de prueba', 'encargado_id' => $this->operador->id,
            'miembros' => [$this->operador->id], 'fecha_inicio' => '2026-10-24', 'fecha_fin' => '2026-10-28'], $cambios);
    }

    private function grupoConRuta(?JornadaVotacion $jornada = null): GrupoVotacion
    {
        $jornada ??= $this->jornada;
        $grupo = $jornada->grupos()->create(['numero' => 1, 'nombre' => 'Equipo de origen', 'encargado_id' => $this->operador->id]);
        $grupo->miembros()->attach($this->operador);
        $id = DB::table('establecimientos')->insertGetId(['rbd' => 99991, 'nombre_establecimiento' => 'Local sintético', 'comuna' => 'Comuna de prueba', 'latitud' => -36.9, 'longitud' => -73.1]);
        $ruta = $grupo->rutas()->create(['establecimiento_id' => $id, 'orden' => 1, 'activa' => true]);
        $ruta->visita()->create(['estado' => 'pendiente']);

        return $grupo;
    }

    private function urlGrupos(): string
    {
        return route('votaciones.admin.grupos.store', $this->jornada);
    }

    public function test_nuevo_grupo_se_asocia_a_la_jornada_y_persiste_su_periodo(): void
    {
        $this->post($this->urlGrupos(), $this->datosGrupo())->assertSessionHasNoErrors()->assertRedirect();
        $grupo = $this->jornada->grupos()->sole();
        $this->assertSame($this->jornada->id, $grupo->jornada->id);
        $this->assertSame('2026-10-24', $grupo->fecha_inicio->toDateString());
        $this->assertSame('2026-10-28', $grupo->fecha_fin->toDateString());
        $this->assertSame(1, $grupo->miembros()->count());
        $this->get(route('votaciones.admin.jornadas.show', $this->jornada))->assertOk()
            ->assertSee('24-10-2026 al 28-10-2026')->assertSee('Asociar grupo existente');
    }

    public function test_asocia_existente_copiando_equipo_y_ruta_sin_copiar_estados_ni_visitas(): void
    {
        $origen = $this->crearJornada('origen', '2026-10-20', '2026-10-21');
        $grupo = $this->grupoConRuta($origen);
        $grupo->update(['estado' => 'finalizado', 'iniciado_at' => '2026-10-20 09:00:00', 'finalizado_at' => '2026-10-20 12:00:00']);
        $grupo->rutas->first()->visita->update(['estado' => 'finalizada']);
        $previo = $grupo->fresh()->toArray();
        $this->get(route('votaciones.admin.jornadas.show', $this->jornada))->assertOk()
            ->assertSee('id="grupo-origen"', false)->assertSee('Equipo de origen')->assertSee('Jornada origen');
        $this->post($this->urlGrupos(), ['modo' => 'asociar', 'grupo_origen_id' => $grupo->id, 'numero' => 4,
            'fecha_inicio' => '2026-10-25', 'fecha_fin' => '2026-10-27'])->assertSessionHasNoErrors()->assertRedirect();
        $copia = $this->jornada->grupos()->sole();
        $this->assertNotSame($grupo->id, $copia->id);
        $this->assertSame($grupo->id, $copia->grupo_origen_id);
        $this->assertSame('pendiente', $copia->estado);
        $this->assertNull($copia->iniciado_at);
        $this->assertNull($copia->finalizado_at);
        $this->assertSame($grupo->encargado_id, $copia->encargado_id);
        $this->assertSame($grupo->miembros()->pluck('users.id')->all(), $copia->miembros()->pluck('users.id')->all());
        $this->assertSame($grupo->rutas()->pluck('establecimiento_id')->all(), $copia->rutas()->pluck('establecimiento_id')->all());
        $this->assertNotSame($grupo->rutas->first()->id, $copia->rutas->first()->id);
        $this->assertNull($copia->rutas->first()->visita);
        $this->assertSame($previo, $grupo->fresh()->toArray());
        $this->assertDatabaseHas('bitacora_votacion', ['evento' => 'grupo_asociado', 'jornada_votacion_id' => $this->jornada->id]);
        $this->get(route('votaciones.admin.jornadas.show', $this->jornada))->assertOk()->assertViewHas('gruposDisponibles', fn ($g) => $g->isEmpty());
        $this->put(route('votaciones.admin.grupos.update', $copia), $this->datosGrupo(['numero' => 4, 'nombre' => 'Solo cambia destino']))->assertSessionHasNoErrors();
        $this->assertSame('Equipo de origen', $grupo->fresh()->nombre);
        $this->jornada->update(['estado' => 'publicada']);
        $this->travelTo(CarbonImmutable::parse('2026-10-25 09:00:00', 'America/Santiago'));
        $this->post(route('votaciones.operacion.grupos.iniciar', $copia))->assertSessionHasNoErrors();
        $this->assertSame('en_traslado', $copia->rutas->first()->visita()->sole()->estado);
        $this->assertSame('finalizada', $grupo->rutas->first()->visita()->sole()->estado);
    }

    public function test_no_duplica_asociacion_ni_permite_asociar_un_grupo_de_la_misma_jornada(): void
    {
        $grupo = $this->grupoConRuta($this->crearJornada('origen', '2026-10-20', null));
        $data = ['modo' => 'asociar', 'grupo_origen_id' => $grupo->id, 'numero' => 1];
        $this->post($this->urlGrupos(), $data)->assertSessionHasNoErrors();
        $this->post($this->urlGrupos(), array_replace($data, ['numero' => 2]))->assertSessionHasErrors('grupo_origen_id');
        $data['grupo_origen_id'] = $this->jornada->grupos()->sole()->id;
        $this->post($this->urlGrupos(), array_replace($data, ['numero' => 2]))->assertSessionHasErrors('grupo_origen_id');
        $this->assertSame(1, $this->jornada->grupos()->count());
    }

    public function test_numeracion_es_independiente_por_jornada_y_unica_dentro_de_ella(): void
    {
        $this->post($this->urlGrupos(), $this->datosGrupo())->assertSessionHasNoErrors();
        $this->post($this->urlGrupos(), $this->datosGrupo())->assertSessionHasErrors('numero');
        $otra = $this->crearJornada('otra', '2026-10-23', '2026-10-29');
        $this->post(route('votaciones.admin.grupos.store', $otra), $this->datosGrupo())->assertSessionHasNoErrors();
        $this->assertSame(1, $this->jornada->grupos()->count());
        $this->assertSame(1, $otra->grupos()->count());
    }

    public static function periodosInvalidos(): array
    {
        return [['2026-10-22', '2026-10-28', 'fecha_inicio'], ['2026-10-30', '2026-10-30', 'fecha_inicio'],
            ['2026-10-24', '2026-10-30', 'fecha_fin'], ['2026-10-24', '2026-10-23', 'fecha_fin']];
    }

    #[DataProvider('periodosInvalidos')]
    public function test_rechaza_grupos_fuera_del_periodo(string $inicio, string $fin, string $campo): void
    {
        $this->post($this->urlGrupos(), $this->datosGrupo(['fecha_inicio' => $inicio, 'fecha_fin' => $fin]))->assertSessionHasErrors($campo);
        $this->assertSame(0, $this->jornada->grupos()->count());
    }

    public function test_fechas_historicas_y_solicitudes_antiguas_heredan_el_periodo_de_la_jornada(): void
    {
        $this->jornada->update(['fecha_fin' => null]);
        $data = $this->datosGrupo(); unset($data['fecha_inicio'], $data['fecha_fin']);
        $this->post($this->urlGrupos(), $data)->assertSessionHasNoErrors();
        $grupo = $this->jornada->grupos()->sole();
        $grupo->update(['fecha_inicio' => null, 'fecha_fin' => null]);
        $this->assertSame('23-10-2026', $grupo->periodo_funcionamiento);
        $this->assertTrue($grupo->dentroDelPeriodo(CarbonImmutable::parse('2026-10-23 23:59:59', 'America/Santiago')));
        $this->assertFalse($grupo->dentroDelPeriodo(CarbonImmutable::parse('2026-10-24 00:00:00', 'America/Santiago')));
    }

    public function test_jornada_admite_rango_y_no_puede_reducirlo_dejando_grupos_fuera(): void
    {
        $data = ['nombre' => 'Jornada nueva', 'slug' => 'nueva', 'fecha' => '2026-10-23', 'fecha_fin' => '2026-10-29', 'procesos' => DB::table('procesos_votacion')->pluck('id')->all()];
        $this->post(route('votaciones.admin.jornadas.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-29', JornadaVotacion::where('slug', 'nueva')->sole()->fecha_fin->toDateString());
        $this->post($this->urlGrupos(), $this->datosGrupo())->assertSessionHasNoErrors();
        $this->put(route('votaciones.admin.jornadas.update', $this->jornada), array_replace($data, ['slug' => 'destino', 'fecha_fin' => '2026-10-27']))->assertSessionHasErrors('fecha_fin');
        $this->assertSame('2026-10-29', $this->jornada->fresh()->fecha_fin->toDateString());
        $this->post(route('votaciones.admin.jornadas.store'), array_replace($data, ['slug' => 'invalida', 'fecha_fin' => '2026-10-22']))->assertSessionHasErrors('fecha_fin');
    }

    public function test_solo_borradores_y_usuarios_con_permiso_pueden_asociar_y_configurar(): void
    {
        $origen = $this->grupoConRuta($this->crearJornada('origen', '2026-10-20', null));
        $data = ['modo' => 'asociar', 'grupo_origen_id' => $origen->id, 'numero' => 1];
        $this->jornada->update(['estado' => 'publicada']);
        $this->post($this->urlGrupos(), $data)->assertSessionHasErrors('grupo');
        $this->jornada->update(['estado' => 'borrador']);
        $this->actingAs($this->testUser(2, 2))->post($this->urlGrupos(), $data)->assertForbidden();
        $this->assertSame(0, $this->jornada->grupos()->count());
    }

    public function test_edicion_de_periodos_y_slug_conserva_limites_y_redirecciona_al_slug_actual(): void
    {
        $this->post($this->urlGrupos(), $this->datosGrupo())->assertSessionHasNoErrors();
        $grupo = $this->jornada->grupos()->sole();
        $this->put(route('votaciones.admin.grupos.update', $grupo), $this->datosGrupo(['fecha_fin' => '2026-10-30']))->assertSessionHasErrors('fecha_fin');
        $this->assertSame('2026-10-28', $grupo->fresh()->fecha_fin->toDateString());
        $data = ['nombre' => 'Jornada ajustada', 'slug' => 'destino-ajustado', 'fecha' => '2026-10-24', 'fecha_fin' => '2026-10-28', 'procesos' => DB::table('procesos_votacion')->pluck('id')->all()];
        $this->put(route('votaciones.admin.jornadas.update', $this->jornada), $data)->assertSessionHasNoErrors()
            ->assertRedirect(route('votaciones.admin.jornadas.show', ['jornada' => 'destino-ajustado']));
    }

    public function test_indice_unico_protege_asociaciones_incluso_sin_la_validacion_http(): void
    {
        $origen = $this->grupoConRuta($this->crearJornada('origen', '2026-10-20', null));
        $data = ['modo' => 'asociar', 'grupo_origen_id' => $origen->id, 'numero' => 1];
        app(ConfiguracionGrupoVotacionService::class)->crear($this->jornada, $data, $this->operador);
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->jornada->grupos()->create(['numero' => 2, 'nombre' => 'Duplicado rechazado', 'grupo_origen_id' => $origen->id]);
    }

    public function test_vistas_muestran_fechas_de_jornada_y_grupo(): void
    {
        $grupo = $this->grupoConRuta();
        $this->get(route('votaciones.admin.jornadas.index'))->assertOk()->assertSee('23-10-2026 al 29-10-2026');
        $this->get(route('votaciones.admin.jornadas.edit', $this->jornada))->assertOk()->assertSee('Fecha de término');
        $this->get(route('votaciones.admin.dashboard', ['jornada' => $this->jornada->slug]))->assertOk()->assertSee('23-10-2026 al 29-10-2026');
        $this->jornada->update(['estado' => 'publicada']);
        $this->get(route('votaciones.operacion.index'))->assertOk()->assertSee('23-10-2026 al 29-10-2026');
        $this->get(route('votaciones.operacion.show', $grupo))->assertOk()->assertSee('INICIAR JORNADA');
    }

    public static function diasOperacion(): array
    {
        return [['2026-10-23 23:59:59', false], ['2026-10-24 00:00:00', true], ['2026-10-28 23:59:59', true], ['2026-10-29 00:00:00', false]];
    }

    #[DataProvider('diasOperacion')]
    public function test_inicio_respeta_ambos_extremos_en_hora_de_chile(string $momento, bool $permitido): void
    {
        $grupo = $this->grupoConRuta();
        $grupo->update(['fecha_inicio' => '2026-10-24', 'fecha_fin' => '2026-10-28']);
        $this->jornada->update(['estado' => 'publicada']);
        $this->travelTo(CarbonImmutable::parse($momento, 'America/Santiago')->utc());
        $response = $this->post(route('votaciones.operacion.grupos.iniciar', $grupo));
        if ($permitido) {
            $response->assertSessionHasNoErrors();
            $this->assertSame('en_traslado', $grupo->fresh()->estado);
        } else {
            $response->assertSessionHasErrors('fecha_hora');
            $this->assertSame('pendiente', $grupo->fresh()->estado);
            $this->assertDatabaseCount('bitacora_votacion', 0);
            $this->get(route('votaciones.operacion.show', $grupo))->assertOk()
                ->assertSee('Fuera del período')->assertDontSee('INICIAR JORNADA');
        }
    }

    public function test_bloquea_visitas_fuera_del_rango_incluso_con_fecha_anterior_manipulada(): void
    {
        $grupo = $this->grupoConRuta();
        $grupo->update(['fecha_inicio' => '2026-10-24', 'fecha_fin' => '2026-10-24']);
        $this->jornada->update(['estado' => 'publicada']);
        $ruta = $grupo->rutas->first();
        $this->post(route('votaciones.operacion.grupos.iniciar', $grupo))->assertSessionHasNoErrors();
        $this->post(route('votaciones.operacion.rutas.iniciar', $ruta), ['fecha_hora' => '2026-10-23 09:00:00'])->assertSessionHasErrors('fecha_hora');
        $this->post(route('votaciones.operacion.rutas.iniciar', $ruta))->assertSessionHasNoErrors();
        $antes = VisitaVotacion::findOrFail($ruta->visita->id)->toArray();
        $this->travelTo(CarbonImmutable::parse('2026-10-25 00:00:00', 'America/Santiago'));
        $this->post(route('votaciones.operacion.rutas.finalizar', $ruta), ['fecha_hora' => '2026-10-24 12:00:00'])->assertSessionHasErrors('fecha_hora');
        $this->assertSame($antes, VisitaVotacion::findOrFail($ruta->visita->id)->toArray());
    }

    public function test_asociacion_es_atomica_si_falla_la_bitacora(): void
    {
        $origen = $this->grupoConRuta($this->crearJornada('origen', '2026-10-20', null));
        $this->mock(BitacoraVotacionService::class)->shouldReceive('registrar')->once()->andThrow(new \RuntimeException('Fallo simulado'));
        try {
            app(ConfiguracionGrupoVotacionService::class)->crear($this->jornada, ['modo' => 'asociar', 'grupo_origen_id' => $origen->id, 'numero' => 1], $this->operador);
            $this->fail('Debió fallar la bitácora.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado', $e->getMessage());
        }
        $this->assertSame(0, $this->jornada->grupos()->count());
        $this->assertDatabaseCount('grupos_votacion', 1);
        $this->assertDatabaseCount('rutas_votacion', 1);
        $this->assertDatabaseCount('grupo_votacion_miembros', 1);
    }
}
