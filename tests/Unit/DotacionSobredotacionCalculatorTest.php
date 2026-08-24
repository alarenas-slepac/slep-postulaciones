<?php

namespace Tests\Unit;

use App\Models\Establecimiento;
use App\Support\DotacionSobredotacionCalculator;
use Tests\TestCase;

class DotacionSobredotacionCalculatorTest extends TestCase
{
    public function test_separa_sobredotacion_sin_asignacion_y_horas_declaradas_ajustables(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Docente planta Aula', 44, 44, 0, 35, 9, 0, true),
            $this->docente('22222222-2', 'Docente contrata Aula', 44, 0, 44, 20, 0, 0, false),
            $this->docente('33333333-3', 'Educadora diferencial', 30, 0, 30, 0, 0, 30, false),
            $this->docente('44444444-4', 'Coordinador PIE', 20, 20, 0, 0, 0, 20, true),
        ], $this->resumen());

        $aula = $resultado['aula'];
        $this->assertSame(88.0, $aula['resumen']['horas_dotacion_total']);
        $this->assertSame(55.0, $aula['resumen']['horas_asignadas_protegidas']);
        $this->assertSame(9.0, $aula['resumen']['horas_declaradas_ajustables']);
        $this->assertSame(24.0, $aula['resumen']['horas_sobredotacion_total']);
        $this->assertSame(0.0, $aula['resumen']['horas_sobredotacion_planta']);
        $this->assertSame(24.0, $aula['resumen']['horas_sobredotacion_contrata']);
        $this->assertSame(33.0, $aula['resumen']['horas_potencial_ajuste']);
        $this->assertSame(16.0, $aula['resumen']['horas_sobredotacion_estructural']);
        $this->assertSame('Docente contrata Aula', $aula['items']->sole()['nombre']);
        $this->assertSame('Docente planta Aula', $aula['ajustes']->sole()['nombre']);

        $pie = $resultado['pie'];
        $this->assertSame(50.0, $pie['resumen']['horas_dotacion_total']);
        $this->assertSame(25.0, $pie['resumen']['horas_necesarias_total']);
        $this->assertSame(25.0, $pie['resumen']['horas_sobredotacion_total']);
        $this->assertSame(0.0, $pie['resumen']['horas_sobredotacion_planta']);
        $this->assertSame(25.0, $pie['resumen']['horas_sobredotacion_contrata']);
        $this->assertSame('Educadora diferencial', $pie['items']->sole()['nombre']);
    }

    public function test_funcion_directiva_normativa_cubre_al_docente_que_la_tiene_asignada(): void
    {
        $director = $this->docente('11111111-1', 'Director reemplazo', 44, 0, 44, 0, 0, 0, false);
        unset($director['horas_asignadas_protegidas'], $director['horas_declaradas_ajustables']);
        $director['asignaciones'] = [[
            'tipo_asignacion' => 'funcion_directiva',
            'dotacion_funcion_id' => null,
            'horas_contrato' => 44,
        ]];

        $resultado = DotacionSobredotacionCalculator::build([$director], [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 0,
            'horas_dotacion_funciones_normativas' => 44,
            'horas_contrato_docentes_aula' => 44,
            'horas_dotacion_funciones_declaradas' => 0,
            'horas_contrato_pie_necesarias' => 0,
            'horas_contrato_docente_pie' => 0,
        ]);

        $this->assertSame(44.0, $resultado['aula']['resumen']['horas_asignadas_protegidas']);
        $this->assertSame(0.0, $resultado['aula']['resumen']['horas_sobredotacion_total']);
        $this->assertSame(0, $resultado['aula']['resumen']['docentes_sobredotacion']);
        $this->assertTrue($resultado['aula']['items']->isEmpty());
    }

    public function test_clasifica_asignaciones_reales_en_protegidas_declaradas_y_contrato_pie(): void
    {
        $docente = $this->docente('11111111-1', 'Docente con funciones', 44, 44, 0, 0, 0, 0, true);
        unset(
            $docente['horas_asignadas_protegidas'],
            $docente['horas_declaradas_ajustables'],
            $docente['horas_contrato_pie']
        );
        $docente['asignaciones'] = [
            ['tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 10],
            ['tipo_asignacion' => 'funcion_directiva', 'dotacion_funcion_id' => null, 'horas_contrato' => 20],
            ['tipo_asignacion' => 'otra_funcion', 'dotacion_funcion_id' => 5, 'horas_contrato' => 4],
            [
                'tipo_asignacion' => 'funcion_tecnico_pedagogica',
                'subtipo_asignacion' => 'pie',
                'asignatura_nombre' => 'Coordinador(a) PIE',
                'horas_contrato' => 6,
            ],
        ];

        $resultado = DotacionSobredotacionCalculator::build([$docente], [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 10,
            'horas_dotacion_funciones_normativas' => 20,
            'horas_contrato_docentes_aula' => 38,
            'horas_dotacion_funciones_declaradas' => 4,
            'horas_contrato_pie_necesarias' => 2,
            'horas_contrato_docente_pie' => 6,
        ]);

        $aula = $resultado['aula']['items']->sole();
        $this->assertSame(30.0, $aula['horas_asignadas_protegidas']);
        $this->assertSame(4.0, $aula['horas_declaradas_ajustables']);
        $this->assertSame(4.0, $aula['horas_sobredotacion_total']);
        $this->assertSame(4.0, $resultado['aula']['ajustes']->sole()['horas_declaradas_ajustables']);
        $this->assertSame(6.0, $resultado['pie']['resumen']['horas_asignadas_registradas']);
        $this->assertSame(4.0, $resultado['pie']['resumen']['horas_sobredotacion_total']);
    }

    public function test_reserva_pie_desde_contrata_y_deja_primero_el_saldo_contrata_sin_asignacion(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Docente mixto', 44, 30, 14, 30, 0, 10, true),
        ], [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 30,
            'horas_dotacion_funciones_normativas' => 0,
            'horas_contrato_docentes_aula' => 34,
            'horas_dotacion_funciones_declaradas' => 0,
            'horas_contrato_pie_necesarias' => 0,
            'horas_contrato_docente_pie' => 10,
        ]);

        $aula = $resultado['aula']['items']->sole();
        $this->assertSame(4.0, $aula['horas_sobredotacion_total']);
        $this->assertSame(0.0, $aula['horas_sobredotacion_planta']);
        $this->assertSame(4.0, $aula['horas_sobredotacion_contrata']);

        $pie = $resultado['pie']['items']->sole();
        $this->assertSame(10.0, $pie['horas_sobredotacion_contrata']);
        $this->assertSame(0.0, $pie['horas_sobredotacion_planta']);
    }

    public function test_distingue_brecha_estructural_sobredotacion_factual_y_potencial_de_ajuste(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Bolsa Planta Aula', 1150, 1150, 0, 802, 348, 0, true),
            $this->docente('22222222-2', 'Bolsa Contrata Aula', 660, 0, 660, 0, 0, 0, false),
            $this->docente('33333333-3', 'Bolsa Contrata PIE', 676, 0, 676, 0, 0, 676, false),
        ], [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 919,
            'horas_dotacion_funciones_normativas' => 231,
            'horas_contrato_docentes_aula' => 1810,
            'horas_dotacion_funciones_declaradas' => 348,
            'horas_contrato_pie_necesarias' => 336,
            'horas_contrato_docente_pie' => 676,
        ]);

        $aula = $resultado['aula']['resumen'];
        $this->assertSame(312.0, $aula['horas_sobredotacion_estructural']);
        $this->assertSame(660.0, $aula['horas_sobredotacion_total']);
        $this->assertSame(348.0, $aula['horas_declaradas_ajustables']);
        $this->assertSame(1008.0, $aula['horas_potencial_ajuste']);
        $this->assertSame(340.0, $resultado['pie']['resumen']['horas_sobredotacion_total']);
    }

    public function test_vista_muestra_nominas_separadas_de_sobredotacion_y_posible_ajuste(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Docente planta Aula', 44, 44, 0, 35, 9, 0, true),
            $this->docente('22222222-2', 'Docente contrata Aula', 44, 0, 44, 20, 0, 0, false),
            $this->docente('33333333-3', 'Educadora diferencial', 30, 0, 30, 0, 0, 30, false),
            $this->docente('44444444-4', 'Coordinador PIE', 20, 20, 0, 0, 0, 20, true),
        ], $this->resumen());
        $establecimiento = new Establecimiento(['nombre_establecimiento' => 'Prueba']);
        $establecimiento->id = 1;

        $htmlAula = view('admin.dotacion-establecimiento.partials._sobredotacion', [
            'sobredotacion' => $resultado,
            'sobredotacionTipo' => 'aula',
            'establecimiento' => $establecimiento,
            'anio' => 2026,
        ])->render();
        $this->assertStringContainsString('Horas contrato Aula', $htmlAula);
        $this->assertStringContainsString('Sobredotaci', $htmlAula);
        $this->assertStringContainsString('Horas de posible ajuste', $htmlAula);
        $this->assertStringContainsString('Asignaciones protegidas', $htmlAula);
        $this->assertStringContainsString('Docente contrata Aula', $htmlAula);
        $this->assertStringContainsString('Docente planta Aula', $htmlAula);
        $this->assertStringNotContainsString('Necesidad cubierta', $htmlAula);

        $htmlPie = view('admin.dotacion-establecimiento.partials._sobredotacion', [
            'sobredotacion' => $resultado,
            'sobredotacionTipo' => 'pie',
            'establecimiento' => $establecimiento,
            'anio' => 2026,
        ])->render();
        $this->assertStringContainsString('Horas contrato docente PIE', $htmlPie);
        $this->assertStringContainsString('Necesidad cubierta', $htmlPie);
        $this->assertStringContainsString('Educadora diferencial', $htmlPie);
        $this->assertStringNotContainsString('Horas de posible ajuste', $htmlPie);
    }

    /** @return array<string, float> */
    private function resumen(): array
    {
        return [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 50.0,
            'horas_dotacion_funciones_normativas' => 10.0,
            'horas_contrato_docentes_aula' => 88.0,
            'horas_dotacion_funciones_declaradas' => 12.0,
            'horas_contrato_pie_necesarias' => 25.0,
            'horas_contrato_docente_pie' => 50.0,
        ];
    }

    private function docente(
        string $rut,
        string $nombre,
        float $contrato,
        float $planta,
        float $contrata,
        float $protegidas,
        float $declaradas,
        float $contratoPie,
        bool $titular
    ): array {
        return [
            'rut' => $rut,
            'nombre' => $nombre,
            'funcion' => $contratoPie > 0 ? 'PIE' : 'Docente Aula',
            'tipo_contrato' => $titular ? 'PLANTA' : 'CONTRATA',
            'es_titular' => $titular,
            'horas_contrato' => $contrato,
            'horas_planta' => $planta,
            'horas_contrata' => $contrata,
            'horas_asignadas_protegidas' => $protegidas,
            'horas_declaradas_ajustables' => $declaradas,
            'horas_contrato_pie' => $contratoPie,
        ];
    }
}
