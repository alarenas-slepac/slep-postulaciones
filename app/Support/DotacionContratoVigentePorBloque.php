<?php

namespace App\Support;

use App\Models\Establecimiento;
use Illuminate\Support\Collection;

class DotacionContratoVigentePorBloque
{
    public function paraEstablecimiento(Establecimiento $establecimiento, int $anio): array
    {
        // Misma nómina y último período contractual que la vista de dotación.
        // docentes() aplica historial, exclusiones y año base de la proyección;
        // no incorpora cupos ficticios ni contratos de asistentes.
        $docentes = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio);
        $asignaciones = $docentes->isEmpty() ? collect() : DotacionAsignacionCalculator::assignmentsFor($establecimiento, $anio);

        return $this->desdeDocentes($docentes, $asignaciones, (bool) $establecimiento->especial);
    }

    public function desdeDocentes(Collection $docentes, Collection $asignaciones, bool $especial = false): array
    {
        $contratoTotal = (float) $docentes->sum(fn (array $docente) => (float) ($docente['horas_contrato'] ?? 0));
        $pie = (float) DotacionAsignacionCalculator::resumenContratoDocentePie($asignaciones, $docentes, $especial)['total'];
        $parvularia = DotacionEstablecimientoCalculator::contratoParvularia($docentes, max(0.0, $contratoTotal - $pie), 0);
        $periodos = $docentes->map(fn (array $docente) => [
            'anio' => (int) ($docente['anio'] ?? 0), 'mes' => (int) ($docente['mes'] ?? 0),
        ])->filter(fn (array $periodo) => $periodo['anio'] > 0 && $periodo['mes'] >= 1 && $periodo['mes'] <= 12)
            ->unique(fn (array $periodo) => $periodo['anio'] * 100 + $periodo['mes'])
            ->sortByDesc(fn (array $periodo) => $periodo['anio'] * 100 + $periodo['mes'])
            ->map(fn (array $periodo) => sprintf('%02d/%d', $periodo['mes'], $periodo['anio']))->implode(', ');

        return [
            'contrato_vigente_bloque_1' => $parvularia['horas_contrato_docentes_aula_general'],
            'contrato_vigente_bloque_2' => $parvularia['horas_contrato_docentes_parvularia'],
            'contrato_vigente_bloque_3' => round($pie, 2),
            'periodo_contractual' => $periodos ?: 'Sin contratos docentes vigentes',
        ];
    }
}
