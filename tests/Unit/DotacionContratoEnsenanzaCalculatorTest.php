<?php

namespace Tests\Unit;

use App\Support\DotacionContratoEnsenanzaCalculator;
use Tests\TestCase;

class DotacionContratoEnsenanzaCalculatorTest extends TestCase
{
    public function test_separa_contratos_sin_cursos_combinados(): void
    {
        $resultado = DotacionContratoEnsenanzaCalculator::split(
            $this->cursos(),
            [],
            180
        );

        $this->assertSame(100.0, $resultado['contrato_plan_parvularia']);
        $this->assertSame(80.0, $resultado['contrato_plan_general']);
        $this->assertSame(106.0, $resultado['contrato_parvularia_mas_pie']);
        $this->assertSame(83.0, $resultado['contrato_general_mas_pie']);
    }

    public function test_reemplaza_la_suma_individual_por_la_necesidad_del_grupo_combinado_parvularia(): void
    {
        $resultado = DotacionContratoEnsenanzaCalculator::split(
            $this->cursos(),
            [$this->grupoCombinado([1, 2], 50, 'nt_jec')],
            130
        );

        $this->assertSame(50.0, $resultado['contrato_plan_parvularia']);
        $this->assertSame(80.0, $resultado['contrato_plan_general']);
        $this->assertSame(3.0, $resultado['trabajo_colaborativo_pie_parvularia']);
        $this->assertSame(53.0, $resultado['contrato_parvularia_mas_pie']);
        $this->assertSame(83.0, $resultado['contrato_general_mas_pie']);
        $this->assertSame(
            136.0,
            $resultado['contrato_parvularia_mas_pie'] + $resultado['contrato_general_mas_pie']
        );
    }

    public function test_asigna_a_plan_general_la_necesidad_consolidada_de_su_grupo_combinado(): void
    {
        $resultado = DotacionContratoEnsenanzaCalculator::split(
            $this->cursos(),
            [$this->grupoCombinado([10, 11], 50)],
            150
        );

        $this->assertSame(100.0, $resultado['contrato_plan_parvularia']);
        $this->assertSame(50.0, $resultado['contrato_plan_general']);
        $this->assertSame(106.0, $resultado['contrato_parvularia_mas_pie']);
        $this->assertSame(53.0, $resultado['contrato_general_mas_pie']);
    }

    public function test_no_duplica_el_refuerzo_nt_ya_incluido_en_el_contrato_equivalente_del_curso(): void
    {
        $cursos = $this->cursos(53, 53, 3, 3);

        $resultado = DotacionContratoEnsenanzaCalculator::split(
            $cursos,
            [$this->grupoCombinado([1, 2], 50, 'nt_jec')],
            136
        );

        $this->assertSame(56.0, $resultado['contrato_plan_parvularia']);
        $this->assertSame(80.0, $resultado['contrato_plan_general']);
        $this->assertSame(59.0, $resultado['contrato_parvularia_mas_pie']);
        $this->assertSame(83.0, $resultado['contrato_general_mas_pie']);
    }

    private function cursos(
        float $contratoNt1 = 50,
        float $contratoNt2 = 50,
        float $refuerzoNt1 = 0,
        float $refuerzoNt2 = 0
    ): array {
        return [
            'grupos' => [
                'parvularia' => [
                    'niveles' => ['NT1', 'NT2'],
                    'totales' => [
                        'horas_contrato_equivalente' => $contratoNt1 + $contratoNt2,
                        'trabajo_colaborativo_pie' => 6,
                    ],
                ],
                'basica' => [
                    'niveles' => ['1B'],
                    'totales' => [
                        'horas_contrato_equivalente' => 80,
                        'trabajo_colaborativo_pie' => 3,
                    ],
                ],
            ],
            'rows' => [
                'NT1' => [
                    'detalles' => [[
                        'establecimiento_curso_id' => 1,
                        'horas_contrato_equivalente_redondeado' => $contratoNt1,
                        'horas_contrato_refuerzo_ld_otro_docente' => $refuerzoNt1,
                        'trabajo_colaborativo_pie' => 3,
                    ]],
                ],
                'NT2' => [
                    'detalles' => [[
                        'establecimiento_curso_id' => 2,
                        'horas_contrato_equivalente_redondeado' => $contratoNt2,
                        'horas_contrato_refuerzo_ld_otro_docente' => $refuerzoNt2,
                        'trabajo_colaborativo_pie' => 3,
                    ]],
                ],
                '1B' => [
                    'detalles' => [[
                        'establecimiento_curso_id' => 10,
                        'horas_contrato_equivalente_redondeado' => 80,
                        'horas_contrato_refuerzo_ld_otro_docente' => 0,
                        'trabajo_colaborativo_pie' => 3,
                    ]],
                ],
            ],
            'totales' => [
                'trabajo_colaborativo_pie' => 9,
            ],
        ];
    }

    private function grupoCombinado(array $cursoIds, float $horasContrato, string $proporcion = 'auto'): array
    {
        return [
            'activo' => true,
            'proporcion' => $proporcion,
            'miembros' => collect($cursoIds)
                ->map(fn (int $id) => ['id' => $id])
                ->all(),
            'totales' => [
                'horas_contrato' => $horasContrato,
            ],
        ];
    }
}
