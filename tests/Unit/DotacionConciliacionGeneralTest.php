<?php

namespace Tests\Unit;

use App\Support\DotacionConciliacionGeneral;
use App\Support\DocenteHorasNoLectivasCalculator;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

class DotacionConciliacionGeneralTest extends TestCase
{
    public function test_concilia_asignaciones_reservas_tope_y_aaee_sin_modificar_la_nomina(): void
    {
        $datos = $this->datos();
        $antes = serialize($datos);
        $resultado = DotacionConciliacionGeneral::build(...$datos);

        $this->assertSame(643.0, $resultado['contrato']);
        $this->assertSame(597.0, $resultado['asignadas']);
        $this->assertSame(10.0, $resultado['reservadas']);
        $this->assertSame(36.0, $resultado['saldo_neto']);
        $this->assertSame(36.0, $resultado['saldo_nomina']);
        $this->assertSame(32.0, $resultado['diferencia_maximo']);
        $this->assertSame(387.0, $resultado['plan_individual']);
        $this->assertSame(385.0, $resultado['plan_institucional']);
        $this->assertSame(2.0, $resultado['diferencia_plan']);
        $this->assertSame(6.0, $resultado['cobertura_aaee']);
        $this->assertSame(0.0, $resultado['otros_ajustes']);
        $this->assertSame($antes, serialize($datos));
    }

    public function test_separa_parvularia_pie_virtuales_inactivos_y_cobertura_asistente(): void
    {
        $datos = $this->datos();
        $datos[0][] = ['cupo_contrata_id' => 1, 'asignaciones' => [$this->fila('otra_funcion', 44)]];
        $datos[0][] = ['estamento_cobertura' => 'asistente', 'asignaciones' => [$this->fila('otra_funcion', 6)]];
        $datos[0][] = [
            'titulo' => 'Pedagogía en Educación de Párvulos',
            'horas_contrato_especial' => 55,
            'asignaciones' => [
                $this->fila('plan_estudio', 55, ['necesidad_key' => 'plan:nt', 'proporcion_aplicada' => 'NT Con JEC']),
                $this->fila('pie_colaborativo', 3, ['necesidad_key' => 'pie:nt']),
                $this->fila('reserva_no_normativa', 1),
            ],
        ];
        $datos[0][] = [
            'titulo' => 'Pedagogía en Educación Diferencial',
            'asignaciones' => [$this->fila('pie_educadora_diferencial', 44), $this->fila('otra_funcion', 3)],
        ];
        $datos[0][0]['asignaciones'][] = $this->fila('funcion_directiva', 44, ['estado' => 'inactiva']);
        $datos[0][0]['asignaciones'][] = $this->fila('funcion_directiva', 44, ['asignacion_automatica' => true]);
        $datos[0][0]['asignaciones'][] = $this->fila('funcion_directiva', 6, ['estamento_cobertura' => 'asistente']);
        $datos[2]['need_blocks'] = ['plan:nt' => 'bloque_2', 'pie:nt' => 'bloque_2'];

        $resultado = DotacionConciliacionGeneral::build(...$datos);
        $this->assertSame(597.0, $resultado['asignadas']);
        $this->assertSame(10.0, $resultado['reservadas']);
        $this->assertSame(36.0, $resultado['saldo_neto']);
    }

    public function test_reasignar_aaee_a_docentes_cambia_saldo_y_no_el_contrato_o_maximo(): void
    {
        $datos = $this->datos();
        $datos[0][0]['asignaciones'][] = $this->fila('funcion_tecnico_pedagogica', 6);
        $datos[2]['bloques']['bloque_1']['asignadas_asistentes_obligatorias'] = 0;
        $datos[3]['vacantes_por_bloque']['plan_estudio']['horas_total'] = 30;

        $resultado = DotacionConciliacionGeneral::build(...$datos);
        $this->assertSame(603.0, $resultado['asignadas']);
        $this->assertSame(30.0, $resultado['saldo_neto']);
        $this->assertSame(32.0, $resultado['diferencia_maximo']);
        $this->assertSame(0.0, $resultado['cobertura_aaee']);
        $this->assertSame(0.0, $resultado['otros_ajustes']);
        $this->assertSame(611.0, $resultado['maximo']);
    }

    public function test_no_atribuye_a_conversion_el_margen_autorizado_ni_la_cobertura_pendiente(): void
    {
        $datos = $this->datos();
        $datos[2]['bloques']['bloque_1']['maximo'] = 615;
        $resultado = DotacionConciliacionGeneral::build(...$datos);
        $this->assertSame(4.0, $resultado['otros_ajustes']);
        $this->assertSame(36.0, $resultado['saldo_neto']);
        $this->assertSame($resultado['saldo_neto'], round(
            $resultado['diferencia_maximo'] + $resultado['cobertura_aaee']
            - $resultado['diferencia_plan'] + $resultado['otros_ajustes'], 2
        ));
    }

