<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionInformeCursosExport
{
    public function download(array $informe, Collection $catalogo, array $filtros): StreamedResponse
    {
        $book = $this->workbook($informe, $catalogo, $filtros);

        return response()->streamDownload(function () use ($book): void {
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, 'dotacion_cursos_por_nivel_'.$filtros['anio'].'_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function workbook(array $informe, Collection $catalogo, array $filtros): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Cursos por nivel');
        $sheet->setShowGridlines(false);
        $headers = array_merge(['RBD', 'Establecimiento', 'Comuna'], array_column($informe['columnas'], 'label'), ['Total cursos', 'Detalle grupos combinados']);
        $last = Coordinate::stringFromColumnIndex(count($headers));
        $totalIndex = count($headers) - 1;
        $totalColumn = Coordinate::stringFromColumnIndex($totalIndex);
        $text = fn ($cell, $value) => $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
        $sheet->mergeCells('A1:'.$last.'1');
        $text('A1', 'DOTACIÓN '.$filtros['anio'].' · CURSOS POR NIVEL');
        $sheet->mergeCells('A2:'.$last.'2');
        $text('A2', 'Establecimientos: '.count($informe['filas']).' | Comuna: '.($filtros['comuna'] ?: 'Todas').' | Búsqueda: '.($filtros['q'] ?: 'Sin filtro'));
        $sheet->mergeCells('A3:'.$last.'3');
        $text('A3', 'Niveles: '.$catalogo->whereIn('id', $filtros['seleccionados'])->pluck('nombre')->implode(', '));
        $sheet->mergeCells('A4:'.$last.'4');
        $text('A4', 'Cursos activos con matrícula. Cada grupo combinado activo cuenta una vez, en su columna combinada; sus integrantes no se suman por separado. Si selecciona parte de un grupo, se incluye completo una vez.');
        foreach ($headers as $i => $header) { $text(Coordinate::stringFromColumnIndex($i + 1).'6', $header); }
        $row = 7;
        foreach ($informe['filas'] as $fila) {
            foreach (['A' => 'rbd', 'B' => 'nombre', 'C' => 'comuna'] as $column => $key) { $text($column.$row, $fila[$key]); }
            $i = 4;
            foreach (array_keys($informe['columnas']) as $key) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($i++).$row, $fila['conteos'][$key]);
            }
            $sheet->setCellValue($totalColumn.$row, $fila['total']);
            $text($last.$row, $fila['detalle_combinados']);
            $row++;
        }
        if ($informe['filas'] === []) {
            $text('A7', 'Sin establecimientos para los filtros aplicados.');
            $row++;
        }
        $lastData = $row - 1;
        $text('B'.$row, 'TOTAL');
        $i = 4;
        foreach (array_keys($informe['columnas']) as $key) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i++).$row, $informe['totales'][$key]);
        }
        $sheet->setCellValue($totalColumn.$row, $informe['total']);
        foreach (['A1:'.$last.'1', 'A6:'.$last.'6'] as $range) {
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3D91']],
            ]);
        }
        $sheet->getStyle('A1')->getFont()->setSize(16);
        $sheet->getStyle('A'.$row.':'.$last.$row)->getFont()->setBold(true);
        $sheet->getStyle('A'.$row.':'.$last.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF6FF');
        $sheet->getStyle('A1:'.$last.$row)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle('D6:'.$totalColumn.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('D7:'.$totalColumn.$row)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(32);
        $sheet->getRowDimension(3)->setRowHeight(48);
        $sheet->getRowDimension(4)->setRowHeight(48);
        $sheet->getRowDimension(6)->setRowHeight(65);
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(44);
        $sheet->getColumnDimension('C')->setWidth(23);
        for ($i = 4; $i <= $totalIndex; $i++) { $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(22); }
        $sheet->getColumnDimension($last)->setWidth(65);
        $sheet->freezePane('D7');
        $sheet->setAutoFilter('A6:'.$last.$lastData);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(6, 6);

        return $book;
    }
}
