<?php

namespace App\Exports;

use App\Models\Establecimiento;
use App\Support\DotacionInformeFunciones;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionInformeFuncionesExport
{
    public const HEADERS = [
        'RBD', 'Nombre del establecimiento',
        'N.º docentes función directiva', 'Horas contrato función directiva',
        'N.º docentes función técnico-pedagógica', 'Horas contrato función técnico-pedagógica',
        'N.º docentes aula y planes normativos', 'Horas contrato aula y planes normativos',
        'N.º docentes otras funciones PIE', 'Horas contrato otras funciones PIE',
        'N.º total docentes', 'Total horas docentes (contrato completo)', 'Horas SEP (complementarias)',
    ];

    public const CONCILIACION_HEADERS = [
        'RBD', 'Nombre del establecimiento', 'Comuna', 'Base contractual (mes/año)',
        'Contrato original completo', 'Contrato considerado por dotación', 'Excluidas por situaciones',
        'Asignadas en las cuatro funciones', 'Otras funciones asignadas', 'Total horas asignadas',
        'Reservadas sin función', 'Sin asignar', 'Sobreasignadas', 'Redondeo incluido en Aula',
        'SEP explícito (complementario)', 'Financiamiento mixto con SEP sin desglose',
        'Contrato conciliado', 'Diferencia de conciliación',
    ];

    public function __construct(private readonly DotacionInformeFunciones $calculador) {}

    public function download(Builder $establecimientos, int $anio, array $filters = []): StreamedResponse
    {
        @set_time_limit(240);
        // Construir antes de enviar cabeceras: un fallo vuelve al formulario y
        // nunca entrega un archivo parcial con ceros en lugar de establecimientos.
        $book = $this->workbook($this->filas($establecimientos, $anio), $anio, $filters);

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'dotacion_por_funciones_'.$anio.'_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function filas(Builder $establecimientos, int $anio): \Generator
    {
        foreach ((clone $establecimientos)->lazy(10) as $establecimiento) {
            /** @var Establecimiento $establecimiento */
            try {
                $fila = DotacionLecturaCache::ejecutar(fn () => $this->calculador->fila($establecimiento, $anio));
                if ($fila['error'] ?? false) {
                    throw new \RuntimeException('El cálculo del establecimiento no está disponible.');
                }
            } catch (\Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages([
                    'export_excel' => 'No se pudo calcular el RBD '.$establecimiento->rbd.'. No se generó el Excel; revise el establecimiento y vuelva a descargar.',
                ]);
            } finally {
                $establecimiento->unsetRelations();
            }
            yield $fila;
        }
    }

    /** Acepta un generador para no acumular las nóminas del territorio. */
    public function workbook(iterable $filas, int $anio, array $filters = []): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('SGA SLEP Andalién Costa')->setTitle('Dotación por funciones '.$anio);
        $resumen = $book->getActiveSheet()->setTitle('Dotación por funciones');
        $conciliacion = $book->createSheet()->setTitle('Conciliación');
        $headers = self::HEADERS;
        $headers[12] = 'Horas SEP '.$anio.' (complementarias)';
        $this->prepararHoja($resumen, $headers, 'DOTACIÓN POR FUNCIONES '.$anio,
            'Docentes únicos por establecimiento. Una persona puede cumplir varias funciones; SEP no se suma nuevamente al contrato.');
        $this->prepararHoja($conciliacion, self::CONCILIACION_HEADERS, 'CONCILIACIÓN DEL CONTRATO DOCENTE '.$anio,
            'Contrato completo = asignadas + reservadas + sin asignar + excluidas por situaciones − sobreasignadas. El redondeo ya está incluido en Aula.');
        $resumen->mergeCells('A5:B5')->setCellValue('A5', 'Establecimiento');
        foreach (['C5:D5' => 'Directiva', 'E5:F5' => 'Técnico-pedagógica', 'G5:H5' => 'Aula y planes', 'I5:J5' => 'PIE', 'K5:L5' => 'Totales'] as $range => $label) {
            $resumen->mergeCells($range)->setCellValue(explode(':', $range)[0], $label);
        }
        $resumen->setCellValue('M5', 'Complementario');
        foreach (['A5:D5' => 'Establecimiento y base', 'E5:G5' => 'Contrato', 'H5:J5' => 'Asignaciones',
            'K5:M5' => 'Reservas y saldos', 'N5:P5' => 'Redondeo y SEP', 'Q5:R5' => 'Conciliación'] as $range => $label) {
            $conciliacion->mergeCells($range)->setCellValue(explode(':', $range)[0], $label);
        }
        $count = 0;
        try {
            foreach ($filas as $fila) {
                $linea = 7 + $count++;
                $row = [(string) $fila['rbd'], (string) $fila['nombre']];
                foreach (DotacionInformeFunciones::GRUPOS as $key => $label) {
                    $row[] = (int) $fila['grupos'][$key]['docentes'];
                    $row[] = (float) $fila['grupos'][$key]['horas'];
                }
                $row = [...$row, (int) $fila['docentes_total'], (float) $fila['contrato_total'], (float) $fila['sep']];
                $this->escribirFila($resumen, $linea, $row, 2);
                $this->escribirFila($conciliacion, $linea, [
                    (string) $fila['rbd'], (string) $fila['nombre'], (string) $fila['comuna'],
                    implode(', ', $fila['periodos']) ?: 'Sin contratos vigentes',
                    (float) $fila['contrato_total'], (float) $fila['contrato_considerado'], (float) $fila['horas_excluidas'],
                    round((float) $fila['asignadas'] - (float) $fila['otras'], 2), (float) $fila['otras'],
                    '=ROUND(H'.$linea.'+I'.$linea.',2)', (float) $fila['reservadas'], (float) $fila['sin_asignar'],
                    (float) $fila['sobreasignadas'], (float) $fila['redondeo'], (float) $fila['sep'], (float) $fila['sep_sin_desglose'],
                    '=ROUND(J'.$linea.'+K'.$linea.'+L'.$linea.'+G'.$linea.'-M'.$linea.',2)', '=ROUND(E'.$linea.'-Q'.$linea.',2)',
                ], 4, [10, 17, 18]);
                if ((float) $fila['sep_sin_desglose'] > 0) {
                    $conciliacion->getStyle('P'.$linea)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF8E1');
                }
                if ((float) $fila['sobreasignadas'] > 0) {
                    $conciliacion->getStyle('M'.$linea)->getFont()->getColor()->setRGB('B42318');
                }
            }
            foreach ([$resumen, $conciliacion] as $sheet) {
                $last = $sheet === $resumen ? 'M' : 'R';
                $metadata = 'Año: '.$anio.' | Comuna: '.(($filters['comuna'] ?? '') ?: 'Todas').' | Búsqueda: '.($filters['q'] ?? '').' | Establecimientos: '.$count;
                $sheet->setCellValueExplicit('A2', $metadata, DataType::TYPE_STRING);
                $this->finalizarHoja($sheet, $count, $last, $sheet === $resumen ? 3 : 5);
            }
            $resumen->mergeCells('A'.(10 + $count).':M'.(10 + $count))->setCellValue('A'.(10 + $count), 'Fuente: SGA · Dotación del año consultado. Ver período contractual y financiamientos sin desglose en Conciliación.');
            $resumen->getRowDimension(10 + $count)->setRowHeight(32);
            $conciliacion->mergeCells('A'.(10 + $count).':R'.(10 + $count))->setCellValue('A'.(10 + $count), 'Los totales suman nóminas por establecimiento y pueden repetir personas que trabajan en más de uno. No se compensan saldos entre docentes.');
            $conciliacion->getRowDimension(10 + $count)->setRowHeight(32);
            $book->setActiveSheetIndex(0);

            return $book;
        } catch (\Throwable $exception) {
            $book->disconnectWorksheets();
            throw $exception;
        }
    }

    private function prepararHoja(Worksheet $sheet, array $headers, string $title, string $ayuda): void
    {
        $last = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->setShowGridlines(false);
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet->getStyle('A1:'.$last.'6')->getAlignment()->setWrapText(true)->setVertical('center');
        $sheet->mergeCells('A1:'.$last.'1')->setCellValue('A1', $title);
        $sheet->mergeCells('A2:'.$last.'2');
        $sheet->mergeCells('A3:'.$last.'3')->setCellValue('A3', $ayuda);
        $sheet->mergeCells('A4:'.$last.'4')->setCellValue('A4', 'Las cantidades se expresan en horas de contrato. Los períodos de respaldo se identifican en Conciliación.');
        foreach ($headers as $index => $header) {
            $sheet->setCellValueExplicit([$index + 1, 6], $header, DataType::TYPE_STRING);
        }
        foreach (['A1:'.$last.'1', 'A6:'.$last.'6'] as $range) {
            $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B3D91');
        }
        $sheet->getStyle('A5:'.$last.'5')->getFont()->setBold(true);
        $sheet->getStyle('A5:'.$last.'5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF6FF');
        foreach ([1 => 30, 2 => 38, 3 => 34, 4 => 28, 5 => 28, 6 => 88] as $line => $height) {
            $sheet->getRowDimension($line)->setRowHeight($height);
        }
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(48);
        for ($index = 3; $index <= count($headers); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(21);
        }
        $sheet->freezePane('C7');
    }

    /** Texto explícito evita fórmulas inyectadas desde nombres, comunas o filtros. */
    private function escribirFila(Worksheet $sheet, int $linea, array $valores, int $columnasTexto, array $formulas = []): void
    {
        $alto = 45;
        foreach ($valores as $index => $value) {
            $columna = $index + 1;
            $tipo = $columna <= $columnasTexto ? DataType::TYPE_STRING
                : (in_array($columna, $formulas, true) ? DataType::TYPE_FORMULA : DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit([$columna, $linea], $value, $tipo);
            if ($columna <= $columnasTexto) {
                $ancho = $columna === 2 ? 44 : ($columna === 1 ? 10 : 18);
                $lineas = array_sum(array_map(fn ($texto) => max(1, (int) ceil(mb_strlen($texto) / $ancho)), explode("\n", (string) $value)));
                $alto = max($alto, $lineas * 15);
            }
        }
        $sheet->getRowDimension($linea)->setRowHeight($alto);
    }

    private function finalizarHoja(Worksheet $sheet, int $count, string $last, int $primeraNumerica): void
    {
        $lastData = 6 + $count;
        $total = $count > 0 ? $lastData + 1 : 8;
        if ($count === 0) {
            $sheet->mergeCells('A7:'.$last.'7')->setCellValue('A7', 'Sin establecimientos para los filtros aplicados.');
        }
        $endText = Coordinate::stringFromColumnIndex($primeraNumerica - 1);
        $sheet->mergeCells('A'.$total.':'.$endText.$total)->setCellValue('A'.$total, 'TOTAL DE LOS ESTABLECIMIENTOS FILTRADOS');
        for ($index = $primeraNumerica; $index <= Coordinate::columnIndexFromString($last); $index++) {
            $col = Coordinate::stringFromColumnIndex($index);
            $sheet->setCellValueExplicit($col.$total, $count ? '=SUM('.$col.'7:'.$col.$lastData.')' : '=0', DataType::TYPE_FORMULA);
        }
        $sheet->getStyle('A'.$total.':'.$last.$total)->getFont()->setBold(true);
        $sheet->getStyle('A'.$total.':'.$last.$total)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF6FF');
        $sheet->getRowDimension($total)->setRowHeight(32);
        $sheet->getStyle('A7:'.$last.(10 + $count))->getAlignment()->setWrapText(true)->setVertical('center');
        $first = Coordinate::stringFromColumnIndex($primeraNumerica);
        $sheet->getStyle($first.'7:'.$last.$total)->getNumberFormat()->setFormatCode('#,##0.00');
        if ($last === 'M') {
            foreach (['C', 'E', 'G', 'I', 'K'] as $col) {
                $sheet->getStyle($col.'7:'.$col.$total)->getNumberFormat()->setFormatCode('#,##0');
            }
        }
        $sheet->setAutoFilter('A6:'.$last.$lastData);
    }
}
