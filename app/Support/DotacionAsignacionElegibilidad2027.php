<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Reglas compartidas por el selector y la validación de asignaciones de 2027. */
final class DotacionAsignacionElegibilidad2027
{
    /** Usar la necesidad del plan vigente, no el nombre enviado en el formulario. */
    public static function esReligion(array $necesidad): bool
    {
        if (($necesidad['tipo_asignacion'] ?? '') !== 'plan_estudio') { return false; }
        $nombre = trim((string) ($necesidad['asignatura_oficial'] ?? ''));
        if ($nombre === '') { $nombre = (string) ($necesidad['asignatura_nombre'] ?? $necesidad['titulo'] ?? ''); }

        return preg_match('/\bRELIGION\b/', Str::upper(Str::ascii($nombre))) === 1;
    }

    public static function esTrabajoColaborativoSinPrelacion(array $asignacion): bool
    {
        return ($asignacion['tipo_asignacion'] ?? '') === 'pie_colaborativo';
    }

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
