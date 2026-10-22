<?php

namespace App\Services\Votaciones;

use App\Support\Votaciones\RutConsulta;
use DateTimeImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;
use ZipArchive;

class PadronConsultaExcel
{
    public const PADRON = ['RUT', 'NOMBRE', 'RBD', 'ESTABLECIMIENTO', 'TIPO_DE_CONTRATO', 'GRUPO', 'RBD_LUGAR_DE_VOTACION', 'LUGAR_DE_VOTACION'];

    public const LOCALES = ['RBD', 'ESTABLECIMIENTO', 'RBD_LUGAR_DE_VOTACION', 'LUGAR_DE_VOTACION', 'HORARIO', 'DIA'];

    /** Analiza sin escribir en la BD, sin calcular fórmulas y sin registrar datos personales. */
    public function analizar(string $path): array
    {
        $this->validarContenedor($path);
        $book = null;
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true)->setReadEmptyCells(false);
            $sheets = [];
            foreach ($reader->listWorksheetInfo($path) as $info) {
                $name = $this->clave($info['worksheetName']);
                if (in_array($name, ['PADRON', 'PADRON_VOTACION', 'LOCALES_VOTACION'], true)) {
                    $kind = $name === 'LOCALES_VOTACION' ? 'locales' : 'padron';
                    if (isset($sheets[$kind]) || $info['totalRows'] > ($kind === 'padron' ? 20001 : 2001) || $info['totalColumns'] > 30) {
                        throw new InvalidArgumentException('Hay hojas repetidas o se supera el límite de 20.000 votantes, 2.000 locales o 30 columnas.');
                    }
                    $sheets[$kind] = $info['worksheetName'];
                }
            }
            if (count($sheets) !== 2) {
                throw new InvalidArgumentException('El Excel debe tener las hojas PADRON VOTACIÓN (o PADRON) y LOCALES VOTACION.');
            }
            $reader->setLoadSheetsOnly(array_values($sheets));
            $book = $reader->load($path);
            $localRows = $this->filas($book->getSheetByName($sheets['locales']), self::LOCALES);
            $voterRows = $this->filas($book->getSheetByName($sheets['padron']), self::PADRON);
            // El archivo de origen usa MM/DD/YYYY. Se detecta sólo con días > 12;
            // si no hay evidencia, las fechas con / se interpretan como DD/MM/YYYY.
            $monthFirst = false;
            foreach ($localRows as $row) {
                if (preg_match('~^\d{1,2}/(\d{1,2})/\d{4}$~', trim((string) $row['DIA']), $m) && (int) $m[1] > 12) {
                    $monthFirst = true;
                }
            }

            $errors = [];
            $totalErrors = 0;
            $addError = static function (string $message) use (&$errors, &$totalErrors): void {
                $totalErrors++;
                if (count($errors) < 100) {
                    $errors[] = $message;
                }
            };
            $locals = $voters = [];
            $duplicateLocals = $duplicateVoters = $withoutGroup = 0;
            foreach ($localRows as $number => $row) {
                try {
                    $origin = $this->rbd($row['RBD'], 'RBD');
                    $target = $this->rbd($row['RBD_LUGAR_DE_VOTACION'], 'RBD_LUGAR_DE_VOTACION');
                    $local = [
                        'rbd_origen' => $origin,
                        'establecimiento' => $this->texto($row['ESTABLECIMIENTO'], 'ESTABLECIMIENTO'),
                        'rbd_local' => $target,
                        'lugar_votacion' => $this->texto($row['LUGAR_DE_VOTACION'], 'LUGAR_DE_VOTACION'),
                        'dia' => $this->dia($row['DIA'], $monthFirst),
                        'hora_inicio' => $this->hora($row['HORARIO']),
                    ];
                    $key = $origin.'|'.$target;
                    if (isset($locals[$key])) {
                        if ($locals[$key]['dia'] !== $local['dia'] || $locals[$key]['hora_inicio'] !== $local['hora_inicio']) {
                            $addError("LOCALES VOTACION, filas {$locals[$key]['fila_excel']} y {$number}: el mismo RBD de origen y local tiene distinto día u horario.");
                        } else {
                            $duplicateLocals++;
                        }
                        continue;
                    }
                    $locals[$key] = $local + ['fila_excel' => $number];
                } catch (InvalidArgumentException $e) {
                    $addError("LOCALES VOTACION, fila {$number}: {$e->getMessage()}");
                }
            }
            foreach ($voterRows as $number => $row) {
                try {
                    $rut = RutConsulta::normalizar($row['RUT']);
                    if ($rut === null) {
                        throw new InvalidArgumentException('RUT inválido; debe incluir su dígito verificador correcto.');
                    }
                    $origin = $this->rbd($row['RBD'], 'RBD');
                    $target = $this->rbd($row['RBD_LUGAR_DE_VOTACION'], 'RBD_LUGAR_DE_VOTACION');
                    $this->texto($row['LUGAR_DE_VOTACION'], 'LUGAR_DE_VOTACION');
                    $key = $origin.'|'.$target;
                    if (! isset($locals[$key])) {
                        throw new InvalidArgumentException('No existe la combinación RBD + RBD_LUGAR_DE_VOTACION en LOCALES VOTACION.');
                    }
                    $group = trim((string) $row['GRUPO']);
                    if (mb_strlen($group) > 100) {
                        throw new InvalidArgumentException('GRUPO supera los 100 caracteres.');
                    }
                    $voter = [
                        'rut' => $rut,
                        'nombre' => $this->texto($row['NOMBRE'], 'NOMBRE'),
                        'rbd' => $origin,
                        'establecimiento' => $this->texto($row['ESTABLECIMIENTO'], 'ESTABLECIMIENTO'),
                        'tipo_contrato' => $this->texto($row['TIPO_DE_CONTRATO'], 'TIPO_DE_CONTRATO', 150),
                        'grupo' => $group === '' ? null : $group,
                        'local_key' => $key,
                    ];
                    $hash = hash('sha256', json_encode($voter, JSON_THROW_ON_ERROR));
                    if (isset($voters[$hash])) {
                        $duplicateVoters++;
                        continue;
                    }
                    $voters[$hash] = $voter + ['fila_hash' => $hash];
                    $withoutGroup += $group === '' ? 1 : 0;
                } catch (InvalidArgumentException $e) {
                    $addError("PADRON VOTACIÓN, fila {$number}: {$e->getMessage()}");
                }
            }
            if (! $voterRows || ! $localRows) {
                $addError('Ambas hojas deben contener datos, además de los encabezados.');
            }

