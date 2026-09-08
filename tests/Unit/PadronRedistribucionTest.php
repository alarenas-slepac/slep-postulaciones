<?php

namespace Tests\Unit;

use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronRedistribucionService;
use Tests\TestCase;

class PadronRedistribucionTest extends TestCase
{
    private function data(string $fin, int $horas, array $extra = []): array
    {
        return array_replace(['rut' => '111111111', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'anio' => 2026, 'mes' => 9, 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA',
            'tipocontrato' => 'PLANTA', 'financiamiento' => $fin, 'fecha_ingreso' => '2020-03-01',
            'jornada' => $horas, 'jornada_basica' => $horas, 'jornada_media' => 0], $extra);
    }

    private function actuales(): array
    {
        return [$this->data('SUB.GENERAL', 26, ['id' => 101, 'mes' => 8, 'vigente' => true]),
            $this->data('PIE', 2, ['id' => 102, 'mes' => 8, 'vigente' => true, 'tipocontrato' => 'PLANTA PIE', 'escalafon' => 'DOCENTE PIE'])];
    }

    public function test_only_suggests_receiver_and_donor_without_resolving_either_row(): void
    {
        $old = [...$this->actuales(), $this->data('SEP', 4, ['id' => 103, 'mes' => 8])];
        $incoming = [$this->data('SEP', 4), $this->data('SUB.GENERAL', 28)];
        $report = app(PadronConciliador::class)->reconcile(array_map(fn ($d, $i) => [
            'datos' => $d, 'fila_excel' => $i + 2, 'observaciones' => [],
        ], $incoming, array_keys($incoming)), $old, [99999 => 1]);
        $row = $report['filas'][1];
        $this->assertSame('revision_manual', $row['accion']);
        $this->assertNull($row['personal_id']);
        $this->assertCount(2, $row['candidatos']);
        $proposal = collect($row['candidatos'])->firstWhere('id', 101)['_redistribucion'];
        $this->assertSame(101, $proposal['receptor_id']);
        $this->assertSame(102, $proposal['absorbido_id']);
        $this->assertSame(26.0, $proposal['horas_antes']);
        $this->assertSame(28.0, $proposal['horas_despues']);
        $this->assertSame(2.0, $proposal['horas_absorbidas']);
        $this->assertSame(32.0, $proposal['total_antes']);
        $this->assertSame(32.0, $proposal['total_despues']);
        $this->assertSame(['ausencia_por_revisar', 'ausencia_por_revisar'], array_column(array_slice($report['filas'], 2), 'accion'));
        $this->assertArrayNotHasKey('_redistribucion', $row['datos']);
        $this->assertArrayNotHasKey('_redistribucion', $old[0]);
    }

    public function test_total_equality_is_not_enough_when_identity_or_composition_changes(): void
    {
        $incoming = $this->data('SUB.GENERAL', 28);
        foreach ([['rbd' => 99998], ['anio' => 2025], ['estatuto' => 'ASISTENTE'], ['vigente' => false],
            ['_historico' => true], ['rut' => '222222222'], ['tipocontrato' => 'REEMPLAZO'],
            ['tipocontrato' => 'SUPLENCIA'], ['tipocontrato' => 'PLANTA SEP'], ['financiamiento' => 'SUB.GENERAL'],
            ['jornada_basica' => 1], ['jornada_media' => 1], ['jornada' => -2]] as $change) {
            $old = $this->actuales();
            $old[1] = array_replace($old[1], $change);
            $this->assertNull(app(PadronRedistribucionService::class)->proponer($incoming, $old, [$incoming], $old), json_encode($change));
        }
        $old = $this->actuales();
        $old[0]['fecha_ingreso'] = '2019-01-01';
        $this->assertNull(app(PadronRedistribucionService::class)->proponer($incoming, $old, [$incoming], $old));
    }

    public function test_does_not_propose_multiple_donors_duplicates_or_unbalanced_global_total(): void
    {
        $incoming = $this->data('SUB.GENERAL', 28);
        $old = $this->actuales();
        $service = app(PadronRedistribucionService::class);
        $this->assertNull($service->proponer($incoming, [$old[0], $old[0]], [$incoming], $old));
        $this->assertNull($service->proponer($incoming, [...$old, $this->data('SEP', 2, ['id' => 103])], [$incoming], $old));
        $this->assertNull($service->proponer($incoming, $old, [$incoming, $this->data('SEP', 4)], [...$old, $this->data('SEP', 5)]));
    }

    public function test_suggestion_is_order_independent_and_preserves_escalafon_from_file(): void
    {
        $incoming = $this->data('SUB.GENERAL', 28, ['escalafon' => 'DOCENTE GENERAL']);
        $old = $this->actuales();
        $service = app(PadronRedistribucionService::class);
        $this->assertSame($service->proponer($incoming, $old, [$incoming], $old), $service->proponer($incoming, array_reverse($old), [$incoming], array_reverse($old)));
        $this->assertSame('DOCENTE GENERAL', $incoming['escalafon']);
    }
}
