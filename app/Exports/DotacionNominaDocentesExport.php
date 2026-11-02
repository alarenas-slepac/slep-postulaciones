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

class DotacionNominaDocentesExport
{
    public const ROLES = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];
    private const CAMPOS = ['rut', 'nombre', 'titulo', 'prelacion', 'tramo', 'fecha_antiguedad',
        'contrato_original', 'contrato_considerado', 'aula_asignada', 'contrato_asignado', 'reservadas',
        'pendientes', 'con_justificacion', 'sin_justificacion', 'detalle_justificaciones'];

    public static function canExport(?string $rol): bool { return in_array($rol, self::ROLES, true); }

    public function download(Establecimiento $establecimiento, int $anio, array $informe): StreamedResponse
    {
        return response()->streamDownload(function () use ($establecimiento, $anio, $informe) {
            $book = $this->workbook($establecimiento, $anio, $informe);
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, 'nomina_docentes_prelacion_rbd_'.(int) $establecimiento->rbd.'_'.$anio.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function workbook(Establecimiento $establecimiento, int $anio, array $informe): Spreadsheet
    {
        $rows = collect($informe['filas']);
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet()->setTitle('Nómina docente');
        $text = static fn ($cell, $value) => $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
        foreach ([
            1 => 'Nómina de docentes en orden de prelación · Dotación '.$anio,
            2 => 'RBD '.$establecimiento->rbd.' · '.$establecimiento->nombre_establecimiento.' · '.($establecimiento->comuna ?? ''),
            3 => 'Proceso '.$anio.' · Fuente contractual: '.($informe['periodos_padron'] ?? 'Período no informado'),
            4 => 'Nómina completa de docentes, sin cupos por contratar. Las columnas de supresión/no renovación corresponden a las horas en revisión de Etapa 7. Ver hoja Definiciones.',
        ] as $line => $value) { $sheet->mergeCells('A'.$line.':P'.$line); $text('A'.$line, $value); }
        $headers = ['N°', 'RUT', 'Nombre completo', 'Título', 'Grupo de prelación', 'Tramo', 'Fecha de antigüedad',
            'Horas contrato originales '.$anio, 'Horas contrato consideradas '.$anio, 'Horas aula asignadas',
            'Horas contrato asignadas', 'Horas reservadas', 'Horas sin asignar / pendientes',
            'Hrs. suprimidas o no renovadas con justificación vigente (Etapa 7)',
            'Hrs. suprimidas o no renovadas sin justificación vigente (Etapa 7)', 'Detalle de justificaciones'];
        foreach ($headers as $index => $header) { $text([$index + 1, 6], $header); }
        foreach ($rows->values() as $index => $row) {
            $line = $index + 7; $sheet->setCellValue('A'.$line, $index + 1);
            foreach (self::CAMPOS as $offset => $field) {
                $column = $offset + 2; $cell = [$column, $line]; $value = $row[$field] ?? null;
                if ($column >= 8 && $column <= 15) {
                    if ($value !== null) { $sheet->setCellValue($cell, (float) $value); }
                } elseif ($field === 'fecha_antiguedad' && $value) {
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
                    if ($date && $date->format('Y-m-d') === $value) { $sheet->setCellValue($cell, Date::PHPToExcel($date)); }
                    else { $text($cell, $value); }
                } else { $text($cell, $value ?? ''); }
            }
            if ((float) ($row['sin_justificacion'] ?? 0) > 0.01) {
                $sheet->getStyle('O'.$line)->applyFromArray(['font' => ['color' => ['rgb' => '8A4B00'], 'bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF8E1']]]);
            }
        }
        $lastData = 6 + $rows->count();
        if ($rows->isEmpty()) { $sheet->mergeCells('A7:P7'); $text('A7', 'No hay docentes en la nómina para este establecimiento y año.'); }
        $totalLine = max(7, $lastData) + 1;
        $sheet->mergeCells('A'.$totalLine.':G'.$totalLine); $text('A'.$totalLine, 'Total nómina · '.$rows->count().' docentes');
        foreach (array_slice(self::CAMPOS, 6, 8) as $offset => $field) {
            if (!$rows->contains(fn (array $row) => !array_key_exists($field, $row) || $row[$field] === null)) {
                $sheet->setCellValue([$offset + 8, $totalLine], round((float) $rows->sum($field), 2));
            }
        }
        $sheet->getStyle('A1:P'.$totalLine)->getAlignment()->setVertical('center')->setWrapText(true);
        $sheet->getStyle('A1:P1')->applyFromArray(['font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3D91']]]);
        $sheet->getStyle('A6:P6')->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3D91']]]);
        $sheet->getStyle('A'.$totalLine.':P'.$totalLine)->applyFromArray(['font' => ['bold' => true], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF6FF']]]);
        $sheet->getStyle('H7:O'.$totalLine)->getNumberFormat()->setFormatCode('#,##0.##;[Red]-#,##0.##;0');
        $sheet->getStyle('G7:G'.$totalLine)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        foreach (['A' => 7, 'B' => 16, 'C' => 35, 'D' => 38, 'E' => 36, 'F' => 22, 'G' => 17, 'H' => 18, 'I' => 18, 'J' => 17, 'K' => 18, 'L' => 16, 'M' => 19, 'N' => 23, 'O' => 23, 'P' => 65] as $column => $width) { $sheet->getColumnDimension($column)->setWidth($width); }
        foreach ([1 => 32, 2 => 30, 3 => 28, 4 => 32, 6 => 80] as $line => $height) { $sheet->getRowDimension($line)->setRowHeight($height); }
        $sheet->setAutoFilter('A6:P'.max(6, $lastData)); $sheet->freezePane('D7');
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(6, 6);
        $sheet->getPageSetup()->setPrintArea('A1:P'.$totalLine);

        $definitions = $book->createSheet()->setTitle('Definiciones');
        $notes = [
            ['Campo', 'Definición'],
            ['Año del proceso', (string) $anio],
            ['Fuente contractual', $informe['periodos_padron'] ?? 'Período no informado'],
            ['Prelación', 'Se reutilizan los grupos y desempates del SGA: protección por fuero maternal/horas gremiales; No Regido Ley 20903; titulares Avanzado/Experto; otros titulares; contrata. Dentro del grupo, antigüedad ascendente, fechas no informadas al final y nombre. Es un orden de presentación; no cambia las reglas del año consultado.'],
            ['Alcance', 'Docentes de la nómina anual del establecimiento. Se excluyen cupos virtuales por contratar y cobertura de asistentes. Se exporta la nómina completa, independientemente de los filtros de revisión en pantalla.'],
            ['Contrato original', 'Horas originales del padrón que sirve de base al proceso seleccionado; la fuente puede corresponder a un período anterior, que se identifica en el encabezado.'],
            ['Contrato considerado', 'Horas que se contabilizan en Dotación después de aplicar la situación docente del año. Proceso BIR mantiene el contrato completo durante el año consultado.'],
            ['Aula y contrato asignados', 'Aula corresponde a horas aula reales. El contrato asignado reutiliza la conversión consolidada de la pestaña Docentes, incluidas sus reglas especiales y el redondeo NT; no incluye las reservas informadas en su propia columna.'],
            ['Reservadas', 'Consumen contrato y se descuentan una sola vez del saldo, aunque aún no tengan una función vinculada.'],
            ['Horas sin asignar / pendientes', 'Saldo de la pestaña Docentes, después del redondeo NT. Contrato considerado menos contrato asignado menos reservas. Un valor negativo indica sobreasignación; una celda vacía indica que no existe un saldo determinado.'],
            ['Etapa 7', 'Horas titulares sujetas a supresión y horas a contrata sujetas a no renovación, en Plan general, Educación Parvularia y PIE. Se distinguen de las bajas ya ejecutadas. Los contratos protegidos conservan la exclusión del universo revisable que aplica el SGA.'],
            ['Con justificación vigente', 'El respaldo debe tener texto y corresponder a la misma cantidad de horas actual, establecimiento, año, RUT, bloque y tipo de horas. Se suman sus horas completas.'],
            ['Sin justificación vigente', 'No hay respaldo, está vacío o la cantidad registrada ya no coincide con las horas actuales. Una justificación desactualizada no se contabiliza como cobertura parcial.'],
            ['Disponibilidad de justificaciones', ($informe['justificaciones_disponibles'] ?? false) ? 'Se consultaron las justificaciones registradas para este establecimiento y año.' : 'La tabla de justificaciones no está instalada. Las horas de revisión se informan como pendientes de justificar.'],
            ['Totales', 'Suma de toda la nómina exportada; no se recalcula al filtrar filas en Excel. Si alguna fila carece de un valor determinado, el total de esa columna queda vacío.'],
        ];
        foreach ($notes as $index => $note) { foreach ($note as $column => $value) { $definitions->setCellValueExplicit([$column + 1, $index + 1], $value, DataType::TYPE_STRING); } }
        $definitions->getColumnDimension('A')->setWidth(33); $definitions->getColumnDimension('B')->setWidth(105);
        $definitions->getStyle('A1:B'.count($notes))->getAlignment()->setWrapText(true)->setVertical('top');
        $definitions->getStyle('A1:B1')->getFont()->setBold(true); $definitions->freezePane('A2');
        $book->setActiveSheetIndex(0);

        return $book;
    }
}