            return [
                'padron' => array_values($voters), 'locales' => $locals, 'errores' => $errors,
                'resumen' => [
                    'filas_padron' => count($voterRows), 'filas_locales' => count($localRows),
                    'votantes' => count($voters), 'locales' => count($locals), 'errores' => $totalErrors,
                    'duplicados_padron' => $duplicateVoters, 'duplicados_locales' => $duplicateLocals,
                    'sin_grupo' => $withoutGroup, 'fechas_mes_primero' => $monthFirst,
                ],
            ];
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable) {
            throw new InvalidArgumentException('No fue posible leer el Excel. Verifica que sea un archivo .xlsx válido, sin contraseña.');
        } finally {
            $book?->disconnectWorksheets();
        }
    }

    private function filas(Worksheet $sheet, array $expected): array
    {
        $columns = [];
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $lastRow = $sheet->getHighestDataRow();
        for ($column = 1; $column <= $lastColumn; $column++) {
            $cell = $sheet->getCell([$column, 1]);
            $header = $this->clave((string) $cell->getValue());
            $header = $header === 'TIPO_DE_CONTRAT0' ? 'TIPO_DE_CONTRATO' : $header;
            if ($header !== '') {
                if (isset($columns[$header])) {
                    throw new InvalidArgumentException('Hay encabezados repetidos en la hoja '.$sheet->getTitle().'.');
                }
                $columns[$header] = $column;
            }
        }
        if (array_diff($expected, array_keys($columns))) {
            throw new InvalidArgumentException('Faltan columnas en '.$sheet->getTitle().': '.implode(', ', array_diff($expected, array_keys($columns))).'.');
        }
        $rows = [];
        for ($number = 2; $number <= $lastRow; $number++) {
            $row = [];
            foreach ($expected as $header) {
                $cell = $sheet->getCell([$columns[$header], $number]);
                if ($cell->isFormula()) {
                    throw new InvalidArgumentException("Hoja {$sheet->getTitle()}, fila {$number}: reemplaza las fórmulas por valores antes de importar.");
                }
                $row[$header] = $cell->getValue();
            }
            if (array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                $rows[$number] = $row;
            }
        }

        return $rows;
    }

    private function clave(string $value): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper(Str::ascii(trim($value)))), '_');
    }

    private function texto(mixed $value, string $column, int $max = 255): string
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $max) {
            throw new InvalidArgumentException("{$column} es obligatorio y admite hasta {$max} caracteres.");
        }

        return $text;
    }

    private function rbd(mixed $value, string $column): int
    {
        if (! preg_match('/^[0-9]{1,7}$/', trim((string) $value))) {
            throw new InvalidArgumentException("{$column} debe ser un identificador numérico de hasta 7 dígitos.");
        }

        return (int) $value;
    }

    private function dia(mixed $value, bool $monthFirst): string
    {
        if ((is_int($value) || is_float($value)) && $value >= 1 && $value < 100000) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }
        $raw = trim((string) $value);
        $formats = ['Y-m-d', 'd-m-Y', $monthFirst ? 'm/d/Y' : 'd/m/Y'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $raw);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        throw new InvalidArgumentException('DIA debe ser una fecha válida con año (preferentemente AAAA-MM-DD).');
    }

    private function hora(mixed $value): string
    {
        if ((is_int($value) || is_float($value)) && $value >= 0 && $value < 1) {
            return Date::excelToDateTimeObject($value)->format('H:i:s');
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(?:HRS?\.?|HORAS)?$/i', trim((string) $value), $m)
            && (int) $m[1] < 24 && (int) $m[2] < 60 && (int) ($m[3] ?? 0) < 60) {
            return sprintf('%02d:%02d:%02d', $m[1], $m[2], $m[3] ?? 0);
        }
        throw new InvalidArgumentException('HORARIO debe indicar una hora de inicio válida, por ejemplo 09:00.');
    }

    private function validarContenedor(string $path): void
    {
        $zip = new ZipArchive;
        if (! is_file($path) || filesize($path) > 10 * 1024 * 1024 || $zip->open($path) !== true) {
            throw new InvalidArgumentException('Sube un archivo .xlsx de hasta 10 MB.');
        }
        try {
            $bytes = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $bytes += $zip->statIndex($i)['size'];
                if ($bytes > 64 * 1024 * 1024 || $zip->numFiles > 3000) {
                    throw new InvalidArgumentException('El contenido descomprimido del Excel supera el límite permitido.');
                }
            }
        } finally {
            $zip->close();
        }
    }
}
