<?php

namespace App\Support;

use App\Models\EstablecimientoCurso;
use Illuminate\Support\Str;

/** Bases contractuales completas; PIE y refuerzo de otro docente van aparte. */
class DotacionParvulariaCalculator
{
    public static function conJec(EstablecimientoCurso $curso, ?string $proporcion = null): bool
    {
        if (in_array($proporcion, ['nt_jec', 'parvularia_jec_especial_65_35_ld'], true)) {
            return true;
        }
        if (in_array($proporcion, ['nt_sin_jec', 'parvularia_sin_jec_especial_65_35_ld'], true)) {
            return false;
        }
        $texto = Str::of(collect([
            $curso->regimen_jec, $curso->jornada, $curso->tipo_jornada,
            $curso->planEstudio?->nombre_plan, $curso->planEstudio?->nombre, $curso->planEstudio?->regimen_jec,
        ])->filter()->implode(' '))->ascii()->upper()->toString();

        return ! str_contains($texto, 'SIN JEC') && str_contains($texto, 'JEC');
    }

    public static function base(EstablecimientoCurso $curso, ?bool $conJec = null): float
    {
        if ($conJec ?? self::conJec($curso)) {
            return 55.0;
        }
        $nivel = Str::of(($curso->curso?->codigo ?? '').' '.($curso->curso?->nombre ?? '').' '.$curso->nombre_seccion)
            ->ascii()->upper()->toString();

        return str_contains($nivel, 'NT2') ? 31.0 : 35.0;
    }

    public static function convertir(float $horas, float $totalPlan, float $base, bool $conJec): array
    {
        // Sin un total de referencia no se inventa una equivalencia para la asignación.
        $contrato = $totalPlan > 0 ? round(max(0.0, $horas) / $totalPlan * $base, 4) : 0.0;

        return [
            'proporcion' => $conJec ? 'parvularia_jec_especial_65_35_ld' : 'parvularia_sin_jec_especial_65_35_ld',
            'proporcion_label' => 'NT '.($conJec ? 'Con JEC' : 'Sin JEC').' · base contractual '.$base.' h',
            'origen_proporcion' => 'regla_especial_parvularia',
            'origen_proporcion_label' => 'Base contractual completa de Educación Parvularia',
            'horas_aula_cronologicas' => round(max(0.0, $horas) * 45 / 60, 4),
            'horas_contrato_equivalente' => $contrato,
            // Se conserva el nombre de la clave histórica, sin ceil por asignatura.
            'horas_contrato_equivalente_redondeado' => round($contrato, 2),
            'horas_contrato' => $contrato,
            'horas_contrato_redondeadas' => round($contrato, 2),
            'parvularia_base_contrato' => $base,
            'parvularia_horas_plan_total' => $totalPlan,
            'horas_libre_disposicion_parvularia' => 0.0,
            'motivo' => $totalPlan > 0
                ? 'Reparto proporcional: '.$horas.' / '.$totalPlan.' h de plan × '.$base.' h de contrato. PIE y refuerzo de otro docente se contabilizan aparte.'
                : 'Falta el total de horas del plan para distribuir la base contractual de Parvularia.',
        ];
    }

    /** Conserva un único refuerzo (el máximo) por grupo; no duplica integrantes. */
    public static function consolidarRefuerzos(array $refuerzos, iterable $grupos): array
    {
        foreach ($grupos as $grupo) {
            $ids = collect(data_get($grupo, 'miembros', []))
                ->map(fn ($miembro) => (int) (data_get($miembro, 'establecimiento_curso_id') ?? data_get($miembro, 'id')))
                ->filter()->unique()->values();
            $presentes = $ids->filter(fn ($id) => isset($refuerzos[$id]));
            if (! (bool) data_get($grupo, 'activo', true) || $presentes->isEmpty()) {
                continue;
            }
            $representante = $presentes->sortByDesc(fn ($id) => $refuerzos[$id]['horas_plan'])->first();
            $detalle = $refuerzos[$representante];
            foreach ($presentes as $id) {
                unset($refuerzos[$id]);
            }
            $refuerzos[$representante] = $detalle;
        }

        return $refuerzos;
    }
}
