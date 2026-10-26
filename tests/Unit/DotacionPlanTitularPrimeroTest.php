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

    public function test_completa_titular_insuficiente_con_contrata_del_mismo_docente(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('99000001K', 33, 2, 32),
            $this->docente('99000002K', 0, 10, 0),
        ]));
        $seleccionado = $docentes->first();
        $this->assertSame('titular', DotacionPlanTitularPrimero::fase($docentes));
        $this->assertSame(['99000001K'], DotacionPlanTitularPrimero::opciones($docentes)->pluck('rut_normalizado')->all());
        $this->assertSame(1.0, DotacionPlanTitularPrimero::disponibles($seleccionado, 'titular'));
        $this->assertSame(3.0, DotacionPlanTitularPrimero::saldoAsignable($seleccionado, 'titular'));
        DotacionPlanTitularPrimero::validar($docentes, $seleccionado, 2, false);
        DotacionPlanTitularPrimero::validar($docentes, $seleccionado, 3, false);
        $this->assertInvalidAssignment($docentes, $seleccionado, 3.01);
        $this->assertInvalidAssignment($docentes, $docentes->last(), 2);
        $this->assertInvalidAssignment($docentes, null, 2, true);
    }

    public function test_capacidad_mixta_respeta_saldo_total_y_no_suma_dos_veces_reservas(): void
    {
        $docente = [
            'horas_disponibles' => 2.0, 'horas_titulares_disponibles' => 1.0,
            'horas_contrata_disponibles' => 5.0,
        ];
        $this->assertSame(2.0, DotacionPlanTitularPrimero::saldoAsignable($docente, 'titular'));
        $this->assertSame(2.0, DotacionPlanTitularPrimero::saldoAsignable($docente, 'contrata'));
        $docente['horas_disponibles'] = 0;
        $this->assertSame(0.0, DotacionPlanTitularPrimero::saldoAsignable($docente, 'titular'));
    }

    public function test_comparacion_de_prelacion_considera_la_capacidad_mixta_sin_alterar_los_saldos(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('99000001K', 33, 2, 32) + ['fecha_antiguedad' => '2000-01-01'],
            $this->docente('99000002K', 44, 0, 0) + ['fecha_antiguedad' => '2020-01-01'],
        ]));
        $original = $docentes->all();
        $candidatos = DotacionPlanTitularPrimero::candidatosParaPrelacion($docentes);
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($candidatos, $candidatos->last(), 2));
        $this->assertFalse(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($candidatos, $candidatos->last(), 4));
        $this->assertSame($original, $docentes->all());
    }

    public function test_editar_restituye_titular_y_contrata_y_conserva_su_combinacion(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('99000001K', 33, 2, 34),
        ]));
        $current = new DotacionDocenteAsignacion([
            'docente_rut_normalizado' => '99000001K', 'estamento_cobertura' => 'docente', 'horas_contrato' => 2,
        ]);
        $elegibles = DotacionPlanTitularPrimero::elegibles($docentes, ['99000001K'], [], $current);
        $this->assertSame(1.0, $elegibles->first()['horas_titulares_disponibles']);
        $this->assertSame(2.0, $elegibles->first()['horas_contrata_disponibles']);
        $this->assertSame(3.0, DotacionPlanTitularPrimero::saldoAsignable($elegibles->first(), 'titular'));
        DotacionPlanTitularPrimero::validar($elegibles, $elegibles->first(), 3, false);
        $this->assertInvalidAssignment($elegibles, $elegibles->first(), 4);
    }

    public function test_contrata_de_docente_protegido_sin_saldo_titular_no_bloquea_fase_titular(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('111111111', 8, 36, 8) + ['exclusion_docente' => ['motivo' => 'fuero_maternal']],
            $this->docente('222222222', 44, 0, 0),
        ]));
        $original = $docentes->all();
        $candidatos = DotacionPlanTitularPrimero::candidatosParaPrelacion($docentes);
        $this->assertSame(['222222222'], $candidatos->pluck('rut_normalizado')->all());
        $this->assertFalse(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($candidatos, $candidatos->first(), 2));
        $this->assertSame($original, $docentes->all());
    }

    public function test_fase_contrata_compara_saldo_contrata_y_no_saldo_titular_agotado(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('111111111', 8, 6, 8) + ['fecha_antiguedad' => '2000-01-01'],
            $this->docente('222222222', 0, 6, 0),
        ]));
        $this->assertSame('contrata', DotacionPlanTitularPrimero::fase($docentes));
        $candidatos = DotacionPlanTitularPrimero::candidatosParaPrelacion($docentes);
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($candidatos, $candidatos->last(), 2));
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
