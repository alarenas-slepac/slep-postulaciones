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

    public function test_additional_historical_labels_match_uniquely_without_mutating_original_data(): void
    {
        foreach (['CONTRATA', 'INDEFINIDO', 'PLAZO FIJO'] as $base) {
            $incoming = [$this->data('SEP', 17, ['tipocontrato' => $base]), $this->data('PIE', 2, ['tipocontrato' => $base])];
            $old = array_map(fn ($r, $i) => array_replace($r, ['id' => 101 + $i, 'mes' => 8, 'vigente' => true,
                'tipocontrato' => strtolower($base).'  '.$r['financiamiento']]), $incoming, [0, 1]);
            $report = $this->report($incoming, $old);
            $this->assertSame([101, 102], array_column($report['filas'], 'personal_id'));
            $this->assertSame(['actualizacion_propuesta', 'actualizacion_propuesta'], array_column($report['filas'], 'accion'));
            $this->assertSame($old[0], $report['filas'][0]['anterior']);
            $this->assertSame($incoming[0], $report['filas'][0]['datos']);
            $this->assertStringContainsString('Corrección de denominación histórica', implode(' ', $report['filas'][0]['observaciones']));
            $this->assertStringNotContainsString('No se propone reemplazar ningún ID', implode(' ', $report['filas'][0]['observaciones']));
            $this->assertSame([102, 101], array_column($this->report(array_reverse($incoming), array_reverse($old))['filas'], 'personal_id'));
        }
    }

    public function test_additional_labels_do_not_hide_other_differences_or_resolve_residual_contract_changes(): void
    {
        foreach (['CONTRATA', 'INDEFINIDO', 'PLAZO FIJO'] as $base) {
            $incoming = [$this->data('SEP', 17, ['tipocontrato' => $base]), $this->data('PIE', 2)];
            $old = [$this->data('SEP', 17, ['id' => 101, 'tipocontrato' => $base.' SEP']),
                $this->data('PIE', 2, ['id' => 102, 'tipocontrato' => 'CONTRATA'])];
            foreach ([[], ['rbd' => 99998], ['fecha_ingreso' => '2021-03-01'], ['estatuto' => 'ASISTENTE'],
                ['escalafon' => 'DOCENTE SEP'], ['jornada' => 16], ['jornada_basica' => 16], ['jornada_media' => 1],
                ['financiamiento' => 'SUB.GENERAL'], ['tipocontrato' => $base.' PIE'], ['tipocontrato' => $base.' SEP EXTRA']] as $change) {
                $report = $this->report($incoming, [array_replace($old[0], $change), $old[1]]);
                $this->assertSame($change ? null : 101, $report['filas'][0]['personal_id'], json_encode($change));
                $this->assertSame('revision_manual', $report['filas'][1]['accion']); // No CONTRATA -> PLANTA por descarte.
                $this->assertNull($report['filas'][1]['personal_id']);
            }
        }
    }

    public function test_canonical_and_historical_duplicate_candidates_remain_ambiguous(): void
    {
        foreach (['CONTRATA', 'INDEFINIDO', 'PLAZO FIJO'] as $base) {
            $incoming = $this->data('SEP', 17, ['tipocontrato' => $base]);
            foreach ([$base, $base.' SEP'] as $otherLabel) {
                $old = [array_replace($incoming, ['id' => 101, 'tipocontrato' => $base.' SEP']),
                    array_replace($incoming, ['id' => 102, 'tipocontrato' => $otherLabel])];
                foreach ([$old, array_reverse($old)] as $order) {
                    $report = $this->report([$incoming], $order);
                    $this->assertNull($report['filas'][0]['personal_id']);
                    $this->assertSame('revision_manual', $report['filas'][0]['accion']);
                }
            }
            $old = [array_replace($incoming, ['id' => 101, 'tipocontrato' => $base.' SEP'])];
            $report = $this->report([$incoming, array_replace($incoming, ['nombre' => 'Otra etiqueta sintética'])], $old);
            $this->assertSame([null, null], array_column(array_slice($report['filas'], 0, 2), 'personal_id'));
            $canonicalOld = [array_replace($incoming, ['id' => 101])];
            $mixedIncoming = [$incoming, array_replace($incoming, ['tipocontrato' => $base.' SEP'])];
            $report = $this->report($mixedIncoming, $canonicalOld);
            $this->assertSame([null, null], array_column(array_slice($report['filas'], 0, 2), 'personal_id'));
        }
    }

    public function test_extension_does_not_classify_historical_labels_as_valid_incoming_contracts(): void
    {
        foreach (['CONTRATA SEP', 'INDEFINIDO PIE', 'PLAZO FIJO PIE', 'REEMPLAZO SEP', 'SUPLENCIA PIE'] as $label) {
            $this->assertNotSame('regular', PadronConciliador::tipo(['tipocontrato' => $label]));
        }
    }
}
