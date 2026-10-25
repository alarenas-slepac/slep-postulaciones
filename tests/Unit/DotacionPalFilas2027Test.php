<?php

namespace Tests\Unit;

use App\Support\DotacionPalFilas2027;
use PHPUnit\Framework\TestCase;

class DotacionPalFilas2027Test extends TestCase
{
    public function test_suma_columnas_y_conserva_notas_sin_modificar_las_filas_originales(): void
    {
        $filas = [$this->fila(['K' => 5, 'S' => 'Primer origen.']),
            $this->fila(['A' => '99.000.001-k', 'K' => 5, 'S' => 'Segundo origen.']),
            $this->fila(['M' => null, 'P' => 3, 'Q' => 2, 'R' => 1, 'S' => 'PIE y otras horas.'])];
        $antes = $filas;
        $out = (new DotacionPalFilas2027)->consolidar($filas, $this->tablas());
        $this->assertCount(1, $out); $this->assertSame($antes, $filas);
        $this->assertSame(10.0, $out[0]['K']); $this->assertNull($out[0]['L']);
        $this->assertSame(3.0, $out[0]['P']); $this->assertSame(2.0, $out[0]['Q']); $this->assertSame(1.0, $out[0]['R']);
        foreach (['Primer origen.', 'Segundo origen.', 'PIE y otras horas.', '3 filas de origen'] as $nota) {
            $this->assertStringContainsString($nota, $out[0]['S']);
        }
        $this->assertArrayNotHasKey('_pal_orden', $out[0]);
    }

    public function test_la_identidad_exige_docente_establecimiento_funcion_y_calidad_iguales(): void
    {
        $filas = [$this->fila(['P' => 1]), $this->fila(['A' => '99000002-8', 'P' => 2]),
            $this->fila(['C' => '99002', 'P' => 3]), $this->fila(['G' => 'Función Directiva', 'P' => 4]),
            $this->fila(['H' => 'Plazo Fijo', 'P' => 5])];
        $this->assertSame($filas, (new DotacionPalFilas2027)->consolidar($filas, $this->tablas()));
        $central = (new DotacionPalFilas2027)->consolidar([$this->fila(['C' => '0', 'P' => 1]), $this->fila(['C' => '0', 'P' => 2])], $this->tablas());
        $this->assertCount(1, $central); $this->assertSame(3.0, $central[0]['P']);
    }

    public function test_no_mezcla_proporciones_ni_niveles_sin_opcion_en_la_plantilla(): void
    {
        foreach ([['M' => '60/40'], ['D' => 'Educación Parvularia'], ['E' => 'Educadora de Parvulos'],
            ['F' => 'Otro cargo'], ['I' => 'si'], ['S' => 'INCOMPATIBLE: revisar asignación.']] as $diferente) {
            $out = (new DotacionPalFilas2027)->consolidar([$this->fila(['K' => 5]), $this->fila(['K' => 5, ...$diferente])], $this->tablas());
            $this->assertCount(2, $out);
            $this->assertStringContainsString('se conservan filas separadas', $out[0]['S']);
        }
    }

    public function test_compara_lectivas_y_no_lectivas_aunque_el_contrato_total_coincida(): void
    {
        $tablas = $this->tablas();
        $tablas['65/35']['10.0000'] = ['contrato' => 12, 'lectivas' => 7.6, 'no_lectivas' => 4.4];
        $out = (new DotacionPalFilas2027)->consolidar([$this->fila(['K' => 5]), $this->fila(['K' => 5])], $tablas);
        $this->assertCount(2, $out);
        $this->assertSame([5, 5], array_column($out, 'K'));
    }

    public function test_no_consolida_dos_filas_6040_ni_les_agrega_pie_aunque_la_tabla_lo_admita(): void
    {
        $out = (new DotacionPalFilas2027)->consolidar([
            $this->fila(['M' => '60/40', 'K' => 5]),
            $this->fila(['M' => '60/40', 'D' => 'Educación Media', 'L' => 5]),
            $this->fila(['M' => null, 'P' => 3]),
        ], $this->tablas());
        $this->assertCount(3, $out);
        $this->assertSame(['60/40', '60/40', null], array_column($out, 'M'));
        $this->assertSame([5, null, null], array_column($out, 'K'));
        $this->assertSame([null, 5, null], array_column($out, 'L'));
        $this->assertSame([null, null, 3], array_column($out, 'P'));
        foreach (array_slice($out, 0, 2) as $row) {
            $this->assertStringContainsString('60/40 queda excluida', $row['S']);
        }
    }

    public function test_busca_k_mas_l_y_no_consolida_si_el_total_no_existe_en_la_tabla(): void
    {
        $out = (new DotacionPalFilas2027)->consolidar([$this->fila(['K' => 10]),
            $this->fila(['D' => 'Educación Media', 'L' => 15])], $this->tablas());
        $this->assertCount(2, $out);
        $out = (new DotacionPalFilas2027)->consolidar([$this->fila(['K' => 5]),
            $this->fila(['D' => 'Educación Media', 'L' => 5])], $this->tablas());
        $this->assertCount(1, $out); $this->assertSame('Ed. Básica/Media', $out[0]['D']);
        $this->assertSame(5.0, $out[0]['K']); $this->assertSame(5.0, $out[0]['L']);
    }

    public function test_consolida_subconjuntos_sin_mover_las_otras_funciones(): void
    {
        $out = (new DotacionPalFilas2027)->consolidar([$this->fila(['K' => 10]),
            $this->fila(['G' => 'Función Directiva', 'P' => 3]), $this->fila(['K' => 10]), $this->fila(['K' => 10])], $this->tablas());
        $this->assertCount(3, $out);
        $this->assertSame([20.0, null, 10], array_column($out, 'K'));
        $this->assertSame('Función Directiva', $out[1]['G']);
        $this->assertStringContainsString('2 filas de origen', $out[0]['S']);
    }

    public function test_intenta_el_grupo_completo_y_no_inventa_identidad_ni_equivalencias(): void
    {
        $tablas = $this->tablas(); unset($tablas['65/35']['10.0000']);
        $out = (new DotacionPalFilas2027)->consolidar(array_fill(0, 3, $this->fila(['K' => 5])), $tablas);
        $this->assertCount(1, $out); $this->assertSame(15.0, $out[0]['K']);
        $sinRut = array_fill(0, 2, $this->fila(['A' => '', 'P' => 3]));
        $this->assertSame($sinRut, (new DotacionPalFilas2027)->consolidar($sinRut, $tablas));
        $out = (new DotacionPalFilas2027)->consolidar(array_fill(0, 2, $this->fila(['K' => 5])), []);
        $this->assertCount(2, $out);
    }

    private function fila(array $cambios = []): array
    {
        return array_replace(array_fill_keys(['A','B','C','D','E','F','G','H','I','K','L','M','P','Q','R','S'], null),
            ['A' => '99000001-K', 'B' => 'Escuela sintética', 'C' => '99001', 'D' => 'Educación Básica',
                'E' => 'Docente', 'F' => 'Docente', 'G' => 'Docente aula', 'H' => 'Titular', 'M' => '65/35', 'S' => 'Referencia sintética.'], $cambios);
    }

    private function tablas(): array
    {
        $tabla = [];
        foreach ([5, 10, 15, 20] as $aula) {
            $tabla[number_format($aula, 4, '.', '')] = ['contrato' => $aula * 1.2, 'lectivas' => $aula * 0.75, 'no_lectivas' => $aula * 0.45];
        }
        return ['65/35' => $tabla, '60/40' => $tabla];
    }
}
