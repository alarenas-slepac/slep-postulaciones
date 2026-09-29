<?php

namespace Tests\Unit;

use App\Models\DotacionDocenteAsignacion;
use App\Support\DotacionAsignacionPorCursoBloque;
use PHPUnit\Framework\TestCase;

class DotacionAsignacionPorCursoBloqueTest extends TestCase
{
    public function test_selecciona_solo_asignaciones_del_curso_y_bloque_incluido_acompanamiento(): void
    {
        $plan = $this->asignacion(1);
        $acompanamiento = $this->asignacion(2);
        $otroBloque = $this->asignacion(3);
        $otroCurso = $this->asignacion(4);
        $automatica = $this->asignacion(5, true);
        $necesidades = [
            'plan_estudio' => [
                ['curso_label' => 'NT1 A', 'bloque' => 'Libre disposición', 'asignaciones' => [$plan, $automatica], 'acompanamientos' => [$acompanamiento]],
                ['curso_label' => 'NT1 A', 'bloque' => 'Libre disposición', 'asignaciones' => [$plan]],
                ['curso_label' => 'NT1 A', 'bloque' => 'Plan común', 'asignaciones' => [$otroBloque]],
                ['curso_label' => 'NT2 A', 'bloque' => 'Libre disposición', 'asignaciones' => [$otroCurso]],
            ],
            'pie_colaborativo' => [
                ['curso_label' => 'NT1 A', 'asignaciones' => [$otroCurso]],
            ],
        ];

        $scope = DotacionAsignacionPorCursoBloque::necesidades($necesidades, 'plan_estudio', 'NT1 A', 'Libre disposición');
        $this->assertCount(2, $scope);
        $this->assertSame([1, 2], DotacionAsignacionPorCursoBloque::asignaciones($scope)->pluck('id')->all());

        $cursoCompleto = DotacionAsignacionPorCursoBloque::necesidades($necesidades, 'plan_estudio', 'NT1 A', null);
        $this->assertSame([1, 2, 3], DotacionAsignacionPorCursoBloque::asignaciones($cursoCompleto)->pluck('id')->all());

        $scopePie = DotacionAsignacionPorCursoBloque::necesidades($necesidades, 'pie_colaborativo', 'NT1 A', null);
        $this->assertSame([4], DotacionAsignacionPorCursoBloque::asignaciones($scopePie)->pluck('id')->all());
    }

    private function asignacion(int $id, bool $automatica = false): DotacionDocenteAsignacion
    {
        $asignacion = new DotacionDocenteAsignacion();
        $asignacion->id = $id;
        if ($automatica) {
            $asignacion->asignacion_automatica = true;
        }

        return $asignacion;
    }
}
