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
        $this->assertSame(3.0, $combinado['total_trabajo_colaborativo_pie']);
        $this->assertSame(53.0, $combinado['total_contrato_mas_trabajo_colaborativo_pie']);
        $this->assertSame(2, $combinado['trabajo_colaborativo_pie_cursos']);
        $this->assertSame(32.0, $combinado['horas_plan_reduccion']);

        $this->assertSame(65, $resultado['totales']['matricula']);
        $this->assertSame(3, $resultado['totales']['cursos']);
        $this->assertSame(70.0, $resultado['totales']['horas']);
        $this->assertSame(109.0, $resultado['totales']['horas_contrato_equivalente']);
        $this->assertSame(3.0, $resultado['totales']['trabajo_colaborativo_pie']);
        $this->assertSame(112.0, $resultado['totales']['contrato_mas_trabajo_colaborativo_pie']);
    }

    public function test_separa_el_refuerzo_nt_de_la_fila_combinada_y_conserva_el_pie(): void
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

        $this->assertSame(32.0, $combinado['total_horas']);
        $this->assertSame(50.0, $combinado['total_horas_contrato_equivalente']);
        $this->assertSame(32.0, $combinado['horas_plan_por_curso']);
        $this->assertSame(3.0, $combinado['total_trabajo_colaborativo_pie']);
        $this->assertSame(6.0, $combinado['horas_plan_refuerzo_ld_otro_docente']);
        $this->assertSame(3.0, $combinado['horas_contrato_refuerzo_ld_otro_docente']);
        $this->assertSame(6.0, $resultado['refuerzo_plan_general']['horas']);
        $this->assertSame(3.0, $resultado['refuerzo_plan_general']['horas_contrato_equivalente']);
        $this->assertSame(0.0, $resultado['refuerzo_plan_general']['trabajo_colaborativo_pie']);
        $this->assertSame(76.0, $resultado['totales']['horas']);
        $this->assertSame(115.0, $resultado['totales']['contrato_mas_trabajo_colaborativo_pie']);
    }

    public function test_separa_refuerzo_de_nt_independientes_sin_alterar_total_matricula_cursos_ni_pie(): void
    {
        $cursos = $this->cursos();
        foreach (['NT1', 'NT2'] as $nivel) {
            $cursos['rows'][$nivel]['detalles'][0] = array_merge($cursos['rows'][$nivel]['detalles'][0], [
                'horas' => 44,
                'horas_contrato_equivalente_redondeado' => 64,
                'horas_plan_refuerzo_ld_otro_docente' => 6,
                'horas_contrato_refuerzo_ld_otro_docente' => 7,
                'proporcion_docente_label' => 'NT Con JEC especial + 65/35 LD + 65/35 LD otro docente',
                'origen_proporcion_label' => 'Regla especial Educación Parvularia + libre disposición asignada',
            ]);
        }

        $resultado = DotacionCursosPlanesResumenCalculator::build($cursos, []);

        foreach (['NT1', 'NT2'] as $nivel) {
            $this->assertSame(38.0, $resultado['rows'][$nivel]['horas_por_nivel']);
            $this->assertSame(57.0, $resultado['rows'][$nivel]['total_horas_contrato_equivalente']);
            $this->assertSame(60.0, $resultado['rows'][$nivel]['total_contrato_mas_trabajo_colaborativo_pie']);
            $this->assertSame('NT Con JEC especial + 65/35 LD', $resultado['rows'][$nivel]['proporcion_docente_label']);
            $this->assertSame('Regla especial Educación Parvularia', $resultado['rows'][$nivel]['origen_proporcion_label']);
        }
        $this->assertSame(120.0, $resultado['grupos']['parvularia']['totales']['contrato_mas_trabajo_colaborativo_pie']);
        $this->assertSame(12.0, $resultado['refuerzo_plan_general']['horas']);
        $this->assertSame(14.0, $resultado['refuerzo_plan_general']['horas_contrato_equivalente']);
        $this->assertSame(0, $resultado['refuerzo_plan_general']['cursos']);
        $this->assertSame(0, $resultado['refuerzo_plan_general']['matricula']);
        $this->assertSame(65, $resultado['totales']['matricula']);
        $this->assertSame(3, $resultado['totales']['cursos']);
        $this->assertSame(6.0, $resultado['totales']['trabajo_colaborativo_pie']);
        $this->assertSame(193.0, $resultado['totales']['contrato_mas_trabajo_colaborativo_pie']);
        $this->assertSame(44, $cursos['rows']['NT1']['detalles'][0]['horas']);
    }

    public function test_renderiza_resumen_y_pdf_con_refuerzo_separado_de_parvularia(): void
    {
        $cursos = $this->cursos();
        foreach (['NT1', 'NT2'] as $nivel) {
            $detalle = &$cursos['rows'][$nivel]['detalles'][0];
            $detalle['horas'] = 44;
            $detalle['horas_contrato_equivalente_redondeado'] = 64;
            $detalle['horas_plan_refuerzo_ld_otro_docente'] = 6;
            $detalle['horas_contrato_refuerzo_ld_otro_docente'] = 7;
            unset($detalle);
        }
        $cursos['resumen_cursos_planes'] = DotacionCursosPlanesResumenCalculator::build($cursos, []);
        $datos = [
            'cursos' => $cursos,
            'establecimiento' => (object) ['rbd' => 'TEST', 'nombre_establecimiento' => 'Escuela de prueba', 'comuna' => 'Prueba'],
            'anio' => 2026,
            'resumen' => [],
            'bloques' => [],
            'docentes' => collect(),
            'generatedBy' => null,
            'generatedAt' => now(),
        ];

        foreach (['admin.dotacion-establecimiento.partials._resumen', 'admin.dotacion-establecimiento.pdf'] as $vista) {
            $html = view($vista, $datos)->render();
            $dom = new \DOMDocument;
            $prev = libxml_use_internal_errors(true);
            try {
                $dom->loadHTML('<meta charset="UTF-8">'.$html);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($prev);
            }
            $xpath = new \DOMXPath($dom);
            foreach (['NT1', 'NT2'] as $nivel) {
                $fila = '//tr[normalize-space(td[1])="'.$nivel.'"]';
                $this->assertSame('38', $xpath->evaluate('normalize-space('.$fila.'/td[4])'), $vista);
                $this->assertSame('57', $xpath->evaluate('normalize-space('.$fila.'/td[7])'), $vista);
                $this->assertSame('60', $xpath->evaluate('normalize-space('.$fila.'/td[9])'), $vista);
            }
            $refuerzo = '//tr[contains(normalize-space(td[1]), "Plan General · Libre disposición NT1/NT2")]';
            $this->assertSame(1, $xpath->query($refuerzo)->length, $vista);
            $this->assertSame('14', $xpath->evaluate('normalize-space('.$refuerzo.'/td[7])'), $vista);
            $this->assertSame('—', $xpath->evaluate('normalize-space('.$refuerzo.'/td[8])'), $vista);
        }
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
        $this->assertStringContainsString('una sola necesidad de 3 h por grupo', $resumen);
        $this->assertStringContainsString("horas_plan_por_curso", $resumen);
        $this->assertStringContainsString('Total cursos combinados', $resumen);
        $this->assertStringContainsString('$totalesCursosPlanes', $resumen);
        $this->assertStringContainsString('Cursos combinados activos', $pdf);
        $this->assertStringContainsString('una sola necesidad de 3 horas por grupo combinado', $pdf);
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
