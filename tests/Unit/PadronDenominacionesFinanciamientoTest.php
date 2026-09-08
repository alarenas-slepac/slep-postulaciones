<?php

namespace Tests\Unit;

use App\Services\Padron\PadronConciliador;
use Tests\TestCase;

class PadronDenominacionesFinanciamientoTest extends TestCase
{
    private function data(string $financiamiento, int $horas, array $changes = []): array
    {
        return array_replace([
            'rut' => '111111111', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'tipocontrato' => 'PLANTA', 'financiamiento' => $financiamiento,
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA', 'fecha_ingreso' => '2020-03-01',
            'anio' => 2026, 'mes' => 9, 'jornada' => $horas, 'jornada_basica' => $horas, 'jornada_media' => 0,
        ], $changes);
    }

    private function old(): array
    {
        return [
            $this->data('SUB.GENERAL', 21, ['id' => 101, 'mes' => 8, 'vigente' => true]),
            $this->data('PIE', 2, ['id' => 102, 'mes' => 8, 'vigente' => true, 'tipocontrato' => 'PLANTA PIE', 'escalafon' => 'DOCENTE PIE']),
            $this->data('SEP', 17, ['id' => 103, 'mes' => 8, 'vigente' => true, 'tipocontrato' => 'PLANTA SEP', 'escalafon' => 'DOCENTE SEP']),
        ];
    }

    private function report(array $incoming, ?array $old = null): array
    {
        return app(PadronConciliador::class)->reconcile(
            array_map(fn ($data, $i) => ['datos' => $data, 'fila_excel' => $i + 2, 'observaciones' => []], $incoming, array_keys($incoming)),
            $old ?? $this->old(), [99999 => 1, 99998 => 2],
            [['id' => 501, 'docente_rut' => '111111111', 'reemplazos_personal_id' => 103]], ['111111111' => 40],
        );
    }

    public function test_corrected_contract_and_escalafon_keep_all_three_ids_and_new_values(): void
    {
        $incoming = [$this->data('SEP', 17), $this->data('SUB.GENERAL', 21), $this->data('PIE', 2)];
        $old = $this->old();
        $report = $this->report($incoming, $old);
        $this->assertSame([103, 101, 102], array_column($report['filas'], 'personal_id'));
        $this->assertSame(['actualizacion_propuesta', 'sin_cambios', 'actualizacion_propuesta'], array_column($report['filas'], 'accion'));
        $this->assertCount(3, $report['filas']); // No falsas ausencias ni incorporaciones.
        $this->assertSame([], $report['errores']);
        $this->assertSame([], $report['excesos']);
        $this->assertSame(40, array_sum(array_column(array_column($report['filas'], 'datos'), 'jornada')));
        foreach ([0, 2] as $i) {
            $row = $report['filas'][$i];
            $this->assertSame('PLANTA', $row['datos']['tipocontrato']);
            $this->assertSame('DOCENTE AULA', $row['datos']['escalafon']);
            $this->assertSame(501, $row['asignaciones'][0]['id']);
            $this->assertStringContainsString('se conserva el ID', implode(' ', $row['observaciones']));
        }
        $this->assertSame('PLANTA SEP', $report['filas'][0]['anterior']['tipocontrato']);
        $this->assertSame('DOCENTE SEP', $report['filas'][0]['anterior']['escalafon']);
        $this->assertSame($this->old(), $old); // Comparación de solo lectura.
        $reverse = $this->report(array_reverse($incoming), array_reverse($old));
        $this->assertSame([102, 101, 103], array_column($reverse['filas'], 'personal_id'));
    }

    public function test_escalafon_is_updated_even_if_contract_is_already_named_planta(): void
    {
        $old = array_map(fn ($r) => array_replace($r, ['tipocontrato' => 'PLANTA']), $this->old());
        $report = $this->report([$this->data('SEP', 17), $this->data('PIE', 2), $this->data('SUB.GENERAL', 21)], $old);
        $this->assertSame([103, 102, 101], array_column($report['filas'], 'personal_id'));
        $this->assertSame('actualizacion_propuesta', $report['filas'][0]['accion']);
        $this->assertSame('DOCENTE AULA', $report['filas'][0]['datos']['escalafon']);
    }

    public function test_duplicate_old_candidates_remain_manual_even_with_different_escalafones(): void
    {
        $old = [$this->old()[2], array_replace($this->old()[2], ['id' => 104, 'escalafon' => 'OTRO ESCALAFON'])];
        $report = $this->report([$this->data('SEP', 17)], $old);
        $this->assertSame('revision_manual', $report['filas'][0]['accion']);
        $this->assertNull($report['filas'][0]['personal_id']);
        $this->assertCount(2, $report['filas'][0]['candidatos']);
    }

    public function test_multiple_incoming_candidates_are_not_resolved_by_escalafon_or_order(): void
    {
        $report = $this->report([$this->data('SEP', 17), $this->data('SEP', 17, ['escalafon' => 'OTRO ESCALAFON'])], [$this->old()[2]]);
        $this->assertSame(['revision_manual', 'revision_manual'], array_column(array_slice($report['filas'], 0, 2), 'accion'));
        $this->assertSame([null, null], array_column(array_slice($report['filas'], 0, 2), 'personal_id'));
    }

    public function test_other_contract_fields_still_prevent_automatic_multiline_matching(): void
    {
        foreach ([
            ['rbd' => 99998], ['fecha_ingreso' => '2021-03-01'], ['estatuto' => 'ASISTENTE'],
            ['jornada' => 16], ['jornada_basica' => 16], ['jornada_media' => 1], ['financiamiento' => 'SUB.GENERAL'],
            ['tipocontrato' => 'CONTRATA'], ['tipocontrato' => 'REEMPLAZO'],
        ] as $changes) {
            $report = $this->report([$this->data('SEP', 17, $changes), $this->data('PIE', 2, ['jornada' => 3])], array_slice($this->old(), 1));
            $this->assertSame('revision_manual', $report['filas'][0]['accion'], json_encode($changes));
            $this->assertNull($report['filas'][0]['personal_id']);
        }
    }

    public function test_contradictory_or_unknown_suffix_is_not_normalized(): void
    {
        foreach (['PLANTA PIE', 'PLANTA OTRO', 'PLANTA SEP EXTRA'] as $contrato) {
            $old = [$this->old()[1], array_replace($this->old()[2], ['tipocontrato' => $contrato])];
            $report = $this->report([$this->data('SEP', 17), $this->data('PIE', 3)], $old);
            $this->assertSame('revision_manual', $report['filas'][0]['accion']);
            $this->assertNull($report['filas'][0]['personal_id']);
        }
    }

    public function test_other_contracts_do_not_ignore_escalafon(): void
    {
        $old = array_map(fn ($row) => array_replace($row, ['tipocontrato' => 'CONTRATA']), array_slice($this->old(), 1));
        $report = $this->report([$this->data('PIE', 2, ['tipocontrato' => 'CONTRATA']), $this->data('SEP', 17, ['tipocontrato' => 'CONTRATA'])], $old);
        $this->assertSame(['revision_manual', 'revision_manual'], array_column(array_slice($report['filas'], 0, 2), 'accion'));
    }
}
