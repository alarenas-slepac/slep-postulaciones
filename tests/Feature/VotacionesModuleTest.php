<?php

namespace Tests\Feature;

use App\Models\BitacoraVotacion;
use App\Models\Establecimiento;
use App\Models\GrupoVotacion;
use App\Models\IncidenciaVotacion;
use App\Models\JornadaVotacion;
use App\Models\RutaVotacion;
use App\Models\User;
use App\Models\VisitaVotacion;
use App\Services\Votaciones\EstadoPublicoVotacionService;
use App\Services\Votaciones\OperacionVotacionService;
use App\Services\Votaciones\RutaVialVotacionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class VotacionesModuleTest extends TestCase
{
    private object $migration;

    private object $permissionMigration;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('v', 32))]);
        Cache::flush();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('rut')->unique();
            $table->string('nombres');
            $table->string('apellido_paterno');
            $table->string('apellido_materno');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cod_estab')->unique();
            $table->unsignedInteger('rbd')->unique();
            $table->string('nombre_establecimiento');
            $table->string('comuna')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->timestamps();
        });
        Schema::create('admision_establecimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
        $this->permissionMigration = require database_path('migrations/2025_09_13_011857_create_permission_tables.php');
        $this->permissionMigration->up();
        $this->migration = require database_path('migrations/2026_08_27_210000_create_votaciones_module_tables.php');
        $this->migration->up();
        $this->operator = $this->crearUsuario('111111111', 'Operador', 'de', 'Prueba', 'operador@example.test');
        $permissions = collect([
            'votaciones.manage-jornadas',
            'votaciones.manage-grupos',
            'votaciones.manage-rutas',
            'votaciones.operate-group',
            'votaciones.report-incidents',
            'votaciones.view-history',
            'votaciones.admin',
        ])->map(fn ($name) => Permission::create(['name' => $name, 'guard_name' => 'web']));
        $this->operator->givePermissionTo($permissions->where('name', '!=', 'votaciones.admin'));
    }

    protected function tearDown(): void
    {
        $this->migration->down();
        $this->permissionMigration->down();
        Schema::dropIfExists('admision_establecimientos');
        Schema::dropIfExists('establecimientos');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_operacion_avanza_automaticamente_a_la_siguiente_visita(): void
    {
        [$jornada,$grupo,$rutas] = $this->escenario();
        $service = app(OperacionVotacionService::class);
        $service->iniciarGrupo($grupo, $this->operator);
        $this->assertSame('en_traslado', $rutas[0]->visita()->first()->estado);

        $service->iniciarVisita($rutas[0], $this->operator);
        $service->finalizarVisita($rutas[0], $this->operator);

        $this->assertSame('finalizada', $rutas[0]->visita()->first()->estado);
        $this->assertSame('en_traslado', $rutas[1]->visita()->first()->estado);
        $this->assertSame('en_traslado', $grupo->refresh()->estado);
        $this->assertDatabaseHas('bitacora_votacion', ['jornada_votacion_id' => $jornada->id, 'evento' => 'traslado_iniciado']);
    }

    public function test_crear_jornada_redirige_al_registro_persistido(): void
    {
        $this->withoutMiddleware();
        $procesoId = DB::table('procesos_votacion')->where('codigo', 'CCAF')->value('id');

        $response = $this->actingAs($this->operator)->post(route('votaciones.admin.jornadas.store'), [
            'nombre' => 'Jornada creada desde endpoint',
            'slug' => 'jornada-creada-endpoint',
            'fecha' => '2026-08-28',
            'descripcion' => 'Prueba de regresión del redireccionamiento.',
            'procesos' => [$procesoId],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $jornada = JornadaVotacion::where('slug', 'jornada-creada-endpoint')->firstOrFail();
        $response->assertRedirect(route('votaciones.admin.jornadas.show', $jornada));
        $this->assertDatabaseHas('bitacora_votacion', [
            'jornada_votacion_id' => $jornada->id,
            'evento' => 'jornada_creada',
        ]);
    }

    public function test_usuario_sin_permiso_no_puede_crear_jornada(): void
    {
        $sinPermiso = $this->crearUsuario('222222222', 'Usuario', 'Sin', 'Permiso', 'sin-permiso@example.test');
        $procesoId = DB::table('procesos_votacion')->where('codigo', 'CCAF')->value('id');
        $this->withoutMiddleware();

        $this->actingAs($sinPermiso)->post(route('votaciones.admin.jornadas.store'), [
            'nombre' => 'Jornada no autorizada',
            'slug' => 'jornada-no-autorizada',
            'fecha' => '2026-08-29',
            'procesos' => [$procesoId],
        ])->assertForbidden();

        $this->assertDatabaseMissing('jornadas_votacion', ['slug' => 'jornada-no-autorizada']);
    }

    public function test_slug_de_jornada_no_se_puede_duplicar(): void
    {
        JornadaVotacion::create(['nombre' => 'Original', 'slug' => 'slug-unico', 'fecha' => '2026-08-29', 'estado' => 'borrador']);
        $procesoId = DB::table('procesos_votacion')->where('codigo', 'CCAF')->value('id');
        $this->withoutMiddleware();

        $this->actingAs($this->operator)->post(route('votaciones.admin.jornadas.store'), [
            'nombre' => 'Duplicada',
            'slug' => 'slug-unico',
            'fecha' => '2026-08-30',
            'procesos' => [$procesoId],
        ])->assertSessionHasErrors('slug');

        $this->assertSame(1, JornadaVotacion::where('slug', 'slug-unico')->count());
    }

    public function test_detalle_jornada_usa_el_nombre_completo_del_esquema_real_de_usuarios(): void
    {
        [$jornada] = $this->escenario();
        $jornada->update(['estado' => JornadaVotacion::BORRADOR, 'publica' => false]);

        $this->withoutAccessMiddleware();
        $this->actingAs($this->operator)
            ->get(route('votaciones.admin.jornadas.show', $jornada))
            ->assertOk()
            ->assertSee('Operador de Prueba')
            ->assertSee('data-votaciones-admin-map', false)
            ->assertSee('data-votacion-establecimiento-search', false);
    }

    public function test_ruta_rechaza_un_establecimiento_duplicado(): void
    {
        [, $grupo, $rutas] = $this->escenario();
        $grupo->jornada->update(['estado' => JornadaVotacion::BORRADOR, 'publica' => false]);
        $this->withoutAccessMiddleware();

        $this->actingAs($this->operator)->post(route('votaciones.admin.rutas.store', $grupo), [
            'establecimiento_id' => $rutas[0]->establecimiento_id,
        ])->assertSessionHasErrors('establecimiento_id');

        $this->assertSame(2, $grupo->rutas()->count());
    }

    public function test_orden_de_ruta_es_unico_dentro_del_grupo(): void
    {
        [, $grupo] = $this->escenario();
        $establecimiento = Establecimiento::create(['cod_estab' => 9010, 'rbd' => 8010, 'nombre_establecimiento' => 'RBD adicional', 'comuna' => 'Comuna de prueba']);

        $this->expectException(QueryException::class);
        RutaVotacion::create([
            'grupo_votacion_id' => $grupo->id,
            'establecimiento_id' => $establecimiento->id,
            'orden' => 1,
            'activa' => true,
        ]);
    }

    public function test_publicacion_rechaza_coordenadas_fuera_de_rango(): void
    {
        [$jornada, , $rutas] = $this->escenario();
        $jornada->update(['estado' => JornadaVotacion::BORRADOR, 'publica' => false]);
        $rutas[0]->establecimiento->update(['latitud' => -120, 'longitud' => -73]);
        $this->withoutAccessMiddleware();

        $this->actingAs($this->operator)
            ->post(route('votaciones.admin.jornadas.publicar', $jornada))
            ->assertSessionHasErrors('publicacion');

        $this->assertSame(JornadaVotacion::BORRADOR, $jornada->refresh()->estado);
        $this->assertFalse($jornada->publica);
    }

    public function test_solo_usuarios_asignados_pueden_operar_un_grupo(): void
    {
        [, $grupo] = $this->escenario();
        $otro = $this->crearUsuario('333333333', 'Otro', 'Operador', 'Prueba', 'otro-operador@example.test');
        $otro->givePermissionTo(['votaciones.operate-group', 'votaciones.report-incidents']);

        $this->assertTrue(Gate::forUser($this->operator)->allows('operate', $grupo));
        $this->assertFalse(Gate::forUser($otro)->allows('operate', $grupo));
        $this->assertFalse(Gate::forUser($otro)->allows('reportIncident', $grupo));
    }

    public function test_no_se_puede_finalizar_una_visita_sin_iniciarla(): void
    {
        [, , $rutas] = $this->escenario();

        $this->expectException(ValidationException::class);
        app(OperacionVotacionService::class)->finalizarVisita($rutas[0], $this->operator);
    }

    public function test_no_se_pueden_iniciar_dos_visitas_simultaneas(): void
    {
        [, $grupo, $rutas] = $this->escenario();
        $service = app(OperacionVotacionService::class);
        $service->iniciarGrupo($grupo, $this->operator);
        $service->iniciarVisita($rutas[0], $this->operator);

        $this->expectException(ValidationException::class);
        $service->iniciarVisita($rutas[1], $this->operator);
    }

    public function test_no_se_acepta_un_horario_futuro(): void
    {
        [, $grupo, $rutas] = $this->escenario();
        $service = app(OperacionVotacionService::class);
        $service->iniciarGrupo($grupo, $this->operator);

        $this->expectException(ValidationException::class);
        $service->iniciarVisita($rutas[0], $this->operator, now(config('votaciones.timezone'))->addMinutes(10)->toDateTimeString());
    }

    public function test_incidencia_autorizada_se_reporta_y_resuelve_con_bitacora(): void
    {
        [$jornada, $grupo] = $this->escenario();
        $this->withoutAccessMiddleware();

        $this->actingAs($this->operator)->post(route('votaciones.operacion.incidencias.store', $grupo), [
            'tipo' => 'retraso',
            'detalle_interno' => 'Detalle reservado para coordinación.',
            'publica' => true,
            'mensaje_publico' => 'El recorrido presenta un retraso.',
        ])->assertSessionHasNoErrors();

        $incidencia = IncidenciaVotacion::where('grupo_votacion_id', $grupo->id)->firstOrFail();
        $this->assertSame(IncidenciaVotacion::ABIERTA, $incidencia->estado);
        $this->assertDatabaseHas('bitacora_votacion', ['jornada_votacion_id' => $jornada->id, 'evento' => 'incidencia_reportada']);

        $this->operator->givePermissionTo('votaciones.admin');
        $this->actingAs($this->operator)->patch(route('votaciones.admin.incidencias.resolver', $incidencia), [
            'resolucion' => 'Coordinación completada.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(IncidenciaVotacion::RESUELTA, $incidencia->refresh()->estado);
        $this->assertDatabaseHas('bitacora_votacion', ['jornada_votacion_id' => $jornada->id, 'evento' => 'incidencia_resuelta']);
    }

    public function test_estado_publico_expone_solo_datos_operativos_y_conserva_orden(): void
    {
        [$jornada,$grupo] = $this->escenario();
        IncidenciaVotacion::create(['jornada_votacion_id' => $jornada->id, 'grupo_votacion_id' => $grupo->id, 'tipo' => 'retraso', 'detalle_interno' => 'Nombre privado de prueba', 'mensaje_publico' => 'El recorrido presenta un retraso.', 'publica' => true, 'estado' => 'abierta']);
        $payload = app(EstadoPublicoVotacionService::class)->obtener($jornada);
        $json = json_encode($payload);

        $this->assertSame([1, 2], $payload['grupos'][0]['rutas']->pluck('orden')->all());
        $this->assertStringNotContainsString('operador@example.test', $json);
        $this->assertStringNotContainsString('111111111', $json);
        $this->assertStringNotContainsString('Operador de Prueba', $json);
        $this->assertStringNotContainsString('encargado_id', $json);
        $this->assertStringNotContainsString('Nombre privado de prueba', $json);
        $this->assertStringContainsString('El recorrido presenta un retraso.', $json);
        $this->assertSame('RBD de prueba 1', $payload['grupos'][0]['rutas'][0]['nombre']);
    }

    public function test_estado_publico_normaliza_coordenadas_invalidas_y_reutiliza_logo_de_admision(): void
    {
        Storage::fake('public');
        config(['admision.media_disk' => 'public']);
        [$jornada, , $rutas] = $this->escenario();
        $rutas[0]->establecimiento->update(['latitud' => 95, 'longitud' => -73]);
        DB::table('admision_establecimientos')->insert([
            'establecimiento_id' => $rutas[1]->establecimiento_id,
            'logo_path' => 'admision/logos/establecimiento.webp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::flush();

        $payload = app(EstadoPublicoVotacionService::class)->obtener($jornada);
        $primera = $payload['grupos'][0]['rutas'][0];
        $segunda = $payload['grupos'][0]['rutas'][1];

        $this->assertFalse($primera['coordenadas_validas']);
        $this->assertNull($primera['latitud']);
        $this->assertNull($primera['longitud']);
        $this->assertTrue($segunda['coordenadas_validas']);
        $this->assertStringContainsString('admision/logos/establecimiento.webp', $segunda['logo_url']);
    }

    public function test_no_permite_saltar_el_orden_de_la_ruta(): void
    {
        [, $grupo, $rutas] = $this->escenario();
        $service = app(OperacionVotacionService::class);
        $service->iniciarGrupo($grupo, $this->operator);

        $this->expectException(ValidationException::class);
        $service->iniciarVisita($rutas[1], $this->operator);
    }

    public function test_ultima_visita_finaliza_grupo_y_jornada(): void
    {
        [$jornada,$grupo,$rutas] = $this->escenario();
        $service = app(OperacionVotacionService::class);
        $service->iniciarGrupo($grupo, $this->operator);
        foreach ($rutas as $ruta) {
            $service->iniciarVisita($ruta, $this->operator);
            $service->finalizarVisita($ruta, $this->operator);
        }

        $this->assertSame('finalizado', $grupo->refresh()->estado);
        $this->assertSame('finalizada', $jornada->refresh()->estado);
    }

    public function test_pagina_publica_y_endpoint_de_estado_solo_aceptan_jornada_publicada(): void
    {
        [$jornada] = $this->escenario();
        $this->get(route('public.votaciones.show', $jornada))->assertOk()->assertSee('votaciones-estado-inicial', false);
        $this->getJson(route('public.votaciones.estado', $jornada))->assertOk()->assertJsonPath('jornada.slug', 'jornada-prueba');
        $jornada->update(['publica' => false]);
        Cache::flush();
        $this->get(route('public.votaciones.show', $jornada))->assertNotFound();
    }

    public function test_servicio_vial_entrega_geometria_distancia_tramos_y_cache(): void
    {
        config([
            'votaciones.routing.enabled' => true,
            'votaciones.routing.base_url' => 'https://routing.example.test',
            'votaciones.routing.profile' => 'driving',
        ]);
        Http::fake([
            'routing.example.test/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 2500.4,
                    'duration' => 360.2,
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[-73.01, -36.99], [-73.015, -36.985], [-73.02, -36.98]],
                    ],
                    'legs' => [[
                        'distance' => 2500.4,
                        'duration' => 360.2,
                        'steps' => [[
                            'geometry' => [
                                'type' => 'LineString',
                                'coordinates' => [[-73.01, -36.99], [-73.015, -36.985], [-73.02, -36.98]],
                            ],
                        ]],
                    ]],
                ]],
            ]),
        ]);
        [$jornada, $grupo, $rutas] = $this->escenario();

        $service = app(RutaVialVotacionService::class);
        $payload = $service->obtener($jornada);
        $roadGroup = $payload['grupos'][0];

        $this->assertTrue($roadGroup['disponible']);
        $this->assertSame('vial', $roadGroup['tipo']);
        $this->assertSame(2500.4, $roadGroup['distancia_m']);
        $this->assertSame([-36.99, -73.01], $roadGroup['trazado'][0]);
        $this->assertSame($rutas[0]->id, $roadGroup['tramos'][0]['desde_ruta_id']);
        $this->assertSame($rutas[1]->id, $roadGroup['tramos'][0]['hasta_ruta_id']);
        $this->assertSame($grupo->id, $roadGroup['grupo_id']);

        $service->obtener($jornada);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/route/v1/driving/-73.01,-36.99;-73.02,-36.98')
            && $request['geometries'] === 'geojson'
            && $request['steps'] === 'true');
    }

    public function test_servicio_vial_conserva_linea_directa_si_el_proveedor_falla(): void
    {
        config([
            'votaciones.routing.enabled' => true,
            'votaciones.routing.base_url' => 'https://routing-failure.example.test',
        ]);
        Http::fake(['routing-failure.example.test/*' => Http::response(['code' => 'NoRoute'], 500)]);
        [$jornada] = $this->escenario();

        $roadGroup = app(RutaVialVotacionService::class)->obtener($jornada)['grupos'][0];

        $this->assertFalse($roadGroup['disponible']);
        $this->assertSame('linea_directa', $roadGroup['tipo']);
        $this->assertNull($roadGroup['distancia_m']);
        $this->assertCount(2, $roadGroup['trazado']);
    }

    public function test_endpoints_viales_respetan_publicacion_y_permisos(): void
    {
        config(['votaciones.routing.enabled' => false]);
        [$jornada] = $this->escenario();

        $this->getJson(route('public.votaciones.ruta-vial', $jornada))
            ->assertOk()
            ->assertJsonPath('grupos.0.tipo', 'linea_directa');

        $this->withoutAccessMiddleware();
        $this->actingAs($this->operator)
            ->getJson(route('votaciones.admin.jornadas.ruta-vial', $jornada))
            ->assertOk()
            ->assertJsonPath('grupos.0.grupo_id', $jornada->grupos()->value('id'));

        $sinPermiso = $this->crearUsuario('444444444', 'Usuario', 'Sin', 'Ruta', 'sin-ruta@example.test');
        $this->actingAs($sinPermiso)
            ->getJson(route('votaciones.admin.jornadas.ruta-vial', $jornada))
            ->assertForbidden();

        $jornada->update(['publica' => false]);
        $this->getJson(route('public.votaciones.ruta-vial', $jornada))->assertNotFound();
    }

    public function test_bitacora_no_admite_edicion_ni_eliminacion(): void
    {
        [$jornada] = $this->escenario();
        $evento = BitacoraVotacion::create(['jornada_votacion_id' => $jornada->id, 'evento' => 'prueba', 'descripcion' => 'Original']);
        $this->expectException(\LogicException::class);
        $evento->update(['descripcion' => 'Alterada']);
    }

    public function test_frontend_publico_usa_leaflet_polling_filtros_y_pausa_por_visibilidad(): void
    {
        $script = file_get_contents(resource_path('js/votaciones-publicas.js'));
        $view = file_get_contents(resource_path('views/public/votaciones/show.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/votaciones-public.blade.php'));
        $styles = file_get_contents(resource_path('css/votaciones-publicas.css'));
        $this->assertStringContainsString("from 'leaflet'", $script);
        $this->assertStringContainsString('setInterval(refresh', $script);
        $this->assertStringContainsString('document.hidden', $script);
        $this->assertStringContainsString('Posición en ruta', $script);
        $this->assertStringContainsString('coordenadas_validas', $script);
        $this->assertStringContainsString('loadRoadRoutes', $script);
        $this->assertStringContainsString('formatKm', $script);
        $this->assertStringContainsString('segment.trazado', $script);
        $this->assertStringContainsString('segmentStage', $script);
        $this->assertStringContainsString("window.addEventListener('resize'", $script);
        $this->assertStringContainsString('data-vp-commune', $view);
        $this->assertStringContainsString('data-vp-search', $view);
        $this->assertStringContainsString('data-routing-url', $view);
        $this->assertStringContainsString('data-vp-group-cards', $view);
        $this->assertStringContainsString('data-vp-route-panel', $view);
        $this->assertStringContainsString('Recorridos planificados, no ubicación GPS personal', $view);
        $this->assertStringContainsString('Información pública y operativa', $layout);
        $this->assertStringContainsString('.vp-map-marker--en_votacion', $styles);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $styles);
        $this->assertStringContainsString('data-votaciones-admin-distance-summary', file_get_contents(resource_path('views/votaciones/admin/show.blade.php')));
    }

    private function withoutAccessMiddleware(): void
    {
        $this->withoutMiddleware([
            \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            \App\Http\Middleware\EnsureModuleAccess::class,
            \App\Http\Middleware\TouchLastSeen::class,
            \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    }

    private function crearUsuario(string $rut, string $nombres, string $apellidoPaterno, string $apellidoMaterno, string $email): User
    {
        $id = DB::table('users')->insertGetId([
            'rut' => $rut,
            'nombres' => $nombres,
            'apellido_paterno' => $apellidoPaterno,
            'apellido_materno' => $apellidoMaterno,
            'email' => $email,
            'password' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function escenario(): array
    {
        $jornada = JornadaVotacion::create(['nombre' => 'Jornada de prueba', 'slug' => 'jornada-prueba', 'fecha' => '2026-08-27', 'estado' => 'publicada', 'publica' => true, 'publicada_at' => now(), 'creada_por' => $this->operator->id]);
        $proceso = DB::table('procesos_votacion')->where('codigo', 'CCAF')->value('id');
        $jornada->procesos()->attach($proceso);
        $grupo = GrupoVotacion::create(['jornada_votacion_id' => $jornada->id, 'numero' => 1, 'nombre' => 'Grupo 1', 'encargado_id' => $this->operator->id, 'estado' => 'pendiente']);
        $rutas = [];
        foreach ([1, 2] as $orden) {
            $e = Establecimiento::create(['cod_estab' => 9000 + $orden, 'rbd' => 8000 + $orden, 'nombre_establecimiento' => "RBD de prueba {$orden}", 'comuna' => 'Comuna de prueba', 'latitud' => -37.0 + $orden / 100, 'longitud' => -73.0 - $orden / 100]);
            $ruta = RutaVotacion::create(['grupo_votacion_id' => $grupo->id, 'establecimiento_id' => $e->id, 'orden' => $orden, 'activa' => true]);
            VisitaVotacion::create(['ruta_votacion_id' => $ruta->id, 'estado' => 'pendiente']);
            $rutas[] = $ruta;
        }

        return [$jornada, $grupo, $rutas];
    }
}
