<?php

namespace App\Support;

use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\EstablecimientoPlanEstudio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DotacionAsignacionCalculator
{
    /** @var array<string, bool> */
    private static array $schemaTableCache = [];

    /** @var array<string, bool> */
    private static array $schemaColumnCache = [];

    private static function schemaHasTable(string $table): bool
    {
        if (! array_key_exists($table, self::$schemaTableCache)) {
            self::$schemaTableCache[$table] = Schema::hasTable($table);
        }

        return self::$schemaTableCache[$table];
    }

    private static function schemaHasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        if (! array_key_exists($key, self::$schemaColumnCache)) {
            self::$schemaColumnCache[$key] = Schema::hasColumn($table, $column);
        }

        return self::$schemaColumnCache[$key];
    }

    public const SUBVENCIONES = [
        'General',
        'SEP',
        'PIE',
        'Libre disposición',
        'Otra',
        'Sin clasificar',
    ];

    public static function build(Establecimiento $establecimiento, int $anio, Collection $docentes, array $cursos, array $bloques, ?Collection $asistentes = null): array
    {
        $asistentes ??= collect();
        $asignaciones = self::assignmentsFor($establecimiento, $anio);
        $necesidades = self::necesidades($establecimiento, $anio, $cursos, $bloques, $asignaciones);
        $asignacionesHuerfanas = self::asignacionesHuerfanas($asignaciones, $necesidades);

        // Se mantienen los totales contractuales históricos para reportes globales,
        // pero el bloque de plan de estudios dispone además de totales exclusivos
        // de horas aula pedagógicas para evitar mezclar unidades en la interfaz.
        $totalRequeridasPlan = DotacionCursoCombinadoCalculator::adjustedContractRequired(
            $necesidades['plan_estudio'] ?? []
        );
        $totalRequeridasOtros = collect($necesidades)
            ->except('plan_estudio')
            ->sum(fn ($items) => collect($items)->sum(
                fn ($item) => (float) ($item['horas_contrato_requeridas'] ?? 0)
            ));
        $totalRequeridas = round($totalRequeridasPlan + (float) $totalRequeridasOtros, 2);
        $totalAsignadas = $asignaciones->sum(fn ($item) => (float) $item->horas_contrato);
        $pendientes = max(0.0, round($totalRequeridas - $totalAsignadas, 2));
        $excedidas = max(0.0, round($totalAsignadas - $totalRequeridas, 2));

        $necesidadesPlan = collect($necesidades['plan_estudio'] ?? []);
        $totalAulaRequeridas = (float) $necesidadesPlan->sum(fn ($item) => (float) ($item['horas_plan_requeridas'] ?? 0));
        $totalAulaAsignadas = (float) $necesidadesPlan->sum(fn ($item) => (float) ($item['horas_plan_asignadas'] ?? 0));
        $aulaPendientes = max(0.0, round($totalAulaRequeridas - $totalAulaAsignadas, 2));
        $aulaExcedidas = max(0.0, round($totalAulaAsignadas - $totalAulaRequeridas, 2));
        $contratoDocentePie = self::resumenContratoDocentePie($asignaciones);

        return [
            'necesidades' => $necesidades,
            'asignaciones' => $asignaciones,
            'asignaciones_huerfanas' => $asignacionesHuerfanas,
            'docentes' => $docentes,
            'asistentes' => $asistentes,
            'resumen' => [
                'horas_requeridas' => $totalRequeridas,
                'horas_asignadas' => $totalAsignadas,
                'horas_pendientes' => $pendientes,
                'horas_excedidas' => $excedidas,
                'horas_aula_requeridas' => $totalAulaRequeridas,
                'horas_aula_asignadas' => $totalAulaAsignadas,
                'horas_aula_pendientes' => $aulaPendientes,
                'horas_aula_excedidas' => $aulaExcedidas,
                'horas_contrato_docente_pie_coordinacion' => $contratoDocentePie['coordinacion_pie'],
                'horas_contrato_docente_pie_educadoras_diferenciales' => $contratoDocentePie['educadoras_diferenciales'],
                'horas_contrato_docente_pie' => $contratoDocentePie['total'],
                'docentes_sobrecarga' => $docentes->filter(fn ($d) => ($d['estado_cuadratura']['key'] ?? null) === 'sobrecarga')->count(),
                'asistentes_asignados' => $asignaciones
                    ->filter(fn ($row) => self::coverageEstamento($row) === 'asistente')
                    ->map(fn ($row) => DotacionEstablecimientoCalculator::normalizeRut($row->docente_rut_normalizado ?: $row->docente_rut))
                    ->filter()
                    ->unique()
                    ->count(),
                'horas_aula_asistentes' => round((float) $asignaciones
                    ->filter(fn ($row) => self::coverageEstamento($row) === 'asistente')
                    ->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0)), 2),
                'horas_contrato_asistentes' => round((float) $asignaciones
                    ->filter(fn ($row) => self::coverageEstamento($row) === 'asistente')
                    ->sum(fn ($row) => (float) ($row->horas_contrato ?? 0)), 2),
                'docentes_disponibles' => $docentes->filter(fn ($d) => (float) ($d['diferencia'] ?? 0) > 0.01)->count(),
                'asignaciones_fantasma' => $asignacionesHuerfanas->count(),
                'horas_fantasma' => round((float) $asignacionesHuerfanas->sum(fn ($row) => (float) ($row->horas_contrato ?? 0)), 2),
                'planes_fantasma' => $asignacionesHuerfanas
                    ->filter(fn ($row) => (bool) ($row->es_plan_huerfano ?? false))
                    ->count(),
                'horas_planes_fantasma' => round((float) $asignacionesHuerfanas
                    ->filter(fn ($row) => (bool) ($row->es_plan_huerfano ?? false))
                    ->sum(fn ($row) => (float) ($row->horas_contrato ?? 0)), 2),
                'plan_estudio_fantasma' => $asignacionesHuerfanas
                    ->filter(fn ($row) => (bool) ($row->es_plan_estudio_huerfano ?? false))
                    ->count(),
                'horas_aula_plan_estudio_fantasma' => round((float) $asignacionesHuerfanas
                    ->filter(fn ($row) => (bool) ($row->es_plan_estudio_huerfano ?? false))
                    ->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0)), 2),
                'horas_contrato_plan_estudio_fantasma' => round((float) $asignacionesHuerfanas
                    ->filter(fn ($row) => (bool) ($row->es_plan_estudio_huerfano ?? false))
                    ->sum(fn ($row) => (float) ($row->horas_contrato ?? 0)), 2),
                'docentes_horas_fantasma' => $asignacionesHuerfanas
                    ->map(fn ($row) => DotacionEstablecimientoCalculator::normalizeRut($row->docente_rut_normalizado ?: $row->docente_rut))
                    ->filter()
                    ->unique()
                    ->count(),
            ],
            'subvenciones' => self::subvencionResumen($asignaciones),
        ];
    }

    public static function assignmentsFor(Establecimiento $establecimiento, int $anio): Collection
    {
        if (! self::schemaHasTable('dotacion_docente_asignaciones')) {
            return collect();
        }

        return DotacionDocenteAsignacion::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('estado', 'activa')
            ->orderBy('tipo_asignacion')
            ->orderBy('asignatura_nombre')
            ->orderBy('docente_nombre')
            ->get();
    }

    /**
     * @return array{coordinacion_pie: float, educadoras_diferenciales: float, total: float}
     */
    private static function resumenContratoDocentePie(Collection $asignaciones): array
    {
        $asignacionesDocentes = $asignaciones
            ->filter(fn ($row) => self::coverageEstamento($row) === 'docente');
        $coordinacionPie = (float) $asignacionesDocentes
            ->filter(fn ($row) => self::esAsignacionCoordinacionPie($row))
            ->sum(fn ($row) => (float) data_get($row, 'horas_contrato', 0));
        $educadorasDiferenciales = (float) $asignacionesDocentes
            ->filter(fn ($row) => data_get($row, 'tipo_asignacion') === 'pie_educadora_diferencial')
            ->sum(fn ($row) => (float) data_get($row, 'horas_contrato', 0));

        return [
            'coordinacion_pie' => round($coordinacionPie, 2),
            'educadoras_diferenciales' => round($educadorasDiferenciales, 2),
            'total' => round($coordinacionPie + $educadorasDiferenciales, 2),
        ];
    }

    private static function esAsignacionCoordinacionPie(object|array $asignacion): bool
    {
        if (data_get($asignacion, 'tipo_asignacion') !== 'funcion_tecnico_pedagogica') {
            return false;
        }

        $subtipo = Str::of((string) data_get($asignacion, 'subtipo_asignacion'))
            ->ascii()
            ->lower()
            ->trim()
            ->toString();
        if ($subtipo === 'pie') {
            return true;
        }

        $nombre = Str::of((string) data_get($asignacion, 'asignatura_nombre'))
            ->ascii()
            ->upper()
            ->toString();

        return str_contains($nombre, 'PIE') && str_contains($nombre, 'COORDIN');
    }

    public static function assignmentsByRut(Establecimiento $establecimiento, int $anio): array
    {
        return self::assignmentsFor($establecimiento, $anio)
            ->filter(fn ($row) => self::coverageEstamento($row) === 'docente')
            ->groupBy(fn ($row) => DotacionEstablecimientoCalculator::normalizeRut($row->docente_rut_normalizado ?: $row->docente_rut))
            ->map(function ($items) {
                $plan = $items->where('tipo_asignacion', 'plan_estudio')->values();
                $plan65 = $plan->filter(fn ($row) => self::proportionGroup($row->proporcion_aplicada) === '65_35');
                $plan60 = $plan->filter(fn ($row) => self::proportionGroup($row->proporcion_aplicada) === '60_40');
                $planEspecial = $plan->filter(fn ($row) => self::proportionGroup($row->proporcion_aplicada) === 'especial');

                $aula65 = (float) $plan65->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0));
                $aula60 = (float) $plan60->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0));
                $aulaEspecial = (float) $planEspecial->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0));
                $aulaTotal = round($aula65 + $aula60 + $aulaEspecial, 2);

                // Las horas de contrato 65/35 y 60/40 se calculan sobre el total aula
                // consolidado del docente para cada proporción, evitando redondear cada
                // asignatura por separado. Las reglas especiales (por ejemplo NT1/NT2)
                // conservan la conversión específica almacenada en cada asignación.
                $contrato65 = self::contratoDesdeAula(DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL, $aula65);
                $contrato60 = self::contratoDesdeAula(DocenteHorasNoLectivasCalculator::PROPORCION_PRIORITARIOS, $aula60);
                $contratoEspecial = (float) $planEspecial->sum(fn ($row) => (float) $row->horas_contrato);

                $pie = (float) $items->filter(fn ($row) => in_array($row->tipo_asignacion, ['pie_colaborativo', 'pie_educadora_diferencial'], true))->sum(fn ($row) => (float) $row->horas_contrato);
                $directivas = (float) $items->where('tipo_asignacion', 'funcion_directiva')->sum(fn ($row) => (float) $row->horas_contrato);
                $tecnicoPedagogicas = (float) $items->where('tipo_asignacion', 'funcion_tecnico_pedagogica')->sum(fn ($row) => (float) $row->horas_contrato);
                $planes = (float) $items->where('tipo_asignacion', 'plan_normativo')->sum(fn ($row) => (float) $row->horas_contrato);
                $otras = (float) $items->where('tipo_asignacion', 'otra_funcion')->sum(fn ($row) => (float) $row->horas_contrato);
                $funcionesTotal = round($pie + $directivas + $tecnicoPedagogicas + $planes + $otras, 2);
                $totalContratoCalculado = round($contrato65 + $contrato60 + $contratoEspecial + $funcionesTotal, 2);

                return [
                    'items' => $items->values(),
                    'total' => $totalContratoCalculado,
                    'aula' => $aulaTotal,
                    'aula_65_35' => round($aula65, 2),
                    'aula_60_40' => round($aula60, 2),
                    'aula_especial' => round($aulaEspecial, 2),
                    'contrato_65_35' => round($contrato65, 2),
                    'contrato_60_40' => round($contrato60, 2),
                    'contrato_especial' => round($contratoEspecial, 2),
                    'funciones_total' => $funcionesTotal,
                    'pie' => $pie,
                    'directivas' => $directivas,
                    'tecnico_pedagogicas' => $tecnicoPedagogicas,
                    'planes' => $planes,
                    'otras' => $otras,
                    'subvenciones' => self::subvencionResumen($items),
                ];
            })
            ->all();
    }


    public static function assistantAssignmentsByRut(Establecimiento $establecimiento, int $anio): array
    {
        return self::assignmentsFor($establecimiento, $anio)
            ->filter(fn ($row) => self::coverageEstamento($row) === 'asistente')
            ->groupBy(fn ($row) => DotacionEstablecimientoCalculator::normalizeRut($row->docente_rut_normalizado ?: $row->docente_rut))
            ->map(function ($items) {
                $aula = (float) $items
                    ->where('tipo_asignacion', 'plan_estudio')
                    ->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0));
                $funciones = (float) $items
                    ->where('tipo_asignacion', '!=', 'plan_estudio')
                    ->sum(fn ($row) => (float) ($row->horas_contrato ?? 0));
                $total = (float) $items->sum(fn ($row) => (float) ($row->horas_contrato ?? 0));

                return [
                    'items' => $items->values(),
                    'total' => round($total, 2),
                    'aula' => round($aula, 2),
                    'funciones_total' => round($funciones, 2),
                    'subvenciones' => self::subvencionResumen($items),
                ];
            })
            ->all();
    }

    public static function subvencionResumen(Collection $items): Collection
    {
        return $items->groupBy(fn ($item) => $item->subvencion ?: 'Sin clasificar')
            ->map(fn ($rows, $subvencion) => [
                'subvencion' => $subvencion,
                'horas' => (float) $rows->sum(fn ($row) => (float) $row->horas_contrato),
                'horas_aula' => (float) $rows->where('tipo_asignacion', 'plan_estudio')->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0)),
                'horas_contrato_funciones' => (float) $rows->filter(fn ($row) => $row->tipo_asignacion !== 'plan_estudio')->sum(fn ($row) => (float) $row->horas_contrato),
            ])
            ->sortKeys()
            ->values();
    }

    public static function planNeedForKey(Establecimiento $establecimiento, int $anio, string $key): ?array
    {
        if ($key === '') {
            return null;
        }

        return self::necesidadesPlanEstudio(
            $establecimiento,
            $anio,
            self::assignmentsFor($establecimiento, $anio)
        )->first(fn ($item) => ($item['key'] ?? null) === $key);
    }

    public static function necesidades(Establecimiento $establecimiento, int $anio, array $cursos, array $bloques, Collection $asignaciones): array
    {
        $needs = [
            'plan_estudio' => self::necesidadesPlanEstudio($establecimiento, $anio, $asignaciones),
            'pie_colaborativo' => self::necesidadesTrabajoColaborativo(
                $cursos,
                $asignaciones,
                $establecimiento,
                $anio
            ),
            'pie_educadora_diferencial' => self::necesidadesEducadorasDiferenciales($bloques, $asignaciones),
            'funciones' => self::necesidadesFunciones($bloques, $asignaciones),
        ];

        return $needs;
    }

    private static function necesidadesPlanEstudio(Establecimiento $establecimiento, int $anio, Collection $asignaciones): Collection
    {
        $porcentaje = DotacionEstablecimientoCalculator::porcentajePrioritariosPara($establecimiento, $anio);
        $cursos = EstablecimientoCurso::query()
            ->with(['curso', 'planEstudio.asignaturas', 'planEstudio.bloques'])
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('activo', true)
            ->where('matricula', '>', 0)
            ->orderBy('curso_id')
            ->orderBy('letra')
            ->get();

        $items = collect();
        foreach ($cursos as $curso) {
            $plan = DotacionPlanEstudioResolver::resolve($curso);
            if (! $plan) {
                continue;
            }

            $planEsReferencial = DotacionPlanEstudioResolver::isReferential($curso, $plan);
            $fuentePlan = $planEsReferencial ? 'plan referencial estimado' : 'plan asociado';
            $oficiales = $plan->asignaturas ?? collect();
            $personalizadas = self::asignaturasPersonalizadas($curso, (int) $plan->id);
            $tieneLibreDisposicionPersonalizada = $personalizadas
                ->filter(fn ($custom) => ($custom['tipo_bloque'] ?? null) === 'libre_disposicion')
                ->isNotEmpty();

            $libreDisposicionOrientacion = $personalizadas->filter(
                fn ($custom) => ($custom['tipo_bloque'] ?? null) === 'libre_disposicion'
                    && self::esOrientacion($custom['nombre'] ?? null)
            );
            $horasLibreDisposicionOrientacion = (float) $libreDisposicionOrientacion->sum(fn ($custom) => (float) ($custom['horas_semanales'] ?? 0));
            $orientacionOficialConsolidada = false;

            foreach ($oficiales as $asig) {
                $horas = (float) ($asig->horas_semanales ?? 0);
                if ($horas <= 0) {
                    continue;
                }
                $subtipo = $asig->tipo_bloque ?: 'tiempo_minimo';

                // Las filas de resumen/subtotal/total del plan no son necesidades asignables.
                // Sus valores se reflejan como sumatorias del curso/bloque en la vista.
                if (self::esFilaResumenPlan($asig->asignatura ?? null, $subtipo)) {
                    continue;
                }

                // Si el establecimiento configuró libre disposición personalizada,
                // se muestra esa asignación real y no la fila genérica del plan oficial.
                if ($subtipo === 'libre_disposicion' && $tieneLibreDisposicionPersonalizada) {
                    continue;
                }

                $esOrientacionOficial = self::esOrientacion($asig->asignatura ?? null);
                $horasPlanOriginal = $horas;
                $fuente = 'Asignatura oficial del plan';
                $bloque = self::bloqueLabel($subtipo);
                $subvencion = $subtipo === 'libre_disposicion' ? 'Libre disposición' : 'General';

                if ($esOrientacionOficial && $horasLibreDisposicionOrientacion > 0) {
                    // Orientacion puede venir fraccionada: 0,5 h del plan oficial y 0,5 h de libre disposicion.
                    // Para asignacion docente debe tratarse como una sola necesidad asignable de Orientacion,
                    // evitando filas separadas de 0,5 h que no representan una hora de contrato practicable.
                    $horas = round($horas + $horasLibreDisposicionOrientacion, 2);
                    $orientacionOficialConsolidada = true;
                    $bloque = 'Plan de estudios + libre disposición';
                    $subvencion = 'General';
                    $fuente = 'Asignatura oficial del plan consolidada con libre disposición: '.$horasPlanOriginal.' h plan + '.$horasLibreDisposicionOrientacion.' h libre disposición';
                }

                $calc = DotacionEstablecimientoCalculator::contratoEquivalenteAsignacion($curso, $horas, $porcentaje, $subtipo);
                $key = self::key('plan', [$curso->id, $subtipo, $asig->asignatura]);
                $items->push(self::needRow($key, 'plan_estudio', $subtipo, [
                    'curso' => $curso,
                    'establecimiento_curso_id' => $curso->id,
                    'curso_label' => self::cursoLabel($curso),
                    'titulo' => $asig->asignatura,
                    'bloque' => $bloque,
                    'horas_plan' => $horas,
                    'horas_contrato' => (float) ($calc['horas_contrato_equivalente_redondeado'] ?? 0),
                    'horas_aula_cronologicas' => (float) ($calc['horas_aula_cronologicas'] ?? 0),
                    'proporcion' => $calc['proporcion_label'] ?? null,
                    'origen_proporcion' => $calc['origen_proporcion'] ?? 'regla_general',
                    'origen_proporcion_label' => $calc['origen_proporcion_label'] ?? 'Regla general',
                    'motivo_proporcion' => $calc['motivo'] ?? null,
                    'subvencion' => $subvencion,
                    'plan_estudio_id' => $plan->id,
                    'plan_referencial_estimado' => $planEsReferencial,
                    'asignatura_nombre' => $asig->asignatura,
                    'fuente' => $fuente,
                    'horas_plan_oficial' => $esOrientacionOficial && $horasLibreDisposicionOrientacion > 0 ? $horasPlanOriginal : null,
                    'horas_libre_disposicion_consolidada' => $esOrientacionOficial && $horasLibreDisposicionOrientacion > 0 ? $horasLibreDisposicionOrientacion : null,
                ], $asignaciones));
            }

            foreach ($personalizadas as $custom) {
                if ($orientacionOficialConsolidada
                    && ($custom['tipo_bloque'] ?? null) === 'libre_disposicion'
                    && self::esOrientacion($custom['nombre'] ?? null)) {
                    continue;
                }
                $horas = (float) ($custom['horas_semanales'] ?? 0);
                if ($horas <= 0) {
                    continue;
                }
                $subtipo = $custom['tipo_bloque'] ?: 'libre_disposicion';
                if (self::esFilaResumenPlan($custom['nombre'] ?? null, $subtipo)) {
                    continue;
                }
                $calc = DotacionEstablecimientoCalculator::contratoEquivalenteAsignacion($curso, $horas, $porcentaje, $subtipo);
                $key = self::key('plan', [$curso->id, $subtipo, $custom['nombre']]);
                $items->push(self::needRow($key, 'plan_estudio', $subtipo, [
                    'curso' => $curso,
                    'establecimiento_curso_id' => $curso->id,
                    'curso_label' => self::cursoLabel($curso),
                    'titulo' => $custom['nombre'],
                    'bloque' => $custom['bloque'] ?: self::bloqueLabel($subtipo),
                    'horas_plan' => $horas,
                    'horas_contrato' => (float) ($calc['horas_contrato_equivalente_redondeado'] ?? 0),
                    'horas_aula_cronologicas' => (float) ($calc['horas_aula_cronologicas'] ?? 0),
                    'proporcion' => $calc['proporcion_label'] ?? null,
                    'origen_proporcion' => $calc['origen_proporcion'] ?? 'regla_general',
                    'origen_proporcion_label' => $calc['origen_proporcion_label'] ?? 'Regla general',
                    'motivo_proporcion' => $calc['motivo'] ?? null,
                    'subvencion' => 'Libre disposición',
                    'plan_estudio_id' => $plan->id,
                    'plan_referencial_estimado' => $planEsReferencial,
                    'plan_bloque_id' => $custom['plan_bloque_id'] ?? null,
                    'asignatura_id' => $custom['asignatura_id'] ?? null,
                    'asignatura_nombre' => $custom['nombre'],
                    'nombre_personalizado' => $custom['nombre_personalizado'] ?? null,
                    'plan_comun_asociado' => $custom['plan_comun_asociado'] ?? null,
                    'asignatura_oficial' => $custom['asignatura_oficial'] ?? null,
                    'fuente' => $custom['plan_comun_asociado']
                        ? 'Libre disposición configurada · Plan común asociado: '.$custom['plan_comun_asociado']
                        : 'Libre disposición configurada por establecimiento',
                ], $asignaciones));
            }

            if ($oficiales->isEmpty() && $items->where('establecimiento_curso_id', $curso->id)->isEmpty()) {
                foreach (($plan->bloques ?? collect())->where('activo', true) as $bloque) {
                    if (($bloque->tipo_bloque ?? null) === 'total') {
                        continue;
                    }
                    $horas = (float) ($bloque->horas_semanales ?? 0);
                    if ($horas <= 0) {
                        continue;
                    }
                    $subtipo = $bloque->tipo_bloque ?: 'bloque_plan';
                    if (self::esFilaResumenPlan($bloque->nombre ?? null, $subtipo)) {
                        continue;
                    }
                    $calc = DotacionEstablecimientoCalculator::contratoEquivalenteAsignacion($curso, $horas, $porcentaje, $subtipo);
                    $key = self::key('plan', [$curso->id, $subtipo, $bloque->nombre]);
                    $items->push(self::needRow($key, 'plan_estudio', $subtipo, [
                        'curso' => $curso,
                        'establecimiento_curso_id' => $curso->id,
                        'curso_label' => self::cursoLabel($curso),
                        'titulo' => $bloque->nombre,
                        'bloque' => $bloque->nombre,
                        'horas_plan' => $horas,
                        'horas_contrato' => (float) ($calc['horas_contrato_equivalente_redondeado'] ?? 0),
                        'horas_aula_cronologicas' => (float) ($calc['horas_aula_cronologicas'] ?? 0),
                        'proporcion' => $calc['proporcion_label'] ?? null,
                        'origen_proporcion' => $calc['origen_proporcion'] ?? 'regla_general',
                        'origen_proporcion_label' => $calc['origen_proporcion_label'] ?? 'Regla general',
                        'motivo_proporcion' => $calc['motivo'] ?? null,
                        'subvencion' => $subtipo === 'libre_disposicion' ? 'Libre disposición' : 'General',
                        'plan_estudio_id' => $plan->id,
                        'plan_referencial_estimado' => $planEsReferencial,
                        'plan_bloque_id' => $bloque->id,
                        'asignatura_nombre' => $bloque->nombre,
                        'fuente' => 'Bloque del plan',
                    ], $asignaciones));
                }
            }

            // El desglose puede estar incompleto aunque el catálogo defina el
            // total semanal. La diferencia se concilia primero contra libre
            // disposición para que los cursos combinados agrupen esas horas en
            // el mismo bloque curricular y no como una necesidad adicional.
            $horasPlanTotal = max(0.0, round((float) ($plan->horas_semanales_total ?? 0), 2));
            $itemsCurso = $items->where('establecimiento_curso_id', $curso->id);
            $horasPlanDesglosadas = round((float) $itemsCurso
                ->sum(fn ($item) => (float) ($item['horas_plan_requeridas'] ?? 0)), 2);
            $horasPlanFaltantes = max(0.0, round($horasPlanTotal - $horasPlanDesglosadas, 2));
            $horasLibreDisposicionTotal = max(
                0.0,
                round((float) ($plan->horas_semanales_libre_disposicion ?? 0), 2)
            );
            $horasLibreDisposicionDesglosadas = round(
                (float) $itemsCurso
                    ->where('subtipo_asignacion', 'libre_disposicion')
                    ->sum(fn ($item) => (float) ($item['horas_plan_requeridas'] ?? 0))
                + (float) $itemsCurso
                    ->sum(fn ($item) => (float) ($item['horas_libre_disposicion_consolidada'] ?? 0)),
                2
            );
            $horasLibreDisposicionFaltantes = min(
                $horasPlanFaltantes,
                max(0.0, round($horasLibreDisposicionTotal - $horasLibreDisposicionDesglosadas, 2))
            );

            $agregarRespaldo = function (
                string $subtipo,
                string $titulo,
                string $bloque,
                string $subvencion,
                float $horas,
                float $horasDesglosadas,
                string $detalleFuente,
                bool $usarClaveRespaldoAnterior = false
            ) use ($curso, $porcentaje, $plan, $planEsReferencial, $fuentePlan, $asignaciones, $horasPlanTotal, $items): void {
                if ($horas <= 0.01) {
                    return;
                }

                $calc = DotacionEstablecimientoCalculator::contratoEquivalenteAsignacion(
                    $curso,
                    $horas,
                    $porcentaje,
                    $subtipo
                );
                $key = $usarClaveRespaldoAnterior
                    ? self::key('plan', [$curso->id, 'plan_sin_desglose', $plan->id])
                    : self::key('plan', [$curso->id, $subtipo, $titulo, $plan->id]);
                $items->push(self::needRow($key, 'plan_estudio', $subtipo, [
                    'curso' => $curso,
                    'establecimiento_curso_id' => $curso->id,
                    'curso_label' => self::cursoLabel($curso),
                    'titulo' => $titulo,
                    'bloque' => $bloque,
                    'horas_plan' => $horas,
                    'horas_contrato' => (float) ($calc['horas_contrato_equivalente_redondeado'] ?? 0),
                    'horas_aula_cronologicas' => (float) ($calc['horas_aula_cronologicas'] ?? 0),
                    'proporcion' => $calc['proporcion_label'] ?? null,
                    'origen_proporcion' => $calc['origen_proporcion'] ?? 'regla_general',
                    'origen_proporcion_label' => $calc['origen_proporcion_label'] ?? 'Regla general',
                    'motivo_proporcion' => $calc['motivo'] ?? null,
                    'subvencion' => $subvencion,
                    'plan_estudio_id' => $plan->id,
                    'asignatura_nombre' => $titulo,
                    'plan_referencial_estimado' => $planEsReferencial,
                    'horas_plan_total' => $horasPlanTotal,
                    'horas_plan_desglosadas' => $horasDesglosadas,
                    'fuente' => sprintf(
                        '%s para completar %.2f h del %s; el desglose disponible suma %.2f h',
                        $detalleFuente,
                        $horasPlanTotal,
                        $fuentePlan,
                        $horasDesglosadas
                    ),
                ], $asignaciones));
            };

            $agregarRespaldo(
                'libre_disposicion',
                'Horas de libre disposición',
                'Libre disposición',
                'Libre disposición',
                $horasLibreDisposicionFaltantes,
                $horasPlanDesglosadas,
                'Diferencia de libre disposición',
                $horasPlanFaltantes - $horasLibreDisposicionFaltantes <= 0.01
            );

            $horasPlanDesglosadas = round((float) $items
                ->where('establecimiento_curso_id', $curso->id)
                ->sum(fn ($item) => (float) ($item['horas_plan_requeridas'] ?? 0)), 2);
            $horasPlanComunFaltantes = max(0.0, round($horasPlanTotal - $horasPlanDesglosadas, 2));
            if ($horasPlanComunFaltantes > 0.01) {
                $agregarRespaldo(
                    'plan_sin_desglose',
                    'Horas del plan común sin desglose',
                    'Plan común',
                    'General',
                    $horasPlanComunFaltantes,
                    $horasPlanDesglosadas,
                    'Diferencia del plan común',
                    true
                );
            }
        }

        $items = $items
            ->sortBy(fn ($row) => sprintf('%s|%s|%s', $row['curso_label'] ?? '', $row['bloque'] ?? '', $row['titulo'] ?? ''))
            ->values();

        return DotacionCursoCombinadoCalculator::apply(
            $items,
            $asignaciones,
            $establecimiento,
            $anio
        );
    }

    private static function asignaturasPersonalizadas(EstablecimientoCurso $curso, int $planEstudioId): Collection
    {
        if (! self::schemaHasTable('establecimiento_planes_estudio') || ! self::schemaHasTable('establecimiento_planes_estudio_asignaturas')) {
            return collect();
        }

        $select = [
            'detalle.id',
            'detalle.plan_estudio_bloque_id',
            'detalle.asignatura_id',
            'detalle.horas_semanales',
            'detalle.horas_anuales',
            'detalle.origen',
            'detalle.observacion',
            'detalle.orden',
            'bloque.nombre as bloque_nombre',
            'bloque.tipo_bloque as bloque_tipo',
            'asig.nombre as asignatura_nombre',
        ];

        if (self::schemaHasColumn('establecimiento_planes_estudio_asignaturas', 'nombre_asignatura_personalizada')) {
            $select[] = 'detalle.nombre_asignatura_personalizada';
        }
        if (self::schemaHasColumn('establecimiento_planes_estudio_asignaturas', 'asignatura_plan_comun_id')) {
            $select[] = 'detalle.asignatura_plan_comun_id';
            $select[] = 'plan_comun.nombre as plan_comun_nombre';
        }

        $buildQuery = function () use ($select) {
            $query = DB::table('establecimiento_planes_estudio_asignaturas as detalle')
                ->join('establecimiento_planes_estudio as config', 'config.id', '=', 'detalle.establecimiento_plan_estudio_id')
                ->leftJoin('planes_estudio_bloques as bloque', 'bloque.id', '=', 'detalle.plan_estudio_bloque_id')
                ->leftJoin('asignaturas as asig', 'asig.id', '=', 'detalle.asignatura_id')
                ->orderBy('bloque.orden')
                ->orderBy('detalle.orden')
                ->orderBy('detalle.id')
                ->select($select);

            if (self::schemaHasColumn('establecimiento_planes_estudio_asignaturas', 'asignatura_plan_comun_id')) {
                $query->leftJoin('asignaturas as plan_comun', 'plan_comun.id', '=', 'detalle.asignatura_plan_comun_id');
            }

            return $query;
        };

        $rows = $buildQuery()
            ->where('config.establecimiento_curso_id', $curso->id)
            ->where('config.plan_estudio_id', $planEstudioId)
            ->get();

        // Respaldo para instalaciones donde la configuracion exista por establecimiento/curso/anio,
        // pero el identificador establecimiento_curso_id haya cambiado por recarga de cursos.
        if ($rows->isEmpty()) {
            $rows = $buildQuery()
                ->where('config.establecimiento_id', $curso->establecimiento_id)
                ->where('config.curso_id', $curso->curso_id)
                ->where('config.anio', $curso->anio)
                ->where('config.plan_estudio_id', $planEstudioId)
                ->get();
        }

        return $rows->map(function ($detalle) {
            $tipoBloque = $detalle->bloque_tipo ?: 'libre_disposicion';
            $esLibreDisposicion = $tipoBloque === 'libre_disposicion';
            $nombrePersonalizado = trim((string) ($detalle->nombre_asignatura_personalizada ?? ''));
            $planComun = trim((string) ($detalle->plan_comun_nombre ?? ''));
            $asignatura = trim((string) ($detalle->asignatura_nombre ?? ''));

            // Para libre disposicion, la necesidad asignable debe mostrar la denominacion
            // definida por el establecimiento. Si no hay nombre personalizado, se muestra
            // el plan comun asociado antes que la asignatura generica "Horas de libre disposicion".
            $nombre = $nombrePersonalizado !== ''
                ? $nombrePersonalizado
                : ($esLibreDisposicion && $planComun !== ''
                    ? $planComun
                    : ($asignatura !== '' ? $asignatura : 'Asignatura personalizada'));

            return [
                'nombre' => $nombre,
                'nombre_personalizado' => $nombrePersonalizado !== '' ? $nombrePersonalizado : null,
                'plan_comun_asociado' => $planComun !== '' ? $planComun : null,
                'asignatura_oficial' => $asignatura !== '' ? $asignatura : null,
                'bloque' => $detalle->bloque_nombre,
                'tipo_bloque' => $tipoBloque,
                'horas_semanales' => $detalle->horas_semanales,
                'plan_bloque_id' => $detalle->plan_estudio_bloque_id,
                'asignatura_id' => $detalle->asignatura_id,
            ];
        });
    }

    private static function necesidadesTrabajoColaborativo(
        array $cursos,
        Collection $asignaciones,
        Establecimiento $establecimiento,
        int $anio
    ): Collection
    {
        $items = collect();
        foreach (($cursos['rows'] ?? []) as $row) {
            foreach (($row['detalles'] ?? []) as $detalle) {
                if (! ($detalle['tiene_nee'] ?? false)) {
                    continue;
                }
                $key = self::key('pie_colab', [$detalle['establecimiento_curso_id'] ?? null]);
                $items->push(self::needRow($key, 'pie_colaborativo', 'trabajo_colaborativo', [
                    'titulo' => 'Trabajo colaborativo PIE',
                    'curso_label' => $detalle['nombre_seccion'] ?? 'Curso',
                    'horas_contrato' => 3.0,
                    'horas_plan' => null,
                    'subvencion' => 'PIE',
                    'establecimiento_curso_id' => $detalle['establecimiento_curso_id'] ?? null,
                    'asignatura_nombre' => 'Trabajo colaborativo PIE',
                    'fuente' => '3 horas por curso con estudiantes NEE',
                ], $asignaciones));
            }
        }
        return DotacionCursoCombinadoCalculator::applyCollaborativePie(
            $items->values(),
            $asignaciones,
            $establecimiento,
            $anio
        );
    }

    private static function necesidadesEducadorasDiferenciales(array $bloques, Collection $asignaciones): Collection
    {
        $horas = 0.0;
        $fuentes = [];
        foreach (($bloques['pie']['items'] ?? []) as $item) {
            if (str_contains(Str::of($item['nombre'] ?? '')->ascii()->upper()->toString(), 'EDUCADORAS DIFERENCIALES')) {
                $horas += (float) ($item['horas'] ?? 0);
                if (! empty($item['detalle'])) {
                    $fuentes[] = (string) $item['detalle'];
                }
            }
        }
        if ($horas <= 0) {
            return collect();
        }
        $key = self::key('pie_educadora', ['bolsa']);
        return collect([self::needRow($key, 'pie_educadora_diferencial', 'bolsa_total', [
            'titulo' => 'Bolsa Educadoras Diferenciales PIE',
            'curso_label' => 'Establecimiento',
            'horas_contrato' => $horas,
            'subvencion' => 'PIE',
            'asignatura_nombre' => 'Educadoras diferenciales PIE',
            'fuente' => $fuentes ? implode(' · ', array_unique($fuentes)) : 'PROF EDUC. DIF desde Estudiantes PIE por curso',
        ], $asignaciones)]);
    }

    private static function necesidadesFunciones(array $bloques, Collection $asignaciones): Collection
    {
        $items = collect();
        foreach ($bloques as $keyBloque => $bloque) {
            foreach (($bloque['items'] ?? []) as $index => $item) {
                $nombre = (string) ($item['nombre'] ?? 'Función');
                if ($keyBloque === 'pie' && str_contains(Str::of($nombre)->ascii()->upper()->toString(), 'EDUCADORAS DIFERENCIALES')) {
                    continue;
                }
                $horas = (float) ($item['horas'] ?? 0);
                if ($horas <= 0) {
                    continue;
                }
                $tipo = match ($keyBloque) {
                    'directiva' => 'funcion_directiva',
                    'tecnico_pedagogica' => 'funcion_tecnico_pedagogica',
                    'planes_programas' => 'plan_normativo',
                    'otras_funciones_docentes' => 'otra_funcion',
                    'pie' => 'funcion_tecnico_pedagogica',
                    default => 'otra_funcion',
                };
                $subtipo = $keyBloque;
                $needKey = self::key('funcion', [$keyBloque, $index, $nombre]);
                $items->push(self::needRow($needKey, $tipo, $subtipo, [
                    'titulo' => $nombre,
                    'curso_label' => $bloque['label'] ?? 'Bloque',
                    'horas_contrato' => $horas,
                    'subvencion' => $keyBloque === 'pie' ? 'PIE' : 'General',
                    'asignatura_nombre' => $nombre,
                    'fuente' => ($item['origen'] ?? 'Dotación funciones').' · '.($item['detalle'] ?? ''),
                    'dotacion_funcion_id' => $item['dotacion_funcion_id'] ?? null,
                    'dotacion_funcion_regla_id' => $item['dotacion_funcion_regla_id'] ?? null,
                ], $asignaciones));
            }
        }
        return $items->values();
    }

    /**
     * Asignaciones activas que continúan sumando horas, pero cuya necesidad de
     * función, plan normativo o plan de estudio ya no existe en la configuración vigente.
     */
    private static function asignacionesHuerfanas(Collection $asignaciones, array $necesidades): Collection
    {
        $tiposFuncion = [
            'pie_colaborativo',
            'pie_educadora_diferencial',
            'funcion_directiva',
            'funcion_tecnico_pedagogica',
            'plan_normativo',
            'otra_funcion',
        ];

        // Todas las necesidades vigentes de plan de estudio se consideran, sin
        // restringir el subtipo. Esto incluye plan_comun_formacion_general,
        // libre_disposicion, tiempo_minimo y cualquier otro subtipo almacenado.
        $necesidadesPlanEstudio = collect($necesidades['plan_estudio'] ?? [])->values();

        $necesidadesVigentes = collect([
            ...collect($necesidades['pie_colaborativo'] ?? [])->all(),
            ...collect($necesidades['pie_educadora_diferencial'] ?? [])->all(),
            ...collect($necesidades['funciones'] ?? [])->all(),
        ]);

        $planesVigentes = $necesidadesVigentes
            ->filter(fn ($item) => self::esNecesidadPlanNormativo($item))
            ->values();

        $keysVigentes = $necesidadesVigentes
            ->flatMap(fn ($item) => [
                data_get($item, 'key'),
                ...collect(data_get($item, 'necesidad_keys_historicas', []))->all(),
            ])
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values();

        $funcionesVigentes = $necesidadesVigentes
            ->pluck('dotacion_funcion_id')
            ->filter(fn ($value) => (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $reglasVigentes = $necesidadesVigentes
            ->pluck('dotacion_funcion_regla_id')
            ->filter(fn ($value) => (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        return $asignaciones
            ->filter(function ($row) use ($tiposFuncion, $keysVigentes, $funcionesVigentes, $reglasVigentes, $planesVigentes, $necesidadesPlanEstudio) {
                $tipo = (string) ($row->tipo_asignacion ?? '');
                $esPlanEstudio = $tipo === 'plan_estudio';
                $esPlanNormativo = self::esAsignacionPlanNormativo($row);

                if ($esPlanEstudio) {
                    return ! self::planEstudioVigenteParaAsignacion($row, $necesidadesPlanEstudio);
                }

                if (! $esPlanNormativo && ! in_array($tipo, $tiposFuncion, true)) {
                    return false;
                }

                if ($esPlanNormativo) {
                    return ! self::planNormativoVigenteParaAsignacion($row, $planesVigentes);
                }

                $funcionId = (int) ($row->dotacion_funcion_id ?? 0);
                if ($funcionId > 0) {
                    return ! $funcionesVigentes->contains($funcionId);
                }

                $reglaId = (int) ($row->dotacion_funcion_regla_id ?? 0);
                if ($reglaId > 0) {
                    return ! $reglasVigentes->contains($reglaId);
                }

                $key = trim((string) ($row->necesidad_key ?? ''));

                return $key === '' || ! $keysVigentes->contains($key);
            })
            ->map(function ($row) use ($funcionesVigentes, $reglasVigentes, $planesVigentes, $necesidadesPlanEstudio) {
                $tipo = (string) ($row->tipo_asignacion ?? '');
                $funcionId = (int) ($row->dotacion_funcion_id ?? 0);
                $reglaId = (int) ($row->dotacion_funcion_regla_id ?? 0);
                $key = trim((string) ($row->necesidad_key ?? ''));
                $esPlanEstudio = $tipo === 'plan_estudio';
                $esPlanNormativo = self::esAsignacionPlanNormativo($row);

                $motivo = 'La necesidad asociada ya no existe en Dotación funciones y planes.';
                $categoria = 'funcion_eliminada';

                if ($esPlanEstudio) {
                    $motivo = self::motivoPlanEstudioHuerfano($row, $necesidadesPlanEstudio);
                    $categoria = 'plan_estudio_eliminado';
                } elseif ($esPlanNormativo) {
                    $motivo = self::motivoPlanNormativoHuerfano($row, $planesVigentes);
                    $categoria = 'plan_normativo_eliminado';
                } elseif ($funcionId > 0 && ! $funcionesVigentes->contains($funcionId)) {
                    $motivo = 'La función declarada fue eliminada de Dotación funciones y planes.';
                } elseif ($reglaId > 0 && ! $reglasVigentes->contains($reglaId)) {
                    $motivo = 'La regla o función asociada ya no está vigente para este establecimiento.';
                } elseif ($key === '') {
                    $motivo = 'Asignación antigua sin vínculo identificable con una necesidad vigente.';
                }

                $row->setAttribute('motivo_huerfana', $motivo);
                $row->setAttribute('categoria_huerfana', $categoria);
                $row->setAttribute('es_plan_huerfano', $esPlanNormativo);
                $row->setAttribute('es_plan_estudio_huerfano', $esPlanEstudio);

                return $row;
            })
            ->sortBy([
                fn ($a, $b) => ((bool) ($b->es_plan_estudio_huerfano ?? false)) <=> ((bool) ($a->es_plan_estudio_huerfano ?? false)),
                fn ($a, $b) => ((bool) ($b->es_plan_huerfano ?? false)) <=> ((bool) ($a->es_plan_huerfano ?? false)),
                fn ($a, $b) => strcasecmp((string) $a->docente_nombre, (string) $b->docente_nombre),
                fn ($a, $b) => strcasecmp((string) $a->asignatura_nombre, (string) $b->asignatura_nombre),
            ])
            ->values();
    }

    /**
     * Valida una asignación curricular contra todas las necesidades vigentes de
     * tipo plan_estudio, cualquiera sea su subtipo_asignacion.
     */
    private static function planEstudioVigenteParaAsignacion(object $row, Collection $necesidadesPlanEstudio): bool
    {
        if ($necesidadesPlanEstudio->isEmpty()) {
            return false;
        }

        $key = trim((string) ($row->necesidad_key ?? ''));
        if ($key !== '' && $necesidadesPlanEstudio->contains(fn ($item) => (string) ($item['key'] ?? '') === $key)) {
            return true;
        }

        $candidatos = $necesidadesPlanEstudio;
        $usoIdentificador = false;

        $cursoId = (int) ($row->establecimiento_curso_id ?? 0);
        if ($cursoId > 0) {
            $usoIdentificador = true;
            $candidatos = $candidatos
                ->filter(fn ($item) => (int) ($item['establecimiento_curso_id'] ?? 0) === $cursoId)
                ->values();
            if ($candidatos->isEmpty()) {
                return false;
            }
        }

        $planId = (int) ($row->plan_estudio_id ?? 0);
        if ($planId > 0) {
            $usoIdentificador = true;
            $candidatos = $candidatos
                ->filter(fn ($item) => (int) ($item['plan_estudio_id'] ?? 0) === $planId)
                ->values();
            if ($candidatos->isEmpty()) {
                return false;
            }
        }

        $subtipo = self::normalizarNombreVinculo((string) ($row->subtipo_asignacion ?? ''));
        if ($subtipo !== '') {
            $usoIdentificador = true;
            $candidatos = $candidatos
                ->filter(fn ($item) => self::normalizarNombreVinculo((string) ($item['subtipo_asignacion'] ?? '')) === $subtipo)
                ->values();
            if ($candidatos->isEmpty()) {
                return false;
            }
        }

        $bloqueId = (int) ($row->plan_bloque_id ?? 0);
        if ($bloqueId > 0) {
            $usoIdentificador = true;
            $candidatos = $candidatos
                ->filter(fn ($item) => (int) ($item['plan_bloque_id'] ?? 0) === $bloqueId)
                ->values();
            if ($candidatos->isEmpty()) {
                return false;
            }
        }

        $asignaturaId = (int) ($row->asignatura_id ?? 0);
        if ($asignaturaId > 0) {
            $usoIdentificador = true;
            $candidatos = $candidatos
                ->filter(fn ($item) => (int) ($item['asignatura_id'] ?? 0) === $asignaturaId)
                ->values();
            if ($candidatos->isEmpty()) {
                return false;
            }
        }

        $nombre = self::normalizarNombreVinculo((string) ($row->asignatura_nombre ?? ''));
        if ($nombre !== '') {
            $usoIdentificador = true;
            $candidatos = $candidatos
                ->filter(fn ($item) => self::nombreNecesidadNormalizado($item) === $nombre)
                ->values();
        }

        return $usoIdentificador && $candidatos->isNotEmpty();
    }

    private static function motivoPlanEstudioHuerfano(object $row, Collection $necesidadesPlanEstudio): string
    {
        $subtipo = trim((string) ($row->subtipo_asignacion ?? ''));
        $subtipoLabel = $subtipo !== '' ? str_replace('_', ' ', $subtipo) : 'sin subtipo';
        $cursoId = (int) ($row->establecimiento_curso_id ?? 0);

        if ($cursoId > 0 && ! $necesidadesPlanEstudio->contains(fn ($item) => (int) ($item['establecimiento_curso_id'] ?? 0) === $cursoId)) {
            return 'El curso o sección asociado ya no está activo, no tiene matrícula o dejó de tener un plan de estudio vigente.';
        }

        $planId = (int) ($row->plan_estudio_id ?? 0);
        if ($planId > 0 && ! $necesidadesPlanEstudio->contains(fn ($item) => (int) ($item['plan_estudio_id'] ?? 0) === $planId && ($cursoId <= 0 || (int) ($item['establecimiento_curso_id'] ?? 0) === $cursoId))) {
            return 'El plan de estudio asociado fue eliminado, reemplazado o ya no está asignado al curso.';
        }

        $bloqueId = (int) ($row->plan_bloque_id ?? 0);
        if ($bloqueId > 0 && ! $necesidadesPlanEstudio->contains(fn ($item) => (int) ($item['plan_bloque_id'] ?? 0) === $bloqueId)) {
            return 'El bloque del plan de estudio fue eliminado o dejó de estar vigente.';
        }

        $asignaturaId = (int) ($row->asignatura_id ?? 0);
        $nombre = self::normalizarNombreVinculo((string) ($row->asignatura_nombre ?? ''));
        if (($asignaturaId > 0 && ! $necesidadesPlanEstudio->contains(fn ($item) => (int) ($item['asignatura_id'] ?? 0) === $asignaturaId))
            || ($nombre !== '' && ! $necesidadesPlanEstudio->contains(fn ($item) => self::nombreNecesidadNormalizado($item) === $nombre))) {
            return 'La asignatura o componente curricular fue eliminado del plan de estudio vigente.';
        }

        if (trim((string) ($row->necesidad_key ?? '')) === '') {
            return 'Asignación antigua de plan de estudio sin vínculo identificable con una necesidad vigente.';
        }

        return 'La necesidad curricular de subtipo '.$subtipoLabel.' ya no existe en el plan de estudio vigente del establecimiento.';
    }

    private static function esAsignacionPlanNormativo(object $row): bool
    {
        if ((string) ($row->tipo_asignacion ?? '') === 'plan_normativo') {
            return true;
        }

        if ((string) ($row->tipo_asignacion ?? '') === 'plan_estudio') {
            return false;
        }

        $subtipo = self::normalizarNombreVinculo((string) ($row->subtipo_asignacion ?? ''));

        return in_array($subtipo, [
            'PLAN',
            'PLANES',
            'PLAN NORMATIVO',
            'PLANES NORMATIVOS',
            'PLANES PROGRAMAS',
        ], true);
    }

    private static function esNecesidadPlanNormativo(array $item): bool
    {
        if ((string) ($item['tipo_asignacion'] ?? '') === 'plan_normativo') {
            return true;
        }

        $subtipo = self::normalizarNombreVinculo((string) ($item['subtipo_asignacion'] ?? ''));

        return in_array($subtipo, [
            'PLAN',
            'PLANES',
            'PLAN NORMATIVO',
            'PLANES NORMATIVOS',
            'PLANES PROGRAMAS',
        ], true);
    }

    private static function planNormativoVigenteParaAsignacion(object $row, Collection $planesVigentes): bool
    {
        if ($planesVigentes->isEmpty()) {
            return false;
        }

        $funcionId = (int) ($row->dotacion_funcion_id ?? 0);
        if ($funcionId > 0) {
            // Una función declarada identifica un plan concreto. Si ese registro fue
            // eliminado, no debe considerarse vigente sólo porque exista otra regla
            // o plan con un nombre parecido.
            return $planesVigentes->contains(
                fn ($item) => (int) ($item['dotacion_funcion_id'] ?? 0) === $funcionId
            );
        }

        $key = trim((string) ($row->necesidad_key ?? ''));
        if ($key !== '' && $planesVigentes->contains(fn ($item) => (string) ($item['key'] ?? '') === $key)) {
            return true;
        }

        $reglaId = (int) ($row->dotacion_funcion_regla_id ?? 0);
        $nombre = self::normalizarNombreVinculo((string) ($row->asignatura_nombre ?? ''));

        if ($reglaId > 0) {
            $planesMismaRegla = $planesVigentes
                ->filter(fn ($item) => (int) ($item['dotacion_funcion_regla_id'] ?? 0) === $reglaId)
                ->values();

            if ($planesMismaRegla->isEmpty()) {
                return false;
            }

            if ($nombre !== '') {
                return $planesMismaRegla->contains(
                    fn ($item) => self::nombreNecesidadNormalizado($item) === $nombre
                );
            }

            // Compatibilidad para asignaciones antiguas sin nombre ni necesidad_key:
            // se acepta la regla sólo cuando identifica un único plan vigente.
            return $planesMismaRegla->count() === 1;
        }

        if ($nombre !== '') {
            return $planesVigentes->contains(
                fn ($item) => self::nombreNecesidadNormalizado($item) === $nombre
            );
        }

        return false;
    }

    private static function motivoPlanNormativoHuerfano(object $row, Collection $planesVigentes): string
    {
        $funcionId = (int) ($row->dotacion_funcion_id ?? 0);
        if ($funcionId > 0) {
            return 'El plan declarado fue eliminado de Dotación funciones y planes, pero sus horas continúan asignadas al docente.';
        }

        $reglaId = (int) ($row->dotacion_funcion_regla_id ?? 0);
        if ($reglaId > 0) {
            $planesMismaRegla = $planesVigentes
                ->filter(fn ($item) => (int) ($item['dotacion_funcion_regla_id'] ?? 0) === $reglaId);

            if ($planesMismaRegla->isEmpty()) {
                return 'La regla del plan fue eliminada, desactivada o ya no genera horas para este establecimiento y año.';
            }

            return 'El plan asignado no coincide con ningún plan vigente de la misma regla; puede corresponder a un plan eliminado o reemplazado.';
        }

        $key = trim((string) ($row->necesidad_key ?? ''));
        if ($key === '') {
            return 'Asignación antigua de plan sin vínculo identificable; el plan no existe actualmente en Dotación funciones y planes.';
        }

        return 'El plan asociado a esta asignación ya no existe en Dotación funciones y planes para este establecimiento y año.';
    }

    private static function nombreNecesidadNormalizado(array $item): string
    {
        return self::normalizarNombreVinculo((string) (
            $item['asignatura_nombre']
            ?? $item['titulo']
            ?? ''
        ));
    }

    private static function normalizarNombreVinculo(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private static function needRow(string $key, string $tipo, ?string $subtipo, array $data, Collection $asignaciones): array
    {
        $dotacionFuncionId = (int) ($data['dotacion_funcion_id'] ?? 0);
        $assigned = $asignaciones->filter(function ($row) use ($key, $dotacionFuncionId) {
            if ((string) ($row->necesidad_key ?? '') === $key) {
                return true;
            }

            // El identificador de la función declarada es estable aunque una
            // asignación histórica conserve una necesidad_key generada antes
            // de reordenar o renombrar los bloques del establecimiento.
            return $dotacionFuncionId > 0
                && (int) ($row->dotacion_funcion_id ?? 0) === $dotacionFuncionId;
        });
        $horasContrato = (float) ($data['horas_contrato'] ?? 0);
        $horasPlan = isset($data['horas_plan']) ? (float) $data['horas_plan'] : null;
        $assignedContrato = (float) $assigned->sum(fn ($row) => (float) $row->horas_contrato);
        $assignedPlan = (float) $assigned->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0));
        $esPlanEstudio = $tipo === 'plan_estudio' && $horasPlan !== null;
        $estadoRequeridas = $esPlanEstudio ? $horasPlan : $horasContrato;
        $estadoAsignadas = $esPlanEstudio ? $assignedPlan : $assignedContrato;

        return array_merge([
            'key' => $key,
            'tipo_asignacion' => $tipo,
            'subtipo_asignacion' => $subtipo,
            'titulo' => $data['titulo'] ?? 'Necesidad',
            'curso_label' => $data['curso_label'] ?? self::cursoLabel($data['curso'] ?? null),
            'establecimiento_curso_id' => $data['establecimiento_curso_id'] ?? ($data['curso']->id ?? null),
            'plan_estudio_id' => $data['plan_estudio_id'] ?? ($data['curso']->plan_estudio_id ?? null),
            'plan_bloque_id' => $data['plan_bloque_id'] ?? null,
            'asignatura_id' => $data['asignatura_id'] ?? null,
            'asignatura_nombre' => $data['asignatura_nombre'] ?? ($data['titulo'] ?? null),
            'nombre_personalizado' => $data['nombre_personalizado'] ?? null,
            'plan_comun_asociado' => $data['plan_comun_asociado'] ?? null,
            'asignatura_oficial' => $data['asignatura_oficial'] ?? null,
            'bloque' => $data['bloque'] ?? null,
            'horas_plan_requeridas' => $horasPlan,
            'horas_contrato_requeridas' => $horasContrato,
            'horas_aula_cronologicas' => $data['horas_aula_cronologicas'] ?? null,
            'horas_plan_asignadas' => $assignedPlan,
            'horas_contrato_asignadas' => $assignedContrato,
            'horas_plan_pendientes' => $horasPlan !== null ? max(0.0, round($horasPlan - $assignedPlan, 2)) : null,
            'horas_contrato_pendientes' => max(0.0, round($horasContrato - $assignedContrato, 2)),
            'estado' => self::estadoNecesidad($estadoRequeridas, $estadoAsignadas),
            'asignaciones' => $assigned->values(),
            'subvencion' => $data['subvencion'] ?? 'General',
            'fuente' => $data['fuente'] ?? null,
        ], $data);
    }

    private static function estadoNecesidad(float $requeridas, float $asignadas): array
    {
        if ($asignadas <= 0.01) {
            return ['key' => 'pendiente', 'label' => 'Pendiente', 'class' => 'text-bg-secondary'];
        }
        if ($asignadas + 0.01 < $requeridas) {
            return ['key' => 'parcial', 'label' => 'Parcial', 'class' => 'text-bg-warning'];
        }
        if ($asignadas - 0.01 > $requeridas) {
            return ['key' => 'excedida', 'label' => 'Excedida', 'class' => 'text-bg-danger'];
        }
        return ['key' => 'cubierta', 'label' => 'Cubierta', 'class' => 'text-bg-success'];
    }

    private static function contratoDesdeAula(string $proporcion, float $horasAula): float
    {
        if ($horasAula <= 0) {
            return 0.0;
        }

        $conversion = DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula($proporcion, $horasAula);

        return (float) ($conversion['horas_contrato'] ?? 0);
    }


    public static function coverageEstamento(object|array $row): string
    {
        $value = Str::of((string) data_get($row, 'estamento_cobertura', 'docente'))
            ->ascii()
            ->lower()
            ->trim()
            ->toString();

        return $value === 'asistente' ? 'asistente' : 'docente';
    }

    private static function proportionGroup(?string $proporcion): string
    {
        $valor = Str::of((string) $proporcion)
            ->ascii()
            ->upper()
            ->replace([' ', '-', '_'], '/')
            ->replaceMatches('/\/+/', '/')
            ->trim('/')
            ->toString();

        if ($valor === '' || $valor === '65/35') {
            return '65_35';
        }

        if ($valor === '60/40') {
            return '60_40';
        }

        return 'especial';
    }

    private static function esOrientacion(?string $nombre): bool
    {
        $texto = Str::of((string) $nombre)->ascii()->upper()->trim()->toString();

        return $texto === 'ORIENTACION'
            || str_contains($texto, 'ORIENTACION');
    }

    private static function esFilaResumenPlan(?string $titulo, ?string $subtipo): bool
    {
        $tipo = Str::of((string) $subtipo)->ascii()->lower()->toString();
        $nombre = Str::of((string) $titulo)->ascii()->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();

        if (in_array($tipo, ['total', 'subtotal', 'sub_total', 'resumen', 'total_plan'], true)) {
            return true;
        }

        return str_starts_with($nombre, 'total ')
            || str_starts_with($nombre, 'sub total ')
            || str_starts_with($nombre, 'subtotal ')
            || str_contains($nombre, 'total tiempo minimo')
            || str_contains($nombre, 'sub total tiempo minimo')
            || str_contains($nombre, 'subtotal tiempo minimo')
            || str_contains($nombre, 'total plan');
    }


    private static function key(string $prefix, array $parts): string
    {
        return $prefix.':'.md5(collect($parts)->map(fn ($p) => (string) $p)->implode('|'));
    }


    private static function cursoLabel($curso): ?string
    {
        if (! $curso) {
            return null;
        }

        $nombre = $curso->nombre_seccion
            ?? $curso->curso?->nombre
            ?? $curso->nombre_curso
            ?? $curso->curso_nombre
            ?? 'Curso';
        $letra = $curso->letra ?? null;

        if ($letra && ! str_ends_with((string) $nombre, ' '.$letra)) {
            return trim($nombre.' '.$letra);
        }

        return $nombre;
    }

    private static function bloqueLabel(?string $tipo): string
    {
        return match ($tipo) {
            'libre_disposicion' => 'Libre disposición',
            'total' => 'Total plan',
            'tiempo_minimo', 'obligatoria' => 'Tiempo mínimo obligatorio',
            default => ucfirst(str_replace('_', ' ', (string) $tipo)),
        };
    }
}
