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

    public function test_el_maximo_reserva_el_plan_consolidado_y_agrega_funciones_no_normativas(): void
    {
        $this->assertSame(593.0, DotacionProceso2027Calculator::contratoComprometidoParaMaximo(593, 569, 0));
        $this->assertSame(603.0, DotacionProceso2027Calculator::contratoComprometidoParaMaximo(593, 579, 10));
        $this->assertSame(614.0, DotacionProceso2027Calculator::contratoComprometidoParaMaximo(593, 614, 10));
    }

    public function test_el_acompanamiento_simultaneo_no_ocupa_un_segundo_cupo_del_maximo(): void
    {
        $this->assertSame(0.0, DotacionProceso2027Calculator::horasImputablesAlMaximo('acompanamiento_parvularia', 8));
        $this->assertSame(8.0, DotacionProceso2027Calculator::horasImputablesAlMaximo('plan_estudio', 8));

        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, [
            'resumen' => [
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 0,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 55,
                'contrato_plan_por_ensenanza_desglose' => ['contrato_plan_parvularia' => 55],
            ],
            'cursos' => [
                'rows' => ['NT1' => ['detalles' => [['establecimiento_curso_id' => 99]]]],
                'totales' => ['cursos' => 1, 'sin_horas_plan' => 0],
            ],
            'asignacion' => [
                'necesidades' => ['plan_estudio' => [[
                    'key' => 'plan:nt', 'establecimiento_curso_id' => 99,
                    'horas_plan_requeridas' => 35, 'horas_plan_asignadas' => 35,
                ]]],
                'asignaciones' => [
                    ['necesidad_key' => 'plan:nt', 'tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 55],
                    ['necesidad_key' => 'plan:nt', 'tipo_asignacion' => 'acompanamiento_parvularia', 'horas_contrato' => 8],
                ],
            ],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        $bloque = $resumen['bloques']['bloque_2'];
        $this->assertSame(63.0, $bloque['asignadas']);
        $this->assertSame(8.0, $bloque['asignadas_acompanamiento']);
        $this->assertSame(55.0, $bloque['contrato_comprometido']);
        $this->assertSame(0.0, $bloque['pendientes']);
    }

    public function test_libre_disposicion_nt_de_otro_docente_se_registra_en_plan_general(): void
    {
        $asignacionExterna = [
            'necesidad_key' => 'plan:nt', 'tipo_asignacion' => 'plan_estudio',
            'subtipo_asignacion' => 'libre_disposicion', 'proporcion_aplicada' => '65/35',
            'horas_contrato' => 7,
        ];
        $this->assertSame('bloque_1', DotacionProceso2027Calculator::bloqueLibreDisposicionNtOtroDocente(
            $asignacionExterna, 'bloque_2', ['titulo' => 'Pedagogía en Educación Básica']
        ));
        $this->assertNull(DotacionProceso2027Calculator::bloqueLibreDisposicionNtOtroDocente(
            $asignacionExterna, 'bloque_2', ['titulo' => 'Pedagogía en Educación de Párvulos']
        ));
        $this->assertNull(DotacionProceso2027Calculator::bloqueLibreDisposicionNtOtroDocente(
            array_merge($asignacionExterna, ['proporcion_aplicada' => 'NT Con JEC']), 'bloque_2', ['titulo' => '']
        ));

        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, [
            'resumen' => [
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 7,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 55,
                'contrato_plan_por_ensenanza_desglose' => [
                    'contrato_plan_general' => 7, 'contrato_plan_parvularia' => 55,
                ],
            ],
            'cursos' => [
                'rows' => ['NT1' => ['detalles' => [['establecimiento_curso_id' => 99]]]],
                'totales' => ['cursos' => 1, 'sin_horas_plan' => 0],
            ],
            'asignacion' => [
                'necesidades' => ['plan_estudio' => [[
                    'key' => 'plan:nt', 'establecimiento_curso_id' => 99,
                    'horas_plan_requeridas' => 38, 'horas_plan_asignadas' => 38,
                ]]],
                'asignaciones' => [
                    ['necesidad_key' => 'plan:nt', 'tipo_asignacion' => 'plan_estudio',
                        'subtipo_asignacion' => 'tiempo_minimo', 'proporcion_aplicada' => 'NT Con JEC',
                        'horas_contrato' => 48],
                    $asignacionExterna,
                ],
            ],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        $general = $resumen['bloques']['bloque_1'];
        $parvularia = $resumen['bloques']['bloque_2'];
        $this->assertSame(7.0, $general['asignadas']);
        $this->assertSame(7.0, $general['asignadas_libre_disposicion_nt_otro_docente']);
        $this->assertSame(48.0, $parvularia['asignadas']);
        $this->assertSame(0.0, $parvularia['asignadas_libre_disposicion_nt_otro_docente']);
        $this->assertSame(7.0, $general['asignadas_plan_obligatorias']);
        $this->assertSame(48.0, $parvularia['asignadas_plan_obligatorias']);
        $this->assertSame(0.0, $general['ajuste_cobertura_plan']);
        $this->assertSame(7.0, $parvularia['ajuste_cobertura_plan']);
        $this->assertSame(0.0, $general['pendientes']);
        $this->assertSame(0.0, $parvularia['pendientes']);
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

    public function test_cuenta_la_asignacion_vinculada_a_una_necesidad_con_clave_historica(): void
    {
        $asignacion = [
            'id' => 41,
            'necesidad_key' => 'funcion:clave_anterior',
            'docente_rut_normalizado' => '111111111',
            'tipo_asignacion' => 'funcion_tecnico_pedagogica',
            'dotacion_funcion_regla_id' => 99,
            'horas_contrato' => 8,
            'estamento_cobertura' => 'docente',
            'estado' => 'activa',
        ];
        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => [
                'necesidades' => ['funciones' => [[
                    'key' => 'funcion:clave_vigente',
                    'tipo_asignacion' => 'funcion_tecnico_pedagogica',
                    'dotacion_funcion_regla_id' => 99,
                    'horas_contrato_requeridas' => 8,
                    'horas_contrato_asignadas' => 8,
                    'necesidad_condicionada_por_asignacion_docente' => true,
                    'asignaciones' => [$asignacion],
                ]]],
                'asignaciones' => [$asignacion],
            ],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        $this->assertSame(8.0, $resumen['bloques']['bloque_1']['asignadas_obligatorias']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_1']['pendientes']);
        $this->assertTrue($resumen['pasos']['asignacion']['completo']);
    }

    public function test_cuenta_horas_obligatorias_asignadas_a_docente_provisional_de_parvularia(): void
    {
        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, [
            'resumen' => [
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 0,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 32,
            ],
            'cursos' => [
                'rows' => ['NT1' => ['detalles' => [['establecimiento_curso_id' => 99]]]],
                'totales' => ['cursos' => 1, 'sin_horas_plan' => 0],
            ],
            'asignacion' => [
                'necesidades' => ['plan_estudio' => [[
                    'key' => 'plan:nt1',
                    'establecimiento_curso_id' => 99,
                    'horas_contrato_requeridas' => 32,
                ]]],
                'asignaciones' => [[
                    'necesidad_key' => 'plan:nt1',
                    'docente_rut_normalizado' => 'VACANTE_7',
                    'tipo_asignacion' => 'plan_estudio',
                    'establecimiento_curso_id' => 99,
                    'horas_contrato' => 32,
                    'estamento_cobertura' => 'docente',
                    'estado' => 'activa',
                ]],
            ],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        $this->assertSame(32.0, $resumen['bloques']['bloque_2']['asignadas_obligatorias']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_2']['pendientes']);
        $this->assertTrue($resumen['pasos']['asignacion']['completo']);
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

    public function test_acredita_el_plan_completo_con_su_contrato_consolidado_sin_liberar_horas_del_maximo(): void
    {
        $data = [
            'resumen' => [
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 390,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 116,
                'contrato_plan_por_ensenanza_desglose' => [
                    'contrato_plan_general' => 366,
                    'contrato_plan_parvularia' => 110,
                ],
            ],
            'cursos' => [
                'rows' => [
                    'NT1' => ['detalles' => [['establecimiento_curso_id' => 99]]],
                    '1B' => ['detalles' => [['establecimiento_curso_id' => 100]]],
                ],
                'totales' => ['cursos' => 2, 'sin_horas_plan' => 0],
            ],
            'asignacion' => [
                'necesidades' => [
                    'plan_estudio' => [
                        ['key' => 'plan:general', 'establecimiento_curso_id' => 100, 'horas_plan_requeridas' => 300, 'horas_plan_asignadas' => 300],
                        ['key' => 'plan:nt', 'establecimiento_curso_id' => 99, 'horas_plan_requeridas' => 80, 'horas_plan_asignadas' => 80],
                    ],
                    'pie_colaborativo' => [
                        ['key' => 'pie:general', 'establecimiento_curso_id' => 100, 'horas_contrato_requeridas' => 24],
                        ['key' => 'pie:nt', 'establecimiento_curso_id' => 99, 'horas_contrato_requeridas' => 6],
                    ],
                    'pie_educadora_diferencial' => [['key' => 'pie:especializado', 'horas_contrato_requeridas' => 224]],
                    'funciones' => [['key' => 'funcion:normativa', 'horas_contrato_requeridas' => 203]],
                ],
                'asignaciones' => [
                    ['necesidad_key' => 'plan:general', 'tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 342],
                    ['necesidad_key' => 'plan:nt', 'tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 106.4],
                    ['necesidad_key' => 'pie:general', 'tipo_asignacion' => 'pie_colaborativo', 'horas_contrato' => 24],
                    ['necesidad_key' => 'pie:nt', 'tipo_asignacion' => 'pie_colaborativo', 'horas_contrato' => 6],
                    ['necesidad_key' => 'pie:especializado', 'tipo_asignacion' => 'pie_educadora_diferencial', 'horas_contrato' => 224],
                    ['necesidad_key' => 'funcion:normativa', 'tipo_asignacion' => 'funcion_directiva', 'horas_contrato' => 203],
                ],
            ],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ];

        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, $data);

        $this->assertSame(569.0, $resumen['bloques']['bloque_1']['asignadas']);
        $this->assertSame(112.4, $resumen['bloques']['bloque_2']['asignadas']);
        $this->assertSame(24.0, $resumen['bloques']['bloque_1']['ajuste_cobertura_plan']);
        $this->assertSame(3.6, $resumen['bloques']['bloque_2']['ajuste_cobertura_plan']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_1']['pendientes']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_2']['pendientes']);
        $this->assertSame(593.0, $resumen['bloques']['bloque_1']['contrato_comprometido']);
        $this->assertSame(116.0, $resumen['bloques']['bloque_2']['contrato_comprometido']);
        $this->assertTrue($resumen['pasos']['asignacion']['completo']);

        $dataConNoNormativa = $data;
        $dataConNoNormativa['asignacion']['asignaciones'][] = [
            'tipo_asignacion' => 'otra_funcion', 'dotacion_funcion_id' => 10, 'horas_contrato' => 10,
        ];
        $conNoNormativa = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, $dataConNoNormativa);
        $this->assertSame(10.0, $conNoNormativa['bloques']['bloque_1']['asignadas_no_normativas']);
        $this->assertSame(603.0, $conNoNormativa['bloques']['bloque_1']['contrato_comprometido']);

        $data['asignacion']['necesidades']['plan_estudio'][0]['horas_plan_asignadas'] = 299;
        $incompleto = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, $data);
        $this->assertSame(0.0, $incompleto['bloques']['bloque_1']['ajuste_cobertura_plan']);
        $this->assertSame(24.0, $incompleto['bloques']['bloque_1']['pendientes']);
        $this->assertFalse($incompleto['pasos']['asignacion']['completo']);

        $data['asignacion']['necesidades']['plan_estudio'][0]['horas_plan_asignadas'] = 300;
        $data['asignacion']['asignaciones'][2]['horas_contrato'] = 23;
        $pieIncompleto = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, $data);
        $this->assertSame(1.0, $pieIncompleto['bloques']['bloque_1']['pendientes']);
        $this->assertFalse($pieIncompleto['pasos']['asignacion']['completo']);
    }

    public function test_cuenta_cobertura_normativa_aaee_sin_duplicar_contrato_docente(): void
    {
        $docente = [
            'id' => 1, 'necesidad_key' => 'funcion:normativa',
            'tipo_asignacion' => 'funcion_directiva', 'estamento_cobertura' => 'docente',
            'horas_contrato' => 197, 'estado' => 'activa',
        ];
        $asistente = [
            'id' => 2, 'necesidad_key' => 'funcion:normativa',
            'tipo_asignacion' => 'funcion_directiva', 'estamento_cobertura' => 'asistente',
            'horas_contrato' => 6, 'estado' => 'activa',
        ];
        $data = [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => [
                'necesidades' => ['funciones' => [[
                    'key' => 'funcion:normativa', 'tipo_asignacion' => 'funcion_directiva',
                    'horas_contrato_requeridas' => 203, 'horas_contrato_asignadas' => 203,
                    'asignaciones' => [$docente, $asistente],
                ]]],
                'asignaciones' => [$docente, $asistente],
            ],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ];

        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, $data);

        $this->assertSame(197.0, $resumen['bloques']['bloque_1']['asignadas']);
        $this->assertSame(203.0, $resumen['bloques']['bloque_1']['asignadas_obligatorias']);
        $this->assertSame(6.0, $resumen['bloques']['bloque_1']['asignadas_asistentes_obligatorias']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_1']['pendientes']);
        $this->assertTrue($resumen['pasos']['asignacion']['completo']);

        $data['asignacion']['necesidades']['funciones'][0]['horas_contrato_requeridas'] = 197;
        $sinDobleConteo = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, $data);
        $this->assertSame(0.0, $sinDobleConteo['bloques']['bloque_1']['asignadas_asistentes_obligatorias']);
    }

    public function test_acredita_aaee_de_trabajo_colaborativo_sin_sumarlos_al_contrato_docente(): void
    {
        $asistente = [
            'id' => 7, 'necesidad_key' => 'pie:colaborativo',
            'tipo_asignacion' => 'pie_colaborativo', 'estamento_cobertura' => 'asistente',
            'horas_contrato' => 6, 'estado' => 'activa',
        ];
        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => [
                'necesidades' => ['pie_colaborativo' => [[
                    'key' => 'pie:colaborativo', 'horas_contrato_requeridas' => 6,
                    'horas_contrato_asignadas' => 6, 'asignaciones' => [$asistente],
                ]]],
                'asignaciones' => [$asistente],
            ],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        $this->assertSame(6.0, $resumen['bloques']['bloque_1']['asignadas_obligatorias']);
        $this->assertSame(6.0, $resumen['bloques']['bloque_1']['asignadas_asistentes_obligatorias']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_1']['asignadas']);
        $this->assertSame(0.0, $resumen['bloques']['bloque_1']['pendientes']);
    }

    public function test_no_acredita_la_plaza_automatica_de_director_por_asumir(): void
    {
        $porAsumir = [
            'necesidad_key' => 'funcion:director', 'tipo_asignacion' => 'funcion_directiva',
            'horas_contrato' => 44, 'asignacion_automatica' => true,
        ];
        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => [
                'necesidades' => ['funciones' => [[
                    'key' => 'funcion:director', 'tipo_asignacion' => 'funcion_directiva',
                    'horas_contrato_requeridas' => 44, 'horas_contrato_asignadas' => 44,
                    'horas_contrato_asignadas_calculo' => 0, 'asignacion_automatica' => true,
                    'asignaciones' => [$porAsumir],
                ]]],
                'asignaciones' => [$porAsumir],
            ],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        $this->assertSame(0.0, $resumen['bloques']['bloque_1']['asignadas_obligatorias']);
        $this->assertSame(44.0, $resumen['bloques']['bloque_1']['pendientes']);
        $this->assertFalse($resumen['pasos']['asignacion']['completo']);
    }

    public function test_clasifica_titular_y_contrata_desde_el_padron_sin_descontar_dos_veces_las_asignaciones(): void
    {
        $docente = $this->docente('Docente prueba', 30, 14, 44, null, 'Inicial', '2000-01-01');
        $docente['rut_normalizado'] = '111111111';
        $asignaciones = [
            ['docente_rut_normalizado' => '111111111', 'tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 35],
            ['docente_rut_normalizado' => '111111111', 'tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 9],
            ['docente_rut_normalizado' => '222222222', 'tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 6],
        ];
        $resumen = DotacionProceso2027Calculator::resumen(new Establecimiento(['id' => 1]), 2027, [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => ['necesidades' => [], 'asignaciones' => $asignaciones, 'docentes' => [$docente]],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        $this->assertSame(30.0, $resumen['bloques']['bloque_1']['titulares_asignadas']);
        $this->assertSame(14.0, $resumen['bloques']['bloque_1']['contrata_asignadas']);
        $this->assertSame(6.0, $resumen['bloques']['bloque_1']['sin_padron_asignadas']);
        $this->assertSame(50.0, $resumen['bloques']['bloque_1']['asignadas']);
        $this->assertSame(0.0, $resumen['docentes']->first()['horas_disponibles']);
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
