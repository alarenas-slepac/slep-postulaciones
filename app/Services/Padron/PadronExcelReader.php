<?php

namespace App\Services\Padron;

use App\Support\RutChile;
use DateTimeImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class PadronExcelReader
{
    public const REQUIRED = ['rut', 'nombre', 'fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media', 'rbd', 'bienios'];

    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $info = $reader->listWorksheetInfo($path)[0] ?? null;
        if (! $info || $info['totalRows'] < 2 || $info['totalRows'] > 50001 || $info['totalColumns'] > 100) {
            throw ValidationException::withMessages(['excel' => 'La primera hoja debe contener entre 1 y 50.000 registros y hasta 100 columnas.']);
        }
        $reader->setLoadSheetsOnly($info['worksheetName']);
        $rows = [];
        $headers = [];
        for ($start = 2; $start <= $info['totalRows']; $start += 500) {
            $end = min($start + 499, $info['totalRows']);
            $reader->setReadFilter(new class($start, $end) implements IReadFilter {
                public function __construct(private int $start, private int $end) {}
                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row === 1 || ($row >= $this->start && $row <= $this->end);
                }
            });
            $book = $reader->load($path);
            try {
                $sheet = $book->getSheet(0);
                if ($start === 2) {
                    $headers = array_map(fn ($value) => self::header((string) $value), $sheet->rangeToArray('A1:'.$info['lastColumnLetter'].'1', null, false, false)[0]);
                    $nonempty = array_filter($headers);
                    if (count($nonempty) !== count(array_unique($nonempty))) {
                        throw ValidationException::withMessages(['excel' => 'Existen encabezados repetidos en la primera hoja.']);
                    }
                    $missing = array_diff(self::REQUIRED, $headers);
                    if ($missing) {
                        throw ValidationException::withMessages(['excel' => 'Faltan columnas: '.implode(', ', $missing)]);
                    }
                }
                $values = $sheet->rangeToArray('A'.$start.':'.$info['lastColumnLetter'].$end, null, false, false);
                foreach ($values as $offset => $cells) {
                    if (! count(array_filter($cells, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                        continue;
                    }
                    $raw = [];
                    foreach ($headers as $index => $header) {
                        if (in_array($header, [...self::REQUIRED, 'tramo', 'fecha_antiguedad'], true)) {
                            $raw[$header] = $cells[$index] ?? null;
                        }
                    }
                    $rows[] = $this->normalize($raw, $start + $offset);
                }
            } finally {
                $book->disconnectWorksheets();
            }
        }
        if (! $rows) {
            throw ValidationException::withMessages(['excel' => 'El archivo no contiene registros para revisar.']);
        }
        return $rows;
    }

    public static function header(string $value): string
    {
        $key = Str::of($value)->ascii()->lower()->trim()->replaceMatches('/[\s_]+/', '_')->toString();
        return in_array($key, ['tramo_docente', 'tramo'], true) ? 'tramo' : $key;
    }

    public function normalize(array $raw, int $line): array
    {
        $errors = [];
        $data = [];
        foreach ($raw as $key => $value) {
            $data[$key] = trim((string) $value);
            if (str_starts_with($data[$key], '=')) {
                $errors[] = 'No se aceptan fórmulas: '.$key.'.';
            }
            if (mb_strlen($data[$key]) > 255) {
                $errors[] = 'El campo '.$key.' supera 255 caracteres.';
                $data[$key] = mb_substr($data[$key], 0, 255);
            }
        }
        $rut = strtoupper(preg_replace('/[.\s-]/', '', $data['rut'] ?? ''));
        if (! preg_match('/^[0-9]{7,8}[0-9K]$/', $rut) || RutChile::dv((int) substr($rut, 0, -1)) !== substr($rut, -1)) {
            $errors[] = 'RUT inválido o dígito verificador incorrecto.';
        }
        $data['rut'] = mb_substr($rut, 0, 32);
        foreach (['nombre', 'tipocontrato', 'estatuto', 'escalafon'] as $key) {
            if (($data[$key] ?? '') === '') {
                $errors[] = 'Falta '.$key.'.';
            }
        }
        foreach (['anio', 'mes', 'rbd', 'jornada', 'jornada_basica', 'jornada_media', 'bienios'] as $key) {
            $value = $data[$key] ?? '';
            $required = in_array($key, ['anio', 'mes', 'rbd', 'jornada'], true);
            $value = str_replace(',', '.', $value);
            if ($value === '' && ! $required) {
                $data[$key] = null;
                continue;
            }
            $maximum = $key === 'rbd' ? 2147483647 : 65535;
            if (! is_numeric($value) || (float) $value < 0 || floor((float) $value) !== (float) $value || (float) $value > $maximum) {
                $errors[] = $key.': debe ser un entero no negativo válido.';
                $data[$key] = null;
                continue;
            }
            $data[$key] = (int) $value;
        }
        if (($data['anio'] ?? 0) < 2000 || ($data['anio'] ?? 0) > 2100 || ($data['mes'] ?? 0) < 1 || ($data['mes'] ?? 0) > 12) {
            $errors[] = 'Período inválido.';
        }
        if (($data['rbd'] ?? 0) < 1) {
            $errors[] = 'RBD inválido.';
        }
        if (($data['jornada_basica'] ?? 0) + ($data['jornada_media'] ?? 0) > ($data['jornada'] ?? 0)) {
            $errors[] = 'Jornada Básica + Media supera la jornada total.';
        }
        foreach (['fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'fecha_antiguedad'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            $data[$key] = $this->date($value);
            if ($value !== '' && $data[$key] === null) {
                $errors[] = 'Fecha inválida: '.$key.'.';
            }
        }
        if (! empty($data['fecha_ingreso']) && ! empty($data['fecha_termino']) && $data['fecha_termino'] < $data['fecha_ingreso']) {
            $errors[] = 'Fecha término anterior a fecha ingreso.';
        }
        return ['fila_excel' => $line, 'datos' => $data, 'observaciones' => $errors];
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 0 && (float) $value < 73416) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && (! $errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        return null;
    }
}
