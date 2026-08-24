<?php

namespace Tests\Unit;

use App\Models\Establecimiento;
use App\Support\DotacionSobredotacionCalculator;
use Tests\TestCase;

class DotacionSobredotacionCalculatorTest extends TestCase
{
    public function test_separa_aula_y_pie_y_concilia_ambas_brechas_del_resumen(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Docente planta Aula', 44, 44, 0, 35, 0, true),
            $this->docente('22222222-2', 'Docente contrata Aula', 44, 0, 44, 20, 0, false),
            $this->docente('33333333-3', 'Educadora diferencial', 30, 0, 30, 0, 30, false),
            $this->docente('44444444-4', 'Coordinador PIE', 20, 20, 0, 0, 20, true),
        ], $this->resumen());

        $this->assertSame(88.0, $resultado['aula']['resumen']['horas_dotacion_total']);
        $this->assertSame(72.0, $resultado['aula']['resumen']['horas_necesarias_total']);
        $this->assertSame(16.0, $resultado['aula']['resumen']['horas_sobredotacion_total']);
        $this->assertSame(0.0, $resultado['aula']['resumen']['horas_sobredotacion_planta']);
        $this->assertSame(16.0, $resultado['aula']['resumen']['horas_sobredotacion_contrata']);
        $this->assertSame('Docente contrata Aula', $resultado['aula']['items']->first()['nombre']);

        $this->assertSame(50.0, $resultado['pie']['resumen']['horas_dotacion_total']);
        $this->assertSame(25.0, $resultado['pie']['resumen']['horas_necesarias_total']);
        $this->assertSame(25.0, $resultado['pie']['resumen']['horas_sobredotacion_total']);
        $this->assertSame(0.0, $resultado['pie']['resumen']['horas_sobredotacion_planta']);
        $this->assertSame(25.0, $resultado['pie']['resumen']['horas_sobredotacion_contrata']);
        $this->assertSame('Educadora diferencial', $resultado['pie']['items']->first()['nombre']);
    }

    public function test_reserva_pie_desde_contrata_y_prioriza_planta_al_cubrir_necesidades(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Docente mixto', 44, 30, 14, 30, 10, true),
        ], [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 30,
            'horas_dotacion_funciones_normativas' => 0,
            'horas_contrato_docentes_aula' => 34,
            'horas_dotacion_funciones_declaradas' => 0,
            'horas_contrato_pie_necesarias' => 0,
            'horas_contrato_docente_pie' => 10,
        ]);

        $aula = $resultado['aula']['items']->first();
        $this->assertSame(4.0, $aula['horas_sobredotacion_total']);
        $this->assertSame(0.0, $aula['horas_sobredotacion_planta']);
        $this->assertSame(4.0, $aula['horas_sobredotacion_contrata']);

        $pie = $resultado['pie']['items']->first();
        $this->assertSame(10.0, $pie['horas_sobredotacion_contrata']);
        $this->assertSame(0.0, $pie['horas_sobredotacion_planta']);
    }

    public function test_vista_muestra_subpestanas_formulas_y_nominas_separadas(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Docente planta Aula', 44, 44, 0, 35, 0, true),
            $this->docente('22222222-2', 'Docente contrata Aula', 44, 0, 44, 20, 0, false),
            $this->docente('33333333-3', 'Educadora diferencial', 30, 0, 30, 0, 30, false),
            $this->docente('44444444-4', 'Coordinador PIE', 20, 20, 0, 0, 20, true),
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
        $this->assertStringContainsString('Horas contrato docente PIE', $htmlAula);
        $this->assertStringContainsString('Dotación General', $htmlAula);
        $this->assertStringContainsString('bloque normativo + bloque declarado', $htmlAula);
        $this->assertStringNotContainsString('Contrato Aula + bloque declarado', $htmlAula);
        $this->assertStringContainsString('Docente contrata Aula', $htmlAula);
        $this->assertStringNotContainsString('Educadora diferencial</div>', $htmlAula);

        $htmlPie = view('admin.dotacion-establecimiento.partials._sobredotacion', [
            'sobredotacion' => $resultado,
            'sobredotacionTipo' => 'pie',
            'establecimiento' => $establecimiento,
            'anio' => 2026,
        ])->render();
        $this->assertStringContainsString('Dotación PIE', $htmlPie);
        $this->assertStringContainsString('Educadora diferencial', $htmlPie);
        $this->assertStringNotContainsString('Docente contrata Aula</div>', $htmlPie);
    }

    public function test_clasifica_asignaciones_reales_en_general_declaradas_y_contrato_pie(): void
    {
        $docente = $this->docente('11111111-1', 'Docente con funciones', 44, 44, 0, 0, 0, true);
        unset(
            $docente['horas_asignadas_general'],
            $docente['horas_contrato_pie']
        );
        $docente['asignaciones'] = [
            ['tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 10],
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
            'horas_dotacion_funciones_normativas' => 0,
            'horas_contrato_docentes_aula' => 38,
            'horas_dotacion_funciones_declaradas' => 4,
            'horas_contrato_pie_necesarias' => 2,
            'horas_contrato_docente_pie' => 6,
        ]);

        $this->assertSame(14.0, $resultado['aula']['resumen']['horas_asignadas_registradas']);
        $this->assertSame(38.0, $resultado['aula']['resumen']['horas_dotacion_total']);
        $this->assertSame(6.0, $resultado['pie']['resumen']['horas_asignadas_registradas']);
        $this->assertSame(4.0, $resultado['pie']['resumen']['horas_sobredotacion_total']);
    }

    public function test_bloque_declarado_aumenta_necesidad_sin_aumentar_contrato_individual(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Docente planta', 44, 44, 0, 10, 0, true),
        ], [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 10,
            'horas_dotacion_funciones_normativas' => 0,
            'horas_contrato_docentes_aula' => 44,
            'horas_dotacion_funciones_declaradas' => 12,
            'horas_contrato_pie_necesarias' => 0,
            'horas_contrato_docente_pie' => 0,
        ]);

        $docente = $resultado['aula']['items']->firstWhere('rut', '11111111-1');

        $this->assertSame(44.0, $docente['horas_contrato_categoria']);
        $this->assertSame(44.0, $docente['horas_dotacion_total']);
        $this->assertSame(22.0, $resultado['aula']['resumen']['horas_necesarias_total']);
        $this->assertSame(22.0, $docente['horas_sobredotacion_total']);
        $this->assertFalse($resultado['aula']['resumen']['tiene_ajuste_no_asociado']);
    }

    public function test_reproduce_los_totales_del_ejemplo_reportado(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Bolsa Planta Aula', 1150, 1150, 0, 1150, 0, true),
            $this->docente('22222222-2', 'Bolsa Contrata Aula', 660, 0, 660, 0, 0, false),
            $this->docente('33333333-3', 'Bolsa Contrata PIE', 676, 0, 676, 0, 676, false),
        ], [
            'contrato_plan_mas_trabajo_colaborativo_pie' => 919,
            'horas_dotacion_funciones_normativas' => 231,
            'horas_contrato_docentes_aula' => 1810,
            'horas_dotacion_funciones_declaradas' => 348,
            'horas_contrato_pie_necesarias' => 336,
            'horas_contrato_docente_pie' => 676,
        ]);

        $this->assertSame(1810.0, $resultado['aula']['resumen']['horas_dotacion_total']);
        $this->assertSame(1498.0, $resultado['aula']['resumen']['horas_necesarias_total']);
        $this->assertSame(312.0, $resultado['aula']['resumen']['horas_sobredotacion_total']);
        $this->assertTrue($resultado['aula']['items']->every(
            fn (array $item) => $item['horas_dotacion_total'] <= $item['horas_contrato_categoria']
        ));
        $this->assertSame(676.0, $resultado['pie']['resumen']['horas_dotacion_total']);
        $this->assertSame(336.0, $resultado['pie']['resumen']['horas_necesarias_total']);
        $this->assertSame(340.0, $resultado['pie']['resumen']['horas_sobredotacion_total']);
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
        float $asignadasGeneral,
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
            'horas_asignadas_general' => $asignadasGeneral,
            'horas_contrato_pie' => $contratoPie,
        ];
    }
}
