<?php

namespace Tests\Unit;

use Tests\TestCase;

class DotacionEstablecimientoKpiViewTest extends TestCase
{
    public function test_renderiza_las_cuatro_filas_en_el_orden_solicitado_sin_cambiar_valores(): void
    {
        $html = $this->renderIndicadores(true);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $expected = [
            'generales' => ['Matrícula', 'Cursos', 'Docentes'],
            'necesidades' => ['Contrato Educación Parvularia + PIE', 'Contrato Plan General + PIE', 'Funciones directivas / técnico pedagógicas y planes normativos', 'Otras funciones no normativas'],
            'contratos' => ['Horas contrato PIE necesarias', 'Horas contrato docentes', 'Horas contrato aula', 'Horas contrato parvularia', 'Horas contrato docente PIE'],
            'sobredotacion' => ['Sobredotación plan de estudio + funciones normativas', 'Sobredotación Parvularia', 'Sobredotación PIE'],
        ];
        $rows = $xpath->query('//*[@data-kpi-row]');
        $this->assertSame(array_keys($expected), array_map(fn ($node) => $node->getAttribute('data-kpi-row'), iterator_to_array($rows)));
        foreach ($expected as $key => $labels) {
            $nodes = $xpath->query('//*[@data-kpi-row="'.$key.'"]//div[contains(@class,"fw-semibold") and contains(@class,"text-muted")]');
            $this->assertSame($labels, array_map(fn ($node) => trim($node->textContent), iterator_to_array($nodes)));
        }
        $this->assertStringContainsString('row-cols-xl-4', $html);
        $this->assertStringContainsString('row-cols-xl-5', $html);
        $values = $xpath->query('//*[@data-kpi-row="sobredotacion"]//div[contains(@class,"fs-2")]');
        $this->assertSame(['562', '88', '340'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($values)));
        foreach ($values as $value) {
            $this->assertStringContainsString('text-danger', $value->getAttribute('class'));
        }
    }

    public function test_las_filas_sin_parvularia_no_dejan_tarjetas_vacias(): void
    {
        $html = $this->renderIndicadores(false);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        foreach (['generales' => 3, 'necesidades' => 3, 'contratos' => 4, 'sobredotacion' => 2] as $key => $count) {
            $this->assertSame($count, $xpath->query('//*[@data-kpi-row="'.$key.'"]/*')->length);
        }
        $this->assertStringNotContainsString('>Horas contrato parvularia</div>', $html);
        $this->assertStringNotContainsString('>Sobredotación Parvularia</div>', $html);
    }

    private function renderIndicadores(bool $parvularia): string
    {
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php'));
        preg_match('/@php(.*?)@endphp/s', $source, $setup);
        $start = strpos($source, '{{-- Inicio de filas de indicadores --}}');
        $end = strpos($source, '{{-- Fin de filas de indicadores --}}');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return \Illuminate\Support\Facades\Blade::render($setup[0].substr($source, $start, $end - $start).'</div>', [
            'cursos' => ['grupos' => ['parvularia' => ['totales' => ['cursos' => $parvularia ? 2 : 0]]]],
            'resumen' => [
                'matricula_total' => 465, 'cursos_total' => 19, 'docentes_total' => 62,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 120,
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 809,
                'horas_dotacion_funciones_normativas' => 231,
                'horas_dotacion_funciones_declaradas' => 348,
                'horas_dotacion_desglose' => ['total_declaradas_asignadas' => 346],
                'horas_contrato_pie_necesarias' => 336,
                'horas_contrato_pie_necesarias_desglose' => ['total_asignadas' => 676],
                'horas_contrato_docentes' => 2486,
                'horas_contrato_docentes_aula' => 1810,
                'horas_contrato_docentes_aula_general' => 1602,
                'horas_contrato_docentes_parvularia' => $parvularia ? 208 : 0,
                'horas_contrato_docente_pie' => 676,
            ],
        ]);
    }

    public function test_reagrupa_los_indicadores_superiores_sin_mostrar_tarjetas_individuales(): void
    {
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("['label' => 'Contrato plan + PIE'", $source);
        $this->assertStringContainsString("['label' => 'Contrato Educación Parvularia + PIE'", $source);
        $this->assertStringContainsString("['label' => 'Contrato Plan General + PIE'", $source);
        $this->assertStringContainsString('...($tieneEducacionParvularia ? [', $source);
        $this->assertStringContainsString("['label' => 'Funciones directivas / técnico pedagógicas y planes normativos'", $source);
        $this->assertStringContainsString("['label' => 'Otras funciones no normativas'", $source);
        $this->assertStringNotContainsString("['label' => 'Horas plan'", $source);
        $this->assertStringNotContainsString("['label' => 'Contrato plan',", $source);
        $this->assertStringNotContainsString("['label' => 'Trabajo colab. PIE'", $source);
    }

