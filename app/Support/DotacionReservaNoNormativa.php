<?php

namespace App\Support;

use Illuminate\Support\Collection;

class DotacionReservaNoNormativa
{
    public static function bloque(array $docente): string
    {
        return DotacionProceso2027Calculator::bloqueFuncionPorDocente(
            ['tipo_asignacion' => 'reserva_no_normativa'], $docente
        ) ?: 'bloque_1';
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function elegibles(array $proceso): Collection
    {
        return collect($proceso['docentes'] ?? [])->filter(function (array $docente) use ($proceso): bool {
            if (! empty($docente['cupo_contrata_id'])) {
                return false;
            }

            $bloque = self::bloque($docente);

            return (float) data_get($proceso, 'bloques.'.$bloque.'.saldo_maximo', 0) >= 1.0
                && (float) ($docente['horas_disponibles'] ?? 0) >= 1.0;
        })->values();
    }

    /** @param Collection<int, array<string, mixed>> $docentes */
    public static function fase(Collection $docentes): string
    {
        return DotacionPlanTitularPrimero::fase($docentes);
    }

    /** @param Collection<int, array<string, mixed>> $docentes
     *  @return Collection<int, array<string, mixed>>
     */
    public static function opciones(Collection $docentes): Collection
    {
        return DotacionPlanTitularPrimero::opciones($docentes);
    }

    public static function maximoParaDocente(array $proceso, array $docente, string $fase): float
    {
        return max(0.0, round(min(
            (float) ($proceso['capacidad_reserva_no_normativa'] ?? 0),
            (float) data_get($proceso, 'bloques.'.self::bloque($docente).'.saldo_maximo', 0),
            DotacionPlanTitularPrimero::disponibles($docente, $fase)
        ), 2));
    }
}
