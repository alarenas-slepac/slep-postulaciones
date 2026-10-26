<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Exports\DotacionPal2027Export;
use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionPal2027;
use App\Support\DotacionPalExclusiones2027;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionPal2027ExclusionesTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        $this->ee = Establecimiento::create(['cod_estab' => 99001, 'rbd' => 99001, 'nombre_establecimiento' => 'Escuela sintética']);
        Schema::create('reemplazos_personal', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->string('rut'); $t->string('nombre');
            $t->integer('anio'); $t->integer('mes'); $t->boolean('vigente')->default(true);
            foreach (['jornada', 'jornada_basica', 'jornada_media'] as $f) { $t->integer($f)->nullable(); }
            foreach (['tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'row_hash'] as $f) { $t->string($f)->nullable(); }
            $t->timestamps();
        });
        Schema::create('dotacion_docente_exclusiones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            $t->string('docente_rut'); $t->string('docente_rut_normalizado'); $t->string('motivo'); $t->integer('horas');
            $t->boolean('considerar_dotacion_siguiente')->default(true); $t->boolean('conservar_horas_necesarias')->default(true);
        });
        Schema::create('dotacion_no_renovaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); $t->string('docente_rut_normalizado');
            $t->text('justificacion')->nullable(); $t->timestamp('anulada_at')->nullable();
        });
    }

    public function test_detalla_no_continuidad_de_2026_y_no_renovacion_sin_reincorporarlas_ni_duplicarlas(): void
    {
        $this->personal('99000001K', 30); $this->personal('99000002K', 20); $this->personal('99000003K', 10);
        $this->situacion('99000001K'); $this->situacion('99000002K');
        $this->noRenovacion('99000002K');
        $this->noRenovacion('99000003K', ['anulada_at' => '2026-10-25 12:00:00']);
        // No se suman períodos antiguos, suplencias, asistentes, no vigentes u otro EE.
        $this->personal('99000001K', 44, ['mes' => 7]);
        $this->personal('99000004K', 44, ['tipocontrato' => 'SUPLENCIA']); $this->situacion('99000004K');
        $this->personal('99000005K', 44, ['estatuto' => 'AAEE', 'escalafon' => 'ASISTENTE']); $this->situacion('99000005K');
        $this->personal('99000006K', 44, ['vigente' => false]); $this->situacion('99000006K');
        $this->personal('99000007K', 44, ['establecimiento_id' => 999]); $this->situacion('99000007K');
        $antes = DB::table('dotacion_docente_exclusiones')->get()->toJson();
        $nomina = DotacionEstablecimientoCalculator::docentes($this->ee, 2027, false);
        $this->assertSame(['99000003K'], $nomina->pluck('rut')->all());
        $filas = app(DotacionPalExclusiones2027::class)->proyeccion($this->ee);
        $this->assertCount(2, $filas); $this->assertEquals(50, array_sum(array_column($filas, 'horas')));
        $porRut = collect($filas)->keyBy('rut');
        $this->assertStringContainsString('No continúa en 2027, según situación registrada en 2026', $porRut['99000001K']['motivo']);
        $this->assertStringContainsString('Renuncia voluntaria', $porRut['99000001K']['detalle']);
        $this->assertSame('No renovación 2027', $porRut['99000002K']['motivo']);
        $this->assertStringContainsString('Motivo sintético de no renovación', $porRut['99000002K']['detalle']);
        $this->assertSame('Contrato docente', $filas[0]['base']); $this->assertSame(2026, $filas[0]['anio_base']);
        $this->assertSame($antes, DB::table('dotacion_docente_exclusiones')->get()->toJson());
    }

    public function test_padron_propio_2027_prevalece_sobre_decision_2026_y_respeta_ambito_anual(): void
    {
        $this->personal('99000001K', 30, ['anio' => 2027]); $this->situacion('99000001K');
        $this->noRenovacion('99000001K', ['anio' => 2026]);
        $this->assertCount(1, DotacionEstablecimientoCalculator::docentes($this->ee, 2027, false));
        $this->assertSame([], app(DotacionPalExclusiones2027::class)->proyeccion($this->ee));
    }

    public function test_bir_sobredotacion_y_excesos_informan_base_motivo_y_detalle_sin_sumar_cupos_al_padron(): void
    {
        Schema::create('dotacion_sobredotacion_justificaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); $t->string('docente_rut_normalizado');
            $t->string('bloque'); $t->string('tipo_horas'); $t->decimal('horas_detectadas', 8, 2); $t->text('justificacion');
        });
        DB::table('dotacion_sobredotacion_justificaciones')->insert(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut_normalizado' => '99000002K', 'bloque' => 'plan_estudio', 'tipo_horas' => 'titular',
            'horas_detectadas' => 4, 'justificacion' => 'Detalle sintético registrado.']);
        $datos = ['docentes' => [
            $this->docente('99000001K', ['exclusion_docente' => ['motivo' => 'proceso_bir']]),
            $this->docente('99000002K'),
            $this->docente('99000003K', ['asignaciones' => [['id' => 1, 'estado' => 'activa', 'tipo_asignacion' => 'funcion_directiva',
                'subvencion' => 'General', 'horas_contrato' => 46]]]),
        ], 'asignacion' => ['docentes' => [['cupo_contrata_id' => 1, 'horas_contrato' => 10, 'nombre' => 'Cupo sintético']],
            'plazas_por_asumir' => [['horas_contrato' => 5, 'nombre' => 'Plaza sintética']]]];
        $report = app(DotacionPal2027::class)->construir($this->ee, $datos, app(DotacionPal2027Export::class)->tablas());
        $excl = collect($report['excluidos'])->keyBy('motivo');
        $this->assertCount(3, $excl);
        $this->assertEquals(44, $excl['Proceso BIR 2027']['horas']);
        $this->assertEquals(4, $excl['Horas titulares justificadas en Sobredotación 2027']['horas']);
        $this->assertStringContainsString('Detalle sintético registrado.', $excl['Horas titulares justificadas en Sobredotación 2027']['detalle']);
        $this->assertSame('Asignación excedente', $excl['Asignación excede capacidad contractual']['base']);
        $this->assertEquals(2, $excl['Asignación excede capacidad contractual']['horas']);
        $this->assertEquals(0, $excl->where('base', 'Cupo o plaza sin persona')->sum('horas'));
        $this->assertSame(1, $report['cupos_por_contratar']); $this->assertSame(1, $report['plazas_por_asumir']);
        $this->assertEquals(48, $excl->where('base', 'Contrato docente')->sum('horas'));
        foreach ($report['docentes'] as $fila) { $this->assertArrayNotHasKey('_pal_exclusiones', $fila); }
    }

    public function test_ambos_excel_agregan_hoja_filtrable_con_texto_literal_y_sin_cambiar_formulas(): void
    {
        $registro = app(DotacionPalExclusiones2027::class)->fila($this->ee,
            ['rut' => '99000001-K', 'nombre' => '=Nombre sintético', 'horas_planta' => 44, 'anio' => 2026],
            'No continúa en 2027, según situación registrada en 2026', 44, '=Detalle <sintético> & razón');
        foreach ([false, true] as $global) {
            $report = ['docentes' => [], 'cursos' => [], 'excluidos' => [$registro]];
            $path = Storage::disk('local')->path($global ? 'excluidos-global.xlsx' : 'excluidos.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::load($path);
            try {
                $this->assertCount(10, $book->getAllSheets());
                $sheet = $book->getSheetByName('Excluidos PAL 2027');
                $this->assertNotNull($sheet); $this->assertSame('A4:J5', $sheet->getAutoFilter()->getRange());
                $this->assertSame('A5', $sheet->getFreezePane());
                $this->assertSame('=Nombre sintético', (string) $sheet->getCell('D5')->getValue());
                $this->assertNotSame('f', $sheet->getCell('D5')->getDataType());
                $this->assertSame('=Detalle <sintético> & razón', (string) $sheet->getCell('H5')->getValue());
                $this->assertEquals(44, $sheet->getCell('G5')->getValue());
                $this->assertEquals(2026, $sheet->getCell('J5')->getValue());
                $this->assertStringStartsWith('=', $book->getSheetByName('Info Funcionario (DOCENTE)')->getCell('J3')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    private function personal(string $rut, float $horas, array $extra = []): void
    {
        DB::table('reemplazos_personal')->insert($extra + ['establecimiento_id' => $this->ee->id, 'rut' => $rut,
            'nombre' => 'Persona sintética '.$rut, 'anio' => 2026, 'mes' => 8, 'jornada' => $horas,
            'tipocontrato' => 'PLANTA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => bin2hex(random_bytes(8))]);
    }

    private function situacion(string $rut): void
    {
        DB::table('dotacion_docente_exclusiones')->insert(['establecimiento_id' => $this->ee->id, 'anio' => 2026,
            'docente_rut' => $rut, 'docente_rut_normalizado' => $rut, 'motivo' => 'renuncia_voluntaria', 'horas' => 0,
            'considerar_dotacion_siguiente' => false]);
    }

    private function noRenovacion(string $rut, array $extra = []): void
    {
        DB::table('dotacion_no_renovaciones')->insert($extra + ['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut_normalizado' => $rut, 'justificacion' => 'Motivo sintético de no renovación']);
    }

    private function docente(string $rut, array $extra = []): array
    {
        return $extra + ['rut' => $rut, 'nombre' => 'Docente sintético', 'horas_contrato_base' => 44,
            'horas_contrato' => 44, 'horas_planta' => 44, 'horas_contrata' => 0, 'asignaciones' => [], 'niveles_declarados' => ['Básica']];
    }
}
