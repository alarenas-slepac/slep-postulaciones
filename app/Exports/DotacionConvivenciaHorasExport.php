<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DotacionConvivenciaHorasExport
{
    public const HEADERS = ['RBD', 'Nombre Establecimiento', 'Matrícula registrada', 'N° Hrs de Coord. Conv. Educativa.'];

    public function workbook(Collection $filas, int $anio): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Convivencia '.$anio);
        $sheet->fromArray(self::HEADERS, null, 'A1');
        foreach ($filas->values() as $i => $fila) {
            $row = $i + 2;
            $sheet->setCellValueExplicit('A'.$row, (string) $fila['rbd'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, $fila['nombre'], DataType::TYPE_STRING);
            $sheet->setCellValue('C'.$row, $fila['matricula']);
            $sheet->setCellValue('D'.$row, $fila['horas']);
            $sheet->getCell('D'.$row)->getDataValidation()
                ->setType(DataValidation::TYPE_DECIMAL)->setOperator(DataValidation::OPERATOR_BETWEEN)
                ->setFormula1('0')->setFormula2('44')->setAllowBlank(true)
                ->setShowErrorMessage(true)->setErrorTitle('Horas no válidas')
                ->setError('Ingrese entre 0 y 44 horas, con hasta dos decimales.');
        }
        $last = max(1, $filas->count() + 1);
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3D91']],
        ]);
        $sheet->getStyle('A1:D'.$last)->getAlignment()->setVertical('center')->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(38);
        foreach (['A' => 15, 'B' => 60, 'C' => 23, 'D' => 32] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        if ($last > 1) {
            $sheet->getStyle('D2:D'.$last)->getNumberFormat()->setFormatCode('0.##');
            $sheet->getStyle('D2:D'.$last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF6FF');
        }
        $sheet->freezePane('C2')->setAutoFilter('A1:D'.$last);
        $sheet->setShowGridlines(false);
        $instructions = $book->createSheet()->setTitle('Instrucciones');
        $instructions->setCellValue('A1', 'Año')->setCellValue('B1', $anio);
        $instructions->setCellValue('A3', 'Edite sólo las horas de la columna D. RBD identifica al establecimiento; nombre y matrícula son referencias.');
        $instructions->setCellValue('A4', 'Las horas provienen de la carga anual vigente; si no existe, de las asignaciones activas, la definición previa o el catálogo, en ese orden.');
        $instructions->setCellValue('A5', 'Use valores entre 0 y 44, con hasta dos decimales. Una celda de horas vacía conserva el valor actual; cero define cero horas.');
        $instructions->setCellValue('A6', 'La matrícula es la suma de los cursos activos del año seleccionado. No se importa ni se modifica.');
        $instructions->setCellValue('A7', 'La carga modifica la necesidad anual de la función. Las asignaciones existentes se conservan; los excesos deben corregirse por separado.');
        $instructions->getColumnDimension('A')->setWidth(110);
        $instructions->getColumnDimension('B')->setWidth(15);
        $instructions->getStyle('A1:B7')->getAlignment()->setWrapText(true);
        foreach (range(3, 7) as $row) {
            $instructions->getRowDimension($row)->setRowHeight(40);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }
}
