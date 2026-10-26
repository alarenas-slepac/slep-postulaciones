<?php

namespace Tests\Unit;

use App\Models\DotacionDocenteAsignacion;
use App\Services\Padron\PadronDatosExcel;
use App\Support\DotacionPlanTitularPrimero;
use App\Support\DotacionProceso2027Calculator;
use App\Support\TramoDocente;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class NoRegidoLey20903PrelacionTest extends IsolatedSecurityTestCase
{
    private function docente(string $rut, float $planta, float $contrata, ?string $tramo, ?string $fecha = '2010-01-01'): array
    {
        return ['rut' => $rut, 'rut_normalizado' => $rut, 'nombre' => 'Persona sintética '.$rut,
            'horas_planta' => $planta, 'horas_contrata' => $contrata, 'horas_contrato' => $planta + $contrata,
            'horas_asignadas_total' => 0, 'tramo' => $tramo, 'fecha_antiguedad' => $fecha];
    }

    public static function variantes(): array
    {
        return [
            ['No Regido Ley 20903', true], [' no  regido ley 20.903 ', true],
            ["NO\tREGIDO LEY 20903", true], ['No_Regido_Ley_20.903', true],
            ['No Regido Ley 20904', false], ['No Regido Ley 20903 pendiente', false],
            ['Avanzado', false], ['Sin tramo', false], [null, false],
        ];
    }

    #[DataProvider('variantes')]
    public function test_reconoce_solo_la_categoria_correcta(?string $valor, bool $esperado): void
    {
        $this->assertSame($esperado, TramoDocente::esNoRegidoLey20903($valor));
    }

    public static function contratos(): array { return [[20, 0], [0, 20], [8, 12]]; }

    #[DataProvider('contratos')]
    public function test_el_grupo_adelanta_a_avanzado_y_expertos_en_cualquier_calidad(float $planta, float $contrata): void
    {
        $noRegido = $this->docente('99000001K', $planta, $contrata, 'No Regido Ley 20903', '2025-01-01');
        $fuero = $this->docente('99000002K', 0, 20, 'Inicial') + ['exclusion_docente' => ['motivo' => 'fuero_maternal']];
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('99000003K', 20, 0, 'Experto 2', '1990-01-01'),
            $this->docente('99000004K', 20, 0, 'Avanzado', '1980-01-01'), $noRegido, $fuero,
            $this->docente('99000005K', 20, 0, 'Inicial'),
            $this->docente('99000006K', 0, 20, 'Experto 1'),
        ]));
        $this->assertSame([1, 2, 3, 3, 4, 5], $docentes->pluck('prioridad_2027')->all());
        $this->assertSame('99000001K', $docentes[1]['rut_normalizado']);
        $this->assertStringContainsString('No Regido Ley 20903', $docentes[1]['prioridad_2027_label']);
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible(
            $docentes->reject(fn ($d) => $d['prioridad_2027'] === 1), $docentes[2], 5
        ));
    }

    public function test_antiguedad_ordena_y_bloquea_dentro_del_nuevo_grupo_con_fecha_ausente_al_final(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('99000001K', 20, 0, 'No Regido Ley 20903', null),
            $this->docente('99000002K', 20, 0, 'No Regido Ley 20903', '2020-01-01'),
            $this->docente('99000003K', 0, 20, 'No Regido Ley 20903', '1990-01-01'),
        ]));
        $this->assertSame(['99000003K', '99000002K', '99000001K'], $docentes->pluck('rut_normalizado')->all());
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($docentes, $docentes[1], 5));
        $this->assertFalse(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($docentes, $docentes[0], 5));
    }

    public function test_fase_preferente_admite_completar_saldo_titular_con_contrata_propia(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('99000001K', 0, 12, 'No Regido Ley 20903'),
            $this->docente('99000002K', 20, 5, 'Avanzado'),
            $this->docente('99000003K', 0, 30, 'Experto 2'),
        ]));
        $this->assertSame('preferente', DotacionPlanTitularPrimero::fase($docentes));
        $this->assertSame(['99000001K', '99000002K'], DotacionPlanTitularPrimero::opciones($docentes)->pluck('rut_normalizado')->all());
        $this->assertSame(12.0, DotacionPlanTitularPrimero::disponibles($docentes[0], 'preferente'));
        $this->assertSame(20.0, DotacionPlanTitularPrimero::disponibles($docentes[1], 'preferente'));
        DotacionPlanTitularPrimero::validar($docentes, $docentes[0], 12, false);
        $this->assertSame(25.0, DotacionPlanTitularPrimero::saldoAsignable($docentes[1], 'preferente'));
        DotacionPlanTitularPrimero::validar($docentes, $docentes[1], 21, false);
        $this->expectException(ValidationException::class);
        DotacionPlanTitularPrimero::validar($docentes, $docentes[1], 26, false);
    }

    public function test_la_comparacion_usa_el_saldo_completo_no_regido_y_no_modifica_el_contrato(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('99000001K', 0, 12, 'No Regido Ley 20903'),
            $this->docente('99000002K', 20, 0, 'Avanzado'),
        ]));
        $original = $docentes->all();
        $candidatos = DotacionPlanTitularPrimero::candidatosParaPrelacion($docentes);
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($candidatos, $candidatos[1], 8));
        $this->assertFalse(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($candidatos, $candidatos[0], 8));
        $this->assertSame($original, $docentes->all());
    }

    public function test_al_agotar_el_grupo_se_reanuda_titular_primero_y_un_saldo_menor_a_una_hora_se_omite(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('99000001K', 0, .99, 'No Regido Ley 20903'),
            $this->docente('99000002K', 20, 0, 'Avanzado'),
        ]));
        $this->assertSame('titular', DotacionPlanTitularPrimero::fase($docentes));
        $this->assertSame(['99000002K'], DotacionPlanTitularPrimero::opciones($docentes)->pluck('rut_normalizado')->all());
    }

    public function test_el_excel_de_actualizacion_admite_la_categoria_y_normaliza_su_etiqueta(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['rut', 'Tramo'], ['990000018', ' no regido ley 20.903 ']]);
        $path = Storage::disk('local')->path('tramo-sintetico.xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $filas = app(PadronDatosExcel::class)->leer($path, ['tramo']);
        $this->assertSame([], $filas[0]['errores']);
        $this->assertSame(TramoDocente::NO_REGIDO_LEY_20903, $filas[0]['datos']['tramo']);
    }

    public function test_editar_restituye_saldo_no_regido_sin_modificar_la_calidad_contractual(): void
    {
        $noRegido = $this->docente('99000001K', 8, 12, 'No Regido Ley 20903');
        $noRegido['horas_asignadas_total'] = 20;
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $noRegido, $this->docente('99000002K', 20, 0, 'Avanzado'),
        ]));
        $current = new DotacionDocenteAsignacion(['docente_rut_normalizado' => '99000001K', 'estamento_cobertura' => 'docente', 'horas_contrato' => 4]);
        $elegibles = DotacionPlanTitularPrimero::elegibles($docentes, ['99000001K', '99000002K'], [], $current);
        $this->assertSame('preferente', DotacionPlanTitularPrimero::fase($elegibles));
        $this->assertSame(4.0, DotacionPlanTitularPrimero::disponibles($elegibles[0], 'preferente'));
        $this->assertSame(8.0, $elegibles[0]['horas_planta']);
        $this->assertSame(12.0, $elegibles[0]['horas_contrata']);
    }

    public function test_fuero_y_gremiales_conservan_primera_prioridad_ante_el_nuevo_grupo(): void
    {
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([
            $this->docente('99000001K', 0, 20, 'No Regido Ley 20903'),
            $this->docente('99000002K', 0, 20, 'Inicial') + ['exclusion_docente' => ['motivo' => 'horas_gremiales']],
        ]));
        $this->assertSame('preferente', DotacionPlanTitularPrimero::fase($docentes));
        $candidatos = DotacionPlanTitularPrimero::candidatosParaPrelacion($docentes);
        $this->assertSame(['99000002K', '99000001K'], $candidatos->pluck('rut_normalizado')->all());
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($candidatos, $candidatos[1], 5));
    }
}
