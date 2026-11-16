<?php

namespace App\Exports;

use App\Support\DotacionNominaDocentes;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionNominaCompletaExport
{
    private const COLUMNAS = [
        'comuna' => 'Comuna', 'rbd' => 'RBD', 'establecimiento' => 'Establecimiento', 'rut' => 'RUT',
        'nombre' => 'Docente', 'titulo' => 'Título profesional', 'tramo' => 'Tramo', 'bloques_label' => 'Bloques contractuales',
        'contrato_original' => 'H. contrato original', 'titulares' => 'H. titulares', 'contrata' => 'H. contrata',
        'otras_calidades' => 'H. otras calidades', 'plan_estudio' => 'H. Plan de estudio',
        'parvularia' => 'H. Parvularia', 'pie' => 'H. PIE', 'contrato_considerado' => 'H. consideradas en dotación',
        'estado_dotacion' => 'Estado en dotación', 'justificada' => 'Justificación registrada',
        'tipo_contrato' => 'Tipos de contrato', 'periodo_padron' => 'Período padrón', 'registros_contrato' => 'Líneas contractuales consolidadas',
    ];
    private const HORAS = ['contrato_original', 'titulares', 'contrata', 'otras_calidades', 'plan_estudio', 'parvularia', 'pie', 'contrato_considerado'];

    public function download(int $anio, Collection $filas, array $filtros): StreamedResponse
    {
        return response()->streamDownload(function () use ($anio, $filas, $filtros) {
            $book = $this->workbook($anio, $filas, $filtros);
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, 'nomina_completa_docentes_por_bloque_'.$anio.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function workbook(int $anio, Collection $filas, array $filtros = []): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet()->setTitle('Docentes');
        $this->encabezado($sheet, 'NÓMINA COMPLETA DE DOCENTES · '.$anio, array_values(self::COLUMNAS));
        $this->texto($sheet, 'A3', 'Una fila por docente y establecimiento. Contrato original consolidado, con independencia del ajuste o la justificación en dotación.');
        $this->texto($sheet, 'A4', 'Bloque: '.(DotacionNominaDocentes::BLOQUES[$filtros['bloque'] ?? ''] ?? 'Todos').' · Comuna: '.(($filtros['comuna'] ?? '') ?: 'Todas').' · Búsqueda: '.($filtros['q'] ?? ''));
        foreach ($filas->values() as $i => $fila) {
            foreach (array_keys(self::COLUMNAS) as $c => $key) {
                $cell = [$c + 1, $i + 7];
                $valor = $fila[$key] ?? '';
                if (in_array($key, self::HORAS, true) || $key === 'registros_contrato') {
                    $sheet->setCellValue($cell, (float) $valor);
                } else {
                    $sheet->setCellValueExplicit($cell, $key === 'justificada' ? ($valor ? 'Sí' : 'No') : (string) $valor, DataType::TYPE_STRING);
                }
            }
        }
        $final = max(7, $filas->count() + 6);
        if ($filas->isEmpty()) { $this->texto($sheet, 'A7', 'No hay docentes para los filtros seleccionados.'); }
        $total = $final + 2;
        $this->texto($sheet, 'A'.$total, 'TOTAL · '.$filas->count().' registros docente-establecimiento');
        foreach (array_keys(self::COLUMNAS) as $c => $key) {
            if (in_array($key, self::HORAS, true)) {
                $sheet->setCellValue([$c + 1, $total], round((float) $filas->sum($key), 2));
            }
        }
        $sheet->getStyle('I7:P'.$total)->getNumberFormat()->setFormatCode('#,##0.00');
        $this->terminar($sheet, 'U', $final, $total);
        foreach (['C' => 46, 'E' => 42, 'F' => 55, 'H' => 42, 'Q' => 44, 'S' => 35] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $resumen = $book->createSheet()->setTitle('Resumen establecimientos');
        $headers = ['Comuna', 'RBD', 'Establecimiento', 'Docentes', ...array_map(fn ($key) => self::COLUMNAS[$key], self::HORAS)];
        $this->encabezado($resumen, 'RESUMEN POR ESTABLECIMIENTO · '.$anio, $headers);
        $this->texto($resumen, 'A3', 'Una fila por establecimiento, con los docentes y horas de la nómina filtrada.');
        $r = 7;
        foreach ($filas->groupBy('establecimiento_id') as $grupo) {
            $primero = $grupo->first();
            foreach (['comuna', 'rbd', 'establecimiento'] as $c => $key) {
                $resumen->setCellValueExplicit([$c + 1, $r], (string) $primero[$key], DataType::TYPE_STRING);
            }
            $resumen->setCellValue([4, $r], $grupo->count());
            foreach (self::HORAS as $c => $key) { $resumen->setCellValue([$c + 5, $r], round((float) $grupo->sum($key), 2)); }
            $r++;
        }
        $finalResumen = max(7, $r - 1);
        $totalResumen = $finalResumen + 2;
        if ($filas->isEmpty()) { $this->texto($resumen, 'A7', 'No hay establecimientos con docentes para esta selección.'); }
        $this->texto($resumen, 'A'.$totalResumen, 'TOTAL');
        $resumen->setCellValue([4, $totalResumen], $filas->count());
        foreach (self::HORAS as $c => $key) { $resumen->setCellValue([$c + 5, $totalResumen], round((float) $filas->sum($key), 2)); }
        $resumen->getStyle('E7:L'.$totalResumen)->getNumberFormat()->setFormatCode('#,##0.00');
        $this->terminar($resumen, 'L', $finalResumen, $totalResumen);
        $resumen->getColumnDimension('C')->setWidth(50);

        $def = $book->createSheet()->setTitle('Definiciones');
        $def->fromArray([
            ['Concepto', 'Definición'],
            ['Alcance', 'Docentes vigentes del último padrón disponible para el año, incluidos contratos ajustados, protegidos, justificados, no continuidades y no renovaciones de la proyección. No exige establecimiento finalizado ni saldo disponible.'],
            ['Unidad', 'Una fila por RUT y establecimiento. Se suman las líneas del mismo período y se eliminan duplicados contractuales según el padrón. Una persona en dos establecimientos conserva una fila y su propio contrato en cada uno.'],
            ['Contrato original', 'Horas completas del padrón antes del ajuste por situación o proyección. La declaración de título no sustituye la jornada contractual.'],
            ['Distribución por bloques', 'Reutiliza las reglas contractuales del SGA con el contrato original. Plan de estudio + Parvularia + PIE concilia con el contrato de cada fila. Un docente de varios bloques aparece una sola vez. Las escuelas especiales conservan PIE en el bloque general.'],
            ['Filtro de bloque', 'Selecciona personas con aporte al bloque; conserva su contrato completo y muestra las horas de todos sus bloques para evitar perder información. No sumar descargas separadas si hay docentes compartidos.'],
            ['Horas consideradas', 'Horas efectivas del proceso tras la situación docente; cero para quienes no continúan o tienen no renovación registrada. Se muestran como referencia y no limitan esta nómina.'],
            ['Justificación', 'Indica la existencia de justificación registrada de Sobredotación en el año y establecimiento. No elimina a la persona de este informe ni implica coincidencia con su saldo actual.'],
            ['Sin horas o título', 'Los contratos cero conservan una fila y el bloque de su perfil. La ausencia de título se informa como Sin título declarado.'],
            ['Exclusiones', 'Se conservan las reglas del padrón docente: no incorpora asistentes, plazas virtuales, reemplazos ni suplencias. Las salas cuna quedan fuera de Dotación Establecimiento.'],
            ['Filtros y paginación', 'El Excel aplica los mismos filtros de año, bloque, comuna, establecimiento y búsqueda que la vista; exporta todos los resultados, no sólo la página visible.'],
            ['Período', 'La columna Período padrón identifica el respaldo de cada docente, incluso cuando el proceso usa el padrón del año anterior. Las horas son horas cronológicas de contrato.'],
        ]);
        $def->getColumnDimension('A')->setWidth(28); $def->getColumnDimension('B')->setWidth(125);
        $def->getStyle('A1:B12')->getAlignment()->setWrapText(true)->setVertical('top');
        for ($i = 2; $i <= 12; $i++) { $def->getRowDimension($i)->setRowHeight(48); }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function encabezado(Worksheet $sheet, string $titulo, array $headers): void
    {
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        foreach ([1, 3, 4] as $line) { $sheet->mergeCells('A'.$line.':'.$last.$line); }
        $this->texto($sheet, 'A1', $titulo);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF0B3D91');
        $sheet->getRowDimension(1)->setRowHeight(30);
        foreach ([3, 4] as $line) { $sheet->getStyle('A'.$line)->getAlignment()->setWrapText(true); $sheet->getRowDimension($line)->setRowHeight(30); }
        $sheet->fromArray($headers, null, 'A6');
        $sheet->getStyle('A6:'.$last.'6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0B3D91');
        $sheet->getStyle('A6:'.$last.'6')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A6:'.$last.'6')->getAlignment()->setWrapText(true)->setVertical('center');
        $sheet->getRowDimension(6)->setRowHeight(40);
        for ($i = 1; $i <= count($headers); $i++) { $sheet->getColumnDimensionByColumn($i)->setWidth(20); }
    }

    private function terminar(Worksheet $sheet, string $last, int $final, int $total): void
    {
        $sheet->freezePane('E7'); $sheet->setAutoFilter('A6:'.$last.$final);
        $sheet->getStyle('A7:'.$last.$final)->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->getStyle('A'.$total.':'.$last.$total)->getFont()->setBold(true);
        $sheet->getStyle('A'.$total.':'.$last.$total)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEF6FF');
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(6, 6);
    }

    private function texto(Worksheet $sheet, string $cell, string $text): void
    {
        $sheet->setCellValueExplicit($cell, $text, DataType::TYPE_STRING);
    }
}
