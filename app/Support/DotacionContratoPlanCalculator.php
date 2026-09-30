<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Convierte el aula acumulada por docente sin reescribir registros históricos. */
class DotacionContratoPlanCalculator
{
    public static function consolidar(Collection $asignaciones): Collection
    {
        if (! Schema::hasTable('docente_horas_proporciones')) {
            return $asignaciones;
        }

        $acumuladas = [];
        $contratos = [];
        // El orden coincide con el recálculo al guardar. Se conserva el orden
        // original de presentación y cada fila aporta sólo el incremento real.
        foreach ($asignaciones->sortBy(fn ($row) => (int) data_get($row, 'id', 0)) as $indice => $row) {
            $proporcion = self::proporcion($row);
            $rut = DotacionEstablecimientoCalculator::normalizeRut((string) (
                data_get($row, 'docente_rut_normalizado') ?: data_get($row, 'docente_rut', '')
            ));
            $aula = data_get($row, 'horas_plan_pedagogicas');
            if ($proporcion === null || $rut === '' || $aula === null || (float) $aula <= 0) {
                continue;
            }
            $grupo = implode('|', [
                data_get($row, 'establecimiento_id', ''), data_get($row, 'anio', ''), $rut,
                DotacionAsignacionCalculator::proportionGroup(data_get($row, 'proporcion_aplicada')),
            ]);
            $previas = $acumuladas[$grupo] ?? ['aula' => 0.0, 'contrato' => 0.0];
            $totalAula = round($previas['aula'] + (float) $aula, 2);
            $contrato = (float) DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula(
                $proporcion, $totalAula
            )['horas_contrato'];
            $contratos[$indice] = round($contrato - $previas['contrato'], 2);
            $acumuladas[$grupo] = ['aula' => $totalAula, 'contrato' => $contrato];
        }

        return $asignaciones->map(function ($row, $indice) use ($contratos) {
            if (! array_key_exists($indice, $contratos)) {
                return $row;
            }
            // Trabajar sobre copias evita cambiar las instancias recibidas o
            // persistir accidentalmente un cálculo al consultar un informe.
            if (is_array($row)) {
                $row['horas_contrato'] = $contratos[$indice];
            } else {
                $row = clone $row;
                $row->horas_contrato = $contratos[$indice];
            }

            return $row;
        });
    }

    /** Contrato que se libera al retirar una asignación del total del docente. */
    public static function horasLiberadas(Collection $asignaciones, object|array $actual): float
    {
        $id = (int) data_get($actual, 'id', 0);
        if ($id <= 0 || ! $asignaciones->contains(fn ($row) => (int) data_get($row, 'id', 0) === $id)) {
            return max(0.0, (float) data_get($actual, 'horas_contrato', 0));
        }
        $sinActual = $asignaciones->reject(fn ($row) => (int) data_get($row, 'id', 0) === $id);

        return max(0.0, round(
            (float) self::consolidar($asignaciones)->sum('horas_contrato')
            - (float) self::consolidar($sinActual)->sum('horas_contrato'), 2
        ));
    }

    private static function proporcion(object|array $row): ?string
    {
        if (! DotacionAsignacionCalculator::esAsignacionDocenteReal($row)
            || ! in_array(data_get($row, 'tipo_asignacion'), ['plan_estudio', 'acompanamiento_parvularia'], true)) {
            return null;
        }
        // Mantiene las reglas especiales NT sin JEC y las bases proporcionales
        // históricas. La regla vigente NT JEC CPEIP ya agrupa aula y acompañamiento.
        if (data_get($row, 'proporcion_aplicada') === 'NT JEC · CPEIP 65/35') {
            return DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL;
        }

        return match (DotacionAsignacionCalculator::proportionGroup(data_get($row, 'proporcion_aplicada'))) {
            '65_35' => DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL,
            '60_40' => DocenteHorasNoLectivasCalculator::PROPORCION_PRIORITARIOS,
            default => null,
        };
    }
}
