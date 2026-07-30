<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curso;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use ZipArchive;

class EstablecimientoCursoController extends Controller
{
    public function index(Request $request)
    {
        $anio = trim((string) $request->query('anio', (string) now()->year));
        $establecimientoId = trim((string) $request->query('establecimiento_id', ''));
        $cursoId = trim((string) $request->query('curso_id', ''));
        $regimen = trim((string) $request->query('regimen_jec', ''));
        $q = trim((string) $request->query('q', ''));

        $items = DB::table('establecimiento_cursos as ec')
            ->join('establecimientos as e', 'e.id', '=', 'ec.establecimiento_id')
            ->join('cursos as c', 'c.id', '=', 'ec.curso_id')
            ->leftJoin('planes_estudio as pe', 'pe.id', '=', 'ec.plan_estudio_id')
            ->where('ec.activo', true)
            ->whereNotNull('ec.establecimiento_id')
            ->whereNotNull('ec.curso_id')
            ->whereNotNull('ec.anio')
            ->when($anio !== '', fn ($query) => $query->where('ec.anio', (int) $anio))
            ->when($establecimientoId !== '', fn ($query) => $query->where('ec.establecimiento_id', (int) $establecimientoId))
            ->when($cursoId !== '', fn ($query) => $query->where('ec.curso_id', (int) $cursoId))
            ->when($regimen !== '', fn ($query) => $query->where('ec.regimen_jec', $regimen))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('ec.nombre_seccion', 'like', "%{$q}%")
                        ->orWhere('ec.rbd', 'like', "%{$q}%")
                        ->orWhere('e.rbd', 'like', "%{$q}%")
                        ->orWhere('e.nombre_establecimiento', 'like', "%{$q}%")
                        ->orWhere('e.comuna', 'like', "%{$q}%")
                        ->orWhere('c.nombre', 'like', "%{$q}%")
                        ->orWhere('pe.nombre_plan', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('ec.anio')
            ->orderBy('e.comuna')
            ->orderBy('e.nombre_establecimiento')
            ->orderBy('c.orden')
            ->orderBy('ec.letra')
            ->select([
                'ec.id',
                'ec.establecimiento_id',
                'ec.rbd',
                'ec.curso_id',
                'ec.plan_estudio_id',
                'ec.anio',
                'ec.letra',
                'ec.nombre_seccion',
                'ec.matricula',
                'ec.regimen_jec',
                'ec.fuente',
                'ec.activo',
                'e.rbd as establecimiento_rbd',
                'e.nombre_establecimiento as establecimiento_nombre',
                'e.comuna as establecimiento_comuna',
                'c.nombre as curso_nombre',
                'c.codigo as curso_codigo',
                'pe.nombre_plan as plan_nombre',
                'pe.regimen_jec as plan_regimen_jec',
                'pe.horas_semanales_total as plan_horas_semanales_total',
            ])
            ->paginate(25)
            ->withQueryString();

        return view('admin.establecimiento-cursos.index', [
            'items' => $items,
            'establecimientos' => $this->establecimientosAgrupados(),
            'cursos' => Curso::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']),
            'anio' => $anio,
            'establecimientoId' => $establecimientoId,
            'cursoId' => $cursoId,
            'regimen' => $regimen,
            'q' => $q,
        ]);
    }

    public function create()
    {
        return view('admin.establecimiento-cursos.create', [
            'establecimientoCurso' => new EstablecimientoCurso([
                'anio' => now()->year,
                'matricula' => 0,
                'regimen_jec' => 'Con JEC',
                'activo' => true,
            ]),
            'establecimientos' => $this->establecimientosAgrupados(),
            'cursos' => Curso::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(),
            'planes' => $this->planesDisponibles(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data = $this->completeDerivedFields($data);
        EstablecimientoCurso::create($data);

        return redirect()->route('admin.establecimiento-cursos.index')->with('status', 'Curso del establecimiento creado correctamente.');
    }

    public function show(EstablecimientoCurso $establecimiento_curso)
    {
        $establecimiento_curso->load(['establecimiento', 'curso', 'planEstudio']);

        return view('admin.establecimiento-cursos.show', ['establecimientoCurso' => $establecimiento_curso]);
    }

    public function edit(EstablecimientoCurso $establecimiento_curso)
    {
        return view('admin.establecimiento-cursos.edit', [
            'establecimientoCurso' => $establecimiento_curso,
            'establecimientos' => $this->establecimientosAgrupados(),
            'cursos' => Curso::query()->where('activo', true)->orderBy('orden')->orderBy('nombre')->get(),
            'planes' => $this->planesDisponibles(),
        ]);
    }

    public function update(Request $request, EstablecimientoCurso $establecimiento_curso)
    {
        $data = $this->validatedData($request, $establecimiento_curso);
        $data = $this->completeDerivedFields($data);
        $establecimiento_curso->update($data);

        return redirect()->route('admin.establecimiento-cursos.index')->with('status', 'Curso del establecimiento actualizado correctamente.');
    }

    public function destroy(EstablecimientoCurso $establecimiento_curso)
    {
        $establecimiento_curso->delete();

        return redirect()->route('admin.establecimiento-cursos.index')->with('status', 'Curso del establecimiento eliminado correctamente.');
    }

    public function importForm()
    {
        return view('admin.establecimiento-cursos.import');
    }

    public function downloadTemplate()
    {
        $path = resource_path('templates/establecimiento_cursos/plantilla_carga_masiva_establecimiento_cursos.xlsx');

        abort_unless(is_file($path), 404, 'Plantilla no disponible.');

        return response()->download($path, 'plantilla_carga_masiva_establecimiento_cursos.xlsx');
    }

    public function importStore(Request $request)
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'reemplazar_existentes' => ['nullable', 'boolean'],
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
            'establecimiento' => ['ESTABLECIMIENTOS', 'ESTABLECIMIENTO', 'NOMBRE_ESTABLECIMIENTO'],
            'curso' => ['CURSO', 'NIVEL'],
            'letra' => ['LETRA'],
            'matricula' => ['MATRICULA_2026', 'MATRÍCULA_2026', 'MATRICULA', 'MATRÍCULA'],
            'jec' => ['JEC', 'REGIMEN_JEC', 'RÉGIMEN_JEC', 'REGIMEN'],
        ]);

        foreach (['rbd', 'curso', 'matricula', 'jec'] as $required) {
            if (! array_key_exists($required, $header)) {
                return back()->withErrors(['archivo' => 'La planilla debe incluir al menos las columnas RBD, CURSO, MATRICULA_2026 y JEC.']);
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $read = 0;
        $withoutPlan = 0;
        $errors = [];
        $anio = (int) $data['anio'];
        $reemplazarExistentes = $request->boolean('reemplazar_existentes');

        DB::transaction(function () use ($rows, $header, $anio, $reemplazarExistentes, &$created, &$updated, &$skipped, &$read, &$withoutPlan, &$errors) {
            $this->purgeIncompleteEstablecimientoCursos($anio);

            if ($reemplazarExistentes) {
                EstablecimientoCurso::query()->where('anio', $anio)->delete();
            }

            foreach (array_slice($rows, 1) as $offset => $row) {
                $line = $offset + 2;
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $read++;
                $rbd = $this->normalizeInteger($row[$header['rbd']] ?? null);
                $cursoRaw = trim((string) ($row[$header['curso']] ?? ''));
                $letra = $this->normalizeLetra($row[$header['letra']] ?? null);
                $matricula = $this->normalizeInteger($row[$header['matricula']] ?? 0) ?? 0;
                $regimen = $this->normalizeRegimen($row[$header['jec']] ?? '');

                if (! $rbd) {
                    $skipped++; $errors[] = "Fila {$line}: RBD inválido."; continue;
                }
                if ($cursoRaw === '') {
                    $skipped++; $errors[] = "Fila {$line}: curso vacío."; continue;
                }
                if ($this->normalizeText($cursoRaw) === 'TOTAL') {
                    $skipped++; continue;
                }
                if (! in_array($regimen, ['Con JEC', 'Sin JEC', 'No aplica'], true)) {
                    $skipped++; $errors[] = "Fila {$line}: régimen JEC inválido."; continue;
                }

                $establecimiento = $this->findEstablecimientoByRbd($rbd);
                if (! $establecimiento) {
                    $skipped++; $errors[] = "Fila {$line}: RBD {$rbd} no existe en establecimientos."; continue;
                }

                $curso = $this->findCursoByName($cursoRaw);
                if (! $curso) {
                    $skipped++; $errors[] = "Fila {$line}: curso '{$cursoRaw}' no existe en el mantenedor Cursos."; continue;
                }

                $plan = $this->findMatchingPlan($curso->id, $anio, $regimen);
                if (! $plan) {
                    $withoutPlan++;
                    $errors[] = "Fila {$line}: no se encontró plan para {$curso->nombre}, año {$anio}, régimen {$regimen}. Se importó sin plan asociado.";
                }

                $payload = [
                    'establecimiento_id' => $establecimiento->id,
                    'rbd' => $rbd,
                    'curso_id' => $curso->id,
                    'plan_estudio_id' => $plan?->id,
                    'anio' => $anio,
                    'letra' => $letra,
                    'nombre_seccion' => trim($curso->nombre.' '.($letra ?: '')),
                    'matricula' => max(0, (int) $matricula),
                    'regimen_jec' => $regimen,
                    'fuente' => 'MAT_JEC_2026',
                    'activo' => true,
                ];

                $item = EstablecimientoCurso::query()
                    ->where('establecimiento_id', $establecimiento->id)
                    ->where('curso_id', $curso->id)
                    ->where('anio', $anio)
                    ->where(function ($query) use ($letra) {
                        if ($letra === null) {
                            $query->whereNull('letra');
                        } else {
                            $query->where('letra', $letra);
                        }
                    })
                    ->first();

                if ($item) {
                    $item->update($payload);
                    $updated++;
                } else {
                    EstablecimientoCurso::create($payload);
                    $created++;
                }
            }
        });

        return redirect()
            ->route('admin.establecimiento-cursos.index', ['anio' => $anio])
            ->with('status', ($reemplazarExistentes ? 'Registros del año reemplazados. ' : '')."Carga masiva procesada. Filas leídas: {$read}. Creados: {$created}. Actualizados: {$updated}. Sin plan asociado: {$withoutPlan}. Omitidos: {$skipped}.")
            ->with('import_errors', array_slice($errors, 0, 150));
    }

    private function purgeIncompleteEstablecimientoCursos(int $anio): void
    {
        EstablecimientoCurso::query()
            ->where(function ($query) {
                $query->whereNull('anio')
                    ->orWhereNull('establecimiento_id')
                    ->orWhereNull('curso_id')
                    ->orWhereNull('nombre_seccion')
                    ->orWhere('nombre_seccion', '')
                    ->orWhereNull('regimen_jec')
                    ->orWhere('regimen_jec', '');
            })
            ->delete();
    }

    private function findEstablecimientoByRbd(int $rbd): ?Establecimiento
    {
        return Establecimiento::query()
            ->where('rbd', $rbd)
            ->orWhereRaw("CAST(REPLACE(REPLACE(REPLACE(CAST(rbd AS CHAR), '.', ''), '-', ''), ' ', '') AS UNSIGNED) = ?", [$rbd])
            ->first();
    }

    private function validatedData(Request $request, ?EstablecimientoCurso $item = null): array
    {
        $itemId = $item?->id;

        $data = $request->validate([
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'curso_id' => ['required', 'integer', 'exists:cursos,id'],
            'plan_estudio_id' => ['nullable', 'integer', 'exists:planes_estudio,id'],
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'letra' => [
                'nullable', 'string', 'max:20',
                Rule::unique('establecimiento_cursos', 'letra')
                    ->where(fn ($query) => $query
                        ->where('establecimiento_id', $request->input('establecimiento_id'))
                        ->where('curso_id', $request->input('curso_id'))
                        ->where('anio', $request->input('anio')))
                    ->ignore($itemId),
            ],
            'nombre_seccion' => ['nullable', 'string', 'max:160'],
            'matricula' => ['required', 'integer', 'min:0', 'max:9999'],
            'regimen_jec' => ['required', 'string', Rule::in(['Con JEC', 'Sin JEC', 'No aplica'])],
            'fuente' => ['nullable', 'string', 'max:120'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['letra'] = $this->normalizeLetra($data['letra'] ?? null);
        $data['activo'] = (bool) ($data['activo'] ?? false);
        $data['fuente'] = trim((string) ($data['fuente'] ?? '')) ?: 'manual';

        return $data;
    }

    private function completeDerivedFields(array $data): array
    {
        $establecimiento = Establecimiento::find($data['establecimiento_id']);
        $curso = Curso::find($data['curso_id']);
        $data['rbd'] = $establecimiento?->rbd;

        if (empty($data['nombre_seccion']) && $curso) {
            $data['nombre_seccion'] = trim($curso->nombre.' '.($data['letra'] ?? ''));
        }

        if (empty($data['plan_estudio_id']) && $curso) {
            $data['plan_estudio_id'] = $this->findMatchingPlan($curso->id, (int) $data['anio'], $data['regimen_jec'])?->id;
        }

        return $data;
    }

    private function findMatchingPlan(int $cursoId, int $anio, string $regimen): ?PlanEstudio
    {
        $regimenBusqueda = $regimen === 'No aplica' ? 'Sin JEC' : $regimen;

        return PlanEstudio::query()
            ->where('curso_id', $cursoId)
            ->where('anio', $anio)
            ->where('regimen_jec', $regimenBusqueda)
            ->where('activo', true)
            ->first();
    }

    private function findCursoByName(string $name): ?Curso
    {
        $normalized = $this->normalizeText($name);

        $aliases = [
            'NT1' => 'NT1',
            'NT2' => 'NT2',
            '1 BASICO' => '1B',
            '2 BASICO' => '2B',
            '3 BASICO' => '3B',
            '4 BASICO' => '4B',
            '5 BASICO' => '5B',
            '6 BASICO' => '6B',
            '7 BASICO' => '7B',
            '8 BASICO' => '8B',
            '1 MEDIO' => '1M',
            '2 MEDIO' => '2M',
            '3 MEDIO HC' => '3M-HC',
            '4 MEDIO HC' => '4M-HC',
            '3 MEDIO TP' => '3M-TP',
            '4 MEDIO TP' => '4M-TP',
            '3 MEDIO ARTISTICO' => '3M-ART',
            '4 MEDIO ARTISTICO' => '4M-ART',
            'NIVEL BASICO 1 1 A 4 BASICO' => 'EPJA_BASICA_N1',
            'NIVEL BASICO 2 5 Y 6 BASICO' => 'EPJA_BASICA_N2',
            'NIVEL BASICO 3 7 Y 8 BASICO' => 'EPJA_BASICA_N3',
            '1ER NIVEL MEDIO 1 Y 2 MEDIO' => 'EPJA_MEDIA_N1',
            '1 NIVEL MEDIO 1 Y 2 MEDIO' => 'EPJA_MEDIA_N1',
            '2 NIVEL MEDIO 3 Y 4 MEDIO' => 'EPJA_MEDIA_N2',
            'LABORAL 1' => 'ESPECIAL_LABORAL_1',
            'LABORAL 2' => 'ESPECIAL_LABORAL_2',
            'LABORAL 3' => 'ESPECIAL_LABORAL_3',
        ];

        if (isset($aliases[$normalized])) {
            return Curso::query()->where('codigo', $aliases[$normalized])->first();
        }

        return Curso::query()
            ->whereRaw('UPPER(nombre) = ?', [mb_strtoupper(trim($name), 'UTF-8')])
            ->orWhereRaw('UPPER(codigo) = ?', [mb_strtoupper(trim($name), 'UTF-8')])
            ->first();
    }

    private function establecimientosAgrupados()
    {
        return Establecimiento::query()
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna'])
            ->groupBy(fn ($establecimiento) => $establecimiento->comuna ?: 'Sin comuna');
    }

    private function planesDisponibles()
    {
        return PlanEstudio::query()
            ->with('curso')
            ->join('cursos', 'cursos.id', '=', 'planes_estudio.curso_id')
            ->where('planes_estudio.activo', true)
            ->orderByDesc('planes_estudio.anio')
            ->orderBy('cursos.orden')
            ->orderBy('planes_estudio.regimen_jec')
            ->select('planes_estudio.*')
            ->get();
    }

    private function normalizeRegimen($value): string
    {
        $text = $this->normalizeText((string) $value);
        return match ($text) {
            'JEC', 'CON JEC', 'CON JORNADA ESCOLAR COMPLETA' => 'Con JEC',
            'SIN JEC', 'NO JEC', 'SIN JORNADA ESCOLAR COMPLETA' => 'Sin JEC',
            'NO APLICA', 'N/A', 'NA' => 'No aplica',
            default => trim((string) $value),
        };
    }

    private function normalizeLetra($value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }
        return mb_strtoupper($text, 'UTF-8');
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
