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
        $this->escribirProyeccionPie($book->createSheet(), $filas, $anio);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function escribirHoja(Worksheet $sheet, string $nombre, int $anio, string $nota, array $filas, bool $detalle = false): void
    {
        $horasCol = $detalle ? 'E' : 'D';
        $encabezados = ['N.º', 'RBD', 'Nombre del establecimiento educacional', ...($detalle ? ['Fuente de financiamiento'] : []), 'Total, horas'];
        $this->prepararHoja($sheet, $nombre, 'Horas de '.$nombre.' proyectadas por RBD · '.$anio, $nota, $horasCol, $encabezados);

        $row = 5;
        foreach ($filas as $fila) {
            $this->escribirRbd($sheet, $row, $fila);
            if ($detalle) {
                $sheet->setCellValueExplicit('D'.$row, $fila['fuente'], DataType::TYPE_STRING);
            }
            $sheet->setCellValue($horasCol.$row, round($fila['horas'], 2));
            $row++;
        }
        $this->cerrarTabla($sheet, $row, $horasCol, [$horasCol], ['D' => $detalle ? 38 : 20, 'E' => 20],
            'Sin establecimientos para los filtros aplicados.');
        $sheet->getStyle($horasCol.'5:'.$horasCol.max(6, $row))->getNumberFormat()->setFormatCode('#,##0.##');
    }

    private function escribirProyeccionPie(Worksheet $sheet, array $filas, int $anio): void
    {
        $this->prepararHoja($sheet, 'Proyección PIE', 'Matrícula y horas de apoyo PIE proyectadas por RBD · '.$anio,
            'Estudiantes PIE por curso · Apoyo docente en horas de contrato y Apoyo A.E. según PAEC.',
            'H', ['N.º', 'RBD', 'Nombre del establecimiento educacional', 'N.º cursos', 'Matrícula NEET',
                'Matrícula NEEP', 'Horas Apoyo Docente', 'Horas Apoyo A.E.']);

        $row = 5;
        foreach ($filas as $fila) {
            $pie = $fila['pie_proyectado'];
            if ($pie['cursos_nee'] === 0) {
                continue;
            }
            $this->escribirRbd($sheet, $row, $fila);
            $sheet->fromArray([
                $pie['cursos_nee'], $pie['neet'], $pie['neep'],
                (int) $pie['educadoras_diferenciales_horas'], (int) ceil($pie['pae_minutos'] / 60),
            ], null, 'D'.$row, true);
            $row++;
        }
        $this->cerrarTabla($sheet, $row, 'H', range('D', 'H'), ['D' => 14, 'E' => 17, 'F' => 17, 'G' => 24, 'H' => 21],
            'Sin cursos activos con estudiantes PIE para los filtros aplicados.');
    }

    private function prepararHoja(Worksheet $sheet, string $nombre, string $titulo, string $nota, string $ultimaCol, array $encabezados): void
    {
        $sheet->setTitle($nombre)->setShowGridlines(false);
        $sheet->mergeCells('A1:'.$ultimaCol.'1')->setCellValue('A1', $titulo);
        $sheet->mergeCells('A2:'.$ultimaCol.'2')->setCellValue('A2', $nota);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0B3D91');
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->fromArray($encabezados, null, 'A4');
    }

    private function escribirRbd(Worksheet $sheet, int $row, array $fila): void
    {
        $sheet->setCellValue('A'.$row, $row - 4);
        $sheet->setCellValueExplicit('B'.$row, $fila['rbd'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C'.$row, $fila['nombre'], DataType::TYPE_STRING);
    }

    private function cerrarTabla(Worksheet $sheet, int $row, string $ultimaCol, array $columnasTotales, array $anchos, string $vacio): void
    {
        if ($row === 5) {
            $sheet->mergeCells('A5:'.$ultimaCol.'5')->setCellValue('A5', $vacio);
            $row++;
        }
        $ultimaFila = $row - 1;
        $sheet->mergeCells('A'.$row.':'.chr(ord($columnasTotales[0]) - 1).$row)->setCellValue('A'.$row, 'Total');
        foreach ($columnasTotales as $columna) {
            $sheet->setCellValue($columna.$row, '=SUM('.$columna.'5:'.$columna.$ultimaFila.')');
        }
        $tabla = $sheet->getStyle('A4:'.$ultimaCol.$row);
        $tabla->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DEE6F4');
        $tabla->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('424C61');
        foreach ([4, $row] as $filaDestacada) {
            $style = $sheet->getStyle('A'.$filaDestacada.':'.$ultimaCol.$filaDestacada);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6D3E8');
            $style->getFont()->setBold(true);
        }
        foreach (['A' => 8, 'B' => 13, 'C' => 61] + $anchos as $col => $ancho) {
            $sheet->getColumnDimension($col)->setWidth($ancho);
        }
        $sheet->setAutoFilter('A4:'.$ultimaCol.$ultimaFila);
        $sheet->freezePane('A5');
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(4, 4);
    }
}
