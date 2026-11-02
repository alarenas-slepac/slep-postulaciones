<?php

namespace App\Exports;

use App\Models\Establecimiento;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionSobredotacionExport
{
    public const BLOQUES = ['plan_estudio' => 'Plan de estudio', 'parvularia' => 'Educación Parvularia', 'pie' => 'PIE'];

    private const CAMPOS = [
        'rut', 'nombre', 'titulo', 'prelacion', 'tramo', 'fecha_antiguedad',
        'contrato_original', 'contrato_considerado', 'aula_asignada', 'contrato_asignado', 'reservadas', 'pendientes',
        'contrato_bloque', 'saldo_bloque', 'horas_titular', 'horas_contrata', 'con_justificacion', 'sin_justificacion',
        'justificacion_titular', 'estado_titular', 'justificacion_contrata', 'estado_contrata',
    ];

    public function download(Establecimiento $establecimiento, int $anio, array $informe): StreamedResponse
    {
        return response()->streamDownload(function () use ($establecimiento, $anio, $informe) {
            $book = $this->workbook($establecimiento, $anio, $informe);
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, 'nomina_sobredotacion_prelacion_rbd_'.(int) $establecimiento->rbd.'_'.$anio.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function workbook(Establecimiento $establecimiento, int $anio, array $informe): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headers = [
            'N°', 'RUT', 'Nombre completo', 'Título', 'Grupo de prelación', 'Tramo', 'Fecha de antigüedad',
            'Horas contrato originales '.$anio, 'Horas contrato consideradas '.$anio, 'Horas aula asignadas',
            'Horas contrato asignadas', 'Horas reservadas', 'Horas sin asignar / pendientes del docente',
            'Horas contrato del bloque', 'Horas sin asignar del bloque (Etapa 7)',
            'Horas titulares sujetas a supresión', 'Horas contrata sujetas a no renovación',
            'Horas del bloque con justificación vigente', 'Horas del bloque sin justificación vigente',
            'Justificación de supresión titular', 'Estado justificación titular',
            'Justificación de no renovación contrata', 'Estado justificación contrata',
        ];
        foreach (self::BLOQUES as $key => $title) {
            $sheet = ($key === 'plan_estudio' ? $book->getActiveSheet() : $book->createSheet())->setTitle($title);
            $text = static fn ($cell, $value) => $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
            $rows = collect($informe['bloques'][$key] ?? [])->values();
            foreach ([
                1 => 'Sobredotación docente · '.$title.' · '.$anio,
                2 => 'RBD '.$establecimiento->rbd.' · '.$establecimiento->nombre_establecimiento.' · '.($establecimiento->comuna ?? ''),
                3 => 'Proceso '.$anio.' · Fuente contractual: '.($informe['periodos_padron'] ?? 'Período no informado'),
                4 => 'Docentes con saldo en este bloque, en orden de prelación. Horas en revisión de Etapa 7. Los contratos generales pueden repetirse entre hojas. Ver Definiciones.',
            ] as $line => $value) { $sheet->mergeCells('A'.$line.':W'.$line); $text('A'.$line, $value); }
            foreach ($headers as $index => $header) { $text([$index + 1, 6], $header); }
            foreach ($rows as $index => $row) {
                $line = $index + 7;
                $sheet->setCellValue('A'.$line, $index + 1);
                foreach (self::CAMPOS as $offset => $field) {
                    $column = $offset + 2; $cell = [$column, $line]; $value = $row[$field] ?? null;
                    if ($column >= 8 && $column <= 19) {
                        if ($value !== null) { $sheet->setCellValue($cell, (float) $value); }
                    } elseif ($field === 'fecha_antiguedad' && $value) {
                        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
                        if ($date && $date->format('Y-m-d') === $value) { $sheet->setCellValue($cell, Date::PHPToExcel($date)); }
                        else { $text($cell, $value); }
                    } else { $text($cell, $value ?? ''); }
                }
                if ((float) ($row['sin_justificacion'] ?? 0) > 0.01) {
                    $sheet->getStyle('S'.$line)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => '8A4B00']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E1']]]);
                }
            }
            $lastData = 6 + $rows->count();
            if ($rows->isEmpty()) { $sheet->mergeCells('A7:W7'); $text('A7', 'No hay docentes con horas contractuales sin asignación en este bloque.'); }
            $totalLine = max(7, $lastData) + 1;
            $sheet->mergeCells('A'.$totalLine.':G'.$totalLine); $text('A'.$totalLine, 'Total '.$title.' · '.$rows->count().' docentes');
            foreach (array_slice(self::CAMPOS, 6, 12) as $offset => $field) {
                if (!$rows->contains(fn (array $row) => !array_key_exists($field, $row) || $row[$field] === null)) {
                    $sheet->setCellValue([$offset + 8, $totalLine], round((float) $rows->sum($field), 2));
                }
            }
            $sheet->getStyle('A1:W'.$totalLine)->getAlignment()->setVertical('center')->setWrapText(true);
            foreach (['A1:W1', 'A6:W6'] as $range) {
                $sheet->getStyle($range)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3D91']]]);
            }
            $sheet->getStyle('A1')->getFont()->setSize(16);
            $sheet->getStyle('A'.$totalLine.':W'.$totalLine)->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF6FF']]]);
            $sheet->getStyle('H7:S'.$totalLine)->getNumberFormat()->setFormatCode('#,##0.##;[Red]-#,##0.##;0');
            $sheet->getStyle('G7:G'.$totalLine)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
            foreach (range('A', 'W') as $column) { $sheet->getColumnDimension($column)->setWidth(20); }
            foreach (['A' => 7, 'B' => 16, 'C' => 35, 'D' => 38, 'E' => 36, 'G' => 17, 'T' => 65, 'U' => 36, 'V' => 65, 'W' => 36] as $column => $width) { $sheet->getColumnDimension($column)->setWidth($width); }
            foreach ([1 => 32, 2 => 30, 3 => 28, 4 => 32, 6 => 90] as $line => $height) { $sheet->getRowDimension($line)->setRowHeight($height); }
            $sheet->setAutoFilter('A6:W'.max(6, $lastData)); $sheet->freezePane('D7');
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(6, 6); $sheet->getPageSetup()->setPrintArea('A1:W'.$totalLine);
        }
        $definitions = $book->createSheet()->setTitle('Definiciones');
        $notes = [
            ['Campo', 'Definición'],
            ['Alcance', 'Nómina completa de saldos contractuales de Sobredotación del establecimiento y año consultados, sin aplicar filtros de pantalla. Una hoja por bloque: Plan de estudio, Educación Parvularia y PIE. No incluye cupos virtuales, asistentes, contratos protegidos ni funciones declaradas que ya tienen asignación.'],
            ['Prelación', 'Se usa el orden del SGA por grupo de prelación, fecha de antigüedad y nombre. Las fechas no informadas quedan al final del grupo. Ordenar no modifica los cálculos del proceso.'],
            ['Contrato y asignación del docente', 'Son las cifras generales de la pestaña Docentes, con reservas separadas, conversión consolidada y redondeo NT. El saldo negativo indica sobreasignación; un valor no determinado queda vacío. El período contractual real de origen se indica en el encabezado.'],
            ['Contrato y saldo del bloque', 'Provienen de los saldos de Etapa 7 de Sobredotación, conservando su distribución entre bloques y calidades. Se excluye la fracción de Parvularia neteada por el SGA.'],
            ['Supresión / no renovación', 'Horas titulares sujetas a supresión y horas a contrata sujetas a no renovación; son horas en revisión, no bajas ejecutadas.'],
            ['Justificación', 'Se informa por separado el texto registrado para titular y contrata, del mismo establecimiento, año, RUT y bloque. Si no tiene texto: Sin justificación. Sin horas de esa calidad y sin texto: No aplica.'],
            ['Vigencia', 'Vigente requiere texto y coincidencia con las horas actuales. Si cambió la cantidad, se conserva el texto y se indica Desactualizada con la cantidad anterior; todas esas horas permanecen pendientes. Sin saldo actual conserva el texto si existe, sin sumar horas justificadas.'],
            ['Registros fuera de nómina', 'No se agregan docentes o bloques sin saldo actual sólo porque conservan una justificación histórica. Pueden revisarse en la sección Justificaciones fuera de la nómina de saldos del SGA.'],
            ['Disponibilidad de justificaciones', ($informe['justificaciones_disponibles'] ?? false) ? 'Se consultaron los registros del establecimiento y año seleccionados.' : 'La tabla de justificaciones no está instalada; las horas quedan sin justificación.'],
            ['Totales por hoja', 'Cada hoja totaliza sus filas completas y no recalcula los totales al aplicar filtros en Excel. Los antecedentes contractuales generales se repiten si el docente aparece en varios bloques: no sumar estas columnas entre hojas. Un total queda vacío si alguna fila tiene un valor no determinado.'],
        ];
        foreach ($notes as $index => $note) {
            foreach ($note as $column => $value) { $definitions->setCellValueExplicit([$column + 1, $index + 1], $value, DataType::TYPE_STRING); }
        }
        $definitions->getColumnDimension('A')->setWidth(33); $definitions->getColumnDimension('B')->setWidth(105);
        $definitions->getStyle('A1:B'.count($notes))->getAlignment()->setWrapText(true)->setVertical('top');
        $definitions->getStyle('A1:B1')->getFont()->setBold(true); $definitions->freezePane('A2');
        $book->setActiveSheetIndex(0);

        return $book;
    }
}
