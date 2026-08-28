<?php

namespace App\Services\Votaciones;

use App\Models\GrupoVotacion;
use App\Models\JornadaVotacion;
use App\Models\RutaVotacion;
use App\Models\User;
use App\Models\VisitaVotacion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperacionVotacionService
{
    public function __construct(private readonly BitacoraVotacionService $bitacora) {}

    public function iniciarGrupo(GrupoVotacion $grupo, User $user): GrupoVotacion
    {
        return DB::transaction(function () use ($grupo, $user) {
            $grupo = GrupoVotacion::query()->lockForUpdate()->findOrFail($grupo->id);
            if (! in_array($grupo->jornada->estado, [JornadaVotacion::PUBLICADA, JornadaVotacion::EN_CURSO], true)) {
                throw ValidationException::withMessages(['grupo' => 'La jornada no está habilitada para operar.']);
            }
            if ($grupo->estado !== GrupoVotacion::PENDIENTE) {
                throw ValidationException::withMessages(['grupo' => 'El grupo ya fue iniciado.']);
            }
            $primera = $grupo->rutas()->where('activa', true)->orderBy('orden')->lockForUpdate()->first();
            if (! $primera) {
                throw ValidationException::withMessages(['grupo' => 'El grupo no tiene una ruta activa.']);
            }

            $ahora = now(config('votaciones.timezone'));
            $grupo->update(['estado' => GrupoVotacion::EN_TRASLADO, 'iniciado_at' => $ahora]);
            $grupo->jornada()->update(['estado' => JornadaVotacion::EN_CURSO, 'iniciada_at' => DB::raw('COALESCE(iniciada_at, CURRENT_TIMESTAMP)')]);
            $visita = VisitaVotacion::firstOrCreate(['ruta_votacion_id' => $primera->id]);
            $visita->update(['estado' => VisitaVotacion::EN_TRASLADO, 'inicio_traslado_at' => $ahora]);
            $this->bitacora->registrar($grupo->jornada, 'grupo_iniciado', "Se inició {$grupo->nombre}.", $user, $grupo, $primera);

            return $grupo->refresh();
        }, 3);
    }

    public function iniciarVisita(RutaVotacion $ruta, User $user, ?string $fechaHora = null): VisitaVotacion
    {
        return DB::transaction(function () use ($ruta, $user, $fechaHora) {
            $ruta = RutaVotacion::query()->with('grupo.jornada')->lockForUpdate()->findOrFail($ruta->id);
            $grupo = GrupoVotacion::query()->lockForUpdate()->findOrFail($ruta->grupo_votacion_id);
            if (! in_array($ruta->grupo->jornada->estado, [JornadaVotacion::PUBLICADA, JornadaVotacion::EN_CURSO], true)) {
                throw ValidationException::withMessages(['estado' => 'La jornada no está habilitada para operar.']);
            }
            if (! $ruta->activa) {
                throw ValidationException::withMessages(['estado' => 'El establecimiento no pertenece a la ruta activa.']);
            }
            if ($grupo->estado !== GrupoVotacion::EN_TRASLADO) {
                throw ValidationException::withMessages(['estado' => 'El grupo no está en traslado.']);
            }
            $activa = VisitaVotacion::query()->whereHas('ruta', fn ($q) => $q->where('grupo_votacion_id', $grupo->id))->where('estado', VisitaVotacion::EN_VOTACION)->lockForUpdate()->exists();
            if ($activa) {
                throw ValidationException::withMessages(['estado' => 'El grupo ya tiene una votación en curso.']);
            }
            $visita = VisitaVotacion::query()->lockForUpdate()->firstOrCreate(['ruta_votacion_id' => $ruta->id]);
            $rutaEnTraslado = VisitaVotacion::query()->where('estado', VisitaVotacion::EN_TRASLADO)->whereHas('ruta', fn ($q) => $q->where('grupo_votacion_id', $grupo->id))->lockForUpdate()->value('ruta_votacion_id');
            if ((int) $rutaEnTraslado !== (int) $ruta->id) {
                throw ValidationException::withMessages(['estado' => 'Debe iniciar el establecimiento que corresponde según el orden de la ruta.']);
            }
            if (! in_array($visita->estado, [VisitaVotacion::PENDIENTE, VisitaVotacion::EN_TRASLADO], true)) {
                throw ValidationException::withMessages(['estado' => 'Esta visita ya fue iniciada o finalizada.']);
            }
            $momento = $this->momentoValido($fechaHora);
            if ($visita->inicio_traslado_at && $momento->lt($visita->inicio_traslado_at)) {
                throw ValidationException::withMessages(['fecha_hora' => 'El inicio no puede ser anterior al traslado.']);
            }
            $visita->update(['estado' => VisitaVotacion::EN_VOTACION, 'inicio_votacion_at' => $momento, 'iniciada_por' => $user->id]);
            $grupo->update(['estado' => GrupoVotacion::EN_VOTACION]);
            $this->bitacora->registrar($ruta->grupo->jornada, 'votacion_iniciada', 'Se inició la votación en el establecimiento.', $user, $grupo, $ruta, ['inicio' => $momento->toIso8601String()]);

            return $visita->refresh();
        }, 3);
    }

    public function finalizarVisita(RutaVotacion $ruta, User $user, ?string $fechaHora = null): VisitaVotacion
    {
        return DB::transaction(function () use ($ruta, $user, $fechaHora) {
            $ruta = RutaVotacion::query()->with('grupo.jornada')->lockForUpdate()->findOrFail($ruta->id);
            $grupo = GrupoVotacion::query()->lockForUpdate()->findOrFail($ruta->grupo_votacion_id);
            if (! in_array($ruta->grupo->jornada->estado, [JornadaVotacion::PUBLICADA, JornadaVotacion::EN_CURSO], true)) {
                throw ValidationException::withMessages(['estado' => 'La jornada no está habilitada para operar.']);
            }
            if ($grupo->estado !== GrupoVotacion::EN_VOTACION) {
                throw ValidationException::withMessages(['estado' => 'El grupo no tiene una votación en curso.']);
            }
            $visita = VisitaVotacion::query()->where('ruta_votacion_id', $ruta->id)->lockForUpdate()->first();
            if (! $visita || $visita->estado !== VisitaVotacion::EN_VOTACION) {
                throw ValidationException::withMessages(['estado' => 'La visita no está en votación.']);
            }
            $momento = $this->momentoValido($fechaHora);
            if (! $visita->inicio_votacion_at || $momento->lt($visita->inicio_votacion_at)) {
                throw ValidationException::withMessages(['fecha_hora' => 'El término no puede ser anterior al inicio.']);
            }
            $visita->update(['estado' => VisitaVotacion::FINALIZADA, 'fin_votacion_at' => $momento, 'finalizada_por' => $user->id]);
            $this->bitacora->registrar($ruta->grupo->jornada, 'votacion_finalizada', 'Se finalizó la votación en el establecimiento.', $user, $grupo, $ruta, ['fin' => $momento->toIso8601String()]);

            $siguiente = $grupo->rutas()->where('activa', true)->where('orden', '>', $ruta->orden)->orderBy('orden')->lockForUpdate()->first();
            if ($siguiente) {
                $grupo->update(['estado' => GrupoVotacion::EN_TRASLADO]);
                VisitaVotacion::firstOrCreate(['ruta_votacion_id' => $siguiente->id])->update(['estado' => VisitaVotacion::EN_TRASLADO, 'inicio_traslado_at' => $momento]);
                $this->bitacora->registrar($ruta->grupo->jornada, 'traslado_iniciado', 'Se inició el traslado al siguiente establecimiento.', $user, $grupo, $siguiente);
            } else {
                $grupo->update(['estado' => GrupoVotacion::FINALIZADO, 'finalizado_at' => $momento]);
                $this->bitacora->registrar($ruta->grupo->jornada, 'grupo_finalizado', "Finalizó {$grupo->nombre}.", $user, $grupo);
                if (! $grupo->jornada->grupos()->where('estado', '!=', GrupoVotacion::FINALIZADO)->exists()) {
                    $grupo->jornada->update(['estado' => JornadaVotacion::FINALIZADA, 'finalizada_at' => $momento]);
                    $this->bitacora->registrar($grupo->jornada, 'jornada_finalizada', 'Todos los grupos finalizaron su recorrido.', $user);
                }
            }

            return $visita->refresh();
        }, 3);
    }

    private function momentoValido(?string $value): CarbonImmutable
    {
        $ahora = CarbonImmutable::now(config('votaciones.timezone'));
        $momento = $value ? CarbonImmutable::parse($value, config('votaciones.timezone')) : $ahora;
        if ($momento->gt($ahora)) {
            throw ValidationException::withMessages(['fecha_hora' => 'La hora no puede estar en el futuro.']);
        }

        return $momento;
    }
}
