<?php

namespace App\Services;

use App\Models\MaeCarga;
use App\Models\MaeHomologacionColumna;
use App\Support\MaeChunkReadFilter;
use App\Support\MaeColumnNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MaeImportService
{
    public function enqueueImport(UploadedFile $file, array $payload, int $userId): MaeCarga
    {
        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'excel' => 'No se pudo subir el archivo MAE. Intenta nuevamente.',
            ]);
        }

        $anio = (int) ($payload['anio'] ?? 0);
        $mes = (int) ($payload['mes'] ?? 0);
        $dominio = trim((string) ($payload['dominio'] ?? ''));
        $motivoReemplazo = trim((string) ($payload['motivo_reemplazo'] ?? ''));

        $normalizedDomain = MaeColumnNormalizer::normalizeDomain($dominio);
        $hash = hash_file('sha256', $file->getRealPath());

        if (MaeCarga::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('dominio', $dominio)
            ->where('hash_archivo', $hash)
            ->exists()) {
            throw ValidationException::withMessages([
                'excel' => 'Ya existe una carga idéntica para ese período y dominio. Sube un archivo actualizado o revisa el historial de versiones.',
            ]);
        }

        $disk = 'local';
        $dir = sprintf('imports/endeudamiento/%04d-%02d/%s', $anio, $mes, preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($normalizedDomain)));
        Storage::disk($disk)->makeDirectory($dir);

        $storedPath = $file->storeAs(
            $dir,
            now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $file->getClientOriginalName()),
            $disk
        );

        if (!$storedPath || !Storage::disk($disk)->exists($storedPath)) {
            throw ValidationException::withMessages([
                'excel' => 'No se pudo guardar el archivo en storage para iniciar la importación.',
            ]);
        }

        $version = ((int) MaeCarga::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('dominio', $dominio)
            ->max('version')) + 1;

        $vigenteAnterior = MaeCarga::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('dominio', $dominio)
            ->where('es_vigente', true)
            ->latest('version')
            ->first();

        return MaeCarga::query()->create([
            'anio' => $anio,
            'mes' => $mes,
            'dominio' => $dominio,
            'comuna_origen' => null,
            'version' => $version,
            'es_vigente' => false,
            'reemplaza_carga_id' => $vigenteAnterior?->id,
            'motivo_reemplazo' => $motivoReemplazo !== '' ? $motivoReemplazo : null,
            'nombre_archivo' => $file->getClientOriginalName(),
            'ruta_archivo' => $storedPath,
            'hash_archivo' => $hash,
            'estado' => 'pendiente',
            'total_filas' => 0,
            'filas_validas' => 0,
            'filas_omitidas' => 0,
            'filas_observadas' => 0,
            'observaciones' => null,
            'subido_por' => $userId,
        ]);
    }

    public function processMaeCarga(int $cargaId): MaeCarga
    {
        $carga = MaeCarga::query()->findOrFail($cargaId);
        $fullPath = Storage::disk('local')->path($carga->ruta_archivo);

        if (!is_file($fullPath)) {
            throw ValidationException::withMessages([
                'excel' => 'No se encontró el archivo MAE guardado en storage para procesar la carga.',
            ]);
        }

        $anio = (int) $carga->anio;
        $mes = (int) $carga->mes;
        $dominio = trim((string) $carga->dominio);
        $normalizedDomain = MaeColumnNormalizer::normalizeDomain($dominio);
        $vigenteAnterior = $carga->reemplazaCarga()->first();

        $carga->update([
            'estado' => 'procesando',
            'observaciones' => null,
            'updated_at' => now(),
        ]);

        $reader = IOFactory::createReaderForFile($fullPath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }

        $worksheetInfo = $reader->listWorksheetInfo($fullPath);
        if (empty($worksheetInfo)) {
            throw ValidationException::withMessages([
                'excel' => 'No fue posible leer la Hoja1 del archivo MAE.',
            ]);
        }

        $sheetName = $worksheetInfo[0]['worksheetName'] ?? 'Worksheet';
        $highestRow = (int) ($worksheetInfo[0]['totalRows'] ?? 0);
        $highestColumn = (string) ($worksheetInfo[0]['lastColumnLetter'] ?? 'A');
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        $reader->setLoadSheetsOnly([$sheetName]);
        $readFilter = new MaeChunkReadFilter(1, 1);
        $reader->setReadFilter($readFilter);

        $spreadsheet = $reader->load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();

        $headers = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0] ?? [];
        $headers = array_pad($headers, $highestColumnIndex, '');
        $normalizedHeaders = array_map(static fn($header) => MaeColumnNormalizer::normalizeHeader((string) $header), $headers);

        $required = ['RUT', 'NOMBRE', 'COMUNA', 'DIAS TRAB', 'TOTAL HABERES', 'MONTO IMPONIBLE', 'MONTO TRIBUTABLE'];
        $missing = [];
        foreach ($required as $requiredHeader) {
            if ($this->firstIndex($normalizedHeaders, $requiredHeader) === null) {
                $missing[] = $requiredHeader;
            }
        }

        if (!empty($missing)) {
            throw ValidationException::withMessages([
                'excel' => 'El archivo no trae la estructura mínima esperada en Hoja1. Faltan encabezados: ' . implode(', ', $missing),
            ]);
        }

        $idxRut = $this->firstIndex($normalizedHeaders, 'RUT');
        $idxNombre = $this->firstIndex($normalizedHeaders, 'NOMBRE');
        $idxComuna = $this->firstIndex($normalizedHeaders, 'COMUNA');
        $idxDiasTrab = $this->firstIndex($normalizedHeaders, 'DIAS TRAB');
        $idxTotalHaberes = $this->firstIndex($normalizedHeaders, 'TOTAL HABERES');
        $idxMontoImponible = $this->firstIndex($normalizedHeaders, 'MONTO IMPONIBLE');
        $idxMontoTributable = $this->firstIndex($normalizedHeaders, 'MONTO TRIBUTABLE');
        $legalStartIndex = $idxTotalHaberes !== null ? ($idxTotalHaberes + 1) : 0;
        $legalEndIndex = max($legalStartIndex, $idxMontoTributable !== null ? $idxMontoTributable : $highestColumnIndex - 1);
        $detailStartIndex = $idxMontoTributable !== null ? ($idxMontoTributable + 1) : 0;
        $detailEndIndex = max($detailStartIndex, $highestColumnIndex - 2);
        $idxImposiciones = MaeColumnNormalizer::findLegalDiscountIndex($normalizedHeaders, 'imposiciones', $legalStartIndex, $legalEndIndex);
        $idxSalud = MaeColumnNormalizer::findLegalDiscountIndex($normalizedHeaders, 'salud', $legalStartIndex, $legalEndIndex);
        $idxImpuesto = MaeColumnNormalizer::findLegalDiscountIndex($normalizedHeaders, 'impuesto', $legalStartIndex, $legalEndIndex);

        $homologations = MaeHomologacionColumna::query()
            ->where('activo', true)
            ->orderByDesc('prioridad')
            ->orderBy('id')
            ->get()
            ->groupBy('columna_normalizada')
            ->map(fn($items) => $items->first());

        $distinctCommunes = [];
        $totalRows = 0;
        $filasValidas = 0;
        $filasOmitidas = 0;
        $filasObservadas = 0;
        $observations = [];
        $detailBuffer = [];
        $otherBuffer = [];
        $chunkSize = 150;

        try {
            DB::beginTransaction();

            DB::table('mae_registro_descuentos')->whereIn('mae_registro_id', function ($query) use ($carga) {
                $query->select('id')->from('mae_registros')->where('mae_carga_id', $carga->id);
            })->delete();
            DB::table('mae_registro_otros_descuentos')->whereIn('mae_registro_id', function ($query) use ($carga) {
                $query->select('id')->from('mae_registros')->where('mae_carga_id', $carga->id);
            })->delete();
            DB::table('mae_registros')->where('mae_carga_id', $carga->id)->delete();

            for ($chunkStart = 2; $chunkStart <= $highestRow; $chunkStart += $chunkSize) {
                $readFilter->setRows($chunkStart, $chunkSize);
                $chunkSpreadsheet = $reader->load($fullPath);
                $chunkSheet = $chunkSpreadsheet->getActiveSheet();
                $chunkEnd = min($highestRow, $chunkStart + $chunkSize - 1);

                for ($row = $chunkStart; $row <= $chunkEnd; $row++) {
                    $rowValues = $chunkSheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false)[0] ?? [];
                    $rowValues = array_pad($rowValues, $highestColumnIndex, null);

                    $rut = $this->stringValue($rowValues[$idxRut] ?? null);
                    $nombre = $this->stringValue($rowValues[$idxNombre] ?? null);

                    if ($rut === '' && $nombre === '') {
                        continue;
                    }

                    $totalRows++;

                    if ($rut === '') {
                        $filasOmitidas++;
                        $observations[] = "Fila {$row}: se omitió porque no trae RUT.";
                        continue;
                    }

                    $rowComuna = $this->stringValue($rowValues[$idxComuna] ?? null);
                    if ($rowComuna !== '') {
                        $distinctCommunes[MaeColumnNormalizer::normalizeDomain($rowComuna)] = $rowComuna;
                    }

                    $datosTrabajador = $this->buildSectionMap($headers, $rowValues, $idxNombre, $idxDiasTrab);
                    $rawRow = $this->buildRawRow($headers, $rowValues);

                    $imposicionesBase = $this->resolveLegalDiscountValue($rowValues, $rawRow, $idxImposiciones, 'imposiciones');
                    $saludBase = $this->resolveLegalDiscountValue($rowValues, $rawRow, $idxSalud, 'salud');
                    $impuestoBase = $this->resolveLegalDiscountValue($rowValues, $rawRow, $idxImpuesto, 'impuesto');

                    $registroId = DB::table('mae_registros')->insertGetId([
                        'mae_carga_id' => $carga->id,
                        'anio' => $anio,
                        'mes' => $mes,
                        'dominio' => $dominio,
                        'comuna_origen' => $rowComuna !== '' ? $rowComuna : null,
                        'rut' => $rut,
                        'nombre_completo' => $nombre !== '' ? $nombre : null,
                        'dias_trab' => $this->decimalValue($rowValues[$idxDiasTrab] ?? null),
                        'datos_trabajador_json' => json_encode($datosTrabajador, JSON_UNESCAPED_UNICODE),
                        'total_haberes' => $this->decimalValue($rowValues[$idxTotalHaberes] ?? null),
                        'monto_imponible' => $this->decimalValue($rowValues[$idxMontoImponible] ?? null),
                        'monto_tributable' => $this->decimalValue($rowValues[$idxMontoTributable] ?? null),
                        'imposiciones' => $imposicionesBase,
                        'salud' => $saludBase,
                        'impuesto' => $impuestoBase,
                        'total_descuentos_homologados' => 0,
                        'total_aportes_patronales' => 0,
                        'total_otros_descuentos' => 0,
                        'observaciones_importacion' => null,
                        'raw_row_json' => json_encode($rawRow, JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $sumHomologados = 0.0;
                    $sumPatronales = 0.0;
                    $sumOtros = 0.0;
                    $rowObservations = [];

                    for ($index = $detailStartIndex; $index < $highestColumnIndex; $index++) {
                        if ($index > $detailEndIndex) {
                            continue;
                        }

                        $headerOriginal = trim((string) ($headers[$index] ?? ''));
                        $headerNormalized = $normalizedHeaders[$index] ?? MaeColumnNormalizer::normalizeHeader($headerOriginal);
                        $value = $this->decimalValue($rowValues[$index] ?? null);
                        $rawValue = $rowValues[$index] ?? null;

                        if ($this->isEmptyAmount($rawValue, $value)) {
                            continue;
                        }

                        if (in_array($headerNormalized, ['IMPOSICIONES', 'SALUD', 'IMPUESTO', 'APORTE ADICIONAL AFP'], true)) {
                            continue;
                        }

                        $isPatronal = MaeColumnNormalizer::isAportePatronal($headerOriginal);
                        $homologation = $homologations->get($headerNormalized);

                        if ($homologation && !$homologation->es_guardable) {
                            continue;
                        }

                        if ($homologation) {
                            $tipoMovimiento = $homologation->tipo_movimiento ?: 'descuento';
                            if ($isPatronal || $homologation->es_aporte_patronal) {
                                $tipoMovimiento = 'aporte_patronal';
                            }

                            $detailBuffer[] = [
                                'mae_registro_id' => $registroId,
                                'orden_columna' => $index + 1,
                                'columna_origen' => $headerOriginal !== '' ? $headerOriginal : '(sin encabezado)',
                                'columna_normalizada' => $headerNormalized,
                                'campo_canonico' => $homologation->campo_canonico,
                                'grupo' => $homologation->grupo,
                                'subgrupo' => $homologation->subgrupo,
                                'tipo_movimiento' => $tipoMovimiento,
                                'es_aporte_patronal' => $tipoMovimiento === 'aporte_patronal',
                                'valor' => $value,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            if ($tipoMovimiento === 'aporte_patronal') {
                                $sumPatronales += (float) $value;
                            } else {
                                $sumHomologados += (float) $value;
                            }
                        } else {
                            $inferred = MaeColumnNormalizer::inferDiscountMetadata($headerOriginal, $headerNormalized);
                            $tipoMovimiento = $inferred['tipo_movimiento'];

                            $detailBuffer[] = [
                                'mae_registro_id' => $registroId,
                                'orden_columna' => $index + 1,
                                'columna_origen' => $headerOriginal !== '' ? $headerOriginal : '(sin encabezado)',
                                'columna_normalizada' => $headerNormalized,
                                'campo_canonico' => $inferred['campo_canonico'],
                                'grupo' => $inferred['grupo'],
                                'subgrupo' => $inferred['subgrupo'],
                                'tipo_movimiento' => $tipoMovimiento,
                                'es_aporte_patronal' => $tipoMovimiento === 'aporte_patronal',
                                'valor' => $value,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            if ($tipoMovimiento === 'aporte_patronal') {
                                $sumPatronales += (float) $value;
                            } else {
                                $sumHomologados += (float) $value;
                            }

                            $rowObservations[] = 'Columna no homologada categorizada como ' . $inferred['subgrupo'] . ': ' . ($headerOriginal !== '' ? $headerOriginal : '(sin encabezado)');
                        }
                    }

                    DB::table('mae_registros')->where('id', $registroId)->update([
                        'total_descuentos_homologados' => $sumHomologados,
                        'total_aportes_patronales' => $sumPatronales,
                        'total_otros_descuentos' => $sumOtros,
                        'observaciones_importacion' => !empty($rowObservations) ? implode(' | ', $rowObservations) : null,
                        'updated_at' => now(),
                    ]);

                    if (!empty($rowObservations)) {
                        $filasObservadas++;
                    }

                    $filasValidas++;

                    if (count($detailBuffer) >= 1000) {
                        DB::table('mae_registro_descuentos')->insert($detailBuffer);
                        $detailBuffer = [];
                    }

                    if (count($otherBuffer) >= 1000) {
                        DB::table('mae_registro_otros_descuentos')->insert($otherBuffer);
                        $otherBuffer = [];
                    }
                }

                $chunkSpreadsheet->disconnectWorksheets();
                unset($chunkSpreadsheet, $chunkSheet);
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $sheet);

            if (count($distinctCommunes) > 1) {
                throw ValidationException::withMessages([
                    'dominio' => 'El archivo trae más de una comuna/dominio en Hoja1. Corrige el MAE antes de importar.',
                ]);
            }

            $comunaOrigen = reset($distinctCommunes) ?: null;
            if ($comunaOrigen === null) {
                throw ValidationException::withMessages([
                    'excel' => 'No fue posible determinar la comuna/dominio del archivo desde la columna comuna.',
                ]);
            }

            if (MaeColumnNormalizer::normalizeDomain($comunaOrigen) !== $normalizedDomain) {
                throw ValidationException::withMessages([
                    'dominio' => "El dominio seleccionado ({$dominio}) no coincide con la comuna del archivo ({$comunaOrigen}).",
                ]);
            }

            if (!empty($detailBuffer)) {
                DB::table('mae_registro_descuentos')->insert($detailBuffer);
            }
            if (!empty($otherBuffer)) {
                DB::table('mae_registro_otros_descuentos')->insert($otherBuffer);
            }

            if ($vigenteAnterior) {
                $vigenteAnterior->update(['es_vigente' => false, 'updated_at' => now()]);
            }

            $estadoFinal = $filasObservadas > 0 ? 'procesado_con_observaciones' : 'procesado';

            $carga->update([
                'comuna_origen' => $comunaOrigen,
                'es_vigente' => true,
                'estado' => $estadoFinal,
                'total_filas' => $totalRows,
                'filas_validas' => $filasValidas,
                'filas_omitidas' => $filasOmitidas,
                'filas_observadas' => $filasObservadas,
                'observaciones' => !empty($observations) ? implode(' | ', array_slice($observations, 0, 50)) : null,
                'procesado_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            $carga->update([
                'estado' => 'fallido',
                'observaciones' => $e->getMessage(),
                'updated_at' => now(),
            ]);

            throw $e;
        }

        return $carga->fresh(['subidaPor', 'reemplazaCarga']);
    }

    private function resolveLegalDiscountValue(array $rowValues, array $rawRow, ?int $index, string $field): ?float
    {
        $directValue = $index !== null ? ($rowValues[$index] ?? null) : null;
        $directDecimal = $this->decimalValue($directValue);

        $fallbackValue = MaeColumnNormalizer::findLegalDiscountValueInRawRow($rawRow, $field);
        $fallbackDecimal = $this->decimalValue($fallbackValue);

        if ($fallbackDecimal !== null && abs($fallbackDecimal) > 0.000001) {
            if ($directDecimal === null || abs($directDecimal) <= 0.000001) {
                return $fallbackDecimal;
            }
        }

        if ($directDecimal !== null) {
            return $directDecimal;
        }

        return $fallbackDecimal;
    }

    private function firstIndex(array $normalizedHeaders, string $needle): ?int
    {
        foreach ($normalizedHeaders as $index => $header) {
            if ($header === $needle) {
                return $index;
            }
        }

        return null;
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            $text = (string) $value;
            if (preg_match('/\.0+$/', $text)) {
                $text = preg_replace('/\.0+$/', '', $text) ?? $text;
            }
            return trim($text);
        }

        return trim((string) $value);
    }

    private function decimalValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/[^0-9,.-]/', '', $text) ?? '';
        if ($text === '' || $text === '-' || $text === ',') {
            return null;
        }

        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif (substr_count($text, '.') > 1) {
            $text = str_replace('.', '', $text);
        } elseif (str_contains($text, ',') && !str_contains($text, '.')) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function isEmptyAmount(mixed $rawValue, ?float $decimal): bool
    {
        if ($decimal !== null && abs($decimal) > 0.000001) {
            return false;
        }

        $text = $this->stringValue($rawValue);
        return $text === '' || $text === '0';
    }

    private function buildSectionMap(array $headers, array $rowValues, int $fromIndex, int $toIndex): array
    {
        $data = [];
        for ($index = $fromIndex; $index <= $toIndex; $index++) {
            $label = trim((string) ($headers[$index] ?? ''));
            $label = $label !== '' ? $label : 'columna_' . ($index + 1);
            $labelBase = $label;
            $counter = 2;
            while (array_key_exists($label, $data)) {
                $label = $labelBase . ' #' . $counter;
                $counter++;
            }
            $data[$label] = $rowValues[$index] ?? null;
        }
        return $data;
    }

    private function buildRawRow(array $headers, array $rowValues): array
    {
        $data = [];
        foreach ($rowValues as $index => $value) {
            $label = trim((string) ($headers[$index] ?? ''));
            $label = $label !== '' ? $label : 'columna_' . ($index + 1);
            $labelBase = $label;
            $counter = 2;
            while (array_key_exists($label, $data)) {
                $label = $labelBase . ' #' . $counter;
                $counter++;
            }
            $data[$label] = $value;
        }
        return $data;
    }
}
