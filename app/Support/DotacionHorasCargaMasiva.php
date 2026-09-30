<?php

namespace App\Support;

use App\Models\Establecimiento;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DotacionHorasCargaMasiva
{
    /** Valida todo el archivo antes de permitir guardar una sola fila. */
    public static function leer(string $path, int $anio, string $titulo, array $headers, array $columnas, float $maximo): array
    {
        try {
            $book = IOFactory::createReader('Xlsx')->load($path);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['archivo' => 'No se pudo leer el archivo Excel. Descargue y utilice la plantilla XLSX.']);
        }
        try {
            $sheet = $book->getSheetByName($titulo.' '.$anio);
            $instructions = $book->getSheetByName('Instrucciones');
            if (! $sheet || ! $instructions || (string) $instructions->getCell('B1')->getValue() !== (string) $anio) {
                throw ValidationException::withMessages(['archivo' => 'El año o tipo de la plantilla no coincide con la carga seleccionada. Descargue la plantilla correspondiente al año '.$anio.'.']);
            }
            foreach ($headers as $i => $header) {
                if (trim((string) $sheet->getCell([$i + 1, 1])->getValue()) !== $header) {
                    throw ValidationException::withMessages(['archivo' => 'Conserve las columnas y los encabezados de la plantilla.']);
                }
            }
            $last = $sheet->getHighestDataRow();
            if ($last > 10001) {
                throw ValidationException::withMessages(['archivo' => 'El archivo supera el máximo de 10.000 establecimientos.']);
            }
            $establecimientos = Establecimiento::query()->get(['id', 'rbd'])->groupBy('rbd');
            $errores = [];
            $cambios = [];
            $vistos = [];
            $omitidas = 0;
            for ($row = 2; $row <= $last; $row++) {
                $rbdCell = $sheet->getCell('A'.$row);
                $rbd = trim((string) $rbdCell->getValue());
                $valores = [];
                foreach ($columnas as $col => $campo) {
                    $valores[$campo] = trim((string) $sheet->getCell($col.$row)->getValue());
                }
                if ($rbd === '' && count(array_filter($valores, fn ($v) => $v !== '')) === 0) {
                    continue;
                }
                $coincidencias = ctype_digit($rbd) ? $establecimientos->get((int) $rbd, collect()) : collect();
                if ($rbdCell->getDataType() === DataType::TYPE_FORMULA || $coincidencias->count() !== 1) {
                    $errores[] = 'Fila '.$row.': RBD inexistente, inválido o duplicado en el catálogo.';
                    continue;
                }
                $id = $coincidencias->sole()->id;
                if (isset($vistos[$id])) {
                    $errores[] = 'Fila '.$row.': el RBD '.$rbd.' está repetido en el archivo.';
                    continue;
                }
                $vistos[$id] = true;
                $datos = [];
                foreach ($columnas as $col => $campo) {
                    $valor = str_replace(',', '.', $valores[$campo]);
                    if ($valor === '') {
                        continue;
                    }
                    if ($sheet->getCell($col.$row)->getDataType() === DataType::TYPE_FORMULA
                        || ! preg_match('/^\d+(?:\.\d{1,2})?$/D', $valor) || (float) $valor > $maximo) {
                        $errores[] = 'Fila '.$row.' (RBD '.$rbd.'), columna '.$col.': ingrese entre 0 y '.$maximo.' horas, con hasta dos decimales; no use fórmulas.';
                        continue;
                    }
                    $datos[$campo] = round((float) $valor, 2);
                }
                if ($datos === []) {
                    $omitidas++;
                } else {
                    $cambios[$id] = $datos;
                }
            }
            if ($errores !== []) {
                throw ValidationException::withMessages(['archivo' => $errores]);
            }
            if ($cambios === []) {
                throw ValidationException::withMessages(['archivo' => 'No hay horas para cargar. Complete al menos una fila de la plantilla.']);
            }

            return ['cambios' => $cambios, 'omitidas' => $omitidas];
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
