<?php

namespace Tests\Feature;

use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionContratoPlanCalculator;
use App\Support\DotacionPlanTitularPrimero;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

class DotacionContratoPlanConsolidadoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->resetCaches();
        (require database_path('migrations/2026_05_25_183000_create_docente_horas_proporciones_table.php'))->up();
        (require database_path('migrations/2026_07_23_170000_sync_docente_horas_proporciones_cpeip.php'))->up();
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id();
            $t->integer('establecimiento_id');
            $t->integer('anio');
            $t->string('docente_rut_normalizado');
            $t->string('docente_nombre');
            $t->string('asignatura_nombre');
            $t->string('necesidad_key')->nullable();
            $t->string('subvencion')->default('General');
            $t->string('estado')->default('activa');
            $t->string('tipo_asignacion')->default('plan_estudio');
            $t->string('estamento_cobertura')->default('docente');
            $t->string('proporcion_aplicada')->default('65/35');
            $t->decimal('horas_plan_pedagogicas', 8, 2)->nullable();
            $t->decimal('horas_contrato', 8, 2);
        });
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    private function resetCaches(): void
    {
        foreach (['schemaTableCache', 'schemaColumnCache'] as $campo) {
            (new ReflectionProperty(DotacionAsignacionCalculator::class, $campo))->setValue(null, []);
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
    }

    private function establecimiento(): Establecimiento
    {
        return (new Establecimiento)->forceFill(['id' => 1]);
    }

    private function fila(?float $aula, float $contrato, array $extras = []): int
    {
        return DB::table('dotacion_docente_asignaciones')->insertGetId(array_merge([
            'establecimiento_id' => 1, 'anio' => 2027,
            'docente_rut_normalizado' => '111111111', 'docente_nombre' => 'Docente sintético',
            'asignatura_nombre' => 'Asignatura sintética',
            'horas_plan_pedagogicas' => $aula, 'horas_contrato' => $contrato,
        ], $extras));
    }

    public function test_convierte_total_por_docente_y_coinciden_detalle_resumen_y_proceso_sin_reescribir_historia(): void
    {
        $this->fila(15, 17, ['necesidad_key' => 'plan:curso1']);
        $this->fila(15, 17, ['necesidad_key' => 'plan:curso2']);
        $this->fila(null, 4, ['tipo_asignacion' => 'funcion_directiva']);
        $this->fila(null, 3, ['tipo_asignacion' => 'pie_colaborativo']);
        $this->fila(null, 2, ['tipo_asignacion' => 'reserva_no_normativa']);
        $antes = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();

        $asignaciones = DotacionAsignacionCalculator::assignmentsFor($this->establecimiento(), 2027);
        $detalle = DotacionAsignacionCalculator::assignmentsByRut($this->establecimiento(), 2027)['111111111'];
        $this->assertSame(35.0, (float) $asignaciones->where('tipo_asignacion', 'plan_estudio')->sum('horas_contrato'));
        $this->assertSame(35.0, $detalle['contrato_65_35']);
        $this->assertSame(44.0, $detalle['total']);
        $this->assertSame(42.0, DotacionAsignacionCalculator::subvencionResumen($asignaciones)->first()['horas']);
        $proceso = DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027, [
            'asignacion' => ['asignaciones' => $asignaciones, 'docentes' => [[
                'rut_normalizado' => '111111111', 'nombre' => 'Docente sintético',
                'titulo' => 'Pedagogía en Educación Básica', 'horas_contrato' => 44,
                'horas_planta' => 44, 'horas_contrata' => 0, 'horas_asignadas_total' => $detalle['total'],
            ]]],
        ]);
        $this->assertSame(44.0, $proceso['bloques']['bloque_1']['asignadas']);
        $this->assertSame(44.0, $proceso['bloques']['bloque_1']['titulares_asignadas']);
        $this->assertSame(2.0, $proceso['bloques']['bloque_1']['reservadas_no_normativas']);
        $this->assertSame(0.0, $proceso['docentes']->first()['horas_disponibles']);
        $this->assertSame($antes, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
    }

    public function test_correcciones_individuales_reproducen_diferencia_neta_de_dos_horas(): void
    {
        foreach ([[30, 34], [29, 33], [30, 34], [27, 33], [32, 38], [30, 34], [6, 6]] as $i => [$aula, $contrato]) {
            $this->fila($aula, $contrato, ['docente_rut_normalizado' => 'SINTETICO'.$i]);
        }
        $this->assertSame(212.0, (float) DB::table('dotacion_docente_asignaciones')->sum('horas_contrato'));
        $asignaciones = DotacionAsignacionCalculator::assignmentsFor($this->establecimiento(), 2027);
        $this->assertSame(214.0, (float) $asignaciones->sum('horas_contrato'));
        $this->assertSame([35.0, 34.0, 35.0, 31.0, 37.0, 35.0, 7.0],
            $asignaciones->sortBy('id')->map(fn ($row) => (float) $row->horas_contrato)->values()->all());
    }

    public function test_no_mezcla_docentes_proporciones_establecimientos_ni_anios(): void
    {
        $this->fila(15, 17);
        $this->fila(15, 17);
        $this->fila(14, 17, ['proporcion_aplicada' => '60/40']);
        $this->fila(14, 17, ['proporcion_aplicada' => '60_40']);
        $this->fila(15, 17, ['docente_rut_normalizado' => '222222222']);
        $this->fila(15, 17, ['establecimiento_id' => 2]);
        $this->fila(15, 17, ['anio' => 2026]);
        $filas = DotacionContratoPlanCalculator::consolidar(DotacionDocenteAsignacion::all());
        $this->assertSame(121.0, (float) $filas->sum('horas_contrato')); // 35 + 35 + 17 + 17 + 17.
        $detalle = DotacionAsignacionCalculator::assignmentsByRut($this->establecimiento(), 2027)['111111111'];
        $this->assertSame(35.0, $detalle['contrato_65_35']);
        $this->assertSame(35.0, $detalle['contrato_60_40']);
        $this->assertSame(70.0, $detalle['total']);
    }

    public function test_libre_disposicion_se_incluye_en_el_total_y_las_funciones_no_se_convierten(): void
    {
        $this->fila(24, 28);
        $this->fila(6, 6, ['subvencion' => 'Libre disposición']);
        foreach (['funcion_directiva', 'funcion_tecnico_pedagogica', 'plan_normativo', 'otra_funcion', 'pie_colaborativo', 'reserva_no_normativa'] as $tipo) {
            $this->fila(9, 2, ['tipo_asignacion' => $tipo]);
        }
        $detalle = DotacionAsignacionCalculator::assignmentsByRut($this->establecimiento(), 2027)['111111111'];
        $this->assertSame(30.0, $detalle['aula']);
        $this->assertSame(35.0, $detalle['contrato_65_35']);
        $this->assertSame(10.0, $detalle['funciones_total']);
        $this->assertSame(2.0, $detalle['reservadas_no_normativas']);
        $this->assertSame(47.0, $detalle['total']);
    }

    public function test_respeta_aaee_reglas_especiales_filas_inactivas_y_registros_sin_aula(): void
    {
        $this->fila(15, 20, ['estamento_cobertura' => 'asistente']);
        $this->fila(15, 21, ['proporcion_aplicada' => 'NT Sin JEC']);
        $this->fila(null, 7);
        $this->fila(15, 18, ['estado' => 'inactiva']);
        $entrada = DotacionDocenteAsignacion::all();
        $antes = $entrada->toJson();
        $resultado = DotacionContratoPlanCalculator::consolidar($entrada);
        $this->assertSame([20.0, 21.0, 7.0, 18.0], $resultado->map(fn ($row) => (float) $row->horas_contrato)->all());
        $this->assertSame($antes, $entrada->toJson());
    }

    public function test_editar_libera_diferencia_del_total_y_no_el_contrato_aislado_de_la_fila(): void
    {
        $id = $this->fila(15, 17);
        $this->fila(15, 17);
        $this->fila(null, 9, ['tipo_asignacion' => 'funcion_directiva']);
        $actual = DotacionDocenteAsignacion::findOrFail($id);
        $asignaciones = DotacionAsignacionCalculator::assignmentsFor($this->establecimiento(), 2027);
        $this->assertSame(18.0, DotacionContratoPlanCalculator::horasLiberadas($asignaciones, $actual));
        $docentes = DotacionProceso2027Calculator::docentesPriorizados(collect([[
            'rut_normalizado' => '111111111', 'horas_contrato' => 44, 'horas_planta' => 44,
            'horas_contrata' => 0, 'horas_asignadas_total' => 44, 'asignaciones' => $asignaciones,
        ]]));
        $elegibles = DotacionPlanTitularPrimero::elegibles($docentes, ['111111111'], [], $actual);
        $this->assertSame(18.0, DotacionPlanTitularPrimero::disponibles($elegibles->first(), 'titular'));
        DotacionPlanTitularPrimero::validar($elegibles, $elegibles->first(), 18, false);
    }

    public function test_parvularia_cpeip_agrupa_aula_y_acompanamiento_y_conserva_bases_historicas(): void
    {
        $this->fila(29, 33, ['proporcion_aplicada' => 'NT JEC · CPEIP 65/35']);
        $this->fila(3, 4, ['proporcion_aplicada' => 'NT JEC · CPEIP 65/35', 'tipo_asignacion' => 'acompanamiento_parvularia']);
        $this->fila(1, 1.45, ['proporcion_aplicada' => 'NT Con JEC · base contractual 55 h']);
        $detalle = DotacionAsignacionCalculator::assignmentsByRut($this->establecimiento(), 2027)['111111111'];
        $this->assertSame(38.45, $detalle['contrato_especial']); // 32 aula CPEIP -> 37; base histórica 1,45.
        $this->assertSame(38.45, $detalle['total']);
    }
}
