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
        $this->assertStringContainsString("['label' => 'Funciones directivas / técnico pedagógicas y planes normativos'", $source);
        $this->assertStringContainsString("['label' => 'Otras funciones no normativas'", $source);
        $this->assertStringNotContainsString("['label' => 'Horas plan'", $source);
        $this->assertStringNotContainsString("['label' => 'Contrato plan',", $source);
        $this->assertStringNotContainsString("['label' => 'Trabajo colab. PIE'", $source);
    }

    public function test_hace_colapsables_los_desgloses_de_funciones_y_pie(): void
    {
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('Desglose de horas de contrato de funciones directivas, técnico pedagógicas, planes y Otras funciones', $source);
        $this->assertSame(2, substr_count($source, 'data-bs-toggle="collapse"'));
        $this->assertStringContainsString('data-bs-target="#dotacion-funciones-collapse"', $source);
        $this->assertStringContainsString('aria-controls="dotacion-funciones-collapse"', $source);
        $this->assertStringContainsString('id="dotacion-funciones-collapse" class="collapse show"', $source);
        $this->assertStringContainsString('data-bs-target="#dotacion-pie-necesarias-collapse"', $source);
        $this->assertStringContainsString('aria-controls="dotacion-pie-necesarias-collapse"', $source);
        $this->assertStringContainsString('id="dotacion-pie-necesarias-collapse" class="collapse show"', $source);
    }
}
