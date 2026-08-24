<?php

namespace Tests\Unit;

use App\Support\DotacionSobredotacionCalculator;
use Tests\TestCase;

class DotacionSobredotacionCalculatorTest extends TestCase
{
    public function test_detecta_sobredotacion_y_prioriza_horas_planta_sobre_contrata(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Docente mixto', 44, 30, 14, 35, true),
            $this->docente('22222222-2', 'Docente planta', 30, 30, 0, 20, true),
            $this->docente('33333333-3', 'Docente con sobrecarga', 22, 0, 22, 25, false),
            $this->docente('44444444-4', 'Docente contrata', 10, 0, 0, 4, false),
        ]);

        $this->assertSame([
            'docentes_analizados' => 4,
            'docentes_sobredotacion' => 3,
            'horas_contrato_total' => 106.0,
            'horas_asignadas_total' => 84.0,
            'horas_sobredotacion_total' => 25.0,
            'horas_sobredotacion_planta' => 10.0,
            'horas_sobredotacion_contrata' => 15.0,
        ], $resultado['resumen']);

        $mixto = $resultado['items']->firstWhere('rut', '11111111-1');
        $this->assertSame(30.0, $mixto['horas_asignadas_planta']);
        $this->assertSame(5.0, $mixto['horas_asignadas_contrata']);
        $this->assertSame(0.0, $mixto['horas_sobredotacion_planta']);
        $this->assertSame(9.0, $mixto['horas_sobredotacion_contrata']);

        $this->assertNull($resultado['items']->firstWhere('rut', '33333333-3'));

        $html = view('admin.dotacion-establecimiento.partials._sobredotacion', [
            'sobredotacion' => $resultado,
        ])->render();
        $this->assertStringContainsString('Detalle sobredotación', $html);
        $this->assertStringContainsString('Docente mixto', $html);
        $this->assertStringContainsString('Sobredotación Planta', $html);
        $this->assertStringContainsString('Sobredotación Contrata', $html);
        $this->assertStringNotContainsString('Docente con sobrecarga', $html);
    }

    public function test_reduccion_del_contrato_considerado_preserva_primero_planta(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([
            $this->docente('11111111-1', 'Docente con exclusión', 34, 30, 14, 30, true),
        ]);

        $docente = $resultado['items']->first();
        $this->assertSame(30.0, $docente['horas_contrato_planta']);
        $this->assertSame(4.0, $docente['horas_contrato_contrata']);
        $this->assertSame(0.0, $docente['horas_sobredotacion_planta']);
        $this->assertSame(4.0, $docente['horas_sobredotacion_contrata']);
    }

    private function docente(
        string $rut,
        string $nombre,
        float $contrato,
        float $planta,
        float $contrata,
        float $asignadas,
        bool $titular
    ): array {
        return [
            'rut' => $rut,
            'nombre' => $nombre,
            'funcion' => 'Docente aula',
            'tipo_contrato' => $titular ? 'PLANTA' : 'CONTRATA',
            'es_titular' => $titular,
            'horas_contrato' => $contrato,
            'horas_planta' => $planta,
            'horas_contrata' => $contrata,
            'horas_asignadas_total' => $asignadas,
        ];
    }
}
