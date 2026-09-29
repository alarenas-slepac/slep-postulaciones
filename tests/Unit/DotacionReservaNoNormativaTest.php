<?php

namespace Tests\Unit;

use App\Support\DotacionReservaNoNormativa;
use Tests\TestCase;

class DotacionReservaNoNormativaTest extends TestCase
{
    public function test_traspasa_primero_saldos_titulares_y_limita_por_docente_bloque_y_establecimiento(): void
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
        $this->assertSame('titular', DotacionReservaNoNormativa::fase($elegibles));
        $this->assertSame(['111111111'], DotacionReservaNoNormativa::opciones($elegibles)->pluck('rut_normalizado')->all());
        $this->assertSame(4.0, DotacionReservaNoNormativa::maximoParaDocente($proceso, $elegibles->first(), 'titular'));

        $proceso['docentes'][0]['horas_titulares_disponibles'] = 0.99;
        $proceso['docentes'][0]['horas_disponibles'] = 0.99;
        $elegibles = DotacionReservaNoNormativa::elegibles($proceso);
        $this->assertSame('contrata', DotacionReservaNoNormativa::fase($elegibles));
        $this->assertSame(['222222222', '333333333'], DotacionReservaNoNormativa::opciones($elegibles)->pluck('rut_normalizado')->all());
        $this->assertSame(5.0, DotacionReservaNoNormativa::maximoParaDocente($proceso, $elegibles->first(), 'contrata'));
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
