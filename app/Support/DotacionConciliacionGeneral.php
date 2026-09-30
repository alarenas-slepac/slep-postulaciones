<?php

namespace App\Support;

use Illuminate\Support\Collection;

/** Conciliación informativa; conserva contratos, asignaciones y topes autorizados. */
class DotacionConciliacionGeneral
{
    public static function build(iterable $docentes, array $resumen, array $proceso, array $sobredotacion): array
    {
        if (! ($proceso['aplica'] ?? false)
            || ! array_key_exists('horas_contrato_docentes_aula_general', $resumen)) {
            return [];
        }

        $plan = $colaborativo = $funciones = $reservadas = 0.0;
        foreach ($docentes as $docente) {
            if (isset($docente['cupo_contrata_id'])
                || DotacionAsignacionCalculator::coverageEstamento($docente) !== 'docente') {
                continue;
            }
            $asignaciones = collect($docente['asignaciones'] ?? [])
                ->filter(fn ($row) => DotacionAsignacionCalculator::esAsignacionDocenteReal($row));
            $generales = $asignaciones->filter(function ($row) use ($docente, $proceso): bool {
                $necesidad = data_get($proceso, 'need_blocks.'.data_get($row, 'necesidad_key', ''));
                $bloque = DotacionProceso2027Calculator::bloqueFuncionPorDocente($row, $docente)
                    ?? DotacionProceso2027Calculator::bloqueLibreDisposicionNtOtroDocente($row, $necesidad, $docente)
                    ?? $necesidad
                    ?? DotacionProceso2027Calculator::bloqueParaAsignacion($row);

                return $bloque === 'bloque_1';
            });
            $plan += self::contratoPlan($docente, $asignaciones, $generales);
            foreach ($generales as $row) {
                $tipo = (string) data_get($row, 'tipo_asignacion');
                $horas = max(0.0, (float) data_get($row, 'horas_contrato', 0));
                if ($tipo === 'reserva_no_normativa') {
                    $reservadas += $horas;
                } elseif ($tipo === 'pie_colaborativo') {
                    $colaborativo += $horas;
                } elseif (! in_array($tipo, ['plan_estudio', 'acompanamiento_parvularia'], true)) {
                    $funciones += $horas;
                }
            }
        }

        $contrato = round((float) $resumen['horas_contrato_docentes_aula_general'], 2);
        $asignadas = round($plan + $colaborativo + $funciones, 2);
        $reservadas = round($reservadas, 2);
        $saldo = round($contrato - $asignadas - $reservadas, 2);
        $maximo = data_get($proceso, 'bloques.bloque_1.maximo');
        $planInstitucional = round((float) ($resumen['contrato_plan_general_mas_trabajo_colaborativo_pie'] ?? 0), 2);
        $planIndividual = round($plan + $colaborativo, 2);
        $aaee = round((float) data_get($proceso, 'bloques.bloque_1.asignadas_asistentes_obligatorias', 0), 2);
        $diferenciaPlan = round($planIndividual - $planInstitucional, 2);

        return [
            'contrato' => $contrato,
            'asignadas' => $asignadas,
            'reservadas' => $reservadas,
            'saldo_neto' => $saldo,
            'saldo_nomina' => round((float) data_get($sobredotacion, 'vacantes_por_bloque.plan_estudio.horas_total', 0), 2),
            'maximo' => $maximo === null ? null : round((float) $maximo, 2),
            'diferencia_maximo' => $maximo === null ? null : round($contrato - (float) $maximo, 2),
            'plan_individual' => $planIndividual,
            'plan_institucional' => $planInstitucional,
            'diferencia_plan' => $diferenciaPlan,
            'cobertura_aaee' => $aaee,
            // Incluye margen del tope, necesidades pendientes y otras diferencias
            // de cobertura. No se atribuye todo el desfase a la conversión del plan.
            'otros_ajustes' => $maximo === null ? null : round(
                (float) $maximo - $asignadas - $reservadas - $aaee + $diferenciaPlan, 2
            ),
        ];
    }

    private static function contratoPlan(array $docente, Collection $asignaciones, Collection $generales): float
    {
        $esPlan = fn ($row) => in_array(data_get($row, 'tipo_asignacion'), ['plan_estudio', 'acompanamiento_parvularia'], true);
        $todoPlan = $asignaciones->filter($esPlan);
        $planGeneral = $generales->filter($esPlan);
        $total = 0.0;
        foreach ([
            '65_35' => DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL,
            '60_40' => DocenteHorasNoLectivasCalculator::PROPORCION_PRIORITARIOS,
            'especial' => null,
        ] as $grupo => $proporcion) {
            $filas = $planGeneral->filter(fn ($row) => DotacionAsignacionCalculator::proportionGroup(data_get($row, 'proporcion_aplicada')) === $grupo);
            if ($filas->isEmpty()) {
                continue;
            }
            $campo = 'horas_contrato_'.$grupo;
            $todas = $todoPlan->filter(fn ($row) => DotacionAsignacionCalculator::proportionGroup(data_get($row, 'proporcion_aplicada')) === $grupo);
            if ($grupo === 'especial') {
                $total += (float) $filas->sum('horas_contrato');
            } elseif (array_key_exists($campo, $docente) && $filas->count() === $todas->count()) {
                // Reutiliza exactamente la conversión consolidada del detalle docente.
                $total += (float) $docente[$campo];
            } else {
                $total += (float) DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula(
                    $proporcion, (float) $filas->sum('horas_plan_pedagogicas')
                )['horas_contrato'];
            }
        }

        return round($total, 2);
    }
}
