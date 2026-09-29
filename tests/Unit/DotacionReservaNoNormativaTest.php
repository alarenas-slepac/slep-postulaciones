<?php

namespace Tests\Unit;

use App\Models\Establecimiento;
use App\Support\DotacionReservaNoNormativa;
use Tests\TestCase;

class DotacionReservaNoNormativaTest extends TestCase
{
    public function test_traspasa_solo_saldos_titulares_y_limita_por_docente_bloque_y_establecimiento(): void
    {
        $proceso = [
            'capacidad_reserva_no_normativa' => 8.0,
            'bloques' => [
                'bloque_1' => ['saldo_maximo' => 5.0],
                'bloque_2' => ['saldo_maximo' => 0.0],
                'bloque_3' => ['saldo_maximo' => 0.0],
            ],
            'docentes' => [
                $this->docente('111111111', 4, 0, 4),
                $this->docente('222222222', 0, 10, 10),
                $this->docente('333333333', 0.37, 8, 8.37),
            ],
        ];

        $elegibles = DotacionReservaNoNormativa::elegibles($proceso);
        $this->assertSame(['111111111'], $elegibles->pluck('rut_normalizado')->all());
        $this->assertSame('titular', DotacionReservaNoNormativa::fase($elegibles));
        $this->assertSame(['111111111'], DotacionReservaNoNormativa::opciones($elegibles)->pluck('rut_normalizado')->all());
        $this->assertSame(4.0, DotacionReservaNoNormativa::maximoParaDocente($proceso, $elegibles->first(), 'titular'));

        $proceso['docentes'][0]['horas_titulares_disponibles'] = 0.99;
        $proceso['docentes'][0]['horas_disponibles'] = 0.99;
        $elegibles = DotacionReservaNoNormativa::elegibles($proceso);
        $this->assertTrue($elegibles->isEmpty());
        $this->assertSame('titular', DotacionReservaNoNormativa::fase($elegibles));
        $this->assertTrue(DotacionReservaNoNormativa::opciones($elegibles)->isEmpty());
        $this->assertSame(0.0, DotacionReservaNoNormativa::maximoParaDocente($proceso, $proceso['docentes'][1], 'contrata'));
    }

    public function test_oculta_el_formulario_cuando_solo_quedan_horas_a_contrata(): void
    {
        $establecimiento = new Establecimiento(['nombre_establecimiento' => 'Establecimiento sintético']);
        $establecimiento->id = 1;
        $html = view('admin.dotacion-establecimiento.partials._reserva_no_normativa', [
            'proceso2027Asignacion' => [
                'capacidad_reserva_no_normativa' => 3.0,
                'bloques' => ['bloque_1' => ['saldo_maximo' => 3.0]],
                'docentes' => [$this->docente('111111111', 0, 3, 3)],
            ],
            'asignaciones' => collect(),
            'necesidades' => ['funciones' => []],
            'asignacion2027Habilitada' => true,
            'establecimiento' => $establecimiento,
            'fmt' => fn ($horas) => (string) $horas,
        ])->render();

        $this->assertStringContainsString('Las horas a contrata no se pueden reservar', $html);
        $this->assertStringNotContainsString('name="docente_rut"', $html);
    }

    private function docente(string $rut, float $titular, float $contrata, float $total): array
    {
        return [
            'rut' => $rut,
            'rut_normalizado' => $rut,
            'nombre' => 'Docente sintético',
            'titulo' => 'Pedagogía en Educación Básica',
            'horas_disponibles' => $total,
            'horas_titulares_disponibles' => $titular,
            'horas_contrata_disponibles' => $contrata,
        ];
    }
}
