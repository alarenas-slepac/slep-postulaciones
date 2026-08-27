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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VotacionesModuleTest extends TestCase
{
    private object $migration;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('v', 32))]);
        Cache::flush();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
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
        $this->migration = require database_path('migrations/2026_08_27_210000_create_votaciones_module_tables.php');
        $this->migration->up();
        $id = DB::table('users')->insertGetId(['name' => 'Operador de prueba', 'email' => 'operador@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $this->operator = User::findOrFail($id);
    }

    protected function tearDown(): void
    {
        $this->migration->down();
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

    public function test_estado_publico_expone_solo_datos_operativos_y_conserva_orden(): void
    {
        [$jornada,$grupo] = $this->escenario();
        IncidenciaVotacion::create(['jornada_votacion_id' => $jornada->id, 'grupo_votacion_id' => $grupo->id, 'tipo' => 'retraso', 'detalle_interno' => 'Nombre privado de prueba', 'mensaje_publico' => 'El recorrido presenta un retraso.', 'publica' => true, 'estado' => 'abierta']);
        $payload = app(EstadoPublicoVotacionService::class)->obtener($jornada);
        $json = json_encode($payload);

        $this->assertSame([1, 2], $payload['grupos'][0]['rutas']->pluck('orden')->all());
        $this->assertStringNotContainsString('operador@example.test', $json);
        $this->assertStringNotContainsString('encargado_id', $json);
        $this->assertStringNotContainsString('Nombre privado de prueba', $json);
        $this->assertStringContainsString('El recorrido presenta un retraso.', $json);
        $this->assertSame('RBD de prueba 1', $payload['grupos'][0]['rutas'][0]['nombre']);
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
        $this->assertStringContainsString("from 'leaflet'", $script);
        $this->assertStringContainsString('setInterval(refresh', $script);
        $this->assertStringContainsString('document.hidden', $script);
        $this->assertStringContainsString('data-vp-commune', file_get_contents(resource_path('views/public/votaciones/show.blade.php')));
        $this->assertStringContainsString('data-vp-search', file_get_contents(resource_path('views/public/votaciones/show.blade.php')));
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
