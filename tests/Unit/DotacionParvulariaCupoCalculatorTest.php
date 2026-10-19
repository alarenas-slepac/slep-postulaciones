<?php

namespace Tests\Unit;

use App\Support\DotacionContratoParvulariaCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DotacionParvulariaCupoCalculatorTest extends TestCase
{
    public static function bases(): array
    {
        return [
            'JEC independiente' => [38, 55, true, 0, 56],
            'JEC combinado' => [38, 55, true, 77, 56],
            'NT1 sin JEC' => [32, 35, false, 0, 36],
            'NT2 sin JEC combinado' => [32, 31, false, 77, 32],
        ];
    }

    #[DataProvider('bases')]
    public function test_el_curso_conserva_su_base_y_cada_docente_redondea_su_contrato(
        float $plan, float $base, bool $jec, int $grupo, float $contratoPersonal
    ): void {
        $filas = collect([
            11 => $this->fila(1, $plan / 2, $plan, $base, $jec, $grupo),
            23 => $this->fila(2, $plan / 2, $plan, $base, $jec, $grupo),
        ]);
        $antes = $filas->all();
        $institucional = DotacionContratoParvulariaCalculator::consolidarParaMaximo($filas);
        $individual = DotacionContratoParvulariaCalculator::consolidar($filas);

        $this->assertSame($base, (float) $institucional->sum('horas_contrato'));
        $this->assertSame($contratoPersonal, (float) $individual->sum('horas_contrato'));
        $this->assertSame([11, 23], $institucional->keys()->all());
        $this->assertSame($antes, $filas->all());
        $this->assertSame($base / 2, DotacionContratoParvulariaCalculator::horasParaMaximo($filas->first(), 99));
    }

    public function test_suma_sin_redondear_cada_asignatura_y_no_depende_del_orden(): void
    {
        $filas = collect();
        foreach (range(1, 38) as $i) {
            $filas->push($this->fila($i % 4 + 1, 1));
        }
        foreach ([$filas, $filas->reverse()] as $orden) {
            $resultado = DotacionContratoParvulariaCalculator::consolidarParaMaximo($orden);
            $this->assertEqualsWithDelta(55, $resultado->sum('horas_contrato'), 0.000001);
        }
    }

    public static function excluidas(): array
    {
        return [
            'histórico 2026' => [['anio' => 2026]],
            'asistente' => [['estamento_cobertura' => 'asistente']],
            'PIE' => [['tipo_asignacion' => 'pie_colaborativo']],
            'inactiva' => [['estado' => 'inactiva']],
            'otra proporción' => [['proporcion_aplicada' => '65/35']],
        ];
    }

    #[DataProvider('excluidas')]
    public function test_conserva_historicos_y_horas_que_no_usan_la_base_especial(array $cambios): void
    {
        $fila = array_replace($this->fila(1, 5), $cambios, ['horas_contrato' => 9]);
        $this->assertSame([$fila], DotacionContratoParvulariaCalculator::consolidarParaMaximo(collect([$fila]))->all());
        $this->assertSame(7.0, DotacionContratoParvulariaCalculator::horasParaMaximo($fila, 7));
        $this->assertSame(9, $fila['horas_contrato']);
    }

    private function fila(int $docente, float $aula, float $plan = 38, float $base = 55, bool $jec = true, int $grupo = 0): array
    {
        return [
            'id' => $docente, 'establecimiento_id' => 1, 'establecimiento_curso_id' => 1,
            'dotacion_curso_combinado_id' => $grupo, 'anio' => 2027, 'estado' => 'activa',
            'docente_rut_normalizado' => '9900000'.$docente.'K', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'plan_estudio', 'horas_plan_pedagogicas' => $aula, 'horas_contrato' => 0,
            'proporcion_aplicada' => 'NT '.($jec ? 'Con' : 'Sin').' JEC · base contractual '.$base.' h',
            'fuente_calculo' => 'Reparto proporcional: '.$aula.' / '.$plan.' h de plan × '.$base.' h de contrato.',
        ];
    }
}
