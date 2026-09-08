<?php

namespace Tests\Unit;

use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronExcelReader;
use App\Services\Padron\PadronReemplazosVigentes;
use PHPUnit\Framework\TestCase;

class PadronReemplazosVigentesTest extends TestCase
{
    private function row(int $line, array $changes = []): array
    {
        return (new PadronExcelReader)->normalize(array_replace([
            'rut' => '111111111', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'anio' => 2026, 'mes' => 8, 'jornada' => 44, 'jornada_basica' => 0, 'jornada_media' => 0,
            'bienios' => 0, 'tipocontrato' => 'REEMPLAZO', 'estatuto' => 'DOCENTE',
            'escalafon' => 'DOCENTE AULA', 'financiamiento' => 'REGULAR',
            'fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2026-08-31',
        ], $changes), $line);
    }

    private function reconcile(array $rows, array $current = []): array
    {
        return (new PadronConciliador)->reconcile($rows, $current, [99999 => 1, 99998 => 2]);
    }

    public function test_keeps_latest_full_contract_and_retains_older_line_for_review(): void
    {
        $old = $this->row(2, ['fecha_ingreso' => '2026-07-01', 'fecha_termino' => '2026-07-31']);
        $latest = $this->row(3);
        foreach ([[$old, $latest], [$latest, $old]] as $rows) {
            $report = $this->reconcile($rows);
            $actions = array_column($report['filas'], 'accion', 'fila_excel');
            $this->assertSame(PadronReemplazosVigentes::OMITIDO, $actions[2]);
            $this->assertSame('nueva_incorporacion', $actions[3]);
            $this->assertSame([], $report['excesos']);
            $this->assertSame([], $report['errores']);
            $this->assertCount(2, $report['filas']);
        }
    }

    public function test_keeps_same_date_components_together_across_rbd_and_financing(): void
    {
        $report = $this->reconcile([
            $this->row(2, ['fecha_ingreso' => '2026-07-01']),
            $this->row(3, ['jornada' => 24]),
            $this->row(4, ['jornada' => 20, 'rbd' => 99998, 'financiamiento' => 'SEP']),
        ]);
        $this->assertSame([PadronReemplazosVigentes::OMITIDO, 'nueva_incorporacion', 'nueva_incorporacion'], array_column($report['filas'], 'accion'));
        $this->assertSame([], $report['excesos']);
    }

    public function test_other_contract_hours_reduce_the_available_budget_but_are_never_omitted(): void
    {
        $report = $this->reconcile([
            $this->row(2, ['tipocontrato' => 'TITULAR', 'jornada' => 22]),
            $this->row(3, ['jornada' => 22]),
            $this->row(4, ['jornada' => 22, 'fecha_ingreso' => '2026-07-01']),
        ]);
        $this->assertSame(['nueva_incorporacion', 'nueva_incorporacion', PadronReemplazosVigentes::OMITIDO], array_column($report['filas'], 'accion'));
        $this->assertSame([], $report['excesos']);
    }

    public function test_only_exact_reemplazo_is_selected_not_suplencia_or_other_variants(): void
    {
        foreach (['SUPLENCIA', 'CONTRATA', 'REEMPLAZO DOCENTE'] as $type) {
            $report = $this->reconcile([$this->row(2, ['tipocontrato' => $type]), $this->row(3, ['tipocontrato' => $type, 'fecha_ingreso' => '2026-07-01'])]);
            $this->assertArrayNotHasKey(PadronReemplazosVigentes::OMITIDO, $report['resumen']);
            $this->assertSame(88.0, $report['excesos']['111111111']['total']);
        }
        $report = $this->reconcile([$this->row(2, ['tipocontrato' => ' reemplazo ']), $this->row(3, ['fecha_ingreso' => '2026-07-01'])]);
        $this->assertSame(1, $report['resumen'][PadronReemplazosVigentes::OMITIDO]);
    }

