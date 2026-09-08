<?php

namespace Tests\Unit;

use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronExcelReader;
use Tests\TestCase;

class PadronConciliadorTest extends TestCase
{
    private function row(array $changes = [], int $line = 2): array
    {
        return (new PadronExcelReader)->normalize(array_replace([
            'rut' => '11.111.111-1', 'nombre' => 'Persona sintética', 'fecha_nacimiento' => '1980-01-01',
            'fecha_ingreso' => '2020-03-01', 'fecha_termino' => '', 'tipocontrato' => 'CONTRATA',
            'financiamiento' => 'REGULAR', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA',
            'anio' => 2026, 'mes' => 9, 'jornada' => 22, 'jornada_basica' => 22,
            'jornada_media' => 0, 'rbd' => 99999, 'bienios' => 3,
        ], $changes), $line);
    }

    private function old(array $changes = []): array
    {
        return array_replace($this->row()['datos'], ['id' => 101, 'mes' => 8, 'vigente' => true], $changes);
    }

    private function report(array $rows, array $old = [], array $assignments = [], array $declarations = []): array
    {
        return (new PadronConciliador)->reconcile($rows, $old, [99999 => 1, 99998 => 2], $assignments, $declarations);
    }

    public function test_normalizes_dates_and_preserves_omitted_seniority(): void
    {
        $row = $this->row(['fecha_antiguedad' => '08/09/2010']);
        $this->assertSame([], $row['observaciones']);
        $this->assertSame('2010-09-08', $row['datos']['fecha_antiguedad']);
        $this->assertSame('111111111', $row['datos']['rut']);
        $this->assertArrayNotHasKey('fecha_antiguedad', $this->row()['datos']);
        $report = $this->report([$this->row(['fecha_antiguedad' => ''])], [$this->old(['fecha_antiguedad' => '2010-01-01'])]);
        $this->assertSame('sin_cambios', $report['filas'][0]['accion']);
        $this->assertSame(101, $report['filas'][0]['personal_id']);
    }

    public function test_invalid_dates_rut_formulas_and_hours_are_not_silently_accepted(): void
    {
        foreach ([['fecha_antiguedad' => '31/02/2026'], ['rut' => '12345678-9'], ['nombre' => '=1+1'], ['jornada' => -1], ['mes' => 13], ['jornada' => 10.5]] as $invalid) {
            $this->assertNotEmpty($this->row($invalid)['observaciones']);
        }
        $this->assertLessThanOrEqual(32, strlen($this->row(['rut' => str_repeat('1', 100)])['datos']['rut']));
    }

    public function test_unique_update_transfer_and_reactivation_keep_original_id(): void
    {
        foreach ([
            [$this->row(['tipocontrato' => 'TITULAR']), $this->old(), 'actualizacion_propuesta'],
            [$this->row(['rbd' => 99998]), $this->old(), 'traslado_propuesto'],
            [$this->row(), $this->old(['vigente' => false]), 'reactivacion_propuesta'],
        ] as [$incoming, $old, $action]) {
            $report = $this->report([$incoming], [$old]);
            $this->assertCount(1, $report['filas']);
            $this->assertSame(101, $report['filas'][0]['personal_id']);
            $this->assertSame($action, $report['filas'][0]['accion']);
        }
    }

    public function test_multiple_changed_contracts_are_not_guessed(): void
    {
        $report = $this->report([
            $this->row(['jornada' => 24, 'jornada_basica' => 24]),
            $this->row(['jornada' => 20, 'jornada_basica' => 20, 'financiamiento' => 'SEP'], 3),
        ], [$this->old(), $this->old(['id' => 102, 'financiamiento' => 'SEP'])]);
        $this->assertSame(['revision_manual', 'revision_manual', 'ausencia_por_revisar', 'ausencia_por_revisar'], array_column($report['filas'], 'accion'));
        $this->assertNull($report['filas'][0]['personal_id']);
        $this->assertCount(2, $report['filas'][0]['candidatos']);
    }

    public function test_exact_multiline_match_does_not_depend_on_excel_order(): void
    {
        $report = $this->report([$this->row(['financiamiento' => 'SEP']), $this->row([], 3)], [
            $this->old(), $this->old(['id' => 102, 'financiamiento' => 'SEP']),
        ]);
        $this->assertSame([102, 101], array_column($report['filas'], 'personal_id'));
        $this->assertSame([], $report['excesos']);
    }

    public function test_duplicates_invalid_and_mixed_periods_block_absence_proposals(): void
    {
        foreach ([
            [$this->row(), $this->row([], 3)],
            [$this->row(['rbd' => 123])],
            [$this->row(), $this->row(['mes' => 8], 3)],
        ] as $incoming) {
            $report = $this->report($incoming, [$this->old(['rut' => '222222222'])]);
            $this->assertNotEmpty($report['errores']);
            $this->assertNotContains('baja_propuesta', array_column($report['filas'], 'accion'));
        }
        $report = $this->report([$this->row()], [$this->old(['rut' => '222222222'])]);
        $this->assertSame(['nueva_incorporacion', 'baja_propuesta'], array_column($report['filas'], 'accion'));
    }

    public function test_44_hours_aggregates_all_contracts_and_ignores_assistants_only(): void
    {
        $report = $this->report([$this->row(), $this->row(['rbd' => 99998, 'jornada' => 23], 3)]);
        $this->assertSame(45.0, $report['excesos']['111111111']['total']);
        $this->assertSame([2, 3], $report['excesos']['111111111']['filas']);
        $report = $this->report([$this->row(['estatuto' => 'ASISTENTE EDUCACION', 'escalafon' => 'PARADOCENTE', 'jornada' => 45])]);
        $this->assertSame([], $report['excesos']);
    }

    public function test_transfer_displays_assignments_and_does_not_override_declaration(): void
    {
        $assignment = ['id' => 501, 'docente_rut' => '11.111.111-1', 'reemplazos_personal_id' => 101];
        $report = $this->report([$this->row(['rbd' => 99998])], [$this->old()], [$assignment], ['111111111' => 40]);
        $this->assertSame([$assignment], $report['filas'][0]['asignaciones']);
        $this->assertStringContainsString('no se trasladan', implode(' ', $report['filas'][0]['observaciones']));
        $this->assertStringContainsString('Mantiene prioridad', implode(' ', $report['filas'][0]['observaciones']));
    }

    public function test_replacements_and_unknown_contracts_are_explicitly_flagged(): void
    {
        foreach (['REEMPLAZO', 'SUPLENCIA'] as $type) {
            $report = $this->report([$this->row(['tipocontrato' => $type])]);
            $this->assertStringContainsString('Reemplazo/suplencia', implode(' ', $report['filas'][0]['observaciones']));
        }
        $report = $this->report([$this->row(['tipocontrato' => 'OTRO'])]);
        $this->assertSame('revision_manual', $report['filas'][0]['accion']);
    }
}
