<?php

namespace App\Services\Padron;

use DateTimeImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class PadronDatosExcel
{
    public const CAMPOS = [
        'fecha_nacimiento' => ['titulo' => 'Fecha de nacimiento', 'columna' => 'FECHA_NACIMIENTO'],
        'fecha_antiguedad' => ['titulo' => 'Fecha de antigüedad', 'columna' => 'fecha_antiguedad'],
        'tramo' => ['titulo' => 'Tramo', 'columna' => 'Tramo'],
        'bienios' => ['titulo' => 'Bienios', 'columna' => 'Bienios'],
    ];

    public function leer(string $path, array $campos): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $info = $reader->listWorksheetInfo($path)[0] ?? null;
        if (! $info || $info['totalRows'] < 2 || $info['totalRows'] > 10001 || $info['totalColumns'] > 30) {
            $this->fallar('La primera hoja debe contener entre 1 y 10.000 filas de datos y hasta 30 columnas.');
        }
        $reader->setLoadSheetsOnly($info['worksheetName']);
        $headers = [];
        $filas = [];
        for ($inicio = 2; $inicio <= $info['totalRows']; $inicio += 500) {
            $fin = min($inicio + 499, $info['totalRows']);
            $reader->setReadFilter(new class($inicio, $fin) implements IReadFilter {
                public function __construct(private int $inicio, private int $fin) {}
                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row === 1 || ($row >= $this->inicio && $row <= $this->fin);
                }
            });
            $book = $reader->load($path);
            try {
                $sheet = $book->getSheet(0);
                if ($inicio === 2) {
                    $headers = array_map(function ($valor) {
                        $key = PadronExcelReader::header((string) $valor);
                        return $key === 'fechaing' ? 'fecha_antiguedad' : $key;
                    }, $sheet->rangeToArray('A1:'.$info['lastColumnLetter'].'1', null, false, false)[0]);
                    $noVacios = array_filter($headers);
                    if (count($noVacios) !== count(array_unique($noVacios))) {
                        $this->fallar('Hay encabezados repetidos; fechaing y fecha_antiguedad corresponden al mismo campo.');
                    }
                    $faltantes = array_diff(['rut', ...$campos], $headers);
                    if ($faltantes) {
                        $this->fallar('Faltan columnas para los campos seleccionados: '.implode(', ', $faltantes).'.');
                    }
                }
                foreach ($sheet->rangeToArray('A'.$inicio.':'.$info['lastColumnLetter'].$fin, null, false, false) as $offset => $cells) {
                    if (! array_filter($cells, fn ($v) => $v !== null && trim((string) $v) !== '')) { continue; }
                    $linea = $inicio + $offset;
                    $raw = array_combine($headers, array_map(fn ($v) => trim((string) $v), $cells));
                    $original = $raw['rut'];
                    if (mb_strlen($original) > 32 || str_starts_with($original, '=')) {
                        $this->fallar('Fila '.$linea.': RUT demasiado largo o con fórmula.');
                    }
                    try {
                        $rut = app(PadronIndividualService::class)->rut($original);
                    } catch (ValidationException) {
                        $filas[] = ['fila' => $linea, 'rut' => null, 'rut_excel' => $original, 'datos' => [], 'motivo' => 'RUT inválido o dígito verificador incorrecto.'];
                        continue;
                    }
                    $datos = [];
                    $errores = [];
                    foreach ($campos as $campo) {
                        $valor = $raw[$campo];
                        if ($valor === '') { continue; } // Vacío conserva el valor existente, incluido cero.
                        if (str_starts_with($valor, '=') || mb_strlen($valor) > 100) {
                            $errores[] = 'Fila '.$linea.': '.$campo.' contiene una fórmula o excede 100 caracteres.';
                            continue;
                        }
                        try {
                            $datos[$campo] = $this->valor($campo, $valor, $linea);
                        } catch (ValidationException $error) {
                            $errores[] = $error->errors()['excel_actualizacion'][0];
                        }
                    }
                    $filas[] = ['fila' => $linea, 'rut' => $rut, 'rut_excel' => $original, 'datos' => $datos, 'errores' => $errores];
                }
            } finally {
                $book->disconnectWorksheets();
                unset($book);
            }
        }
        if (! $filas) { $this->fallar('El Excel no contiene registros para actualizar.'); }
        return $filas;
    }

    private function valor(string $campo, string $valor, int $linea): string|int
    {
        if (in_array($campo, ['fecha_nacimiento', 'fecha_antiguedad'], true)) {
            $fecha = null;
            if (is_numeric($valor) && (float) $valor > 0 && (float) $valor < 73416) {
                $fecha = ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d');
            } else {
                foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
                    $date = DateTimeImmutable::createFromFormat('!'.$formato, $valor);
                    $errors = DateTimeImmutable::getLastErrors();
                    if ($date && (! $errors || ! ($errors['warning_count'] + $errors['error_count']))) {
                        $fecha = $date->format('Y-m-d');
                        break;
                    }
                }
            }
            if ($fecha === null || ($campo === 'fecha_nacimiento' && $fecha >= now()->format('Y-m-d'))) {
                $this->fallar('Fila '.$linea.': '.$campo.' debe ser una fecha válida (YYYY-MM-DD, DD/MM/YYYY o fecha Excel). La fecha de nacimiento debe ser anterior a hoy.');
            }
            return $fecha;
        }
        if ($campo === 'bienios') {
            if (! is_numeric($valor) || (float) $valor < 0 || (float) $valor > 50 || floor((float) $valor) !== (float) $valor) {
                $this->fallar('Fila '.$linea.': Bienios debe ser un entero entre 0 y 50.');
            }
            return (int) $valor;
        }
        $key = preg_replace('/[\s_]+/', '', Str::lower(Str::ascii($valor)));
        $tramos = ['acceso' => 'Acceso', 'inicial' => 'Inicial', 'temprano' => 'Temprano', 'avanzado' => 'Avanzado',
            'experto1' => 'Experto 1', 'expertoi' => 'Experto 1', 'experto2' => 'Experto 2', 'expertoii' => 'Experto 2', 'sintramo' => 'Sin tramo'];
        if (! isset($tramos[$key])) {
            $this->fallar('Fila '.$linea.': Tramo debe ser Acceso, Inicial, Temprano, Avanzado, Experto 1, Experto 2 o Sin tramo.');
        }
        return $tramos[$key];
    }

    public function plantilla(array $campos): Spreadsheet
    {
        $headers = ['rut', ...array_map(fn ($campo) => self::CAMPOS[$campo]['columna'], $campos)];
        $book = $this->documento($headers, [], 'Actualizar datos');
        $instrucciones = $book->createSheet();
        $instrucciones->setTitle('Instrucciones');
        $instrucciones->fromArray([
            ['Complete la primera hoja con una fila por RUT. No hay datos de ejemplo para importar.'],
            ['Sólo se actualizan las líneas contractuales del último mes cargado al momento de ejecutar la actualización.'],
            ['Un RUT con varios contratos en ese mes actualiza todas sus líneas. Otros meses se conservan.'],
            ['Las celdas vacías conservan sus valores actuales. Para Bienios, cero es un valor válido.'],
            ['Fechas: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY o fecha Excel. fechaing se admite como alias de fecha_antiguedad.'],
            ['Tramo: Acceso, Inicial, Temprano, Avanzado, Experto 1, Experto 2 o Sin tramo. Bienios: entero de 0 a 50.'],
            ['Los RUT no encontrados se omiten y se incluyen en un informe Excel descargable.'],
            ['Seleccione en el modal los mismos campos que completará. Las demás columnas no se actualizan. No use fórmulas.'],
            ['Se conservan los IDs, contratos, jornadas, vigencias y asignaciones. Se auditan los cambios.'],
        ], null, 'A1');
        $instrucciones->getColumnDimension('A')->setWidth(110);
        $instrucciones->getStyle('A1:A9')->getAlignment()->setWrapText(true);
        foreach ($campos as $index => $campo) {
            if (str_starts_with($campo, 'fecha_')) {
                $book->getSheet(0)->getStyle(Coordinate::stringFromColumnIndex($index + 2).':'.Coordinate::stringFromColumnIndex($index + 2))->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }
        }
        $book->setActiveSheetIndex(0);
        return $book;
    }

    public function documento(array $headers, array $filas, string $titulo): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle($titulo);
        // Texto explícito: ni datos del archivo ni motivos se interpretan como fórmulas.
        foreach ([$headers, ...$filas] as $index => $fila) {
            foreach (array_values($fila) as $col => $valor) {
                $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($col + 1).($index + 1), (string) $valor, DataType::TYPE_STRING);
            }
        }
        $last = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:'.$last.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D6EFD');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$last.max(1, count($filas) + 1));
        foreach (range(1, count($headers)) as $col) { $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth($col === 3 ? 70 : 25); }
        return $book;
    }

    private function fallar(string $mensaje): never
    {
        throw ValidationException::withMessages(['excel_actualizacion' => $mensaje]);
    }
}
