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

    public function test_consolida_el_trabajo_colaborativo_pie_y_conserva_asignaciones_historicas(): void
    {
        $resultado = DotacionCursoCombinadoCalculator::consolidateCollaborativePie(
            [
                $this->pieItem(1, 'pie_colab|1'),
                $this->pieItem(2, 'pie_colab|2'),
                $this->pieItem(3, 'pie_colab|3'),
            ],
            [[
                'id' => 10,
                'nombre' => 'NT1 + NT2 A',
                'activo' => true,
                'miembros' => [['id' => 1], ['id' => 2]],
            ]],
            [
                $this->asignacionPie(101, 'pie_colab|1', 3),
                $this->asignacionPie(102, 'pie_colab|2', 3),
            ]
        );

        $this->assertCount(2, $resultado);
        $combinado = $resultado->firstWhere('dotacion_curso_combinado_id', 10);
        $independiente = $resultado->firstWhere('establecimiento_curso_id', 3);

        $this->assertNotNull($combinado);
        $this->assertNotNull($independiente);
        $this->assertSame('pie_colab_combinado|10', $combinado['key']);
        $this->assertSame(3.0, $combinado['horas_contrato_requeridas']);
        $this->assertSame(6.0, $combinado['horas_contrato_asignadas']);
        $this->assertSame(0.0, $combinado['horas_contrato_pendientes']);
        $this->assertSame('excedida', $combinado['estado']['key']);
        $this->assertCount(2, $combinado['asignaciones']);
        $this->assertSame(6.0, (float) $resultado->sum('horas_contrato_requeridas'));
    }

    private function pieItem(int $cursoId, string $key): array
    {
        return [
            'key' => $key,
            'tipo_asignacion' => 'pie_colaborativo',
            'subtipo_asignacion' => 'trabajo_colaborativo',
            'titulo' => 'Trabajo colaborativo PIE',
            'curso_label' => 'Curso '.$cursoId,
            'establecimiento_curso_id' => $cursoId,
            'horas_contrato_requeridas' => 3.0,
            'horas_contrato_asignadas' => 0.0,
            'horas_contrato_pendientes' => 3.0,
            'asignaciones' => collect(),
        ];
    }

    private function asignacionPie(int $id, string $key, float $horas): array
    {
        return [
            'id' => $id,
            'tipo_asignacion' => 'pie_colaborativo',
            'necesidad_key' => $key,
            'horas_contrato' => $horas,
        ];
    }
}
