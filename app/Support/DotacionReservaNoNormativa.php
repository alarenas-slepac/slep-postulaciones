<?php

namespace App\Support;

use App\Models\DotacionDocenteExclusion;
use Illuminate\Support\Collection;

class DotacionReservaNoNormativa
{
    public const FUENTE_VINCULACION_TITULAR_PARVULARIA = 'Vinculada desde reserva titular de Educación Parvularia para otras funciones';

    public static function esVinculacionTitularParvularia(object|array $asignacion): bool
    {
        return (int) data_get($asignacion, 'dotacion_funcion_id', 0) > 0
            && data_get($asignacion, 'fuente_calculo') === self::FUENTE_VINCULACION_TITULAR_PARVULARIA;
    }

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
        return self::candidatos($proceso)->filter(fn (array $docente) =>
            self::maximoParaDocente($proceso, $docente, self::faseParaDocente($docente)) >= 1.0
        )->values();
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

        $limites = [
            (float) ($proceso['capacidad_reserva_no_normativa'] ?? 0),
            DotacionPlanTitularPrimero::disponibles($docente, $fase),
        ];
        // El saldo titular de Parvularia puede destinarse al cupo no normativo
        // del establecimiento aunque su bloque curricular esté sin margen.
        if ($fase !== 'titular' || ! DotacionProfesionDocenteResolver::perfilTitulo($docente)['es_educacion_parvulos']) {
            $limites[] = (float) data_get($proceso, 'bloques.'.self::bloque($docente).'.saldo_maximo', 0);
        }

        return max(0.0, round(min($limites), 2));
    }
}
