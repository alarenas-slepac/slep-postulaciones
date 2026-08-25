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
}
