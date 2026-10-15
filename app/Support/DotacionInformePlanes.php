<?php

namespace App\Support;

use App\Models\DotacionCursoCombinado;
use App\Models\Establecimiento;
use Illuminate\Support\Str;

/** Totales del cuadro Cursos y planes; no son horas efectivamente asignadas. */
class DotacionInformePlanes
{
    public function fila(Establecimiento $establecimiento, int $anio): array
    {
        if (DotacionCursoCombinadoCalculator::tablesReady()
            && DotacionCursoCombinado::query()->where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio)->where('activo', true)->exists()) {
            // Las combinaciones requieren las necesidades por asignatura para
            // respetar sus modalidades conjuntas/separadas y su redondeo.
            $cursos = DotacionEstablecimientoCalculator::build($establecimiento, $anio, false, false)['cursos'];
        } else {
            $cursos = DotacionEstablecimientoCalculator::cursosPorNivel($establecimiento, $anio);
            $cursos['resumen_cursos_planes'] = DotacionCursosPlanesResumenCalculator::build($cursos, []);
        }

        return $this->resumir($cursos) + [
            'rbd' => (string) $establecimiento->rbd,
            'nombre' => (string) $establecimiento->nombre_establecimiento,
            'comuna' => (string) $establecimiento->comuna,
        ];
    }

    public function resumir(array $cursos): array
    {
        // Es la misma fuente que utiliza Total establecimiento en el resumen.
        $totales = $cursos['resumen_cursos_planes']['totales'];
        $detalles = collect($cursos['rows'] ?? [])->flatMap(fn ($row) => $row['detalles'] ?? []);
        $modalidades = $detalles->map(function ($detalle): string {
            $regimen = Str::of((string) ($detalle['regimen_jec'] ?? ''))->ascii()->upper()->squish()->toString();

            return match (true) {
                $regimen === 'NO APLICA' => 'No aplica',
                str_contains($regimen, 'SIN JEC') => 'Sin JEC',
                str_contains($regimen, 'JEC') => 'Con JEC',
                default => 'Sin información',
            };
        })->unique();
        // Orden estable aun cuando el primer curso leído sea sin JEC.
        $modalidad = collect(['Con JEC', 'Sin JEC', 'No aplica', 'Sin información'])
            ->filter(fn ($label) => $modalidades->contains($label))->implode(' / ');
        $observaciones = [];
        if ((int) $totales['cursos'] === 0) {
            $observaciones[] = 'Sin cursos activos con matrícula en el año consultado.';
        }
        $sinPlan = $detalles->filter(fn ($detalle) => (float) ($detalle['horas_plan_base'] ?? $detalle['horas'] ?? 0) <= 0)->count();
        if ($sinPlan > 0) {
            $observaciones[] = $sinPlan.' curso(s) sin horas de plan configuradas.';
        }
        if ($modalidades->contains('Sin información')) {
            $observaciones[] = 'Existen cursos sin régimen JEC informado.';
        }

        return [
            'modalidad_jec' => $modalidad ?: 'Sin cursos',
            'matricula' => (int) $totales['matricula'],
            'cursos' => (int) $totales['cursos'],
            'horas_plan' => (float) $totales['horas'],
            'contrato_equivalente' => (float) $totales['horas_contrato_equivalente'],
            'trabajo_colaborativo_pie' => (float) $totales['trabajo_colaborativo_pie'],
            'contrato_mas_pie' => (float) $totales['contrato_mas_trabajo_colaborativo_pie'],
            'observaciones' => implode(' ', $observaciones),
        ];
    }
}
