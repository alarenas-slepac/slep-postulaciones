<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\EstablecimientoCursoPie;
use App\Models\AlumnoPrioritarioPorcentaje;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\PieHorasCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class EstablecimientoCursoPieController extends Controller
{
    private array $allowedRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'];
    private array $editableRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp'];

    public function index(Request $request)
    {
        $user = $request->user();
        $activeRole = $this->authorizePieAccess($request);
        $anio = trim((string) $request->query('anio', (string) now()->year));
        $establecimientoId = trim((string) $request->query('establecimiento_id', ''));
        $cursoId = trim((string) $request->query('curso_id', ''));
        $estado = trim((string) $request->query('estado', ''));
        $registro = trim((string) $request->query('registro', ''));
        $q = trim((string) $request->query('q', ''));

        if ($this->isEstablecimientoRole($activeRole)) {
            $establecimientoId = (string) ((int) ($user->establecimiento_id ?? 0));
        }

        $items = DB::table('establecimiento_cursos as ec')
            ->join('establecimientos as e', 'e.id', '=', 'ec.establecimiento_id')
            ->join('cursos as c', 'c.id', '=', 'ec.curso_id')
            ->leftJoin('planes_estudio as pe', 'pe.id', '=', 'ec.plan_estudio_id')
            ->leftJoin('alumnos_prioritarios_porcentajes as ap', function ($join) use ($anio) {
                $join->on('ap.establecimiento_id', '=', 'ec.establecimiento_id');
                if ($anio !== '') {
                    $join->where('ap.anio', '=', (int) $anio);
                }
            })
            ->leftJoin('establecimiento_curso_pie as pie', function ($join) use ($anio) {
                $join->on('pie.establecimiento_curso_id', '=', 'ec.id');
                if ($anio !== '') {
                    $join->where('pie.anio', '=', (int) $anio);
                }
            })
            ->where('ec.activo', true)
            ->whereNotNull('ec.establecimiento_id')
            ->whereNotNull('ec.curso_id')
            ->whereNotNull('ec.anio')
            ->when($anio !== '', fn ($query) => $query->where('ec.anio', (int) $anio))
            ->when($establecimientoId !== '', fn ($query) => $query->where('ec.establecimiento_id', (int) $establecimientoId))
            ->when($cursoId !== '', fn ($query) => $query->where('ec.curso_id', (int) $cursoId))
            ->when($estado !== '', fn ($query) => $query->where('pie.estado', $estado))
            ->when($registro === 'con', fn ($query) => $query->whereNotNull('pie.id'))
            ->when($registro === 'sin', fn ($query) => $query->whereNull('pie.id'))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('ec.nombre_seccion', 'like', "%{$q}%")
                        ->orWhere('ec.rbd', 'like', "%{$q}%")
                        ->orWhere('e.rbd', 'like', "%{$q}%")
                        ->orWhere('e.nombre_establecimiento', 'like', "%{$q}%")
                        ->orWhere('e.comuna', 'like', "%{$q}%")
                        ->orWhere('c.nombre', 'like', "%{$q}%");
                });
            })
            ->orderBy('e.comuna')
            ->orderBy('e.nombre_establecimiento')
            ->orderBy('c.orden')
            ->orderBy('ec.letra')
            ->select([
                'ec.id as establecimiento_curso_id',
                'ec.establecimiento_id',
                'ec.rbd',
                'ec.anio',
                'ec.letra',
                'ec.nombre_seccion',
                'ec.matricula',
                'ec.regimen_jec',
                'e.rbd as establecimiento_rbd',
                'e.nombre_establecimiento as establecimiento_nombre',
                'e.comuna as establecimiento_comuna',
                'c.id as curso_id',
                'c.nombre as curso_nombre',
                'pe.nombre_plan as plan_nombre',
                'ap.porcentaje as prioritarios_porcentaje',
                'pie.id as pie_id',
                'pie.necesidades_transitorias',
                'pie.necesidades_permanentes',
                'pie.total_pie',
                'pie.estado',
                'pie.observacion',
                'pie.regimen_calculo',
                'pie.neet_calculo',
                'pie.neep_calculo',
                'pie.total_crono_minutos',
                'pie.prof_educ_dif_minutos',
                'pie.pae_minutos',
                'pie.calculo_observacion',
                'pie.updated_at as pie_updated_at',
            ])
            ->paginate(25)
            ->withQueryString();

        $items->getCollection()->transform(function ($item) {
            $item->horas_no_lectivas = DocenteHorasNoLectivasCalculator::referenceFor($item, $item->prioritarios_porcentaje !== null ? (float) $item->prioritarios_porcentaje : null);
            return $item;
        });

        $summary = $this->summaryFor($anio, $establecimientoId, $activeRole, $user);

        return view('admin.establecimiento-curso-pie.index', [
            'items' => $items,
            'summary' => $summary,
            'formatMinutes' => fn (?int $minutes): string => PieHorasCalculator::formatMinutes($minutes),
            'formatDocenteMinutes' => fn (?int $minutes): string => DocenteHorasNoLectivasCalculator::formatMinutes($minutes),
            'formatEducadoresDiferenciales' => fn (?int $minutes, int $baseMinutes = 1710): string => PieHorasCalculator::formatEducadoresDiferenciales($minutes, $baseMinutes),
            'educadoresDiferencialesRedondeados' => fn (?int $minutes, int $baseMinutes = 1710): int => PieHorasCalculator::educadoresDiferencialesRedondeados($minutes, $baseMinutes),
            'establecimientos' => $this->establecimientosAgrupados($activeRole, $user, $anio),
            'cursos' => Curso::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => EstablecimientoCursoPie::ESTADOS,
            'activeRole' => $activeRole,
            'canEditPie' => in_array($activeRole, $this->editableRoles, true),
            'anio' => $anio,
            'establecimientoId' => $establecimientoId,
            'cursoId' => $cursoId,
            'estado' => $estado,
            'registro' => $registro,
            'q' => $q,
        ]);
    }

    public function create(Request $request)
    {
        $activeRole = $this->authorizePieAccess($request, true);
        $curso = $this->resolveEstablecimientoCurso($request, (int) $request->query('establecimiento_curso_id'));

        return view('admin.establecimiento-curso-pie.create', [
            'pie' => new EstablecimientoCursoPie([
                'establecimiento_curso_id' => $curso?->id,
                'establecimiento_id' => $curso?->establecimiento_id,
                'curso_id' => $curso?->curso_id,
                'plan_estudio_id' => $curso?->plan_estudio_id,
                'anio' => $curso?->anio ?: now()->year,
                'rbd' => $curso?->rbd,
                'necesidades_transitorias' => 0,
                'necesidades_permanentes' => 0,
                'estado' => 'borrador',
            ]),
            'cursoSeleccionado' => $curso,
            'cursosDisponibles' => $this->cursosDisponibles($request),
            'estados' => EstablecimientoCursoPie::ESTADOS,
            'activeRole' => $activeRole,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePieAccess($request, true);
        $data = $this->validatedData($request);
        $curso = $this->resolveEstablecimientoCurso($request, (int) $data['establecimiento_curso_id'], true);
        $data = $this->completePayload($data, $curso, $request);

        EstablecimientoCursoPie::updateOrCreate(
            [
                'establecimiento_curso_id' => $curso->id,
                'anio' => (int) $data['anio'],
            ],
            $data
        );

        return redirect()->route('admin.establecimiento-curso-pie.index', ['anio' => $data['anio']])
            ->with('status', 'Registro PIE guardado correctamente.');
    }

    public function show(Request $request, EstablecimientoCursoPie $establecimiento_curso_pie)
    {
        $activeRole = $this->authorizePieAccess($request);
        $this->authorizeRecordScope($request, $establecimiento_curso_pie);
        $establecimiento_curso_pie->load(['establecimiento', 'establecimientoCurso.curso', 'establecimientoCurso.planEstudio', 'curso', 'planEstudio', 'creador', 'actualizador']);
        $porcentajePrioritarios = AlumnoPrioritarioPorcentaje::query()
            ->where('establecimiento_id', $establecimiento_curso_pie->establecimiento_id)
            ->where('anio', $establecimiento_curso_pie->anio)
            ->value('porcentaje');
        $horasNoLectivas = DocenteHorasNoLectivasCalculator::referenceFor(
            $establecimiento_curso_pie->establecimientoCurso,
            $porcentajePrioritarios !== null ? (float) $porcentajePrioritarios : null
        );
        $baseEducadorDif = (int) (($horasNoLectivas['horas_aula_cronologicas_minutos'] ?? 1710) ?: 1710);
        $contratoEducadorDif = DocenteHorasNoLectivasCalculator::contratoAsociadoDesdeMinutosAula(
            (int) ($establecimiento_curso_pie->prof_educ_dif_minutos ?? 0),
            $baseEducadorDif
        );

        return view('admin.establecimiento-curso-pie.show', [
            'pie' => $establecimiento_curso_pie,
            'activeRole' => $activeRole,
            'canEditPie' => in_array($activeRole, $this->editableRoles, true),
            'horasNoLectivas' => $horasNoLectivas,
            'contratoEducadorDif' => $contratoEducadorDif,
            'tablaHorasNoLectivas' => DocenteHorasNoLectivasCalculator::proportionTable($horasNoLectivas['proporcion']),
            'formatDocenteMinutes' => fn (?int $minutes): string => DocenteHorasNoLectivasCalculator::formatMinutes($minutes),
            'formatEducadoresDiferenciales' => fn (?int $minutes, int $baseMinutes = 1710): string => PieHorasCalculator::formatEducadoresDiferenciales($minutes, $baseMinutes),
            'educadoresDiferencialesRedondeados' => fn (?int $minutes, int $baseMinutes = 1710): int => PieHorasCalculator::educadoresDiferencialesRedondeados($minutes, $baseMinutes),
        ]);
    }

    public function edit(Request $request, EstablecimientoCursoPie $establecimiento_curso_pie)
    {
        $activeRole = $this->authorizePieAccess($request, true);
        $this->authorizeRecordScope($request, $establecimiento_curso_pie);
        $establecimiento_curso_pie->load(['establecimientoCurso.establecimiento', 'establecimientoCurso.curso', 'establecimientoCurso.planEstudio']);

        return view('admin.establecimiento-curso-pie.edit', [
            'pie' => $establecimiento_curso_pie,
            'cursoSeleccionado' => $establecimiento_curso_pie->establecimientoCurso,
            'cursosDisponibles' => $this->cursosDisponibles($request),
            'estados' => EstablecimientoCursoPie::ESTADOS,
            'activeRole' => $activeRole,
        ]);
    }

    public function update(Request $request, EstablecimientoCursoPie $establecimiento_curso_pie)
    {
        $this->authorizePieAccess($request, true);
        $this->authorizeRecordScope($request, $establecimiento_curso_pie);
        $data = $this->validatedData($request, $establecimiento_curso_pie);
        $curso = $this->resolveEstablecimientoCurso($request, (int) $data['establecimiento_curso_id'], true);
        $data = $this->completePayload($data, $curso, $request, $establecimiento_curso_pie);
        $establecimiento_curso_pie->update($data);

        return redirect()->route('admin.establecimiento-curso-pie.index', ['anio' => $data['anio']])
            ->with('status', 'Registro PIE actualizado correctamente.');
    }

    public function destroy(Request $request, EstablecimientoCursoPie $establecimiento_curso_pie)
    {
        $activeRole = $this->authorizePieAccess($request, true);
        abort_unless($activeRole === 'admin', 403);
        $anio = $establecimiento_curso_pie->anio;
        $establecimiento_curso_pie->delete();

        return redirect()->route('admin.establecimiento-curso-pie.index', ['anio' => $anio])
            ->with('status', 'Registro PIE eliminado correctamente.');
    }

    public function importForm(Request $request)
    {
        $this->authorizePieAccess($request, true);
        return view('admin.establecimiento-curso-pie.import');
    }

    public function downloadTemplate(Request $request)
    {
        $this->authorizePieAccess($request, true);
        $path = resource_path('templates/establecimiento_curso_pie/plantilla_carga_masiva_pie.xlsx');
        abort_unless(is_file($path), 404, 'Plantilla no disponible.');
        return response()->download($path, 'plantilla_carga_masiva_pie.xlsx');
    }

    public function importStore(Request $request)
    {
        $this->authorizePieAccess($request, true);
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
        ], [
            'archivo.mimes' => 'La carga masiva debe ser un archivo Excel .xlsx.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        $rows = $this->readXlsxRows($data['archivo']->getRealPath());
        if (count($rows) < 2) {
            return back()->withErrors(['archivo' => 'El archivo no contiene filas para importar.']);
        }

        $header = $this->mapHeader($rows[0] ?? [], [
            'rbd' => ['RBD'],
            'curso' => ['CURSO', 'NIVEL'],
            'letra' => ['LETRA'],
            'anio' => ['ANIO', 'AÑO'],
            'neet' => ['NEET', 'NECESIDADES_TRANSITORIAS', 'TRANSITORIAS'],
            'neep' => ['NEEP', 'NECESIDADES_PERMANENTES', 'PERMANENTES'],
            'observacion' => ['OBSERVACION', 'OBSERVACIÓN'],
        ]);

        foreach (['rbd', 'curso', 'letra', 'neet', 'neep'] as $required) {
            if (! array_key_exists($required, $header)) {
                return back()->withErrors(['archivo' => 'La planilla debe incluir al menos las columnas RBD, CURSO, LETRA, NEET y NEEP.']);
            }
        }

        $anioDefault = (int) $data['anio'];
        $user = $request->user();
        $activeRole = $this->activeRole($request);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $read = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $header, $anioDefault, $user, $activeRole, &$created, &$updated, &$skipped, &$read, &$errors) {
            foreach (array_slice($rows, 1) as $offset => $row) {
                $line = $offset + 2;
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $read++;
                $rbd = $this->normalizeInteger($row[$header['rbd']] ?? null);
                $cursoRaw = trim((string) ($row[$header['curso']] ?? ''));
                $letra = $this->normalizeLetra($row[$header['letra']] ?? null);
                $anio = array_key_exists('anio', $header) ? ($this->normalizeInteger($row[$header['anio']] ?? null) ?: $anioDefault) : $anioDefault;
                $neet = $this->normalizeInteger($row[$header['neet']] ?? 0);
                $neep = $this->normalizeInteger($row[$header['neep']] ?? 0);
                $observacion = array_key_exists('observacion', $header) ? trim((string) ($row[$header['observacion']] ?? '')) : null;

                if (! $rbd) { $skipped++; $errors[] = "Fila {$line}: RBD inválido."; continue; }
                if ($cursoRaw === '') { $skipped++; $errors[] = "Fila {$line}: curso vacío."; continue; }
                if ($neet === null || $neet < 0) { $skipped++; $errors[] = "Fila {$line}: NEET debe ser mayor o igual a 0."; continue; }
                if ($neep === null || $neep < 0) { $skipped++; $errors[] = "Fila {$line}: NEEP debe ser mayor o igual a 0."; continue; }

                $curso = $this->findCursoByName($cursoRaw);
                if (! $curso) { $skipped++; $errors[] = "Fila {$line}: curso '{$cursoRaw}' no existe en el mantenedor Cursos."; continue; }

                $establecimiento = $this->findEstablecimientoByRbd($rbd);
                if (! $establecimiento) { $skipped++; $errors[] = "Fila {$line}: RBD {$rbd} no existe en establecimientos."; continue; }
                if ($this->isEstablecimientoRole($activeRole) && (int) ($user->establecimiento_id ?? 0) !== (int) $establecimiento->id) {
                    $skipped++; $errors[] = "Fila {$line}: no puedes cargar datos para un establecimiento distinto al asociado a tu usuario."; continue;
                }

                $establecimientoCurso = EstablecimientoCurso::query()
                    ->where('establecimiento_id', $establecimiento->id)
                    ->where('curso_id', $curso->id)
                    ->where('anio', $anio)
                    ->where('activo', true)
                    ->where(function ($query) use ($letra) {
                        if ($letra === null) {
                            $query->whereNull('letra')->orWhere('letra', '');
                        } else {
                            $query->where('letra', $letra);
                        }
                    })
                    ->first();

                if (! $establecimientoCurso) {
                    $skipped++; $errors[] = "Fila {$line}: no se encontró curso/sección activo para RBD {$rbd}, curso {$cursoRaw}, letra ".($letra ?: 'sin letra').", año {$anio}."; continue;
                }

                if (($neet + $neep) > (int) $establecimientoCurso->matricula) {
                    $skipped++; $errors[] = "Fila {$line}: NEET + NEEP supera la matrícula del curso/sección ({$establecimientoCurso->matricula})."; continue;
                }

                $calculo = PieHorasCalculator::calculate($establecimientoCurso, $neet, $neep);

                $payload = [
                    'establecimiento_id' => $establecimientoCurso->establecimiento_id,
                    'establecimiento_curso_id' => $establecimientoCurso->id,
                    'curso_id' => $establecimientoCurso->curso_id,
                    'plan_estudio_id' => $establecimientoCurso->plan_estudio_id,
                    'anio' => $anio,
                    'rbd' => $establecimientoCurso->rbd ?: $establecimiento->rbd,
                    'necesidades_transitorias' => $neet,
                    'necesidades_permanentes' => $neep,
                    'total_pie' => $neet + $neep,
                    'observacion' => $observacion,
                    'estado' => $this->isEstablecimientoRole($activeRole) ? 'borrador' : 'validado',
                    'updated_by' => $user?->id,
                ];
                $payload = array_merge($payload, $calculo);

                $item = EstablecimientoCursoPie::query()
                    ->where('establecimiento_curso_id', $establecimientoCurso->id)
                    ->where('anio', $anio)
                    ->first();

                if ($item) {
                    $item->update($payload);
                    $updated++;
                } else {
                    $payload['created_by'] = $user?->id;
                    EstablecimientoCursoPie::create($payload);
                    $created++;
                }
            }
        });

        return redirect()
            ->route('admin.establecimiento-curso-pie.index', ['anio' => $anioDefault])
            ->with('status', "Carga masiva PIE procesada. Filas leídas: {$read}. Creados: {$created}. Actualizados: {$updated}. Omitidos: {$skipped}.")
            ->with('import_errors', array_slice($errors, 0, 150));
    }

    private function authorizePieAccess(Request $request, bool $requiresEdit = false): string
    {
        $activeRole = $this->activeRole($request);
        abort_unless(in_array($activeRole, $this->allowedRoles, true), 403);
        if ($requiresEdit) {
            abort_unless(in_array($activeRole, $this->editableRoles, true), 403);
        }
        if ($this->isEstablecimientoRole($activeRole)) {
            abort_unless((int) ($request->user()->establecimiento_id ?? 0) > 0, 403, 'Usuario sin establecimiento asociado.');
        }
        return $activeRole;
    }

    private function activeRole(Request $request): ?string
    {
        $user = $request->user();
        return $user && method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
    }

    private function isEstablecimientoRole(?string $activeRole): bool
    {
        return $activeRole === 'funcionario_directivo_estab';
    }

    private function authorizeRecordScope(Request $request, EstablecimientoCursoPie $pie): void
    {
        if ($this->isEstablecimientoRole($this->activeRole($request))) {
            abort_unless((int) $pie->establecimiento_id === (int) ($request->user()->establecimiento_id ?? 0), 403);
        }
    }

    private function resolveEstablecimientoCurso(Request $request, int $id, bool $required = false): ?EstablecimientoCurso
    {
        $query = EstablecimientoCurso::query()->with(['establecimiento', 'curso', 'planEstudio'])->where('activo', true);
        if ($this->isEstablecimientoRole($this->activeRole($request))) {
            $query->where('establecimiento_id', (int) ($request->user()->establecimiento_id ?? 0));
        }
        $curso = $id > 0 ? $query->find($id) : null;
        if ($required) {
            abort_unless($curso, 404, 'Curso/sección no disponible.');
        }
        return $curso;
    }

    private function validatedData(Request $request, ?EstablecimientoCursoPie $item = null): array
    {
        $activeRole = $this->activeRole($request);
        $rules = [
            'establecimiento_curso_id' => ['required', 'integer', 'exists:establecimiento_cursos,id'],
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'necesidades_transitorias' => ['required', 'integer', 'min:0', 'max:9999'],
            'necesidades_permanentes' => ['required', 'integer', 'min:0', 'max:9999'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];

        if (in_array($activeRole, ['admin', 'coordinador_uatp'], true)) {
            $rules['estado'] = ['required', Rule::in(array_keys(EstablecimientoCursoPie::ESTADOS))];
        }

        $data = $request->validate($rules);
        if (! isset($data['estado'])) {
            $data['estado'] = $item?->estado ?: 'borrador';
        }
        return $data;
    }

    private function completePayload(array $data, EstablecimientoCurso $curso, Request $request, ?EstablecimientoCursoPie $item = null): array
    {
        $neet = (int) $data['necesidades_transitorias'];
        $neep = (int) $data['necesidades_permanentes'];
        if (($neet + $neep) > (int) $curso->matricula) {
            throw ValidationException::withMessages([
                'necesidades_transitorias' => 'La suma NEET + NEEP no puede superar la matrícula del curso/sección.',
            ]);
        }

        $calculo = PieHorasCalculator::calculate($curso, $neet, $neep);

        return array_merge([
            'establecimiento_id' => $curso->establecimiento_id,
            'establecimiento_curso_id' => $curso->id,
            'curso_id' => $curso->curso_id,
            'plan_estudio_id' => $curso->plan_estudio_id,
            'anio' => (int) $data['anio'],
            'rbd' => $curso->rbd ?: $curso->establecimiento?->rbd,
            'necesidades_transitorias' => $neet,
            'necesidades_permanentes' => $neep,
            'total_pie' => $neet + $neep,
            'observacion' => trim((string) ($data['observacion'] ?? '')) ?: null,
            'estado' => $data['estado'],
            'created_by' => $item?->created_by ?: $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ], $calculo);
    }

    private function cursosDisponibles(Request $request)
    {
        $query = EstablecimientoCurso::query()
            ->with(['establecimiento', 'curso', 'planEstudio'])
            ->where('activo', true)
            ->whereNotNull('establecimiento_id')
            ->whereNotNull('curso_id')
            ->orderByDesc('anio');

        if ($this->isEstablecimientoRole($this->activeRole($request))) {
            $query->where('establecimiento_id', (int) ($request->user()->establecimiento_id ?? 0));
        }

        return $query->limit(800)->get()->sortBy([
            fn ($a, $b) => strcmp((string) ($a->establecimiento?->nombre_establecimiento ?? ''), (string) ($b->establecimiento?->nombre_establecimiento ?? '')),
            fn ($a, $b) => ($a->curso?->orden ?? 999) <=> ($b->curso?->orden ?? 999),
            fn ($a, $b) => strcmp((string) $a->letra, (string) $b->letra),
        ]);
    }

    private function establecimientosAgrupados(?string $activeRole, $user, string $anio)
    {
        $query = Establecimiento::query()->orderBy('comuna')->orderBy('nombre_establecimiento');

        if ($this->isEstablecimientoRole($activeRole)) {
            $query->where('id', (int) ($user->establecimiento_id ?? 0));
        } else {
            $query->whereIn('id', function ($subQuery) use ($anio) {
                $subQuery->select('establecimiento_id')
                    ->from('establecimiento_curso_pie')
                    ->whereNotNull('establecimiento_id');

                if ($anio !== '') {
                    $subQuery->where('anio', (int) $anio);
                }
            });
        }

        return $query->get(['id', 'rbd', 'nombre_establecimiento', 'comuna'])->groupBy(fn ($e) => $e->comuna ?: 'Sin comuna');
    }

    private function summaryFor(string $anio, string $establecimientoId, ?string $activeRole, $user): array
    {
        $query = EstablecimientoCursoPie::query()->with(['establecimientoCurso.curso', 'establecimientoCurso.planEstudio']);
        if ($anio !== '') {
            $query->where('anio', (int) $anio);
        }
        if ($establecimientoId !== '') {
            $query->where('establecimiento_id', (int) $establecimientoId);
        }
        if ($this->isEstablecimientoRole($activeRole)) {
            $query->where('establecimiento_id', (int) ($user->establecimiento_id ?? 0));
        }

        $records = (clone $query)->get();
        $educadoresEquivalentes = 0.0;
        $contratoTotalMinutosExactos = 0.0;
        $basesUsadas = [];
        $educadoresPorProporcion = [];
        $porcentajesCache = [];

        foreach ($records as $record) {
            $curso = $record->establecimientoCurso;
            $baseMinutes = 1710;

            if ($curso) {
                $porcentajeKey = ((int) $record->establecimiento_id).'_'.((int) $record->anio);
                if (! array_key_exists($porcentajeKey, $porcentajesCache)) {
                    $porcentajesCache[$porcentajeKey] = AlumnoPrioritarioPorcentaje::query()
                        ->where('establecimiento_id', $record->establecimiento_id)
                        ->where('anio', $record->anio)
                        ->value('porcentaje');
                }
                $porcentajePrioritarios = $porcentajesCache[$porcentajeKey];
                $referencia = DocenteHorasNoLectivasCalculator::referenceFor(
                    $curso,
                    $porcentajePrioritarios !== null ? (float) $porcentajePrioritarios : null
                );
                $baseMinutes = (int) ($referencia['horas_aula_cronologicas_minutos'] ?? 1710) ?: 1710;
                $label = $referencia['proporcion_label'] ?? '65/35';
                $basesUsadas[$label] = DocenteHorasNoLectivasCalculator::formatMinutes($baseMinutes);
            } else {
                $label = '65/35';
            }

            $profMinutes = (int) ($record->prof_educ_dif_minutos ?? 0);
            $contrato = DocenteHorasNoLectivasCalculator::contratoAsociadoDesdeMinutosAula($profMinutes, $baseMinutes);
            $equivalentes = (float) ($contrato['equivalentes'] ?? 0.0);
            $educadoresEquivalentes += $equivalentes;
            $contratoTotalMinutosExactos += (float) ($contrato['minutos_contrato_exactos'] ?? 0.0);

            if (! isset($educadoresPorProporcion[$label])) {
                $educadoresPorProporcion[$label] = [
                    'minutos' => 0,
                    'base_minutos' => $baseMinutes,
                    'base_label' => DocenteHorasNoLectivasCalculator::formatMinutes($baseMinutes),
                    'equivalentes' => 0.0,
                    'contrato_minutos_exactos' => 0.0,
                ];
            }

            $educadoresPorProporcion[$label]['minutos'] += $profMinutes;
            $educadoresPorProporcion[$label]['equivalentes'] += $equivalentes;
            $educadoresPorProporcion[$label]['contrato_minutos_exactos'] += (float) ($contrato['minutos_contrato_exactos'] ?? 0.0);
        }

        foreach ($educadoresPorProporcion as &$detalleProporcion) {
            $minutosContrato = (int) round((float) ($detalleProporcion['contrato_minutos_exactos'] ?? 0.0));
            $detalleProporcion['contrato_minutos_redondeados'] = $minutosContrato;
            $detalleProporcion['contrato_label'] = DocenteHorasNoLectivasCalculator::formatMinutes($minutosContrato);
            $detalleProporcion['contrato_horas_bolsa'] = $minutosContrato > 0
                ? (int) ceil(((float) $detalleProporcion['contrato_minutos_exactos']) / 60)
                : 0;
        }
        unset($detalleProporcion);

        $contratoTotalMinutosRedondeados = (int) round($contratoTotalMinutosExactos);
        $contratoTotalHorasBolsa = $contratoTotalMinutosExactos > 0
            ? (int) ceil($contratoTotalMinutosExactos / 60)
            : 0;

        return [
            'registros' => $records->count(),
            'neet' => $records->sum('necesidades_transitorias'),
            'neep' => $records->sum('necesidades_permanentes'),
            'total' => $records->sum('total_pie'),
            'total_crono_minutos' => $records->sum('total_crono_minutos'),
            'prof_educ_dif_minutos' => $records->sum('prof_educ_dif_minutos'),
            'pae_minutos' => $records->sum('pae_minutos'),
            'educadores_equivalentes' => $educadoresEquivalentes,
            'educadores_redondeados' => $educadoresEquivalentes > 0 ? (int) ceil($educadoresEquivalentes) : 0,
            'horas_contrato_minutos_exactos' => $contratoTotalMinutosExactos,
            'horas_contrato_minutos_redondeados' => $contratoTotalMinutosRedondeados,
            'horas_contrato_label' => DocenteHorasNoLectivasCalculator::formatMinutes($contratoTotalMinutosRedondeados),
            'horas_contrato_bolsa' => $contratoTotalHorasBolsa,
            'educadores_bases_label' => $basesUsadas ? collect($basesUsadas)->map(fn ($base, $label) => "{$label}: {$base}")->values()->implode(' · ') : '65/35: 28:30',
            'educadores_por_proporcion' => collect($educadoresPorProporcion)->sortKeys()->all(),
        ];
    }

    private function findEstablecimientoByRbd(int $rbd): ?Establecimiento
    {
        return Establecimiento::query()
            ->where('rbd', $rbd)
            ->orWhereRaw("CAST(REPLACE(REPLACE(REPLACE(CAST(rbd AS CHAR), '.', ''), '-', ''), ' ', '') AS UNSIGNED) = ?", [$rbd])
            ->first();
    }

    private function findCursoByName(string $name): ?Curso
    {
        $normalized = $this->normalizeText($name);
        $aliases = [
            'NT1' => 'NT1', 'NT2' => 'NT2',
            '1 BASICO' => '1B', '2 BASICO' => '2B', '3 BASICO' => '3B', '4 BASICO' => '4B',
            '5 BASICO' => '5B', '6 BASICO' => '6B', '7 BASICO' => '7B', '8 BASICO' => '8B',
            '1 MEDIO' => '1M', '2 MEDIO' => '2M', '3 MEDIO HC' => '3M-HC', '4 MEDIO HC' => '4M-HC',
            '3 MEDIO TP' => '3M-TP', '4 MEDIO TP' => '4M-TP',
        ];
        if (isset($aliases[$normalized])) {
            return Curso::query()->where('codigo', $aliases[$normalized])->first();
        }
        return Curso::query()
            ->whereRaw('UPPER(nombre) = ?', [mb_strtoupper(trim($name), 'UTF-8')])
            ->orWhereRaw('UPPER(codigo) = ?', [mb_strtoupper(trim($name), 'UTF-8')])
            ->first();
    }

    private function normalizeLetra($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : mb_strtoupper($text, 'UTF-8');
    }

    private function normalizeInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = preg_replace('/[^0-9-]/', '', (string) $value);
        return $number === '' ? null : (int) $number;
    }

    private function normalizeText(string $text): string
    {
        $text = trim($text);
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            '°' => '', 'º' => '', '(' => ' ', ')' => ' ', '/' => ' ', '.' => ' ', '-' => ' ', '_' => ' ',
        ]);
        $text = preg_replace('/\s+/', ' ', $text);
        return mb_strtoupper(trim($text), 'UTF-8');
    }

    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = @simplexml_load_string($sharedXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } else {
                        $parts = [];
                        foreach ($si->r as $run) {
                            $parts[] = (string) $run->t;
                        }
                        $sharedStrings[] = implode('', $parts);
                    }
                }
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            $zip->close();
            return [];
        }
        $xml = @simplexml_load_string($sheetXml);
        $rows = [];
        if ($xml && isset($xml->sheetData)) {
            foreach ($xml->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    $index = $this->columnIndexFromCell($ref);
                    $type = (string) $cell['t'];
                    $raw = isset($cell->v) ? (string) $cell->v : '';
                    if ($type === 's') {
                        $raw = $sharedStrings[(int) $raw] ?? '';
                    } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                        $raw = (string) $cell->is->t;
                    }
                    $values[$index] = $raw;
                }
                if ($values) {
                    $max = max(array_keys($values));
                    $rows[] = array_map(fn ($i) => $values[$i] ?? null, range(0, $max));
                }
            }
        }
        $zip->close();
        return $rows;
    }

    private function columnIndexFromCell(string $cell): int
    {
        preg_match('/^([A-Z]+)/i', $cell, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }

    private function mapHeader(array $header, array $aliases): array
    {
        $normalizedHeader = [];
        foreach ($header as $idx => $value) {
            $normalizedHeader[$this->normalizeText((string) $value)] = $idx;
        }
        $map = [];
        foreach ($aliases as $key => $names) {
            foreach ($names as $name) {
                $needle = $this->normalizeText($name);
                if (array_key_exists($needle, $normalizedHeader)) {
                    $map[$key] = $normalizedHeader[$needle];
                    break;
                }
            }
        }
        return $map;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }
}
