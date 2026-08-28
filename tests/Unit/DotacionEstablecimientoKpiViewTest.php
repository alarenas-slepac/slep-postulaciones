<?php

namespace Tests\Unit;

use Tests\TestCase;

class DotacionEstablecimientoKpiViewTest extends TestCase
{
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
        $this->assertStringContainsString('ajustadas por cursos combinados', $source);
        $this->assertStringContainsString('@if ($tieneEducacionParvularia)', $pdfSource);
        $this->assertStringContainsString('Contrato plan + PIE por tipo de enseñanza', $pdfSource);
        $this->assertStringContainsString('Educación Parvularia + PIE', $pdfSource);
        $this->assertStringContainsString('Plan General + PIE', $pdfSource);
        $this->assertStringContainsString('contrato_educacion_parvularia_mas_trabajo_colaborativo_pie', $pdfSource);
        $this->assertStringContainsString('contrato_plan_general_mas_trabajo_colaborativo_pie', $pdfSource);
        $this->assertStringContainsString('Contrato equivalente ajustado por cursos combinados', $pdfSource);
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
        $this->assertStringContainsString('$contratoEducacionParvulariaMasPie + $contratoPlanGeneralMasPie + $horasBloqueNormativas', $source);
        $this->assertStringContainsString('- $horasContratoAula', $source);
        $this->assertStringContainsString('$resultadoGeneral = $resultadoBrecha($brechaDotacionGeneral);', $source);
        $this->assertStringContainsString('>Sobredotación estructural</div>', $source);
        $this->assertStringNotContainsString("data_get(\$sobredotacion ?? [], 'aula.resumen.horas_sobredotacion_total'", $source);
        $this->assertStringNotContainsString('Resultado contractual final para comparación.', $source);
        $this->assertStringNotContainsString('$resultadoFinal', $source);
        $this->assertStringContainsString('$contratoEducacionParvulariaMasPie + $contratoPlanGeneralMasPie + $horasBloqueNormativas', $pdfSource);
        $this->assertStringContainsString('<strong>Sobredotación estructural</strong>', $pdfSource);
        $this->assertStringNotContainsString('aula.resumen.horas_sobredotacion_total', $pdfSource);
        $this->assertStringNotContainsString('$resultadoEstructuralGeneral', $pdfSource);
    }
}
