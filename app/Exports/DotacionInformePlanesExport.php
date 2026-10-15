<?php

namespace App\Exports;

use App\Support\DotacionInformePlanes;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionInformePlanesExport
{
    public const HEADERS = [
        'RBD', 'Nombre del establecimiento', 'Comuna', 'Modalidad JEC',
        'Matrícula', 'Cursos', 'Horas plan de estudio', 'Contrato equivalente del plan',
        'Trabajo colaborativo PIE (contrato)', 'Contrato plan + trabajo colaborativo PIE', 'Observaciones',
    ];

    public function __construct(private readonly DotacionInformePlanes $calculador) {}

    public function download(Builder $establecimientos, int $anio, array $filters = []): StreamedResponse
    {
        @set_time_limit(240);
        $book = $this->workbook($this->filas($establecimientos, $anio), $anio, $filters);

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'dotacion_planes_consolidado_'.$anio.'_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function filas(Builder $establecimientos, int $anio): \Generator
    {
        foreach ((clone $establecimientos)->lazy(10) as $ee) {
            try {
                $fila = DotacionLecturaCache::ejecutar(fn () => $this->calculador->fila($ee, $anio));
            } catch (\Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages([
                    'export_planes' => 'No se pudo calcular el plan del RBD '.$ee->rbd.'. No se generó el Excel; revise el establecimiento y vuelva a descargar.',
                ]);
            } finally {
                $ee->unsetRelations();
            }
            yield $fila;
        }
    }

    public function workbook(iterable $filas, int $anio, array $filters = []): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('SGA SLEP Andalién Costa')->setTitle('Consolidado planes de estudio '.$anio);
        $sheet = $book->getActiveSheet()->setTitle('Planes por establecimiento');
        $this->prepararHoja($sheet, $anio);
        $count = 0;
        try {
            foreach ($filas as $fila) {
                $this->escribirFila($sheet, 7 + $count++, $fila);
            }
            $this->finalizarHoja($sheet, $count, $anio, $filters);

            return $book;
        } catch (\Throwable $exception) {
            $book->disconnectWorksheets();
            throw $exception;
        }
    }

    /** Formato compartido por el consolidado simple y el libro por niveles. */
    public function prepararHoja(Worksheet $sheet, int $anio, ?string $titulo = null, ?string $nota = null): void
    {
        $book = $sheet->getParent();
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet->setShowGridlines(false);
        $sheet->mergeCells('A1:K1')->setCellValue('A1', ($titulo ?? 'CONSOLIDADO DE PLANES DE ESTUDIO').' '.$anio);
        $sheet->mergeCells('A2:K2');
        $sheet->mergeCells('A3:K3')->setCellValue('A3', 'Fuente: cuadro Cursos y planes · Total establecimiento. Horas plan pedagógicas; contrato equivalente y colaboración PIE en horas de contrato.');
        $sheet->mergeCells('A4:K4')->setCellValue('A4', $nota ?? 'Incluye combinaciones activas y libre disposición NT1/NT2 de otros docentes una sola vez. No incluye funciones directivas, normativas ni PIE especializado.');
        $sheet->mergeCells('A5:K5')->setCellValue('A5', 'JEC se informa desde los cursos activos con matrícula del año: si existen ambas modalidades se indican Con JEC / Sin JEC. No aplica y Sin información conservan su significado.');
        foreach (self::HEADERS as $index => $label) {
            $sheet->setCellValueExplicit([$index + 1, 6], $label, DataType::TYPE_STRING);
        }
        foreach (['A1:K1', 'A6:K6'] as $range) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B3D91');
            $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        }
        foreach (['A' => 12, 'B' => 48, 'C' => 22, 'D' => 28, 'E' => 16, 'F' => 12, 'G' => 20, 'H' => 22, 'I' => 24, 'J' => 26, 'K' => 52] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        foreach ([1 => 32, 2 => 38, 3 => 32, 4 => 32, 5 => 32, 6 => 64] as $row => $height) {
            $sheet->getRowDimension($row)->setRowHeight($height);
        }
    }

    public function escribirFila(Worksheet $sheet, int $row, array $fila): void
    {
        $values = [$fila['rbd'], $fila['nombre'], $fila['comuna'], $fila['modalidad_jec'],
            $fila['matricula'], $fila['cursos'], $fila['horas_plan'], $fila['contrato_equivalente'],
            $fila['trabajo_colaborativo_pie'], $fila['contrato_mas_pie'], $fila['observaciones']];
        $height = 45;
        foreach ($values as $index => $value) {
            $text = $index < 4 || $index === 10;
            $sheet->setCellValueExplicit([$index + 1, $row], $text ? (string) $value : $value,
                $text ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
            if ($text) {
                $width = $sheet->getColumnDimensionByColumn($index + 1)->getWidth() - 4;
                $lines = array_sum(array_map(fn ($line) => max(1, (int) ceil(mb_strlen($line) / $width)), explode("\n", (string) $value)));
                $height = max($height, $lines * 16);
            }
        }
        $sheet->getRowDimension($row)->setRowHeight($height);
    }

    public function finalizarHoja(Worksheet $sheet, int $count, int $anio, array $filters = []): void
    {
        $sheet->setCellValueExplicit('A2', 'Año: '.$anio.' | Comuna: '.(($filters['comuna'] ?? '') ?: 'Todas')
            .' | Búsqueda: '.($filters['q'] ?? '').' | Establecimientos: '.$count, DataType::TYPE_STRING);
        $last = 6 + $count;
        $total = $count ? $last + 1 : 8;
        if (! $count) {
            $sheet->mergeCells('A7:K7')->setCellValue('A7', 'Sin establecimientos para los filtros aplicados.');
        }
        $sheet->mergeCells('A'.$total.':D'.$total)->setCellValue('A'.$total, 'TOTAL DE LOS ESTABLECIMIENTOS FILTRADOS');
        foreach (range('E', 'J') as $col) {
            $sheet->setCellValueExplicit($col.$total, $count ? '=SUM('.$col.'7:'.$col.$last.')' : '=0', DataType::TYPE_FORMULA);
        }
        $sheet->getStyle('A'.$total.':K'.$total)->getFont()->setBold(true);
        $sheet->getStyle('A'.$total.':K'.$total)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF6FF');
        $sheet->getRowDimension($total)->setRowHeight(32);
        $sheet->getStyle('A1:K'.$total)->getAlignment()->setWrapText(true)->setVertical('center');
        $sheet->getStyle('E7:F'.$total)->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle('G7:J'.$total)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->setAutoFilter('A6:K'.$last);
        $sheet->freezePane('C7');
    }
}
