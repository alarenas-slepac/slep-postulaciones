<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Exports\DotacionPal2027Export;
use App\Models\Establecimiento;
use App\Support\DotacionPal2027;
use App\Support\DotacionPalFilas2027;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionPal2027AulaEquivalenteTest extends IsolatedSecurityTestCase
{
    public function test_6535_y_6040_conservan_contrato_formulas_y_origen_real_en_ambos_excel(): void
    {
        $tabla = app(DotacionPal2027Export::class)->tablas(); $filas = []; $esperados = [];
        foreach (['65/35', '60/40'] as $proporcion) {
            foreach ([1, 2, 3, 4, 7, 12, 23, 25, 27, 32, 33, 39, 40, 41, 42, 44] as $horas) {
                $rut = '99000'.str_pad((string) count($filas), 3, '0', STR_PAD_LEFT).'-K';
                $fila = $this->fila(['A' => $rut, 'K' => $horas * 0.83, 'M' => $proporcion, '_pal_contrato_aula' => $horas]);
                $antes = $fila;
                $convertidas = (new DotacionPalFilas2027)->consolidar([$fila], $tabla);
                $this->assertSame($antes, $fila);
                foreach ($convertidas as $row) {
                    $this->assertSame($proporcion, $row['M']);
                    $this->assertStringContainsString('h pedagógicas reales', $row['S']);
                    $this->assertStringNotContainsString('INCOMPATIBLE:', $row['S']);
                    $this->assertArrayNotHasKey('_pal_contrato_aula', $row);
                }
                array_push($filas, ...$convertidas); $esperados[$rut] = $horas;
            }
        }
        foreach ([false, true] as $global) {
            $report = ['docentes' => $filas, 'cursos' => [], 'excluidos' => []];
            $path = Storage::disk('local')->path($global ? 'aula-global.xlsx' : 'aula.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)', 'Tabla 65-35', 'Tabla 60-40'])->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)'); $sumas = [];
                foreach ($filas as $i => $fila) {
                    $r = $i + 3; $rut = $fila['A'];
                    $j = $sheet->getCell('J'.$r)->getCalculatedValue();
                    $this->assertIsNumeric($j);
                    $sumas[$rut] = ($sumas[$rut] ?? 0) + $j;
                    foreach (['J', 'N', 'O'] as $col) { $this->assertStringStartsWith('=', $sheet->getCell($col.$r)->getValue()); }
                }
                $this->assertEquals($esperados, $sumas);
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_al_dividir_40_h_no_repite_pie_sep_ni_otras_horas(): void
    {
        $filas = (new DotacionPalFilas2027)->consolidar([$this->fila(['K' => 35, 'M' => '65/35',
            'P' => 3, 'Q' => 2, 'R' => 4, '_pal_contrato_aula' => 40])], app(DotacionPal2027Export::class)->tablas());
        $this->assertCount(2, $filas);
        $this->assertSame([3, null], array_column($filas, 'P')); $this->assertSame([2, null], array_column($filas, 'Q'));
        $this->assertSame([4, null], array_column($filas, 'R')); $this->assertSame([34.0, 1.0], array_column($filas, 'K'));
    }

    public function test_trunca_centesimas_con_regla_explicita_y_conserva_contrato_original_en_observaciones(): void
    {
        $filas = (new DotacionPalFilas2027)->consolidar([$this->fila(['K' => 1, 'M' => '65/35', '_pal_contrato_aula' => 1.01])],
            app(DotacionPal2027Export::class)->tablas());
        $this->assertEquals(1, $filas[0]['K']);
        $this->assertStringContainsString('1.01 h SGA → 1 h PAL', $filas[0]['S']);
        $this->assertArrayNotHasKey('_pal_exclusiones', $filas[0]);
    }

    public function test_cuartos_de_hora_se_redondean_separados_por_calidad_y_se_incorporan(): void
    {
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        $ee = Establecimiento::create(['cod_estab' => 99001, 'rbd' => 99001, 'nombre_establecimiento' => 'Escuela sintética']);
        $doc = ['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'horas_contrato_base' => 44, 'horas_contrato' => 44,
            'horas_planta' => 12, 'horas_contrata' => 32, 'niveles_declarados' => ['Básica'], 'asignaciones' => [
                ['id' => 1, 'estado' => 'activa', 'tipo_asignacion' => 'plan_estudio', 'subvencion' => 'General', 'proporcion_aplicada' => '65/35',
                    'horas_contrato' => 35, 'horas_plan_pedagogicas' => 30, 'establecimientoCurso' => ['curso' => ['codigo' => '1B']]],
                ['id' => 2, 'estado' => 'activa', 'tipo_asignacion' => 'reserva_no_normativa', 'subtipo_asignacion' => 'titular',
                    'subvencion' => 'General', 'horas_contrato' => 1.25],
            ]];
        $report = app(DotacionPal2027::class)->construir($ee, ['docentes' => [$doc]], app(DotacionPal2027Export::class)->tablas());
        $this->assertSame(0, $report['incompatibles']); $this->assertCount(0, $report['excluidos']);
        $tabla = app(DotacionPal2027Export::class)->tablas()['65/35']; $sumas = [];
        foreach ($report['docentes'] as $fila) {
            if ($fila['M'] === null) { continue; }
            $sumas[$fila['H']] = ($sumas[$fila['H']] ?? 0) + $tabla[number_format((float) $fila['K'] + (float) $fila['L'], 4, '.', '')]['contrato'];
            $this->assertStringContainsString($fila['H'] === 'Titular' ? '10.75 h SGA → 11 h PAL' : '24.25 h SGA → 24 h PAL', $fila['S']);
        }
        $this->assertEquals(['Titular' => 11, 'Plazo Fijo' => 24], $sumas);
        $this->assertEquals(9, array_sum(array_column($report['docentes'], 'R')));
    }

    private function fila(array $extra): array
    {
        return $extra + ['A' => '99000001-K', 'B' => 'Escuela sintética', 'C' => '99001', 'D' => 'Educación Básica',
            'E' => 'Docente', 'F' => 'Docente', 'G' => 'Docente aula', 'H' => 'Titular', 'S' => 'Origen sintético.',
            'K' => null, 'L' => null, 'M' => null, 'P' => null, 'Q' => null, 'R' => null];
    }
}