    public function test_separa_contrato_parvularia_y_plan_general_solo_si_existen_cursos_nt(): void
    {
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php'));
        $pdfSource = file_get_contents(resource_path('views/admin/dotacion-establecimiento/pdf.blade.php'));

        $this->assertIsString($source);
        $this->assertIsString($pdfSource);
        $this->assertStringContainsString("data_get(\$cursos ?? [], 'grupos.parvularia', [])", $source);
        $this->assertStringContainsString('contrato_educacion_parvularia_mas_trabajo_colaborativo_pie', $source);
        $this->assertStringContainsString('contrato_plan_general_mas_trabajo_colaborativo_pie', $source);
        $this->assertStringContainsString('los grupos combinados reemplazan la suma individual', $source);
        $this->assertStringContainsString('aplican 65/35 o 60/40', $source);
        $this->assertStringContainsString('@if ($tieneEducacionParvularia)', $pdfSource);
        $this->assertStringContainsString('Contrato plan + PIE por tipo de enseñanza', $pdfSource);
        $this->assertStringContainsString('Educación Parvularia + PIE', $pdfSource);
        $this->assertStringContainsString('Plan General + PIE', $pdfSource);
        $this->assertStringContainsString('contrato_educacion_parvularia_mas_trabajo_colaborativo_pie', $pdfSource);
        $this->assertStringContainsString('contrato_plan_general_mas_trabajo_colaborativo_pie', $pdfSource);
        $this->assertStringContainsString('Necesidad para cubrir NT1 y NT2', $pdfSource);
    }

    public function test_hace_colapsables_los_desgloses_de_funciones_y_pie(): void
    {
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('Desglose de horas de contrato de funciones directivas, técnico pedagógicas, planes y Otras funciones', $source);
        $this->assertSame(2, substr_count($source, 'data-bs-toggle="collapse"'));
        $this->assertSame(2, substr_count($source, 'dotacion-collapse-toggle collapsed'));
        $this->assertStringContainsString('data-bs-target="#dotacion-funciones-collapse"', $source);
        $this->assertStringContainsString('aria-controls="dotacion-funciones-collapse"', $source);
        $this->assertMatchesRegularExpression('/data-bs-target="#dotacion-funciones-collapse"\s+aria-expanded="false"\s+aria-controls="dotacion-funciones-collapse"/', $source);
        $this->assertStringContainsString('id="dotacion-funciones-collapse" class="collapse"', $source);
        $this->assertStringContainsString('data-bs-target="#dotacion-pie-necesarias-collapse"', $source);
        $this->assertStringContainsString('aria-controls="dotacion-pie-necesarias-collapse"', $source);
        $this->assertMatchesRegularExpression('/data-bs-target="#dotacion-pie-necesarias-collapse"\s+aria-expanded="false"\s+aria-controls="dotacion-pie-necesarias-collapse"/', $source);
        $this->assertStringContainsString('id="dotacion-pie-necesarias-collapse" class="collapse"', $source);
        $this->assertStringNotContainsString('id="dotacion-funciones-collapse" class="collapse show"', $source);
        $this->assertStringNotContainsString('id="dotacion-pie-necesarias-collapse" class="collapse show"', $source);
    }

    public function test_muestra_sobredotacion_estructural_sin_sumar_horas_individuales_sin_asignar(): void
    {
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php'));
        $pdfSource = file_get_contents(resource_path('views/admin/dotacion-establecimiento/pdf.blade.php'));

        $this->assertIsString($source);
        $this->assertIsString($pdfSource);
        $this->assertStringContainsString('($contratoPlanGeneralMasPie + $horasBloqueNormativas)', $source);
        $this->assertStringNotContainsString('$contratoEducacionParvulariaMasPie + $contratoPlanGeneralMasPie', $source);
        $this->assertStringContainsString('- $horasContratoAulaGeneral,', $source);
        $this->assertStringContainsString('$resultadoGeneral = $resultadoBrecha($brechaDotacionGeneral);', $source);
        $this->assertStringContainsString('>Sobredotación plan de estudio + funciones normativas</div>', $source);
        $this->assertStringContainsString('>Sobredotación PIE</div>', $source);
        $this->assertStringNotContainsString("data_get(\$sobredotacion ?? [], 'aula.resumen.horas_sobredotacion_total'", $source);
        $this->assertStringNotContainsString('Resultado contractual final para comparación.', $source);
        $this->assertStringNotContainsString('$resultadoFinal', $source);
        $this->assertStringContainsString('($contratoPlanGeneralMasPie + $horasBloqueNormativas)', $pdfSource);
        $this->assertStringNotContainsString('$contratoEducacionParvulariaMasPie + $contratoPlanGeneralMasPie', $pdfSource);
        $this->assertStringContainsString('- $horasContratoAulaGeneral,', $pdfSource);
        $this->assertStringContainsString('<strong>Sobredotación estructural</strong>', $pdfSource);
        $this->assertStringNotContainsString('aula.resumen.horas_sobredotacion_total', $pdfSource);
        $this->assertStringNotContainsString('$resultadoEstructuralGeneral', $pdfSource);
    }
}
