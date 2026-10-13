<?php

namespace App\Support;

use App\Models\EstablecimientoCurso;

/** La consolidación curricular puede contener plan común y libre disposición. */
class DotacionAcompanamientoParvularia
{
    public static function aplica(array $necesidad): bool
    {
        $curso = $necesidad['curso'] ?? null;

        return ($necesidad['tipo_asignacion'] ?? '') === 'plan_estudio'
            && $curso instanceof EstablecimientoCurso
            && DotacionProfesionDocenteResolver::esCursoNt($curso)
            && DotacionParvulariaCalculator::conJec($curso, $necesidad['proporcion_key'] ?? null)
            && self::horasLibreDisposicion($necesidad) > 0;
    }

    public static function horasLibreDisposicion(array $necesidad): float
    {
        $total = max(0.0, (float) ($necesidad['horas_plan_requeridas'] ?? 0));
        if (! empty($necesidad['curso_combinado'])) {
            // La cifra se obtiene de las filas del plan vigente, no del nombre
            // personalizado ni de campos enviados por el formulario.
            $libre = $necesidad['curso_combinado_horas_libre_disposicion']
                ?? (! empty($necesidad['curso_combinado_libre_disposicion']) ? $total : 0);

            return round(min($total, max(0.0, (float) $libre)), 2);
        }

        return ($necesidad['subtipo_asignacion'] ?? '') === 'libre_disposicion' ? round($total, 2) : 0.0;
    }

    public static function disponibles(array $necesidad): float
    {
        return self::aplica($necesidad) ? max(0.0, round(
            min(self::horasLibreDisposicion($necesidad), (float) ($necesidad['horas_externas_libre_disposicion'] ?? 0))
            - (float) ($necesidad['horas_acompanamiento_asignadas'] ?? 0), 2
        )) : 0.0;
    }
}
