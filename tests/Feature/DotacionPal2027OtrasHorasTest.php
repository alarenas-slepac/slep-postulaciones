<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Exports\DotacionPal2027Export;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DotacionPal2027;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionPal2027OtrasHorasTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        $this->ee = Establecimiento::create(['cod_estab' => 99001, 'rbd' => 99001, 'nombre_establecimiento' => 'Escuela sintética']);
    }

    public function test_saldo_sin_asignar_solo_se_incorpora_para_titulares(): void
    {
        $doc = $this->docente(['horas_planta' => 20, 'horas_contrata' => 24, 'asignaciones' => []]);
        $antes = json_encode($doc);
        $report = $this->reporte($doc);
        $this->assertCount(1, $report['docentes']);
        $row = $report['docentes'][0];
        $this->assertSame('Titular', $row['H']); $this->assertSame(20.0, $row['R']);
        $this->assertSame('Otros (Talleristas / Apoyo / etc.)', $row['G']);
        $this->assertStringContainsString('20 h titulares sin asignación', $row['S']);
        $this->assertSame($antes, json_encode($doc));
        $row = $this->reporte($this->docente(['horas_planta' => 0, 'horas_contrata' => 44, 'asignaciones' => []]))['docentes'][0];
        $this->assertSame('Plazo Fijo', $row['H']); $this->assertSame('', $row['G']); $this->assertNull($row['R']);
    }

    public function test_situacion_se_separa_por_calidad_y_suma_el_contrato_en_los_dos_excel(): void
    {
        $doc = $this->docente(['horas_planta' => 32, 'horas_contrata' => 12, 'horas_contrato' => 20, 'horas_excluidas' => 24,
            'exclusion_docente' => ['motivo' => 'traslado', 'horas' => 24], 'asignaciones' => [$this->asignacion(20)]]);
        $report = $this->reporte($doc); $rows = $report['docentes'];
        $this->assertSame(['Titular', 'Titular', 'Plazo Fijo'], array_column($rows, 'H'));
        $this->assertSame([20.0, 12.0, 12.0], array_column($rows, 'R'));
        $this->assertSame(['Función Directiva', 'Otros (Talleristas / Apoyo / etc.)', 'Otros (Talleristas / Apoyo / etc.)'], array_column($rows, 'G'));
        $this->assertSame(1, $report['personas']); $this->assertSame(0, $report['incompatibles']);
        foreach ([false, true] as $global) {
            $path = Storage::disk('local')->path($global ? 'otras-horas-todos.xlsx' : 'otras-horas.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)'])->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)');
                $total = 0;
                foreach ([3 => 20, 4 => 12, 5 => 12] as $r => $horas) {
                    $total += $sheet->getCell('J'.$r)->getCalculatedValue();
                    $this->assertEquals($horas, $sheet->getCell('J'.$r)->getCalculatedValue());
                    $this->assertStringStartsWith('=', $sheet->getCell('J'.$r)->getValue());
                }
                $this->assertEquals(44, $total);
                foreach ([4, 5] as $r) {
                    $this->assertSame('Otros (Talleristas / Apoyo / etc.)', (string) $sheet->getCell('G'.$r)->getValue());
                    $this->assertStringContainsString('12 h por situación 2027: Traslado', (string) $sheet->getCell('S'.$r)->getValue());
                }
                $this->assertNull($sheet->getCell('A6')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_saldo_contrata_no_se_incorpora_aunque_exista_una_situacion(): void
    {
        $doc = $this->docente(['horas_planta' => 20, 'horas_contrata' => 24, 'horas_contrato' => 30, 'horas_excluidas' => 14,
            'exclusion_docente' => ['motivo' => 'horas_gremiales', 'horas' => 14],
            'asignaciones' => [$this->asignacion(25), $this->asignacion(3, 'pie_colaborativo', 'PIE')]]);
        $rows = $this->reporte($doc)['docentes'];
        $otros = array_values(array_filter($rows, fn ($r) => $r['G'] === 'Otros (Talleristas / Apoyo / etc.)'));
        $this->assertCount(1, $otros); $this->assertSame('Plazo Fijo', $otros[0]['H']);
        $this->assertSame(14.0, $otros[0]['R']);
        $this->assertStringNotContainsString('2 h titulares sin asignación', $otros[0]['S']);
        $this->assertEquals(42, array_sum(array_column($rows, 'R')) + array_sum(array_column($rows, 'P')));
    }

    public function test_descuenta_solo_justificaciones_titulares_del_establecimiento_y_2027(): void
    {
        $this->justificar(['bloque' => 'plan_estudio', 'horas_detectadas' => 4, 'docente_rut_normalizado' => '99.000.001-k']);
        $this->justificar(['bloque' => 'pie', 'horas_detectadas' => 3]);
        $this->justificar(['establecimiento_id' => 999, 'horas_detectadas' => 40]);
        $this->justificar(['anio' => 2026, 'horas_detectadas' => 40]);
        $this->justificar(['tipo_horas' => 'contrata', 'horas_detectadas' => 40]);
        $this->justificar(['bloque' => 'parvularia', 'justificacion' => '  ', 'horas_detectadas' => 40]);
        $this->justificar(['bloque' => 'otro', 'horas_detectadas' => 40]);
        $this->justificar(['horas_detectadas' => 0]);
        $antes = DB::table('dotacion_sobredotacion_justificaciones')->orderBy('id')->get()->toJson();
        $rows = $this->reporte($this->docente(['horas_planta' => 32, 'horas_contrata' => 12]))['docentes'];
        $this->assertCount(2, $rows); $this->assertSame(5.0, $rows[1]['R']);
        $this->assertSame('Titular', $rows[1]['H']);
        $this->assertStringContainsString('7 h titulares justificadas excluidas', $rows[1]['S']);
        $this->assertEquals(25, array_sum(array_column($rows, 'R')));
        $this->assertSame($antes, DB::table('dotacion_sobredotacion_justificaciones')->orderBy('id')->get()->toJson());
    }

    public function test_justificacion_se_limita_al_saldo_y_no_resta_asignaciones_ni_situaciones(): void
    {
        $this->justificar(['horas_detectadas' => 50]);
        $rows = $this->reporte($this->docente(['asignaciones' => [$this->asignacion(30)]]))['docentes'];
        $this->assertCount(1, $rows); $this->assertSame(30.0, $rows[0]['R']);
        $this->assertStringContainsString('14 h titulares justificadas excluidas', $rows[0]['S']);
        $doc = $this->docente(['horas_planta' => 20, 'horas_contrata' => 24, 'horas_contrato' => 0, 'horas_excluidas' => 44,
            'exclusion_docente' => ['motivo' => 'sumario_administrativo', 'horas' => 44], 'asignaciones' => []]);
        $rows = $this->reporte($doc)['docentes'];
        $this->assertSame(['Titular', 'Plazo Fijo'], array_column($rows, 'H'));
        $this->assertSame([20.0, 24.0], array_column($rows, 'R'));
    }

    public function test_otros_existente_se_consolida_sin_duplicar_situacion_y_excesos_siguen_marcados(): void
    {
        $doc = $this->docente(['horas_contrato' => 20, 'horas_excluidas' => 24,
            'exclusion_docente' => ['motivo' => 'horas_lactancia', 'horas' => 24],
            'asignaciones' => [$this->asignacion(20, 'otra_funcion')]]);
        $rows = $this->reporte($doc)['docentes'];
        $this->assertCount(1, $rows); $this->assertSame(44.0, $rows[0]['R']);
        $this->assertStringContainsString('24 h por situación 2027: Horas de lactancia', $rows[0]['S']);
        $doc['asignaciones'] = [$this->asignacion(30)];
        $report = $this->reporte($doc);
        $this->assertSame(1, $report['incompatibles']);
        $this->assertEquals(44, array_sum(array_column($report['docentes'], 'R')));
    }

    public function test_contrato_inconsistente_cancela_el_informe_sin_inventar_horas(): void
    {
        $this->expectException(ValidationException::class);
        $this->reporte($this->docente(['horas_contrato' => 20, 'horas_excluidas' => 25,
            'exclusion_docente' => ['motivo' => 'traslado', 'horas' => 25]]));
    }

    private function reporte(array $doc): array
    {
        return app(DotacionPal2027::class)->construir($this->ee, ['docentes' => [$doc]], app(DotacionPal2027Export::class)->tablas());
    }

    private function docente(array $cambios = []): array
    {
        return array_replace(['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Sin título declarado',
            'funcion' => 'Docente', 'horas_contrato_base' => 44, 'horas_contrato' => 44, 'horas_planta' => 44,
            'horas_contrata' => 0, 'niveles_declarados' => ['Básica'], 'asignaciones' => [$this->asignacion(20)]], $cambios);
    }

    private function asignacion(float $horas, string $tipo = 'funcion_directiva', string $fondos = 'General'): DotacionDocenteAsignacion
    {
        return (new DotacionDocenteAsignacion)->forceFill(['id' => 1, 'docente_rut' => '99000001-K', 'tipo_asignacion' => $tipo,
            'subvencion' => $fondos, 'horas_contrato' => $horas, 'horas_plan_pedagogicas' => 0, 'estado' => 'activa'])
            ->setRelation('establecimientoCurso', null);
    }

    private function justificar(array $cambios): void
    {
        if (! Schema::hasTable('dotacion_sobredotacion_justificaciones')) {
            Schema::create('dotacion_sobredotacion_justificaciones', function (Blueprint $t): void {
                $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->unsignedSmallInteger('anio');
                $t->string('docente_rut_normalizado'); $t->string('bloque'); $t->string('tipo_horas');
                $t->decimal('horas_detectadas', 8, 2); $t->text('justificacion');
            });
        }
        DB::table('dotacion_sobredotacion_justificaciones')->insert(array_replace(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut_normalizado' => '99000001K', 'bloque' => 'plan_estudio', 'tipo_horas' => 'titular',
            'horas_detectadas' => 5, 'justificacion' => 'Justificación sintética de revisión.'], $cambios));
    }
}
