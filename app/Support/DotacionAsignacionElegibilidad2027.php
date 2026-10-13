<?php

namespace App\Support;

use Illuminate\Support\Collection;

/** Reglas compartidas por el selector y la validación de asignaciones de 2027. */
final class DotacionAsignacionElegibilidad2027
{
    public static function esFuncionSinPrelacion(array $asignacion): bool
    {
        return in_array($asignacion['tipo_asignacion'] ?? '', [
            'funcion_directiva', 'funcion_tecnico_pedagogica', 'plan_normativo', 'otra_funcion',
        ], true);
    }

    public static function puedeRecibirBolsaDiferencial(array $persona): bool
    {
        $perfil = DotacionProfesionDocenteResolver::perfilTitulo($persona);

        // La profesión declarada es autoritativa. Sólo la ausencia de título
        // y los marcadores de ausencia usados por el SGA admiten la excepción.
        return $perfil['es_educacion_diferencial']
            || in_array($perfil['titulo_normalizado'], ['', 'SIN TITULO', 'SIN TITULO DECLARADO'], true);
    }

    /** @param Collection<int, array<string, mixed>> $docentes */
    public static function candidatosParaPrelacion(Collection $docentes, array $asignacion): Collection
    {
        return ($asignacion['tipo_asignacion'] ?? '') === 'pie_educadora_diferencial'
            ? $docentes->filter(fn (array $docente) => self::puedeRecibirBolsaDiferencial($docente))->values()
            : $docentes;
    }
}
