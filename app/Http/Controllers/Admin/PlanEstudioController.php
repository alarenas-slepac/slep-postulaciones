<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\PlanEstudio;
use App\Models\PlanEstudioBloque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class PlanEstudioController extends Controller
{
    public function index(Request $request)
    {
        $anio = trim((string) $request->query('anio', ''));
        $cursoId = trim((string) $request->query('curso_id', ''));
        $regimen = trim((string) $request->query('regimen_jec', ''));
        $nivel = trim((string) $request->query('nivel_educativo', ''));
        $activo = trim((string) $request->query('activo', ''));
        $q = trim((string) $request->query('q', ''));

        $planes = PlanEstudio::query()
            ->with('curso')
            ->when($anio !== '', fn ($query) => $query->where('anio', (int) $anio))
            ->when($cursoId !== '', fn ($query) => $query->where('curso_id', (int) $cursoId))
            ->when($regimen !== '', fn ($query) => $query->where('regimen_jec', $regimen))
            ->when($nivel !== '', fn ($query) => $query->where('nivel_educativo', $nivel))
            ->when($activo !== '', fn ($query) => $query->where('activo', $activo === '1'))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nombre_plan', 'like', "%{$q}%")
                        ->orWhere('decreto_referencia', 'like', "%{$q}%")
                        ->orWhereHas('curso', fn ($curso) => $curso->where('nombre', 'like', "%{$q}%"));
                });
            })
            ->join('cursos', 'cursos.id', '=', 'planes_estudio.curso_id')
            ->orderByDesc('planes_estudio.anio')
            ->orderBy('cursos.orden')
            ->orderBy('planes_estudio.regimen_jec')
            ->select('planes_estudio.*')
            ->paginate(20)
            ->withQueryString();

        $cursos = Curso::query()->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']);
        $niveles = PlanEstudio::query()->select('nivel_educativo')->whereNotNull('nivel_educativo')->distinct()->orderBy('nivel_educativo')->pluck('nivel_educativo')->filter()->values();

        return view('admin.planes-estudio.index', compact('planes', 'cursos', 'niveles', 'anio', 'cursoId', 'regimen', 'nivel', 'activo', 'q'));
    }

    public function create()
    {
        return view('admin.planes-estudio.create', [
            'plan' => new PlanEstudio([
                'anio' => now()->year,
                'regimen_jec' => 'Con JEC',
                'activo' => true,
            ]),
            'cursos' => Curso::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedData($request);

        DB::transaction(function () use ($payload) {
            $asignaturas = $payload['asignaturas'];
            $bloques = $payload['bloques'];
            unset($payload['asignaturas'], $payload['bloques']);

            $plan = PlanEstudio::create($payload);
            $this->syncBloques($plan, $bloques);
            $this->syncAsignaturas($plan, $asignaturas);
        });

        return redirect()->route('admin.planes-estudio.index')->with('status', 'Plan de estudio creado correctamente.');
    }

    public function show(PlanEstudio $planes_estudio)
    {
        $planes_estudio->load(['curso', 'bloques', 'asignaturas']);

        return view('admin.planes-estudio.show', ['plan' => $planes_estudio]);
    }

    public function edit(PlanEstudio $planes_estudio)
    {
        $planes_estudio->load(['bloques', 'asignaturas']);

        return view('admin.planes-estudio.edit', [
            'plan' => $planes_estudio,
            'cursos' => Curso::query()->orderBy('orden')->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, PlanEstudio $planes_estudio)
    {
        $payload = $this->validatedData($request, $planes_estudio);

        DB::transaction(function () use ($payload, $planes_estudio) {
            $asignaturas = $payload['asignaturas'];
            $bloques = $payload['bloques'];
            unset($payload['asignaturas'], $payload['bloques']);

            $planes_estudio->update($payload);
            $this->syncBloques($planes_estudio, $bloques);
            $this->syncAsignaturas($planes_estudio, $asignaturas);
        });

        return redirect()->route('admin.planes-estudio.index')->with('status', 'Plan de estudio actualizado correctamente.');
    }

    public function destroy(PlanEstudio $planes_estudio)
    {
        $planes_estudio->delete();

        return redirect()->route('admin.planes-estudio.index')->with('status', 'Plan de estudio eliminado correctamente.');
    }

    public function importForm()
    {
        return view('admin.planes-estudio.import');
    }

    public function downloadTemplate()
    {
        $path = resource_path('templates/planes_estudio/plantilla_carga_masiva_planes_estudio.xlsx');

        abort_unless(is_file($path), 404, 'Plantilla no disponible.');

        return response()->download($path, 'plantilla_carga_masiva_planes_estudio.xlsx');
    }

    public function importStore(Request $request)
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'archivo.mimes' => 'La carga masiva debe ser un archivo Excel .xlsx.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        $path = $data['archivo']->getRealPath();
        $planRows = $this->readXlsxRows($path, 1);
        if (count($planRows) < 2) {
            return back()->withErrors(['archivo' => 'La hoja Resumen planes no contiene filas para importar.']);
        }

        $planHeader = $this->mapHeader($planRows[0] ?? [], [
            'anio' => ['ANIO', 'AÑO', 'ANO'],
            'curso' => ['CURSO', 'NIVEL', 'CURSO/NIVEL'],
            'regimen_jec' => ['REGIMEN_JEC', 'RÉGIMEN_JEC', 'REGIMEN', 'JEC'],
            'nombre_plan' => ['NOMBRE_PLAN', 'PLAN', 'NOMBRE'],
            'nivel_educativo' => ['NIVEL_EDUCATIVO', 'NIVEL EDUCATIVO'],
            'modalidad' => ['MODALIDAD'],
            'horas_semanales_subtotal' => ['HORAS_SEMANALES_SUBTOTAL', 'SUBTOTAL SEMANAL', 'SUBTOTAL'],
            'horas_semanales_libre_disposicion' => ['HORAS_SEMANALES_LIBRE_DISPOSICION', 'LIBRE DISPOSICION', 'HORAS LIBRE DISPOSICION'],
            'horas_semanales_total' => ['HORAS_SEMANALES_TOTAL', 'TOTAL SEMANAL', 'TOTAL HORAS SEMANALES', 'TOTAL'],
            'horas_anuales_total' => ['HORAS_ANUALES_TOTAL', 'TOTAL ANUAL', 'TOTAL HORAS ANUALES'],
            'decreto_referencia' => ['DECRETO_REFERENCIA', 'DECRETO', 'REFERENCIA'],
            'observacion' => ['OBSERVACION', 'OBSERVACIÓN'],
            'activo' => ['ACTIVO'],
        ]);

        foreach (['anio', 'curso', 'regimen_jec', 'horas_semanales_total'] as $required) {
            if (! array_key_exists($required, $planHeader)) {
                return back()->withErrors(['archivo' => 'La hoja Resumen planes debe incluir ANIO, CURSO, REGIMEN_JEC y HORAS_SEMANALES_TOTAL.']);
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $touchedPlanIds = [];

        DB::transaction(function () use ($planRows, $planHeader, &$created, &$updated, &$skipped, &$errors, &$touchedPlanIds) {
            foreach (array_slice($planRows, 1) as $offset => $row) {
                $line = $offset + 2;
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $anio = $this->normalizeInteger($row[$planHeader['anio']] ?? null);
                $cursoNombre = trim((string) ($row[$planHeader['curso']] ?? ''));
                $regimen = $this->normalizeRegimen($row[$planHeader['regimen_jec']] ?? '');
                $total = $this->normalizeDecimal($row[$planHeader['horas_semanales_total']] ?? null);

                if (! $anio || $anio < 2020 || $anio > 2100) {
                    $skipped++; $errors[] = "Fila {$line}: año inválido."; continue;
                }
                if ($cursoNombre === '') {
                    $skipped++; $errors[] = "Fila {$line}: curso vacío."; continue;
                }
                if (! in_array($regimen, ['Con JEC', 'Sin JEC'], true)) {
                    $skipped++; $errors[] = "Fila {$line}: régimen JEC inválido."; continue;
                }
                if ($total === null || $total <= 0) {
                    $skipped++; $errors[] = "Fila {$line}: total semanal inválido."; continue;
                }

                $curso = $this->findCursoByName($cursoNombre);
                if (! $curso) {
                    $skipped++; $errors[] = "Fila {$line}: no existe el curso '{$cursoNombre}' en el mantenedor Cursos."; continue;
                }

                $payload = [
                    'curso_id' => $curso->id,
                    'anio' => $anio,
                    'regimen_jec' => $regimen,
                    'nombre_plan' => $this->cell($row, $planHeader, 'nombre_plan') ?: ('Plan de Estudio '.$curso->nombre.' - '.$regimen),
                    'nivel_educativo' => $this->cell($row, $planHeader, 'nivel_educativo') ?: $curso->nivel_educativo,
                    'modalidad' => $this->cell($row, $planHeader, 'modalidad') ?: $curso->modalidad,
                    'horas_semanales_subtotal' => $this->normalizeDecimal($this->cell($row, $planHeader, 'horas_semanales_subtotal')),
                    'horas_semanales_libre_disposicion' => $this->normalizeDecimal($this->cell($row, $planHeader, 'horas_semanales_libre_disposicion')),
                    'horas_semanales_total' => $total,
                    'horas_anuales_total' => $this->normalizeDecimal($this->cell($row, $planHeader, 'horas_anuales_total')),
                    'decreto_referencia' => $this->cell($row, $planHeader, 'decreto_referencia') ?: null,
                    'observacion' => $this->cell($row, $planHeader, 'observacion') ?: null,
                    'activo' => $this->normalizeBoolean($this->cell($row, $planHeader, 'activo'), true),
                ];

                $plan = PlanEstudio::query()
                    ->where('curso_id', $curso->id)
                    ->where('anio', $anio)
                    ->where('regimen_jec', $regimen)
                    ->first();

                if ($plan) {
                    $plan->update($payload);
                    $updated++;
                } else {
                    $plan = PlanEstudio::create($payload);
                    $created++;
                }
                $touchedPlanIds[$plan->id] = $plan->id;
            }

            $this->importBloques($touchedPlanIds, $errors, $skipped);
            $this->importDetalleAsignaturas($touchedPlanIds, $errors, $skipped);
        });

        return redirect()
            ->route('admin.planes-estudio.index')
            ->with('status', "Carga masiva procesada. Creados: {$created}. Actualizados: {$updated}. Omitidos: {$skipped}.")
            ->with('import_errors', array_slice($errors, 0, 100));
    }


    private function importBloques(array $touchedPlanIds, array &$errors, int &$skipped): void
    {
        $upload = request()->file('archivo');
        if (! $upload) {
            return;
        }

        $rows = $this->readXlsxRows($upload->getRealPath(), 2);
        if (count($rows) < 2) {
            return;
        }

        $header = $this->mapHeader($rows[0] ?? [], [
            'anio' => ['ANIO', 'AÑO', 'ANO'],
            'curso' => ['CURSO', 'NIVEL'],
            'regimen_jec' => ['REGIMEN_JEC', 'REGIMEN', 'JEC'],
            'nombre' => ['NOMBRE_BLOQUE', 'BLOQUE', 'NOMBRE'],
            'tipo_bloque' => ['TIPO_BLOQUE', 'TIPO'],
            'horas_semanales' => ['HORAS_SEMANALES', 'HORAS SEMANALES'],
            'horas_anuales' => ['HORAS_ANUALES', 'HORAS ANUALES'],
            'permite_asignaturas_establecimiento' => ['PERMITE_ASIGNATURAS_ESTABLECIMIENTO', 'PERMITE_SELECCION_ESTABLECIMIENTO'],
            'permite_asignaturas_personalizadas' => ['PERMITE_ASIGNATURAS_PERSONALIZADAS', 'PERMITE_PERSONALIZADAS'],
            'orden' => ['ORDEN'],
            'activo' => ['ACTIVO'],
        ]);

        foreach (['anio', 'curso', 'regimen_jec', 'nombre'] as $required) {
            if (! array_key_exists($required, $header)) {
                return;
            }
        }

        $grouped = [];
        foreach (array_slice($rows, 1) as $offset => $row) {
            $line = $offset + 2;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $curso = $this->findCursoByName(trim((string) ($row[$header['curso']] ?? '')));
            $anio = $this->normalizeInteger($row[$header['anio']] ?? null);
            $regimen = $this->normalizeRegimen($row[$header['regimen_jec']] ?? '');
            $nombre = trim((string) ($row[$header['nombre']] ?? ''));

            if (! $curso || ! $anio || ! in_array($regimen, ['Con JEC', 'Sin JEC'], true) || $nombre === '') {
                $skipped++;
                $errors[] = "Bloques fila {$line}: no fue posible asociar el bloque a un plan.";
                continue;
            }

            $plan = PlanEstudio::query()
                ->where('curso_id', $curso->id)
                ->where('anio', $anio)
                ->where('regimen_jec', $regimen)
                ->first();

            if (! $plan) {
                $skipped++;
                $errors[] = "Bloques fila {$line}: no existe plan para {$curso->nombre} {$anio} {$regimen}.";
                continue;
            }

            $grouped[$plan->id][] = [
                'nombre' => $nombre,
                'tipo_bloque' => $this->cell($row, $header, 'tipo_bloque') ?: 'plan_comun_formacion_general',
                'horas_semanales' => $this->normalizeDecimal($this->cell($row, $header, 'horas_semanales')),
                'horas_anuales' => $this->normalizeDecimal($this->cell($row, $header, 'horas_anuales')),
                'permite_asignaturas_establecimiento' => $this->normalizeBoolean($this->cell($row, $header, 'permite_asignaturas_establecimiento'), false),
                'permite_asignaturas_personalizadas' => $this->normalizeBoolean($this->cell($row, $header, 'permite_asignaturas_personalizadas'), false),
                'orden' => $this->normalizeInteger($this->cell($row, $header, 'orden')) ?: (count($grouped[$plan->id] ?? []) + 1),
                'activo' => $this->normalizeBoolean($this->cell($row, $header, 'activo'), true),
            ];
        }

        foreach ($grouped as $planId => $rows) {
            $plan = PlanEstudio::find($planId);
            if ($plan) {
                $this->syncBloques($plan, $rows);
            }
        }
    }

    private function importDetalleAsignaturas(array $touchedPlanIds, array &$errors, int &$skipped): void
    {
        $upload = request()->file('archivo');
        if (! $upload) {
            return;
        }

        $detailRows = $this->readXlsxRows($upload->getRealPath(), 3);
        if (count($detailRows) < 2) {
            return;
        }

        $header = $this->mapHeader($detailRows[0] ?? [], [
            'anio' => ['ANIO', 'AÑO', 'ANO'],
            'curso' => ['CURSO', 'NIVEL'],
            'regimen_jec' => ['REGIMEN_JEC', 'REGIMEN', 'JEC'],
            'asignatura' => ['ASIGNATURA', 'COMPONENTE'],
            'horas_semanales' => ['HORAS_SEMANALES', 'HORAS SEMANALES'],
            'horas_anuales' => ['HORAS_ANUALES', 'HORAS ANUALES'],
            'tipo_bloque' => ['TIPO_BLOQUE', 'TIPO'],
            'orden' => ['ORDEN'],
        ]);

        foreach (['anio', 'curso', 'regimen_jec', 'asignatura'] as $required) {
            if (! array_key_exists($required, $header)) {
                return;
            }
        }

        $grouped = [];
        foreach (array_slice($detailRows, 1) as $offset => $row) {
            $line = $offset + 2;
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $curso = $this->findCursoByName(trim((string) ($row[$header['curso']] ?? '')));
            $anio = $this->normalizeInteger($row[$header['anio']] ?? null);
            $regimen = $this->normalizeRegimen($row[$header['regimen_jec']] ?? '');
            $asignatura = trim((string) ($row[$header['asignatura']] ?? ''));

            if (! $curso || ! $anio || ! in_array($regimen, ['Con JEC', 'Sin JEC'], true) || $asignatura === '') {
                $skipped++;
                $errors[] = "Detalle fila {$line}: no fue posible asociar la asignatura a un plan.";
                continue;
            }

            $plan = PlanEstudio::query()
                ->where('curso_id', $curso->id)
                ->where('anio', $anio)
                ->where('regimen_jec', $regimen)
                ->first();

            if (! $plan) {
                $skipped++;
                $errors[] = "Detalle fila {$line}: no existe plan para {$curso->nombre} {$anio} {$regimen}.";
                continue;
            }

            $grouped[$plan->id][] = [
                'asignatura' => $asignatura,
                'horas_semanales' => $this->normalizeDecimal($this->cell($row, $header, 'horas_semanales')),
                'horas_anuales' => $this->normalizeDecimal($this->cell($row, $header, 'horas_anuales')),
                'tipo_bloque' => $this->cell($row, $header, 'tipo_bloque') ?: 'asignatura',
                'orden' => $this->normalizeInteger($this->cell($row, $header, 'orden')) ?: (count($grouped[$plan->id] ?? []) + 1),
            ];
        }

        foreach ($grouped as $planId => $rows) {
            $plan = PlanEstudio::find($planId);
            if ($plan) {
                $this->syncAsignaturas($plan, $rows);
            }
        }
    }

    private function validatedData(Request $request, ?PlanEstudio $plan = null): array
    {
        $data = $request->validate([
            'curso_id' => ['required', 'integer', Rule::exists('cursos', 'id')],
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'nombre_plan' => ['required', 'string', 'max:180'],
            'nivel_educativo' => ['nullable', 'string', 'max:80'],
            'modalidad' => ['nullable', 'string', 'max:80'],
            'regimen_jec' => ['required', Rule::in(['Con JEC', 'Sin JEC'])],
            'horas_semanales_subtotal' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'horas_semanales_libre_disposicion' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'horas_semanales_total' => ['required', 'numeric', 'min:1', 'max:999'],
            'horas_anuales_total' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'decreto_referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string', 'max:4000'],
            'activo' => ['nullable', 'boolean'],
            'bloques' => ['nullable', 'array'],
            'bloques.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'bloques.*.nombre' => ['nullable', 'string', 'max:160'],
            'bloques.*.tipo_bloque' => ['nullable', 'string', 'max:80'],
            'bloques.*.horas_semanales' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'bloques.*.horas_anuales' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'bloques.*.permite_asignaturas_establecimiento' => ['nullable', 'boolean'],
            'bloques.*.permite_asignaturas_personalizadas' => ['nullable', 'boolean'],
            'bloques.*.orden' => ['nullable', 'integer', 'min:1', 'max:999'],
            'bloques.*.activo' => ['nullable', 'boolean'],
            'asignaturas' => ['nullable', 'array'],
            'asignaturas.*.asignatura' => ['nullable', 'string', 'max:180'],
            'asignaturas.*.horas_semanales' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'asignaturas.*.horas_anuales' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'asignaturas.*.tipo_bloque' => ['nullable', 'string', 'max:60'],
            'asignaturas.*.orden' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $exists = PlanEstudio::query()
            ->where('curso_id', (int) $data['curso_id'])
            ->where('anio', (int) $data['anio'])
            ->where('regimen_jec', $data['regimen_jec'])
            ->when($plan, fn ($query) => $query->where('id', '<>', $plan->id))
            ->exists();

        if ($exists) {
            abort(422, 'Ya existe un plan de estudio para el curso, año y régimen JEC seleccionados.');
        }

        $curso = Curso::find($data['curso_id']);
        $data['nivel_educativo'] = trim((string) ($data['nivel_educativo'] ?? '')) ?: ($curso?->nivel_educativo);
        $data['modalidad'] = trim((string) ($data['modalidad'] ?? '')) ?: ($curso?->modalidad);
        $data['activo'] = (bool) ($data['activo'] ?? false);
        $data['bloques'] = $this->normalizeBloques($data['bloques'] ?? []);
        $data['asignaturas'] = $this->normalizeAsignaturas($data['asignaturas'] ?? []);

        return $data;
    }

    private function syncBloques(PlanEstudio $plan, array $bloques): void
    {
        $existing = $plan->bloques()->get()->keyBy('id');
        $retainedIds = [];

        foreach ($this->normalizeBloques($bloques) as $index => $row) {
            $payload = [
                'nombre' => $row['nombre'],
                'tipo_bloque' => $row['tipo_bloque'],
                'horas_semanales' => $row['horas_semanales'],
                'horas_anuales' => $row['horas_anuales'],
                'permite_asignaturas_establecimiento' => $row['permite_asignaturas_establecimiento'],
                'permite_asignaturas_personalizadas' => $row['permite_asignaturas_personalizadas'],
                'orden' => $row['orden'] ?: ($index + 1),
                'activo' => $row['activo'],
            ];

            $block = null;
            $blockId = (int) ($row['id'] ?? 0);

            if ($blockId > 0) {
                if (! $existing->has($blockId)) {
                    throw ValidationException::withMessages([
                        'bloques' => 'Uno de los bloques enviados no pertenece al plan de estudio que se está editando.',
                    ]);
                }

                $block = $existing->get($blockId);
            } elseif ($blockId === 0) {
                // Compatibilidad con formularios antiguos que no enviaban el ID:
                // intenta conservar el bloque por su firma antes de crear uno nuevo.
                $block = $existing
                    ->reject(fn (PlanEstudioBloque $candidate) => in_array($candidate->id, $retainedIds, true))
                    ->first(function (PlanEstudioBloque $candidate) use ($row, $index) {
                        return trim((string) $candidate->nombre) === $row['nombre']
                            && trim((string) $candidate->tipo_bloque) === $row['tipo_bloque']
                            && (int) $candidate->orden === (int) ($row['orden'] ?: ($index + 1));
                    });
            }

            if ($block) {
                $block->update($payload);
                $retainedIds[] = (int) $block->id;
                continue;
            }

            $created = $plan->bloques()->create($payload);
            $retainedIds[] = (int) $created->id;
        }

        $obsolete = $existing->reject(
            fn (PlanEstudioBloque $block) => in_array((int) $block->id, $retainedIds, true)
        );

        foreach ($obsolete as $block) {
            $isUsedByEstablishment = DB::table('establecimiento_planes_estudio_asignaturas')
                ->where('plan_estudio_bloque_id', $block->id)
                ->exists();

            if ($isUsedByEstablishment) {
                throw ValidationException::withMessages([
                    'bloques' => "No se puede quitar el bloque '{$block->nombre}' porque ya está utilizado en la configuración de planes de uno o más establecimientos. Puede mantenerlo y desactivarlo.",
                ]);
            }

            $block->delete();
        }
    }

    private function syncAsignaturas(PlanEstudio $plan, array $asignaturas): void
    {
        $plan->asignaturas()->delete();

        foreach ($this->normalizeAsignaturas($asignaturas) as $index => $row) {
            $plan->asignaturas()->create([
                'asignatura' => $row['asignatura'],
                'horas_semanales' => $row['horas_semanales'],
                'horas_anuales' => $row['horas_anuales'],
                'tipo_bloque' => $row['tipo_bloque'] ?: 'asignatura',
                'orden' => $row['orden'] ?: ($index + 1),
            ]);
        }
    }

    private function normalizeBloques(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $nombre = trim((string) ($row['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $normalized[] = [
                'id' => $this->normalizeInteger($row['id'] ?? null),
                'nombre' => $nombre,
                'tipo_bloque' => trim((string) ($row['tipo_bloque'] ?? '')) ?: 'plan_comun_formacion_general',
                'horas_semanales' => $this->normalizeDecimal($row['horas_semanales'] ?? null),
                'horas_anuales' => $this->normalizeDecimal($row['horas_anuales'] ?? null),
                'permite_asignaturas_establecimiento' => ! empty($row['permite_asignaturas_establecimiento']),
                'permite_asignaturas_personalizadas' => ! empty($row['permite_asignaturas_personalizadas']),
                'orden' => $this->normalizeInteger($row['orden'] ?? null) ?: ($index + 1),
                'activo' => array_key_exists('activo', $row) ? (bool) $row['activo'] : true,
            ];
        }
        return $normalized;
    }

    private function normalizeAsignaturas(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $index => $row) {
            $asignatura = trim((string) ($row['asignatura'] ?? ''));
            if ($asignatura === '') {
                continue;
            }
            $normalized[] = [
                'asignatura' => $asignatura,
                'horas_semanales' => $this->normalizeDecimal($row['horas_semanales'] ?? null),
                'horas_anuales' => $this->normalizeDecimal($row['horas_anuales'] ?? null),
                'tipo_bloque' => trim((string) ($row['tipo_bloque'] ?? 'asignatura')) ?: 'asignatura',
                'orden' => $this->normalizeInteger($row['orden'] ?? null) ?: ($index + 1),
            ];
        }
        return $normalized;
    }

    private function findCursoByName(string $nombre): ?Curso
    {
        $normalized = $this->normalizeText($nombre);
        return Curso::query()->get()->first(fn ($curso) => $this->normalizeText($curso->nombre) === $normalized || $this->normalizeText($curso->codigo) === $normalized);
    }

    private function readXlsxRows(string $path, int $sheetNumber = 1): array
    {
        if (! class_exists(ZipArchive::class)) {
            abort(500, 'La extensión ZipArchive de PHP es requerida para leer archivos .xlsx.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            abort(422, 'No fue posible abrir el archivo Excel.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet'.$sheetNumber.'.xml');
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sheetXml);
        if (! $xml) {
            abort(422, 'No fue posible leer la hoja '.$sheetNumber.' del archivo Excel.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $xmlRow) {
            $row = [];
            foreach ($xmlRow->c as $cell) {
                $ref = (string) $cell['r'];
                $colIndex = $this->excelColumnIndex($ref);
                $type = (string) $cell['t'];
                $value = '';

                if ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } elseif ($type === 's') {
                    $idx = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$idx] ?? '';
                } else {
                    $value = (string) ($cell->v ?? '');
                }

                $row[$colIndex] = trim($value);
            }
            if ($row) {
                ksort($row);
                $max = max(array_keys($row));
                $rows[] = array_map(fn ($idx) => $row[$idx] ?? '', range(0, $max));
            }
        }
        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xmlString = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlString === false) return [];
        $xml = simplexml_load_string($xmlString);
        if (! $xml) return [];
        $strings = [];
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
                continue;
            }
            $parts = [];
            foreach ($si->r as $run) {
                $parts[] = (string) ($run->t ?? '');
            }
            $strings[] = implode('', $parts);
        }
        return $strings;
    }

    private function mapHeader(array $row, array $aliases): array
    {
        $map = [];
        foreach ($row as $index => $label) {
            $normalized = $this->normalizeHeader((string) $label);
            foreach ($aliases as $field => $options) {
                foreach ($options as $option) {
                    if ($normalized === $this->normalizeHeader($option)) {
                        $map[$field] = $index;
                    }
                }
            }
        }
        return $map;
    }

    private function cell(array $row, array $header, string $field): ?string
    {
        if (! array_key_exists($field, $header)) return null;
        $value = trim((string) ($row[$header[$field]] ?? ''));
        return $value !== '' ? $value : null;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') return false;
        }
        return true;
    }

    private function excelColumnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }
        return max(0, $index - 1);
    }

    private function normalizeHeader(string $value): string
    {
        return $this->normalizeText(str_replace(['_', '-'], ' ', $value));
    }

    private function normalizeText(string $value): string
    {
        $value = trim(mb_strtoupper($value, 'UTF-8'));
        $value = str_replace(['Á','É','Í','Ó','Ú','Ü','Ñ'], ['A','E','I','O','U','U','N'], $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function normalizeRegimen($value): string
    {
        $text = $this->normalizeText((string) $value);
        if (str_contains($text, 'SIN')) return 'Sin JEC';
        if (str_contains($text, 'CON')) return 'Con JEC';
        return $text;
    }

    private function normalizeInteger($value): ?int
    {
        $value = preg_replace('/[^0-9-]/', '', (string) $value);
        return $value === '' ? null : (int) $value;
    }

    private function normalizeDecimal($value): ?float
    {
        if ($value === null || trim((string) $value) === '') return null;

        $value = trim(str_replace('%', '', (string) $value));
        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasDot && substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function normalizeBoolean($value, bool $default = true): bool
    {
        if ($value === null || trim((string) $value) === '') return $default;
        $text = $this->normalizeText((string) $value);
        return in_array($text, ['1', 'SI', 'SÍ', 'TRUE', 'ACTIVO', 'ACTIVA'], true);
    }
}
