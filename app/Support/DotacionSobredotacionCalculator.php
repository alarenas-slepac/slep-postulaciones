<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DotacionSobredotacionCalculator
{
    public const ALLOWED_ROLES = [
        'admin',
        'coordinador_gdp',
        'supervisor_plani',
        'coordinador_uatp',
    ];

    public static function canView(?string $role): bool
    {
        return in_array($role, self::ALLOWED_ROLES, true);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $docentes
     * @return array{items: Collection<int, array<string, mixed>>, resumen: array<string, float|int>}
     */
    public static function build(iterable $docentes): array
    {
        $analizados = collect($docentes)
            ->map(fn (array $docente) => self::detalleDocente($docente))
            ->values();

        $items = $analizados
            ->filter(fn (array $docente) => $docente['horas_sobredotacion_total'] > 0.01)
            ->sortBy([
                ['horas_sobredotacion_total', 'desc'],
                ['nombre', 'asc'],
            ])
            ->values();

        return [
            'items' => $items,
            'resumen' => [
                'docentes_analizados' => $analizados->count(),
                'docentes_sobredotacion' => $items->count(),
                'horas_contrato_total' => self::sumar($analizados, 'horas_contrato_total'),
                'horas_asignadas_total' => self::sumar($analizados, 'horas_asignadas_total'),
                'horas_sobredotacion_total' => self::sumar($items, 'horas_sobredotacion_total'),
                'horas_sobredotacion_planta' => self::sumar($items, 'horas_sobredotacion_planta'),
                'horas_sobredotacion_contrata' => self::sumar($items, 'horas_sobredotacion_contrata'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function detalleDocente(array $docente): array
    {
        $horasContrato = round(max(0.0, (float) ($docente['horas_contrato'] ?? 0)), 2);
        $horasAsignadas = round(max(0.0, (float) ($docente['horas_asignadas_total'] ?? 0)), 2);
        [$horasPlanta, $horasContrata] = self::contratoPorCalidad($docente, $horasContrato);

        $horasAsignadasConsideradas = min($horasContrato, $horasAsignadas);
        $horasAsignadasPlanta = min($horasPlanta, $horasAsignadasConsideradas);
        $horasAsignadasContrata = min(
            $horasContrata,
            max(0.0, round($horasAsignadasConsideradas - $horasAsignadasPlanta, 2))
        );
        $sobredotacionPlanta = round(max(0.0, $horasPlanta - $horasAsignadasPlanta), 2);
        $sobredotacionContrata = round(max(0.0, $horasContrata - $horasAsignadasContrata), 2);

        return [
            'rut' => (string) ($docente['rut'] ?? ''),
            'nombre' => (string) ($docente['nombre'] ?? 'Docente sin nombre'),
            'funcion' => (string) ($docente['funcion'] ?? 'Sin función declarada'),
            'tipo_contrato' => (string) ($docente['tipo_contrato'] ?? 'Sin tipo contrato'),
            'titularidad' => $docente['titularidad'] ?? null,
            'horas_contrato_total' => $horasContrato,
            'horas_contrato_planta' => $horasPlanta,
            'horas_contrato_contrata' => $horasContrata,
            'horas_asignadas_total' => $horasAsignadas,
            'horas_asignadas_consideradas' => round($horasAsignadasConsideradas, 2),
            'horas_asignadas_planta' => round($horasAsignadasPlanta, 2),
            'horas_asignadas_contrata' => round($horasAsignadasContrata, 2),
            'horas_sobredotacion_total' => round($sobredotacionPlanta + $sobredotacionContrata, 2),
            'horas_sobredotacion_planta' => $sobredotacionPlanta,
            'horas_sobredotacion_contrata' => $sobredotacionContrata,
        ];
    }

    /** @return array{0: float, 1: float} */
    private static function contratoPorCalidad(array $docente, float $horasContrato): array
    {
        $horasPlanta = min($horasContrato, max(0.0, (float) ($docente['horas_planta'] ?? 0)));
        $horasContrata = min(
            max(0.0, $horasContrato - $horasPlanta),
            max(0.0, (float) ($docente['horas_contrata'] ?? 0))
        );
        $sinClasificar = max(0.0, round($horasContrato - $horasPlanta - $horasContrata, 2));

        if ($sinClasificar > 0.0) {
            if (self::esTitular($docente)) {
                $horasPlanta += $sinClasificar;
            } else {
                $horasContrata += $sinClasificar;
            }
        }

        return [round($horasPlanta, 2), round($horasContrata, 2)];
    }

    private static function esTitular(array $docente): bool
    {
        if ((bool) ($docente['es_titular'] ?? false)) {
            return true;
        }

        $tipoContrato = Str::of((string) ($docente['tipo_contrato'] ?? ''))
            ->ascii()
            ->upper()
            ->toString();

        return str_contains($tipoContrato, 'PLANTA') || str_contains($tipoContrato, 'TITULAR');
    }

    private static function sumar(Collection $items, string $key): float
    {
        return round((float) $items->sum(fn (array $item) => (float) ($item[$key] ?? 0)), 2);
    }
}
