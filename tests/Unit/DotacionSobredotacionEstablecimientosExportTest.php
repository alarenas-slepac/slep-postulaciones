<?php

namespace Tests\Unit;

use App\Exports\DotacionSobredotacionEstablecimientosExport;
use App\Models\Establecimiento;
use App\Support\DotacionSobredotacionCalculator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DotacionSobredotacionEstablecimientosExportTest extends TestCase
{
    public function test_genera_una_hoja_unica_por_establecimiento_con_todos_los_detalles(): void
    {
        $sobredotacion = $this->sobredotacion();
        $establecimientoUno = $this->establecimiento(
            1,
            '10001',
            'Escuela / Nueva [Andalién] con un nombre suficientemente extenso',
            'Tomé'
        );
        $establecimientoDos = $this->establecimiento(
            2,
            '10001',
            'Escuela / Nueva [Andalién] con un nombre suficientemente extenso',
            'Tomé'
        );

        $workbook = (new DotacionSobredotacionEstablecimientosExport)->workbookFromRows(collect([
            ['establecimiento' => $establecimientoUno, 'sobredotacion' => $sobredotacion],
            ['establecimiento' => $establecimientoDos, 'sobredotacion' => $sobredotacion],
        ]), 2026);

        $this->assertSame(2, $workbook->getSheetCount());
        $this->assertCount(2, array_unique(array_map('mb_strtolower', $workbook->getSheetNames())));
        foreach ($workbook->getSheetNames() as $title) {
            $this->assertLessThanOrEqual(31, mb_strlen($title));
            $this->assertDoesNotMatchRegularExpression('/[\\\\\/\?\*\[\]:]/u', $title);
        }

        $firstSheet = $workbook->getSheet(0);
        $this->assertFalse($firstSheet->getShowGridlines());
        $this->assertSame('A6', $firstSheet->getFreezePane());
        $this->assertSame(34.0, $firstSheet->getColumnDimension('B')->getWidth());
        $this->assertSame('1F4E78', $firstSheet->getStyle('A1')->getFill()->getStartColor()->getRGB());

        $sheetRows = $firstSheet->toArray(null, true, false, false);
        $values = collect($sheetRows)->flatten();
        $this->assertContains('DETALLE DE SOBREDOTACIÓN - DOTACIÓN ESTABLECIMIENTO', $values);
        $this->assertContains('Contrato Aula sin asignación registrada', $values);
        $this->assertContains('Funciones declaradas asignadas a docentes (revisables)', $values);
        $this->assertContains('Detalle de funciones no normativas', $values);
        $this->assertContains('Sobredotación PIE', $values);
        $this->assertContains('Apoyo a dirección', $values);
        $this->assertContains('Coordinación de convivencia', $values);
        $this->assertContains('Docente mixto ajustable', $values);
        $this->assertContains('Educadora diferencial', $values);

        $ajusteHeader = collect($sheetRows)->search(fn (array $row) => in_array('Total declarado asignado', $row, true));
        $this->assertIsInt($ajusteHeader);
        $ajusteRow = $sheetRows[$ajusteHeader + 1];
        $this->assertSame(14.0, $ajusteRow[5]);
        $this->assertSame(4.0, $ajusteRow[6]);
        $this->assertSame(10.0, $ajusteRow[7]);
        $this->assertSame(0.0, (float) ($ajusteRow[8] ?? 0));

        $temporaryBase = tempnam(sys_get_temp_dir(), 'sobredotacion_export_');
        $this->assertNotFalse($temporaryBase);
        @unlink($temporaryBase);
        $path = $temporaryBase.'.xlsx';

        try {
            (new Xlsx($workbook))->save($path);
            $this->assertFileExists($path);
            $this->assertGreaterThan(0, filesize($path));

            $reloaded = IOFactory::load($path);
            $this->assertSame(2, $reloaded->getSheetCount());
            $allValues = collect($reloaded->getAllSheets())
                ->flatMap(fn ($sheet) => collect($sheet->toArray(null, true, true, false))->flatten())
                ->filter(fn ($value) => is_string($value));
            $this->assertFalse($allValues->contains(fn (string $value) => preg_match('/#REF!|#DIV\/0!|#VALUE!|#NAME\?|#N\/A/', $value) === 1));
            $reloaded->disconnectWorksheets();
        } finally {
            $workbook->disconnectWorksheets();
            @unlink($path);
        }
    }

    public function test_genera_hoja_informativa_cuando_los_filtros_no_tienen_resultados(): void
    {
        $workbook = (new DotacionSobredotacionEstablecimientosExport)
            ->workbookFromRows(collect(), 2026);

        $this->assertSame(['Sin resultados'], $workbook->getSheetNames());
        $this->assertSame(
            'No existen establecimientos para los filtros aplicados en 2026.',
            $workbook->getActiveSheet()->getCell('A3')->getValue()
        );

        $workbook->disconnectWorksheets();
    }

    public function test_vista_general_incluye_exportacion_y_conserva_filtros(): void
    {
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/index.blade.php'));
        $controllerSource = file_get_contents(app_path('Http/Controllers/Admin/DotacionEstablecimientoController.php'));

        $this->assertIsString($source);
        $this->assertIsString($controllerSource);
        $this->assertStringContainsString('Excel sobredotación', $source);
        $this->assertStringContainsString("'export_sobredotacion' => 1", $source);
        $this->assertStringContainsString("'q' => \$q", $source);
        $this->assertStringContainsString("'comuna' => \$comuna", $source);
        $this->assertStringContainsString('DotacionSobredotacionCalculator::canView($activeRole)', $source);
        $this->assertStringContainsString("boolean('export_sobredotacion')", $controllerSource);
        $this->assertStringContainsString('DotacionSobredotacionEstablecimientosExport', $controllerSource);
        $this->assertStringContainsString('abort_unless(DotacionSobredotacionCalculator::canView($activeRole), 403)', $controllerSource);
    }

    private function sobredotacion(): array
    {
        return DotacionSobredotacionCalculator::build([
            [
                'rut' => '11111111-1',
                'nombre' => 'Docente mixto ajustable',
                'funcion' => 'Docente',
                'tipo_contrato' => 'PLANTA / CONTRATA',
                'es_titular' => true,
                'horas_contrato' => 44,
                'horas_planta' => 30,
                'horas_contrata' => 14,
                'horas_asignadas_protegidas' => 26,
                'horas_declaradas_ajustables' => 14,
                'horas_declaradas_detalle' => [
                    [
                        'tipo_label' => 'Función directiva',
                        'nombre' => 'Apoyo a dirección',
                        'subtipo_label' => 'Directiva',
                        'subvencion' => 'General',
                        'horas' => 6,
                    ],
                    [
                        'tipo_label' => 'Otra función',
                        'nombre' => 'Coordinación de convivencia',
                        'subtipo_label' => 'Otras funciones docentes',
                        'subvencion' => 'General',
                        'horas' => 8,
                    ],
                ],
                'horas_contrato_pie' => 0,
            ],
            [
                'rut' => '22222222-2',
                'nombre' => 'Docente contrata sin asignación',
                'funcion' => 'Docente',
                'tipo_contrato' => 'CONTRATA',
                'es_titular' => false,
                'horas_contrato' => 44,
                'horas_planta' => 0,
                'horas_contrata' => 44,
                'horas_asignadas_protegidas' => 20,
                'horas_declaradas_ajustables' => 0,
                'horas_contrato_pie' => 0,
            ],
            [
                'rut' => '33333333-3',
                'nombre' => 'Educadora diferencial',
                'funcion' => 'PIE',
                'tipo_contrato' => 'CONTRATA',
                'es_titular' => false,
                'horas_contrato' => 30,
                'horas_planta' => 0,
                'horas_contrata' => 30,
                'horas_asignadas_protegidas' => 0,
                'horas_declaradas_ajustables' => 0,
                'horas_contrato_pie' => 30,
            ],
        ], [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 46,
            'horas_dotacion_funciones_normativas' => 0,
            'horas_contrato_docentes_aula' => 88,
            'horas_dotacion_funciones_declaradas' => 14,
            'horas_contrato_pie_necesarias' => 10,
            'horas_contrato_docente_pie' => 30,
        ]);
    }

    private function establecimiento(int $id, string $rbd, string $nombre, string $comuna): Establecimiento
    {
        $establecimiento = new Establecimiento;
        $establecimiento->id = $id;
        $establecimiento->rbd = $rbd;
        $establecimiento->nombre_establecimiento = $nombre;
        $establecimiento->comuna = $comuna;

        return $establecimiento;
    }
}
