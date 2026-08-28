<?php

namespace App\Support;

use App\Models\DotacionCursoCombinado;
use App\Models\DotacionCursoCombinadoAsignatura;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DotacionCursoCombinadoCalculator
{
    private static ?bool $tablesReady = null;

    public static function apply(
        Collection $items,
        Collection $asignaciones,
        Establecimiento $establecimiento,
        int $anio
    ): Collection {
        if (! self::tablesReady()) {
            return $items->values();
        }

        $grupos = DotacionCursoCombinado::query()
            ->with(['miembros.curso.curso', 'asignaturas'])
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        if ($grupos->isEmpty()) {
            return $items->values();
        }

        $courseGroupMap = [];
        foreach ($grupos as $grupo) {
            foreach ($grupo->miembros as $miembro) {
                $courseGroupMap[(int) $miembro->establecimiento_curso_id] = $grupo;
            }
        }

        $result = $items
            ->reject(fn (array $item) => isset($courseGroupMap[(int) ($item['establecimiento_curso_id'] ?? 0)]))
            ->values();

        foreach ($grupos as $grupo) {
            $courseIds = $grupo->miembros
                ->pluck('establecimiento_curso_id')
                ->map(fn ($id) => (int) $id)
                ->values();
            $groupItems = $items
                ->filter(fn (array $item) => $courseIds->contains((int) ($item['establecimiento_curso_id'] ?? 0)))
                ->values();

            if ($groupItems->isEmpty()) {
                continue;
            }

            $rules = $grupo->asignaturas->keyBy('asignatura_key');
            $courseLabels = $grupo->miembros
                ->mapWithKeys(fn ($miembro) => [
                    (int) $miembro->establecimiento_curso_id => self::courseLabel($miembro->curso),
                ]);

            foreach ($groupItems->groupBy(fn (array $item) => self::subjectKey($item)) as $subjectKey => $subjectItems) {
                $subjectItems = collect($subjectItems)->values();
                $rule = $rules->get($subjectKey);
                $courseTotals = $subjectItems
                    ->groupBy(fn (array $item) => (int) ($item['establecimiento_curso_id'] ?? 0))
                    ->map(fn (Collection $rows) => round((float) $rows->sum(
                        fn (array $row) => (float) ($row['horas_plan_requeridas'] ?? 0)
                    ), 2));

                $presentCourses = $courseTotals->filter(fn ($hours) => (float) $hours > 0.0)->count();
                $defaultMode = $presentCourses > 1 ? 'conjunta' : 'separada';
                $mode = in_array($rule?->modalidad, array_keys(DotacionCursoCombinadoAsignatura::MODALIDADES), true)
                    ? $rule->modalidad
                    : $defaultMode;

                $rawHours = round((float) $courseTotals->sum(), 2);
                $maxHours = round((float) ($courseTotals->max() ?? 0), 2);
                $hours = self::resolvedHours($mode, $courseTotals, $rule, $maxHours);
                $proportion = self::resolvedProportion($grupo->proporcion, $subjectItems);
                $conversion = self::conversionForProportion($proportion['key'], $hours);
                $needKey = self::needKey((int) $grupo->id, (string) $subjectKey);
                $assigned = $asignaciones->where('necesidad_key', $needKey)->values();
                $assignedPlan = round((float) $assigned->sum(
                    fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0)
                ), 2);
                $assignedContract = round((float) $assigned->sum(
                    fn ($row) => (float) ($row->horas_contrato ?? 0)
                ), 2);
                $title = self::subjectName($subjectItems);
                $representative = $subjectItems->first();
                $ruleId = $rule?->id;
                $planIds = $subjectItems->pluck('plan_estudio_id')->filter()->unique()->values();
                $subjectIds = $subjectItems->pluck('asignatura_id')->filter()->unique()->values();
                $pendingPlan = max(0.0, round($hours - $assignedPlan, 2));
                $requiredContract = (float) ($conversion['horas_contrato'] ?? 0);

                $result->push(array_merge($representative, [
                    'key' => $needKey,
                    'tipo_asignacion' => 'plan_estudio',
                    'subtipo_asignacion' => 'curso_combinado',
                    'titulo' => $title,
                    'curso_label' => $grupo->nombre,
                    'establecimiento_curso_id' => (int) ($courseIds->first() ?? 0),
                    'dotacion_curso_combinado_id' => (int) $grupo->id,
                    'dotacion_curso_combinado_asignatura_id' => $ruleId,
                    'plan_estudio_id' => $planIds->count() === 1 ? (int) $planIds->first() : null,
                    'plan_bloque_id' => null,
                    'asignatura_id' => $subjectIds->count() === 1 ? (int) $subjectIds->first() : null,
                    'asignatura_nombre' => $title,
                    'bloque' => 'Curso combinado · '.(DotacionCursoCombinadoAsignatura::MODALIDADES[$mode] ?? ucfirst($mode)),
                    'horas_plan_requeridas' => $hours,
                    'horas_contrato_requeridas' => $requiredContract,
                    'horas_aula_cronologicas' => $conversion['horas_aula_cronologicas'] ?? null,
                    'horas_plan_asignadas' => $assignedPlan,
                    'horas_contrato_asignadas' => $assignedContract,
                    'horas_plan_pendientes' => $pendingPlan,
                    'horas_contrato_pendientes' => max(0.0, round($requiredContract - $assignedContract, 2)),
                    'estado' => self::status($hours, $assignedPlan),
                    'asignaciones' => $assigned,
                    'subvencion' => self::resolvedSubsidy($subjectItems),
                    'fuente' => 'Necesidad consolidada de cursos combinados',
                    'proporcion' => $proportion['label'],
                    'proporcion_key' => $proportion['key'],
                    'origen_proporcion' => $proportion['origin'],
                    'origen_proporcion_label' => $proportion['origin_label'],
                    'motivo_proporcion' => $proportion['reason'],
                    'curso_combinado' => true,
                    'curso_combinado_nombre' => $grupo->nombre,
                    'curso_combinado_modalidad' => $mode,
                    'curso_combinado_asignatura_key' => $subjectKey,
                    'curso_combinado_cursos' => $courseLabels->values()->all(),
                    'curso_combinado_curso_ids' => $courseIds->all(),
                    'curso_combinado_horas_por_curso' => $courseTotals
                        ->mapWithKeys(fn ($value, $courseId) => [
                            (string) ($courseLabels->get((int) $courseId) ?? 'Curso '.$courseId) => (float) $value,
                        ])->all(),
                    'horas_plan_brutas' => $rawHours,
                    'horas_plan_reduccion' => max(0.0, round($rawHours - $hours, 2)),
                    'horas_conjuntas' => $rule?->horas_conjuntas !== null
                        ? (float) $rule->horas_conjuntas
                        : null,
                    'horas_personalizadas' => $rule?->horas_personalizadas !== null
                        ? (float) $rule->horas_personalizadas
                        : null,
                    'horas_exclusivas' => $rule?->horas_exclusivas ?? [],
                    'observacion_combinacion' => $rule?->observacion,
                ]));
            }
        }

        return $result
            ->sortBy(fn (array $row) => sprintf(
                '%s|%s|%s',
                $row['curso_label'] ?? '',
                $row['bloque'] ?? '',
                $row['titulo'] ?? ''
            ))
            ->values();
    }

    public static function summary(
        Establecimiento $establecimiento,
        int $anio,
        Collection|array $planNeeds
    ): array {
        $planNeeds = collect($planNeeds);
        if (! self::tablesReady()) {
            return [
                'tables_ready' => false,
                'grupos' => collect(),
                'cursos_disponibles' => collect(),
                'resumen' => self::emptySummary(),
            ];
        }

        $grupos = DotacionCursoCombinado::query()
            ->with(['miembros.curso.curso', 'asignaturas'])
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        $activeCourseIds = $grupos->where('activo', true)
            ->flatMap(fn ($grupo) => $grupo->miembros->pluck('establecimiento_curso_id'))
            ->map(fn ($id) => (int) $id)
            ->unique();

        $cursosDisponibles = EstablecimientoCurso::query()
            ->with('curso')
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('activo', true)
            ->where('matricula', '>', 0)
            ->orderBy('curso_id')
            ->orderBy('letra')
            ->get()
            ->map(fn (EstablecimientoCurso $curso) => [
                'id' => (int) $curso->id,
                'label' => self::courseLabel($curso),
                'matricula' => (int) ($curso->matricula ?? 0),
                'disponible' => ! $activeCourseIds->contains((int) $curso->id),
            ]);

        $groupRows = $planNeeds
            ->filter(fn (array $row) => ! empty($row['dotacion_curso_combinado_id']))
            ->groupBy(fn (array $row) => (int) $row['dotacion_curso_combinado_id']);

        $gruposResumen = $grupos->map(function (DotacionCursoCombinado $grupo) use ($groupRows) {
            $rows = collect($groupRows->get((int) $grupo->id, []))->values();
            $members = $grupo->miembros->map(fn ($miembro) => [
                'id' => (int) $miembro->establecimiento_curso_id,
                'label' => self::courseLabel($miembro->curso),
                'matricula' => (int) ($miembro->curso?->matricula ?? 0),
            ])->values();

            $requiredHours = round((float) $rows->sum(
                fn ($row) => (float) ($row['horas_plan_requeridas'] ?? 0)
            ), 2);

            return [
                'id' => (int) $grupo->id,
                'nombre' => $grupo->nombre,
                'anio' => (int) $grupo->anio,
                'proporcion' => $grupo->proporcion,
                'proporcion_label' => $grupo->proporcion === 'auto'
                    ? 'Automática'
                    : self::proportionLabel(match ($grupo->proporcion) {
                        'nt_jec' => 'parvularia_jec_especial_65_35_ld',
                        'nt_sin_jec' => 'parvularia_sin_jec_especial_65_35_ld',
                        default => $grupo->proporcion,
                    }),
                'observacion' => $grupo->observacion,
                'activo' => (bool) $grupo->activo,
                'miembros' => $members,
                'asignaturas' => $rows,
                'totales' => [
                    'horas_brutas' => round((float) $rows->sum(fn ($row) => (float) ($row['horas_plan_brutas'] ?? 0)), 2),
                    'horas_requeridas' => $requiredHours,
                    // La necesidad contractual del grupo se convierte después de
                    // consolidar todas sus horas aula. No se suman los redondeos de
                    // cada asignatura porque eso sobredimensionaría el contrato.
                    'horas_contrato' => self::contractForRows($rows),
                    'reduccion' => round((float) $rows->sum(fn ($row) => (float) ($row['horas_plan_reduccion'] ?? 0)), 2),
                    'asignadas' => round((float) $rows->sum(fn ($row) => (float) ($row['horas_plan_asignadas'] ?? 0)), 2),
                    'pendientes' => round((float) $rows->sum(fn ($row) => (float) ($row['horas_plan_pendientes'] ?? 0)), 2),
                ],
            ];
        })->values();

        $activeRows = $gruposResumen->where('activo', true);

        return [
            'tables_ready' => true,
            'grupos' => $gruposResumen,
            'cursos_disponibles' => $cursosDisponibles,
            'resumen' => [
                'grupos_activos' => $activeRows->count(),
                'cursos_combinados' => $activeRows->sum(fn ($row) => count($row['miembros'] ?? [])),
                'horas_brutas' => round((float) $activeRows->sum(fn ($row) => (float) data_get($row, 'totales.horas_brutas', 0)), 2),
                'horas_requeridas' => round((float) $activeRows->sum(fn ($row) => (float) data_get($row, 'totales.horas_requeridas', 0)), 2),
                'horas_contrato' => round((float) $activeRows->sum(fn ($row) => (float) data_get($row, 'totales.horas_contrato', 0)), 2),
                'reduccion' => round((float) $activeRows->sum(fn ($row) => (float) data_get($row, 'totales.reduccion', 0)), 2),
            ],
        ];
    }

    public static function adjustedContractRequired(Collection|array $items): float
    {
        $items = collect($items);
        $combined = $items->filter(
            fn (array $row) => ! empty($row['dotacion_curso_combinado_id'])
        );

        if ($combined->isEmpty()) {
            return round((float) $items->sum(
                fn (array $row) => (float) ($row['horas_contrato_requeridas'] ?? 0)
            ), 2);
        }

        $independent = $items->reject(
            fn (array $row) => ! empty($row['dotacion_curso_combinado_id'])
        )->sum(fn (array $row) => (float) ($row['horas_contrato_requeridas'] ?? 0));
        $groups = $combined
            ->groupBy(fn (array $row) => (int) $row['dotacion_curso_combinado_id'])
            ->sum(fn (Collection $rows) => self::contractForRows($rows));

        return round((float) $independent + (float) $groups, 2);
    }

    public static function subjectKey(array $item): string
    {
        $canonical = trim((string) ($item['plan_comun_asociado'] ?? ''));
        if ($canonical === '') {
            $canonical = trim((string) ($item['asignatura_nombre'] ?? $item['titulo'] ?? 'ASIGNATURA'));
        }

        $normalized = Str::of($canonical)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', ' ')
            ->squish()
            ->toString();

        // La agrupación debe usar el nombre curricular normalizado y no el ID de
        // una fila particular. Las asignaturas oficiales y las horas de libre
        // disposición pueden provenir de tablas distintas y tener IDs diferentes,
        // aun cuando ambas cubran la misma asignatura del plan común.
        if ($normalized === '') {
            $normalized = 'ASIGNATURA '.((int) ($item['asignatura_id'] ?? 0));
        }

        $base = 'nombre_'.Str::slug($normalized, '_');

        return Str::limit($base, 105, '').'_'.substr(sha1($normalized), 0, 10);
    }

    public static function needKey(int $groupId, string $subjectKey): string
    {
        return 'plan_combinado|'.$groupId.'|'.substr(sha1($subjectKey), 0, 20);
    }

    public static function tablesReady(): bool
    {
        if (self::$tablesReady === null) {
            self::$tablesReady = Schema::hasTable('dotacion_cursos_combinados')
                && Schema::hasTable('dotacion_curso_combinado_miembros')
                && Schema::hasTable('dotacion_curso_combinado_asignaturas');
        }

        return self::$tablesReady;
    }

    public static function clearCache(): void
    {
        self::$tablesReady = null;
    }


    private static function contractForRows(Collection $rows): float
    {
        $contract = (float) $rows
            ->groupBy(fn (array $row) => (string) ($row['proporcion_key'] ?? '65_35'))
            ->map(function (Collection $proportionRows, string $proportion): float {
                $hours = round((float) $proportionRows->sum(
                    fn (array $row) => (float) ($row['horas_plan_requeridas'] ?? 0)
                ), 2);
                $conversion = self::conversionForProportion($proportion, $hours);

                return (float) ($conversion['horas_contrato'] ?? 0);
            })
            ->sum();

        // La jornada se contrata en horas enteras. Se consolida primero toda la
        // necesidad del grupo y se redondea una sola vez hacia arriba para no
        // inflar el resultado por cada asignatura o bloque del plan.
        return $contract > 0 ? (float) ceil($contract) : 0.0;
    }

    private static function resolvedHours(
        string $mode,
        Collection $courseTotals,
        ?DotacionCursoCombinadoAsignatura $rule,
        float $defaultJoint
    ): float {
        return round(match ($mode) {
            'separada' => (float) $courseTotals->sum(),
            'personalizada' => max(0.0, (float) ($rule?->horas_personalizadas ?? $defaultJoint)),
            'mixta' => max(0.0, (float) ($rule?->horas_conjuntas ?? $defaultJoint))
                + collect($rule?->horas_exclusivas ?? [])->sum(fn ($value) => max(0.0, (float) $value)),
            default => max(0.0, (float) ($rule?->horas_conjuntas ?? $defaultJoint)),
        }, 2);
    }

    private static function resolvedProportion(string $configured, Collection $items): array
    {
        $manual = match ($configured) {
            '65_35' => '65_35',
            '60_40' => '60_40',
            'nt_jec' => 'parvularia_jec_especial_65_35_ld',
            'nt_sin_jec' => 'parvularia_sin_jec_especial_65_35_ld',
            default => null,
        };

        if ($manual !== null) {
            return [
                'key' => $manual,
                'label' => self::proportionLabel($manual),
                'origin' => 'curso_combinado_configurado',
                'origin_label' => 'Curso combinado configurado',
                'reason' => 'Regla contractual definida manualmente para el grupo combinado.',
            ];
        }

        $keys = $items->map(function (array $row) {
            return self::normalizeProportionKey($row['proporcion_key'] ?? $row['proporcion'] ?? null);
        })->filter()->unique()->values();

        if ($keys->count() === 1) {
            $key = (string) $keys->first();

            return [
                'key' => $key,
                'label' => self::proportionLabel($key),
                'origin' => 'curso_combinado_automatico',
                'origin_label' => 'Cursos combinados · automática',
                'reason' => str_starts_with($key, 'parvularia_')
                    ? 'Todos los cursos del grupo utilizan la misma regla especial de Educación Parvularia.'
                    : 'Todos los cursos del grupo utilizan la misma proporción.',
            ];
        }

        return [
            'key' => '65_35',
            'label' => '65/35',
            'origin' => 'curso_combinado_mixto_respaldo',
            'origin_label' => 'Cursos combinados · proporciones mixtas',
            'reason' => 'Los cursos tienen reglas distintas y el grupo está en automático. Se utiliza 65/35 como respaldo hasta definir explícitamente la regla del grupo.',
        ];
    }

    private static function normalizeProportionKey(mixed $value): ?string
    {
        $value = trim((string) $value);

        return match ($value) {
            '60/40', '60_40' => '60_40',
            '65/35', '65_35' => '65_35',
            'nt_jec', 'parvularia_jec_especial_65_35_ld' => 'parvularia_jec_especial_65_35_ld',
            'nt_sin_jec', 'parvularia_sin_jec_especial_65_35_ld' => 'parvularia_sin_jec_especial_65_35_ld',
            default => $value !== '' ? $value : null,
        };
    }

    private static function proportionLabel(string $key): string
    {
        return match ($key) {
            '60_40' => '60/40',
            '65_35' => '65/35',
            'parvularia_jec_especial_65_35_ld' => 'NT1/NT2 con JEC · regla especial',
            'parvularia_sin_jec_especial_65_35_ld' => 'NT1/NT2 sin JEC · regla especial',
            default => DocenteHorasNoLectivasCalculator::proporcionLabel($key),
        };
    }

    private static function conversionForProportion(string $proportion, float $hours): array
    {
        $proportion = self::normalizeProportionKey($proportion) ?? '65_35';
        $hours = max(0.0, round($hours, 2));

        if (! in_array($proportion, [
            'parvularia_jec_especial_65_35_ld',
            'parvularia_sin_jec_especial_65_35_ld',
        ], true)) {
            $conversion = DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula(
                in_array($proportion, ['65_35', '60_40'], true) ? $proportion : '65_35',
                $hours
            );
            $conversion['horas_aula_cronologicas'] = round($hours * 45 / 60, 4);

            return $conversion;
        }

        $withJec = $proportion === 'parvularia_jec_especial_65_35_ld';
        $baseHours = min($hours, 32.0);
        $freeHours = max(0.0, $hours - 32.0);
        $baseContractReference = $withJec ? 50.0 : 47.0;
        $baseChronologicalReference = $withJec ? 32.25 : 30.0;
        $baseContract = round(($baseHours / 32.0) * $baseContractReference, 4);
        $baseChronological = round(($baseHours / 32.0) * $baseChronologicalReference, 4);
        $freeChronological = round($freeHours * 45 / 60, 4);
        $freeContract = $freeHours > 0
            ? round($freeChronological / 0.65, 4)
            : 0.0;
        $contract = round($baseContract + $freeContract, 4);

        return [
            'horas_contrato' => $contract,
            'horas_contrato_redondeadas' => $contract > 0 ? (float) ceil($contract) : 0.0,
            'horas_aula_cronologicas' => round($baseChronological + $freeChronological, 4),
            'motivo' => ($withJec ? 'NT1/NT2 con JEC' : 'NT1/NT2 sin JEC')
                .': se aplica la regla especial de Educación Parvularia al bloque consolidado; las horas sobre 32 se convierten con 65/35.',
        ];
    }

    private static function resolvedSubsidy(Collection $items): string
    {
        $values = $items->pluck('subvencion')->filter()->unique()->values();
        return $values->count() === 1 ? (string) $values->first() : 'Mixta';
    }

    private static function subjectName(Collection $items): string
    {
        $canonical = $items->pluck('plan_comun_asociado')->filter()->first();
        return trim((string) ($canonical ?: $items->pluck('asignatura_nombre')->filter()->first() ?: $items->pluck('titulo')->filter()->first() ?: 'Asignatura'));
    }

    private static function status(float $required, float $assigned): array
    {
        if ($assigned <= 0.01) {
            return ['key' => 'pendiente', 'label' => 'Pendiente', 'class' => 'text-bg-secondary'];
        }
        if ($assigned + 0.01 < $required) {
            return ['key' => 'parcial', 'label' => 'Parcial', 'class' => 'text-bg-warning'];
        }
        if ($assigned - 0.01 > $required) {
            return ['key' => 'excedida', 'label' => 'Excedida', 'class' => 'text-bg-danger'];
        }
        return ['key' => 'cubierta', 'label' => 'Cubierta', 'class' => 'text-bg-success'];
    }

    private static function courseLabel(?EstablecimientoCurso $curso): string
    {
        if (! $curso) {
            return 'Curso sin identificar';
        }

        $base = trim((string) ($curso->nombre_seccion ?: $curso->curso?->nombre ?: 'Curso'));
        $letra = trim((string) ($curso->letra ?? ''));
        if ($letra !== '' && ! str_ends_with(Str::upper($base), Str::upper($letra))) {
            $base .= ' '.$letra;
        }

        return trim($base);
    }

    private static function emptySummary(): array
    {
        return [
            'grupos_activos' => 0,
            'cursos_combinados' => 0,
            'horas_brutas' => 0.0,
            'horas_requeridas' => 0.0,
            'horas_contrato' => 0.0,
            'reduccion' => 0.0,
        ];
    }
}
