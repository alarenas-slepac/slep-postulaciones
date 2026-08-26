<?php

namespace App\Exports;

use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionSobredotacionCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionSobredotacionEstablecimientosExport
{
    public function download(
        Collection $establecimientos,
        int $anio,
        array $filters = [],
        mixed $generatedBy = null
    ): StreamedResponse {
        $this->prepareRuntime();
        $rows = $establecimientos->map(function (Establecimiento $establecimiento) use ($anio) {
            try {
                $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio);

                return [
                    'establecimiento' => $establecimiento,
                    'sobredotacion' => DotacionSobredotacionCalculator::build(
                        $data['docentes'],
                        $data['resumen'],
                        data_get($data, 'asignacion.necesidades.funciones', [])
                    ),
                    'error' => null,
                ];
            } catch (\Throwable $exception) {
                report($exception);

                return [
                    'establecimiento' => $establecimiento,
                    'sobredotacion' => null,
                    'error' => 'No fue posible calcular el detalle de sobredotación para este establecimiento.',
                ];
            }
        })->values();

        $spreadsheet = $this->workbookFromRows($rows, $anio, $generatedBy);
        $suffix = collect([
            $anio,
            trim((string) ($filters['comuna'] ?? '')),
            trim((string) ($filters['q'] ?? '')),
            now()->format('Ymd_His'),
        ])->filter(fn ($value) => $value !== '')
            ->map(fn ($value) => Str::slug((string) $value, '_'))
            ->implode('_');
        $filename = 'detalle_sobredotacion_establecimientos_'.$suffix.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * @param  Collection<int, array{establecimiento: Establecimiento, sobredotacion: ?array, error?: ?string}>  $rows
     */
    public function workbookFromRows(Collection $rows, int $anio, mixed $generatedBy = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator($this->generatedByName($generatedBy))
            ->setTitle('Detalle de sobredotación por establecimiento')
            ->setSubject('Sobredotación Aula, funciones ajustables y dotación PIE')
            ->setDescription('Libro con una hoja independiente por establecimiento.');

        if ($rows->isEmpty()) {
            $this->buildEmptySheet($spreadsheet->getActiveSheet(), $anio);

            return $spreadsheet;
        }

        $usedTitles = [];
        foreach ($rows->values() as $index => $row) {
            $sheet = $index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $establecimiento = $row['establecimiento'];
            $sheet->setTitle($this->uniqueSheetTitle($establecimiento, $usedTitles));
            $this->buildEstablishmentSheet(
                $sheet,
                $establecimiento,
                (array) ($row['sobredotacion'] ?? []),
                $anio,
                $row['error'] ?? null
            );
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildEstablishmentSheet(
        Worksheet $sheet,
        Establecimiento $establecimiento,
        array $sobredotacion,
        int $anio,
        ?string $error
    ): void {
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);

        $sheet->mergeCells('A1:L1');
        $sheet->setCellValue('A1', 'DETALLE DE SOBREDOTACIÓN - DOTACIÓN ESTABLECIMIENTO');
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        $sheet->setCellValue('A2', 'Año');
        $sheet->setCellValue('B2', $anio);
        $sheet->setCellValue('D2', 'RBD');
        $sheet->setCellValueExplicit('E2', (string) ($establecimiento->rbd ?? ''), DataType::TYPE_STRING);
        $sheet->setCellValue('G2', 'Comuna');
        $sheet->mergeCells('H2:I2');
        $sheet->setCellValue('H2', (string) ($establecimiento->comuna ?? ''));
        $sheet->setCellValue('J2', 'Generado');
        $sheet->mergeCells('K2:L2');
        $sheet->setCellValue('K2', now()->format('d-m-Y H:i'));
        $sheet->setCellValue('A3', 'Establecimiento');
        $sheet->mergeCells('B3:L3');
        $sheet->setCellValue('B3', (string) ($establecimiento->nombre_establecimiento ?? ''));
        $sheet->getStyle('A2:L3')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        foreach (['A2', 'D2', 'G2', 'J2', 'A3'] as $cell) {
            $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB('1F4E78');
        }

        if ($error !== null || $sobredotacion === []) {
            $sheet->mergeCells('A5:L7');
            $sheet->setCellValue('A5', $error ?: 'No existen datos de sobredotación disponibles para este establecimiento.');
            $sheet->getStyle('A5:L7')->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FCE4D6']],
                'font' => ['bold' => true, 'color' => ['rgb' => 'C00000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $this->setColumnWidths($sheet);

            return;
        }

        $aula = (array) data_get($sobredotacion, 'aula', []);
        $aulaResumen = (array) data_get($aula, 'resumen', []);
        $aulaFormula = (array) data_get($aula, 'formula', []);
        $pie = (array) data_get($sobredotacion, 'pie', []);
        $pieResumen = (array) data_get($pie, 'resumen', []);
        $pieFormula = (array) data_get($pie, 'formula', []);

        $this->writeSummary($sheet, $aulaResumen, $aulaFormula, $pieResumen, $pieFormula);
        $row = 15;

        $row = $this->writeTable(
            $sheet,
            $row,
            'Contrato Aula sin asignación registrada',
            ['RUT', 'Docente', 'Función', 'Tipo contrato', 'Contrato Aula', 'Protegidas', 'Declaradas', 'Total asignado', 'Sin asignación', 'Titular / Planta', 'Contrata', 'Sobreasignadas'],
            collect(data_get($aula, 'items', []))->map(fn (array $item) => [
                (string) ($item['rut'] ?? ''),
                (string) ($item['nombre'] ?? ''),
                (string) ($item['funcion'] ?? ''),
                (string) ($item['tipo_contrato'] ?? ''),
                $this->hours($item['horas_contrato_categoria'] ?? 0),
                $this->hours($item['horas_asignadas_protegidas'] ?? 0),
                $this->hours($item['horas_declaradas_ajustables'] ?? 0),
                $this->hours($item['horas_asignadas_total'] ?? 0),
                $this->hours($item['horas_sobredotacion_total'] ?? 0),
                $this->hours($item['horas_sobredotacion_planta'] ?? 0),
                $this->hours($item['horas_sobredotacion_contrata'] ?? 0),
                $this->hours($item['horas_sobreasignadas'] ?? 0),
            ])->all(),
            range(5, 12),
            'FCE4D6'
        );

        $ajustes = collect(data_get($aula, 'ajustes', []));
        $row = $this->writeTable(
            $sheet,
            $row,
            'Funciones declaradas asignadas a docentes (revisables)',
            ['RUT', 'Docente', 'Función', 'Tipo contrato', 'Contrato Aula', 'Total declarado asignado', 'Titulares', 'Contrata', 'Sin cobertura', 'Protegidas', 'Sin asignación', 'Sobreasignadas'],
            $ajustes->map(fn (array $item) => [
                (string) ($item['rut'] ?? ''),
                (string) ($item['nombre'] ?? ''),
                (string) ($item['funcion'] ?? ''),
                (string) ($item['tipo_contrato'] ?? ''),
                $this->hours($item['horas_contrato_categoria'] ?? 0),
                $this->hours($item['horas_declaradas_ajustables'] ?? 0),
                $this->hours($item['horas_declaradas_titulares'] ?? 0),
                $this->hours($item['horas_declaradas_contrata'] ?? 0),
                $this->hours($item['horas_declaradas_sin_cobertura'] ?? 0),
                $this->hours($item['horas_asignadas_protegidas'] ?? 0),
                $this->hours($item['horas_sobredotacion_total'] ?? 0),
                $this->hours($item['horas_sobreasignadas'] ?? 0),
            ])->all(),
            range(5, 12),
            'FFF2CC'
        );

        $detalleAjustes = $ajustes->flatMap(function (array $item) {
            return collect($item['horas_declaradas_detalle'] ?? [])->map(fn (array $detalle) => [
                (string) ($item['rut'] ?? ''),
                (string) ($item['nombre'] ?? ''),
                (string) ($detalle['tipo_label'] ?? ''),
                (string) ($detalle['nombre'] ?? ''),
                (string) ($detalle['subtipo_label'] ?? ''),
                (string) ($detalle['subvencion'] ?? ''),
                $this->hours($detalle['horas'] ?? 0),
            ]);
        })->values()->all();
        $row = $this->writeTable(
            $sheet,
            $row,
            'Detalle de funciones no normativas',
            ['RUT', 'Docente', 'Tipo', 'Función o actividad', 'Subtipo', 'Subvención', 'Horas'],
            $detalleAjustes,
            [7],
            'FFF2CC'
        );

        $lastRow = $this->writeTable(
            $sheet,
            $row,
            'Sobredotación PIE',
            ['RUT', 'Docente', 'Función', 'Tipo contrato', 'Contrato PIE', 'Necesidad cubierta', 'Sobredotación', 'Titular / Planta', 'Contrata'],
            collect(data_get($pie, 'items', []))->map(fn (array $item) => [
                (string) ($item['rut'] ?? ''),
                (string) ($item['nombre'] ?? ''),
                (string) ($item['funcion'] ?? ''),
                (string) ($item['tipo_contrato'] ?? ''),
                $this->hours($item['horas_contrato_categoria'] ?? 0),
                $this->hours($item['horas_necesidad_cubierta'] ?? 0),
                $this->hours($item['horas_sobredotacion_total'] ?? 0),
                $this->hours($item['horas_sobredotacion_planta'] ?? 0),
                $this->hours($item['horas_sobredotacion_contrata'] ?? 0),
            ])->all(),
            range(5, 9),
            'DDEBF7'
        ) - 1;

        $this->setColumnWidths($sheet);
        $sheet->freezePane('A6');
        $sheet->getStyle("A1:L{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("B15:D{$lastRow}")->getAlignment()->setWrapText(true);
        $sheet->getPageSetup()->setPrintArea("A1:L{$lastRow}");
        $sheet->getHeaderFooter()->setOddFooter('&L'.$anio.' - '.($establecimiento->rbd ?? '').'&RPágina &P de &N');
    }

    private function writeSummary(
        Worksheet $sheet,
        array $aula,
        array $aulaFormula,
        array $pie,
        array $pieFormula
    ): void {
        foreach ([
            ['A6:D6', 'Dotación General', 'D9EAF7'],
            ['E6:H6', 'Contrato Aula sin asignación', 'FCE4D6'],
            ['I6:L6', 'Dotación PIE', 'DDEBF7'],
        ] as [$range, $title, $color]) {
            $sheet->mergeCells($range);
            $sheet->setCellValue(explode(':', $range)[0], $title);
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '1F1F1F']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $brecha = (float) ($aula['brecha_estructural'] ?? 0);
        $sobredotacionGeneral = (float) ($aula['horas_sobredotacion_total'] ?? 0);
        $pieSobredotacion = (float) ($pie['horas_sobredotacion_total'] ?? 0);
        $pieNecesarias = (float) ($pie['horas_necesarias_pendientes'] ?? 0);
        $generalEstado = $sobredotacionGeneral > 0.01 ? 'Horas de sobredotación' : ($brecha > 0.01 ? 'Horas necesarias' : 'Cuadrada');
        $estructuralEstado = $brecha < -0.01 ? 'Sobredotación estructural' : ($brecha > 0.01 ? 'Horas necesarias' : 'Cuadrada');
        $pieEstado = $pieSobredotacion > 0.01 ? 'Sobredotación PIE' : ($pieNecesarias > 0.01 ? 'Horas necesarias' : 'Cuadrada');

        $general = [
            ['Dotación general', $generalEstado, 'Horas', $this->hours($sobredotacionGeneral ?: max(0, $brecha))],
            ['Brecha estructural', $estructuralEstado, 'Horas', round(abs($brecha), 2)],
        ];
        $aulaRows = [
            ['Contrato Aula individualizado', $this->hours($aula['horas_dotacion_total'] ?? 0), 'Asignaciones protegidas', $this->hours($aula['horas_asignadas_protegidas'] ?? 0)],
            ['Declaradas docentes', $this->hours($aula['horas_declaradas_ajustables'] ?? 0), 'Declaradas requeridas', $this->hours($aula['horas_declaradas_requeridas'] ?? $aulaFormula['bloque_declarado'] ?? 0)],
            ['Declaradas pendientes', $this->hours($aula['horas_declaradas_pendientes'] ?? 0), 'Contrato sin asignación', $this->hours($aula['horas_sobredotacion_total'] ?? 0)],
            ['Sin asignación Titular', $this->hours($aula['horas_sobredotacion_planta'] ?? 0), 'Sin asignación Contrata', $this->hours($aula['horas_sobredotacion_contrata'] ?? 0)],
            ['Universo sujeto a revisión', $this->hours($aula['horas_universo_revision'] ?? $aula['horas_potencial_ajuste'] ?? 0), 'Diferencia entre indicadores', round((float) ($aula['horas_diferencia_indicadores'] ?? 0), 2)],
            ['Declaradas sin cobertura', $this->hours($aula['horas_declaradas_sin_cobertura'] ?? 0), 'Sobreasignadas', $this->hours($aula['horas_sobreasignadas'] ?? 0)],
        ];
        $pieRows = [
            ['Horas PIE necesarias', $this->hours($pieFormula['contrato_pie_necesario'] ?? 0), 'Contrato docente PIE', $this->hours($pieFormula['contrato_docente_pie'] ?? 0)],
            ['Resultado', $pieEstado, 'Horas', $this->hours($pieSobredotacion ?: $pieNecesarias)],
            ['Sobredotación Titular', $this->hours($pie['horas_sobredotacion_planta'] ?? 0), 'Sobredotación Contrata', $this->hours($pie['horas_sobredotacion_contrata'] ?? 0)],
            ['Docentes analizados', (int) ($pie['docentes_analizados'] ?? 0), 'Docentes identificados', (int) ($pie['docentes_sobredotacion'] ?? 0)],
        ];

        $sheet->fromArray($general, null, 'A7');
        $sheet->fromArray($aulaRows, null, 'E7');
        $sheet->fromArray($pieRows, null, 'I7');
        foreach (['A7:D10', 'E7:H12', 'I7:L10'] as $range) {
            $sheet->getStyle($range)->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('D9E2F3');
        }
        foreach (['A7', 'C7', 'A8', 'C8', 'A9', 'C9', 'A10', 'C10', 'E7', 'G7', 'E8', 'G8', 'E9', 'G9', 'E10', 'G10', 'E11', 'G11', 'E12', 'G12', 'I7', 'K7', 'I8', 'K8', 'I9', 'K9', 'I10', 'K10'] as $cell) {
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }
        $sheet->getStyle('A7:L12')->getAlignment()->setWrapText(true);
        foreach (['B7:D10', 'F7:H12', 'J7:L10'] as $range) {
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('0.00');
        }
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, int>  $numericColumns  One-based column indexes.
     */
    private function writeTable(
        Worksheet $sheet,
        int $startRow,
        string $title,
        array $headers,
        array $rows,
        array $numericColumns,
        string $sectionColor
    ): int {
        $lastColumn = chr(64 + count($headers));
        $sheet->mergeCells("A{$startRow}:{$lastColumn}{$startRow}");
        $sheet->setCellValue("A{$startRow}", $title);
        $sheet->getStyle("A{$startRow}:{$lastColumn}{$startRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $sectionColor]],
        ]);
        $headerRow = $startRow + 1;
        $sheet->fromArray([$headers], null, "A{$headerRow}");
        $this->styleTableHeader($sheet, "A{$headerRow}:{$lastColumn}{$headerRow}");

        $dataStart = $headerRow + 1;
        if ($rows === []) {
            $sheet->mergeCells("A{$dataStart}:{$lastColumn}{$dataStart}");
            $sheet->setCellValue("A{$dataStart}", 'Sin registros para este establecimiento.');
            $sheet->getStyle("A{$dataStart}:{$lastColumn}{$dataStart}")->getFont()->getColor()->setRGB('7F8C8D');
            $sheet->getStyle("A{$dataStart}:{$lastColumn}{$dataStart}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $dataEnd = $dataStart;
        } else {
            $sheet->fromArray($rows, null, "A{$dataStart}");
            $dataEnd = $dataStart + count($rows) - 1;
            foreach ($numericColumns as $columnIndex) {
                $column = chr(64 + $columnIndex);
                $sheet->getStyle("{$column}{$dataStart}:{$column}{$dataEnd}")
                    ->getNumberFormat()->setFormatCode('0.00');
            }
            $sheet->getStyle("A{$dataStart}:{$lastColumn}{$dataEnd}")
                ->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E7E6E6');
        }

        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$dataEnd}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        return $dataEnd + 2;
    }

    private function styleTableHeader(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']],
            ],
        ]);
    }

    private function buildEmptySheet(Worksheet $sheet, int $anio): void
    {
        $sheet->setTitle('Sin resultados');
        $sheet->setShowGridlines(false);
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'DETALLE DE SOBREDOTACIÓN - DOTACIÓN ESTABLECIMIENTO');
        $sheet->mergeCells('A3:F5');
        $sheet->setCellValue('A3', "No existen establecimientos para los filtros aplicados en {$anio}.");
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A3:F5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setWidth(20);
        }
    }

    private function setColumnWidths(Worksheet $sheet): void
    {
        foreach ([
            'A' => 16, 'B' => 34, 'C' => 30, 'D' => 23,
            'E' => 19, 'F' => 19, 'G' => 19, 'H' => 19,
            'I' => 19, 'J' => 19, 'K' => 19, 'L' => 19,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /** @param array<string, bool> $usedTitles */
    private function uniqueSheetTitle(Establecimiento $establecimiento, array &$usedTitles): string
    {
        $prefix = trim((string) ($establecimiento->rbd ?? ''));
        $name = trim((string) ($establecimiento->nombre_establecimiento ?? 'Establecimiento'));
        $base = trim(($prefix !== '' ? $prefix.' - ' : '').$name);
        $base = preg_replace('/[\\\\\/\?\*\[\]:]+/u', ' ', $base) ?: 'Establecimiento';
        $base = trim((string) preg_replace('/\s+/u', ' ', $base), " '");
        $base = $base !== '' ? $base : 'Establecimiento';
        $candidate = mb_substr($base, 0, 31);
        $number = 2;

        while (isset($usedTitles[mb_strtolower($candidate)])) {
            $suffix = " ({$number})";
            $candidate = mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix;
            $number++;
        }

        $usedTitles[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    private function hours(mixed $value): float
    {
        return round(max(0.0, (float) $value), 2);
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

    private function prepareRuntime(): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        try {
            DB::connection()->disableQueryLog();
        } catch (\Throwable $exception) {
            // La descarga puede continuar si la conexión no permite modificar el query log.
        }

        if (function_exists('gc_enable')) {
            gc_enable();
        }
    }
}
