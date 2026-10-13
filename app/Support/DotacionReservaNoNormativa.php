<?php

namespace App\Support;

use App\Models\DotacionDocenteExclusion;
use Illuminate\Support\Collection;

class DotacionReservaNoNormativa
{
    public static function permiteContrata(array $docente): bool
    {
        $situacion = $docente['exclusion_docente'] ?? null;
        return DotacionDocenteExclusion::tieneFueroMaternal($situacion)
            || ($situacion['motivo'] ?? '') === 'horas_gremiales';
    }

    /** Primero el saldo titular del docente; contrata sólo con situación protegida. */
    public static function faseParaDocente(array $docente): ?string
    {
        if (! empty($docente['cupo_contrata_id'])) { return null; }
        if (DotacionPlanTitularPrimero::disponibles($docente, 'titular') >= 1.0) { return 'titular'; }
        return self::permiteContrata($docente) && DotacionPlanTitularPrimero::disponibles($docente, 'contrata') >= 1.0
            ? 'contrata' : null;
    }

    public static function bloque(array $docente): string
    {
        return DotacionProceso2027Calculator::bloqueFuncionPorDocente(
            ['tipo_asignacion' => 'reserva_no_normativa'], $docente
        ) ?: 'bloque_1';
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function candidatos(array $proceso): Collection
    {
        // Visibilidad del saldo individual, independiente del margen del bloque.
        // El controlador sigue usando elegibles() para autorizar el traspaso.
        return collect($proceso['docentes'] ?? [])->filter(fn (array $docente) =>
            self::faseParaDocente($docente) !== null
        )->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function elegibles(array $proceso): Collection
    {
        return self::candidatos($proceso)->filter(function (array $docente) use ($proceso): bool {
            $bloque = self::bloque($docente);

            return (float) data_get($proceso, 'bloques.'.$bloque.'.saldo_maximo', 0) >= 1.0
                && self::faseParaDocente($docente) !== null;
        })->values();
    }

    /** @param Collection<int, array<string, mixed>> $docentes */
    public static function fase(Collection $docentes): string
    {
        return $docentes->isNotEmpty() && $docentes->every(fn (array $docente) => self::faseParaDocente($docente) === 'contrata')
            ? 'contrata' : 'titular';
    }

    /** @param Collection<int, array<string, mixed>> $docentes
     *  @return Collection<int, array<string, mixed>>
     */
    public static function opciones(Collection $docentes): Collection
    {
        return $docentes->filter(fn (array $docente) =>
            self::faseParaDocente($docente) !== null
        )->values();
    }

    public static function maximoParaDocente(array $proceso, array $docente, string $fase): float
    {
        if ($fase !== self::faseParaDocente($docente)) {
            return 0.0;
        }

        return max(0.0, round(min(
            (float) ($proceso['capacidad_reserva_no_normativa'] ?? 0),
            (float) data_get($proceso, 'bloques.'.self::bloque($docente).'.saldo_maximo', 0),
            DotacionPlanTitularPrimero::disponibles($docente, $fase)
        ), 2));
    }
}
