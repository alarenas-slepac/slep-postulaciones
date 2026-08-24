<?php

namespace Tests\Unit;

use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionDocenteExclusion;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class DotacionDocenteDetalleHorasTest extends TestCase
{
    public function test_consolida_todas_las_lineas_del_ultimo_mes_y_separa_planta_contrata(): void
    {
        $personal = collect([
            $this->personal(1, '11111111-1', 2026, 6, 'PLANTA', 44, 'anterior-planta'),
            $this->personal(2, '22222222-2', 2026, 6, 'CONTRATA', 30, 'solo-mes-anterior'),
            $this->personal(3, '11111111-1', 2026, 7, 'PLANTA', 30, 'actual-planta'),
            $this->personal(4, '11111111-1', 2026, 7, 'CONTRATA', 14, 'actual-contrata'),
            $this->personal(5, '11111111-1', 2026, 7, 'CONTRATA', 14, 'actual-contrata'),
        ]);

        /** @var Collection $resultado */
        $resultado = $this->invokePrivate('consolidarPersonalUltimoPeriodo', [$personal]);

        $this->assertCount(1, $resultado);
        $grupo = $resultado->first();

        $this->assertSame(2026, $grupo['anio']);
        $this->assertSame(7, $grupo['mes']);
        $this->assertSame(2, $grupo['registros']);
        $this->assertSame(44.0, $grupo['jornada_total']);
        $this->assertSame(30.0, $grupo['jornada_planta_total']);
        $this->assertSame(14.0, $grupo['jornada_contrata_total']);
        $this->assertEqualsCanonicalizing(['PLANTA', 'CONTRATA'], explode(' / ', $grupo['tipos_contrato']));
    }

    public function test_detalla_y_agrupa_solo_otras_funciones_con_horas_positivas(): void
    {
        $asignaciones = collect([
            $this->asignacion('otra_funcion', 'Coordinación medioambiental', 3),
            $this->asignacion('otra_funcion', 'Coordinación medioambiental', 2),
            $this->asignacion('otra_funcion', 'Apoyo biblioteca', 4),
            $this->asignacion('otra_funcion', 'Sin horas', 0),
            $this->asignacion('funcion_directiva', 'Dirección', 44),
        ]);

        $resultado = $this->invokePrivate('detalleOtrasFunciones', [$asignaciones]);

        $this->assertSame([
            ['nombre' => 'Apoyo biblioteca', 'horas' => 4.0],
            ['nombre' => 'Coordinación medioambiental', 'horas' => 5.0],
        ], $resultado);
    }

    public function test_detalla_y_agrupa_funciones_tecnico_pedagogicas_con_horas_positivas(): void
    {
        $asignaciones = collect([
            $this->asignacion('funcion_tecnico_pedagogica', 'Coordinación PIE', 3),
            $this->asignacion('funcion_tecnico_pedagogica', 'Coordinación PIE', 2),
            $this->asignacion('funcion_tecnico_pedagogica', 'Orientación', 4),
            $this->asignacion('funcion_tecnico_pedagogica', 'Sin horas', 0),
            $this->asignacion('otra_funcion', 'Apoyo biblioteca', 6),
        ]);

        $resultado = $this->invokePrivate('detalleFuncionesTecnicoPedagogicas', [$asignaciones]);

        $this->assertSame([
            ['nombre' => 'Coordinación PIE', 'horas' => 5.0],
            ['nombre' => 'Orientación', 'horas' => 4.0],
        ], $resultado);
    }

    public function test_vista_omite_calidades_en_cero_y_muestra_detalles_de_funciones(): void
    {
        $html = view('admin.dotacion-establecimiento.partials._docentes', [
            'docentes' => collect([$this->docenteVista()]),
        ])->render();

        $this->assertStringContainsString('Planta 44 h', $html);
        $this->assertStringNotContainsString('Contrata 0 h', $html);
        $this->assertStringNotContainsString('Contrata: 0 h', $html);
        $this->assertStringContainsString('Detalle de funciones técnico-pedagógicas', $html);
        $this->assertStringContainsString('Coordinación PIE', $html);
        $this->assertStringContainsString('Detalle de otras horas', $html);
        $this->assertStringContainsString('Coordinación medioambiental', $html);
    }

    public function test_exclusion_descuenta_solo_las_horas_indicadas_del_contrato_original(): void
    {
        $resultado = $this->invokePrivate('ajustarHorasContratoPorExclusion', [44, 14]);

        $this->assertSame(44.0, $resultado['horas_base']);
        $this->assertSame(14.0, $resultado['horas_excluidas']);
        $this->assertSame(30.0, $resultado['horas_consideradas']);
    }

    public function test_extrae_horas_pie_normativas_del_contrato_bloque_sin_alterar_la_necesidad_total(): void
    {
        $bloques = [
            'directiva' => ['automaticas' => 44, 'declaradas' => 0, 'total' => 44],
            'tecnico_pedagogica' => ['automaticas' => 38, 'declaradas' => 14, 'total' => 52],
            'pie' => [
                'automaticas' => 83,
                'declaradas' => 5,
                'total' => 88,
                'educadoras_diferenciales' => 65,
                'items' => [
                    ['nombre' => 'Coordinador(a) PIE', 'origen' => 'Automática', 'horas' => 18, 'tipo_contrato_pie_necesario' => 'coordinacion_pie'],
                    ['nombre' => 'Educadoras diferenciales PIE', 'origen' => 'Automática', 'horas' => 65, 'tipo_contrato_pie_necesario' => 'educadoras_diferenciales'],
                    ['nombre' => 'Apoyo PIE declarado', 'origen' => 'Declarada', 'horas' => 5],
                ],
            ],
            'planes_programas' => ['automaticas' => 19, 'declaradas' => 0, 'total' => 19],
            'otras_funciones_docentes' => ['automaticas' => 0, 'declaradas' => 7, 'total' => 7],
        ];

        $necesidadesFunciones = [
            ['subtipo_asignacion' => 'directiva', 'dotacion_funcion_id' => null, 'horas_contrato_asignadas' => 32],
            ['subtipo_asignacion' => 'tecnico_pedagogica', 'dotacion_funcion_id' => null, 'horas_contrato_asignadas' => 24],
            ['subtipo_asignacion' => 'tecnico_pedagogica', 'dotacion_funcion_id' => 10, 'horas_contrato_asignadas' => 8],
            ['subtipo_asignacion' => 'planes_programas', 'dotacion_funcion_id' => null, 'horas_contrato_asignadas' => 12],
            ['subtipo_asignacion' => 'pie', 'dotacion_funcion_id' => 11, 'horas_contrato_asignadas' => 3],
            ['subtipo_asignacion' => 'otras_funciones_docentes', 'dotacion_funcion_id' => 12, 'horas_contrato_asignadas' => 4],
        ];

        $necesidadesEducadorasDiferenciales = [
            ['horas_contrato_asignadas' => 40],
        ];

        $desglosePie = $this->invokePrivate('desgloseContratoPieNecesario', [
            $bloques,
            [
                ['subtipo_asignacion' => 'pie', 'dotacion_funcion_id' => null, 'horas_contrato_asignadas' => 12],
                ['subtipo_asignacion' => 'pie', 'dotacion_funcion_id' => 11, 'horas_contrato_asignadas' => 3],
            ],
            $necesidadesEducadorasDiferenciales,
        ]);
        $bloquesContratoDotacion = $this->invokePrivate('bloquesSinContratoPieNecesario', [$bloques]);
        $resultado = $this->invokePrivate('desgloseContratoBloqueDotacion', [$bloquesContratoDotacion, $necesidadesFunciones]);

        $this->assertSame([
            'coordinacion_pie' => 18.0,
            'coordinacion_pie_asignadas' => 12.0,
            'educadoras_diferenciales' => 65.0,
            'educadoras_diferenciales_asignadas' => 40.0,
            'total' => 83.0,
            'total_asignadas' => 52.0,
        ], $desglosePie);
        $this->assertSame(0.0, $bloquesContratoDotacion['pie']['automaticas']);
        $this->assertSame(5.0, $bloquesContratoDotacion['pie']['total']);
        $this->assertCount(1, $bloquesContratoDotacion['pie']['items']);

        $this->assertSame([
            'funciones_directivas' => 44.0,
            'funciones_directivas_normativas' => 44.0,
            'funciones_directivas_declaradas' => 0.0,
            'funciones_directivas_normativas_asignadas' => 32.0,
            'funciones_directivas_declaradas_asignadas' => 0.0,
            'funciones_tecnico_pedagogicas' => 52.0,
            'funciones_tecnico_pedagogicas_normativas' => 38.0,
            'funciones_tecnico_pedagogicas_declaradas' => 14.0,
            'funciones_tecnico_pedagogicas_normativas_asignadas' => 24.0,
            'funciones_tecnico_pedagogicas_declaradas_asignadas' => 8.0,
            'otras_funciones_pie' => 5.0,
            'otras_funciones_pie_asignadas' => 3.0,
            'planes_normativos' => 19.0,
            'planes_normativos_asignadas' => 12.0,
            'planes_declarados' => 0.0,
            'planes_declarados_asignadas' => 0.0,
            'otras_funciones_declaradas' => 7.0,
            'otras_funciones_declaradas_asignadas' => 4.0,
            'total_normativas' => 101.0,
            'total_declaradas' => 26.0,
            'total_declaradas_asignadas' => 15.0,
        ], $resultado);
        $this->assertSame(
            $resultado['funciones_tecnico_pedagogicas'],
            $resultado['funciones_tecnico_pedagogicas_normativas'] + $resultado['funciones_tecnico_pedagogicas_declaradas']
        );
        $this->assertSame(
            210.0,
            (float) collect($bloquesContratoDotacion)->sum(fn ($bloque) => (float) ($bloque['total'] ?? 0)) + $desglosePie['total']
        );
    }

    public function test_resume_cobertura_asignada_de_plan_y_trabajo_colaborativo_pie(): void
    {
        $resultado = $this->invokePrivate('coberturaPlanYTrabajoColaborativo', [[
            'necesidades' => [
                'plan_estudio' => [
                    ['horas_plan_asignadas' => 30, 'horas_contrato_asignadas' => 44],
                    ['horas_plan_asignadas' => 20, 'horas_contrato_asignadas' => 31],
                ],
                'pie_colaborativo' => [
                    ['horas_contrato_asignadas' => 3],
                    ['horas_contrato_asignadas' => 2],
                ],
            ],
        ]]);

        $this->assertSame([
            'horas_plan_asignadas' => 50.0,
            'horas_contrato_plan_asignadas' => 75.0,
            'trabajo_colaborativo_pie_asignadas' => 5.0,
            'contrato_plan_mas_trabajo_colaborativo_pie_asignadas' => 80.0,
        ], $resultado);
    }

    public function test_calcula_brechas_separadas_de_dotacion_general_y_pie(): void
    {
        $resultado = $this->invokePrivate('brechasDotacionSeparadas', [
            500,
            101,
            620,
            26,
            83,
            52,
        ]);

        $this->assertSame([
            'general' => 7.0,
            'pie' => 31.0,
        ], $resultado);
    }

    public function test_vista_ofrece_todos_los_motivos_y_limita_horas_al_saldo_sin_asignar(): void
    {
        $establecimiento = new Establecimiento(['nombre_establecimiento' => 'Establecimiento de prueba']);
        $establecimiento->id = 10;
        $docente = array_merge($this->docenteVista(), [
            'horas_contrato_base' => 44.0,
            'horas_excluidas' => 0.0,
            'exclusion_docente' => null,
            'horas_asignadas_total' => 30.0,
            'diferencia' => 14.0,
            'estado_cuadratura' => [
                'key' => 'faltan_horas',
                'label' => 'Faltan horas',
                'class' => 'text-bg-info',
                'detalle' => 'Existen horas contratadas sin asignación clasificada.',
            ],
        ]);

        $html = view('admin.dotacion-establecimiento.partials._docentes', [
            'docentes' => collect([$docente]),
            'establecimiento' => $establecimiento,
            'anio' => 2026,
            'canManageDocenteExclusiones' => true,
            'docenteExclusionesTableReady' => true,
            'motivosExclusionDocente' => DotacionDocenteExclusion::MOTIVOS,
        ])->render();

        foreach (DotacionDocenteExclusion::MOTIVOS as $motivo) {
            $this->assertStringContainsString($motivo, $html);
        }

        $this->assertStringContainsString('name="horas"', $html);
        $this->assertStringContainsString('max="14"', $html);
        $this->assertStringContainsString('Máximo sin asignar: 14 h.', $html);
    }

    private function personal(int $id, string $rut, int $anio, int $mes, string $tipo, int $jornada, string $hash): ReemplazoPersonal
    {
        $personal = new ReemplazoPersonal([
            'rut' => $rut,
            'nombre' => 'Docente de prueba',
            'anio' => $anio,
            'mes' => $mes,
            'tipocontrato' => $tipo,
            'jornada' => $jornada,
            'estatuto' => 'DOCENTE',
            'escalafon' => 'DOCENTE AULA',
            'row_hash' => $hash,
        ]);
        $personal->id = $id;

        return $personal;
    }

    private function asignacion(string $tipo, string $nombre, float $horas): DotacionDocenteAsignacion
    {
        return new DotacionDocenteAsignacion([
            'tipo_asignacion' => $tipo,
            'asignatura_nombre' => $nombre,
            'horas_contrato' => $horas,
        ]);
    }

    private function docenteVista(): array
    {
        return [
            'rut' => '11111111-1',
            'nombre' => 'Docente de prueba',
            'niveles_declarados' => 'Básica',
            'funcion' => 'Docente aula',
            'titulo' => 'Profesor(a)',
            'estamento' => 'DOCENTE',
            'horas_contrato_base' => 44.0,
            'horas_excluidas' => 0.0,
            'horas_contrato' => 44.0,
            'exclusion_docente' => null,
            'horas_planta' => 44.0,
            'horas_contrata' => 0.0,
            'horas_aula' => 30.0,
            'horas_aula_65_35' => 30.0,
            'horas_aula_60_40' => 0.0,
            'horas_contrato_65_35' => 44.0,
            'horas_contrato_60_40' => 0.0,
            'horas_contrato_especial' => 0.0,
            'horas_funciones_total' => 13.0,
            'horas_directivas' => 0.0,
            'horas_tecnico_pedagogicas' => 8.0,
            'horas_pie' => 0.0,
            'horas_planes' => 0.0,
            'horas_otras_funciones' => 5.0,
            'funciones_tecnico_pedagogicas_detalle' => [
                ['nombre' => 'Coordinación PIE', 'horas' => 8.0],
            ],
            'otras_funciones_detalle' => [
                ['nombre' => 'Coordinación medioambiental', 'horas' => 5.0],
            ],
            'horas_asignadas_total' => 57.0,
            'diferencia' => -13.0,
            'estado_cuadratura' => [
                'key' => 'sobrecarga',
                'label' => 'Sobrecarga',
                'class' => 'text-bg-danger',
                'detalle' => 'Las horas asignadas superan las horas contratadas.',
            ],
            'horas_contrato_detalle' => null,
            'registros_contrato' => 1,
            'tipo_contrato' => 'PLANTA',
            'financiamiento' => 'Regular',
            'fuente_contrato' => 'reemplazos_personal',
            'mes' => 7,
            'anio' => 2026,
        ];
    }

    private function invokePrivate(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod(DotacionEstablecimientoCalculator::class, $method);

        return $reflection->invoke(null, ...$arguments);
    }
}
