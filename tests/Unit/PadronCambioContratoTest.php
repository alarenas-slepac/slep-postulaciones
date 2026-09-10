<?php

namespace Tests\Unit;

use App\Services\Padron\PadronConciliador;
use Tests\TestCase;

class PadronCambioContratoTest extends TestCase
{
    private function data(string $funding, int $hours, array $changes = []): array
    {
        return array_replace([
            'rut' => '111111111', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'tipocontrato' => 'PLANTA', 'financiamiento' => $funding,
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'fecha_ingreso' => '2020-03-01',
            'anio' => 2026, 'mes' => 9, 'jornada' => $hours, 'jornada_basica' => $hours, 'jornada_media' => 0,
        ], $changes);
    }

    private function incoming(): array
    {
        return [$this->data('PIE', 1), $this->data('SUB.GENERAL', 38)];
    }

    private function current(): array
    {
        return [
            $this->data('SUB.GENERAL', 38, ['id' => 101, 'mes' => 8, 'vigente' => true, 'tipocontrato' => 'CONTRATA']),
            $this->data('PIE', 1, ['id' => 102, 'mes' => 8, 'vigente' => true, 'tipocontrato' => 'CONTRATA PIE']),
        ];
    }

    private function report(?array $incoming = null, ?array $current = null, array $observations = []): array
    {
        $incoming ??= $this->incoming();
        return app(PadronConciliador::class)->reconcile(
            array_map(fn ($data, $i) => ['datos' => $data, 'fila_excel' => $i + 2, 'observaciones' => $observations[$i] ?? []], $incoming, array_keys($incoming)),
            $current ?? $this->current(), [99999 => 1, 99998 => 2],
            [['id' => 501, 'docente_rut' => '111111111', 'reemplazos_personal_id' => 102]], ['111111111' => 39],
        );
    }

    public function test_regular_contract_change_keeps_both_ids_without_false_absences(): void
    {
        $report = $this->report();
        $this->assertCount(2, $report['filas']);
        $this->assertSame([102, 101], array_column($report['filas'], 'personal_id'));
        $this->assertSame(['actualizacion_propuesta', 'actualizacion_propuesta'], array_column($report['filas'], 'accion'));
        $this->assertSame([], $report['errores']);
        $this->assertSame([], $report['excesos']);
        foreach ($report['filas'] as $row) {
            $this->assertSame([], $row['candidatos']);
            $this->assertSame('PLANTA', $row['datos']['tipocontrato']);
            $this->assertSame($row['datos']['jornada'], $row['anterior']['jornada']);
            $this->assertSame(501, $row['asignaciones'][0]['id']);
            $this->assertStringContainsString('Cambio de tipo contractual con correspondencia única', implode(' ', $row['observaciones']));
            $this->assertStringNotContainsString('No se propone reemplazar ningún ID', implode(' ', $row['observaciones']));
        }
    }

    public function test_order_and_other_person_do_not_change_identity(): void
    {
        foreach ([$this->current(), array_reverse($this->current())] as $old) {
            $old[] = array_replace($this->current()[1], ['id' => 103, 'rut' => '222222222']);
            $report = $this->report(array_reverse($this->incoming()), $old);
            $this->assertSame([101, 102], array_column(array_slice($report['filas'], 0, 2), 'personal_id'));
            $this->assertSame(103, $report['filas'][2]['personal_id']);
        }
    }

    public function test_regular_transitions_work_in_both_directions_and_preserve_reactivation(): void
    {
        foreach (['CONTRATA', 'TITULAR', 'INDEFINIDO', 'INDEFINIDA', 'PLAZO FIJO', 'CONTRATO INDEFINIDO'] as $base) {
            $incoming = array_map(fn ($row) => array_replace($row, ['tipocontrato' => $base]), $this->incoming());
            $old = array_map(fn ($row) => array_replace($row, ['tipocontrato' => 'PLANTA', 'vigente' => false]), $this->current());
            $report = $this->report($incoming, $old);
            $this->assertSame([102, 101], array_column($report['filas'], 'personal_id'), $base);
            $this->assertSame(['reactivacion_propuesta', 'reactivacion_propuesta'], array_column($report['filas'], 'accion'));
        }
    }

