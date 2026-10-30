<?php

namespace App\Support;

use App\Models\DocenteHorasProporcion;
use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Excepción acotada al tramo de 16 h aula de Religión 65/35 y jornada de 18 h. */
final class DotacionReligionContratoCalculator
{
    public static function aplica(Collection $filas, ?array $docente = null): bool
    {
        if ($filas->isEmpty() || ! $filas->every(fn ($fila) => DotacionAsignacionCalculator::esAsignacionDocenteReal($fila)
                && DotacionAsignacionCalculator::proportionGroup(data_get($fila, 'proporcion_aplicada')) === '65_35'
                && DotacionAsignacionElegibilidad2027::esReligion([
                    'tipo_asignacion' => data_get($fila, 'tipo_asignacion'),
                    'asignatura_nombre' => data_get($fila, 'asignatura_nombre'),
                ]))) {
            return false;
        }

        $identidades = $filas->map(fn ($fila) => implode('|', [
            data_get($fila, 'establecimiento_id'), data_get($fila, 'anio'), self::rut($fila),
        ]))->unique();
        $aula = round((float) $filas->sum('horas_plan_pedagogicas'), 2);
        if ($identidades->count() !== 1 || self::rut($filas->first()) === '' || $aula <= 0 || $aula > 16
            || ! Schema::hasTable('docente_horas_proporciones')) {
            return false;
        }
        $conversion = DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula('65_35', $aula);
        if ((float) ($conversion['horas_contrato'] ?? 0) !== 19.0
            || (float) ($conversion['horas_aula_tramo'] ?? 0) !== 16.0) {
            return false;
        }

        $primera = $filas->first();
        $docente ??= self::docenteActual($primera);
        if ($docente === null || isset($docente['cupo_contrata_id'])
            || DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? '')) !== self::rut($primera)
            || round((float) ($docente['horas_contrato_base'] ?? $docente['horas_contrato'] ?? 0), 2) !== 18.0
            || round((float) ($docente['horas_contrato'] ?? 0), 2) !== 18.0) {
            return false;
        }

        // La jornada menor debe continuar figurando como válida en la tabla vigente.
        return DotacionLecturaCache::recordar('religion:tramo-18-16', fn () => DocenteHorasProporcion::query()
            ->where('proporcion', '65_35')->where('vigente', true)
            ->where('horas_contrato', 18)->where('horas_aula_pedagogicas', 16)->exists());
    }

    private static function docenteActual(object|array $fila): ?array
    {
        $id = (int) data_get($fila, 'establecimiento_id', 0);
        $anio = (int) data_get($fila, 'anio', 0);
        if ($id <= 0 || $anio <= 0 || ! Schema::hasTable('establecimientos') || ! Schema::hasTable('reemplazos_personal')) {
            return null;
        }
        $docentes = DotacionLecturaCache::recordar('religion:contratos:'.$id.':'.$anio, function () use ($id, $anio) {
            $establecimiento = Establecimiento::find($id);

            // Sin asignaciones: evita que resolver el contrato vuelva a consolidar el aula.
            return $establecimiento ? DotacionEstablecimientoCalculator::docentes($establecimiento, $anio, false) : collect();
        });

        return $docentes->first(fn ($docente) => ($docente['rut_normalizado'] ?? '') === self::rut($fila));
    }

    private static function rut(object|array $fila): string
    {
        return DotacionEstablecimientoCalculator::normalizeRut((string) (
            data_get($fila, 'docente_rut_normalizado') ?: data_get($fila, 'docente_rut', '')
        ));
    }
}
