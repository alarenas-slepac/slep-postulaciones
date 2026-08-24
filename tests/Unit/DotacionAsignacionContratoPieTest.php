<?php

namespace Tests\Unit;

use App\Models\DotacionDocenteAsignacion;
use App\Support\DotacionAsignacionCalculator;
use ReflectionMethod;
use Tests\TestCase;

class DotacionAsignacionContratoPieTest extends TestCase
{
    public function test_suma_coordinacion_y_bolsa_pie_asignadas_solo_a_docentes(): void
    {
        $asignaciones = collect([
            $this->asignacion('funcion_tecnico_pedagogica', 'pie', 'Coordinador(a) PIE', 10),
            $this->asignacion('funcion_tecnico_pedagogica', 'tecnico_pedagogica', 'Coordinación PIE', 4),
            $this->asignacion('pie_educadora_diferencial', 'bolsa_total', 'Educadoras diferenciales PIE', 22),
            $this->asignacion('pie_colaborativo', 'trabajo_colaborativo', 'Trabajo colaborativo PIE', 3),
            $this->asignacion('funcion_tecnico_pedagogica', 'tecnico_pedagogica', 'Orientación', 5),
            $this->asignacion('pie_educadora_diferencial', 'bolsa_total', 'Educadoras diferenciales PIE', 8, 'asistente'),
        ]);

        $method = new ReflectionMethod(DotacionAsignacionCalculator::class, 'resumenContratoDocentePie');
        $resultado = $method->invoke(null, $asignaciones);

        $this->assertSame([
            'coordinacion_pie' => 14.0,
            'educadoras_diferenciales' => 22.0,
            'total' => 36.0,
        ], $resultado);
    }

    private function asignacion(
        string $tipo,
        string $subtipo,
        string $nombre,
        float $horas,
        string $estamento = 'docente'
    ): DotacionDocenteAsignacion {
        return new DotacionDocenteAsignacion([
            'tipo_asignacion' => $tipo,
            'subtipo_asignacion' => $subtipo,
            'asignatura_nombre' => $nombre,
            'horas_contrato' => $horas,
            'estamento_cobertura' => $estamento,
        ]);
    }
}