    public function test_termination_date_breaks_equal_start_date_ties(): void
    {
        $report = $this->reconcile([$this->row(2, ['fecha_termino' => '2026-08-15']), $this->row(3)]);
        $this->assertSame(PadronReemplazosVigentes::OMITIDO, $report['filas'][0]['accion']);
        $this->assertSame('nueva_incorporacion', $report['filas'][1]['accion']);
    }

    public function test_incomplete_dates_and_tied_latest_that_cannot_fit_require_correction(): void
    {
        foreach ([
            [$this->row(2, ['fecha_termino' => '']), $this->row(3, ['fecha_ingreso' => '2026-07-01'])],
            [$this->row(2), $this->row(3, ['financiamiento' => 'SEP'])],
            [$this->row(2, ['jornada' => 45]), $this->row(3, ['fecha_ingreso' => '2026-07-01'])],
            [$this->row(2), $this->row(3, ['tipocontrato' => 'TITULAR', 'jornada' => 22])],
        ] as $rows) {
            $report = $this->reconcile($rows);
            $this->assertArrayNotHasKey(PadronReemplazosVigentes::OMITIDO, $report['resumen']);
            $this->assertNotEmpty($report['errores']);
            $this->assertNotEmpty($report['excesos']);
        }
    }

    public function test_does_not_jump_to_an_older_contract_to_fill_remaining_hours(): void
    {
        $report = $this->reconcile([
            $this->row(2, ['jornada' => 30]),
            $this->row(3, ['jornada' => 20, 'fecha_ingreso' => '2026-07-01']),
            $this->row(4, ['jornada' => 14, 'fecha_ingreso' => '2026-06-01']),
        ]);
        $this->assertSame(['nueva_incorporacion', PadronReemplazosVigentes::OMITIDO, PadronReemplazosVigentes::OMITIDO], array_column($report['filas'], 'accion'));
        $this->assertSame([], $report['excesos']);
    }

    public function test_invalid_duplicates_and_mixed_periods_cannot_be_hidden_by_selection(): void
    {
        foreach ([
            [$this->row(2), $this->row(3)],
            [$this->row(2), $this->row(3, ['rut' => '12345678-9'])],
            [$this->row(2), $this->row(3, ['mes' => 5])],
        ] as $rows) {
            $report = $this->reconcile($rows);
            $this->assertArrayNotHasKey(PadronReemplazosVigentes::OMITIDO, $report['resumen']);
            $this->assertNotEmpty($report['errores']);
        }
    }

    public function test_assistant_replacements_are_also_selected_but_regular_assistants_are_unchanged(): void
    {
        $report = $this->reconcile([
            $this->row(2, ['estatuto' => 'ASISTENTE EDUCACION', 'escalafon' => 'AUXILIAR']),
            $this->row(3, ['estatuto' => 'ASISTENTE EDUCACION', 'escalafon' => 'AUXILIAR', 'fecha_ingreso' => '2026-07-01']),
            $this->row(4, ['rut' => '222222222', 'estatuto' => 'ASISTENTE EDUCACION', 'escalafon' => 'AUXILIAR', 'tipocontrato' => 'INDEFINIDO', 'jornada' => 45]),
        ]);
        $this->assertSame(['nueva_incorporacion', PadronReemplazosVigentes::OMITIDO, 'nueva_incorporacion'], array_column($report['filas'], 'accion'));
    }

    public function test_old_ids_of_omitted_lines_require_explicit_absence_review_not_deletion(): void
    {
        $old = $this->row(2, ['fecha_ingreso' => '2026-07-01']);
        $new = $this->row(3);
        $report = $this->reconcile([$old, $new], [
            $old['datos'] + ['id' => 101, 'vigente' => true],
            $new['datos'] + ['id' => 102, 'vigente' => true],
        ]);
        $this->assertNull($report['filas'][0]['personal_id']);
        $this->assertSame(102, $report['filas'][1]['personal_id']);
        $this->assertSame('ausencia_por_revisar', $report['filas'][2]['accion']);
        $this->assertSame(101, $report['filas'][2]['personal_id']);
    }

