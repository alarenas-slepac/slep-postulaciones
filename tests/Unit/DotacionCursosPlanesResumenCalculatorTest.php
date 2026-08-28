<?php

namespace Tests\Unit;

use App\Support\DotacionCursosPlanesResumenCalculator;
use Tests\TestCase;

class DotacionCursosPlanesResumenCalculatorTest extends TestCase
{
    public function test_mantiene_el_resumen_por_nivel_cuando_no_hay_combinaciones(): void
    {
        $resultado = DotacionCursosPlanesResumenCalculator::build($this->cursos(), []);

        $this->assertFalse($resultado['tiene_cursos_combinados']);
        $this->assertCount(3, $resultado['rows']);
        $this->assertSame(65, $resultado['totales']['matricula']);
        $this->assertSame(3, $resultado['totales']['cursos']);
        $this->assertSame(102.0, $resultado['totales']['horas']);
        $this->assertSame(159.0, $resultado['totales']['horas_contrato_equivalente']);
        $this->assertSame(6.0, $resultado['totales']['trabajo_colaborativo_pie']);
        $this->assertSame(165.0, $resultado['totales']['contrato_mas_trabajo_colaborativo_pie']);
    }

    public function test_reemplaza_las_filas_individuales_por_el_grupo_combinado(): void
    {
        $resultado = DotacionCursosPlanesResumenCalculator::build(
            $this->cursos(),
            [$this->grupoCombinado()]
        );

        $this->assertTrue($resultado['tiene_cursos_combinados']);
        $this->assertArrayNotHasKey('parvularia', $resultado['grupos']);
        $this->assertSame(['1B'], array_keys($resultado['rows']));
        $this->assertCount(1, $resultado['combinados']);

        $combinado = $resultado['combinados']->first();
        $this->assertSame('NT1 + NT2 A', $combinado['label']);
        $this->assertSame('NT1 A + NT2 A', $combinado['miembros_label']);
        $this->assertSame(35, $combinado['matricula']);
        $this->assertSame(2, $combinado['cursos']);
        $this->assertSame(32.0, $combinado['horas_plan_por_curso']);
        $this->assertFalse($combinado['horas_plan_por_curso_variable']);
        $this->assertCount(2, $combinado['horas_plan_por_curso_detalle']);
        $this->assertSame(32.0, $combinado['total_horas']);
        $this->assertSame(50.0, $combinado['total_horas_contrato_equivalente']);
        $this->assertSame(6.0, $combinado['total_trabajo_colaborativo_pie']);
        $this->assertSame(56.0, $combinado['total_contrato_mas_trabajo_colaborativo_pie']);
        $this->assertSame(32.0, $combinado['horas_plan_reduccion']);

        $this->assertSame(65, $resultado['totales']['matricula']);
        $this->assertSame(3, $resultado['totales']['cursos']);
        $this->assertSame(70.0, $resultado['totales']['horas']);
        $this->assertSame(109.0, $resultado['totales']['horas_contrato_equivalente']);
        $this->assertSame(6.0, $resultado['totales']['trabajo_colaborativo_pie']);
        $this->assertSame(115.0, $resultado['totales']['contrato_mas_trabajo_colaborativo_pie']);
    }

    public function test_conserva_el_refuerzo_nt_y_el_pie_en_la_fila_combinada(): void
    {
        $cursos = $this->cursos();
        $cursos['rows']['NT1']['detalles'][0]['horas'] = 38;
        $cursos['rows']['NT1']['detalles'][0]['horas_contrato_equivalente_redondeado'] = 53;
        $cursos['rows']['NT1']['detalles'][0]['horas_plan_refuerzo_ld_otro_docente'] = 6;
        $cursos['rows']['NT1']['detalles'][0]['horas_contrato_refuerzo_ld_otro_docente'] = 3;

        $resultado = DotacionCursosPlanesResumenCalculator::build(
            $cursos,
            [$this->grupoCombinado()]
        );
        $combinado = $resultado['combinados']->first();

        $this->assertSame(38.0, $combinado['total_horas']);
        $this->assertSame(53.0, $combinado['total_horas_contrato_equivalente']);
        $this->assertSame(6.0, $combinado['total_trabajo_colaborativo_pie']);
        $this->assertSame(6.0, $combinado['horas_plan_refuerzo_ld_otro_docente']);
        $this->assertSame(3.0, $combinado['horas_contrato_refuerzo_ld_otro_docente']);
    }

    public function test_vistas_muestran_filas_y_totales_de_cursos_combinados(): void
    {
        $resumen = file_get_contents(resource_path('views/admin/dotacion-establecimiento/partials/_resumen.blade.php'));
        $pdf = file_get_contents(resource_path('views/admin/dotacion-establecimiento/pdf.blade.php'));
        $calculator = file_get_contents(app_path('Support/DotacionEstablecimientoCalculator.php'));

        $this->assertIsString($resumen);
        $this->assertIsString($pdf);
        $this->assertIsString($calculator);
        $this->assertStringContainsString("'resumen_cursos_planes'", $calculator);
        $this->assertStringContainsString('Cursos combinados activos', $resumen);
        $this->assertStringContainsString('Grupo combinado', $resumen);
        $this->assertStringContainsString("horas_plan_por_curso", $resumen);
        $this->assertStringContainsString('Total cursos combinados', $resumen);
        $this->assertStringContainsString('$totalesCursosPlanes', $resumen);
        $this->assertStringContainsString('Cursos combinados activos', $pdf);
        $this->assertStringContainsString("horas_plan_por_curso", $pdf);
        $this->assertStringContainsString('Total cursos combinados', $pdf);
        $this->assertStringContainsString('$totalesCursosPlanes', $pdf);
    }

    private function cursos(): array
    {
        return [
            'grupos' => [
                'parvularia' => [
                    'label' => 'Educación Parvularia',
                    'niveles' => ['NT1', 'NT2'],
                ],
                'basica' => [
                    'label' => 'Educación Básica',
                    'niveles' => ['1B'],
                ],
            ],
            'rows' => [
                'NT1' => $this->row('NT1', 1, 20, 32, 50, 3),
                'NT2' => $this->row('NT2', 2, 15, 32, 50, 3),
                '1B' => $this->row('1° Básico', 3, 30, 38, 59, 0),
            ],
        ];
    }

    private function row(
        string $label,
        int $cursoId,
        int $matricula,
        float $horas,
        float $contrato,
        float $pie
    ): array {
        return [
            'label' => $label,
            'detalles' => [[
                'establecimiento_curso_id' => $cursoId,
                'matricula' => $matricula,
                'horas' => $horas,
                'horas_contrato_equivalente_redondeado' => $contrato,
                'trabajo_colaborativo_pie' => $pie,
                'proporcion_docente_label' => '65/35',
                'origen_proporcion_label' => 'Regla general',
                'horas_plan_refuerzo_ld_otro_docente' => 0,
                'horas_contrato_refuerzo_ld_otro_docente' => 0,
            ]],
        ];
    }

    private function grupoCombinado(): array
    {
        return [
            'id' => 10,
            'nombre' => 'NT1 + NT2 A',
            'activo' => true,
            'proporcion_label' => 'NT1/NT2 con JEC',
            'miembros' => [
                ['id' => 1, 'label' => 'NT1 A', 'matricula' => 20],
                ['id' => 2, 'label' => 'NT2 A', 'matricula' => 15],
            ],
            'totales' => [
                'horas_requeridas' => 32,
                'horas_contrato' => 50,
                'reduccion' => 32,
            ],
        ];
    }
}
