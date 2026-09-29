<?php

namespace Tests\Feature;

use App\Http\Controllers\Gestion\SolicitudReemplazoGestionController;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SolicitudReemplazoReasignacionFechaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('reemplazos_personal', function (Blueprint $table) {
            $table->id();
            $table->string('estatuto');
        });
        Schema::create('areas_desempeno', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
        });
        Schema::create('postulant_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('estamento');
            $table->unsignedBigInteger('area_desempeno_id')->nullable();
        });
        Schema::create('solicitudes_reemplazo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reemplazo_personal_id');
            $table->unsignedBigInteger('area_desempeno_id');
            $table->unsignedBigInteger('postulant_profile_id')->nullable();
            $table->string('estado');
            $table->date('fecha_inicio');
            $table->date('fecha_termino');
            $table->date('fecha_inicio_trabajo')->nullable();
            $table->unsignedBigInteger('reasignacion_postulante_from')->nullable();
            $table->text('reasignacion_postulante_motivo')->nullable();
            $table->unsignedBigInteger('reasignacion_postulante_by')->nullable();
            $table->dateTime('reasignacion_postulante_at')->nullable();
            $table->timestamps();
        });
        Schema::create('solicitud_reemplazo_jornadas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitud_reemplazo_id');
            $table->decimal('reemplazo_total', 8, 2);
        });

        DB::table('reemplazos_personal')->insert(['id' => 1, 'estatuto' => 'DOCENTE']);
        DB::table('areas_desempeno')->insert(['id' => 1, 'slug' => 'docente_general']);
        DB::table('postulant_profiles')->insert([
            ['id' => 1, 'estamento' => 'docente'],
            ['id' => 2, 'estamento' => 'docente'],
        ]);
    }

    public function test_reasignacion_usa_nueva_fecha_para_disponibilidad_y_actualiza_solicitud(): void
    {
        $solicitud = $this->solicitud('derivada_slep');
        $ocupacionAnterior = $this->solicitud('aceptada', 2, '2026-10-01', '2026-10-10');
        DB::table('solicitud_reemplazo_jornadas')->insert([
            ['solicitud_reemplazo_id' => $solicitud->id, 'reemplazo_total' => 20],
            ['solicitud_reemplazo_id' => $ocupacionAnterior->id, 'reemplazo_total' => 40],
        ]);

        app(SolicitudReemplazoGestionController::class)->slepReasignarPostulante(
            $this->request('2026-10-15'), $solicitud
        );

        $solicitud->refresh();
        $this->assertSame('2026-10-15', $solicitud->fecha_inicio->toDateString());
        $this->assertNull($solicitud->fecha_inicio_trabajo);
        $this->assertSame(2, (int) $solicitud->postulant_profile_id);
        $this->assertSame(1, (int) $solicitud->reasignacion_postulante_from);
    }

    public function test_reasignacion_aceptada_sincroniza_fecha_de_inicio_de_trabajo(): void
    {
        $solicitud = $this->solicitud('aceptada');
        $solicitud->fecha_inicio_trabajo = '2026-10-01';
        $solicitud->save();

        app(SolicitudReemplazoGestionController::class)->slepReasignarPostulante(
            $this->request('2026-10-15'), $solicitud
        );

        $solicitud->refresh();
        $this->assertSame('2026-10-15', $solicitud->fecha_inicio->toDateString());
        $this->assertSame('2026-10-15', $solicitud->fecha_inicio_trabajo->toDateString());
    }

    public function test_reasignacion_rechaza_fecha_posterior_al_termino(): void
    {
        $solicitud = $this->solicitud('derivada_slep');

        try {
            app(SolicitudReemplazoGestionController::class)->slepReasignarPostulante(
                $this->request('2026-11-01'), $solicitud
            );
            $this->fail('La fecha posterior al término debió ser rechazada.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('fecha_inicio', $exception->errors());
            $this->assertSame('2026-10-01', $solicitud->fresh()->fecha_inicio->toDateString());
        }
    }

    public function test_selector_de_reasignacion_valida_la_fecha_elegida(): void
    {
        $solicitud = $this->solicitud('derivada_slep');
        $request = Request::create('/solicitudes-reemplazo/1/ajax/postulantes', 'GET', [
            'mode' => 'reasignar',
            'fecha_inicio' => '2026-11-01',
        ]);
        $request->setUserResolver(fn () => $this->adminUser());
        $this->assertSame('reasignar', $request->query('mode'));
        $this->assertSame('2026-11-01', $request->query('fecha_inicio'));

        try {
            app(SolicitudReemplazoGestionController::class)->ajaxPostulantesOt($request, $solicitud);
            $this->fail('El selector debió rechazar una fecha posterior al término.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('fecha_inicio', $exception->errors());
        }
    }

    private function solicitud(string $estado, int $postulante = 1, string $inicio = '2026-10-01', string $termino = '2026-10-31'): SolicitudReemplazo
    {
        return SolicitudReemplazo::create([
            'reemplazo_personal_id' => 1,
            'area_desempeno_id' => 1,
            'postulant_profile_id' => $postulante,
            'estado' => $estado,
            'fecha_inicio' => $inicio,
            'fecha_termino' => $termino,
        ]);
    }

    private function request(string $inicio): Request
    {
        $request = Request::create('/solicitudes-reemplazo/1/reasignar-postulante', 'POST', [
            'fecha_inicio' => $inicio,
            'postulant_profile_id' => 2,
            'reasignacion_postulante_motivo' => 'Corrección de fecha y postulante.',
        ]);
        $request->setUserResolver(fn () => $this->adminUser());

        return $request;
    }

    private function adminUser(): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->shouldReceive('hasRole')->andReturnUsing(fn ($role) => $role === 'admin');
        $user->shouldReceive('hasAnyRole')->andReturn(true);

        return $user;
    }
}
