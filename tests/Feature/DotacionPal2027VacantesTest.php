<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Exports\DotacionPal2027Export;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DotacionPal2027;
use App\Support\DotacionPalFilas2027;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionPal2027VacantesTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        $this->ee = Establecimiento::create(['cod_estab' => 99001, 'rbd' => 99001, 'nombre_establecimiento' => 'Escuela sintética']);
    }

    public function test_cupos_con_y_sin_asignaciones_y_directivo_se_incluyen_una_vez_en_ambos_excel(): void
    {
        $automatica = (new DotacionDocenteAsignacion)->forceFill(['id' => 0, 'docente_rut' => 'POR ASUMIR',
            'docente_rut_normalizado' => 'DIRECTOR_ADP_POR_ASUMIR_1', 'asignacion_automatica' => true,
            'dotacion_funcion_regla_id' => 7, 'tipo_asignacion' => 'funcion_directiva', 'subvencion' => 'General',
            'horas_contrato' => 44, 'estado' => 'activa']);
        $cupo = $this->cupo(1, 12, ['asignaciones' => [$this->aula(10, 9)]]);
        $datos = ['docentes' => [$cupo], 'asignacion' => ['docentes' => [$cupo, $this->cupo(2, 5.5)],
            'plazas_por_asumir' => [['es_plaza_por_asumir' => true, 'anio' => 2027, 'nombre' => 'Docente Directivo por asumir',
                'horas_contrato' => 44, 'asignaciones' => collect([$automatica])]]]];
        $antes = $automatica->getAttributes(); $snapshot = json_encode($datos);
        $report = $this->reporte($datos);
        $this->assertSame(0, $report['personas']); $this->assertSame(2, $report['cupos_por_contratar']);
        $this->assertSame(1, $report['plazas_por_asumir']); $this->assertSame([], $report['excluidos']);
        $this->assertSame(17.5, $report['horas_cupos_por_contratar']); $this->assertSame(44.0, $report['horas_plazas_por_asumir']);
        $this->assertSame($antes, $automatica->getAttributes()); $this->assertSame($snapshot, json_encode($datos));
        $identidades = array_values(array_unique(array_column($report['docentes'], 'A')));
        $this->assertSame(['VACANTE-1', 'VACANTE-2', 'VACANTE-ADP-'.$this->ee->id.'-7'], $identidades);
        foreach ($report['docentes'] as $fila) {
            $this->assertSame('Plazo Fijo', $fila['H']); $this->assertStringContainsString('RUT sintético', $fila['S']);
        }
        foreach ([false, true] as $global) {
            $path = Storage::disk('local')->path($global ? 'vacantes-global.xlsx' : 'vacantes.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)', 'Tabla 65-35', 'Tabla 60-40', 'Excluidos PAL 2027'])->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)'); $totales = []; $directiva = 0;
                foreach ($report['docentes'] as $i => $fila) {
                    $row = $i + 3; $rut = (string) $sheet->getCell('A'.$row)->getValue();
                    $totales[$rut] = ($totales[$rut] ?? 0) + $sheet->getCell('J'.$row)->getCalculatedValue();
                    $this->assertStringStartsWith('=', $sheet->getCell('J'.$row)->getValue());
                    $this->assertTrue($sheet->getStyle('A'.$row)->getAlignment()->getWrapText());
                    if (str_starts_with($rut, 'VACANTE-ADP-')) {
                        $this->assertSame('Función Directiva', (string) $sheet->getCell('G'.$row)->getValue());
                        $directiva += $sheet->getCell('J'.$row)->getCalculatedValue();
                    }
                }
                $this->assertEquals(['VACANTE-1' => 12, 'VACANTE-2' => 5.5, 'VACANTE-ADP-'.$this->ee->id.'-7' => 44], $totales);
                $this->assertEquals(44, $directiva);
                $this->assertNull($book->getSheetByName('Excluidos PAL 2027')->getCell('A5')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_identidades_estables_no_se_mezclan_con_rut_real_cupo_u_otro_establecimiento_y_regla(): void
    {
        $datos = ['docentes' => [['rut' => '99000001-K', 'nombre' => 'Persona sintética', 'horas_contrato' => 3,
            'horas_planta' => 3, 'asignaciones' => []]], 'asignacion' => ['docentes' => [$this->cupo(7, 4)],
            'plazas_por_asumir' => [$this->plaza(7), $this->plaza(8), $this->plaza(7)]]];
        $a = $this->reporte($datos); $b = $this->reporte($datos);
        $this->assertSame($a, $b); $this->assertSame(1, $a['personas']);
        $this->assertSame(2, $a['plazas_por_asumir']);
        $ids = array_map([DotacionPal2027::class, 'identidad'], array_column($a['docentes'], 'A'));
        $this->assertCount(4, array_unique($ids));
        $otra = clone $this->ee; $otra->id = 99; $otra->rbd = 99002;
        $c = app(DotacionPal2027::class)->construir($otra, $datos, app(DotacionPal2027Export::class)->tablas());
        $this->assertStringStartsWith('VACANTE-ADP-99-', collect($c['docentes'])->first(fn ($f) => str_starts_with($f['A'], 'VACANTE-ADP-'))['A']);
        $this->assertEquals(4, count((new DotacionPalFilas2027)->consolidar($a['docentes'], app(DotacionPal2027Export::class)->tablas())));
    }

    public function test_copia_la_cobertura_stdclass_que_entrega_el_calculador_sin_modificarla(): void
    {
        $a = (object) ['docente_rut' => 'POR ASUMIR', 'docente_rut_normalizado' => 'DIRECTOR_ADP_POR_ASUMIR_1',
            'asignacion_automatica' => true, 'dotacion_funcion_regla_id' => 7, 'tipo_asignacion' => 'funcion_directiva',
            'subvencion' => 'General', 'horas_contrato' => 44, 'estado' => 'activa'];
        $antes = serialize($a);
        $report = $this->reporte(['asignacion' => ['plazas_por_asumir' => [['anio' => 2027,
            'horas_contrato' => 44, 'asignaciones' => collect([$a])]]]]);
        $this->assertSame($antes, serialize($a));
        $this->assertSame(1, $report['plazas_por_asumir']); $this->assertCount(1, $report['docentes']);
        $this->assertSame('Función Directiva', $report['docentes'][0]['G']); $this->assertSame(44.0, $report['docentes'][0]['R']);
    }

    public function test_ignora_cupos_y_plazas_de_otro_anio_y_asistentes_y_mantiene_base_del_error_virtual(): void
    {
        $datos = ['asignacion' => ['docentes' => [$this->cupo(1, 10, ['anio' => 2026]), $this->cupo(2, 10, ['estamento_cobertura' => 'asistente']),
            $this->cupo(3, 4, ['asignaciones' => [$this->aula(4, 3, 'Financiamiento sin equivalencia')]])],
            'plazas_por_asumir' => [$this->plaza(7, ['anio' => 2026]), $this->plaza(8, ['estamento_cobertura' => 'asistente'])]]];
        $report = $this->reporte($datos);
        $this->assertSame(1, $report['cupos_por_contratar']); $this->assertSame(0, $report['plazas_por_asumir']);
        $this->assertCount(1, $report['excluidos']); $this->assertSame('Cupo por contratar', $report['excluidos'][0]['base']);
        $this->assertSame('VACANTE-3', $report['excluidos'][0]['rut']);
        $this->assertSame('Docente por contratar #3', $report['excluidos'][0]['nombre']);
        $this->assertEquals(4, $report['excluidos'][0]['horas']);
    }

    public function test_redondeo_de_aula_en_limites_y_cero_conserva_otras_horas_y_formulas(): void
    {
        $tablas = app(DotacionPal2027Export::class)->tablas(); $filas = []; $esperados = [];
        foreach (['65/35', '60/40'] as $proporcion) {
            foreach ([0.01, 0.49, 0.5, 0.51, 1.49, 1.5, 10.49, 10.5, 10.75, 24.25, 43.5] as $horas) {
                // Identidad independiente para probar cada tramo sin consolidarlos.
                $rut = '9900'.str_pad((string) count($filas), 4, '0', STR_PAD_LEFT).'-K';
                $entrada = ['A' => $rut, 'B' => 'Escuela sintética', 'C' => '99001', 'G' => 'Docente aula', 'H' => 'Titular',
                    'K' => $horas * 0.83, 'L' => null, 'M' => $proporcion, 'P' => 2, 'Q' => 1, 'R' => 0.25, 'S' => 'Prueba sintética.', '_pal_contrato_aula' => $horas];
                $out = (new DotacionPalFilas2027)->consolidar([$entrada], $tablas);
                $esperados[$rut] = round($horas, 0, PHP_ROUND_HALF_UP) + 3.25;
                foreach ($out as $row) {
                    $this->assertStringContainsString('Redondeo PAL del contrato de aula:', $row['S']);
                    $this->assertArrayNotHasKey('_pal_exclusiones', $row);
                }
                array_push($filas, ...$out);
            }
        }
        $path = Storage::disk('local')->path('redondeo.xlsx');
        app(DotacionPal2027Export::class)->guardar(['docentes' => $filas, 'cursos' => []], $path);
        $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)', 'Tabla 65-35', 'Tabla 60-40'])->load($path);
        try {
            $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)'); $sumas = [];
            foreach ($filas as $i => $fila) { $r = $i + 3; $sumas[$fila['A']] = ($sumas[$fila['A']] ?? 0) + $sheet->getCell('J'.$r)->getCalculatedValue(); }
            $this->assertEquals($esperados, $sumas);
        } finally { $book->disconnectWorksheets(); }
        $this->assertSame(1, DB::table('establecimientos')->count());
    }

    private function reporte(array $datos): array
    {
        return app(DotacionPal2027::class)->construir($this->ee, $datos, app(DotacionPal2027Export::class)->tablas());
    }

    private function cupo(int $id, float $horas, array $extra = []): array
    {
        return $extra + ['cupo_contrata_id' => $id, 'cupo_bloque' => 'plan_estudio', 'anio' => 2027, 'horas_contrato' => $horas,
            'nombre' => 'Docente por contratar #'.$id, 'asignaciones' => []];
    }

    private function aula(float $contrato, float $aula, string $subvencion = 'General'): array
    {
        return ['id' => 1, 'tipo_asignacion' => 'plan_estudio', 'subvencion' => $subvencion, 'estado' => 'activa',
            'horas_contrato' => $contrato, 'horas_plan_pedagogicas' => $aula, 'proporcion_aplicada' => '65/35',
            'establecimientoCurso' => ['curso' => ['codigo' => '1B']]];
    }

    private function plaza(int $regla, array $extra = []): array
    {
        return $extra + ['es_plaza_por_asumir' => true, 'anio' => 2027, 'horas_contrato' => 44, 'asignaciones' => [
            ['dotacion_funcion_regla_id' => $regla, 'tipo_asignacion' => 'funcion_directiva', 'subvencion' => 'General',
                'horas_contrato' => 44, 'estado' => 'activa', 'asignacion_automatica' => true, 'docente_rut' => 'POR ASUMIR'],
        ]];
    }
}
