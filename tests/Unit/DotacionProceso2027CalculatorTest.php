<?php

namespace Tests\Unit;

use App\Models\Establecimiento;
use App\Support\DotacionProceso2027Calculator;
use Tests\TestCase;

class DotacionProceso2027CalculatorTest extends TestCase
{
    public function test_aplica_solo_al_proceso_2027(): void
    {
        $this->assertTrue(DotacionProceso2027Calculator::aplica(2027));
        $this->assertFalse(DotacionProceso2027Calculator::aplica(2026));
        $this->assertFalse(DotacionProceso2027Calculator::aplica(2028));
    }

    public function test_ordena_fuero_tramos_titulares_y_contrata_y_calcula_saldos(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('Contrata', 0, 20, 0, null, null, '2020-01-01'),
            $this->docente('Experto I', 30, 0, 10, null, 'Experto I', '2010-01-01'),
            $this->docente('Avanzado', 30, 0, 4, null, 'Avanzado', '2000-01-01'),
            $this->docente('Gremial', 0, 44, 8, 'horas_gremiales', null, '2024-01-01'),
            $this->docente('Lactancia', 20, 0, 0, 'horas_lactancia', null, '2025-01-01'),
            $this->docente('Experto II', 40, 0, 5, null, 'Experto II', '2015-01-01'),
        ]));

        $this->assertSame(['Gremial', 'Lactancia', 'Experto II', 'Experto I', 'Avanzado', 'Contrata'], $docentes->pluck('nombre')->all());
        $experto = $docentes->firstWhere('nombre', 'Experto II');
        $this->assertSame(35.0, $experto['horas_titulares_disponibles']);
        $this->assertSame(35.0, $experto['horas_disponibles']);
        $this->assertSame(6, $docentes->last()['prioridad_2027']);
    }

    public function test_trabajo_colaborativo_nt_se_contabiliza_en_bloque_parvularia(): void
    {
        $resumen = DotacionProceso2027Calculator::resumen(
            new Establecimiento(['id' => 1]),
            2027,
            [
                'cursos' => [
                    'rows' => [
                        'NT1' => ['detalles' => [['establecimiento_curso_id' => 99]]],
                    ],
                    'totales' => ['cursos' => 1, 'sin_horas_plan' => 0],
                ],
                'asignacion' => [
                    'necesidades' => [
                        'pie_colaborativo' => [[
                            'key' => 'pie_colab:99',
                            'establecimiento_curso_id' => 99,
                            'horas_contrato' => 3,
                            'horas_contrato_requeridas' => 3,
                        ]],
                    ],
                    'asignaciones' => [],
                ],
                'docentes' => [],
                'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
            ]
        );

        $this->assertSame('bloque_2', $resumen['need_blocks']['pie_colab:99']);
        $this->assertSame(3.0, $resumen['bloques']['bloque_2']['requeridas']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_1']['requeridas']);
    }

    public function test_muestra_funcion_normativa_potencial_y_no_la_exige_hasta_definirla(): void
    {
        $resumen = DotacionProceso2027Calculator::resumen(
            new Establecimiento(['id' => 1]),
            2027,
            [
                'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
                'asignacion' => [
                    'necesidades' => [
                        'funciones' => [[
                            'key' => 'funcion:utp',
                            'titulo' => 'Jefatura UTP',
                            'subtipo_asignacion' => 'tecnico_pedagogica',
                            'horas_contrato_requeridas' => 8,
                            'horas_contrato_asignadas' => 0,
                            'necesidad_condicionada_por_asignacion_docente' => true,
                        ]],
                    ],
                    'asignaciones' => [],
                ],
                'docentes' => [],
                'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
            ]
        );

        $this->assertSame(8.0, $resumen['bloques']['bloque_1']['horas_normativas_potenciales']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_1']['requeridas']);
        $this->assertFalse($resumen['pasos']['normativas']['completo']);
        $this->assertFalse($resumen['funciones_normativas']->first()['se_utilizara']);
    }

    private function docente(
        string $nombre,
        float $planta,
        float $contrata,
        float $asignadas,
        ?string $motivo,
        ?string $tramo,
        string $antiguedad
    ): array {
        return [
            'rut_normalizado' => strtolower($nombre),
            'nombre' => $nombre,
            'horas_contrato' => $planta + $contrata,
            'horas_planta' => $planta,
            'horas_contrata' => $contrata,
            'horas_asignadas_total' => $asignadas,
            'tramo' => $tramo,
            'fecha_antiguedad' => $antiguedad,
            'exclusion_docente' => $motivo ? ['motivo' => $motivo] : null,
        ];
    }
}
