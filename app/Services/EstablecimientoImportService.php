<?php

namespace App\Services;

use App\Models\Establecimiento;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EstablecimientoImportService
{
    /**
     * @return array<int, string>
     */
    public function expectedHeaders(): array
    {
        return [
            ...$this->requiredHeaders(),
            'MATRICULA_TOTAL',
            'DIRECTOR_NOMBRE',
            'DIRECTOR_CONTACTO',
        ];
    }

    /**
     * La estructura histórica sigue siendo válida para no romper cargas antiguas.
     *
     * @return array<int, string>
     */
    public function requiredHeaders(): array
    {
        return [
            'COD_ESTAB',
            'RBD',
            'DV',
            'NOMBRE_ESTABLECIMIENTO',
            'CLASIFICACIÓN',
            'TIPO_ESTAB',
            'Sala Cuna',
            'Pre-Escolar',
            'Básica',
            'Media',
            'Técnico-Profesional',
            'Adultos',
            'Especial',
            'COMUNA',
            "% ASIGNACION\nZONA",
            'LATITUD',
            'LONGITUD',
        ];
    }

    /**
     * @param  string|int|null  $sheetOption
     * @return array<string, mixed>
     */
    public function importFromPath(string $path, string|int|null $sheetOption = null, bool $truncate = false): array
    {
        if (!is_file($path)) {
            throw new InvalidArgumentException('Archivo no encontrado para importación.');
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $sheetOption !== null
            ? (is_numeric($sheetOption)
                ? $spreadsheet->getSheet((int) $sheetOption)
                : $spreadsheet->getSheetByName((string) $sheetOption))
            : $spreadsheet->getActiveSheet();

        if (!$sheet) {
            throw new InvalidArgumentException('Hoja no encontrada en el archivo Excel.');
        }

        $rows = $sheet->toArray(null, true, true, true);
        if (count($rows) < 2) {
            throw new InvalidArgumentException('El archivo debe contener al menos 2 filas de encabezados.');
        }

        $headers = $this->buildHeaders($rows[1] ?? [], $rows[2] ?? []);
        $required = $this->requiredHeaders();
        $got = array_slice($headers, 0, count($required));

        if ($got !== $required) {
            throw new InvalidArgumentException(
                'Encabezados inesperados. Debes usar la plantilla oficial de establecimientos.'
            );
        }

        if ($truncate) {
            Establecimiento::query()->delete();
        }

        $idx = array_flip($headers);
        $hasMatricula = ($headers[count($required)] ?? null) === 'MATRICULA_TOTAL';
        $hasDirectorNombre = ($headers[count($required) + 1] ?? null) === 'DIRECTOR_NOMBRE';
        $hasDirectorContacto = ($headers[count($required) + 2] ?? null) === 'DIRECTOR_CONTACTO';
        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_slice($rows, 2) as $excelRow => $row) {
            $displayRow = $excelRow + 3;
            $vals = array_values($row);

            $codRaw = $vals[$idx['COD_ESTAB']] ?? null;
            $rbdRaw = $vals[$idx['RBD']] ?? null;
            $nombre = trim((string) ($vals[$idx['NOMBRE_ESTABLECIMIENTO']] ?? ''));

            if ($this->isEffectivelyEmptyRow($codRaw, $rbdRaw, $nombre, $vals)) {
                continue;
            }

            $cod = $this->toIntOrNull($codRaw);
            $rbd = $this->toIntOrNull($rbdRaw);

            if ($cod === null || $rbd === null || $nombre === '') {
                $errors[] = "Fila {$displayRow}: COD_ESTAB, RBD y NOMBRE_ESTABLECIMIENTO son obligatorios.";
                $skipped++;
                continue;
            }

            $latitud = $this->toCoordinate($vals[$idx['LATITUD']] ?? null, 'latitud', $displayRow, $errors);
            $longitud = $this->toCoordinate($vals[$idx['LONGITUD']] ?? null, 'longitud', $displayRow, $errors);

            if ($latitud === false || $longitud === false) {
                $skipped++;
                continue;
            }

            $matricula = null;
            if ($hasMatricula) {
                $matriculaRaw = $vals[$idx['MATRICULA_TOTAL']] ?? null;
                $matricula = $this->toIntOrNull($matriculaRaw);
                if (trim((string) ($matriculaRaw ?? '')) !== '' && ($matricula === null || $matricula < 0)) {
                    $errors[] = "Fila {$displayRow}: MATRÍCULA_TOTAL debe ser un número entero mayor o igual a cero.";
                    $skipped++;
                    continue;
                }
            }

            $directorNombre = $hasDirectorNombre
                ? $this->toNullableString($vals[$idx['DIRECTOR_NOMBRE']] ?? null)
                : null;
            $directorContacto = $hasDirectorContacto
                ? $this->toNullableString($vals[$idx['DIRECTOR_CONTACTO']] ?? null)
                : null;

            if ($directorNombre !== null && mb_strlen($directorNombre) > 180) {
                $errors[] = "Fila {$displayRow}: DIRECTOR_NOMBRE no puede superar 180 caracteres.";
                $skipped++;
                continue;
            }
            if ($directorContacto !== null && mb_strlen($directorContacto) > 255) {
                $errors[] = "Fila {$displayRow}: DIRECTOR_CONTACTO no puede superar 255 caracteres.";
                $skipped++;
                continue;
            }

            $processed++;

            $payload = [
                'cod_estab' => $cod,
                'rbd' => $rbd,
                'dv' => $this->toDv($vals[$idx['DV']] ?? null),
                'nombre_establecimiento' => $nombre,
                'clasificacion' => $this->toNullableString($vals[$idx['CLASIFICACIÓN']] ?? null),
                'tipo_estab' => $this->toNullableString($vals[$idx['TIPO_ESTAB']] ?? null),
                'sala_cuna' => $this->snToBool($vals[$idx['Sala Cuna']] ?? null),
                'pre_escolar' => $this->snToBool($vals[$idx['Pre-Escolar']] ?? null),
                'basica' => $this->snToBool($vals[$idx['Básica']] ?? null),
                'media' => $this->snToBool($vals[$idx['Media']] ?? null),
                'tecnico_profesional' => $this->snToBool($vals[$idx['Técnico-Profesional']] ?? null),
                'adultos' => $this->snToBool($vals[$idx['Adultos']] ?? null),
                'especial' => $this->snToBool($vals[$idx['Especial']] ?? null),
                'comuna' => $this->toNullableString($vals[$idx['COMUNA']] ?? null),
                'asignacion_zona' => $this->toIntOrZero($vals[$idx["% ASIGNACION\nZONA"]] ?? null),
                'latitud' => $latitud,
                'longitud' => $longitud,
            ];

            if ($hasMatricula) {
                $payload['matricula_total'] = $matricula;
            }
            if ($hasDirectorNombre) {
                $payload['director_nombre'] = $directorNombre;
            }
            if ($hasDirectorContacto) {
                $payload['director_contacto'] = $directorContacto;
            }

            $existing = Establecimiento::query()->where('cod_estab', $cod)->first();
            if ($existing) {
                $existing->fill($payload)->save();
                $updated++;
            } else {
                Establecimiento::query()->create($payload);
                $created++;
            }
        }

        return [
            'sheet_name' => $sheet->getTitle(),
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $headerRow1
     * @param  array<int|string, mixed>  $headerRow2
     * @return array<int, string>
     */
    private function buildHeaders(array $headerRow1, array $headerRow2): array
    {
        $h1 = array_values($headerRow1);
        $h2 = array_values($headerRow2);
        $max = max(count($h1), count($h2));
        $headers = [];

        for ($i = 0; $i < $max; $i++) {
            $v1 = $this->normHeader($h1[$i] ?? null);
            $v2 = $this->normHeader($h2[$i] ?? null);

            if ($v1 === '' && $v2 === '') {
                $headers[] = '';
                continue;
            }

            if (mb_strtoupper($v1) === 'TIPO ENSEÑANZA' && $v2 !== '') {
                $headers[] = $v2;
                continue;
            }

            $headers[] = $v1 !== '' ? $v1 : $v2;
        }

        return $headers;
    }

    /**
     * @param  mixed[]  $vals
     */
    private function isEffectivelyEmptyRow(mixed $cod, mixed $rbd, string $nombre, array $vals): bool
    {
        if ($nombre !== '') {
            return false;
        }

        $hasCode = $this->toIntOrNull($cod) !== null;
        $hasRbd = $this->toIntOrNull($rbd) !== null;
        if ($hasCode || $hasRbd) {
            return false;
        }

        foreach ($vals as $val) {
            if (trim((string) ($val ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normHeader(mixed $value): string
    {
        $string = trim((string) ($value ?? ''));
        return str_replace(["\r\n", "\r"], "\n", $string);
    }

    private function snToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $string = mb_strtoupper(trim((string) ($value ?? '')));
        return in_array($string, ['S', 'SI', 'SÍ', '1', 'TRUE', 'VERDADERO'], true);
    }

    private function toNullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));
        return $string !== '' ? $string : null;
    }

    private function toDv(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return (string) (int) $value;
        }

        $string = trim((string) $value);
        return $string !== '' ? $string : null;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function toIntOrZero(mixed $value): int
    {
        return $this->toIntOrNull($value) ?? 0;
    }

    /**
     * @param array<int, string> $errors
     */
    private function toCoordinate(mixed $value, string $field, int $rowNumber, array &$errors): float|false|null
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));
        if (!is_numeric($normalized)) {
            $errors[] = "Fila {$rowNumber}: {$field} inválida. Debe ser numérica.";
            return false;
        }

        $number = (float) $normalized;
        $min = $field === 'latitud' ? -90 : -180;
        $max = $field === 'latitud' ? 90 : 180;
        if ($number < $min || $number > $max) {
            $errors[] = "Fila {$rowNumber}: {$field} fuera de rango permitido.";
            return false;
        }

        return round($number, 7);
    }
}
