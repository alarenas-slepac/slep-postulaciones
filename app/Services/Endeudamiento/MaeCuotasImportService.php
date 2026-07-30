<?php

namespace App\Services\Endeudamiento;

use App\Models\MaeCarga;
use App\Models\MaeCuotasImportacion;
use App\Support\MaeChunkReadFilter;
use App\Support\MaeColumnNormalizer;
use App\Support\RutChile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MaeCuotasImportService
{
    public function availableDiscounts(MaeCarga $carga): Collection
    {
        return DB::table('mae_registro_descuentos as d')
            ->join('mae_registros as r', 'r.id', '=', 'd.mae_registro_id')
            ->where('r.mae_carga_id', $carga->id)
            ->where('d.es_aporte_patronal', false)
            ->where('d.valor', '>', 0)
            ->groupBy('d.columna_normalizada')
            ->orderByRaw('MIN(d.columna_origen)')
            ->get([
                'd.columna_normalizada',
                DB::raw('MIN(d.columna_origen) as columna_origen'),
                DB::raw('COUNT(DISTINCT d.mae_registro_id) as total_registros'),
                DB::raw('SUM(CASE WHEN d.cuota_actual IS NOT NULL THEN 1 ELSE 0 END) as total_con_cuota'),
            ]);
    }

    public function import(
        MaeCarga $carga,
        string $columnaNormalizada,
        UploadedFile $file,
        int $userId
    ): MaeCuotasImportacion {
        if (!in_array($carga->estado, ['procesado', 'procesado_con_observaciones'], true)) {
            throw ValidationException::withMessages([
                'mae_carga_id' => 'La carga MAE debe estar procesada antes de complementar cuotas.',
            ]);
        }

        $discount = $this->availableDiscounts($carga)
            ->first(fn ($item) => (string) $item->columna_normalizada === $columnaNormalizada);

        if (!$discount) {
            throw ValidationException::withMessages([
                'columna_normalizada' => 'El descuento seleccionado no existe en la carga MAE indicada.',
            ]);
        }

        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'excel' => 'No fue posible recibir la nómina de cuotas.',
            ]);
        }

        $directory = sprintf(
            'imports/endeudamiento/cuotas/%04d-%02d/%s/v%d',
            (int) $carga->anio,
            (int) $carga->mes,
            preg_replace('/[^a-z0-9-]+/i', '-', strtolower((string) $carga->dominio)),
            (int) $carga->version
        );
        Storage::disk('local')->makeDirectory($directory);

        $storedPath = $file->storeAs(
            $directory,
            now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $file->getClientOriginalName()),
            'local'
        );

        if (!$storedPath || !Storage::disk('local')->exists($storedPath)) {
            throw ValidationException::withMessages([
                'excel' => 'No fue posible guardar la nómina de cuotas en storage.',
            ]);
        }

        $importacion = MaeCuotasImportacion::query()->create([
            'mae_carga_id' => $carga->id,
            'columna_origen' => (string) $discount->columna_origen,
            'columna_normalizada' => $columnaNormalizada,
            'nombre_archivo' => $file->getClientOriginalName(),
            'ruta_archivo' => $storedPath,
            'estado' => 'procesando',
            'created_by' => $userId,
        ]);

        try {
            return $this->process($importacion, $userId);
        } catch (ValidationException $e) {
            $importacion->update([
                'estado' => 'fallido',
                'resumen_json' => ['error' => collect($e->errors())->flatten()->first()],
                'procesado_at' => now(),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $importacion->update([
                'estado' => 'fallido',
                'resumen_json' => ['error' => $e->getMessage()],
                'procesado_at' => now(),
            ]);
            throw $e;
        }
    }

    private function process(MaeCuotasImportacion $importacion, int $userId): MaeCuotasImportacion
    {
        $path = Storage::disk('local')->path($importacion->ruta_archivo);
        if (!is_file($path)) {
            throw ValidationException::withMessages([
                'excel' => 'No se encontró el archivo guardado para procesar las cuotas.',
            ]);
        }

        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $worksheetInfo = $reader->listWorksheetInfo($path);
        if (empty($worksheetInfo)) {
            throw ValidationException::withMessages([
                'excel' => 'El archivo no contiene una hoja legible.',
            ]);
        }

        $sheetName = (string) ($worksheetInfo[0]['worksheetName'] ?? 'Worksheet');
        $highestRow = (int) ($worksheetInfo[0]['totalRows'] ?? 0);
        $highestColumn = (string) ($worksheetInfo[0]['lastColumnLetter'] ?? 'A');
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        $reader->setLoadSheetsOnly([$sheetName]);
        $headerFilter = new MaeChunkReadFilter(1, 1);
        $reader->setReadFilter($headerFilter);
        $headerBook = $reader->load($path);
        $headerSheet = $headerBook->getActiveSheet();
        $headers = $headerSheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0] ?? [];
        $headers = array_pad($headers, $highestColumnIndex, '');
        $normalizedHeaders = array_map(
            static fn ($header) => MaeColumnNormalizer::normalizeHeader((string) $header),
            $headers
        );
        $headerBook->disconnectWorksheets();
        unset($headerBook, $headerSheet);

        $idxRut = $this->firstIndex($normalizedHeaders, ['RUT', 'RUN']);
        $idxCuotaActual = $this->firstIndex($normalizedHeaders, [
            'CUOTA ACTUAL', 'CUOTA', 'N CUOTA', 'NRO CUOTA', 'NUMERO CUOTA', 'CUOTA MES', 'CUOTA DEL MES',
        ]);
        $idxTotalCuotas = $this->firstIndex($normalizedHeaders, [
            'TOTAL CUOTAS', 'TOTAL DE CUOTAS', 'N TOTAL CUOTAS', 'NUMERO TOTAL CUOTAS', 'CANTIDAD CUOTAS',
        ]);
        $idxObservacion = $this->firstIndex($normalizedHeaders, ['OBSERVACION', 'OBSERVACIONES']);

        $missing = [];
        if ($idxRut === null) {
            $missing[] = 'RUT';
        }
        if ($idxCuotaActual === null) {
            $missing[] = 'CUOTA_ACTUAL';
        }
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'excel' => 'Faltan columnas obligatorias: ' . implode(', ', $missing) . '.',
            ]);
        }

        [$rutLookup, $discountLookup] = $this->buildLookups($importacion);

        $seenRutKeys = [];
        $seenDiscountIds = [];
        $updates = [];
        $details = [];
        $summary = [
            'filas_procesadas' => 0,
            'asociadas' => 0,
            'rut_no_encontrado' => 0,
            'sin_descuento' => 0,
            'duplicadas' => 0,
            'ambiguas' => 0,
            'datos_invalidos' => 0,
        ];

        $chunkSize = 500;
        $chunkFilter = new MaeChunkReadFilter(2, $chunkSize);
        $reader->setReadFilter($chunkFilter);

        for ($startRow = 2; $startRow <= $highestRow; $startRow += $chunkSize) {
            $chunkFilter->setRows($startRow, $chunkSize);
            $book = $reader->load($path);
            $sheet = $book->getActiveSheet();
            $endRow = min($highestRow, $startRow + $chunkSize - 1);
            $rows = $sheet->rangeToArray(
                'A' . $startRow . ':' . $highestColumn . $endRow,
                null,
                true,
                false
            );

            foreach ($rows as $offset => $row) {
                $rowNumber = $startRow + $offset;
                $rutRaw = $this->stringValue($row[$idxRut] ?? null);
                $cuotaRaw = $row[$idxCuotaActual] ?? null;
                $totalRaw = $idxTotalCuotas !== null ? ($row[$idxTotalCuotas] ?? null) : null;
                $observacion = $idxObservacion !== null
                    ? mb_substr($this->stringValue($row[$idxObservacion] ?? null), 0, 2000)
                    : '';

                if ($rutRaw === '' && $this->stringValue($cuotaRaw) === '' && $this->stringValue($totalRaw) === '') {
                    continue;
                }

                $summary['filas_procesadas']++;
                $cuotaActual = $this->nonNegativeInteger($cuotaRaw);
                $totalCuotas = $this->nullableNonNegativeInteger($totalRaw);
                $totalCuotasInformado = $this->stringValue($totalRaw) !== '';
                $rutKeys = $this->rutKeys($rutRaw);
                $canonicalRutKey = $rutKeys[0] ?? '';

                if ($rutRaw === '' || $canonicalRutKey === '' || $cuotaActual === null || ($totalCuotasInformado && $totalCuotas === null)) {
                    $summary['datos_invalidos']++;
                    $details[] = $this->detailRow(
                        $importacion->id,
                        $rowNumber,
                        $rutRaw,
                        $cuotaActual,
                        $totalCuotas,
                        $observacion,
                        'error',
                        'RUT o datos de cuota inválidos. Se admite 0 o texto equivalente para indicar una cuota indefinida.'
                    );
                    continue;
                }

                if ($cuotaActual > 0 && $totalCuotas !== null && $totalCuotas > 0 && $cuotaActual > $totalCuotas) {
                    $summary['datos_invalidos']++;
                    $details[] = $this->detailRow(
                        $importacion->id,
                        $rowNumber,
                        $rutRaw,
                        $cuotaActual,
                        $totalCuotas,
                        $observacion,
                        'error',
                        'La cuota actual no puede ser mayor que el total de cuotas cuando ambos valores son mayores que cero.'
                    );
                    continue;
                }

                if (isset($seenRutKeys[$canonicalRutKey])) {
                    $summary['duplicadas']++;
                    $details[] = $this->detailRow(
                        $importacion->id,
                        $rowNumber,
                        $rutRaw,
                        $cuotaActual,
                        $totalCuotas,
                        $observacion,
                        'error',
                        'RUT repetido en la nómina cargada.'
                    );
                    continue;
                }
                $seenRutKeys[$canonicalRutKey] = true;

                $registroIds = [];
                foreach ($rutKeys as $key) {
                    foreach ($rutLookup[$key] ?? [] as $registroId) {
                        $registroIds[$registroId] = true;
                    }
                }
                $registroIds = array_keys($registroIds);

                if ($registroIds === []) {
                    $summary['rut_no_encontrado']++;
                    $details[] = $this->detailRow(
                        $importacion->id,
                        $rowNumber,
                        $rutRaw,
                        $cuotaActual,
                        $totalCuotas,
                        $observacion,
                        'error',
                        'El RUT no existe en la carga MAE seleccionada.'
                    );
                    continue;
                }

                if (count($registroIds) > 1) {
                    $summary['ambiguas']++;
                    $details[] = $this->detailRow(
                        $importacion->id,
                        $rowNumber,
                        $rutRaw,
                        $cuotaActual,
                        $totalCuotas,
                        $observacion,
                        'error',
                        'El RUT coincide con más de un registro MAE; requiere revisión manual.'
                    );
                    continue;
                }

                $registroId = (int) $registroIds[0];
                $discountIds = array_values(array_unique($discountLookup[$registroId] ?? []));
                if ($discountIds === []) {
                    $summary['sin_descuento']++;
                    $details[] = $this->detailRow(
                        $importacion->id,
                        $rowNumber,
                        $rutRaw,
                        $cuotaActual,
                        $totalCuotas,
                        $observacion,
                        'error',
                        'El funcionario no tiene el descuento seleccionado en esta carga MAE.'
                    );
                    continue;
                }

                if (count($discountIds) > 1) {
                    $summary['ambiguas']++;
                    $details[] = $this->detailRow(
                        $importacion->id,
                        $rowNumber,
                        $rutRaw,
                        $cuotaActual,
                        $totalCuotas,
                        $observacion,
                        'error',
                        'El funcionario tiene más de una fila para el mismo descuento; requiere revisión manual.'
                    );
                    continue;
                }

                $discountId = (int) $discountIds[0];
                if (isset($seenDiscountIds[$discountId])) {
                    $summary['duplicadas']++;
                    $details[] = $this->detailRow(
                        $importacion->id,
                        $rowNumber,
                        $rutRaw,
                        $cuotaActual,
                        $totalCuotas,
                        $observacion,
                        'error',
                        'El descuento ya fue asociado por otra fila de esta nómina.'
                    );
                    continue;
                }
                $seenDiscountIds[$discountId] = true;

                $updates[$discountId] = [
                    'cuota_actual' => $cuotaActual,
                    'total_cuotas' => $totalCuotas,
                    'cuota_observacion' => $observacion !== '' ? $observacion : null,
                    'cuota_importacion_id' => $importacion->id,
                    'cuota_updated_by' => $userId,
                    'cuota_updated_at' => now(),
                    'updated_at' => now(),
                ];
                $summary['asociadas']++;
                $details[] = $this->detailRow(
                    $importacion->id,
                    $rowNumber,
                    $rutRaw,
                    $cuotaActual,
                    $totalCuotas,
                    $observacion,
                    'asociada',
                    ($cuotaActual === 0 || $totalCuotas === 0)
                        ? 'Descuento indefinido asociado correctamente; continuará participando en el cálculo.'
                        : 'Cuota asociada correctamente.',
                    $discountId
                );
            }

            $book->disconnectWorksheets();
            unset($book, $sheet, $rows);
        }

        if ($summary['filas_procesadas'] === 0) {
            throw ValidationException::withMessages([
                'excel' => 'La nómina no contiene filas de datos para procesar.',
            ]);
        }

        DB::transaction(function () use ($updates, $details) {
            foreach ($updates as $discountId => $payload) {
                DB::table('mae_registro_descuentos')->where('id', $discountId)->update($payload);
            }

            foreach (array_chunk($details, 500) as $chunk) {
                DB::table('mae_cuotas_importacion_detalles')->insert($chunk);
            }
        });

        $totalErrors = $summary['filas_procesadas'] - $summary['asociadas'];
        $importacion->update([
            'estado' => $totalErrors > 0 ? 'procesado_con_errores' : 'procesado',
            'total_filas' => $summary['filas_procesadas'],
            'total_asociadas' => $summary['asociadas'],
            'total_errores' => $totalErrors,
            'resumen_json' => $summary,
            'procesado_at' => now(),
        ]);

        return $importacion->fresh(['carga', 'creadoPor']);
    }

    private function buildLookups(MaeCuotasImportacion $importacion): array
    {
        $rutLookup = [];
        $registros = DB::table('mae_registros')
            ->where('mae_carga_id', $importacion->mae_carga_id)
            ->get(['id', 'rut']);

        foreach ($registros as $registro) {
            foreach ($this->rutKeys((string) $registro->rut) as $key) {
                $rutLookup[$key] ??= [];
                $rutLookup[$key][] = (int) $registro->id;
            }
        }

        $discountLookup = [];
        $discounts = DB::table('mae_registro_descuentos as d')
            ->join('mae_registros as r', 'r.id', '=', 'd.mae_registro_id')
            ->where('r.mae_carga_id', $importacion->mae_carga_id)
            ->where('d.columna_normalizada', $importacion->columna_normalizada)
            ->where('d.es_aporte_patronal', false)
            ->get(['d.id', 'd.mae_registro_id']);

        foreach ($discounts as $discount) {
            $discountLookup[(int) $discount->mae_registro_id] ??= [];
            $discountLookup[(int) $discount->mae_registro_id][] = (int) $discount->id;
        }

        return [$rutLookup, $discountLookup];
    }

    private function rutKeys(string $rut): array
    {
        $raw = strtoupper(trim($rut));
        $clean = preg_replace('/[^0-9K]/', '', $raw) ?? '';
        $keys = [];

        $add = static function (array &$target, string $value): void {
            if ($value !== '' && !in_array($value, $target, true)) {
                $target[] = $value;
            }
        };

        $normalized = RutChile::normalize($rut);
        if ($normalized) {
            $add($keys, preg_replace('/[^0-9K]/', '', strtoupper((string) ($normalized['rut'] ?? ''))) ?? '');
            $add($keys, (string) ($normalized['rut_body'] ?? ''));
        }

        $add($keys, $clean);

        if ($clean !== '' && ctype_digit($clean) && strlen($clean) >= 7 && strlen($clean) <= 8) {
            $body = ltrim($clean, '0');
            $add($keys, $body);
            $add($keys, $body . RutChile::dv((int) $body));
        }

        if (strlen($clean) >= 8) {
            $add($keys, substr($clean, 0, -1));
        }

        return $keys;
    }

    private function firstIndex(array $headers, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $index = array_search($alias, $headers, true);
            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        $value = $this->stringValue($value);
        if ($value === '') {
            return null;
        }

        $normalized = $this->normalizeQuotaText($value);
        if (in_array($normalized, [
            '0',
            'CERO',
            'INDEFINIDO',
            'INDEFINIDA',
            'SIN INICIO',
            'SIN TERMINO',
            'SIN INICIO NI TERMINO',
            'PERMANENTE',
        ], true)) {
            return 0;
        }

        $numericValue = str_replace(',', '.', $value);
        if (!is_numeric($numericValue)) {
            return null;
        }

        $number = (float) $numericValue;
        if ($number < 0 || abs($number - round($number)) > 0.000001) {
            return null;
        }

        return (int) round($number);
    }

    private function nullableNonNegativeInteger(mixed $value): ?int
    {
        if ($this->stringValue($value) === '') {
            return null;
        }

        return $this->nonNegativeInteger($value);
    }

    private function normalizeQuotaText(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = str_replace(
            ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ', '_', '-'],
            ['A', 'E', 'I', 'O', 'U', 'U', 'N', ' ', ' '],
            $value
        );
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_numeric($value)) {
            $value = (string) $value;
            return preg_replace('/\.0+$/', '', $value) ?? $value;
        }

        return trim((string) $value);
    }

    private function detailRow(
        int $importacionId,
        int $rowNumber,
        string $rut,
        ?int $cuotaActual,
        ?int $totalCuotas,
        string $observacion,
        string $estado,
        string $mensaje,
        ?int $discountId = null
    ): array {
        return [
            'mae_cuotas_importacion_id' => $importacionId,
            'mae_registro_descuento_id' => $discountId,
            'numero_fila' => $rowNumber,
            'rut' => $rut !== '' ? mb_substr($rut, 0, 32) : null,
            'cuota_actual' => $cuotaActual,
            'total_cuotas' => $totalCuotas,
            'observacion' => $observacion !== '' ? $observacion : null,
            'estado' => $estado,
            'mensaje' => $mensaje,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
