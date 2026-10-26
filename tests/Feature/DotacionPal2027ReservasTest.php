<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Exports\DotacionPal2027Export;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionPal2027;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionPal2027ReservasTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        $this->ee = Establecimiento::create(['cod_estab' => 99001, 'rbd' => 99001, 'nombre_establecimiento' => 'Escuela sintética']);
    }

    public function test_reservas_titulares_generales_se_cargan_en_otros_y_en_ambos_excel(): void
    {
        foreach ([1, 1.25, 3, 4, 6, 12, 18, 41, 44] as $horas) {
            $doc = $this->docente([$this->funcion(44 - $horas), $this->reserva($horas)]);
            $antes = json_encode($doc);
            $report = $this->reporte($doc);
            $this->assertSame(0, $report['incompatibles']);
            $otros = collect($report['docentes'])->firstWhere('G', 'Otros (Talleristas / Apoyo / etc.)');
            $this->assertSame('Titular', $otros['H']); $this->assertEquals($horas, $otros['R']);
            $this->assertNull($otros['K']); $this->assertNull($otros['M']);
            $this->assertStringContainsString('h de reserva titular', $otros['S']);
            $this->assertSame($antes, json_encode($doc));
            foreach ($report['docentes'] as $fila) {
                $this->assertSame([], array_filter(array_keys($fila), fn ($k) => str_starts_with($k, '_pal_')));
            }
        }
        // Último caso: las 44 h están reservadas; ninguna depende de BUSCARV.
        foreach ([false, true] as $global) {
            $path = Storage::disk('local')->path($global ? 'reservas-todos.xlsx' : 'reservas.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)'])->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)');
                $this->assertEquals(44, $sheet->getCell('R3')->getValue());
                $this->assertEquals(44, $sheet->getCell('J3')->getCalculatedValue());
                $this->assertEquals(0, $sheet->getCell('N3')->getCalculatedValue());
                $this->assertEquals(0, $sheet->getCell('O3')->getCalculatedValue());
                $this->assertStringStartsWith('=', $sheet->getCell('J3')->getValue());
                $this->assertStringNotContainsString('INCOMPATIBLE:', (string) $sheet->getCell('S3')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_origen_titular_de_la_reserva_se_conserva_aunque_el_aula_o_funcion_se_creara_antes(): void
    {
        $report = $this->reporte($this->docente([$this->funcion(34), $this->reserva(10)],
            ['horas_planta' => 20, 'horas_contrata' => 24]));
        $rows = $report['docentes'];
        $this->assertSame(0, $report['incompatibles']);
        $this->assertSame(['Titular', 'Plazo Fijo', 'Titular'], array_column($rows, 'H'));
        $this->assertSame([10.0, 24.0, 10.0], array_column($rows, 'R'));
        $this->assertSame('Otros (Talleristas / Apoyo / etc.)', $rows[2]['G']);
        $this->assertEquals(44, array_sum(array_column($rows, 'R')));
    }

    public function test_reserva_contrata_creada_primero_no_se_reclasifica_como_titular(): void
    {
        $reserva = $this->reserva(12, 'contrata')->forceFill(['id' => 1]);
        $funcion = $this->funcion(32)->forceFill(['id' => 2]);
        $report = $this->reporte($this->docente([$reserva, $funcion], ['horas_planta' => 20, 'horas_contrata' => 24]));
        $this->assertSame(0, $report['incompatibles']);
        $this->assertSame('Plazo Fijo', $report['docentes'][0]['H']);
        $this->assertSame(12.0, $report['docentes'][0]['R']);
        $this->assertStringContainsString('Reserva a plazo fijo incorporada', $report['docentes'][0]['S']);
        $this->assertSame(['Titular', 'Plazo Fijo'], array_column(array_slice($report['docentes'], 1), 'H'));
        $this->assertEquals(44, array_sum(array_column($report['docentes'], 'R')));
    }

    public function test_justificacion_compartida_se_descuenta_una_vez_entre_saldo_y_reserva(): void
    {
        $this->justificar(['bloque' => 'plan_estudio', 'horas_detectadas' => 3]);
        $this->justificar(['bloque' => 'pie', 'horas_detectadas' => 5]);
        $antes = DB::table('dotacion_sobredotacion_justificaciones')->get()->toJson();
        $report = $this->reporte($this->docente([$this->funcion(28), $this->reserva(10)]));
        $this->assertSame(0, $report['incompatibles']);
        $this->assertSame([28.0, 8.0], array_column($report['docentes'], 'R'));
        $this->assertStringContainsString('De esta reserva se excluyen 2 h', $report['docentes'][1]['S']);
        $this->assertStringContainsString('8 h titulares justificadas excluidas', $report['docentes'][1]['S']);
        $this->assertEquals(36, array_sum(array_column($report['docentes'], 'R')));
        $this->assertSame($antes, DB::table('dotacion_sobredotacion_justificaciones')->get()->toJson());
    }

    public function test_reservas_de_ambas_calidades_no_se_mezclan_en_el_mismo_grupo(): void
    {
        $contrata = $this->reserva(4, 'contrata')->forceFill(['id' => 3]);
        $report = $this->reporte($this->docente([$this->funcion(30), $this->reserva(10), $contrata],
            ['horas_planta' => 32, 'horas_contrata' => 12]));
        $otros = array_values(array_filter($report['docentes'], fn ($r) => $r['G'] === 'Otros (Talleristas / Apoyo / etc.)'));
        $this->assertSame(0, $report['incompatibles']);
        $this->assertSame(['Titular', 'Plazo Fijo'], array_column($otros, 'H'));
        $this->assertSame([10.0, 4.0], array_column($otros, 'R'));
        $this->assertEquals(44, array_sum(array_column($report['docentes'], 'R')));
    }

    public function test_justificacion_superior_al_saldo_y_reserva_no_resta_funciones_ni_situaciones(): void
    {
        $this->justificar(['horas_detectadas' => 50]);
        $report = $this->reporte($this->docente([$this->funcion(30), $this->reserva(10)]));
        $this->assertEquals(30, array_sum(array_column($report['docentes'], 'R')));
        $this->assertNull($report['docentes'][1]['R']);
        $this->assertStringContainsString('De esta reserva se excluyen 10 h', $report['docentes'][1]['S']);
        $this->assertStringContainsString('14 h titulares justificadas excluidas', $report['docentes'][0]['S']);
        $doc = $this->docente([$this->funcion(20), $this->reserva(10)], ['horas_planta' => 32, 'horas_contrata' => 12,
            'horas_contrato' => 30, 'horas_excluidas' => 14, 'exclusion_docente' => ['motivo' => 'horas_gremiales', 'horas' => 14]]);
        $rows = $this->reporte($doc)['docentes'];
        $otros = array_values(array_filter($rows, fn ($r) => $r['G'] === 'Otros (Talleristas / Apoyo / etc.)'));
        $this->assertSame(['Titular', 'Plazo Fijo'], array_column($otros, 'H'));
        $this->assertSame([2.0, 12.0], array_column($otros, 'R'));
        $this->assertEquals(34, array_sum(array_column($rows, 'R')));
    }

    public function test_reserva_historica_sin_origen_se_reparte_por_capacidad_de_cada_calidad(): void
    {
        $report = $this->reporte($this->docente([$this->reserva(44, null)], ['horas_planta' => 20, 'horas_contrata' => 24]));
        $this->assertSame(0, $report['incompatibles']);
        $this->assertSame(['Titular', 'Plazo Fijo'], array_column($report['docentes'], 'H'));
        $this->assertSame([20.0, 24.0], array_column($report['docentes'], 'R'));
        $this->assertStringContainsString('Reserva histórica sin origen', $report['docentes'][0]['S']);
    }

    public function test_no_reasigna_excesos_de_calidad_a_contrata_ni_admite_otro_financiamiento(): void
    {
        $report = $this->reporte($this->docente([$this->funcion(38), $this->reserva(6)], ['horas_planta' => 4, 'horas_contrata' => 40]));
        $this->assertSame(1, $report['incompatibles']);
        $this->assertEquals(44, array_sum(array_column($report['docentes'], 'R')));
        $this->assertStringContainsString('reserva excede las horas de su calidad', $report['docentes'][2]['S']);
        foreach (['PIE', 'SEP', 'Fondo sin equivalencia'] as $fondos) {
            $report = $this->reporte($this->docente([$this->reserva(44)->forceFill(['subvencion' => $fondos])]));
            $this->assertSame(1, $report['incompatibles']);
            $this->assertNull($report['docentes'][0]['P']); $this->assertNull($report['docentes'][0]['Q']);
            $this->assertNull($report['docentes'][0]['R']);
        }
    }

    public function test_fracciones_reales_se_detallan_al_convertir_aula_e_incorporar_reserva_contrata(): void
    {
        $curso = (new EstablecimientoCurso)->setRelation('curso', (new Curso)->forceFill(['codigo' => '1B']));
        $aula = $this->funcion(35)->forceFill(['tipo_asignacion' => 'plan_estudio', 'horas_plan_pedagogicas' => 30,
            'proporcion_aplicada' => '65/35'])->setRelation('establecimientoCurso', $curso);
        $report = $this->reporte($this->docente([$aula, $this->reserva(1.25, 'contrata'),
            $this->funcion(3)->forceFill(['id' => 3, 'tipo_asignacion' => 'plan_normativo']),
            $this->funcion(2)->forceFill(['id' => 4, 'tipo_asignacion' => 'pie_colaborativo', 'subvencion' => 'PIE'])],
            ['horas_planta' => 12, 'horas_contrata' => 32]));
        $this->assertSame(0, $report['incompatibles']);
        $this->assertStringContainsString('10.2857 h pedagógicas reales', $report['docentes'][0]['S']);
        $this->assertStringContainsString('19.7143 h pedagógicas reales', $report['docentes'][1]['S']);
        $this->assertSame('Plazo Fijo', $report['docentes'][2]['H']);
        // Reserva 1,25 + plan normativo 3 + saldo 2,75, sin duplicar.
        $this->assertSame(7.0, $report['docentes'][2]['R']);
        $this->assertStringContainsString('Reserva a plazo fijo incorporada', $report['docentes'][2]['S']);
        $this->assertEquals(7, array_sum(array_column($report['docentes'], 'R')));
        $this->assertEquals(2, array_sum(array_column($report['docentes'], 'P')));
    }

    public function test_reservas_anuladas_y_de_asistentes_no_consumen_capacidad_docente(): void
    {
        $report = $this->reporte($this->docente([$this->funcion(44),
            $this->reserva(12)->forceFill(['estado' => 'anulada']),
            $this->reserva(12)->forceFill(['estamento_cobertura' => 'asistente'])]));
        $this->assertCount(1, $report['docentes']); $this->assertSame(44.0, $report['docentes'][0]['R']);
        $this->assertSame(0, $report['incompatibles']);
    }

    private function reporte(array $doc): array
    {
        return app(DotacionPal2027::class)->construir($this->ee, ['docentes' => [$doc]], app(DotacionPal2027Export::class)->tablas());
    }

    private function docente(array $asignaciones, array $cambios = []): array
    {
        return array_replace(['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Sin título declarado',
            'funcion' => 'DOCENTE', 'horas_contrato_base' => 44, 'horas_contrato' => 44, 'horas_planta' => 44,
            'horas_contrata' => 0, 'niveles_declarados' => ['Básica'], 'asignaciones' => $asignaciones], $cambios);
    }

    private function funcion(float $horas): DotacionDocenteAsignacion
    {
        return (new DotacionDocenteAsignacion)->forceFill(['id' => 1, 'docente_rut' => '99000001-K', 'tipo_asignacion' => 'funcion_directiva',
            'subvencion' => 'General', 'horas_contrato' => $horas, 'horas_plan_pedagogicas' => 0, 'estado' => 'activa'])
            ->setRelation('establecimientoCurso', null);
    }

    private function reserva(float $horas, ?string $origen = 'titular'): DotacionDocenteAsignacion
    {
        return $this->funcion($horas)->forceFill(['id' => 2, 'tipo_asignacion' => 'reserva_no_normativa',
            'subtipo_asignacion' => $origen, 'asignatura_nombre' => 'Horas para otras funciones']);
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
