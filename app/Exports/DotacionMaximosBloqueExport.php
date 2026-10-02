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
        'Hrs de contrato vigente Plan general, trabajo colaborativo PIE y funciones normativas',
        'Máximo Plan general, trabajo colaborativo PIE y funciones normativas',
        'Hrs de contrato vigente Educación Parvularia y trabajo colaborativo PIE NT1/NT2',
        'Máximo Educación Parvularia y trabajo colaborativo PIE NT1/NT2',
        'Hrs de contrato vigente PIE especializado: coordinación PIE y educadoras diferenciales',
        'Máximo PIE especializado: coordinación PIE y educadoras diferenciales',
    ];
    public const COLUMNAS = ['E' => 'max_horas_bloque_1', 'G' => 'max_horas_bloque_2', 'I' => 'max_horas_bloque_3'];
    public const CONTRATOS = ['D' => 'contrato_vigente_bloque_1', 'F' => 'contrato_vigente_bloque_2', 'H' => 'contrato_vigente_bloque_3'];
    public const HEADERS_ANTERIORES = [
        'RBD', 'Nombre Establecimiento', 'Matrícula registrada',
        'Máximo Plan general, trabajo colaborativo PIE y funciones normativas',
        'Máximo Educación Parvularia y trabajo colaborativo PIE NT1/NT2',
        'Máximo PIE especializado: coordinación PIE y educadoras diferenciales',
    ];
    public const COLUMNAS_ANTERIORES = ['D' => 'max_horas_bloque_1', 'E' => 'max_horas_bloque_2', 'F' => 'max_horas_bloque_3'];

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
            foreach (self::CONTRATOS as $col => $campo) {
                $sheet->setCellValue($col.$row, $fila[$campo]);
                $sheet->getComment($col.$row)->getText()->createTextRun(
                    'Período contractual: '.$fila['periodo_contractual'].'. Año contractual de referencia: '.($anio - 1).'. Año de dotación a cargar: '.$anio.'. Valor de referencia; no se importa.'
                );
            }
            foreach (self::COLUMNAS as $col => $campo) {
                $sheet->setCellValue($col.$row, $fila[$campo]);
                $sheet->getCell($col.$row)->getDataValidation()->setType(DataValidation::TYPE_DECIMAL)
                    ->setOperator(DataValidation::OPERATOR_BETWEEN)->setFormula1('0')->setFormula2('9999')
                    ->setAllowBlank(true)->setShowErrorMessage(true)->setErrorTitle('Máximo no válido')
                    ->setError('Ingrese entre 0 y 9999 horas, con hasta dos decimales.');
            }
        }
        $last = max(1, $filas->count() + 1);
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3D91']],
        ]);
        $sheet->getStyle('A1:I'.$last)->getAlignment()->setVertical('center')->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(85);
        foreach (['A' => 15, 'B' => 60, 'C' => 23, 'D' => 35, 'E' => 35, 'F' => 35, 'G' => 35, 'H' => 35, 'I' => 35] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
        if ($last > 1) {
            $sheet->getStyle('D2:I'.$last)->getNumberFormat()->setFormatCode('0.##');
            foreach (self::COLUMNAS as $col => $campo) {
                $sheet->getStyle($col.'2:'.$col.$last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF6FF');
            }
            foreach (self::CONTRATOS as $col => $campo) {
                $sheet->getStyle($col.'2:'.$col.$last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
        }
        $sheet->freezePane('D2')->setAutoFilter('A1:I'.$last)->setShowGridlines(false);
        $instructions = $book->createSheet()->setTitle('Instrucciones');
        $instructions->setCellValue('A1', 'Año')->setCellValue('B1', $anio);
        $instructions->setCellValue('A2', 'Año de contratos vigentes de referencia')->setCellValue('B2', $anio - 1);
        $instructions->setCellValue('A3', 'Edite sólo los máximos de las columnas E, G e I (azul claro). Nombre, matrícula y contratos vigentes en D, F y H son referencias y no se importan.');
        $instructions->setCellValue('A4', 'Incluye sólo establecimientos con cursos activos en el año seleccionado, excluyendo salas cuna. Los máximos guardados se precargan y los no configurados aparecen vacíos.');
        $instructions->setCellValue('A5', 'Cada celda vacía conserva el máximo actual de ese bloque. Use entre 0 y 9999 horas y hasta dos decimales; cero establece un máximo de cero.');
        $instructions->setCellValue('A6', 'La carga se guarda sólo para el año seleccionado, sin modificar las asignaciones, reservas ni otras configuraciones.');
        $instructions->setCellValue('A7', 'Un máximo inferior a la necesidad obligatoria mantiene el bloqueo de asignación del proceso guiado. Revise la necesidad antes de reducir los máximos.');
        $instructions->setCellValue('A8', 'Contrato vigente: último período contractual del año anterior al seleccionado ('.($anio - 1).'), distribuido por bloque. Si no hay contratos de ese año, se muestra cero; no se utilizan contratos de otros años.');
        $instructions->setCellValue('A9', 'El comentario de cada celda de contrato indica el mes/año de origen. Excluye asistentes y cupos por contratar; no descuenta reservas como si fueran reducción del contrato vigente.');
        $instructions->getColumnDimension('A')->setWidth(110);
        $instructions->getColumnDimension('B')->setWidth(15);
        $instructions->getStyle('A1:B9')->getAlignment()->setWrapText(true);
        foreach (range(3, 9) as $row) {
            $instructions->getRowDimension($row)->setRowHeight(40);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }
}
