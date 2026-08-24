<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionCursoCombinado;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DotacionCursoCombinadoController extends Controller
{
    private array $allowedRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

    public function store(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $this->authorizeScope($request, $establecimiento);
        $data = $this->validateData($request);
        $courses = $this->validatedCourses($establecimiento, (int) $data['anio'], $data['curso_ids']);
        $this->validateNoActiveOverlap($establecimiento, (int) $data['anio'], $courses->pluck('id')->all());
        $this->validateAutomaticProportion($establecimiento, (int) $data['anio'], $courses, (string) $data['proporcion']);

        DB::transaction(function () use ($request, $establecimiento, $data, $courses): void {
            $group = DotacionCursoCombinado::create([
                'establecimiento_id' => $establecimiento->id,
                'anio' => (int) $data['anio'],
                'nombre' => trim((string) $data['nombre']),
                'proporcion' => (string) $data['proporcion'],
                'observacion' => $data['observacion'] ?? null,
                'activo' => (bool) ($data['activo'] ?? true),
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);

            $group->miembros()->createMany($courses->map(fn ($course) => [
                'establecimiento_curso_id' => $course->id,
            ])->all());
        });

        DotacionCursoCombinadoCalculator::clearCache();

        return redirect()
            ->route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $data['anio'], 'tab' => 'cursos-combinados'])
            ->with('success', 'Curso combinado creado. Revise la modalidad de cada asignatura antes de asignar horas.');
    }

    public function update(
        Request $request,
        Establecimiento $establecimiento,
        DotacionCursoCombinado $cursoCombinado
    ): RedirectResponse {
        $this->authorizeScope($request, $establecimiento);
        $this->authorizeGroup($establecimiento, $cursoCombinado);
        $data = $this->validateData($request, true);
        $courses = $this->validatedCourses($establecimiento, (int) $data['anio'], $data['curso_ids']);
        $this->validateSubjectConfiguration($data['asignaturas'] ?? []);
        $active = (bool) ($data['activo'] ?? false);

        if ($active) {
            $this->validateNoActiveOverlap(
                $establecimiento,
                (int) $data['anio'],
                $courses->pluck('id')->all(),
                (int) $cursoCombinado->id
            );
            $this->validateAutomaticProportion($establecimiento, (int) $data['anio'], $courses, (string) $data['proporcion']);
        }

        DB::transaction(function () use ($request, $cursoCombinado, $data, $courses, $active): void {
            $allowedCourseIds = $courses->pluck('id')->map(fn ($id) => (string) $id)->all();

            $cursoCombinado->update([
                'anio' => (int) $data['anio'],
                'nombre' => trim((string) $data['nombre']),
                'proporcion' => (string) $data['proporcion'],
                'observacion' => $data['observacion'] ?? null,
                'activo' => $active,
                'updated_by' => $request->user()?->id,
            ]);

            $cursoCombinado->miembros()->delete();
            $cursoCombinado->miembros()->createMany($courses->map(fn ($course) => [
                'establecimiento_curso_id' => $course->id,
            ])->all());

            $submittedKeys = [];
            foreach (($data['asignaturas'] ?? []) as $row) {
                $key = trim((string) ($row['key'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $submittedKeys[] = $key;
                $exclusive = collect($row['horas_exclusivas'] ?? [])
                    ->filter(fn ($value, $courseId) => in_array((string) $courseId, $allowedCourseIds, true))
                    ->mapWithKeys(fn ($value, $courseId) => [(string) $courseId => max(0.0, (float) $value)])
                    ->filter(fn ($value) => $value > 0.0)
                    ->all();

                $cursoCombinado->asignaturas()->updateOrCreate(
                    ['asignatura_key' => $key],
                    [
                        'asignatura_nombre' => trim((string) ($row['nombre'] ?? 'Asignatura')),
                        'modalidad' => (string) ($row['modalidad'] ?? 'conjunta'),
                        'horas_conjuntas' => $this->nullableFloat($row['horas_conjuntas'] ?? null),
                        'horas_personalizadas' => $this->nullableFloat($row['horas_personalizadas'] ?? null),
                        'horas_exclusivas' => $exclusive ?: null,
                        'observacion' => trim((string) ($row['observacion'] ?? '')) ?: null,
                    ]
                );
            }

            if ($submittedKeys !== []) {
                $cursoCombinado->asignaturas()->whereNotIn('asignatura_key', $submittedKeys)->delete();
            }
        });

        DotacionCursoCombinadoCalculator::clearCache();

        return redirect()
            ->route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $data['anio'], 'tab' => 'cursos-combinados'])
            ->with('success', 'Configuración del curso combinado actualizada. Las necesidades de horas aula fueron recalculadas.');
    }

    public function destroy(
        Request $request,
        Establecimiento $establecimiento,
        DotacionCursoCombinado $cursoCombinado
    ): RedirectResponse {
        $this->authorizeScope($request, $establecimiento);
        $this->authorizeGroup($establecimiento, $cursoCombinado);

        $assignments = DotacionDocenteAsignacion::query()
            ->where('dotacion_curso_combinado_id', $cursoCombinado->id)
            ->where('estado', 'activa')
            ->count();

        if ($assignments > 0) {
            return back()->withErrors([
                'curso_combinado' => 'El grupo tiene '.$assignments.' asignación(es) activa(s). Elimine esas horas desde Asignación de horas antes de borrar el grupo.',
            ]);
        }

        $year = (int) $cursoCombinado->anio;
        $cursoCombinado->delete();
        DotacionCursoCombinadoCalculator::clearCache();

        return redirect()
            ->route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $year, 'tab' => 'cursos-combinados'])
            ->with('success', 'Curso combinado eliminado. Los cursos originales continúan disponibles de forma independiente.');
    }

    private function validateData(Request $request, bool $updating = false): array
    {
        $rules = [
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'nombre' => ['required', 'string', 'max:180'],
            'proporcion' => ['required', 'in:auto,65_35,60_40,nt_jec,nt_sin_jec'],
            'observacion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['nullable', 'boolean'],
            'curso_ids' => ['required', 'array', 'min:2'],
            'curso_ids.*' => ['required', 'integer', 'distinct'],
        ];

        if ($updating) {
            $rules += [
                'asignaturas' => ['nullable', 'array'],
                'asignaturas.*.key' => ['required_with:asignaturas', 'string', 'max:120'],
                'asignaturas.*.nombre' => ['required_with:asignaturas', 'string', 'max:255'],
                'asignaturas.*.modalidad' => ['required_with:asignaturas', 'in:conjunta,separada,personalizada,mixta'],
                'asignaturas.*.horas_conjuntas' => ['nullable', 'numeric', 'min:0', 'max:999'],
                'asignaturas.*.horas_personalizadas' => ['nullable', 'numeric', 'min:0.25', 'max:999'],
                'asignaturas.*.horas_exclusivas' => ['nullable', 'array'],
                'asignaturas.*.horas_exclusivas.*' => ['nullable', 'numeric', 'min:0', 'max:999'],
                'asignaturas.*.observacion' => ['nullable', 'string', 'max:1000'],
            ];
        }

        return $request->validate($rules);
    }


    private function validateSubjectConfiguration(array $rows): void
    {
        foreach ($rows as $index => $row) {
            $mode = (string) ($row['modalidad'] ?? 'conjunta');
            $name = trim((string) ($row['nombre'] ?? 'Asignatura')) ?: 'Asignatura';

            if ($mode === 'personalizada'
                && ($row['horas_personalizadas'] ?? null) !== 0
                && empty($row['horas_personalizadas'])) {
                throw ValidationException::withMessages([
                    "asignaturas.{$index}.horas_personalizadas" => 'Debe indicar las horas aula personalizadas para '.$name.'.',
                ]);
            }

            if ($mode === 'mixta') {
                $joint = ($row['horas_conjuntas'] ?? null) === null || $row['horas_conjuntas'] === ''
                    ? null
                    : max(0.0, (float) $row['horas_conjuntas']);
                $exclusive = collect($row['horas_exclusivas'] ?? [])->sum(
                    fn ($value) => max(0.0, (float) $value)
                );

                if ($joint !== null && $joint <= 0.0 && $exclusive <= 0.0) {
                    throw ValidationException::withMessages([
                        "asignaturas.{$index}.horas_conjuntas" => 'La modalidad mixta de '.$name.' debe tener horas conjuntas o al menos una hora exclusiva por curso.',
                    ]);
                }
            }
        }
    }

    private function validatedCourses(Establecimiento $establecimiento, int $year, array $ids)
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $courses = EstablecimientoCurso::query()
            ->with(['curso', 'planEstudio'])
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $year)
            ->where('activo', true)
            ->whereIn('id', $ids)
            ->get();

        if ($courses->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'curso_ids' => 'Uno o más cursos no existen, están inactivos o pertenecen a otro establecimiento/año.',
            ]);
        }

        return $courses;
    }

    private function validateNoActiveOverlap(
        Establecimiento $establecimiento,
        int $year,
        array $courseIds,
        ?int $exceptGroupId = null
    ): void {
        $query = DotacionCursoCombinado::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $year)
            ->where('activo', true)
            ->when($exceptGroupId, fn ($builder) => $builder->where('id', '<>', $exceptGroupId))
            ->whereHas('miembros', fn ($builder) => $builder->whereIn('establecimiento_curso_id', $courseIds));

        $overlap = $query->with('miembros.curso.curso')->first();
        if ($overlap) {
            throw ValidationException::withMessages([
                'curso_ids' => 'Uno de los cursos seleccionados ya pertenece al grupo activo “'.$overlap->nombre.'”.',
            ]);
        }
    }

    private function validateAutomaticProportion(
        Establecimiento $establecimiento,
        int $year,
        $courses,
        string $proportion
    ): void {
        $percentage = DotacionEstablecimientoCalculator::porcentajePrioritariosPara($establecimiento, $year);
        $proportions = $courses->map(function (EstablecimientoCurso $course) use ($percentage) {
            $calc = DotacionEstablecimientoCalculator::contratoEquivalenteAsignacion($course, 1.0, $percentage, 'tiempo_minimo');

            return $this->normalizeProportionKey($calc['proporcion'] ?? null);
        })->filter()->unique()->values();

        if ($proportion === 'auto' && $proportions->count() > 1) {
            $labels = $proportions
                ->map(fn (string $value) => $this->proportionLabel($value))
                ->implode(', ');

            throw ValidationException::withMessages([
                'proporcion' => 'Los cursos seleccionados utilizan reglas contractuales distintas ('.$labels.'). Seleccione explícitamente la regla aplicable al grupo combinado.',
            ]);
        }

        if (in_array($proportion, ['nt_jec', 'nt_sin_jec'], true)) {
            $nonParvularia = $proportions->reject(fn (string $value) => in_array($value, [
                'parvularia_jec_especial_65_35_ld',
                'parvularia_sin_jec_especial_65_35_ld',
            ], true));

            if ($nonParvularia->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'proporcion' => 'La regla especial NT1/NT2 solo puede aplicarse cuando todos los cursos seleccionados corresponden a Educación Parvularia.',
                ]);
            }
        }
    }

    private function normalizeProportionKey(mixed $value): ?string
    {
        $value = trim((string) $value);

        return match ($value) {
            '60/40', '60_40' => '60_40',
            '65/35', '65_35' => '65_35',
            'parvularia_jec_especial_65_35_ld', 'nt_jec' => 'parvularia_jec_especial_65_35_ld',
            'parvularia_sin_jec_especial_65_35_ld', 'nt_sin_jec' => 'parvularia_sin_jec_especial_65_35_ld',
            default => $value !== '' ? $value : null,
        };
    }

    private function proportionLabel(string $value): string
    {
        return match ($value) {
            '60_40' => '60/40',
            '65_35' => '65/35',
            'parvularia_jec_especial_65_35_ld' => 'NT1/NT2 con JEC',
            'parvularia_sin_jec_especial_65_35_ld' => 'NT1/NT2 sin JEC',
            default => $value,
        };
    }

    private function authorizeGroup(Establecimiento $establecimiento, DotacionCursoCombinado $group): void
    {
        abort_unless((int) $group->establecimiento_id === (int) $establecimiento->id, 404);
    }

    private function authorizeScope(Request $request, Establecimiento $establecimiento): void
    {
        $role = $request->user() && method_exists($request->user(), 'activeRoleName')
            ? $request->user()->activeRoleName()
            : null;
        abort_unless(in_array($role, $this->allowedRoles, true), 403);
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404);
        if ($role === 'funcionario_directivo_estab') {
            abort_unless((int) $establecimiento->id === (int) ($request->user()->establecimiento_id ?? 0), 403);
        }
        abort_unless(DotacionCursoCombinadoCalculator::tablesReady(), 500, 'Debe ejecutar las migraciones de cursos combinados.');
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round(max(0.0, (float) $value), 2);
    }
}
