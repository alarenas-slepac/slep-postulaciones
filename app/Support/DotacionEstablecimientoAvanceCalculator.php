<?php

namespace App\Support;

use App\Models\Establecimiento;
use Illuminate\Support\Collection;

class DotacionEstablecimientoAvanceCalculator
{
    public static function build(Establecimiento $establecimiento, int $anio): array
    {
        return self::fromData(
            $establecimiento,
            $anio,
            DotacionEstablecimientoCalculator::build($establecimiento, $anio, false)
        );
    }

    public static function fromData(Establecimiento $establecimiento, int $anio, array $data): array
    {
        $cursosTotal = (int) data_get($data, 'cursos.totales.cursos', 0);
        $cursosSinPlan = (int) data_get($data, 'cursos.totales.sin_horas_plan', 0);
        $cursosConfigurados = max(0, $cursosTotal - $cursosSinPlan);
        $porcentajePlanes = self::porcentaje($cursosConfigurados, $cursosTotal);

        // El avance del plan de estudios se mide exclusivamente en horas aula.
        // Las horas contractuales se conservan como información complementaria
        // para PIE, funciones y cuadratura docente, sin mezclar ambas unidades.
        $horasAulaRequeridas = (float) data_get($data, 'asignacion.resumen.horas_aula_requeridas', 0);
        $horasAulaAsignadas = (float) data_get($data, 'asignacion.resumen.horas_aula_asignadas', 0);
        $horasAulaPendientes = (float) data_get($data, 'asignacion.resumen.horas_aula_pendientes', 0);
        $horasAulaExcedidas = (float) data_get($data, 'asignacion.resumen.horas_aula_excedidas', 0);
        $porcentajeAsignacion = self::porcentaje(
            min($horasAulaAsignadas, $horasAulaRequeridas),
            $horasAulaRequeridas
        );

        $horasContratoRequeridas = (float) data_get($data, 'asignacion.resumen.horas_requeridas', 0);
        $horasContratoAsignadas = (float) data_get($data, 'asignacion.resumen.horas_asignadas', 0);
        $horasContratoPendientes = (float) data_get($data, 'asignacion.resumen.horas_pendientes', 0);
        $horasContratoExcedidas = (float) data_get($data, 'asignacion.resumen.horas_excedidas', 0);

        $porcentajeGeneral = round(($porcentajePlanes + $porcentajeAsignacion) / 2, 1);
        $observaciones = collect($data['alertas'] ?? []);

        if ($cursosSinPlan > 0) {
            $observaciones->push($cursosSinPlan.' curso(s) sin plan u horas de plan configuradas.');
        }
        if ($horasAulaPendientes > 0.01) {
            $observaciones->push(DotacionEstablecimientoCalculator::formatHoras($horasAulaPendientes).' horas aula pendientes de asignar.');
        }
        if ($horasAulaExcedidas > 0.01) {
            $observaciones->push(DotacionEstablecimientoCalculator::formatHoras($horasAulaExcedidas).' horas aula asignadas por sobre el plan configurado.');
        }

        $docentesSobrecarga = (int) data_get($data, 'asignacion.resumen.docentes_sobrecarga', 0);
        if ($docentesSobrecarga > 0) {
            $observaciones->push($docentesSobrecarga.' docente(s) con sobrecarga de horas de contrato calculadas.');
        }

        return [
            'establecimiento_id' => $establecimiento->id,
            'rbd' => $establecimiento->rbd,
            'nombre' => $establecimiento->nombre_establecimiento,
            'comuna' => $establecimiento->comuna,
            'anio' => $anio,
            'planes' => [
                'total' => $cursosTotal,
                'configurados' => $cursosConfigurados,
                'pendientes' => $cursosSinPlan,
                'porcentaje' => $porcentajePlanes,
            ],
            'asignacion' => [
                'unidad' => 'horas aula',
                'horas_aula_requeridas' => $horasAulaRequeridas,
                'horas_aula_asignadas' => $horasAulaAsignadas,
                'horas_aula_pendientes' => $horasAulaPendientes,
                'horas_aula_excedidas' => $horasAulaExcedidas,
                // Alias de compatibilidad para las vistas y reportes existentes.
                'horas_requeridas' => $horasAulaRequeridas,
                'horas_asignadas' => $horasAulaAsignadas,
                'horas_pendientes' => $horasAulaPendientes,
                'horas_excedidas' => $horasAulaExcedidas,
                'horas_contrato_requeridas' => $horasContratoRequeridas,
                'horas_contrato_asignadas' => $horasContratoAsignadas,
                'horas_contrato_pendientes' => $horasContratoPendientes,
                'horas_contrato_excedidas' => $horasContratoExcedidas,
                'porcentaje' => $porcentajeAsignacion,
                'docentes_sobrecarga' => $docentesSobrecarga,
                'docentes_disponibles' => (int) data_get($data, 'asignacion.resumen.docentes_disponibles', 0),
            ],
            'desglose' => self::desglose(data_get($data, 'asignacion.necesidades', [])),
            'porcentaje_general' => $porcentajeGeneral,
            'estado' => self::estado($porcentajeGeneral, $observaciones->isNotEmpty()),
            'observaciones' => $observaciones->unique()->values(),
        ];
    }

