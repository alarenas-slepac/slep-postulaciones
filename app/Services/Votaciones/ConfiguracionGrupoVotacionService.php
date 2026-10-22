<?php

namespace App\Services\Votaciones;

use App\Models\GrupoVotacion;
use App\Models\JornadaVotacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfiguracionGrupoVotacionService
{
    public function __construct(private readonly BitacoraVotacionService $bitacora) {}

    public function crear(JornadaVotacion $jornada, array $data, User $user): GrupoVotacion
    {
        return DB::transaction(function () use ($jornada, $data, $user) {
            $jornada = $this->bloquearJornada($jornada->id);
            $periodo = $this->periodo($jornada, $data);
            $origen = null;
            if (($data['modo'] ?? 'nuevo') === 'asociar') {
                $origen = GrupoVotacion::query()->lockForUpdate()->findOrFail($data['grupo_origen_id']);
                if ($origen->jornada_votacion_id === $jornada->id || $jornada->grupos()->where('grupo_origen_id', $origen->id)->exists()) {
                    throw ValidationException::withMessages(['grupo_origen_id' => 'Este grupo ya está asociado a la jornada.']);
                }
                $miembros = $origen->miembros()->get()->mapWithKeys(fn ($miembro) => [$miembro->id => ['rol' => $miembro->pivot->rol]])->all();
                $equipo = ['nombre' => $origen->nombre, 'encargado_id' => $origen->encargado_id, 'observacion' => $origen->observacion, 'grupo_origen_id' => $origen->id];
            } else {
                $miembros = $data['miembros'] ?? [];
                $equipo = ['nombre' => $data['nombre'], 'encargado_id' => $data['encargado_id']];
            }
            $this->validarNumero($jornada, (int) $data['numero']);
            $grupo = $jornada->grupos()->create($equipo + $periodo + ['numero' => $data['numero'], 'estado' => GrupoVotacion::PENDIENTE]);
            $grupo->miembros()->sync($miembros);
            if ($origen) {
                // Se copia la configuración, nunca estados, visitas ni bitácoras.
                foreach ($origen->rutas()->lockForUpdate()->get() as $ruta) {
                    $grupo->rutas()->create($ruta->only(['establecimiento_id', 'orden', 'activa']));
                }
            }
            $this->bitacora->registrar($jornada, $origen ? 'grupo_asociado' : 'grupo_creado',
                $origen ? "Se asoció {$grupo->nombre} desde otra jornada." : "Se creó {$grupo->nombre}.",
                $user, $grupo, metadata: $periodo + ($origen ? ['grupo_origen_id' => $origen->id, 'jornada_origen_id' => $origen->jornada_votacion_id] : []));

            return $grupo;
        }, 3);
    }

    public function actualizar(GrupoVotacion $grupo, array $data, User $user): void
    {
        DB::transaction(function () use ($grupo, $data, $user) {
            $jornada = $this->bloquearJornada($grupo->jornada_votacion_id);
            $grupo = GrupoVotacion::query()->lockForUpdate()->findOrFail($grupo->id);
            $periodo = $this->periodo($jornada, $data, $grupo);
            $this->validarNumero($jornada, (int) $data['numero'], $grupo->id);
            $grupo->update($periodo + array_intersect_key($data, array_flip(['nombre', 'numero', 'encargado_id'])));
            $grupo->miembros()->sync($data['miembros'] ?? []);
            $this->bitacora->registrar($jornada, 'grupo_actualizado', "Se actualizó {$grupo->nombre}.", $user, $grupo, metadata: $periodo);
        }, 3);
    }

    private function bloquearJornada(int $id): JornadaVotacion
    {
        $jornada = JornadaVotacion::query()->lockForUpdate()->findOrFail($id);
        if ($jornada->estado !== JornadaVotacion::BORRADOR) {
            throw ValidationException::withMessages(['grupo' => 'Los grupos solo se configuran en borrador.']);
        }

        return $jornada;
    }

    private function periodo(JornadaVotacion $jornada, array $data, ?GrupoVotacion $grupo = null): array
    {
        $inicio = $data['fecha_inicio'] ?? $grupo?->fecha_inicio?->toDateString() ?? $jornada->fecha->toDateString();
        $fin = $data['fecha_fin'] ?? $grupo?->fecha_fin?->toDateString() ?? $jornada->finFuncionamiento()->toDateString();
        if ($inicio < $jornada->fecha->toDateString() || $inicio > $jornada->finFuncionamiento()->toDateString()) {
            throw ValidationException::withMessages(['fecha_inicio' => 'El inicio del grupo debe estar dentro del período de la jornada.']);
        }
        if ($fin < $inicio || $fin > $jornada->finFuncionamiento()->toDateString()) {
            throw ValidationException::withMessages(['fecha_fin' => 'El término del grupo debe ser igual o posterior al inicio y estar dentro del período de la jornada.']);
        }

        return ['fecha_inicio' => $inicio, 'fecha_fin' => $fin];
    }

    private function validarNumero(JornadaVotacion $jornada, int $numero, ?int $excluir = null): void
    {
        // El bloqueo de la jornada serializa también las altas concurrentes.
        if ($jornada->grupos()->where('numero', $numero)->when($excluir, fn ($q) => $q->whereKeyNot($excluir))->exists()) {
            throw ValidationException::withMessages(['numero' => 'Ya existe un grupo con ese número en la jornada.']);
        }
    }
}
