<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use ZipArchive;

class AsignaturaController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $nivel = trim((string) $request->query('nivel_educativo', ''));
        $area = trim((string) $request->query('area', ''));
        $tipo = trim((string) $request->query('tipo_asignatura', ''));
        $oficial = trim((string) $request->query('es_oficial', ''));
        $activo = trim((string) $request->query('activo', ''));

        $niveles = Asignatura::query()->select('nivel_educativo')->whereNotNull('nivel_educativo')->distinct()->orderBy('nivel_educativo')->pluck('nivel_educativo')->filter()->values();
        $areas = Asignatura::query()->select('area')->whereNotNull('area')->distinct()->orderBy('area')->pluck('area')->filter()->values();
        $tipos = Asignatura::tiposOptions();

        $asignaturas = Asignatura::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('nombre', 'like', "%{$q}%")
                        ->orWhere('codigo', 'like', "%{$q}%")
                        ->orWhere('observacion', 'like', "%{$q}%");
                });
            })
            ->when($nivel !== '', fn ($query) => $query->where('nivel_educativo', $nivel))
            ->when($area !== '', fn ($query) => $query->where('area', $area))
            ->when($tipo !== '', fn ($query) => $query->where('tipo_asignatura', $tipo))
            ->when($oficial !== '', fn ($query) => $query->where('es_oficial', $oficial === '1'))
            ->when($activo !== '', fn ($query) => $query->where('activo', $activo === '1'))
            ->orderBy('nivel_educativo')
            ->orderBy('area')
            ->orderBy('tipo_asignatura')
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return view('admin.asignaturas.index', compact('asignaturas', 'q', 'nivel', 'area', 'tipo', 'oficial', 'activo', 'niveles', 'areas', 'tipos'));
    }

    public function create()
    {
        return view('admin.asignaturas.create', [
            'asignatura' => new Asignatura([
                'tipo_asignatura' => 'obligatoria',
                'es_oficial' => true,
                'activo' => true,
            ]),
            'tipos' => Asignatura::tiposOptions(),
        ]);
    }

    public function store(Request $request)
    {
        Asignatura::create($this->validatedData($request));

        return redirect()->route('admin.asignaturas.index')->with('status', 'Asignatura creada correctamente.');
    }

    public function show(Asignatura $asignatura)
    {
        return view('admin.asignaturas.show', compact('asignatura'));
    }

    public function edit(Asignatura $asignatura)
    {
        return view('admin.asignaturas.edit', [
            'asignatura' => $asignatura,
            'tipos' => Asignatura::tiposOptions(),
        ]);
    }

    public function update(Request $request, Asignatura $asignatura)
    {
        $asignatura->update($this->validatedData($request, $asignatura));

        return redirect()->route('admin.asignaturas.index')->with('status', 'Asignatura actualizada correctamente.');
    }

    public function destroy(Asignatura $asignatura)
    {
        $asignatura->delete();

        return redirect()->route('admin.asignaturas.index')->with('status', 'Asignatura eliminada correctamente.');
    }

    public function importForm()
    {
        return view('admin.asignaturas.import');
    }

    public function downloadTemplate()
    {
        $path = resource_path('templates/asignaturas/plantilla_carga_masiva_asignaturas.xlsx');
        abort_unless(is_file($path), 404, 'Plantilla no disponible.');

        return response()->download($path, 'plantilla_carga_masiva_asignaturas.xlsx');
    }

    public function importStore(Request $request)
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ], [
            'archivo.mimes' => 'La carga masiva debe ser un archivo Excel .xlsx.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
        ]);

        $rows = $this->readXlsxRows($data['archivo']->getRealPath(), 1);
        if (count($rows) < 2) {
            return back()->withErrors(['archivo' => 'La hoja Asignaturas no contiene filas para importar.']);
        }

        $header = $this->mapHeader($rows[0] ?? [], [
            'nombre' => ['NOMBRE', 'ASIGNATURA'],
            'codigo' => ['CODIGO', 'CÓDIGO'],
            'nivel_educativo' => ['NIVEL_EDUCATIVO', 'NIVEL EDUCATIVO'],
            'area' => ['AREA', 'ÁREA'],
            'tipo_asignatura' => ['TIPO_ASIGNATURA', 'TIPO ASIGNATURA', 'TIPO'],
            'es_oficial' => ['ES_OFICIAL', 'OFICIAL'],
            'activo' => ['ACTIVO'],
            'observacion' => ['OBSERVACION', 'OBSERVACIÓN'],
        ]);

        foreach (['nombre', 'codigo', 'tipo_asignatura'] as $required) {
            if (! array_key_exists($required, $header)) {
                return back()->withErrors(['archivo' => 'La hoja Asignaturas debe incluir NOMBRE, CODIGO y TIPO_ASIGNATURA.']);
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $header, &$created, &$updated, &$skipped, &$errors) {
            foreach (array_slice($rows, 1) as $offset => $row) {
                $line = $offset + 2;
                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $nombre = $this->cell($row, $header, 'nombre') ?: '';
                $codigo = $this->cell($row, $header, 'codigo') ?: '';
                $tipo = $this->normalizeTipo($this->cell($row, $header, 'tipo_asignatura') ?: '');

                if ($nombre === '') {
                    $skipped++; $errors[] = "Fila {$line}: nombre vacío."; continue;
                }
                if ($codigo === '') {
                    $skipped++; $errors[] = "Fila {$line}: código vacío."; continue;
                }
                if (! array_key_exists($tipo, Asignatura::tiposOptions())) {
                    $skipped++; $errors[] = "Fila {$line}: tipo de asignatura inválido."; continue;
                }

                $payload = [
                    'nombre' => $nombre,
                    'codigo' => $codigo,
                    'nivel_educativo' => $this->cell($row, $header, 'nivel_educativo') ?: null,
                    'area' => $this->cell($row, $header, 'area') ?: null,
                    'tipo_asignatura' => $tipo,
                    'es_oficial' => $this->normalizeBoolean($this->cell($row, $header, 'es_oficial'), true),
                    'activo' => $this->normalizeBoolean($this->cell($row, $header, 'activo'), true),
                    'observacion' => $this->cell($row, $header, 'observacion') ?: null,
                ];

                $asignatura = Asignatura::query()->where('codigo', $codigo)->first();
                if ($asignatura) {
                    $asignatura->update($payload);
                    $updated++;
                } else {
                    Asignatura::create($payload);
                    $created++;
                }
            }
        });

        $message = "Carga procesada. Creadas: {$created}. Actualizadas: {$updated}. Omitidas: {$skipped}.";
        if ($errors) {
            return redirect()->route('admin.asignaturas.index')->with('status', $message)->with('warning', implode(' | ', array_slice($errors, 0, 20)));
        }

        return redirect()->route('admin.asignaturas.index')->with('status', $message);
    }

    private function validatedData(Request $request, ?Asignatura $asignatura = null): array
    {
        $asignaturaId = $asignatura?->id;
        $tipoKeys = array_keys(Asignatura::tiposOptions());

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:180', Rule::unique('asignaturas', 'nombre')->ignore($asignaturaId)],
            'codigo' => ['required', 'string', 'max:80', Rule::unique('asignaturas', 'codigo')->ignore($asignaturaId)],
            'nivel_educativo' => ['nullable', 'string', 'max:80'],
            'area' => ['nullable', 'string', 'max:120'],
            'tipo_asignatura' => ['required', Rule::in($tipoKeys)],
            'es_oficial' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
            'observacion' => ['nullable', 'string', 'max:4000'],
        ]);

        $data['nivel_educativo'] = trim((string) ($data['nivel_educativo'] ?? '')) ?: null;
        $data['area'] = trim((string) ($data['area'] ?? '')) ?: null;
        $data['observacion'] = trim((string) ($data['observacion'] ?? '')) ?: null;
        $data['es_oficial'] = (bool) ($data['es_oficial'] ?? false);
        $data['activo'] = (bool) ($data['activo'] ?? false);

        return $data;
    }

    private function normalizeTipo(string $value): string
    {
        $text = $this->normalizeText($value);
        $map = [
            'OBLIGATORIA' => 'obligatoria',
            'PLAN COMUN ELECTIVO' => 'plan_comun_electivo',
            'PLAN COMUN FORMACION GENERAL ELECTIVO' => 'plan_comun_electivo',
            'PLAN DIFERENCIADO HC' => 'plan_diferenciado_hc',
            'PLAN DIFERENCIADO HUMANISTICO CIENTIFICO' => 'plan_diferenciado_hc',
            'PLAN DIFERENCIADO TP' => 'plan_diferenciado_tp',
            'PLAN DIFERENCIADO TECNICO PROFESIONAL' => 'plan_diferenciado_tp',
            'PLAN DIFERENCIADO ARTISTICO' => 'plan_diferenciado_artistico',
            'LIBRE DISPOSICION' => 'libre_disposicion',
            'PERSONALIZADA' => 'personalizada',
        ];
        return $map[$text] ?? trim($value);
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
        if ($sheetXml === false) return [];
        $xml = simplexml_load_string($sheetXml);
        if (! $xml) abort(422, 'No fue posible leer la hoja '.$sheetNumber.' del archivo Excel.');

        $rows = [];
        foreach ($xml->sheetData->row as $xmlRow) {
            $row = [];
            foreach ($xmlRow->c as $cell) {
                $ref = (string) $cell['r'];
                $colIndex = $this->excelColumnIndex($ref);
                $type = (string) $cell['t'];
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

    private function normalizeBoolean($value, bool $default = true): bool
    {
        if ($value === null || trim((string) $value) === '') return $default;
        $text = $this->normalizeText((string) $value);
        return in_array($text, ['1', 'SI', 'SÍ', 'TRUE', 'ACTIVO', 'ACTIVA'], true);
    }
}
