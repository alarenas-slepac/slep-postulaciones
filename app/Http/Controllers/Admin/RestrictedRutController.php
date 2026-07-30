<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestrictedRut;
use App\Models\RestrictedRutCourtRecord;
use App\Models\RestrictedRutManualRecord;
use App\Models\User;
use App\Services\RestrictedRutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class RestrictedRutController extends Controller
{
    public function __construct(private readonly RestrictedRutService $service)
    {
        $this->middleware(['auth', 'ensure.role:admin|coordinador_gdp|funcionario_slep']);
        $this->middleware(['ensure.role:admin|coordinador_gdp'])
            ->except(['index', 'show']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $origin = trim((string) $request->query('origin', ''));
        $status = trim((string) $request->query('status', ''));

        $today = now()->toDateString();

        $items = RestrictedRut::query()
            ->with(['courtRecord', 'manualRecord'])
            ->when($search !== '', function ($query) use ($search) {
                $rut = $this->service->normalizeRut($search);
                $query->where(function ($inner) use ($search, $rut) {
                    if ($rut !== '') {
                        $inner->where('rut_normalized', 'like', "%{$rut}%");
                    }
                    $inner->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhereHas('courtRecord', function ($court) use ($search) {
                            $court->where('nombre', 'like', "%{$search}%")
                                ->orWhere('juzgado_origen', 'like', "%{$search}%")
                                ->orWhere('rit', 'like', "%{$search}%");
                        })
                        ->orWhereHas('manualRecord', function ($manual) use ($search) {
                            $manual->where('comentario', 'like', "%{$search}%");
                        });
                });
            })
            ->when($origin === 'court', fn($q) => $q->whereHas('courtRecord'))
            ->when($origin === 'manual', fn($q) => $q->whereHas('manualRecord'))
            ->when($origin === 'both', fn($q) => $q->whereHas('courtRecord')->whereHas('manualRecord'))
            ->when($status === 'blocked', function ($query) use ($today) {
                $query->where(function ($inner) use ($today) {
                    $inner->whereHas('courtRecord', fn($court) => $court->where('activa', true))
                        ->orWhereHas('manualRecord', fn($manual) => $manual->where('activa', true)->whereDate('fecha_inicio_prohibicion', '<=', $today)->whereDate('fecha_termino_prohibicion', '>=', $today));
                });
            })
            ->when($status === 'unblocked', function ($query) use ($today) {
                $query->whereDoesntHave('courtRecord', fn($court) => $court->where('activa', true))
                    ->whereDoesntHave('manualRecord', fn($manual) => $manual->where('activa', true)->whereDate('fecha_inicio_prohibicion', '<=', $today)->whereDate('fecha_termino_prohibicion', '>=', $today));
            })
            ->when($status === 'court', fn($q) => $q->whereHas('courtRecord', fn($court) => $court->where('activa', true)))
            ->when($status === 'manual', fn($q) => $q->whereHas('manualRecord', fn($manual) => $manual->where('activa', true)->whereDate('fecha_inicio_prohibicion', '<=', $today)->whereDate('fecha_termino_prohibicion', '>=', $today)))
            ->orderBy('display_name')
            ->orderBy('rut_normalized')
            ->paginate(20)
            ->withQueryString();

        return view('admin.restricted-ruts.index', compact('items', 'search', 'origin', 'status'));
    }

    public function show(RestrictedRut $restrictedRut): View
    {
        $restrictedRut->load(['courtRecord', 'manualRecord.creator', 'manualRecord.updater']);
        $flags = $this->service->currentStatus($restrictedRut);
        $linkedUser = User::query()->where('rut', $restrictedRut->rut_normalized)->first();

        return view('admin.restricted-ruts.show', compact('restrictedRut', 'flags', 'linkedUser'));
    }

    public function importForm(): View
    {
        return view('admin.restricted-ruts.import');
    }

    public function downloadTemplate()
    {
        $filename = 'plantilla-restricciones-tribunal.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=' . $filename,
        ];

        $columns = ['NOMBRE', 'RUN', 'JUZGADO ORIGEN', 'RIT', 'FECHA FALLO', 'INHABILIDAD'];
        $example = ['NOMBRE APELLIDO', '12345678-9', 'JUZGADO DE LETRAS', 'RIT 123-2025', '2025-01-15', 'Perpetua'];

        $callback = function () use ($columns, $example) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $columns);
            fputcsv($handle, $example);
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    public function importStore(Request $request): RedirectResponse
    {
        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ], [
            'excel.required' => 'Debes seleccionar un archivo.',
            'excel.mimes' => 'El archivo debe ser .xlsx, .xls o .csv.',
        ]);

        $file = $request->file('excel');
        $disk = 'local';
        $dir = 'imports/restricciones-tribunal';
        Storage::disk($disk)->makeDirectory($dir);
        $storedPath = $file->store($dir, $disk);
        $fullPath = Storage::disk($disk)->path($storedPath);

        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getSheet(0);
        $highestColumn = $sheet->getHighestDataColumn();
        $highestRow = $sheet->getHighestDataRow();
        $headerRow = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0] ?? [];

        $colMap = [];
        foreach ($headerRow as $index => $value) {
            $header = $this->normalizeHeader((string) $value);
            if ($header !== '') {
                $colMap[$header] = $index + 1;
            }
        }

        $required = ['NOMBRE', 'RUN', 'JUZGADO ORIGEN', 'RIT', 'FECHA FALLO', 'INHABILIDAD'];
        $missing = array_values(array_filter($required, fn($h) => !isset($colMap[$h])));
        if (!empty($missing)) {
            return back()->withErrors([
                'excel' => 'Faltan columnas requeridas: ' . implode(', ', $missing),
            ]);
        }

        $processed = 0;
        $inserted = 0;
        $updated = 0;
        $errors = [];
        $sourceName = $file->getClientOriginalName();

        for ($row = 2; $row <= $highestRow; $row++) {
            $nombre = $this->cellString($sheet, $colMap['NOMBRE'], $row);
            $runOriginal = $this->cellString($sheet, $colMap['RUN'], $row);
            $rut = $this->service->normalizeRut($runOriginal);
            $juzgado = $this->cellString($sheet, $colMap['JUZGADO ORIGEN'], $row);
            $rit = $this->cellString($sheet, $colMap['RIT'], $row);
            $fechaFallo = $this->parseDateFlexible($sheet->getCell(Coordinate::stringFromColumnIndex($colMap['FECHA FALLO']) . $row)->getValue());
            $inhabilidad = $this->cellString($sheet, $colMap['INHABILIDAD'], $row);

            if ($rut === '' && $nombre === '' && $juzgado === '' && $rit === '' && $inhabilidad === '') {
                continue;
            }

            $processed++;

            if ($rut === '') {
                $errors[] = "Fila {$row}: RUN vacío o inválido.";
                continue;
            }

            DB::transaction(function () use ($rut, $nombre, $runOriginal, $juzgado, $rit, $fechaFallo, $inhabilidad, $sourceName, &$inserted, &$updated) {
                $restricted = RestrictedRut::firstOrCreate(
                    ['rut_normalized' => $rut],
                    ['display_name' => $nombre ?: null]
                );

                if ($nombre && !$restricted->display_name) {
                    $restricted->display_name = $nombre;
                    $restricted->save();
                }

                $record = RestrictedRutCourtRecord::where('restricted_rut_id', $restricted->id)->first();
                $payload = [
                    'nombre' => $nombre ?: ($record?->nombre),
                    'run_original' => $runOriginal ?: ($record?->run_original),
                    'juzgado_origen' => $juzgado,
                    'rit' => $rit,
                    'fecha_fallo' => $fechaFallo?->format('Y-m-d'),
                    'inhabilidad_texto' => $inhabilidad,
                    'activa' => true,
                    'archivo_origen' => $sourceName,
                ];

                if ($record) {
                    $record->fill($payload)->save();
                    $updated++;
                } else {
                    RestrictedRutCourtRecord::create($payload + ['restricted_rut_id' => $restricted->id]);
                    $inserted++;
                }
            });
        }

        return redirect()
            ->route('admin.restricted-ruts.import')
            ->with('status', "Importación completada. Procesadas: {$processed}. Insertadas: {$inserted}. Actualizadas: {$updated}.")
            ->with('import_errors', $errors);
    }

    public function manualCreate(): View
    {
        return view('admin.restricted-ruts.manual-create');
    }

    public function manualStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rut' => ['required', 'string', 'max:20'],
            'fecha_inicio_prohibicion' => ['required', 'date'],
            'fecha_termino_prohibicion' => ['required', 'date', 'after_or_equal:fecha_inicio_prohibicion'],
            'comentario' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'rut' => 'RUT',
            'fecha_inicio_prohibicion' => 'fecha de inicio',
            'fecha_termino_prohibicion' => 'fecha de término',
        ]);

        $rut = $this->service->normalizeRut($data['rut']);
        if ($rut === '') {
            return back()->withErrors(['rut' => 'Debes ingresar un RUT válido.'])->withInput();
        }

        $user = User::query()->where('rut', $rut)->first();

        $restricted = RestrictedRut::firstOrCreate(
            ['rut_normalized' => $rut],
            ['display_name' => $user?->nombre_completo ?: null]
        );

        if (!$restricted->display_name && $user?->nombre_completo) {
            $restricted->display_name = $user->nombre_completo;
            $restricted->save();
        }

        $manual = RestrictedRutManualRecord::firstOrNew(['restricted_rut_id' => $restricted->id]);
        $manual->fill([
            'fecha_inicio_prohibicion' => $data['fecha_inicio_prohibicion'],
            'fecha_termino_prohibicion' => $data['fecha_termino_prohibicion'],
            'comentario' => $data['comentario'] ?? null,
            'activa' => true,
            'updated_by' => $request->user()->id,
        ]);
        if (!$manual->exists) {
            $manual->created_by = $request->user()->id;
        }
        $manual->save();

        return redirect()
            ->route('admin.restricted-ruts.show', $restricted)
            ->with('status', 'Bloqueo manual guardado correctamente.');
    }

    public function manualEdit(RestrictedRutManualRecord $manualRecord): View
    {
        $manualRecord->load('restrictedRut');
        return view('admin.restricted-ruts.manual-edit', compact('manualRecord'));
    }

    public function manualUpdate(Request $request, RestrictedRutManualRecord $manualRecord): RedirectResponse
    {
        $data = $request->validate([
            'fecha_inicio_prohibicion' => ['required', 'date'],
            'fecha_termino_prohibicion' => ['required', 'date', 'after_or_equal:fecha_inicio_prohibicion'],
            'comentario' => ['nullable', 'string', 'max:5000'],
        ]);

        $manualRecord->fill($data + ['updated_by' => $request->user()->id])->save();

        return redirect()
            ->route('admin.restricted-ruts.show', $manualRecord->restricted_rut_id)
            ->with('status', 'Bloqueo manual actualizado correctamente.');
    }

    public function manualToggle(RestrictedRutManualRecord $manualRecord): RedirectResponse
    {
        $manualRecord->activa = !$manualRecord->activa;
        $manualRecord->save();

        return back()->with('status', 'Estado del bloqueo manual actualizado.');
    }

    public function courtToggle(RestrictedRutCourtRecord $courtRecord): RedirectResponse
    {
        $courtRecord->activa = !$courtRecord->activa;
        $courtRecord->save();

        return back()->with('status', 'Estado del registro judicial actualizado.');
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', str_replace(["\n", "\r", "\t"], ' ', $value)));
        return mb_strtoupper($value, 'UTF-8');
    }

    private function cellString($sheet, int $col, int $row): string
    {
        $cell = Coordinate::stringFromColumnIndex($col) . $row;
        $value = $sheet->getCell($cell)->getFormattedValue();
        return trim((string) $value);
    }

    private function parseDateFlexible(mixed $value): ?\Carbon\Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return \Carbon\Carbon::instance(ExcelDate::excelToDateTimeObject($value));
            } catch (\Throwable) {
            }
        }

        $value = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
            }
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
