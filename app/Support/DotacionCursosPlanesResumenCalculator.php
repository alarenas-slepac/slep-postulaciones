<?php

namespace App\Support;

use Illuminate\Support\Collection;

class DotacionCursosPlanesResumenCalculator
{
    /**
     * Prepara las filas del cuadro Cursos y planes sin duplicar las horas de
     * cursos que pertenecen a una combinacion activa.
     */
    public static function build(array $cursos, Collection|array $gruposCombinados): array
    {
        $gruposCombinados = collect($gruposCombinados)
            ->where('activo', true)
            ->values();
        $cursoIdsCombinados = $gruposCombinados
            ->flatMap(fn ($grupo) => collect(data_get($grupo, 'miembros', []))->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $detalles = collect($cursos['rows'] ?? [])
            ->flatMap(fn ($row) => collect($row['detalles'] ?? []))
            ->values();

        $rows = [];
        $grupos = [];
        foreach (($cursos['grupos'] ?? []) as $grupoKey => $grupo) {
            $niveles = [];
            foreach (($grupo['niveles'] ?? []) as $nivelKey) {
                $rowOriginal = $cursos['rows'][$nivelKey] ?? null;
                if (! $rowOriginal) {
                    continue;
                }

                $detallesIndependientes = collect($rowOriginal['detalles'] ?? [])
                    ->reject(fn ($detalle) => $cursoIdsCombinados->contains(
                        (int) data_get($detalle, 'establecimiento_curso_id', 0)
                    ))
                    ->values();
                if ($detallesIndependientes->isEmpty()) {
                    continue;
                }

                $rows[$nivelKey] = self::rowIndependiente($rowOriginal, $detallesIndependientes);
                $niveles[] = $nivelKey;
            }

            if ($niveles === []) {
                continue;
            }

            $rowsGrupo = collect($niveles)->map(fn ($nivelKey) => $rows[$nivelKey]);
            $grupos[$grupoKey] = array_merge($grupo, [
                'niveles' => $niveles,
                'totales' => self::sumRows($rowsGrupo),
            ]);
        }

        $combinados = $gruposCombinados
            ->map(function ($grupo) use ($detalles): array {
                $miembros = collect(data_get($grupo, 'miembros', []))->values();
                $miembroIds = $miembros
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();
                $detallesGrupo = $detalles
                    ->filter(fn ($detalle) => $miembroIds->contains(
                        (int) data_get($detalle, 'establecimiento_curso_id', 0)
                    ))
                    ->values();
                $horasPlanPorCursoDetalle = $detallesGrupo
                    ->map(fn ($detalle) => [
                        'curso' => (string) data_get($detalle, 'nombre_seccion', 'Curso'),
                        'horas' => round((float) data_get($detalle, 'horas', 0), 2),
                    ])
                    ->values();
                $horasPlanPorCursoValores = $horasPlanPorCursoDetalle
                    ->pluck('horas')
                    ->unique()
                    ->values();
                $horasPlanRefuerzo = (float) $detallesGrupo->sum(
                    fn ($detalle) => (float) data_get($detalle, 'horas_plan_refuerzo_ld_otro_docente', 0)
                );
                $horasContratoRefuerzo = (float) $detallesGrupo->sum(
                    fn ($detalle) => (float) data_get($detalle, 'horas_contrato_refuerzo_ld_otro_docente', 0)
                );
                $horasPlan = round(
                    (float) data_get($grupo, 'totales.horas_requeridas', 0) + $horasPlanRefuerzo,
                    2
                );
                $horasContrato = round(
                    (float) data_get($grupo, 'totales.horas_contrato', 0) + $horasContratoRefuerzo,
                    2
                );
                $trabajoColaborativoPie = round((float) $detallesGrupo->sum(
                    fn ($detalle) => (float) data_get($detalle, 'trabajo_colaborativo_pie', 0)
                ), 2);

                return [
                    'id' => (int) data_get($grupo, 'id', 0),
                    'label' => (string) data_get($grupo, 'nombre', 'Curso combinado'),
                    'miembros_label' => $miembros->pluck('label')->filter()->implode(' + '),
                    'matricula' => (int) $miembros->sum(fn ($miembro) => (int) data_get($miembro, 'matricula', 0)),
                    'cursos' => $miembroIds->count(),
                    'horas_plan_por_curso' => $horasPlanPorCursoValores->count() === 1
                        ? (float) $horasPlanPorCursoValores->first()
                        : null,
                    'horas_plan_por_curso_variable' => $horasPlanPorCursoValores->count() > 1,
                    'horas_plan_por_curso_detalle' => $horasPlanPorCursoDetalle->all(),
                    'total_horas' => $horasPlan,
                    'total_horas_contrato_equivalente' => $horasContrato,
                    'total_trabajo_colaborativo_pie' => $trabajoColaborativoPie,
                    'total_contrato_mas_trabajo_colaborativo_pie' => round(
                        $horasContrato + $trabajoColaborativoPie,
                        2
                    ),
                    'proporcion_docente_label' => (string) data_get($grupo, 'proporcion_label', '—'),
                    'horas_plan_reduccion' => round((float) data_get($grupo, 'totales.reduccion', 0), 2),
                    'horas_plan_refuerzo_ld_otro_docente' => round($horasPlanRefuerzo, 2),
                    'horas_contrato_refuerzo_ld_otro_docente' => round($horasContratoRefuerzo, 2),
                    'trabajo_colaborativo_pie_cursos' => $detallesGrupo
                        ->filter(fn ($detalle) => (float) data_get($detalle, 'trabajo_colaborativo_pie', 0) > 0)
                        ->count(),
                ];
            })
            ->values();

        $totalesIndependientes = self::sumRows(collect($rows));
        $totalesCombinados = self::sumRows($combinados);
        $totales = self::sumTotals($totalesIndependientes, $totalesCombinados);

        return [
            'grupos' => $grupos,
            'rows' => $rows,
            'combinados' => $combinados,
            'totales_independientes' => $totalesIndependientes,
            'totales_combinados' => $totalesCombinados,
            'totales' => $totales,
            'tiene_cursos_combinados' => $combinados->isNotEmpty(),
        ];
    }

    private static function rowIndependiente(array $row, Collection $detalles): array
    {
        $horasValores = $detalles
            ->map(fn ($detalle) => round((float) data_get($detalle, 'horas', 0), 2))
            ->unique()
            ->values();
        $proporciones = $detalles
            ->pluck('proporcion_docente_label')
            ->filter()
            ->unique()
            ->values();
        $origenes = $detalles
            ->pluck('origen_proporcion_label')
            ->filter()
            ->unique()
            ->values();
        $horasContrato = round((float) $detalles->sum(
            fn ($detalle) => (float) data_get($detalle, 'horas_contrato_equivalente_redondeado', 0)
        ), 2);
        $trabajoColaborativoPie = round((float) $detalles->sum(
            fn ($detalle) => (float) data_get($detalle, 'trabajo_colaborativo_pie', 0)
        ), 2);

        return array_merge($row, [
            'matricula' => (int) $detalles->sum(fn ($detalle) => (int) data_get($detalle, 'matricula', 0)),
            'cursos' => $detalles->count(),
            'horas_por_nivel' => $horasValores->count() === 1 ? (float) $horasValores->first() : null,
            'horas_variable' => $horasValores->count() > 1,
            'total_horas' => round((float) $detalles->sum(fn ($detalle) => (float) data_get($detalle, 'horas', 0)), 2),
            'total_horas_contrato_equivalente' => $horasContrato,
            'total_trabajo_colaborativo_pie' => $trabajoColaborativoPie,
            'total_contrato_mas_trabajo_colaborativo_pie' => round($horasContrato + $trabajoColaborativoPie, 2),
            'proporcion_docente_label' => match ($proporciones->count()) {
                0 => '—',
                1 => $proporciones->first(),
                default => 'Mixta',
            },
            'origen_proporcion_label' => match ($origenes->count()) {
                0 => 'Regla general',
                1 => $origenes->first(),
                default => 'Mixto',
            },
            'sin_horas_plan' => $detalles->filter(fn ($detalle) => (float) data_get($detalle, 'horas', 0) <= 0)->count(),
            'cursos_refuerzo_ld_otro_docente' => $detalles->filter(
                fn ($detalle) => (float) data_get($detalle, 'horas_plan_refuerzo_ld_otro_docente', 0) > 0
            )->count(),
            'horas_plan_refuerzo_ld_otro_docente' => round((float) $detalles->sum(
                fn ($detalle) => (float) data_get($detalle, 'horas_plan_refuerzo_ld_otro_docente', 0)
            ), 2),
            'horas_contrato_refuerzo_ld_otro_docente' => round((float) $detalles->sum(
                fn ($detalle) => (float) data_get($detalle, 'horas_contrato_refuerzo_ld_otro_docente', 0)
            ), 2),
            'detalles' => $detalles->all(),
        ]);
    }

    private static function sumRows(Collection $rows): array
    {
        return [
            'matricula' => (int) $rows->sum(fn ($row) => (int) data_get($row, 'matricula', 0)),
            'cursos' => (int) $rows->sum(fn ($row) => (int) data_get($row, 'cursos', 0)),
            'horas' => round((float) $rows->sum(fn ($row) => (float) data_get($row, 'total_horas', 0)), 2),
            'horas_contrato_equivalente' => round((float) $rows->sum(
                fn ($row) => (float) data_get($row, 'total_horas_contrato_equivalente', 0)
            ), 2),
            'trabajo_colaborativo_pie' => round((float) $rows->sum(
                fn ($row) => (float) data_get($row, 'total_trabajo_colaborativo_pie', 0)
            ), 2),
            'contrato_mas_trabajo_colaborativo_pie' => round((float) $rows->sum(
                fn ($row) => (float) data_get($row, 'total_contrato_mas_trabajo_colaborativo_pie', 0)
            ), 2),
        ];
    }

    private static function sumTotals(array $primero, array $segundo): array
    {
        return collect(array_keys(self::sumRows(collect())))
            ->mapWithKeys(fn ($key) => [
                $key => str_contains($key, 'matricula') || $key === 'cursos'
                    ? (int) data_get($primero, $key, 0) + (int) data_get($segundo, $key, 0)
                    : round((float) data_get($primero, $key, 0) + (float) data_get($segundo, $key, 0), 2),
            ])
            ->all();
    }
}
