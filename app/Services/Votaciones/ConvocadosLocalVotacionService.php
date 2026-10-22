<?php

namespace App\Services\Votaciones;

use App\Models\Establecimiento;
use App\Models\GrupoVotacion;
use App\Models\JornadaVotacion;
use App\Models\LocalConsultaVotacion;
use App\Models\RutaVotacion;
use App\Models\RutaVotacionConvocado;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ConvocadosLocalVotacionService
{
    public function __construct(private readonly BitacoraVotacionService $bitacora) {}

    public function edicionDisponible(): bool
    {
        return Schema::hasTable('ruta_votacion_convocados')
            && Schema::hasColumn('rutas_votacion', 'convocados_personalizados');
    }

    public function paraJornada(JornadaVotacion $jornada): array
    {
        $jornada->loadMissing('grupos.rutas.establecimiento');

        return $this->resolver($jornada, $jornada->grupos);
    }

    public function paraGrupo(GrupoVotacion $grupo): array
    {
        $grupo->loadMissing(['jornada', 'rutas.establecimiento']);

        return $this->resolver($grupo->jornada, collect([$grupo]));
    }

    /** Sólo información de establecimientos; nunca lee el padrón de personas. */
    private function locales(JornadaVotacion $jornada): Collection
    {
        return Schema::hasTable('votacion_locales_consulta')
            ? LocalConsultaVotacion::query()->where('jornada_votacion_id', $jornada->id)
                ->orderBy('dia')->orderBy('hora_inicio')->orderBy('establecimiento')
                ->get(['rbd_origen', 'establecimiento', 'rbd_local', 'dia', 'hora_inicio'])
            : collect();
    }

    private function resolver(JornadaVotacion $jornada, Collection $grupos): array
    {
        $locales = $this->locales($jornada)->groupBy('rbd_local');
        $rutas = $grupos->flatMap->rutas;
        $configurados = $this->edicionDisponible()
            ? RutaVotacionConvocado::query()->whereIn('ruta_votacion_id', $rutas->pluck('id'))->orderBy('nombre_establecimiento')->get()->groupBy('ruta_votacion_id')
            : collect();
        $resultado = [];
        foreach ($grupos as $grupo) {
            $grupo->setRelation('jornada', $jornada);
            foreach ($grupo->rutas as $ruta) {
                $filas = $locales->get($ruta->establecimiento->rbd, collect())->filter(fn ($fila) =>
                    $fila->dia->toDateString() >= $grupo->inicioFuncionamiento()->toDateString()
                    && $fila->dia->toDateString() <= $grupo->finFuncionamiento()->toDateString()
                );
                $automaticos = $filas->keyBy('rbd_origen')->map(fn ($fila) => [
                    'rbd' => (int) $fila->rbd_origen, 'nombre' => $fila->establecimiento,
                    'dia' => $fila->dia->format('d-m-Y'), 'hora' => substr($fila->hora_inicio, 0, 5),
                ]);
                $personalizada = (bool) $ruta->convocados_personalizados;
                $convocados = $personalizada
                    ? $configurados->get($ruta->id, collect())->map(fn ($fila) => [
                        'rbd' => $fila->rbd, 'nombre' => $fila->nombre_establecimiento,
                        'dia' => $automaticos->get($fila->rbd)['dia'] ?? null,
                        'hora' => $automaticos->get($fila->rbd)['hora'] ?? null,
                    ])
                    : $automaticos;
                $convocados = $convocados->sortBy('nombre')->values();
                $resultado[$ruta->id] = [
                    'personalizada' => $personalizada,
                    'establecimientos' => $convocados,
                    'rbds' => $convocados->pluck('rbd')->map(fn ($rbd) => (string) $rbd)->values()->all(),
                    'desde_padron' => $automaticos->count(),
                ];
            }
        }

        return $resultado;
    }

    public function opciones(JornadaVotacion $jornada): Collection
    {
        $opciones = $this->locales($jornada)->mapWithKeys(fn ($fila) => [(int) $fila->rbd_origen => $fila->establecimiento]);
        foreach (Establecimiento::query()->get(['rbd', 'nombre_establecimiento']) as $establecimiento) {
            $opciones->put($establecimiento->rbd, $establecimiento->nombre_establecimiento);
        }

        return $opciones->sort();
    }

    public function actualizar(RutaVotacion $ruta, string $modo, array $rbds, User $user): void
    {
        if (! $this->edicionDisponible()) {
            throw ValidationException::withMessages(['convocados' => 'Los ajustes manuales requieren completar la actualización del módulo de votaciones.']);
        }
        DB::transaction(function () use ($ruta, $modo, $rbds, $user) {
            $jornada = JornadaVotacion::query()->lockForUpdate()->findOrFail($ruta->grupo->jornada_votacion_id);
            $ruta = RutaVotacion::query()->lockForUpdate()->findOrFail($ruta->id);
            if ($jornada->estado !== JornadaVotacion::BORRADOR) {
                throw ValidationException::withMessages(['convocados' => 'Los establecimientos convocados solo se configuran en jornadas en borrador.']);
            }
            $anteriores = $ruta->convocadosConfigurados()->get();
            if ($modo === 'manual') {
                $opciones = $this->opciones($jornada);
                foreach ($anteriores as $anterior) {
                    if (! $opciones->has($anterior->rbd)) {
                        $opciones->put($anterior->rbd, $anterior->nombre_establecimiento);
                    }
                }
                $rbds = array_values(array_unique(array_map('intval', $rbds)));
                foreach ($rbds as $rbd) {
                    if (! $opciones->has($rbd)) {
                        throw ValidationException::withMessages(['establecimientos' => 'Seleccione establecimientos del catálogo o del padrón de esta jornada.']);
                    }
                }
                $ruta->convocadosConfigurados()->delete();
                foreach ($rbds as $rbd) {
                    $ruta->convocadosConfigurados()->create(['rbd' => $rbd, 'nombre_establecimiento' => $opciones->get($rbd)]);
                }
            } else {
                $ruta->convocadosConfigurados()->delete();
            }
            $ruta->update(['convocados_personalizados' => $modo === 'manual']);
            $this->bitacora->registrar($jornada, $modo === 'manual' ? 'convocados_local_actualizados' : 'convocados_local_desde_padron',
                $modo === 'manual' ? 'Se ajustaron los establecimientos convocados en un local de votación.' : 'Se restablecieron los establecimientos convocados desde el padrón de la jornada.',
                $user, $ruta->grupo, $ruta, ['modo' => $modo, 'rbds_anteriores' => $anteriores->pluck('rbd')->all(), 'rbds' => $modo === 'manual' ? $rbds : []]);
        }, 3);
    }
}
