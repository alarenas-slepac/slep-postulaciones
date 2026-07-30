<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AlumnoPrioritarioPorcentaje;
use App\Models\Establecimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use ZipArchive;

class AlumnoPrioritarioPorcentajeController extends Controller
{
    public function index(Request $request)
    {
        $anio = trim((string) $request->query('anio', ''));
        $comuna = trim((string) $request->query('comuna', ''));
        $q = trim((string) $request->query('q', ''));

        $items = AlumnoPrioritarioPorcentaje::query()
            ->with('establecimiento')
            ->when($anio !== '', fn ($query) => $query->where('anio', (int) $anio))
            ->when($comuna !== '', function ($query) use ($comuna) {
                $query->whereHas('establecimiento', fn ($sub) => $sub->where('comuna', $comuna));
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('establecimiento', function ($sub) use ($q) {
                    $sub->where('nombre_establecimiento', 'like', "%{$q}%")
                        ->orWhere('rbd', 'like', "%{$q}%");
                });
            })
            ->join('establecimientos', 'establecimientos.id', '=', 'alumnos_prioritarios_porcentajes.establecimiento_id')
            ->orderByDesc('alumnos_prioritarios_porcentajes.anio')
            ->orderBy('establecimientos.comuna')
            ->orderBy('establecimientos.nombre_establecimiento')
            ->select('alumnos_prioritarios_porcentajes.*')
            ->paginate(20)
            ->withQueryString();

        $comunas = Establecimiento::query()
            ->whereNotNull('comuna')
            ->where('comuna', '<>', '')
            ->distinct()
            ->orderBy('comuna')
            ->pluck('comuna');

        return view('admin.alumnos-prioritarios.index', compact('items', 'anio', 'comuna', 'q', 'comunas'));
    }

