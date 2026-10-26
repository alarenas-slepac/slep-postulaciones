<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Exports\DotacionPal2027Export;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionPal2027;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionPal2027DocenciaConsolidadaTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        $this->ee = Establecimiento::create(['cod_estab' => 99001, 'rbd' => 99001, 'nombre_establecimiento' => 'Escuela sintética']);
    }

    public function test_religion_4_y_8_horas_pedagogicas_se_validan_juntas_como_14_de_contrato_en_ambos_excel(): void
    {
        $doc = $this->docente();
        $antes = json_encode($doc); $establecimientos = DB::table('establecimientos')->get()->toJson();
        $report = $this->reporte($doc);
        $this->assertCount(2, $report['docentes']); $this->assertSame(0, $report['incompatibles']);
        $this->assertSame(1.0, $report['docentes'][1]['R']);
        $this->assertSame(1, $report['personas']);
        $fila = $report['docentes'][0];
        $this->assertSame('Plazo Fijo', $fila['H']); $this->assertSame('Docente aula', $fila['G']);
        $this->assertSame('Ed. Básica/Media', $fila['D']); $this->assertSame('65/35', $fila['M']);
        $this->assertSame(4.0, $fila['K']); $this->assertSame(8.0, $fila['L']); $this->assertNull($fila['R']);
        $this->assertStringContainsString('Asignación SGA: 4 h cronológicas', $fila['S']);
        $this->assertStringContainsString('Asignación SGA: 10 h cronológicas', $fila['S']);
        $this->assertStringContainsString('12 h pedagógicas / 14 h de contrato SGA', $fila['S']);
        $this->assertStringContainsString('Pendientes de distribución del docente: 1 h', $fila['S']);
        $this->assertStringNotContainsString('INCOMPATIBLE:', $fila['S']);
        $this->assertArrayNotHasKey('_pal_contrato_aula', $fila);
        $this->assertSame($antes, json_encode($doc));
        $this->assertSame($establecimientos, DB::table('establecimientos')->get()->toJson());
        foreach ([false, true] as $global) {
            $path = Storage::disk('local')->path($global ? 'religion-consolidada-todos.xlsx' : 'religion-consolidada.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)', 'Tabla 65-35', 'Tabla 60-40'])->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)');
                $this->assertEquals(4, $sheet->getCell('K3')->getValue()); $this->assertEquals(8, $sheet->getCell('L3')->getValue());
                $this->assertEquals(14, $sheet->getCell('J3')->getCalculatedValue());
                $this->assertEquals(9, $sheet->getCell('N3')->getCalculatedValue());
                $this->assertEquals(5, $sheet->getCell('O3')->getCalculatedValue());
                $this->assertStringStartsWith('=', $sheet->getCell('J3')->getValue());
                $this->assertStringNotContainsString('INCOMPATIBLE:', (string) $sheet->getCell('S3')->getValue());
                $this->assertEquals(1, $sheet->getCell('J4')->getCalculatedValue());
                $this->assertNull($sheet->getCell('A5')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_si_el_total_tampoco_coincide_se_convierten_los_tramos_sin_mezclar_niveles(): void
    {
        $report = $this->reporte($this->docente(['asignaciones' => [$this->asignacion('1B', 4, 4), $this->asignacion('1M', 8, 11)]]));
        $this->assertSame(0, $report['incompatibles']); $this->assertCount(3, $report['docentes']);
        foreach ($report['docentes'] as $fila) {
            $this->assertSame('65/35', $fila['M']);
            $this->assertStringContainsString('Conversión PAL de aula a 65/35:', $fila['S']);
            $this->assertArrayNotHasKey('_pal_contrato_aula', $fila);
        }
        $this->assertNull($report['docentes'][0]['L']); $this->assertNull($report['docentes'][1]['K']);
    }

    public function test_no_rescata_el_total_cruzando_titular_con_plazo_fijo(): void
    {
        $report = $this->reporte($this->docente(['horas_contrato_base' => 14, 'horas_contrato' => 14, 'horas_planta' => 4, 'horas_contrata' => 10]));
        $this->assertSame(['Titular', 'Plazo Fijo'], array_column($report['docentes'], 'H'));
        $this->assertSame(0, $report['incompatibles']);
    }

    public function test_6040_sigue_separada_aunque_su_contrato_conjunto_si_sea_representable(): void
    {
        $report = $this->reporte($this->docente(['asignaciones' => [
            $this->asignacion('1B', 4, 4, '60/40'), $this->asignacion('1M', 8, 11, '60/40'),
        ]]));
        $this->assertSame(0, $report['incompatibles']); $this->assertCount(2, $report['docentes']);
        foreach ($report['docentes'] as $fila) { $this->assertStringContainsString('60/40 queda excluida', $fila['S']); }
    }

    public function test_no_inventa_el_nivel_para_consolidar_y_conserva_los_limites_de_la_tabla(): void
    {
        $report = $this->reporte($this->docente(['asignaciones' => [
            $this->asignacion('EPJA_SIN_DETALLE', 4, 4), $this->asignacion('1M', 8, 10),
        ]]));
        $this->assertSame(1, $report['incompatibles']);
        $this->assertStringContainsString('falta desglose del nivel', $report['docentes'][0]['S']);
        $report = $this->reporte($this->docente(['horas_contrato_base' => 88, 'horas_contrato' => 88, 'horas_contrata' => 88,
            'asignaciones' => [$this->asignacion('1B', 38, 44), $this->asignacion('1M', 38, 44)]]));
        $this->assertCount(2, $report['docentes']); $this->assertSame(0, $report['incompatibles']);
        $this->assertSame([38.0, null], array_column($report['docentes'], 'K'));
        $this->assertSame([null, 38.0], array_column($report['docentes'], 'L'));
    }

    private function reporte(array $doc): array
    {
        return app(DotacionPal2027::class)->construir($this->ee, ['docentes' => [$doc]], app(DotacionPal2027Export::class)->tablas());
    }

    private function docente(array $cambios = []): array
    {
        return array_replace(['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Sin título declarado',
            'funcion' => 'DOCENTE', 'horas_contrato_base' => 15, 'horas_contrato' => 15, 'horas_planta' => 0,
            'horas_contrata' => 15, 'niveles_declarados' => ['Básica', 'Media'],
            'asignaciones' => [$this->asignacion('1B', 4, 4), $this->asignacion('1M', 8, 10)]], $cambios);
    }

    private function asignacion(string $codigo, float $pedagogicas, float $contrato, string $proporcion = '65/35'): DotacionDocenteAsignacion
    {
        $curso = (new EstablecimientoCurso)->setRelation('curso', (new Curso)->forceFill(['codigo' => $codigo]));
        return (new DotacionDocenteAsignacion)->forceFill(['id' => $codigo === '1B' ? 1 : 2, 'docente_rut' => '99000001-K',
            'tipo_asignacion' => 'plan_estudio', 'subvencion' => 'General', 'asignatura_nombre' => 'Religión',
            'horas_contrato' => $contrato, 'horas_plan_pedagogicas' => $pedagogicas, 'proporcion_aplicada' => $proporcion, 'estado' => 'activa'])
            ->setRelation('establecimientoCurso', $curso);
    }
}
