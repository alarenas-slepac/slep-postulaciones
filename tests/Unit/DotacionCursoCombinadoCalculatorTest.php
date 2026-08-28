<?php

namespace Tests\Unit;

use App\Support\DotacionCursoCombinadoCalculator;
use Tests\TestCase;

class DotacionCursoCombinadoCalculatorTest extends TestCase
{
    public function test_redondea_hacia_arriba_una_sola_vez_el_contrato_especial_del_grupo(): void
    {
        $resultado = DotacionCursoCombinadoCalculator::adjustedContractRequired([
            [
                'dotacion_curso_combinado_id' => 10,
                'proporcion_key' => 'parvularia_jec_especial_65_35_ld',
                'horas_plan_requeridas' => 19,
                'horas_contrato_requeridas' => 30,
            ],
            [
                'dotacion_curso_combinado_id' => 10,
                'proporcion_key' => 'parvularia_jec_especial_65_35_ld',
                'horas_plan_requeridas' => 19,
                'horas_contrato_requeridas' => 30,
            ],
        ]);

        $this->assertSame(57.0, $resultado);
        $this->assertSame(60.0, $resultado + 3.0);
    }
}
