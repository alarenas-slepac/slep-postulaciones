<?php

namespace App\Exports;

use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoAvanceCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionEstablecimientoAvanceExport
{
    public function download(
        Collection $establecimientos,
        int $anio,
        array $filters = [],
        mixed $generatedBy = null
    ): StreamedResponse {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $reportRows = $establecimientos->map(function (Establecimiento $establecimiento) use ($anio) {
            try {
                $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio);

                return [
                    'establecimiento' => $establecimiento,
                    'data' => $data,
                    'avance' => DotacionEstablecimientoAvanceCalculator::fromData($establecimiento, $anio, $data),
                ];
            } catch (\Throwable $exception) {
                report($exception);

                return [
                    'establecimiento' => $establecimiento,
                    'data' => null,
                    'avance' => DotacionEstablecimientoAvanceCalculator::error($establecimiento, $anio),
                ];
            }
        })->values();

        $avances = $reportRows->pluck('avance')->values();
        $resumen = DotacionEstablecimientoAvanceCalculator::resumen($avances);
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator($this->generatedByName($generatedBy))
            ->setTitle('Informe de avance Dotación Establecimiento')
            ->setSubject('Configuración de planes, horas aula y cuadratura contractual')
            ->setDescription('Exportación de todos los establecimientos que cumplen los filtros aplicados.');

        $this->buildSummarySheet(
            $spreadsheet,
            $establecimientos,
            $avances,
            $resumen,
            $anio,
            $filters,
            $generatedBy
        );
        $this->buildDetailSheet($spreadsheet, $avances);
        $this->buildTeacherDetailSheet($spreadsheet, $reportRows, $anio);
        $this->buildAssignmentDetailSheet($spreadsheet, $reportRows, $anio);
        $this->buildSubjectSummarySheet($spreadsheet, $reportRows, $anio);
        $this->buildCombinedCoursesSheet($spreadsheet, $reportRows, $anio);

        $suffix = collect([
            $anio,
            trim((string) ($filters['comuna'] ?? '')),
            (int) ($filters['establecimiento_id'] ?? 0) > 0 ? 'establecimiento_'.$filters['establecimiento_id'] : 'todos',
            now()->format('Ymd_His'),
        ])->filter(fn ($value) => $value !== '')->map(fn ($value) => Str::slug((string) $value, '_'))->implode('_');

        $filename = 'informe_avance_dotacion_'.$suffix.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    private function buildSummarySheet(
        Spreadsheet $spreadsheet,
        Collection $establecimientos,
        Collection $avances,
        array $resumen,
        int $anio,
        array $filters,
        mixed $generatedBy
    ): void {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');
        $sheet->setShowGridlines(false);

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'INFORME DE AVANCE - DOTACIÓN ESTABLECIMIENTO');
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $selected = (int) ($filters['establecimiento_id'] ?? 0) > 0
            ? $establecimientos->firstWhere('id', (int) $filters['establecimiento_id'])
            : null;

        $filterRows = [
            ['Año', $anio],
            ['Comuna', trim((string) ($filters['comuna'] ?? '')) !== '' ? $filters['comuna'] : 'Todas'],
            ['Establecimiento', $selected ? (($selected->rbd ? 'RBD '.$selected->rbd.' - ' : '').$selected->nombre_establecimiento) : 'Todos'],
            ['Registros exportados', $avances->count()],
            ['Generado el', now()->format('d-m-Y H:i:s')],
            ['Generado por', $this->generatedByName($generatedBy)],
        ];

        $row = 3;
        foreach ($filterRows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $kpiStart = 11;
        $kpis = [
            ['Indicador', 'Valor'],
            ['Establecimientos', (int) ($resumen['total'] ?? 0)],
            ['Completos', (int) ($resumen['completos'] ?? 0)],
            ['Avanzados', (int) ($resumen['avanzados'] ?? 0)],
            ['En proceso / avance inicial', (int) ($resumen['en_proceso'] ?? 0)],
            ['Sin iniciar', (int) ($resumen['sin_iniciar'] ?? 0)],
            ['Cursos pendientes de plan', (int) ($resumen['cursos_pendientes'] ?? 0)],
            ['Horas aula pendientes', $this->hoursValue((float) ($resumen['horas_aula_pendientes'] ?? $resumen['horas_pendientes'] ?? 0))],
            ['Horas aula excedidas', $this->hoursValue((float) ($resumen['horas_aula_excedidas'] ?? $resumen['horas_excedidas'] ?? 0))],
            ['Promedio configuración de planes', $this->percentageValue((float) ($resumen['promedio_planes'] ?? 0))],
            ['Promedio asignación de horas aula', $this->percentageValue((float) ($resumen['promedio_asignacion'] ?? 0))],
            ['Promedio general', $this->percentageValue((float) ($resumen['promedio_general'] ?? 0))],
        ];
        $sheet->fromArray($kpis, null, "A{$kpiStart}");
        $kpiEnd = $kpiStart + count($kpis) - 1;

        $this->styleHeader($sheet, "A{$kpiStart}:B{$kpiStart}");
        $sheet->getStyle("A{$kpiStart}:B{$kpiEnd}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E2F3');
        $sheet->getStyle('B'.($kpiStart + 7).':B'.($kpiStart + 8))->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('B'.($kpiStart + 9).':B'.($kpiStart + 11))->getNumberFormat()->setFormatCode('0.0%');

        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(3);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->freezePane('A3');
    }

    private function buildDetailSheet(Spreadsheet $spreadsheet, Collection $avances): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Avance establecimientos');
        $sheet->setShowGridlines(false);

        $groups = [
            'plan_estudio' => 'Plan de estudios (horas aula)',
            'pie_colaborativo' => 'Trabajo colaborativo PIE (horas contrato)',
            'pie_educadora_diferencial' => 'Educadoras Diferenciales PIE (horas contrato)',
            'funciones' => 'Funciones y planes normativos (horas contrato)',
        ];

        $headers = [
            'Año',
            'RBD',
            'Establecimiento',
            'Comuna',
            'Estado',
            'Avance general',
            'Cursos totales',
            'Cursos configurados',
            'Cursos pendientes',
            'Avance planes',
            'Horas aula requeridas',
            'Horas aula asignadas',
            'Horas aula pendientes',
            'Horas aula excedidas',
            'Avance asignación aula',
            'Horas contrato requeridas (total)',
            'Horas contrato asignadas (total)',
            'Horas contrato pendientes (total)',
            'Horas contrato excedidas (total)',
            'Docentes disponibles',
            'Docentes con sobrecarga',
        ];

        foreach ($groups as $label) {
            $headers[] = $label.' - requeridas';
            $headers[] = $label.' - asignadas';
            $headers[] = $label.' - pendientes';
            $headers[] = $label.' - excedidas';
            $headers[] = $label.' - avance';
        }
        $headers[] = 'Observaciones';

        $sheet->fromArray([$headers], null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $this->styleHeader($sheet, "A1:{$lastColumn}1");
        $sheet->getRowDimension(1)->setRowHeight(42);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        $row = 2;
        foreach ($avances as $avance) {
            $desglose = collect(data_get($avance, 'desglose', []))->keyBy('key');

            $values = [
                (int) data_get($avance, 'anio', 0),
                (string) data_get($avance, 'rbd', ''),
                (string) data_get($avance, 'nombre', ''),
                (string) data_get($avance, 'comuna', ''),
                (string) data_get($avance, 'estado.label', ''),
                $this->percentageValue((float) data_get($avance, 'porcentaje_general', 0)),
                (int) data_get($avance, 'planes.total', 0),
                (int) data_get($avance, 'planes.configurados', 0),
                (int) data_get($avance, 'planes.pendientes', 0),
                $this->percentageValue((float) data_get($avance, 'planes.porcentaje', 0)),
                $this->hoursValue((float) data_get($avance, 'asignacion.horas_aula_requeridas', 0)),
                $this->hoursValue((float) data_get($avance, 'asignacion.horas_aula_asignadas', 0)),
                $this->hoursValue((float) data_get($avance, 'asignacion.horas_aula_pendientes', 0)),
                $this->hoursValue((float) data_get($avance, 'asignacion.horas_aula_excedidas', 0)),
                $this->percentageValue((float) data_get($avance, 'asignacion.porcentaje', 0)),
                $this->hoursValue((float) data_get($avance, 'asignacion.horas_contrato_requeridas', 0)),
                $this->hoursValue((float) data_get($avance, 'asignacion.horas_contrato_asignadas', 0)),
                $this->hoursValue((float) data_get($avance, 'asignacion.horas_contrato_pendientes', 0)),
                $this->hoursValue((float) data_get($avance, 'asignacion.horas_contrato_excedidas', 0)),
                (int) data_get($avance, 'asignacion.docentes_disponibles', 0),
                (int) data_get($avance, 'asignacion.docentes_sobrecarga', 0),
            ];

            foreach (array_keys($groups) as $groupKey) {
                $group = $desglose->get($groupKey, []);
                $values[] = $this->hoursValue((float) data_get($group, 'horas_requeridas', 0));
                $values[] = $this->hoursValue((float) data_get($group, 'horas_asignadas', 0));
                $values[] = $this->hoursValue((float) data_get($group, 'horas_pendientes', 0));
                $values[] = $this->hoursValue((float) data_get($group, 'horas_excedidas', 0));
                $values[] = $this->percentageValue((float) data_get($group, 'porcentaje', 0));
            }

            $values[] = collect(data_get($avance, 'observaciones', []))->filter()->implode(' | ');
            $sheet->fromArray([$values], null, "A{$row}");
            $sheet->setCellValueExplicit("B{$row}", (string) data_get($avance, 'rbd', ''), DataType::TYPE_STRING);

            $statusColor = match ((string) data_get($avance, 'estado.key', '')) {
                'completo' => 'E2F0D9',
                'avanzado' => 'DDEBF7',
                'en_proceso' => 'FFF2CC',
                'inicial' => 'DDEBF7',
                default => 'E7E6E6',
            };
            $sheet->getStyle("E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($statusColor);
            $row++;
        }

        $lastRow = max(2, $row - 1);
        $percentageIndexes = [6, 10, 15, 26, 31, 36, 41];
        foreach ($percentageIndexes as $index) {
            $column = Coordinate::stringFromColumnIndex($index);
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.0%');
        }

        $hourIndexes = [11, 12, 13, 14, 16, 17, 18, 19];
        for ($start = 22; $start <= 37; $start += 5) {
            for ($offset = 0; $offset < 4; $offset++) {
                $hourIndexes[] = $start + $offset;
            }
        }
        foreach (array_unique($hourIndexes) as $index) {
            $column = Coordinate::stringFromColumnIndex($index);
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('D9E2F3');
        $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("{$lastColumn}2:{$lastColumn}{$lastRow}")->getAlignment()->setWrapText(true);

        $widths = [
            'A' => 10, 'B' => 11, 'C' => 42, 'D' => 18, 'E' => 27, 'F' => 15,
            'G' => 14, 'H' => 18, 'I' => 17, 'J' => 14,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        for ($columnIndex = 11; $columnIndex < count($headers); $columnIndex++) {
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $sheet->getColumnDimension($column)->setWidth($columnIndex === count($headers) ? 80 : 20);
        }
        $sheet->getColumnDimension($lastColumn)->setWidth(80);
    }

    private function buildTeacherDetailSheet(Spreadsheet $spreadsheet, Collection $reportRows, int $anio): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detalle docentes');
        $sheet->setShowGridlines(false);

        $headers = [
            'Año', 'RBD', 'Establecimiento', 'Comuna', 'RUT', 'Docente', 'Tipo contrato',
            'Financiamiento', 'Horas contrato vigentes', 'Horas aula asignadas',
            'Horas aula 65/35', 'Horas contrato 65/35', 'Horas aula 60/40',
            'Horas contrato 60/40', 'Horas contrato especial', 'Funciones asignadas',
            'Total contrato calculado', 'Diferencia contrato', 'Estado', 'Función declarada', 'Título',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $this->styleHeader($sheet, "A1:{$lastColumn}1");
        $sheet->getRowDimension(1)->setRowHeight(40);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        $row = 2;
        foreach ($reportRows as $reportRow) {
            $establecimiento = $reportRow['establecimiento'];
            $docentes = collect(data_get($reportRow, 'data.docentes', []));

            foreach ($docentes as $docente) {
                $values = [
                    $anio,
                    (string) ($establecimiento->rbd ?? ''),
                    (string) ($establecimiento->nombre_establecimiento ?? ''),
                    (string) ($establecimiento->comuna ?? ''),
                    (string) data_get($docente, 'rut', ''),
                    (string) data_get($docente, 'nombre', ''),
                    (string) data_get($docente, 'tipo_contrato', ''),
                    (string) data_get($docente, 'financiamiento', ''),
                    $this->hoursValue((float) data_get($docente, 'horas_contrato', 0)),
                    $this->hoursValue((float) data_get($docente, 'horas_aula', 0)),
                    $this->hoursValue((float) data_get($docente, 'horas_aula_65_35', 0)),
                    $this->hoursValue((float) data_get($docente, 'horas_contrato_65_35', 0)),
                    $this->hoursValue((float) data_get($docente, 'horas_aula_60_40', 0)),
                    $this->hoursValue((float) data_get($docente, 'horas_contrato_60_40', 0)),
                    $this->hoursValue((float) data_get($docente, 'horas_contrato_especial', 0)),
                    $this->hoursValue((float) data_get($docente, 'horas_funciones_total', 0)),
                    $this->hoursValue((float) data_get($docente, 'horas_asignadas_total', 0)),
                    $this->signedHoursValue(data_get($docente, 'diferencia')),
                    (string) data_get($docente, 'estado_cuadratura.label', ''),
                    (string) data_get($docente, 'funcion', ''),
                    (string) data_get($docente, 'titulo', ''),
                ];

                $sheet->fromArray([$values], null, "A{$row}");
                $sheet->setCellValueExplicit("B{$row}", (string) ($establecimiento->rbd ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("E{$row}", (string) data_get($docente, 'rut', ''), DataType::TYPE_STRING);

                $state = (string) data_get($docente, 'estado_cuadratura.key', '');
                $color = match ($state) {
                    'cuadra' => 'E2F0D9',
                    'faltan_horas', 'pendiente_asignacion' => 'DDEBF7',
                    'sobrecarga' => 'FCE4D6',
                    default => 'E7E6E6',
                };
                $sheet->getStyle("S{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                $row++;
            }
        }

        $lastRow = max(2, $row - 1);
        foreach (range(9, 18) as $index) {
            $column = Coordinate::stringFromColumnIndex($index);
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('D9E2F3');
        $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $widths = [
            'A' => 9, 'B' => 11, 'C' => 40, 'D' => 18, 'E' => 14, 'F' => 34,
            'G' => 18, 'H' => 16, 'I' => 18, 'J' => 18, 'K' => 17, 'L' => 20,
            'M' => 17, 'N' => 20, 'O' => 20, 'P' => 18, 'Q' => 21, 'R' => 18,
            'S' => 22, 'T' => 28, 'U' => 36,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->getStyle("T2:U{$lastRow}")->getAlignment()->setWrapText(true);
    }

    private function buildAssignmentDetailSheet(Spreadsheet $spreadsheet, Collection $reportRows, int $anio): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Asignaturas aula');
        $sheet->setShowGridlines(false);

        $headers = [
            'Año', 'RBD', 'Establecimiento', 'Comuna', 'Curso / sección', 'Asignatura',
            'Bloque / origen', 'Proporción', 'Origen proporción', 'Horas aula plan', 'Horas aula asignadas',
            'Saldo horas aula', 'Estado', 'Personal asignado',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $this->styleHeader($sheet, "A1:{$lastColumn}1");
        $sheet->getRowDimension(1)->setRowHeight(38);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        $row = 2;
        foreach ($reportRows as $reportRow) {
            $establecimiento = $reportRow['establecimiento'];
            $necesidades = collect(data_get($reportRow, 'data.asignacion.necesidades.plan_estudio', []));

            foreach ($necesidades as $necesidad) {
                $docentes = collect(data_get($necesidad, 'asignaciones', []))
                    ->map(function ($asignacion) {
                        $estamento = data_get($asignacion, 'estamento_cobertura', 'docente') === 'asistente'
                            ? 'Asistente'
                            : 'Docente';
                        $nombre = (string) data_get($asignacion, 'docente_nombre', $estamento);
                        $horasAula = (float) data_get($asignacion, 'horas_plan_pedagogicas', 0);
                        $horasContrato = (float) data_get($asignacion, 'horas_contrato', 0);
                        $detalle = number_format($horasAula, 2, ',', '.').' h aula';

                        if ($estamento === 'Asistente') {
                            $detalle .= ' / '.number_format($horasContrato, 2, ',', '.').' h contrato AAEE';
                        }

                        return '['.$estamento.'] '.$nombre.' ('.$detalle.')';
                    })
                    ->implode(' | ');

                $requeridas = (float) data_get($necesidad, 'horas_plan_requeridas', 0);
                $asignadas = (float) data_get($necesidad, 'horas_plan_asignadas', 0);
                $saldo = max(0.0, round($requeridas - $asignadas, 2));

                $values = [
                    $anio,
                    (string) ($establecimiento->rbd ?? ''),
                    (string) ($establecimiento->nombre_establecimiento ?? ''),
                    (string) ($establecimiento->comuna ?? ''),
                    (string) data_get($necesidad, 'curso_label', ''),
                    (string) data_get($necesidad, 'titulo', ''),
                    (string) (data_get($necesidad, 'bloque') ?: data_get($necesidad, 'fuente', '')),
                    (string) data_get($necesidad, 'proporcion', ''),
                    (string) data_get($necesidad, 'origen_proporcion_label', 'Regla general'),
                    $this->hoursValue($requeridas),
                    $this->hoursValue($asignadas),
                    $this->hoursValue($saldo),
                    (string) data_get($necesidad, 'estado.label', ''),
                    $docentes,
                ];

                $sheet->fromArray([$values], null, "A{$row}");
                $sheet->setCellValueExplicit("B{$row}", (string) ($establecimiento->rbd ?? ''), DataType::TYPE_STRING);
                $row++;
            }
        }

        $lastRow = max(2, $row - 1);
        foreach (['J', 'K', 'L'] as $column) {
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('D9E2F3');
        $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("G2:G{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("N2:N{$lastRow}")->getAlignment()->setWrapText(true);

        $widths = [
            'A' => 9, 'B' => 11, 'C' => 40, 'D' => 18, 'E' => 22, 'F' => 34,
            'G' => 38, 'H' => 14, 'I' => 24, 'J' => 18, 'K' => 20, 'L' => 18, 'M' => 16, 'N' => 65,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function buildSubjectSummarySheet(Spreadsheet $spreadsheet, Collection $reportRows, int $anio): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumen asignaturas');
        $sheet->setShowGridlines(false);

        $headers = [
            'Año', 'RBD', 'Establecimiento', 'Comuna', 'Asignatura', 'Proporciones', 'Origen proporción',
            'Horas aula plan', 'Horas aula asignadas', 'Horas aula titulares',
            'Horas aula no titulares', 'Contrato requerido 65/35',
            'Contrato requerido 60/40', 'Contrato requerido especial',
            'Contrato requerido total', 'Contrato asignado 65/35',
            'Contrato asignado 60/40', 'Contrato asignado especial',
            'Contrato asignado total', 'Contrato titulares', 'Contrato no titulares',
            'Saldo aula', 'Saldo contrato', 'Cobertura aula', 'Cobertura titular',
            'Horas aula AAEE', 'Contrato asignado AAEE', 'Estado', 'Cursos / secciones',
        ];

        $sheet->fromArray([$headers], null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $this->styleHeader($sheet, "A1:{$lastColumn}1");
        $sheet->getRowDimension(1)->setRowHeight(42);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        $row = 2;
        foreach ($reportRows as $reportRow) {
            $establecimiento = $reportRow['establecimiento'];
            $asignaturas = collect(data_get($reportRow, 'data.asignaturas.items', []));

            foreach ($asignaturas as $asignatura) {
                $detalle = collect(data_get($asignatura, 'detalle', []));
                $cursos = $detalle
                    ->map(fn ($item) => trim((string) data_get($item, 'curso', '')))
                    ->filter()
                    ->unique()
                    ->implode(' | ');

                $values = [
                    $anio,
                    (string) ($establecimiento->rbd ?? ''),
                    (string) ($establecimiento->nombre_establecimiento ?? ''),
                    (string) ($establecimiento->comuna ?? ''),
                    (string) data_get($asignatura, 'asignatura', ''),
                    collect(data_get($asignatura, 'proporciones', []))->implode(', '),
                    collect(data_get($asignatura, 'origenes_proporcion', []))->implode(', ') ?: 'Regla general',
                    $this->hoursValue((float) data_get($asignatura, 'horas_aula_plan', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_aula_asignadas', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_aula_titulares', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_aula_no_titulares', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_requeridas_65_35', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_requeridas_60_40', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_requeridas_especial', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_requeridas_total', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_asignadas_65_35', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_asignadas_60_40', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_asignadas_especial', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_asignadas_total', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_titulares', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_no_titulares', 0)),
                    $this->signedHoursValue(data_get($asignatura, 'saldo_aula')),
                    $this->signedHoursValue(data_get($asignatura, 'saldo_contrato')),
                    $this->percentageValue((float) data_get($asignatura, 'porcentaje_cobertura', 0)),
                    $this->percentageValue((float) data_get($asignatura, 'porcentaje_cobertura_titular', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_aula_asistentes', 0)),
                    $this->hoursValue((float) data_get($asignatura, 'horas_contrato_asignadas_asistentes', data_get($asignatura, 'horas_contrato_asistentes', 0))),
                    (string) data_get($asignatura, 'estado.label', ''),
                    $cursos,
                ];

                $sheet->fromArray([$values], null, "A{$row}");
                $sheet->setCellValueExplicit("B{$row}", (string) ($establecimiento->rbd ?? ''), DataType::TYPE_STRING);

                $state = (string) data_get($asignatura, 'estado.key', '');
                $color = match ($state) {
                    'cubierta' => 'E2F0D9',
                    'pendiente' => 'FFF2CC',
                    'excedida' => 'FCE4D6',
                    default => 'E7E6E6',
                };
                $sheet->getStyle("AB{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                $row++;
            }
        }

        $lastRow = max(2, $row - 1);
        foreach (array_merge(range(8, 23), [26, 27]) as $index) {
            $column = Coordinate::stringFromColumnIndex($index);
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }
        foreach (['X', 'Y'] as $column) {
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.0%');
        }

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('D9E2F3');
        $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("AC2:AC{$lastRow}")->getAlignment()->setWrapText(true);

        $widths = [
            'A' => 9, 'B' => 11, 'C' => 40, 'D' => 18, 'E' => 34, 'F' => 16,
            'G' => 24, 'H' => 17, 'I' => 19, 'J' => 18, 'K' => 20, 'L' => 22,
            'M' => 22, 'N' => 22, 'O' => 22, 'P' => 22, 'Q' => 22, 'R' => 22,
            'S' => 21, 'T' => 19, 'U' => 21, 'V' => 14, 'W' => 16, 'X' => 16,
            'Y' => 18, 'Z' => 18, 'AA' => 23, 'AB' => 16, 'AC' => 60,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function buildCombinedCoursesSheet(Spreadsheet $spreadsheet, Collection $reportRows, int $anio): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Cursos combinados');
        $sheet->setShowGridlines(false);

        $headers = [
            'Año', 'RBD', 'Establecimiento', 'Comuna', 'Grupo combinado', 'Estado',
            'Cursos integrantes', 'Asignatura', 'Modalidad', 'Horas por curso',
            'Horas aula brutas', 'Horas aula requeridas', 'Reducción aula',
            'Proporción', 'Origen proporción', 'Contrato referencial asignatura',
            'Contrato requerido grupo', 'Horas aula asignadas', 'Saldo aula', 'Observación',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $this->styleHeader($sheet, "A1:{$lastColumn}1");
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");
        $sheet->getRowDimension(1)->setRowHeight(40);

        $row = 2;
        foreach ($reportRows as $reportRow) {
            $establecimiento = $reportRow['establecimiento'];
            $groups = collect(data_get($reportRow, 'data.cursos_combinados.grupos', []));

            foreach ($groups as $group) {
                $subjects = collect(data_get($group, 'asignaturas', []));
                if ($subjects->isEmpty()) {
                    $subjects = collect([[]]);
                }

                foreach ($subjects as $subject) {
                    $hoursByCourse = collect(data_get($subject, 'curso_combinado_horas_por_curso', []))
                        ->map(fn ($hours, $label) => $label.': '.number_format((float) $hours, 2, ',', '.').' h')
                        ->implode(' | ');

                    $values = [
                        $anio,
                        (string) ($establecimiento->rbd ?? ''),
                        (string) ($establecimiento->nombre_establecimiento ?? ''),
                        (string) ($establecimiento->comuna ?? ''),
                        (string) data_get($group, 'nombre', ''),
                        data_get($group, 'activo', false) ? 'Activo' : 'Inactivo',
                        collect(data_get($group, 'miembros', []))->pluck('label')->implode(' + '),
                        (string) data_get($subject, 'titulo', ''),
                        (string) data_get($subject, 'curso_combinado_modalidad', ''),
                        $hoursByCourse,
                        $this->hoursValue((float) data_get($subject, 'horas_plan_brutas', 0)),
                        $this->hoursValue((float) data_get($subject, 'horas_plan_requeridas', 0)),
                        $this->hoursValue((float) data_get($subject, 'horas_plan_reduccion', 0)),
                        (string) data_get($subject, 'proporcion', data_get($group, 'proporcion_label', '')),
                        (string) data_get($subject, 'origen_proporcion_label', ''),
                        $this->hoursValue((float) data_get($subject, 'horas_contrato_requeridas', 0)),
                        $this->hoursValue((float) data_get($group, 'totales.horas_contrato', 0)),
                        $this->hoursValue((float) data_get($subject, 'horas_plan_asignadas', 0)),
                        $this->hoursValue((float) data_get($subject, 'horas_plan_pendientes', 0)),
                        (string) data_get($subject, 'observacion_combinacion', data_get($group, 'observacion', '')),
                    ];

                    $sheet->fromArray([$values], null, "A{$row}");
                    $sheet->setCellValueExplicit("B{$row}", (string) ($establecimiento->rbd ?? ''), DataType::TYPE_STRING);
                    $row++;
                }
            }
        }

        $lastRow = max(2, $row - 1);
        foreach (range(11, 13) as $index) {
            $column = Coordinate::stringFromColumnIndex($index);
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }
        foreach (range(16, 19) as $index) {
            $column = Coordinate::stringFromColumnIndex($index);
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
        }
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('D9E2F3');
        $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        foreach (['G', 'J', 'T'] as $column) {
            $sheet->getStyle("{$column}2:{$column}{$lastRow}")->getAlignment()->setWrapText(true);
        }

        $widths = [
            'A' => 9, 'B' => 11, 'C' => 40, 'D' => 18, 'E' => 28, 'F' => 12,
            'G' => 45, 'H' => 34, 'I' => 16, 'J' => 55, 'K' => 17, 'L' => 20,
            'M' => 16, 'N' => 14, 'O' => 25, 'P' => 23, 'Q' => 22, 'R' => 20,
            'S' => 15, 'T' => 45,
        ];
        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function styleHeader(mixed $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']],
            ],
        ]);
    }

    private function hoursValue(float $hours): float
    {
        return round(max(0.0, $hours), 2);
    }

    private function signedHoursValue(mixed $hours): ?float
    {
        return is_numeric($hours) ? round((float) $hours, 2) : null;
    }

    private function percentageValue(float $percentage): float
    {
        return min(100.0, max(0.0, $percentage)) / 100;
    }

    private function generatedByName(mixed $user): string
    {
        if (! $user) {
            return 'Sistema SLEP';
        }

        $name = trim(implode(' ', array_filter([
            (string) ($user->nombres ?? ''),
            (string) ($user->apellido_paterno ?? ''),
            (string) ($user->apellido_materno ?? ''),
        ])));

        return $name !== '' ? $name : ((string) ($user->email ?? 'Sistema SLEP'));
    }
}
