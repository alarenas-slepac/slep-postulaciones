<?php

namespace App\Services;

use App\Models\CargaFamiliar;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class CargaFamiliarImportService
{
    public function __construct(private readonly CargaFamiliarRutService $rutService)
    {
    }

    public function associateForUser(User $user): int
    {
        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $user->rut) ?? '');
        if ($rut === '') {
            return 0;
        }

        return CargaFamiliar::query()
            ->whereNull('user_id')
            ->where('beneficiario_run_normalizado', $rut)
            ->update(['user_id' => $user->id, 'updated_at' => now()]);
    }

    public function import(UploadedFile $file, int $userId, ?string $periodoCarga = null): array
    {
        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'excel' => 'No se pudo subir el archivo de cargas familiares. Intenta nuevamente.',
            ]);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw ValidationException::withMessages([
                'excel' => 'El archivo debe ser Excel (.xlsx o .xls).',
            ]);
        }

        $dir = 'imports/cargas-familiares/' . now()->format('Y/m');
        Storage::disk('local')->makeDirectory($dir);
        $storedPath = $file->storeAs($dir, now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $file->getClientOriginalName()), 'local');
        $fullPath = Storage::disk('local')->path($storedPath);

        $reader = IOFactory::createReaderForFile($fullPath);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($fullPath);
        $summary = [
            'archivo' => $file->getClientOriginalName(),
            'total_filas' => 0,
            'importadas' => 0,
            'actualizadas' => 0,
            'omitidas' => 0,
            'asociadas_user' => 0,
            'hojas' => [],
            'observaciones' => [],
        ];

        DB::transaction(function () use ($spreadsheet, $storedPath, $userId, $periodoCarga, &$summary) {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $sheetName = trim((string) $sheet->getTitle());
                if (in_array($this->normalizeHeader($sheetName), ['RESUMEN', 'INSTRUCCIONES', 'VALIDACIONES', 'CODIGOS Y LISTAS'], true)) {
                    continue;
                }

                $highestRow = (int) $sheet->getHighestDataRow();
                $highestColumn = (string) $sheet->getHighestDataColumn();
                if ($highestRow < 2) {
                    continue;
                }

                $headerInfo = $this->detectHeaderRow($sheet, $highestColumn, $highestRow);
                if (!$headerInfo) {
                    $summary['observaciones'][] = "Hoja {$sheetName}: omitida porque no se detectó fila de encabezados con RUN de beneficiario y causante.";
                    continue;
                }

                [$headerRow, $headers, $headerMap] = $headerInfo;

                if (!$this->hasAnyHeader($headerMap, ['BENEFICIARIO RUN', 'RUN BENEFICIARIO', 'BENEFICIARIO RUT COMPLETO', 'RUT BENEFICIARIO'])
                    || !$this->hasAnyHeader($headerMap, ['CAUSANTE RUN', 'RUN CAUSANTE', 'CAUSANTE RUT COMPLETO', 'RUT CAUSANTE'])) {
                    $summary['observaciones'][] = "Hoja {$sheetName}: omitida porque no contiene encabezados de beneficiario y causante.";
                    continue;
                }

                $sheetStats = ['filas' => 0, 'importadas' => 0, 'actualizadas' => 0, 'omitidas' => 0, 'asociadas_user' => 0];

                for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
                    $row = $sheet->rangeToArray('A' . $rowNumber . ':' . $highestColumn . $rowNumber, null, true, false)[0] ?? [];
                    if ($this->rowIsEmpty($row)) {
                        continue;
                    }

                    $summary['total_filas']++;
                    $sheetStats['filas']++;

                    $payload = $this->payloadFromRow($row, $headerMap, $sheetName, $storedPath, $userId, $periodoCarga);
                    if (($payload['beneficiario_run_normalizado'] ?? '') === '' || ($payload['causante_run_normalizado'] ?? '') === '') {
                        $summary['omitidas']++;
                        $sheetStats['omitidas']++;
                        $summary['observaciones'][] = "Hoja {$sheetName}, fila {$rowNumber}: omitida por RUN/DV incompleto de beneficiario o causante.";
                        continue;
                    }

                    $user = $this->findUserByNormalizedRut((string) $payload['beneficiario_run_normalizado']);
                    if ($user) {
                        $payload['user_id'] = $user->id;
                        $summary['asociadas_user']++;
                        $sheetStats['asociadas_user']++;
                    }

                    $keys = [
                        'beneficiario_run_normalizado' => $payload['beneficiario_run_normalizado'],
                        'causante_run_normalizado' => $payload['causante_run_normalizado'],
                        'periodo_carga' => $payload['periodo_carga'],
                        'comuna_origen' => $payload['comuna_origen'],
                    ];

                    $existing = CargaFamiliar::query()->where($keys)->first();
                    if ($existing) {
                        $existing->fill($payload)->save();
                        $summary['actualizadas']++;
                        $sheetStats['actualizadas']++;
                    } else {
                        CargaFamiliar::query()->create($payload);
                        $summary['importadas']++;
                        $sheetStats['importadas']++;
                    }
                }

                $summary['hojas'][$sheetName] = $sheetStats;
            }
        });

        if ($summary['importadas'] + $summary['actualizadas'] === 0) {
            throw ValidationException::withMessages([
                'excel' => 'No se importó ningún registro. Revisa que el archivo use la plantilla de cargas familiares o que contenga encabezados de beneficiario y causante.',
            ]);
        }

        return $summary;
    }

    private function payloadFromRow(array $row, array $headerMap, string $sheetName, string $storedPath, int $userId, ?string $periodoCarga = null): array
    {
        [$benefRun, $benefDv, $benefRut, $benefNorm] = $this->rutParts(
            $this->get($row, $headerMap, ['BENEFICIARIO RUN', 'RUN BENEFICIARIO', 'RUT BENEFICIARIO', 'BENEFICIARIO RUT']),
            $this->get($row, $headerMap, ['BENEFICIARIO DV', 'DV BENEFICIARIO']),
            $this->get($row, $headerMap, ['BENEFICIARIO RUT COMPLETO', 'RUT BENEFICIARIO COMPLETO'])
        );

        [$causRun, $causDv, $causRut, $causNorm] = $this->rutParts(
            $this->get($row, $headerMap, ['CAUSANTE RUN', 'RUN CAUSANTE', 'RUT CAUSANTE', 'CAUSANTE RUT']),
            $this->get($row, $headerMap, ['CAUSANTE DV', 'DV CAUSANTE']),
            $this->get($row, $headerMap, ['CAUSANTE RUT COMPLETO', 'RUT CAUSANTE COMPLETO'])
        );

        $periodo = $this->string($this->get($row, $headerMap, ['PERIODO CARGA', 'PERIODO', 'MES ANIO', 'MES AÑO']));
        $comuna = $this->string($this->get($row, $headerMap, ['COMUNA ORIGEN', 'COMUNA', 'DOMINIO']));

        return [
            'periodo_carga' => $periodoCarga ?: ($periodo !== '' ? $periodo : now()->format('Y-m')),
            'comuna_origen' => $comuna !== '' ? $comuna : $sheetName,
            'fuente_archivo' => $storedPath,
            'beneficiario_run' => $benefRun,
            'beneficiario_dv' => $benefDv,
            'beneficiario_rut_completo' => $benefRut,
            'beneficiario_run_normalizado' => $benefNorm,
            'beneficiario_apellido_paterno' => $this->string($this->get($row, $headerMap, ['BENEFICIARIO APELLIDO PATERNO', 'APELLIDO PATERNO BENEFICIARIO', 'PATERNO BENEFICIARIO', 'BENEFICIARIO PATERNO'])),
            'beneficiario_apellido_materno' => $this->string($this->get($row, $headerMap, ['BENEFICIARIO APELLIDO MATERNO', 'APELLIDO MATERNO BENEFICIARIO', 'MATERNO BENEFICIARIO', 'BENEFICIARIO MATERNO'])),
            'beneficiario_nombres' => $this->string($this->get($row, $headerMap, ['BENEFICIARIO NOMBRES', 'NOMBRES BENEFICIARIO', 'NOMBRE BENEFICIARIO', 'BENEFICIARIO NOMBRE'])),
            'beneficiario_email' => $this->string($this->get($row, $headerMap, ['BENEFICIARIO EMAIL OPCIONAL', 'BENEFICIARIO EMAIL', 'EMAIL BENEFICIARIO', 'CORREO BENEFICIARIO'])),
            'causante_run' => $causRun,
            'causante_dv' => $causDv,
            'causante_rut_completo' => $causRut,
            'causante_run_normalizado' => $causNorm,
            'causante_apellido_paterno' => $this->string($this->get($row, $headerMap, ['CAUSANTE APELLIDO PATERNO', 'APELLIDO PATERNO CAUSANTE', 'PATERNO CAUSANTE', 'CAUSANTE PATERNO'])),
            'causante_apellido_materno' => $this->string($this->get($row, $headerMap, ['CAUSANTE APELLIDO MATERNO', 'APELLIDO MATERNO CAUSANTE', 'MATERNO CAUSANTE', 'CAUSANTE MATERNO'])),
            'causante_nombres' => $this->string($this->get($row, $headerMap, ['CAUSANTE NOMBRES', 'NOMBRES CAUSANTE', 'NOMBRE CAUSANTE', 'CAUSANTE NOMBRE'])),
            'sexo' => $this->string($this->get($row, $headerMap, ['SEXO', 'CODIGO SEXO', 'CODIGO DE SEXO'])),
            'parentesco' => $this->string($this->get($row, $headerMap, ['PARENTESCO', 'TIPO CAUSANTE', 'RELACION'])),
            'codigo_siagf' => $this->string($this->get($row, $headerMap, ['CODIGO SIAGF', 'SIAGF'])),
            'tipo_beneficio' => $this->string($this->get($row, $headerMap, ['TIPO BENEFICIO', 'BENEFICIO', 'CODIGO TIPO DE BENEFICIO'])),
            'codigo_tipo_causante' => $this->string($this->get($row, $headerMap, ['CODIGO TIPO CAUSANTE', 'CODIGO TIPO DE CAUSANTE'])),
            'fecha_nacimiento' => $this->date($this->get($row, $headerMap, ['FECHA NACIMIENTO', 'FECHA DE NACIMIENTO', 'NACIMIENTO'])),
            'fecha_resolucion' => $this->date($this->get($row, $headerMap, ['FECHA RESOLUCION', 'FECHA DE RESOLUCION'])),
            'numero_resolucion' => $this->string($this->get($row, $headerMap, ['NUMERO RESOLUCION', 'N RESOLUCION', 'NRO RESOLUCION'])),
            'fecha_inicio' => $this->date($this->get($row, $headerMap, ['FECHA INICIO', 'FECHA DE INICIO', 'INICIO BENEFICIO', 'FECHA INICIO BENEFICIO'])),
            'fecha_termino' => $this->date($this->get($row, $headerMap, ['FECHA TERMINO', 'FECHA TERMINO BENEFICIO', 'TERMINO BENEFICIO'])),
            'tipo' => $this->string($this->get($row, $headerMap, ['TIPO'])),
            'tramo' => $this->string($this->get($row, $headerMap, ['TRAMO'])),
            'monto' => $this->money($this->get($row, $headerMap, ['MONTO', 'MONTO ASIGNACION'])),
            'estado_carga' => mb_strtolower($this->string($this->get($row, $headerMap, ['ESTADO CARGA', 'ESTADO']))) ?: 'vigente',
            'observaciones' => $this->string($this->get($row, $headerMap, ['OBSERVACIONES', 'OBSERVACION'])),
            'raw_row' => $this->rawRow($row, $headerMap),
            'imported_by' => $userId,
            'imported_at' => now(),
        ];
    }

    private function rutParts(mixed $run, mixed $dv, mixed $rutCompleto): array
    {
        if ($this->string($rutCompleto) !== '') {
            return $this->rutService->fromString($rutCompleto);
        }

        return $this->rutService->fromParts($run, $dv);
    }

    private function findUserByNormalizedRut(string $normalizedRut): ?User
    {
        return User::query()
            ->whereRaw("REPLACE(REPLACE(UPPER(rut), '.', ''), '-', '') = ?", [$normalizedRut])
            ->first();
    }

    private function detectHeaderRow($sheet, string $highestColumn, int $highestRow): ?array
    {
        $limit = min($highestRow, 30);
        for ($rowNumber = 1; $rowNumber <= $limit; $rowNumber++) {
            $headers = $sheet->rangeToArray('A' . $rowNumber . ':' . $highestColumn . $rowNumber, null, true, false)[0] ?? [];
            $headers = $this->normalizeContextualHeaders($headers);
            $headerMap = $this->buildHeaderMap($headers);

            if ($this->hasAnyHeader($headerMap, ['BENEFICIARIO RUN', 'RUN BENEFICIARIO', 'BENEFICIARIO RUT COMPLETO', 'RUT BENEFICIARIO'])
                && $this->hasAnyHeader($headerMap, ['CAUSANTE RUN', 'RUN CAUSANTE', 'CAUSANTE RUT COMPLETO', 'RUT CAUSANTE'])) {
                return [$rowNumber, $headers, $headerMap];
            }
        }

        return null;
    }

    private function normalizeContextualHeaders(array $headers): array
    {
        $normalized = array_map(fn ($header) => $this->normalizeHeader((string) $header), $headers);
        $rutIndexes = [];
        foreach ($normalized as $index => $header) {
            if ($header === 'RUT' || $header === 'RUN') {
                $rutIndexes[] = $index;
            }
        }

        if (count($rutIndexes) < 2) {
            return $headers;
        }

        $secondRut = $rutIndexes[1];
        $contextual = $headers;
        foreach ($normalized as $index => $header) {
            $beneficiario = $index >= $secondRut;
            $prefix = $beneficiario ? 'BENEFICIARIO ' : 'CAUSANTE ';

            $contextual[$index] = match ($header) {
                'RUT', 'RUN' => $prefix . 'RUN',
                'D', 'DV' => $prefix . 'DV',
                'PATERNO' => $prefix . 'APELLIDO PATERNO',
                'MATERNO' => $prefix . 'APELLIDO MATERNO',
                'NOMBRES', 'NOMBRE' => $prefix . 'NOMBRES',
                'SX' => 'SEXO',
                'F NACIM', 'F NACIMIENTO' => 'FECHA NACIMIENTO',
                'F RESOL', 'F RESOLUCION' => 'FECHA RESOLUCION',
                'NRO RESOL', 'NRO RESOLUCION', 'NUM RESOLUCION' => 'NUMERO RESOLUCION',
                'INICIO' => 'FECHA INICIO',
                'TERMINO' => 'FECHA TERMINO',
                'TRA' => 'TRAMO',
                default => $headers[$index],
            };
        }

        return $contextual;
    }

    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $key = $this->normalizeHeader((string) $header);
            if ($key !== '' && !array_key_exists($key, $map)) {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
        $value = preg_replace('/[^A-Za-z0-9]+/', ' ', $value) ?? '';
        return strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    private function hasAnyHeader(array $headerMap, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($this->normalizeHeader($alias), $headerMap)) {
                return true;
            }
        }
        return false;
    }

    private function get(array $row, array $headerMap, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeHeader($alias);
            if (array_key_exists($key, $headerMap)) {
                return $row[$headerMap[$key]] ?? null;
            }
        }
        return null;
    }

    private function rawRow(array $row, array $headerMap): array
    {
        $raw = [];
        foreach ($headerMap as $header => $index) {
            $raw[$header] = $this->string($row[$index] ?? null);
        }
        return $raw;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->string($value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function string(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }
        return trim((string) $value);
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date instanceof \DateTimeInterface) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return (new \DateTime($value))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $clean = preg_replace('/[^0-9,.-]/', '', (string) $value) ?? '';
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
        return is_numeric($clean) ? (float) $clean : null;
    }
}
