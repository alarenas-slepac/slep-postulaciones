<?php

namespace App\Support;

use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;

class DotacionDocenteProporcional
{
    public const GRUPOS = ['directiva', 'tecnico_pedagogica', 'aula'];

    public function resumir(int $anio): array
    {
        $totalPorRut = [];
        $horasPorEstablecimiento = [];
        $establecimientos = Establecimiento::query()
            ->where(fn ($q) => $q->whereNull('sala_cuna')->orWhere('sala_cuna', false))->lazyById();
        foreach ($establecimientos as $establecimiento) {
            $id = (int) $establecimiento->id;
            $justificados = array_flip(DotacionSobredotacionJustificacion::rutsJustificados($id, $anio));
            foreach (DotacionAsignacionCalculator::assignmentsFor($establecimiento, $anio) as $asignacion) {
                $rut = DotacionEstablecimientoCalculator::normalizeRut($asignacion->docente_rut_normalizado ?: $asignacion->docente_rut);
                $horas = (float) $asignacion->horas_contrato;
                if ($rut === '' || isset($justificados[$rut]) || ContratacionHabilitacionService::esRutVirtual($rut)
                    || $horas <= 0 || DotacionAsignacionCalculator::coverageEstamento($asignacion) !== 'docente'
                    || $asignacion->tipo_asignacion === 'reserva_no_normativa') {
                    continue;
                }
                $grupo = match (true) {
                    $asignacion->tipo_asignacion === 'funcion_directiva' => 'directiva',
                    $asignacion->subtipo_asignacion === 'libre_disposicion',
                    in_array($asignacion->tipo_asignacion, ['plan_estudio', 'acompanamiento_parvularia',
                        'pie_colaborativo', 'pie_educadora_diferencial'], true) => 'aula',
                    default => 'tecnico_pedagogica',
                };
                $horasPorEstablecimiento[$id][$grupo][$rut] = ($horasPorEstablecimiento[$id][$grupo][$rut] ?? 0.0) + $horas;
                $totalPorRut[$rut] = ($totalPorRut[$rut] ?? 0.0) + $horas;
            }
        }

        $filas = [];
        foreach ($horasPorEstablecimiento as $id => $grupos) {
            foreach ($grupos as $grupo => $horasPorRut) {
                $fracciones = 0.0;
                foreach ($horasPorRut as $rut => $horas) {
                    $fracciones += $horas / $totalPorRut[$rut];
                }
                $filas[$id][$grupo] = ['docentes' => $fracciones, 'horas' => array_sum($horasPorRut)];
            }
        }

        return $filas;
    }
}
