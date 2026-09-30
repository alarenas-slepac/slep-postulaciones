<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DotacionMaximosBloqueExport
{
    public const HEADERS = [
        'RBD', 'Nombre Establecimiento', 'Matrícula registrada',
        'Máximo Plan general, trabajo colaborativo PIE y funciones normativas',
        'Máximo Educación Parvularia y trabajo colaborativo PIE NT1/NT2',
        'Máximo PIE especializado: coordinación PIE y educadoras diferenciales',
    ];
    public const COLUMNAS = ['D' => 'max_horas_bloque_1', 'E' => 'max_horas_bloque_2', 'F' => 'max_horas_bloque_3'];

    public function workbook(Collection $filas, int $anio): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Máximos '.$anio);
        $sheet->fromArray(self::HEADERS, null, 'A1');
        foreach ($filas->values() as $i => $fila) {
            $row = $i + 2;
            $sheet->setCellValueExplicit('A'.$row, (string) $fila['rbd'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, $fila['nombre'], DataType::TYPE_STRING);
            $sheet->setCellValue('C'.$row, $fila['matricula']);
            foreach (self::COLUMNAS as $col => $campo) {
                $sheet->setCellValue($col.$row, $fila[$campo]);
                $sheet->getCell($col.$row)->getDataValidation()->setType(DataValidation::TYPE_DECIMAL)
                    ->setOperator(DataValidation::OPERATOR_BETWEEN)->setFormula1('0')->setFormula2('9999')
                    ->setAllowBlank(true)->setShowErrorMessage(true)->setErrorTitle('Máximo no válido')
                    ->setError('Ingrese entre 0 y 9999 horas, con hasta dos decimales.');
            }
        }
        $last = max(1, $filas->count() + 1);
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3D91']],
        ]);
        $sheet->getStyle('A1:F'.$last)->getAlignment()->setVertical('center')->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(70);
        foreach (['A' => 15, 'B' => 60, 'C' => 23, 'D' => 35, 'E' => 35, 'F' => 35] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        if ($last > 1) {
            $sheet->getStyle('D2:F'.$last)->getNumberFormat()->setFormatCode('0.##');
            $sheet->getStyle('D2:F'.$last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF6FF');
        }
        $sheet->freezePane('D2')->setAutoFilter('A1:F'.$last)->setShowGridlines(false);
        $instructions = $book->createSheet()->setTitle('Instrucciones');
        $instructions->setCellValue('A1', 'Año')->setCellValue('B1', $anio);
        $instructions->setCellValue('A3', 'Edite los máximos de las columnas D, E y F. Nombre y matrícula son referencias; la asociación se realiza por RBD.');
        $instructions->setCellValue('A4', 'La plantilla contiene los máximos guardados para el año seleccionado. Los no configurados aparecen vacíos.');
        $instructions->setCellValue('A5', 'Cada celda vacía conserva el máximo actual de ese bloque. Use entre 0 y 9999 horas y hasta dos decimales; cero establece un máximo de cero.');
        $instructions->setCellValue('A6', 'La carga se guarda sólo para el año seleccionado, sin modificar las asignaciones, reservas ni otras configuraciones.');
        $instructions->setCellValue('A7', 'Un máximo inferior a la necesidad obligatoria mantiene el bloqueo de asignación del proceso guiado. Revise la necesidad antes de reducir los máximos.');
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