    public static function error(Establecimiento $establecimiento, int $anio): array
    {
        return [
            'establecimiento_id' => $establecimiento->id,
            'rbd' => $establecimiento->rbd,
            'nombre' => $establecimiento->nombre_establecimiento,
            'comuna' => $establecimiento->comuna,
            'anio' => $anio,
            'planes' => ['total' => 0, 'configurados' => 0, 'pendientes' => 0, 'porcentaje' => 0.0],
            'asignacion' => [
                'unidad' => 'horas aula',
                'horas_aula_requeridas' => 0.0,
                'horas_aula_asignadas' => 0.0,
                'horas_aula_pendientes' => 0.0,
                'horas_aula_excedidas' => 0.0,
                'horas_requeridas' => 0.0,
                'horas_asignadas' => 0.0,
                'horas_pendientes' => 0.0,
                'horas_excedidas' => 0.0,
                'horas_contrato_requeridas' => 0.0,
                'horas_contrato_asignadas' => 0.0,
                'horas_contrato_pendientes' => 0.0,
                'horas_contrato_excedidas' => 0.0,
                'porcentaje' => 0.0,
                'docentes_sobrecarga' => 0,
                'docentes_disponibles' => 0,
            ],
            'desglose' => collect(),
            'porcentaje_general' => 0.0,
            'estado' => self::estado(0.0, true),
            'observaciones' => collect(['No fue posible calcular el avance de este establecimiento.']),
        ];
    }

