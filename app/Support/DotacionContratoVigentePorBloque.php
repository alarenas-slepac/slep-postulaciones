<?php

namespace App\Support;

use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use Illuminate\Support\Collection;

class DotacionContratoVigentePorBloque
{
    public function paraEstablecimiento(Establecimiento $establecimiento, int $anio): array
    {
        // Último período del año contractual solicitado, conservando historial,
        // exclusiones y distribución por bloque. El cálculo de proyección puede
        // recurrir a otro año; esta referencia no debe incorporar esas filas.
        $docentes = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio)
            ->filter(fn (array $docente) => (int) ($docente['anio'] ?? 0) === $anio)
            ->values();
        $asignaciones = $docentes->isEmpty() ? collect() : DotacionAsignacionCalculator::assignmentsFor($establecimiento, $anio);
        $docentes = $docentes->concat(app(ContratacionHabilitacionService::class)
            ->docentesVirtuales($establecimiento, $anio, false))
            ->concat(DotacionAsignacionCalculator::plazasPorAsumir($establecimiento, $anio))->values();

        return $this->desdeDocentes($docentes, $asignaciones, (bool) $establecimiento->especial);
    }

    public function desdeDocentes(Collection $docentes, Collection $asignaciones, bool $especial = false): array
    {
        // El bloque explícito del cupo prevalece sobre su título: un reemplazo
        // puede conservar el título diferencial y tener horas en Plan general.
        $esCupo = fn (array $docente) => ! empty($docente['cupo_contrata_id']) || ! empty($docente['es_plaza_por_asumir']);
        $cupos = $docentes->filter($esCupo);
        $docentes = $docentes->reject($esCupo);
        $horasCupos = fn (string $bloque): float => round((float) $cupos->where('cupo_bloque', $bloque)->sum('horas_contrato'), 2);
        $contratoTotal = (float) $docentes->sum(fn (array $docente) => (float) ($docente['horas_contrato'] ?? 0));
        $pie = (float) DotacionAsignacionCalculator::resumenContratoDocentePie($asignaciones, $docentes, $especial)['total'];
        $parvularia = DotacionEstablecimientoCalculator::contratoParvularia($docentes, max(0.0, $contratoTotal - $pie), 0);
        $periodos = $docentes->map(fn (array $docente) => [
            'anio' => (int) ($docente['anio'] ?? 0), 'mes' => (int) ($docente['mes'] ?? 0),
        ])->filter(fn (array $periodo) => $periodo['anio'] > 0 && $periodo['mes'] >= 1 && $periodo['mes'] <= 12)
            ->unique(fn (array $periodo) => $periodo['anio'] * 100 + $periodo['mes'])
            ->sortByDesc(fn (array $periodo) => $periodo['anio'] * 100 + $periodo['mes'])
            ->map(fn (array $periodo) => sprintf('%02d/%d', $periodo['mes'], $periodo['anio']))->implode(', ');
        $aniosCupos = $cupos->filter(fn ($docente) => ! empty($docente['cupo_contrata_id']))->pluck('anio')->filter()->unique()->sort()->implode(', ');
        if ($aniosCupos !== '') {
            $periodos = ($periodos !== '' ? $periodos.' · ' : '').'Cupos por contratar '.$aniosCupos;
        }
        $aniosPorAsumir = $cupos->where('es_plaza_por_asumir', true)->pluck('anio')->filter()->unique()->sort()->implode(', ');
        if ($aniosPorAsumir !== '') {
            $periodos = ($periodos !== '' ? $periodos.' · ' : '').'Plazas por asumir '.$aniosPorAsumir;
        }

        return [
            'contrato_vigente_bloque_1' => round($parvularia['horas_contrato_docentes_aula_general'] + $horasCupos('plan_estudio'), 2),
            'contrato_vigente_bloque_2' => round($parvularia['horas_contrato_docentes_parvularia'] + $horasCupos('parvularia'), 2),
            'contrato_vigente_bloque_3' => round($pie + $horasCupos('pie'), 2),
            'periodo_contractual' => $periodos ?: 'Sin contratos docentes vigentes',
        ];
    }
}
