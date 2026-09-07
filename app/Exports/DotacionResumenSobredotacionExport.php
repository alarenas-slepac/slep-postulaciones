<?php

namespace App\Exports;

use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionResumenSobredotacionExport
{
    public const ROLES = ['admin', 'coordinador_uatp', 'coordinador_gdp'];

    public const HEADERS = [
        'RBD', 'Establecimiento', 'Matrícula', 'Cursos', 'Docentes',
        'Hrs. Contrato Educación Parvularia + PIE', 'Hrs. Contrato plan + PIE',
        'Hrs. Funciones directivas / técnico pedagógicas y planes normativos',
        'Hrs. Otras funciones no normativas', 'Horas contrato PIE necesarias',
        'Horas contrato docentes', 'Horas contrato aula', 'Horas contrato parvularia',
        'Horas contrato docente PIE', 'Sobredotación plan de estudio + funciones normativas',
        'Hrs. Sobredotación Parvularia', 'Hrs. Sobredotación PIE',
    ];

    public static function canExport(?string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    public function download(Collection $establecimientos, int $anio, array $filters = []): StreamedResponse
    {
        @set_time_limit(240);
        $rows = collect();
        foreach ($establecimientos as $establecimiento) {
            try {
                $rows->push($this->row($establecimiento, $this->summary($establecimiento, $anio)));
            } catch (\Throwable $exception) {
                report($exception);
                // Nunca entregar ceros o totales parciales como si fueran un informe completo.
                throw ValidationException::withMessages([
                    'export_resumen_sobredotacion' => 'No se pudo calcular el RBD '.$establecimiento->rbd.'. No se generó el resumen; revise el establecimiento e intente nuevamente.',
                ]);
            } finally {
                $establecimiento->unsetRelations();
            }
        }
        $book = $this->workbook($rows, $anio, $filters);

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'resumen_sobredotacion_'.$anio.'_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    protected function summary(Establecimiento $establecimiento, int $anio): array
    {
        return DotacionEstablecimientoCalculator::build($establecimiento, $anio, false)['resumen'];
    }

    public function row(Establecimiento $establecimiento, array $summary): array
    {
        $number = fn ($key) => round((float) ($summary[$key] ?? 0), 2);
        $parvularia = $number('contrato_educacion_parvularia_mas_trabajo_colaborativo_pie');
        $general = round((float) ($summary['contrato_plan_general_mas_trabajo_colaborativo_pie']
            ?? max(0, $number('contrato_plan_mas_trabajo_colaborativo_pie') - $parvularia)), 2);
        $normativas = $number('horas_dotacion_funciones_normativas');
        $contratoParvularia = $number('horas_contrato_docentes_parvularia');
        $aula = round((float) ($summary['horas_contrato_docentes_aula_general']
            ?? max(0, $number('horas_contrato_docentes_aula') - $contratoParvularia)), 2);
        $pieNecesarias = $number('horas_contrato_pie_necesarias');
        $pieContrato = $number('horas_contrato_docente_pie');

        return [
            (string) $establecimiento->rbd, (string) $establecimiento->nombre_establecimiento,
            (int) ($summary['matricula_total'] ?? 0), (int) ($summary['cursos_total'] ?? 0),
            (int) ($summary['docentes_total'] ?? 0), $parvularia, $general, $normativas,
            $number('horas_dotacion_funciones_declaradas'), $pieNecesarias,
            $number('horas_contrato_docentes'), $aula, $contratoParvularia, $pieContrato,
            round($general + $normativas - $aula, 2),
            round($parvularia - $contratoParvularia, 2), round($pieNecesarias - $pieContrato, 2),
        ];
    }

    public function workbook(Collection $rows, int $anio, array $filters = []): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Resumen sobredotación');
        $sheet->setShowGridlines(false);
        $sheet->mergeCells('A1:Q1')->setCellValue('A1', 'RESUMEN DE SOBREDOTACIÓN POR ESTABLECIMIENTO');
        $sheet->mergeCells('A2:Q2')->setCellValueExplicit('A2',
            'Año: '.$anio.' | Comuna: '.($filters['comuna'] ?? 'Todas').' | Búsqueda: '.($filters['q'] ?? '').' | Establecimientos: '.$rows->count(), DataType::TYPE_STRING);
        foreach ([['A', 'F', 14], ['G', 'K', 15], ['L', 'Q', 16]] as [$first, $last, $index]) {
            $sheet->mergeCells($first.'3:'.$last.'3')->setCellValue($first.'3', self::HEADERS[$index].' — total excedente');
            $total = round($rows->sum(fn ($row) => max(0, -(float) $row[$index])), 2);
            $sheet->mergeCells($first.'4:'.$last.'4')->setCellValue($first.'4', $total);
            $sheet->getStyle($first.'4:'.$last.'4')->getFont()->setBold(true)->setSize(20)->getColor()->setRGB('C00000');
            $sheet->getStyle($first.'4:'.$last.'4')->getNumberFormat()->setFormatCode('#,##0.##');
        }
        $sheet->mergeCells('A5:Q5')->setCellValue('A5', 'Totales: suma absoluta de resultados negativos, sin compensar déficits. Filas: negativo = sobredotación (rojo); cero o positivo = sin sobredotación (verde).');
        $sheet->fromArray([self::HEADERS], null, 'A6');
        foreach ($rows->values() as $index => $row) {
            $line = $index + 7;
            foreach ($row as $column => $value) {
                $sheet->setCellValueExplicit([$column + 1, $line], $value, $column < 2 ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
            }
            foreach (['O', 'P', 'Q'] as $column) {
                $negative = (float) $sheet->getCell($column.$line)->getValue() < 0;
                $sheet->getStyle($column.$line)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $negative ? 'F8D7DA' : 'D1E7DD']],
                    'font' => ['bold' => true, 'color' => ['rgb' => $negative ? '842029' : '0F5132']],
                ]);
            }
        }
        $lastRow = max(6, 6 + $rows->count());
        $sheet->getStyle('A1:Q'.$lastRow)->getAlignment()->setWrapText(true)->setVertical('center');
        foreach (['A1:Q1', 'A6:Q6'] as $range) {
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            ]);
        }
        $sheet->getStyle('A3:Q4')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('A3:Q3')->getFont()->setBold(true);
        foreach ([1 => 30, 2 => 26, 3 => 36, 4 => 34, 5 => 28, 6 => 100] as $line => $height) {
            $sheet->getRowDimension($line)->setRowHeight($height);
        }
        if ($rows->isNotEmpty()) {
            $sheet->getStyle('F7:Q'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.##;[Red]-#,##0.##;0');
        }
        $sheet->getColumnDimension('A')->setWidth(13);
        $sheet->getColumnDimension('B')->setWidth(44);
        foreach (range('C', 'Q') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['C', 'D', 'E']) ? 12 : 23);
        }
        $sheet->setAutoFilter('A6:Q'.$lastRow);
        $sheet->freezePane('C7');
        $sheet->getComment('G6')->getText()->createTextRun('Plan General + PIE: excluye Educación Parvularia, que se presenta por separado en F.');
        $sheet->getComment('I6')->getText()->createTextRun('Horas declaradas de otras funciones no normativas, no las asignadas. No se incluyen en la brecha de plan de estudio + funciones normativas.');
        $sheet->getComment('J6')->getText()->createTextRun('Necesidad normativa de contrato PIE, no horas asignadas.');

        return $book;
    }
}
