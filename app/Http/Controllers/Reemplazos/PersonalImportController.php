<?php

namespace App\Http\Controllers\Reemplazos;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PersonalImportController extends Controller
{
    private const CHUNK_SIZE = 200;
    private const UPSERT_BUFFER_SIZE = 500;

    public function __construct()
    {
        // Seguridad adicional: aunque la ruta esté protegida, aquí también queda asegurado.
        $this->middleware(['auth', 'ensure.role:admin']);
    }

    public function create(Request $request)
    {
        // La plantilla se descarga mediante la misma ruta ya existente del importador.
        // Esto evita depender de una ruta adicional que pueda perderse al consolidar routes/web.php.
        if ($request->boolean('descargar_plantilla')) {
            return $this->plantilla();
        }

        return view('reemplazos.personal.import');
    }

    public function plantilla()
    {
        $headers = [
            'rut',
            'nombre',
            'FECHA_NACIMIENTO',
            'Fecha_Ingreso',
            'Fecha_Termino',
            'tipocontrato',
            'FINANCIAMIENTO',
            'estatuto',
            'escalafon',
            'anio',
            'mes',
            'jornada',
            'Jornada_Basica',
            'Jornada_Media',
            'RBD',
            'Bienios',
            'Tramo',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Padron Reemplazos');

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column . '1', $header);
        }

        $sheet->fromArray([
            ['12345678-9', 'NOMBRE APELLIDO APELLIDO', '1980-01-01', '2020-03-01', null, 'CONTRATA', 'SEP', 'DOCENTE', 'DOCENTE AULA', 2026, 7, 44, 30, 14, 12345, 8, 'Avanzado'],
            ['11111111-1', 'NOMBRE ASISTENTE EDUCACION', '1975-05-10', '2019-03-01', null, 'CONTRATA', 'REGULAR', 'ASISTENTE EDUCACION', 'INSPECTOR', 2026, 7, 44, 0, 0, 12345, 4, null],
        ], null, 'A2');

        $headerRange = 'A1:Q1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);
        $sheet->getStyle('A1:Q3')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A2:Q3')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('C:C')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('D:D')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($headerRange);

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instrucciones');
        $instructions->fromArray([
            ['Plantilla padrón de reemplazos'],
            ['Usar la hoja "Padron Reemplazos" para la carga. No modificar los nombres de encabezados.'],
            ['La carga debe contener un solo periodo mensual: todas las filas con el mismo anio y mes.'],
            ['Bienios es obligatorio como encabezado y se guardará como número entero si viene informado.'],
            ['Tramo se procesa si viene informado; se recomienda completarlo para docentes. Para no docentes puede quedar vacío.'],
            ['Valores sugeridos para Tramo: Acceso, Inicial, Temprano, Avanzado, Experto I, Experto II, Sin tramo.'],
        ], null, 'A1');
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instructions->getColumnDimension('A')->setWidth(120);
        $instructions->getStyle('A1:A6')->getAlignment()->setWrapText(true);

        $spreadsheet->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'plantilla_padron_reemplazos_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmp);

