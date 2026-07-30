<?php

namespace Tests\Feature;

use App\Http\Controllers\Gestion\SolicitudReemplazoGestionController;
use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoJornada;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class SolicitudReemplazoJornadaAdjustmentTest extends TestCase
{
    public function test_coordinador_uatp_can_increase_a_financing_row_when_the_complete_schedule_is_44_hours(): void
    {
        $solicitud = $this->solicitudWithSchedule('pendiente_uatp', [
            $this->jornada('SUBV. GENERAL', 35, 0, 44, 0),
            $this->jornada('SEP', 7, 0, 0, 0),
            $this->jornada('PIE', 2, 0, 0, 0),
        ], 'coordinador_uatp');

        $response = app(SolicitudReemplazoGestionController::class)->actualizarJornadaReemplazo(
            $this->requestFor('coordinador_uatp', [
                'SUBV. GENERAL' => ['basica' => 44, 'media' => 0],
                'SEP' => ['basica' => 0, 'media' => 0],
                'PIE' => ['basica' => 0, 'media' => 0],
            ]),
            $solicitud
        );

        $this->assertTrue($response->isRedirect());
        $this->assertSame('Jornada del reemplazo actualizada correctamente.', session('status'));
    }

    public function test_supervisor_plani_cannot_save_a_complete_schedule_over_44_hours(): void
    {
        $solicitud = $this->solicitudWithSchedule('pendiente_validacion', [
            $this->jornada('SUBV. GENERAL', 35, 0),
            $this->jornada('SEP', 7, 0),
            $this->jornada('PIE', 2, 0),
        ]);

        $response = app(SolicitudReemplazoGestionController::class)->actualizarJornadaReemplazo(
            $this->requestFor('supervisor_plani', [
                'SUBV. GENERAL' => ['basica' => 35.01, 'media' => 0],
                'SEP' => ['basica' => 7, 'media' => 0],
                'PIE' => ['basica' => 2, 'media' => 0],
            ]),
            $solicitud
        );

        $this->assertTrue($response->isRedirect());
        $this->assertSame(
            'La distribución completa de la jornada del reemplazo no puede superar las 44 horas semanales.',
            session('errors')->getBag('default')->first('jornadas')
        );
    }

    private function solicitudWithSchedule(string $estado, array $jornadas, ?string $expectedUpdateRole = null): SolicitudReemplazo
    {
        $titular = new ReemplazoPersonal();
        $titular->estatuto = 'ASISTENTE DE LA EDUCACIÓN';

        $solicitud = Mockery::mock(SolicitudReemplazo::class)->makePartial();
        $solicitud->id = 100;
        $solicitud->estado = $estado;
        $solicitud->setRelation('funcionarioTitular', $titular);
        $solicitud->setRelation('jornadas', new Collection($jornadas));

        if ($expectedUpdateRole !== null) {
            $solicitud->shouldReceive('update')
                ->once()
                ->with(Mockery::on(function (array $attributes) use ($expectedUpdateRole): bool {
                    return $attributes['horas_aula_cronologicas_reemplazo'] === 0
                        && $attributes['horas_aula_pedagogicas_reemplazo'] === 0
                        && $attributes['reemplazo_ajuste_observacion'] === 'Redistribución autorizada'
                        && $attributes['reemplazo_ajuste_user_id'] === 99
                        && $attributes['reemplazo_ajuste_role'] === $expectedUpdateRole
                        && $attributes['reemplazo_ajuste_at'] !== null;
                }))
                ->andReturnTrue();
        } else {
            $solicitud->shouldNotReceive('update');
        }

        return $solicitud;
    }

    private function jornada(
        string $financiamiento,
        float $titularBasica,
        float $titularMedia,
        ?float $expectedBasica = null,
        ?float $expectedMedia = null
    ): SolicitudReemplazoJornada {
        $jornada = Mockery::mock(SolicitudReemplazoJornada::class)->makePartial();
        $jornada->id = match ($financiamiento) {
            'SUBV. GENERAL' => 1,
            'SEP' => 2,
            default => 3,
        };
        $jornada->financiamiento = $financiamiento;
        $jornada->titular_basica = $titularBasica;
        $jornada->titular_media = $titularMedia;

        if ($expectedBasica !== null && $expectedMedia !== null) {
            $jornada->shouldReceive('update')
                ->once()
                ->with([
                    'reemplazo_basica' => $expectedBasica,
                    'reemplazo_media' => $expectedMedia,
                    'reemplazo_total' => $expectedBasica + $expectedMedia,
                ])
                ->andReturnTrue();
        } else {
            $jornada->shouldNotReceive('update');
        }

        return $jornada;
    }

    private function requestFor(string $role, array $jornadas): Request
    {
        $user = new class ($role) {
            public int $id = 99;

            public function __construct(private readonly string $role)
            {
            }

            public function hasAnyRole(array $roles): bool
            {
                return in_array($this->role, $roles, true);
            }

            public function hasRole(string $role): bool
            {
                return $this->role === $role;
            }
        };

        $request = Request::create('/gestion/solicitudes-reemplazo/100/ajuste-reemplazo', 'POST', [
            'jornadas' => $jornadas,
            'reemplazo_ajuste_observacion' => 'Redistribución autorizada',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