    public function create()
    {
        return view('admin.alumnos-prioritarios.create', [
            'item' => new AlumnoPrioritarioPorcentaje(['anio' => now()->year]),
            'establecimientosPorComuna' => $this->establecimientosPorComuna(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        AlumnoPrioritarioPorcentaje::create($data);

        return redirect()
            ->route('admin.alumnos-prioritarios.index')
            ->with('status', 'Porcentaje de alumnos prioritarios creado correctamente.');
    }

    public function show(AlumnoPrioritarioPorcentaje $alumnos_prioritario)
    {
        $alumnos_prioritario->load(['establecimiento', 'creadoPor', 'actualizadoPor']);

        return view('admin.alumnos-prioritarios.show', [
            'item' => $alumnos_prioritario,
        ]);
    }

    public function edit(AlumnoPrioritarioPorcentaje $alumnos_prioritario)
    {
        return view('admin.alumnos-prioritarios.edit', [
            'item' => $alumnos_prioritario,
            'establecimientosPorComuna' => $this->establecimientosPorComuna(),
        ]);
    }

    public function update(Request $request, AlumnoPrioritarioPorcentaje $alumnos_prioritario)
    {
        $data = $this->validatedData($request, $alumnos_prioritario);
        $data['updated_by'] = $request->user()->id;

        $alumnos_prioritario->update($data);

        return redirect()
            ->route('admin.alumnos-prioritarios.index')
            ->with('status', 'Porcentaje de alumnos prioritarios actualizado correctamente.');
    }

    public function destroy(AlumnoPrioritarioPorcentaje $alumnos_prioritario)
    {
        $alumnos_prioritario->delete();

        return redirect()
            ->route('admin.alumnos-prioritarios.index')
            ->with('status', 'Porcentaje de alumnos prioritarios eliminado correctamente.');
    }

    public function importForm()
    {
        return view('admin.alumnos-prioritarios.import');
    }

    public function downloadTemplate()
    {
        $path = resource_path('templates/alumnos_prioritarios/plantilla_carga_masiva_alumnos_prioritarios.xlsx');

        abort_unless(is_file($path), 404, 'Plantilla no disponible.');

        return response()->download($path, 'plantilla_carga_masiva_alumnos_prioritarios.xlsx');
    }

    public function importStore(Request $request)
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'archivo.mimes' => 'La carga masiva debe ser un archivo Excel .xlsx.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        $rows = $this->readXlsxRows($data['archivo']->getRealPath());

        if (count($rows) < 2) {
            return back()->withErrors(['archivo' => 'El archivo no contiene filas para importar.']);
        }

        $header = $this->mapHeader($rows[0] ?? []);
        $required = ['rbd', 'anio', 'porcentaje'];
        $missing = array_values(array_filter($required, fn ($field) => ! array_key_exists($field, $header)));

        if ($missing) {
            return back()->withErrors([
                'archivo' => 'La plantilla debe incluir columnas equivalentes a: RBD, ANIO y PORCENTAJE. También se acepta ANIO_PROC y CONCENTRACION.',
            ]);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $userId = $request->user()->id;

        DB::transaction(function () use ($rows, $header, &$created, &$updated, &$skipped, &$errors, $userId) {
            foreach (array_slice($rows, 1) as $offset => $row) {
                $line = $offset + 2;

                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $rbd = $this->normalizeRbd($row[$header['rbd']] ?? null);
                $anio = $this->normalizeInteger($row[$header['anio']] ?? null);
                $porcentaje = $this->normalizeDecimal($row[$header['porcentaje']] ?? null);
                $observacion = array_key_exists('observacion', $header)
                    ? trim((string) ($row[$header['observacion']] ?? ''))
                    : null;

                if ($rbd === '') {
                    $skipped++;
                    $errors[] = "Fila {$line}: RBD vacío o inválido.";
                    continue;
                }

                if (! $anio || $anio < 2020 || $anio > 2100) {
                    $skipped++;
                    $errors[] = "Fila {$line}: año inválido.";
                    continue;
                }

                if ($porcentaje === null || $porcentaje < 0 || $porcentaje > 100) {
                    $skipped++;
                    $errors[] = "Fila {$line}: porcentaje inválido; debe estar entre 0 y 100.";
                    continue;
                }

                $establecimiento = Establecimiento::query()
                    ->where('rbd', $rbd)
                    ->orWhere('rbd', (int) $rbd)
                    ->first();

                if (! $establecimiento) {
                    $skipped++;
                    $errors[] = "Fila {$line}: no existe establecimiento con RBD {$rbd}.";
                    continue;
                }

                $item = AlumnoPrioritarioPorcentaje::query()
                    ->where('establecimiento_id', $establecimiento->id)
                    ->where('anio', $anio)
                    ->first();

                $payload = [
                    'establecimiento_id' => $establecimiento->id,
                    'anio' => $anio,
                    'porcentaje' => $porcentaje,
                    'observacion' => $observacion !== '' ? $observacion : null,
                    'updated_by' => $userId,
                ];

                if ($item) {
                    $item->update($payload);
                    $updated++;
                } else {
                    $payload['created_by'] = $userId;
                    AlumnoPrioritarioPorcentaje::create($payload);
                    $created++;
                }
            }
        });

        return redirect()
            ->route('admin.alumnos-prioritarios.index')
            ->with('status', "Carga masiva procesada. Creados: {$created}. Actualizados: {$updated}. Omitidos: {$skipped}.")
            ->with('import_errors', array_slice($errors, 0, 80));
    }

    private function validatedData(Request $request, ?AlumnoPrioritarioPorcentaje $item = null): array
    {
        return $request->validate([
            'establecimiento_id' => [
                'required',
                'integer',
                Rule::exists('establecimientos', 'id'),
                Rule::unique('alumnos_prioritarios_porcentajes', 'establecimiento_id')
                    ->where(fn ($query) => $query->where('anio', (int) $request->input('anio')))
                    ->ignore($item?->id),
            ],
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], [
            'establecimiento_id.unique' => 'El establecimiento seleccionado ya tiene un porcentaje registrado para el año indicado.',
            'porcentaje.max' => 'El porcentaje no puede ser superior a 100.',
            'porcentaje.min' => 'El porcentaje no puede ser inferior a 0.',
        ]);
    }

    private function establecimientosPorComuna()
    {
        return Establecimiento::query()
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna'])
            ->groupBy(fn ($establecimiento) => $establecimiento->comuna ?: 'Sin comuna');
    }

    private function readXlsxRows(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            abort(500, 'La extensión ZipArchive de PHP es requerida para leer archivos .xlsx.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            abort(422, 'No fue posible abrir el archivo Excel.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            abort(422, 'No se encontró la primera hoja del archivo Excel.');
        }

        $xml = simplexml_load_string($sheetXml);
        if (! $xml) {
            abort(422, 'No fue posible leer la hoja del archivo Excel.');
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
        if ($xmlString === false) {
            return [];
        }

        $xml = simplexml_load_string($xmlString);
        if (! $xml) {
            return [];
        }

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

    private function mapHeader(array $row): array
    {
        $aliases = [
            'rbd' => ['RBD'],
            'anio' => ['ANIO', 'AÑO', 'ANO', 'ANIO_PROC', 'AÑO_PROC', 'ANO_PROC', 'ANIO PROCESO', 'AÑO PROCESO'],
            'porcentaje' => ['PORCENTAJE', '% PRIORITARIOS', '% ALUMNOS PRIORITARIOS', 'PORCENTAJE ALUMNOS PRIORITARIOS', 'CONCENTRACION', 'CONCENTRACIÓN'],
            'observacion' => ['OBSERVACION', 'OBSERVACIÓN', 'COMENTARIO', 'COMENTARIOS'],
        ];

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

    private function normalizeHeader(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'], ['A', 'E', 'I', 'O', 'U', 'N'], $value);
        $value = preg_replace('/[^A-Z0-9%]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function normalizeRbd($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d+(?:\.0+)?$/', $value)) {
            return (string) (int) $value;
        }

        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function normalizeInteger($value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d+(?:\.0+)?$/', $value)) {
            return (int) $value;
        }

        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : null;
    }

    private function normalizeDecimal($value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(['%', ' '], '', $value);
        if (strpos($value, ',') !== false && strpos($value, '.') === false) {
            $value = str_replace(',', '.', $value);
        } else {
            $value = str_replace(',', '', $value);
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
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

    private function excelColumnIndex(string $cellRef): int
    {
        preg_match('/^([A-Z]+)/i', $cellRef, $matches);
        $letters = strtoupper($matches[1] ?? 'A');
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