    public function test_ended_replacement_is_not_added_to_a_later_regular_contract_even_in_another_rbd(): void
    {
        foreach ([99999, 99998] as $rbd) {
            foreach ([22, 44] as $hours) {
                $old = $this->row(2, ['fecha_ingreso' => '2026-07-01', 'fecha_termino' => '2026-07-31']);
                $new = $this->row(3, ['tipocontrato' => 'CONTRATA', 'rbd' => $rbd, 'jornada' => $hours, 'fecha_termino' => '']);
                foreach ([[$old, $new], [$new, $old]] as $rows) {
                    $report = $this->reconcile($rows);
                    $byLine = array_column($report['filas'], null, 'fila_excel');
                    $this->assertSame(PadronReemplazosVigentes::OMITIDO, $byLine[2]['accion']);
                    $this->assertStringContainsString('transición contractual', implode(' ', $byLine[2]['observaciones']));
                    $this->assertSame('nueva_incorporacion', $byLine[3]['accion']);
                    $this->assertSame([], $report['excesos']);
                    $this->assertSame([], $report['errores']);
                }
            }
        }
    }

    public function test_successive_contracts_are_not_combined_even_if_the_sum_is_below_44(): void
    {
        $report = $this->reconcile([
            $this->row(2, ['jornada' => 10, 'fecha_ingreso' => '2026-07-01', 'fecha_termino' => '2026-07-31']),
            $this->row(3, ['jornada' => 20, 'tipocontrato' => 'INDEFINIDO']),
        ]);
        $this->assertSame(PadronReemplazosVigentes::OMITIDO, $report['filas'][0]['accion']);
        $this->assertSame('nueva_incorporacion', $report['filas'][1]['accion']);
        $this->assertSame([], $report['errores']);
    }

    public function test_overlap_same_day_and_missing_dates_do_not_prove_a_transition(): void
    {
        foreach (['2026-08-01', '2026-08-15', ''] as $end) {
            $report = $this->reconcile([
                $this->row(2, ['fecha_ingreso' => '2026-07-01', 'fecha_termino' => $end]),
                $this->row(3, ['tipocontrato' => 'TITULAR']),
            ]);
            $this->assertArrayNotHasKey(PadronReemplazosVigentes::OMITIDO, $report['resumen']);
            $this->assertNotEmpty($report['errores']);
            $this->assertSame(88.0, $report['excesos']['111111111']['total']);
        }
    }

    public function test_suplencia_unknown_or_zero_hour_contract_does_not_prove_a_regular_transition(): void
    {
        foreach ([['tipocontrato' => 'SUPLENCIA'], ['tipocontrato' => 'DESCONOCIDO'], ['tipocontrato' => 'TITULAR', 'jornada' => 0]] as $changes) {
            $report = $this->reconcile([
                $this->row(2, ['fecha_ingreso' => '2026-07-01', 'fecha_termino' => '2026-07-31']),
                $this->row(3, $changes),
            ]);
            $this->assertArrayNotHasKey(PadronReemplazosVigentes::OMITIDO, $report['resumen']);
        }
    }

    public function test_a_transition_does_not_omit_the_new_replacement_or_hide_other_regular_excesses(): void
    {
        $report = $this->reconcile([
            $this->row(2, ['fecha_ingreso' => '2026-06-01', 'fecha_termino' => '2026-06-30']),
            $this->row(3, ['fecha_ingreso' => '2026-07-01', 'tipocontrato' => 'CONTRATA', 'jornada' => 22]),
            $this->row(4, ['jornada' => 22]),
        ]);
        $this->assertSame([PadronReemplazosVigentes::OMITIDO, 'nueva_incorporacion', 'nueva_incorporacion'], array_column($report['filas'], 'accion'));
        $this->assertSame([], $report['excesos']);
        $report = $this->reconcile([
            $this->row(2, ['fecha_ingreso' => '2026-06-01', 'fecha_termino' => '2026-06-30']),
            $this->row(3, ['tipocontrato' => 'TITULAR', 'jornada' => 45]),
        ]);
        $this->assertSame(45.0, $report['excesos']['111111111']['total']);
        $this->assertSame([3], $report['excesos']['111111111']['filas']);
    }
}
