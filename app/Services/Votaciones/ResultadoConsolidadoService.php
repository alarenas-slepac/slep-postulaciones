<?php

namespace App\Services\Votaciones;

use App\Models\JornadaVotacion;
use App\Models\ResultadoConsolidadoVotacion;
use App\Models\User;
use App\Support\Votaciones\PapeletasVotacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResultadoConsolidadoService
{
    public function __construct(private BitacoraVotacionService $bitacora) {}

    /** Incluye revisiones y catálogo para rechazar una revisión que ya quedó desactualizada. */
    public function huella(Collection $dias): string
    {
        $fuentes = $dias->sortBy(fn ($dia) => $dia->fecha->toDateString())->map(fn ($dia) => [
            'id' => $dia->id, 'fecha' => $dia->fecha->toDateString(), 'version' => $dia->version,
            'conteos' => $this->ordenar($dia->conteos),
        ])->values()->all();

        return hash('sha256', json_encode([$fuentes, PapeletasVotacion::deDias($dias)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function consolidar(JornadaVotacion $jornada, string $huella, User $usuario): ResultadoConsolidadoVotacion
    {
        return DB::transaction(function () use ($jornada, $huella, $usuario) {
            $jornada = JornadaVotacion::query()->lockForUpdate()->findOrFail($jornada->id);
            $dias = $jornada->resultadosDiarios()->orderBy('fecha')->lockForUpdate()->get();
            if ($dias->isEmpty() || ! PapeletasVotacion::deDias($dias)) {
                throw ValidationException::withMessages(['consolidacion' => 'Registra al menos un día de votación antes de consolidar.']);
            }
            if ($dias->contains(fn ($dia) => $dia->fecha->toDateString() > now(config('votaciones.timezone'))->toDateString())) {
                throw ValidationException::withMessages(['consolidacion' => 'No se pueden consolidar fechas futuras.']);
            }
            $actual = $this->huella($dias);
            $this->comprobarHuella($actual, $huella);
            $existente = $jornada->resultadosConsolidados()->where('huella', $actual)->latest('version')->first();
            if ($existente) return $existente;

            $consolidado = $jornada->resultadosConsolidados()->create([
                'version' => (int) $jornada->resultadosConsolidados()->max('version') + 1,
                'huella' => $actual, 'creado_por' => $usuario->id,
                // Sólo cantidades y fechas: nunca observaciones, usuarios o datos del padrón.
                'dias' => $dias->map(fn ($dia) => ['fecha' => $dia->fecha->toDateString(), 'conteos' => $dia->conteos])->all(),
                'resumen' => PapeletasVotacion::resumir(PapeletasVotacion::deDias($dias), $dias),
            ]);
            $this->bitacora->registrar($jornada, 'resultados_consolidados', 'Se consolidaron todos los días registrados.', $usuario,
                metadata: ['consolidado_id' => $consolidado->id, 'version' => $consolidado->version, 'fechas' => $dias->map(fn ($dia) => $dia->fecha->toDateString())->all()]);

            return $consolidado;
        });
    }

    public function publicar(JornadaVotacion $jornada, ResultadoConsolidadoVotacion $consolidado, bool $publicar, User $usuario): void
    {
        DB::transaction(function () use ($jornada, $consolidado, $publicar, $usuario): void {
            $jornada = JornadaVotacion::query()->lockForUpdate()->findOrFail($jornada->id);
            $consolidado = $jornada->resultadosConsolidados()->lockForUpdate()->findOrFail($consolidado->id);
            if ($publicar) {
                if ($jornada->estado === JornadaVotacion::SUSPENDIDA) {
                    throw ValidationException::withMessages(['consolidacion' => 'La jornada está suspendida y no se pueden publicar sus resultados.']);
                }
                $dias = $jornada->resultadosDiarios()->orderBy('fecha')->lockForUpdate()->get();
                $this->comprobarHuella($this->huella($dias), $consolidado->huella);
                if ($consolidado->publicado_at && ! $consolidado->retirado_at) return;
                $anteriores = $jornada->resultadosConsolidados()->publicados()->whereKeyNot($consolidado->id)->get();
                foreach ($anteriores as $anterior) $anterior->update(['retirado_at' => now(), 'retirado_por' => $usuario->id]);
                $consolidado->update(['publicado_at' => now(), 'publicado_por' => $usuario->id, 'retirado_at' => null, 'retirado_por' => null]);
            } else {
                if (! $consolidado->publicado_at || $consolidado->retirado_at) return;
                $consolidado->update(['retirado_at' => now(), 'retirado_por' => $usuario->id]);
            }
            $this->bitacora->registrar($jornada, $publicar ? 'consolidado_publicado' : 'consolidado_retirado',
                $publicar ? 'Se publicó el consolidado de todos los días registrados.' : 'Se retiró el consolidado de la vista pública.', $usuario,
                metadata: ['consolidado_id' => $consolidado->id, 'version' => $consolidado->version,
                    'anterior_id' => $publicar ? $anteriores->first()?->id : null]);
        });
    }

    private function comprobarHuella(string $actual, string $esperada): void
    {
        if (! hash_equals($actual, $esperada)) {
            throw ValidationException::withMessages(['consolidacion' => 'Los días registrados cambiaron. Recarga la pantalla y vuelve a consolidar todos los días antes de publicar.']);
        }
    }

    private function ordenar(array $conteos): array
    {
        ksort($conteos);
        foreach ($conteos as &$papeleta) ksort($papeleta);

        return $conteos;
    }
}
