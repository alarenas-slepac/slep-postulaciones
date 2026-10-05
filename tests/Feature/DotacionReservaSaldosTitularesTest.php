<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionReservaNoNormativa;
use Illuminate\Support\Facades\Blade;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionReservaSaldosTitularesTest extends IsolatedSecurityTestCase
{
    public function test_muestra_saldos_de_todos_los_bloques_sin_cambiar_la_autorizacion(): void
    {
        $proceso = $this->proceso();
        $original = $proceso;
        $candidatos = DotacionReservaNoNormativa::candidatos($proceso);
        $this->assertSame(['99000001-K', '99000002-K', '99000003-K'], $candidatos->pluck('rut')->all());
        $this->assertSame(['99000001-K', '99000002-K'], DotacionReservaNoNormativa::elegibles($proceso)->pluck('rut')->all());
        $this->assertSame(5.0, DotacionReservaNoNormativa::maximoParaDocente($proceso, $candidatos[0], 'titular'));
        $this->assertSame(2.0, DotacionReservaNoNormativa::maximoParaDocente($proceso, $candidatos[1], 'titular'));
        $this->assertSame(0.4, DotacionReservaNoNormativa::maximoParaDocente($proceso, $candidatos[2], 'titular'));
        $this->assertSame(0.0, DotacionReservaNoNormativa::maximoParaDocente($proceso, $candidatos[0], 'contrata'));
        $this->assertSame($original, $proceso);

        $html = $this->render($proceso);
        $xpath = $this->xpath($html);
        $options = $xpath->query('//select[@name="docente_rut"]/option[@value!=""]');
        $this->assertSame(3, $options->length);
        $this->assertFalse($options[0]->hasAttribute('disabled'));
        $this->assertFalse($options[1]->hasAttribute('disabled'));
        $this->assertTrue($options[2]->hasAttribute('disabled'));
        $this->assertSame('5', $options[2]->getAttribute('data-saldo'));
        $this->assertSame('0,40', $options[2]->getAttribute('data-maximo'));
        $this->assertStringContainsString('PIE especializado', $options[2]->getAttribute('data-motivo'));
        $this->assertStringContainsString('3 docente(s)', $html);
        $this->assertStringContainsString('2 habilitado(s)', $html);
        $this->assertStringContainsString('No se habilitan horas a contrata', $html);
        $this->assertSame(0, $xpath->query('//button[@type="submit"][@disabled]')->length);

        if (getenv('DOTACION_UI_PREVIEW') === '1') {
            file_put_contents(base_path('.codex_work/dotacion-usabilidad-preview/reserva.html'), $html);
        }
    }

    public function test_descuenta_reservas_y_asignaciones_y_omite_fracciones_y_cupos_virtuales(): void
    {
        $proceso = $this->proceso();
        $proceso['docentes'][] = $this->docente(4, 'Pedagogía en Educación Básica', 0.99);
        $proceso['docentes'][] = $this->docente(5, 'Pedagogía en Educación Básica', 6, ['horas_disponibles' => 0]);
        $proceso['docentes'][] = $this->docente(6, 'Pedagogía en Educación Básica', 44, ['cupo_contrata_id' => 9]);
        $proceso['docentes'][] = $this->docente(7, 'Pedagogía en Educación Básica', 0, ['horas_disponibles' => 10, 'horas_contrata_disponibles' => 10]);
        $proceso['docentes'][0]['horas_titulares_disponibles'] = 2;
        $proceso['docentes'][0]['horas_disponibles'] = 2;
        $this->assertCount(3, DotacionReservaNoNormativa::candidatos($proceso));
        $this->assertSame(2.0, DotacionReservaNoNormativa::maximoParaDocente($proceso, $proceso['docentes'][0], 'titular'));
        $this->assertSame(3, $this->xpath($this->render($proceso))->query('//select[@name="docente_rut"]/option[@value!=""]')->length);
    }

    public function test_conserva_visibles_los_saldos_si_todos_los_bloques_estan_sin_margen(): void
    {
        $proceso = $this->proceso();
        foreach ($proceso['bloques'] as &$bloque) {
            $bloque['saldo_maximo'] = 0;
        }
        unset($bloque);
        $this->assertTrue(DotacionReservaNoNormativa::elegibles($proceso)->isEmpty());
        $html = $this->render($proceso);
        $xpath = $this->xpath($html);
        $this->assertSame(3, $xpath->query('//select[@name="docente_rut"]/option[@value!=""][@disabled]')->length);
        $this->assertSame(1, $xpath->query('//button[@type="submit"][@disabled]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="horas_contrato"][@disabled]')->length);
        $this->assertStringContainsString('0 habilitado(s)', $html);
    }

    public function test_saldo_global_agotado_no_oculta_el_saldo_individual_ni_habilita_traspasos(): void
    {
        $proceso = $this->proceso();
        $proceso['capacidad_reserva_no_normativa'] = 0;
        $html = $this->render($proceso);
        $xpath = $this->xpath($html);
        $this->assertSame(3, $xpath->query('//select[@name="docente_rut"]/option[@value!=""][@disabled]')->length);
        $this->assertSame(1, $xpath->query('//button[@type="submit"][@disabled]')->length);
        $this->assertStringContainsString('No queda al menos 1 h de saldo no normativo', $html);
        foreach ($proceso['docentes'] as $docente) {
            $this->assertSame(0.0, DotacionReservaNoNormativa::maximoParaDocente($proceso, $docente, 'titular'));
        }
    }

    public function test_sin_saldo_titular_no_aparece_fase_contrata_y_etapas_previas_bloquean_la_accion(): void
    {
        $proceso = $this->proceso();
        $html = $this->render($proceso, false);
        $this->assertSame(1, $this->xpath($html)->query('//button[@type="submit"][@disabled]')->length);
        $this->assertStringContainsString('Complete las etapas previas', $html);
        $proceso['docentes'] = [$this->docente(7, 'Pedagogía en Educación Básica', 0, ['horas_disponibles' => 10, 'horas_contrata_disponibles' => 10])];
        $html = $this->render($proceso);
        $this->assertSame('titular', DotacionReservaNoNormativa::fase(DotacionReservaNoNormativa::candidatos($proceso)));
        $this->assertStringNotContainsString('name="docente_rut"', $html);
        $this->assertStringContainsString('Las horas a contrata no se pueden reservar', $html);
    }

    private function proceso(): array
    {
        return [
            'capacidad_reserva_no_normativa' => 8.0,
            'bloques' => [
                'bloque_1' => ['saldo_maximo' => 5.0],
                'bloque_2' => ['saldo_maximo' => 2.0],
                'bloque_3' => ['saldo_maximo' => 0.4],
            ],
            'docentes' => [
                $this->docente(1, 'Pedagogía en Educación Básica', 7),
                $this->docente(2, 'Pedagogía en Educación de Párvulos', 4),
                $this->docente(3, 'Pedagogía en Educación Diferencial', 5),
            ],
        ];
    }

    private function docente(int $id, string $titulo, float $titular, array $extra = []): array
    {
        return $extra + [
            'rut' => '9900000'.$id.'-K', 'nombre' => 'Docente sintético '.$id,
            'titulo' => $titulo, 'horas_disponibles' => $titular,
            'horas_titulares_disponibles' => $titular, 'horas_contrata_disponibles' => 0,
        ];
    }

    private function render(array $proceso, bool $habilitada = true): string
    {
        $establecimiento = new Establecimiento(['nombre_establecimiento' => 'Establecimiento sintético']);
        $establecimiento->id = 99999;

        return Blade::render('@include("admin.dotacion-establecimiento.partials._reserva_no_normativa") @stack("styles") @stack("scripts")', [
            'proceso2027Asignacion' => $proceso, 'asignaciones' => collect(),
            'necesidades' => ['funciones' => []], 'asignacion2027Habilitada' => $habilitada,
            'establecimiento' => $establecimiento,
            'fmt' => fn ($horas) => DotacionEstablecimientoCalculator::formatHoras($horas),
        ]);
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }
}
