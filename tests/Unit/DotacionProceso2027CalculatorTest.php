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

    public function test_agrupa_avanzado_y_expertos_en_una_prioridad_reconociendo_numeros_y_romanos(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('Contrata experto', 0, 20, 0, null, 'Experto 2', '1980-01-01'),
            $this->docente('Resto titular', 20, 0, 0, null, 'Inicial', '1990-01-01'),
            $this->docente('Experto 1', 30, 0, 10, null, 'Experto 1', '2010-01-01'),
            $this->docente('Experto I', 30, 0, 10, null, 'Experto I', '2011-01-01'),
            $this->docente('Avanzado', 30, 0, 4, null, 'Avanzado', '2000-01-01'),
            $this->docente('Gremial', 0, 44, 8, 'horas_gremiales', null, '2024-01-01'),
            $this->docente('Lactancia', 20, 0, 0, 'horas_lactancia', null, '2025-01-01'),
            $this->docente('Experto 2', 40, 0, 5, null, ' experto   2 ', '2005-01-01'),
            $this->docente('Experto II', 40, 0, 5, null, 'Experto II', '2015-01-01'),
        ]));

        $this->assertSame([
            'Gremial', 'Lactancia', 'Avanzado', 'Experto 2', 'Experto 1', 'Experto I', 'Experto II',
            'Resto titular', 'Contrata experto',
        ], $docentes->pluck('nombre')->all());
        $this->assertSame([1, 1, 2, 2, 2, 2, 2, 3, 4], $docentes->pluck('prioridad_2027')->all());
        $this->assertCount(1, $docentes->filter(fn ($docente) => $docente['prioridad_2027'] === 2)->pluck('prioridad_2027_label')->unique());
        $this->assertStringContainsString('Experto 1 / Experto 2', $docentes->firstWhere('nombre', 'Experto II')['prioridad_2027_label']);
        $experto = $docentes->firstWhere('nombre', 'Experto II');
        $this->assertSame(35.0, $experto['horas_titulares_disponibles']);
        $this->assertSame(35.0, $experto['horas_disponibles']);
        $this->assertSame(4, $docentes->last()['prioridad_2027']);
    }

    public function test_ordena_el_resto_titular_por_antiguedad_y_exige_justificacion_al_omitirla(): void
    {
        $sinFecha = $this->docente('Sin fecha', 20, 0, 0, null, 'Inicial', '2020-01-01');
        $sinFecha['fecha_antiguedad'] = null;
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('Titular reciente', 20, 0, 0, null, 'Temprano', '2018-01-01'),
            $sinFecha,
            $this->docente('Titular antiguo', 20, 0, 0, null, 'Inicial', '2005-01-01'),
            $this->docente('Titular intermedio', 20, 0, 0, null, 'Acceso', '2010-01-01'),
        ]));

        $this->assertSame([
            'Titular antiguo', 'Titular intermedio', 'Titular reciente', 'Sin fecha',
        ], $docentes->pluck('nombre')->all());
        $this->assertSame([3, 3, 3, 3], $docentes->pluck('prioridad_2027')->all());
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible(
            $docentes, $docentes->firstWhere('nombre', 'Titular reciente')
        ));
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible(
            $docentes, $docentes->firstWhere('nombre', 'Sin fecha')
        ));
        $this->assertFalse(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible(
            $docentes, $docentes->firstWhere('nombre', 'Titular antiguo')
        ));

        $sinSaldoAntiguo = $docentes->map(function (array $docente): array {
            if (in_array($docente['nombre'], ['Titular antiguo', 'Titular intermedio'], true)) {
                $docente['horas_disponibles'] = 0.0;
            }
            return $docente;
        });
        $this->assertFalse(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible(
            $sinSaldoAntiguo, $sinSaldoAntiguo->firstWhere('nombre', 'Titular reciente')
        ));
    }

    public function test_aplica_antiguedad_dentro_del_grupo_avanzado_y_experto_sin_priorizar_tramo(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('Experto antiguo', 20, 0, 0, null, 'Experto I', '2000-01-01'),
            $this->docente('Avanzado reciente', 20, 0, 0, null, 'Avanzado', '2010-01-01'),
        ]));

        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible(
            $docentes, $docentes->firstWhere('nombre', 'Avanzado reciente')
        ));
        $this->assertFalse(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible(
            $docentes, $docentes->firstWhere('nombre', 'Experto antiguo')
        ));
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

    public function test_funciones_no_normativas_permanecen_en_parvularia_y_pie(): void
    {
        $parvularia = [
            'rut_normalizado' => '111111111', 'nombre' => 'Docente de Párvulos',
            'titulo' => 'Pedagogía en Educación de Párvulos',
            'horas_contrato' => 44, 'horas_planta' => 44, 'horas_contrata' => 0,
            'horas_asignadas_total' => 0,
        ];
        $diferencial = [
            'rut_normalizado' => '222222222', 'nombre' => 'Docente Diferencial',
            'titulo' => 'Educadora Diferencial',
            'horas_contrato' => 44, 'horas_planta' => 44, 'horas_contrata' => 0,
            'horas_asignadas_total' => 0,
        ];
        $asignaciones = [
            ['docente_rut_normalizado' => '111111111', 'tipo_asignacion' => 'funcion_directiva', 'horas_contrato' => 4],
            ['docente_rut_normalizado' => '111111111', 'tipo_asignacion' => 'otra_funcion', 'dotacion_funcion_id' => 1, 'horas_contrato' => 6],
            ['docente_rut_normalizado' => '111111111', 'tipo_asignacion' => 'funcion_tecnico_pedagogica', 'subtipo_asignacion' => 'pie', 'horas_contrato' => 2],
            ['docente_rut_normalizado' => '222222222', 'tipo_asignacion' => 'plan_normativo', 'horas_contrato' => 4],
            ['docente_rut_normalizado' => '222222222', 'tipo_asignacion' => 'otra_funcion', 'dotacion_funcion_id' => 2, 'horas_contrato' => 6],
            ['docente_rut_normalizado' => '222222222', 'tipo_asignacion' => 'funcion_tecnico_pedagogica', 'subtipo_asignacion' => 'pie', 'horas_contrato' => 2],
        ];

        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => ['necesidades' => [], 'asignaciones' => $asignaciones],
            'docentes' => [$parvularia, $diferencial],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        $this->assertSame(10.0, $resumen['bloques']['bloque_1']['asignadas']);
        $this->assertSame(6.0, $resumen['bloques']['bloque_2']['asignadas']);
        $this->assertSame(8.0, $resumen['bloques']['bloque_3']['asignadas']);
        $this->assertSame('bloque_1', DotacionProceso2027Calculator::bloqueFuncionPorDocente($asignaciones[2], $parvularia));
        $this->assertSame('bloque_3', DotacionProceso2027Calculator::bloqueFuncionPorDocente($asignaciones[5], $diferencial));
    }

    public function test_coordinacion_pie_cubre_la_necesidad_sin_duplicar_el_bloque_contractual(): void
    {
        $necesidad = [
            'key' => 'funcion:coordinacion_pie',
            'tipo_asignacion' => 'funcion_tecnico_pedagogica',
            'subtipo_asignacion' => 'pie',
            'horas_contrato_requeridas' => 4,
            'horas_contrato_asignadas' => 4,
            'necesidad_condicionada_por_asignacion_docente' => true,
        ];
        $asignacion = [
            'necesidad_key' => $necesidad['key'],
            'docente_rut_normalizado' => '111111111',
            'tipo_asignacion' => 'funcion_tecnico_pedagogica',
            'subtipo_asignacion' => 'pie',
            'horas_contrato' => 4,
        ];
        $docente = [
            'rut_normalizado' => '111111111', 'titulo' => 'Pedagogía en Educación de Párvulos',
            'horas_contrato' => 44, 'horas_planta' => 44, 'horas_contrata' => 0,
            'horas_asignadas_total' => 0,
        ];

        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => ['necesidades' => ['funciones' => [$necesidad]], 'asignaciones' => [$asignacion]],
            'docentes' => [$docente],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        $this->assertSame(4.0, $resumen['bloques']['bloque_1']['asignadas']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_3']['asignadas']);
        $this->assertSame(4.0, $resumen['bloques']['bloque_3']['requeridas']);
        $this->assertSame(4.0, $resumen['bloques']['bloque_3']['asignadas_obligatorias']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_3']['pendientes']);
        $this->assertSame(4.0, $resumen['bloques']['bloque_3']['horas_normativas_potenciales']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_1']['horas_normativas_potenciales']);
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

    public function test_no_completa_los_planes_si_nt_y_libre_disposicion_siguen_pendientes(): void
    {
        $resumen = DotacionProceso2027Calculator::resumen(
            new Establecimiento(['id' => 1]),
            2027,
            [
                'cursos' => [
                    'totales' => ['cursos' => 2, 'sin_horas_plan' => 0],
                    'configuracion_planes' => [
                        'completo' => false,
                        'total' => 2,
                        'configurados' => 0,
                        'pendientes' => ['NT1 A', 'NT2 A'],
                        'libre_disposicion_pendiente' => 2,
                        'detalle' => '0 de 2 curso(s) listos; 2 pendiente(s). 2 pendiente(s) incluyen horas de libre disposición.',
                    ],
                ],
                'asignacion' => ['necesidades' => [], 'asignaciones' => []],
                'docentes' => [],
                'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
            ]
        );

        $this->assertFalse($resumen['pasos']['planes']['completo']);
        $this->assertFalse($resumen['asignacion_habilitada']);
        $this->assertSame(2, $resumen['estado_planes']['libre_disposicion_pendiente']);
        $this->assertStringContainsString('libre disposición', $resumen['pasos']['planes']['detalle']);
    }

    public function test_usa_el_contrato_ajustado_por_cursos_combinados_en_lugar_de_sumar_asignaturas_brutas(): void
    {
        $resumen = DotacionProceso2027Calculator::resumen(
            new Establecimiento(['id' => 1]),
            2027,
            [
                'resumen' => [
                    'contrato_plan_general_mas_trabajo_colaborativo_pie' => 392,
                    'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 72,
                ],
                'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
                'asignacion' => [
                    'necesidades' => [
                        'plan_estudio' => [[
                            'key' => 'plan:combinado',
                            'horas_contrato_requeridas' => 396,
                        ]],
                    ],
                    'asignaciones' => [],
                ],
                'docentes' => [],
                'cursos_combinados' => ['resumen' => ['grupos_activos' => 1]],
            ]
        );

        $this->assertSame(392.0, $resumen['bloques']['bloque_1']['requeridas']);
        $this->assertSame(72.0, $resumen['bloques']['bloque_2']['requeridas']);
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