    public function test_other_identity_changes_still_require_manual_review(): void
    {
        foreach ([['rbd' => 99998], ['financiamiento' => 'SEP'], ['jornada' => 2], ['jornada_basica' => 0],
            ['jornada_media' => 1], ['estatuto' => 'ASISTENTE'], ['escalafon' => 'OTRO'], ['fecha_ingreso' => '2021-03-01']] as $change) {
            $incoming = $this->incoming();
            $incoming[0] = array_replace($incoming[0], $change);
            $report = $this->report($incoming);
            $this->assertNull($report['filas'][0]['personal_id'], json_encode($change));
            $this->assertSame('revision_manual', $report['filas'][0]['accion']);
            $this->assertSame(101, $report['filas'][1]['personal_id']);
        }
    }

    public function test_same_total_does_not_resolve_redistributed_hours(): void
    {
        $report = $this->report([$this->data('PIE', 2), $this->data('SUB.GENERAL', 37)]);
        $this->assertSame([null, null], array_column(array_slice($report['filas'], 0, 2), 'personal_id'));
    }

    public function test_duplicate_candidates_are_not_chosen_by_contract_or_order(): void
    {
        $old = $this->current();
        $old[] = array_replace($old[1], ['id' => 103, 'tipocontrato' => 'INDEFINIDO']);
        foreach ([$old, array_reverse($old)] as $order) {
            $report = $this->report(null, $order);
            $this->assertNull($report['filas'][0]['personal_id']);
            $this->assertSame('revision_manual', $report['filas'][0]['accion']);
        }
        $incoming = $this->incoming();
        $incoming[] = array_replace($incoming[0], ['tipocontrato' => 'INDEFINIDO']);
        $report = $this->report($incoming);
        $this->assertNull($report['filas'][0]['personal_id']);
        $this->assertNull($report['filas'][2]['personal_id']);
    }

    public function test_new_pass_does_not_match_by_elimination_after_exact_match(): void
    {
        $old = $this->current();
        $old[] = array_replace($old[1], ['id' => 103, 'tipocontrato' => 'INDEFINIDO']);
        $incoming = $this->incoming();
        $incoming[] = array_replace($incoming[0], ['tipocontrato' => 'INDEFINIDO']);
        $report = $this->report($incoming, $old);
        $this->assertSame(103, $report['filas'][2]['personal_id']);
        $this->assertNull($report['filas'][0]['personal_id']);
    }

    public function test_replacements_unknown_types_and_inconsistent_suffixes_are_not_auto_matched(): void
    {
        foreach (['REEMPLAZO', 'SUPLENCIA', 'CONTRATA SEP', 'CONTRATA PIE EXTRA', 'OTRO'] as $type) {
            $old = $this->current();
            $old[1]['tipocontrato'] = $type;
            $report = $this->report(null, $old);
            $this->assertNull($report['filas'][0]['personal_id'], $type);
        }
        foreach (['REEMPLAZO', 'SUPLENCIA', 'PLANTA PIE', 'OTRO'] as $type) {
            $incoming = $this->incoming();
            $incoming[0]['tipocontrato'] = $type;
            $report = $this->report($incoming);
            $this->assertNull($report['filas'][0]['personal_id'], $type);
        }
    }

    public function test_invalid_rows_and_mixed_periods_do_not_trigger_new_matching(): void
    {
        $report = $this->report(null, null, [0 => ['Error sintético de archivo.']]);
        $this->assertSame([null, null], array_column(array_slice($report['filas'], 0, 2), 'personal_id'));
        $incoming = $this->incoming();
        $incoming[0]['mes'] = 8;
        $report = $this->report($incoming);
        $this->assertSame([null, null], array_column(array_slice($report['filas'], 0, 2), 'personal_id'));
    }

    public function test_matching_does_not_authorize_more_than_44_hours(): void
    {
        $incoming = [$this->data('PIE', 8), $this->data('SUB.GENERAL', 38)];
        $old = $this->current();
        $old[1] = array_replace($old[1], ['jornada' => 8, 'jornada_basica' => 8]);
        $report = $this->report($incoming, $old);
        $this->assertSame([102, 101], array_column($report['filas'], 'personal_id'));
        $this->assertSame(46.0, $report['excesos']['111111111']['total']);
    }
}
