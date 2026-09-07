<?php

namespace Tests\Unit;

use App\Exports\DotacionResumenSobredotacionExport;
use App\Http\Controllers\Admin\DotacionEstablecimientoController;
use App\Models\Establecimiento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DotacionResumenSobredotacionExportTest extends TestCase
{
    public function test_mapea_las_17_columnas_y_separa_las_tres_brechas(): void
    {
        $export = new DotacionResumenSobredotacionExport;
        $row = $export->row(new Establecimiento(['rbd' => '05006', 'nombre_establecimiento' => 'Escuela de prueba']), $this->summary());
        $this->assertSame([
            '5006', 'Escuela de prueba', 465, 19, 62, 120.0, 809.0, 231.0, 348.0,
            336.0, 2486.0, 1602.0, 208.0, 676.0, -562.0, -88.0, -340.0,
        ], $row);
    }

    public function test_una_hoja_totales_solo_negativos_y_colores_persisten_en_xlsx(): void
    {
        $export = new DotacionResumenSobredotacionExport;
        $row = $export->row(new Establecimiento(['rbd' => '05006', 'nombre_establecimiento' => '=Escuela de prueba']), $this->summary());
        $positive = array_replace($row, [0 => 'TEST2', 14 => 1000.0, 15 => 0.0, 16 => 30.0]);
        $negative = array_replace($row, [0 => 'TEST3', 14 => -10.25, 15 => -2.0, 16 => 0.0]);
        $book = $export->workbook(collect([$row, $positive, $negative]), 2026, ['comuna' => 'Prueba', 'q' => '=texto']);
        $file = tempnam(sys_get_temp_dir(), 'resumen_dotacion_');
        try {
            (new Xlsx($book))->save($file);
            $loaded = IOFactory::load($file);
            $this->assertSame(1, $loaded->getSheetCount());
            $sheet = $loaded->getActiveSheet();
            $this->assertSame(DotacionResumenSobredotacionExport::HEADERS, $sheet->rangeToArray('A6:Q6')[0]);
            foreach (['A4' => 572.25, 'G4' => 90.0, 'L4' => 340.0] as $cell => $value) {
                $this->assertEquals($value, $sheet->getCell($cell)->getValue());
                $this->assertSame('C00000', $sheet->getStyle($cell)->getFont()->getColor()->getRGB());
            }
            foreach (['O7', 'P7', 'Q7', 'O9', 'P9'] as $cell) {
                $this->assertSame('F8D7DA', $sheet->getStyle($cell)->getFill()->getStartColor()->getRGB());
            }
            foreach (['O8', 'P8', 'Q8', 'Q9'] as $cell) {
                $this->assertSame('D1E7DD', $sheet->getStyle($cell)->getFill()->getStartColor()->getRGB());
            }
            $this->assertEquals(-562, $sheet->getCell('O7')->getValue());
            $this->assertSame('5006', $sheet->getCell('A7')->getValue());
            $this->assertSame('s', $sheet->getCell('B7')->getDataType());
            $this->assertSame('A6:Q9', $sheet->getAutoFilter()->getRange());
            $this->assertSame('C7', $sheet->getFreezePane());
            $loaded->disconnectWorksheets();
        } finally {
            $book->disconnectWorksheets();
            unlink($file);
        }
    }

    public function test_sin_resultados_y_sin_parvularia_no_inventa_excedentes(): void
    {
        $export = new DotacionResumenSobredotacionExport;
        $row = $export->row(new Establecimiento(['rbd' => 'TEST']), [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 100,
            'horas_contrato_docentes_aula' => 100,
        ]);
        $this->assertSame([0.0, 0.0, 0.0], array_slice($row, 14));
        $book = $export->workbook(collect(), 2026);
        $this->assertSame(1, $book->getSheetCount());
        $this->assertSame(0.0, $book->getActiveSheet()->getCell('A4')->getValue());
        $this->assertSame('A6:Q6', $book->getActiveSheet()->getAutoFilter()->getRange());
        $book->disconnectWorksheets();
    }

    public function test_roles_autorizados_exportan_todos_los_resultados_filtrados(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function ($table) {
            $table->id();
            $table->string('rbd');
            $table->string('nombre_establecimiento');
            $table->string('comuna');
            $table->boolean('sala_cuna')->nullable();
        });
        for ($id = 1; $id <= 23; $id++) {
            DB::table('establecimientos')->insert([
                'rbd' => 'TEST'.$id, 'nombre_establecimiento' => 'Escuela prueba '.$id,
                'comuna' => $id === 23 ? 'Otra' : 'Prueba', 'sala_cuna' => $id === 22,
            ]);
        }
        $mock = \Mockery::mock(DotacionResumenSobredotacionExport::class);
        $mock->shouldReceive('download')->times(3)
            ->withArgs(fn ($schools, $year, $filters) => $schools->count() === 21 && $year === 2026 && $filters === ['q' => 'Escuela', 'comuna' => 'Prueba'])
            ->andReturn(response()->streamDownload(fn () => null, 'test.xlsx'));
        $this->app->instance(DotacionResumenSobredotacionExport::class, $mock);
        foreach (DotacionResumenSobredotacionExport::ROLES as $role) {
            $request = $this->request($role);
            $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, app(DotacionEstablecimientoController::class)->index($request));
        }
    }

    public function test_rechaza_otros_roles_aunque_intenten_la_url_directa(): void
    {
        foreach (['supervisor_plani', 'funcionario_directivo_estab', 'funcionario_slep', 'postulante'] as $role) {
            $this->assertFalse(DotacionResumenSobredotacionExport::canExport($role));
            try {
                app(DotacionEstablecimientoController::class)->index($this->request($role));
                $this->fail('Debió rechazar el rol '.$role);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    private function request(string $role): Request
    {
        $request = Request::create('/admin/dotacion-establecimiento', 'GET', [
            'export_resumen_sobredotacion' => 1, 'anio' => 2026, 'q' => 'Escuela', 'comuna' => 'Prueba', 'page' => 2,
        ]);
        $request->setUserResolver(fn () => new class($role) {
            public int $establecimiento_id = 1;
            public function __construct(private string $role) {}
            public function activeRoleName(): string { return $this->role; }
        });

        return $request;
    }

    private function summary(): array
    {
        return [
            'matricula_total' => 465, 'cursos_total' => 19, 'docentes_total' => 62,
            'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 120,
            'contrato_plan_general_mas_trabajo_colaborativo_pie' => 809,
            'horas_dotacion_funciones_normativas' => 231, 'horas_dotacion_funciones_declaradas' => 348,
            'horas_contrato_pie_necesarias' => 336, 'horas_contrato_docentes' => 2486,
            'horas_contrato_docentes_aula_general' => 1602, 'horas_contrato_docentes_parvularia' => 208,
            'horas_contrato_docente_pie' => 676,
        ];
    }
}