    public function test_maximo_pendiente_y_periodos_historicos(): void
    {
        $datos = $this->datos();
        $datos[2]['bloques']['bloque_1']['maximo'] = null;
        $resultado = DotacionConciliacionGeneral::build(...$datos);
        $this->assertNull($resultado['diferencia_maximo']);
        $this->assertSame(36.0, $resultado['saldo_neto']);
        $datos[2]['aplica'] = false;
        $this->assertSame([], DotacionConciliacionGeneral::build(...$datos));
        $datos[2]['aplica'] = true;
        $datos[1] = [];
        $this->assertSame([], DotacionConciliacionGeneral::build(...$datos));
    }

    public function test_sin_totales_precalculados_convierte_en_bloque_por_docente_y_proporcion(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $cache = new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache');
        $cache->setValue(null, []);
        (require database_path('migrations/2026_05_25_183000_create_docente_horas_proporciones_table.php'))->up();
        (require database_path('migrations/2026_07_23_170000_sync_docente_horas_proporciones_cpeip.php'))->up();
        try {
            $datos = $this->datos();
            $datos[0] = [
                ['asignaciones' => [
                    $this->fila('plan_estudio', 17, ['horas_plan_pedagogicas' => 15, 'proporcion_aplicada' => '65/35']),
                    $this->fila('plan_estudio', 17, ['horas_plan_pedagogicas' => 15, 'proporcion_aplicada' => '65/35']),
                ]],
                ['asignaciones' => [
                    $this->fila('plan_estudio', 17, ['horas_plan_pedagogicas' => 14, 'proporcion_aplicada' => '60/40']),
                    $this->fila('plan_estudio', 17, ['horas_plan_pedagogicas' => 14, 'proporcion_aplicada' => '60/40']),
                ]],
            ];
            $resultado = DotacionConciliacionGeneral::build(...$datos);
            $this->assertSame(70.0, $resultado['asignadas']); // 30 aula -> 35 contrato; 28 aula -> 35 contrato.
            $this->assertSame(70.0, $resultado['plan_individual']);
        } finally {
            $cache->setValue(null, []);
        }
    }

    public function test_vista_muestra_las_dos_cuentas_y_explica_cobertura_aaee(): void
    {
        [$docentes, $resumen, $proceso, $sobredotacion] = $this->datos();
        $html = view('admin.dotacion-establecimiento.partials._conciliacion_general', [
            'docentes' => $docentes, 'resumen' => $resumen, 'proceso2027' => $proceso,
            'sobredotacion' => $sobredotacion,
        ])->render();
        $this->assertStringContainsString('643 = 597 asignadas + 10 reservadas + 36 de saldo', $html);
        $this->assertStringContainsString('643 h contratadas − 611 h autorizadas = 32 h', $html);
        $this->assertStringContainsString('36 h de saldo = 32 h de diferencia contractual + 6 h AAEE − 2 h de diferencia del plan', $html);
        $this->assertStringContainsString('Cobertura por asistentes de la educación (AAEE): 6 h', $html);
        $this->assertStringContainsString('no consumen horas del contrato de ningún docente', $html);
        $this->assertStringNotContainsString('otros ajustes de cobertura', $html);
    }

    private function datos(): array
    {
        // Totales sintéticos para reproducir el desfase: el plan individual
        // incluye 363 h convertidas, frente a 361 h en las filas guardadas.
        $docentes = [[
            'titulo' => 'Pedagogía en Educación Básica',
            'horas_contrato_65_35' => 363,
            'asignaciones' => [
                $this->fila('plan_estudio', 361, ['horas_plan_pedagogicas' => 300, 'proporcion_aplicada' => '65/35']),
                $this->fila('pie_colaborativo', 24),
                $this->fila('funcion_directiva', 197),
                $this->fila('otra_funcion', 13),
                $this->fila('reserva_no_normativa', 10),
            ],
        ]];

        return [
            $docentes,
            ['horas_contrato_docentes_aula_general' => 643, 'contrato_plan_general_mas_trabajo_colaborativo_pie' => 385],
            ['aplica' => true, 'bloques' => ['bloque_1' => ['maximo' => 611, 'asignadas_asistentes_obligatorias' => 6]]],
            ['vacantes_por_bloque' => ['plan_estudio' => ['horas_total' => 36]]],
        ];
    }

    private function fila(string $tipo, float $horas, array $extras = []): array
    {
        return array_merge(['tipo_asignacion' => $tipo, 'horas_contrato' => $horas, 'estado' => 'activa'], $extras);
    }
}