        return response()->download($tmp, 'plantilla_padron_reemplazos.xlsx')->deleteFileAfterSend(true);
    }

    public function store(Request $request)
    {
        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls'],
        ], [
            'excel.required' => 'Debes seleccionar un archivo Excel.',
            'excel.mimes'    => 'El archivo debe ser .xlsx o .xls.',
        ]);

        $file = $request->file('excel');
        $originalName = $file?->getClientOriginalName() ?: 'padron-personal.xlsx';

        $disk = 'local';
        $dir  = 'imports/reemplazos';

        $storedPath = null;

        // Columnas requeridas (exactas)
        $requiredHeaders = [
            'rut',
            'nombre',
            'FECHA_NACIMIENTO',
            'Fecha_Ingreso',
            'Fecha_Termino',
            'tipocontrato',
            'FINANCIAMIENTO',
            'estatuto',
            'escalafon',
            'anio',
            'mes',
            'jornada',
            'Jornada_Basica',
            'Jornada_Media',
            'RBD',
            'Bienios',
        ];

        // Pre-carga de establecimientos por RBD (para resolver establecimiento_id)
        $establecimientosByRbd = Establecimiento::query()
            ->select('id', 'rbd')
            ->get()
            ->keyBy('rbd');

        try {
            if (!$file->isValid()) {
                return back()->withErrors(['excel' => 'Error al subir el archivo. Intenta nuevamente.']);
            }

            Storage::disk($disk)->makeDirectory($dir);

            $storedPath = $file->store($dir, $disk);

            if (!$storedPath || !Storage::disk($disk)->exists($storedPath)) {
                return back()->withErrors([
                    'excel' => 'No se pudo guardar el archivo en storage. Revisa permisos de storage/app y configuración del disk local.',
                ]);
            }

            $fullPath = Storage::disk($disk)->path($storedPath);
            $fullPath = realpath($fullPath) ?: $fullPath;

            $reader = IOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $worksheetInfo = $reader->listWorksheetInfo($fullPath);
            $firstSheetInfo = $worksheetInfo[0] ?? null;

            if (!$firstSheetInfo) {
                return back()->withErrors([
                    'excel' => 'No fue posible leer la hoja principal del Excel.',
                ]);
            }

            $highestColumn = $firstSheetInfo['lastColumnLetter'] ?? 'A';
            $highestRow = (int) ($firstSheetInfo['totalRows'] ?? 0);
            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            if ($highestRow < 1) {
                return back()->withErrors([
                    'excel' => 'El Excel no contiene filas para importar.',
                ]);
            }

            $headerSpreadsheet = null;
            $headerFilter = new PersonalImportReadFilter(1, 1);
            $reader->setReadFilter($headerFilter);
            $headerSpreadsheet = $reader->load($fullPath);
            $headerSheet = $headerSpreadsheet->getSheet(0);

            // Encabezados fila 1
            $headerRow = $headerSheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0] ?? [];
            $headerRow = array_map(function ($v) {
                if (is_string($v)) {
                    return trim($v);
                }
                if (is_null($v)) {
                    return '';
                }
                return trim((string) $v);
            }, $headerRow);

            $headerSpreadsheet->disconnectWorksheets();
            unset($headerSpreadsheet, $headerSheet);

            // Mapa header => índice de columna (1-based)
            $colMap = [];
            foreach ($headerRow as $idx0 => $header) {
                if ($header !== '') {
                    $colMap[$header] = $idx0 + 1;
                }
            }

            $missing = [];
            foreach ($requiredHeaders as $h) {
                if (!array_key_exists($h, $colMap)) {
                    $missing[] = $h;
                }
            }

            if (!empty($missing)) {
                return back()->withErrors([
                    'excel' => 'Faltan columnas requeridas en el Excel: ' . implode(', ', $missing),
                ]);
            }

            $tramoHeader = $this->findFirstHeader($colMap, [
                'Tramo',
                'TRAMO',
                'tramo',
                'Tramo Docente',
                'TRAMO DOCENTE',
                'TRAMO_DOCENTE',
                'tramo_docente',
            ]);

            $headersToRead = $requiredHeaders;
            if ($tramoHeader !== null) {
                $headersToRead[] = $tramoHeader;
            }

            $allowedColumns = array_values(array_unique(array_map(
                static fn (string $header): int => $colMap[$header],
                $headersToRead
            )));

            $now = now();

            $totalRows = 0;
            $skippedEmpty = 0;
            $skippedRbdNotFound = 0;
            $inserted = 0;
            $updated  = 0;

            $rbdNotFoundList = [];

            // Para asegurar "carga mensual" consistente dentro del mismo archivo:
            $fileAnio = null;
            $fileMes  = null;

            $buffer = [];
            $backfillBieniosByRut = [];

            for ($chunkStart = 2; $chunkStart <= $highestRow; $chunkStart += self::CHUNK_SIZE) {
                $chunkEnd = min($highestRow, $chunkStart + self::CHUNK_SIZE - 1);

                $chunkFilter = new PersonalImportReadFilter($chunkStart, $chunkEnd, $allowedColumns);
                $reader->setReadFilter($chunkFilter);
                $spreadsheet = $reader->load($fullPath);
                $sheet = $spreadsheet->getSheet(0);

                for ($row = $chunkStart; $row <= $chunkEnd; $row++) {
                    $totalRows++;

                    $rut  = $this->cellString($sheet, $colMap['rut'], $row);
                    $rbdV = $this->cellString($sheet, $colMap['RBD'], $row);

                    // Si no hay rut o RBD, omitimos
                    if ($rut === '' || $rbdV === '') {
                        $skippedEmpty++;
                        continue;
                    }

                    $rbd = (int) preg_replace('/\D+/', '', $rbdV);
                    if ($rbd <= 0) {
                        $skippedEmpty++;
                        continue;
                    }

                    $nombre = $this->cellString($sheet, $colMap['nombre'], $row);

                    $fechaNacimiento = $this->parseDateFlexible(
                        $this->cellRaw($sheet, $colMap['FECHA_NACIMIENTO'], $row)
                    );

                    $fechaIngreso = $this->parseDateFlexible(
                        $this->cellRaw($sheet, $colMap['Fecha_Ingreso'], $row)
                    );

                    $fechaTermino = $this->parseDateFlexible(
                        $this->cellRaw($sheet, $colMap['Fecha_Termino'], $row)
                    );

                    $tipocontrato   = $this->cellString($sheet, $colMap['tipocontrato'], $row);
                    $financiamiento = $this->cellString($sheet, $colMap['FINANCIAMIENTO'], $row);
                    $estatuto       = $this->cellString($sheet, $colMap['estatuto'], $row);
                    $escalafon      = $this->cellString($sheet, $colMap['escalafon'], $row);
                    $tramo          = $tramoHeader !== null
                        ? $this->normalizarTramo($this->cellString($sheet, $colMap[$tramoHeader], $row))
                        : null;

                    $anioRaw = $this->cellRaw($sheet, $colMap['anio'], $row);
                    $mesRaw  = $this->cellRaw($sheet, $colMap['mes'], $row);

                    $anio = is_numeric($anioRaw) ? (int) $anioRaw : (int) trim((string) $anioRaw);
                    $mes  = is_numeric($mesRaw)  ? (int) $mesRaw  : (int) trim((string) $mesRaw);

                    if ($anio <= 0 || $mes <= 0 || $mes > 12) {
                        $skippedEmpty++;
                        continue;
                    }

                    if ($fileAnio === null && $fileMes === null) {
                        $fileAnio = $anio;
                        $fileMes  = $mes;
                    } elseif ($anio !== $fileAnio || $mes !== $fileMes) {
                        $spreadsheet->disconnectWorksheets();
                        unset($spreadsheet, $sheet);

                        return back()->withErrors([
                            'excel' => "El Excel contiene más de un período. Encontré {$fileAnio}/{$fileMes} y también {$anio}/{$mes}. Debe ser una carga mensual única.",
                        ]);
                    }

                    $jornada = $this->cellNumeric($sheet, $colMap['jornada'], $row);
                    $jBasica = $this->cellNumeric($sheet, $colMap['Jornada_Basica'], $row);
                    $jMedia  = $this->cellNumeric($sheet, $colMap['Jornada_Media'], $row);
                    $bienios = $this->cellNumeric($sheet, $colMap['Bienios'], $row);
                    if ($bienios !== null) {
                        $backfillBieniosByRut[$rut] = (int) round($bienios);
                    }

                    $establecimiento = $establecimientosByRbd->get($rbd);
                    if (!$establecimiento) {
                        $skippedRbdNotFound++;
                        $rbdNotFoundList[$rbd] = true;
                        continue;
                    }

                    $hashInput = implode('|', [
                        mb_strtolower(trim($rut)),
                        $rbd,
                        $anio,
                        $mes,
                        $fechaIngreso ? $fechaIngreso->format('Y-m-d') : '',
                        mb_strtolower(trim($tipocontrato)),
                        mb_strtolower(trim($financiamiento)),
                        mb_strtolower(trim($estatuto)),
                        mb_strtolower(trim($escalafon)),
                        (string) ($jornada ?? ''),
                        (string) ($jBasica ?? ''),
                        (string) ($jMedia ?? ''),
                    ]);

                    $rowHash = hash('sha256', $hashInput);

                    $buffer[] = [
                        'row_hash'           => $rowHash,
                        'establecimiento_id' => $establecimiento->id,
                        'rut'                => $rut,
                        'nombre'             => $nombre,
                        'fecha_nacimiento'   => $fechaNacimiento?->format('Y-m-d'),
                        'fecha_ingreso'      => $fechaIngreso?->format('Y-m-d'),
                        'fecha_termino'      => $fechaTermino?->format('Y-m-d'),
                        'tipocontrato'       => $tipocontrato,
                        'financiamiento'     => $financiamiento,
                        'estatuto'           => $estatuto,
                        'escalafon'          => $escalafon,
                        'anio'               => $anio,
                        'mes'                => $mes,
                        'jornada'            => $jornada,
                        'jornada_basica'     => $jBasica,
                        'jornada_media'      => $jMedia,
                        'rbd'                => $rbd,
                        'bienios'            => $bienios,
                        'tramo'              => $tramo,
                        'source_filename'    => $originalName,
                        'created_by'         => $request->user()?->id,
                        'imported_at'        => $now,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];

                    if (count($buffer) >= self::UPSERT_BUFFER_SIZE) {
                        [$i, $u] = $this->flushUpsert($buffer);
                        $inserted += $i;
                        $updated  += $u;
                        $buffer = [];
                    }
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet, $sheet, $chunkFilter);
                gc_collect_cycles();
            }

            if (!empty($buffer)) {
                [$i, $u] = $this->flushUpsert($buffer);
                $inserted += $i;
                $updated  += $u;
            }

            $backfilledBienios = $this->backfillBieniosForRuts($backfillBieniosByRut);

            $rbdNotFoundList = array_keys($rbdNotFoundList);
            sort($rbdNotFoundList);

            $summary = [
                'archivo' => $originalName,
                'periodo' => $fileAnio && $fileMes ? str_pad((string) $fileMes, 2, '0', STR_PAD_LEFT) . '/' . $fileAnio : null,
                'leidas' => $totalRows,
                'candidatas' => $inserted + $updated,
                'insertadas' => $inserted,
                'actualizadas' => $updated,
                'omitidas_vacias' => $skippedEmpty,
                'omitidas_sin_estab' => $skippedRbdNotFound,
                'omitidas_duplicadas' => 0,
                'rbds_faltantes' => $rbdNotFoundList,
                'bienios_backfill' => $backfilledBienios,
            ];

            return redirect()
                ->route('reemplazos.personal.import')
                ->with('status', 'Carga finalizada correctamente.')
                ->with('import_stats', [
                    'total_rows'          => $totalRows,
                    'inserted'            => $inserted,
                    'updated'             => $updated,
                    'skipped_empty'       => $skippedEmpty,
                    'skipped_rbd_missing' => $skippedRbdNotFound,
                    'rbd_missing_list'    => $rbdNotFoundList,
                    'periodo'             => $fileAnio && $fileMes ? "{$fileMes}/{$fileAnio}" : null,
                    'backfilled_bienios'  => $backfilledBienios,
                ])
                ->with('import_summary', $summary);
        } catch (\Throwable $e) {
            Log::error('Error import reemplazos personal', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'excel' => 'Ocurrió un error al procesar el Excel: ' . $e->getMessage(),
            ]);
        } finally {
            if (!empty($storedPath)) {
                try {
                    Storage::disk($disk)->delete($storedPath);
                } catch (\Throwable $e) {
                    // no interrumpir flujo por fallas de limpieza
                }
            }
        }
    }

    private function backfillBieniosForRuts(array $bieniosByRut): int
    {
        if (empty($bieniosByRut)) {
            return 0;
        }

        $updated = 0;

        foreach (array_chunk($bieniosByRut, 200, true) as $chunk) {
            foreach ($chunk as $rut => $bienios) {
                if ($rut === '' || $bienios === null) {
                    continue;
                }

                $updated += DB::table('reemplazos_personal')
                    ->where('rut', $rut)
                    ->whereNull('bienios')
                    ->update(['bienios' => (int) $bienios]);
            }
        }

        return $updated;
    }

    /**
     * Upsert idempotente por row_hash.
     * Retorna [inserted, updated] (estimado consultando existentes antes del upsert).
     */
    private function flushUpsert(array $rows): array
    {
        $hashes = array_column($rows, 'row_hash');

        $existing = DB::table('reemplazos_personal')
            ->whereIn('row_hash', $hashes)
            ->pluck('row_hash')
            ->all();

        $existingSet = array_fill_keys($existing, true);
        $existingCount = 0;

        foreach ($hashes as $h) {
            if (isset($existingSet[$h])) {
                $existingCount++;
            }
        }

        $updated  = $existingCount;
        $inserted = count($rows) - $existingCount;

        DB::table('reemplazos_personal')->upsert(
            $rows,
            ['row_hash'],
            [
                'establecimiento_id',
                'rut',
                'nombre',
                'fecha_nacimiento',
                'fecha_ingreso',
                'fecha_termino',
                'tipocontrato',
                'financiamiento',
                'estatuto',
                'escalafon',
                'anio',
                'mes',
                'jornada',
                'jornada_basica',
                'jornada_media',
                'rbd',
                'bienios',
                'tramo',
                'source_filename',
                'imported_at',
                'updated_at',
            ]
        );

        return [$inserted, $updated];
    }

    private function findFirstHeader(array $colMap, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $colMap)) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizarTramo(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtoupper($value, 'UTF-8');
    }

    private function cellRaw($sheet, int $col, int $row)
    {
        $coord = Coordinate::stringFromColumnIndex($col) . $row;
        return $sheet->getCell($coord)->getValue();
    }

    private function cellString($sheet, int $col, int $row): string
    {
        $v = $this->cellRaw($sheet, $col, $row);

        if (is_null($v)) {
            return '';
        }
        if (is_string($v)) {
            return trim($v);
        }

        if (is_numeric($v)) {
            if ((int) $v == $v) {
                return (string) ((int) $v);
            }
            return trim((string) $v);
        }

        return trim((string) $v);
    }

    private function cellNumeric($sheet, int $col, int $row): ?float
    {
        $v = $this->cellRaw($sheet, $col, $row);

        if (is_null($v) || $v === '') {
            return null;
        }

        if (is_numeric($v)) {
            return (float) $v;
        }

        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }

        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * Parse flexible de fecha: soporta:
     * - Excel serial number (numérico)
     * - string "dd/mm/yyyy" (a veces con espacios)
     * - string "yyyy-mm-dd"
     */
    private function parseDateFlexible($value): ?Carbon
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);
                return Carbon::instance($dt)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $s)) {
                return Carbon::createFromFormat('d/m/Y', $s)->startOfDay();
            }
        } catch (\Throwable $e) {
            // sigue
        }

        try {
            if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $s)) {
                return Carbon::createFromFormat('Y-m-d', $s)->startOfDay();
            }
        } catch (\Throwable $e) {
            // sigue
        }

        try {
            return Carbon::parse($s)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

class PersonalImportReadFilter implements IReadFilter
{
    /** @var array<int, true> */
    private array $allowedColumnMap;

    public function __construct(
        private int $startRow,
        private int $endRow,
        array $allowedColumns = []
    ) {
        $this->allowedColumnMap = [];

        foreach ($allowedColumns as $columnIndex) {
            $this->allowedColumnMap[(int) $columnIndex] = true;
        }
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row !== 1 && ($row < $this->startRow || $row > $this->endRow)) {
            return false;
        }

        if (empty($this->allowedColumnMap)) {
            return true;
        }

        $columnIndex = Coordinate::columnIndexFromString($columnAddress);

        return isset($this->allowedColumnMap[$columnIndex]);
    }
}
