<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class DotacionAsignacionSuspension
{
    public static function bloqueada(int $anio, ?string $rol): bool
    {
        $config = config('dotacion_asignacion.suspension_temporal');
        if (! ($config['habilitada'] ?? false)
            || $anio !== (int) ($config['anio'] ?? 0)
            || $rol !== ($config['rol'] ?? null)) {
            return false;
        }

        $zona = $config['zona_horaria'];
        $ahora = now($zona);

        return $ahora->gte(Carbon::parse($config['desde'], $zona))
            && $ahora->lt(Carbon::parse($config['hasta'], $zona));
    }

    public static function mensaje(): string
    {
        $config = config('dotacion_asignacion.suspension_temporal');
        $reapertura = Carbon::parse($config['hasta'], $config['zona_horaria'])->format('d/m/Y H:i');

        return 'La etapa 6 de asignación de horas de Dotación '.$config['anio'].' está temporalmente en modo sólo consulta por actualizaciones de información. Se habilitará el '.$reapertura.' (hora de Chile). Las demás etapas permanecen disponibles.';
    }
}
