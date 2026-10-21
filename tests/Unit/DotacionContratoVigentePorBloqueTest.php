<?php

namespace Tests\Unit;

use App\Models\Establecimiento;
use App\Support\DotacionContratoVigentePorBloque;
use Tests\TestCase;

class DotacionContratoVigentePorBloqueTest extends TestCase
{
    private function docente(string $rut, string $titulo, float $horas, array $asignaciones = [], int $anio = 2026): array
    {
        return [
            'rut_normalizado' => $rut, 'titulo' => $titulo, 'estamento_cobertura' => 'docente',
            'horas_contrato' => $horas, 'asignaciones' => collect($asignaciones), 'anio' => $anio, 'mes' => 8,
        ];
    }

    public function test_distribuye_contratos_vigentes_completos_sin_restar_reservas(): void
    {
        $docentes = collect([
            $this->docente('111111111', 'Pedagogía en Educación Básica', 44, [['tipo_asignacion' => 'reserva_no_normativa', 'horas_contrato' => 10]]),
            $this->docente('222222222', 'Pedagogía en Educación de Párvulos', 43),
            $this->docente('333333333', 'Pedagogía en Educación Diferencial', 44),
        ]);
        $resultado = (new DotacionContratoVigentePorBloque)->desdeDocentes($docentes, collect());
        $this->assertSame(44.0, $resultado['contrato_vigente_bloque_1']);
        $this->assertSame(43.0, $resultado['contrato_vigente_bloque_2']);
        $this->assertSame(44.0, $resultado['contrato_vigente_bloque_3']);
        $this->assertSame('08/2026', $resultado['periodo_contractual']);
    }

    public function test_reutiliza_traslados_normativos_de_parvularia_y_diferencial_sin_duplicar_horas(): void
    {
        $asignacionParv = ['docente_rut_normalizado' => '222222222', 'tipo_asignacion' => 'funcion_tecnico_pedagogica', 'subtipo_asignacion' => 'tecnico_pedagogica', 'dotacion_funcion_id' => null, 'horas_contrato' => 3];
        $asignacionDif = ['docente_rut_normalizado' => '333333333', 'tipo_asignacion' => 'funcion_directiva', 'dotacion_funcion_id' => null, 'horas_contrato' => 6];
        $docentes = collect([
            $this->docente('111111111', 'Pedagogía en Educación Básica', 44),
            $this->docente('222222222', 'Pedagogía en Educación de Párvulos', 43, [$asignacionParv]),
            $this->docente('333333333', 'Pedagogía en Educación Diferencial', 44, [$asignacionDif]),
        ]);
        $resultado = (new DotacionContratoVigentePorBloque)->desdeDocentes($docentes, collect([$asignacionParv, $asignacionDif]));
        $this->assertSame(53.0, $resultado['contrato_vigente_bloque_1']);
        $this->assertSame(40.0, $resultado['contrato_vigente_bloque_2']);
        $this->assertSame(38.0, $resultado['contrato_vigente_bloque_3']);
        $this->assertSame(131.0, array_sum(array_filter($resultado, 'is_float')));
    }

    public function test_en_escuela_especial_diferencial_pertenece_a_plan_general(): void
    {
        $docentes = collect([$this->docente('333333333', 'Pedagogía en Educación Diferencial', 44, [], 2027)]);
        $resultado = (new DotacionContratoVigentePorBloque)->desdeDocentes($docentes, collect(), true);
        $this->assertSame(44.0, $resultado['contrato_vigente_bloque_1']);
        $this->assertSame(0.0, $resultado['contrato_vigente_bloque_3']);
        $this->assertSame('08/2027', $resultado['periodo_contractual']);
    }

    public function test_sin_padron_no_inventa_contratos_ni_periodo(): void
    {
        $resultado = (new DotacionContratoVigentePorBloque)->paraEstablecimiento(new Establecimiento(['id' => 1]), 2027);
        $this->assertSame(0.0, $resultado['contrato_vigente_bloque_1']);
        $this->assertSame(0.0, $resultado['contrato_vigente_bloque_2']);
        $this->assertSame(0.0, $resultado['contrato_vigente_bloque_3']);
        $this->assertSame('Sin contratos docentes vigentes', $resultado['periodo_contractual']);
    }

    public function test_cupos_suman_una_vez_en_su_bloque_explicito_aunque_conserven_otro_titulo(): void
    {
        $virtual = fn (int $id, string $bloque, float $horas): array => [
            ...$this->docente('VACANTE_'.$id, 'Pedagogía en Educación Diferencial', $horas),
            'cupo_contrata_id' => $id, 'cupo_bloque' => $bloque, 'mes' => 0,
        ];
        $docentes = collect([
            $this->docente('99000001K', 'Pedagogía en Educación Básica', 44),
            $virtual(1, 'plan_estudio', 6), $virtual(2, 'parvularia', 12), $virtual(3, 'pie', 38),
        ]);
        $asignaciones = collect([['docente_rut_normalizado' => 'VACANTE_3', 'tipo_asignacion' => 'pie_educadora_diferencial', 'horas_contrato' => 10]]);
        foreach ([false, true] as $especial) {
            $resultado = (new DotacionContratoVigentePorBloque)->desdeDocentes($docentes, $asignaciones, $especial);
            $this->assertSame(50.0, $resultado['contrato_vigente_bloque_1']);
            $this->assertSame(12.0, $resultado['contrato_vigente_bloque_2']);
            $this->assertSame(38.0, $resultado['contrato_vigente_bloque_3']);
            $this->assertSame(100.0, array_sum(array_filter($resultado, 'is_float')));
            $this->assertSame('08/2026 · Cupos por contratar 2026', $resultado['periodo_contractual']);
        }
    }
}
