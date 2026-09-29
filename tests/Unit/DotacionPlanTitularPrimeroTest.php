<?php

namespace Tests\Unit;

use App\Models\DotacionDocenteAsignacion;
use App\Support\DotacionPlanTitularPrimero;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DotacionPlanTitularPrimeroTest extends TestCase
{
    public function test_muestra_primero_solos_los_titulares_asociados_y_despues_las_horas_a_contrata(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('111111111', 10, 0, 6),
            $this->docente('222222222', 0, 12, 0),
            $this->docente('333333333', 10, 0, 0),
        ]));
        $elegibles = DotacionPlanTitularPrimero::elegibles($docentes, ['111111111', '222222222'], []);

        $this->assertSame('titular', DotacionPlanTitularPrimero::fase($elegibles));
        $this->assertSame(['111111111'], DotacionPlanTitularPrimero::opciones($elegibles)->pluck('rut_normalizado')->all());
        $this->assertInvalidAssignment($elegibles, $elegibles->firstWhere('rut_normalizado', '222222222'), 2);
        $this->assertInvalidAssignment($elegibles, $elegibles->firstWhere('rut_normalizado', '111111111'), 5);
        DotacionPlanTitularPrimero::validar($elegibles, $elegibles->firstWhere('rut_normalizado', '111111111'), 4, false);

        $agotados = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('111111111', 10, 5, 10),
            $this->docente('222222222', 0, 12, 0),
        ]));
        $elegiblesAgotados = DotacionPlanTitularPrimero::elegibles($agotados, ['111111111', '222222222'], []);
        $this->assertSame('contrata', DotacionPlanTitularPrimero::fase($elegiblesAgotados));
        $this->assertSame(['111111111', '222222222'], DotacionPlanTitularPrimero::opciones($elegiblesAgotados)->pluck('rut_normalizado')->all());
        DotacionPlanTitularPrimero::validar($elegiblesAgotados, $elegiblesAgotados->firstWhere('rut_normalizado', '222222222'), 8, false);
    }

    public function test_bloquea_cobertura_aaee_mientras_haya_saldo_titular_y_restituye_horas_al_editar(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('111111111', 10, 5, 10),
        ]));
        $current = new DotacionDocenteAsignacion();
        $current->docente_rut_normalizado = '111111111';
        $current->estamento_cobertura = 'docente';
        $current->horas_contrato = 2;
        $elegibles = DotacionPlanTitularPrimero::elegibles($docentes, ['111111111'], [], $current);

        $this->assertSame('titular', DotacionPlanTitularPrimero::fase($elegibles));
        $this->assertSame(2.0, DotacionPlanTitularPrimero::disponibles($elegibles->first(), 'titular'));
        $this->assertInvalidAssignment($elegibles, null, 1, true);
    }

    public function test_saldos_titulares_menores_a_una_hora_no_bloquean_la_contrata(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('111111111', 10, 0, 9.63),
            $this->docente('222222222', 0, 10, 0),
        ]));
        $elegibles = DotacionPlanTitularPrimero::elegibles($docentes, ['111111111', '222222222'], []);

        $this->assertSame('contrata', DotacionPlanTitularPrimero::fase($elegibles));
        $this->assertSame(0.37, DotacionPlanTitularPrimero::disponibles($elegibles->first(), 'titular'));
        $this->assertSame(['222222222'], DotacionPlanTitularPrimero::opciones($elegibles)->pluck('rut_normalizado')->all());
        DotacionPlanTitularPrimero::validar($elegibles, $elegibles->firstWhere('rut_normalizado', '222222222'), 2, false);
        DotacionPlanTitularPrimero::validar($elegibles, null, 2, true);
        $this->assertInvalidAssignment($elegibles, $elegibles->firstWhere('rut_normalizado', '111111111'), 0.34);
    }

    public function test_una_hora_exacta_se_considera_y_saldos_a_contrata_menores_a_una_hora_se_omiten(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('111111111', 10, 0, 9),
            $this->docente('222222222', 0, 0.99, 0),
            $this->docente('333333333', 0, 1, 0),
        ]));
        $elegibles = DotacionPlanTitularPrimero::elegibles($docentes, ['111111111', '222222222', '333333333'], []);

        $this->assertSame('titular', DotacionPlanTitularPrimero::fase($elegibles));
        $this->assertSame(['111111111'], DotacionPlanTitularPrimero::opciones($elegibles)->pluck('rut_normalizado')->all());
        DotacionPlanTitularPrimero::validar($elegibles, $elegibles->firstWhere('rut_normalizado', '111111111'), 1, false);

        $sinTitular = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('111111111', 10, 0, 9.01),
            $this->docente('222222222', 0, 0.99, 0),
            $this->docente('333333333', 0, 1, 0),
        ]));
        $elegiblesSinTitular = DotacionPlanTitularPrimero::elegibles($sinTitular, ['111111111', '222222222', '333333333'], []);
        $this->assertSame('contrata', DotacionPlanTitularPrimero::fase($elegiblesSinTitular));
        $this->assertSame(['333333333'], DotacionPlanTitularPrimero::opciones($elegiblesSinTitular)->pluck('rut_normalizado')->all());
        $this->assertInvalidAssignment($elegiblesSinTitular, $elegiblesSinTitular->firstWhere('rut_normalizado', '222222222'), 0.5);
    }

    private function docente(string $rut, float $planta, float $contrata, float $asignadas): array
    {
        return [
            'rut' => $rut,
            'rut_normalizado' => $rut,
            'nombre' => $rut,
            'titulo' => 'Pedagogía en Educación Básica',
            'horas_planta' => $planta,
            'horas_contrata' => $contrata,
            'horas_contrato' => $planta + $contrata,
            'horas_asignadas_total' => $asignadas,
        ];
    }

    private function assertInvalidAssignment($docentes, ?array $seleccionado, float $horas, bool $asistente = false): void
    {
        try {
            DotacionPlanTitularPrimero::validar($docentes, $seleccionado, $horas, $asistente);
            $this->fail('La asignación debía rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }
}