    public static function resumen(Collection $avances): array
    {
        $total = $avances->count();

        return [
            'total' => $total,
            'completos' => $avances->where('estado.key', 'completo')->count(),
            'avanzados' => $avances->where('estado.key', 'avanzado')->count(),
            'en_proceso' => $avances->whereIn('estado.key', ['inicial', 'en_proceso'])->count(),
            'sin_iniciar' => $avances->where('estado.key', 'sin_iniciar')->count(),
            'cursos_pendientes' => (int) $avances->sum(fn ($item) => (int) data_get($item, 'planes.pendientes', 0)),
            'horas_pendientes' => (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_aula_pendientes', 0)),
            'horas_excedidas' => (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_aula_excedidas', 0)),
            'horas_aula_pendientes' => (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_aula_pendientes', 0)),
            'horas_aula_excedidas' => (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_aula_excedidas', 0)),
            'promedio_planes' => $total > 0 ? round((float) $avances->avg('planes.porcentaje'), 1) : 0.0,
            'promedio_asignacion' => $total > 0 ? round((float) $avances->avg('asignacion.porcentaje'), 1) : 0.0,
            'promedio_general' => $total > 0 ? round((float) $avances->avg('porcentaje_general'), 1) : 0.0,
        ];
    }

    public static function resumenGlobal(Collection $avances): array
    {
        $avances = $avances->values();
        $total = $avances->count();

        $cursosTotal = (int) $avances->sum(fn ($item) => (int) data_get($item, 'planes.total', 0));
        $cursosConfigurados = (int) $avances->sum(fn ($item) => (int) data_get($item, 'planes.configurados', 0));
        $cursosPendientes = max(0, $cursosTotal - $cursosConfigurados);
        $porcentajePlanes = self::porcentaje($cursosConfigurados, $cursosTotal);

        $horasAulaRequeridas = (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_aula_requeridas', 0));
        $horasAulaAsignadas = (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_aula_asignadas', 0));
        $horasAulaPendientes = (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_aula_pendientes', 0));
        $horasAulaExcedidas = (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_aula_excedidas', 0));
        $horasAulaCubiertas = (float) $avances->sum(fn ($item) => min(
            (float) data_get($item, 'asignacion.horas_aula_asignadas', 0),
            (float) data_get($item, 'asignacion.horas_aula_requeridas', 0)
        ));
        $porcentajeAsignacion = self::porcentaje($horasAulaCubiertas, $horasAulaRequeridas);
        $porcentajeGeneral = round(($porcentajePlanes + $porcentajeAsignacion) / 2, 1);

        $horasContratoRequeridas = (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_contrato_requeridas', 0));
        $horasContratoAsignadas = (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_contrato_asignadas', 0));
        $horasContratoPendientes = (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_contrato_pendientes', 0));
        $horasContratoExcedidas = (float) $avances->sum(fn ($item) => (float) data_get($item, 'asignacion.horas_contrato_excedidas', 0));

        $desglose = $avances
            ->flatMap(fn ($item) => collect(data_get($item, 'desglose', [])))
            ->groupBy('key')
            ->map(function (Collection $items) {
                $requeridas = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_requeridas', 0));
                $asignadas = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_asignadas', 0));
                $pendientes = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_pendientes', 0));
                $excedidas = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_excedidas', 0));
                $cubiertas = (float) $items->sum(fn ($item) => min(
                    (float) data_get($item, 'horas_asignadas', 0),
                    (float) data_get($item, 'horas_requeridas', 0)
                ));

                return [
                    'key' => (string) data_get($items->first(), 'key', ''),
                    'label' => (string) data_get($items->first(), 'label', 'Etapa'),
                    'unidad' => (string) data_get($items->first(), 'unidad', 'horas contrato'),
                    'horas_requeridas' => $requeridas,
                    'horas_asignadas' => $asignadas,
                    'horas_pendientes' => $pendientes,
                    'horas_excedidas' => $excedidas,
                    'porcentaje' => self::porcentaje($cubiertas, $requeridas),
                ];
            })
            ->sortBy(fn ($item) => array_search($item['key'], [
                'plan_estudio',
                'pie_colaborativo',
                'pie_educadora_diferencial',
                'funciones',
            ], true))
            ->values();

        $establecimientosCriticos = $avances
            ->sortBy('porcentaje_general')
            ->take(10)
            ->map(fn ($item) => [
                'rbd' => data_get($item, 'rbd'),
                'nombre' => data_get($item, 'nombre'),
                'comuna' => data_get($item, 'comuna'),
                'porcentaje_general' => (float) data_get($item, 'porcentaje_general', 0),
                'planes_pendientes' => (int) data_get($item, 'planes.pendientes', 0),
                'horas_pendientes' => (float) data_get($item, 'asignacion.horas_aula_pendientes', 0),
                'horas_aula_pendientes' => (float) data_get($item, 'asignacion.horas_aula_pendientes', 0),
                'estado' => (string) data_get($item, 'estado.label', 'Sin estado'),
            ])
            ->values();

        return [
            'total' => $total,
            'estados' => [
                'completos' => $avances->where('estado.key', 'completo')->count(),
                'avanzados' => $avances->where('estado.key', 'avanzado')->count(),
                'en_proceso' => $avances->whereIn('estado.key', ['inicial', 'en_proceso'])->count(),
                'sin_iniciar' => $avances->where('estado.key', 'sin_iniciar')->count(),
            ],
            'planes' => [
                'total' => $cursosTotal,
                'configurados' => $cursosConfigurados,
                'pendientes' => $cursosPendientes,
                'porcentaje' => $porcentajePlanes,
            ],
            'asignacion' => [
                'unidad' => 'horas aula',
                'horas_aula_requeridas' => $horasAulaRequeridas,
                'horas_aula_asignadas' => $horasAulaAsignadas,
                'horas_aula_pendientes' => $horasAulaPendientes,
                'horas_aula_excedidas' => $horasAulaExcedidas,
                'horas_requeridas' => $horasAulaRequeridas,
                'horas_asignadas' => $horasAulaAsignadas,
                'horas_pendientes' => $horasAulaPendientes,
                'horas_excedidas' => $horasAulaExcedidas,
                'horas_contrato_requeridas' => $horasContratoRequeridas,
                'horas_contrato_asignadas' => $horasContratoAsignadas,
                'horas_contrato_pendientes' => $horasContratoPendientes,
                'horas_contrato_excedidas' => $horasContratoExcedidas,
                'porcentaje' => $porcentajeAsignacion,
                'docentes_sobrecarga' => (int) $avances->sum(fn ($item) => (int) data_get($item, 'asignacion.docentes_sobrecarga', 0)),
            ],
            'porcentaje_general' => $porcentajeGeneral,
            'desglose' => $desglose,
            'establecimientos_criticos' => $establecimientosCriticos,
        ];
    }

    private static function desglose(array $necesidades): Collection
    {
        $grupos = [
            'plan_estudio' => ['label' => 'Plan de estudios', 'unidad' => 'horas aula'],
            'pie_colaborativo' => ['label' => 'Trabajo colaborativo PIE', 'unidad' => 'horas contrato'],
            'pie_educadora_diferencial' => ['label' => 'Educadoras Diferenciales PIE', 'unidad' => 'horas contrato'],
            'funciones' => ['label' => 'Funciones y planes normativos', 'unidad' => 'horas contrato'],
        ];

        return collect($grupos)->map(function (array $meta, string $key) use ($necesidades) {
            $items = collect($necesidades[$key] ?? []);
            $esPlan = $key === 'plan_estudio';
            $requeridas = (float) $items->sum(fn ($item) => (float) data_get(
                $item,
                $esPlan ? 'horas_plan_requeridas' : 'horas_contrato_requeridas',
                0
            ));
            $asignadas = (float) $items->sum(fn ($item) => (float) data_get(
                $item,
                $esPlan ? 'horas_plan_asignadas' : 'horas_contrato_asignadas',
                0
            ));
            $pendientes = max(0.0, round($requeridas - $asignadas, 2));
            $excedidas = max(0.0, round($asignadas - $requeridas, 2));

            return [
                'key' => $key,
                'label' => $meta['label'],
                'unidad' => $meta['unidad'],
                'horas_requeridas' => $requeridas,
                'horas_asignadas' => $asignadas,
                'horas_pendientes' => $pendientes,
                'horas_excedidas' => $excedidas,
                'porcentaje' => self::porcentaje(min($asignadas, $requeridas), $requeridas),
            ];
        })->values();
    }

    private static function porcentaje(float|int $valor, float|int $total): float
    {
        if ((float) $total <= 0) {
            return 0.0;
        }

        return round(min(100, max(0, ((float) $valor / (float) $total) * 100)), 1);
    }

    private static function estado(float $porcentaje, bool $conObservaciones): array
    {
        if ($porcentaje >= 100 && ! $conObservaciones) {
            return ['key' => 'completo', 'label' => 'Completo', 'class' => 'text-bg-success'];
        }
        if ($porcentaje >= 80) {
            return ['key' => 'avanzado', 'label' => $conObservaciones ? 'Avanzado con observaciones' : 'Avanzado', 'class' => 'text-bg-primary'];
        }
        if ($porcentaje >= 50) {
            return ['key' => 'en_proceso', 'label' => 'En proceso', 'class' => 'text-bg-warning'];
        }
        if ($porcentaje > 0) {
            return ['key' => 'inicial', 'label' => 'Avance inicial', 'class' => 'text-bg-info'];
        }

        return ['key' => 'sin_iniciar', 'label' => 'Sin iniciar', 'class' => 'text-bg-secondary'];
    }
}
