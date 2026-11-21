<?php

namespace App\Exports;

use App\Support\DotacionInformeFinanciamiento;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionInformeFinanciamientoExport
{
    public function __construct(private readonly DotacionInformeFinanciamiento $calculador) {}

    public function download(Builder $establecimientos, int $anio): StreamedResponse
    {
        @set_time_limit(240);
        $book = DotacionLecturaCache::ejecutar(function () use ($establecimientos, $anio): Spreadsheet {
            $periodo = $this->calculador->periodoAsistentes();
            $filas = [];
            foreach ((clone $establecimientos)->lazy(10) as $ee) {
                try {
                    $filas[] = $this->calculador->fila($ee, $anio, $periodo);
                } catch (\Throwable $exception) {
                    report($exception);
                    throw ValidationException::withMessages([
                        'export_financiamiento' => 'No se pudo calcular el financiamiento del RBD '.$ee->rbd.'.',
                    ]);
                }
            }

            return $this->workbook($filas, $anio, $periodo);
        });

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'horas_financiamiento_por_rbd_'.$anio.'_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function workbook(array $filas, int $anio, ?array $periodo): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('SGA SLEP Andalién Costa')->setTitle('Horas por financiamiento y RBD '.$anio);
        $detallePadron = $periodo
            ? sprintf('Padrón vigente de AAEE %02d/%d.', $periodo['mes'], $periodo['anio'])
            : 'Padrón de AAEE sin período disponible.';
        $nota = 'Docentes: asignaciones de dotación '.$anio.'. '.$detallePadron;

        foreach (DotacionInformeFinanciamiento::FUENTES as $clave => $nombre) {
            $sheet = $clave === 'general' ? $book->getActiveSheet() : $book->createSheet();
            $horas = array_map(fn ($fila) => [
                'rbd' => $fila['rbd'], 'nombre' => $fila['nombre'], 'horas' => $fila['horas'][$clave],
            ], $filas);
            $this->escribirHoja($sheet, $nombre, $anio, $nota, $horas);
        }

        $otras = [];
        foreach ($filas as $fila) {
            foreach ($fila['otras_aaee'] as $fuente) {
                $otras[] = ['rbd' => $fila['rbd'], 'nombre' => $fila['nombre']] + $fuente;
            }
        }
        if ($otras) {
            $this->escribirHoja($book->createSheet(), 'Otras fuentes AAEE', $anio, $detallePadron.' Incluye fuentes mixtas sin desglose.', $otras, true);
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function escribirHoja(Worksheet $sheet, string $nombre, int $anio, string $nota, array $filas, bool $detalle = false): void
    {
        $horasCol = $detalle ? 'E' : 'D';
        $sheet->setTitle($nombre)->setShowGridlines(false);
        $sheet->mergeCells('A1:'.$horasCol.'1')->setCellValue('A1', 'Horas de '.$nombre.' proyectadas por RBD · '.$anio);
        $sheet->mergeCells('A2:'.$horasCol.'2')->setCellValue('A2', $nota);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0B3D91');
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $encabezados = ['N.º', 'RBD', 'Nombre del establecimiento educacional', ...($detalle ? ['Fuente de financiamiento'] : []), 'Total, horas'];
        $sheet->fromArray($encabezados, null, 'A4');

        $row = 5;
        foreach ($filas as $fila) {
            $sheet->setCellValue('A'.$row, $row - 4);
            $sheet->setCellValueExplicit('B'.$row, $fila['rbd'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$row, $fila['nombre'], DataType::TYPE_STRING);
            if ($detalle) {
                $sheet->setCellValueExplicit('D'.$row, $fila['fuente'], DataType::TYPE_STRING);
            }
            $sheet->setCellValue($horasCol.$row, round($fila['horas'], 2));
            $row++;
        }
        if ($row === 5) {
            $sheet->mergeCells('A5:'.$horasCol.'5')->setCellValue('A5', 'Sin establecimientos para los filtros aplicados.');
            $row++;
        }
        $ultimaFila = $row - 1;
        $sheet->mergeCells('A'.$row.':'.($detalle ? 'D' : 'C').$row)->setCellValue('A'.$row, 'Total');
        $sheet->setCellValue($horasCol.$row, '=SUM('.$horasCol.'5:'.$horasCol.$ultimaFila.')');
        $tabla = $sheet->getStyle('A4:'.$horasCol.$row);
        $tabla->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DEE6F4');
        $tabla->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('424C61');
        foreach ([4, $row] as $filaDestacada) {
            $style = $sheet->getStyle('A'.$filaDestacada.':'.$horasCol.$filaDestacada);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6D3E8');
            $style->getFont()->setBold(true);
        }
        foreach (['A' => 8, 'B' => 13, 'C' => 61, 'D' => $detalle ? 38 : 20, 'E' => 20] as $col => $ancho) {
            $sheet->getColumnDimension($col)->setWidth($ancho);
        }
        $sheet->getStyle($horasCol.'5:'.$horasCol.$row)->getNumberFormat()->setFormatCode('#,##0.##');
        $sheet->setAutoFilter('A4:'.$horasCol.$ultimaFila);
        $sheet->freezePane('A5');
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(4, 4);
    }
}
