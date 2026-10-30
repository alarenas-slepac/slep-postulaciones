<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionConciliacionGeneral;
use App\Support\DotacionContratoPlanCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionDocentesSubsector;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use App\Support\DotacionProyeccionCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionReligionContrato18Test extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
        foreach ([
            '2026_01_23_000001_create_establecimientos_table.php',
            '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php',
            '2026_07_23_170000_sync_docente_horas_proporciones_cpeip.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php',
            '2026_09_22_180000_create_dotacion_proceso_2027_configuraciones_table.php',
            '2026_09_29_130000_create_dotacion_docente_subsectores_table.php',
        ] as $file) (require database_path('migrations/'.$file))->up();
        Schema::table('dotacion_docente_asignaciones', fn (Blueprint $t) => $t->string('estamento_cobertura')->default('docente'));
        Schema::table('establecimiento_curso_pie', fn (Blueprint $t) => $t->unsignedInteger('prof_educ_dif_minutos')->nullable());
        Schema::table('dotacion_proceso_2027_configuraciones', fn (Blueprint $t) => $t->json('funciones_normativas')->nullable());
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rbd'); $t->string('rut'); $t->integer('horas_contratadas');
            foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $campo) $t->string($campo)->nullable();
        });
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'Establecimiento sintético']);
        $this->personal('99000001-K', 18);
        $this->personal('99000002-K', 19);
        DotacionProceso2027Configuracion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'decision_combinacion' => 'sin_combinacion',
            'max_horas_bloque_1' => 500, 'max_horas_bloque_2' => 500, 'max_horas_bloque_3' => 500,
        ]);
        $this->actingAs($this->testUser(1, 3));
        $this->withSession(['active_role' => 'admin']);
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    public function test_asignacion_unica_de_16_aula_se_guarda_y_lee_como_18_contrato_sin_sobrecarga(): void
    {
        $need = $this->necesidades(1)->sole();
        $this->guardar($need, 16);
        $this->assertSame(18.0, (float) DotacionDocenteAsignacion::sole()->horas_contrato);
        $this->assertSame(19.0, (float) DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula('65_35', 16)['horas_contrato']);
        $this->assertResumen(18, 16);
        $actual = DotacionDocenteAsignacion::sole();
        $actual->update(['asignatura_nombre' => 'RELIGIÓN CATÓLICA']);
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => '99000001-K', 'horas_plan_pedagogicas' => 16,
        ]), $this->ee, $actual);
        $this->assertSame('RELIGIÓN CATÓLICA', $actual->fresh()->asignatura_nombre);
        $this->assertSame(18.0, (float) $actual->fresh()->horas_contrato);
    }

    public function test_ocho_cursos_se_consolidan_y_editar_eliminar_y_reasignar_recalculan_la_excepcion(): void
    {
        $needs = $this->necesidades(8);
        foreach ($needs as $need) $this->guardar($need, 2);
        $this->assertSame(18.0, (float) DotacionDocenteAsignacion::sum('horas_contrato'));
        $this->assertResumen(18, 16);
        $actual = DotacionDocenteAsignacion::orderByDesc('id')->first();
        $controller = app(DotacionAsignacionController::class);
        $controller->update($this->request(['docente_rut' => '99000001-K', 'horas_plan_pedagogicas' => 1]), $this->ee, $actual);
        $this->assertSame(17.0, (float) DotacionDocenteAsignacion::sum('horas_contrato'));
        $controller->update($this->request(['docente_rut' => '99000001-K', 'horas_plan_pedagogicas' => 2]), $this->ee, $actual->fresh());
        $this->assertSame(18.0, (float) DotacionDocenteAsignacion::sum('horas_contrato'));
        $controller->update($this->request(['docente_rut' => '99000002-K', 'horas_plan_pedagogicas' => 2]), $this->ee, $actual->fresh());
        $this->assertSame(16.0, (float) DotacionDocenteAsignacion::where('docente_rut_normalizado', '99000001K')->sum('horas_contrato'));
        $this->assertSame(2.0, (float) $actual->fresh()->horas_contrato);
        $controller->update($this->request(['docente_rut' => '99000001-K', 'horas_plan_pedagogicas' => 2]), $this->ee, $actual->fresh());
        $controller->destroy($this->request([]), $this->ee, $actual->fresh());
        $this->assertSame(16.0, (float) DotacionDocenteAsignacion::sum('horas_contrato'));
        $this->guardar($needs->last(), 2);
        $this->assertResumen(18, 16);
    }

    public function test_jornada_19_sigue_en_19_y_lecturas_historicas_no_reescriben_filas(): void
    {
        $need = $this->necesidades(1)->sole();
        $this->guardar($need, 16, '99000002-K');
        $this->assertSame(19.0, (float) DotacionDocenteAsignacion::sole()->horas_contrato);
        // Reproduce un registro histórico de 19 h para una persona con contrato de 18 h.
        DB::table('dotacion_docente_asignaciones')->update(['docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K']);
        $antes = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        $this->assertResumen(18, 16);
        $this->assertSame($antes, DB::table('dotacion_docente_asignaciones')->get()->toJson());
        // El cambio real de jornada desactiva la excepción en la siguiente lectura.
        DB::table('reemplazos_personal')->where('rut', '99000001-K')->update(['jornada' => 19, 'jornada_basica' => 19]);
        $this->assertSame(19.0, DotacionAsignacionCalculator::assignmentsByRut($this->ee, 2027)['99000001K']['contrato_65_35']);
    }

    public function test_no_extiende_la_excepcion_a_otra_asignatura_aun_con_nombre_enviado_falso(): void
    {
        $need = $this->necesidades(1, 'Matemática')->sole();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('dispone de 18 h de contrato');
        $this->guardar($need, 16, '99000001-K', ['asignatura_nombre' => 'Religión']);
    }

    public function test_las_asignaciones_mixtas_proporciones_y_jornadas_distintas_conservan_la_regla_general(): void
    {
        $this->necesidades(1);
        $persona = ['rut_normalizado' => '99000001K', 'horas_contrato_base' => 18, 'horas_contrato' => 18];
        $base = ['id' => 1, 'establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut_normalizado' => '99000001K', 'tipo_asignacion' => 'plan_estudio',
            'asignatura_nombre' => 'Religión Evangélica', 'proporcion_aplicada' => '65/35',
            'horas_plan_pedagogicas' => 16, 'horas_contrato' => 19, 'estado' => 'activa'];
        $this->assertSame(18.0, (float) DotacionContratoPlanCalculator::consolidar(collect([$base]), $persona)->sum('horas_contrato'));
        foreach ([['horas_contrato' => 17], ['horas_contrato' => 19], ['horas_contrato' => 44], ['horas_contrato_base' => 19], ['cupo_contrata_id' => 1]] as $cambio) {
            $this->assertSame(19.0, (float) DotacionContratoPlanCalculator::consolidar(collect([$base]), array_replace($persona, $cambio))->sum('horas_contrato'));
        }
        foreach ([['asignatura_nombre' => 'Historia'], ['proporcion_aplicada' => '60/40'], ['horas_plan_pedagogicas' => 17], ['estamento_cobertura' => 'asistente']] as $cambio) {
            $salida = DotacionContratoPlanCalculator::consolidar(collect([array_replace($base, $cambio)]), $persona);
            $this->assertNotEquals(18.0, (float) $salida->sum('horas_contrato'));
        }
        $mixtas = collect([array_replace($base, ['horas_plan_pedagogicas' => 8]), array_replace($base, ['id' => 2, 'horas_plan_pedagogicas' => 8, 'asignatura_nombre' => 'Música'])]);
        $this->assertSame(19.0, (float) DotacionContratoPlanCalculator::consolidar($mixtas, $persona)->sum('horas_contrato'));
        $fracciones = collect([array_replace($base, ['horas_plan_pedagogicas' => 15.75]), array_replace($base, ['id' => 2, 'horas_plan_pedagogicas' => .25])]);
        $antes = $fracciones->all();
        $salida = DotacionContratoPlanCalculator::consolidar($fracciones, $persona);
        $this->assertSame(18.0, (float) $salida->sum('horas_contrato'));
        $this->assertTrue($salida->every(fn ($fila) => $fila['horas_contrato'] >= 0));
        $this->assertSame($antes, $fracciones->all());
    }

    public function test_la_excepcion_no_libera_horas_consumidas_por_otras_funciones(): void
    {
        $need = $this->necesidades(1)->sole();
        DotacionDocenteAsignacion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K',
            'docente_nombre' => 'Docente sintético', 'tipo_asignacion' => 'otra_funcion',
            'asignatura_nombre' => 'Función sintética', 'horas_contrato' => 1, 'estado' => 'activa',
        ]);
        try {
            $this->guardar($need, 16);
            $this->fail('La jornada de 18 h no admite sumar otra función al contrato completo de Religión.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('dispone de 17', $error->getMessage());
        }
        $this->assertSame(1, DotacionDocenteAsignacion::count());
    }

    private function assertResumen(float $contrato, float $aula): void
    {
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $docente = collect($data['docentes'])->firstWhere('rut_normalizado', '99000001K');
        $this->assertSame($contrato, $docente['horas_contrato_65_35']);
        $this->assertSame($aula, $docente['horas_aula']);
        $this->assertSame(0.0, $docente['diferencia']);
        $this->assertNotSame('sobrecarga', $docente['estado_cuadratura']['key']);
        $this->assertSame($contrato, (float) collect($data['asignacion']['asignaciones'])->sum('horas_contrato'));
        $asignatura = collect($data['asignaturas']['items'])->sole();
        $this->assertSame($contrato, $asignatura['horas_contrato_asignadas_total']);
        $proceso = DotacionProceso2027Calculator::resumen($this->ee, 2027, $data);
        $this->assertSame($contrato, $proceso['bloques']['bloque_1']['asignadas']);
        $conciliacion = DotacionConciliacionGeneral::build($data['docentes'], $data['resumen'], $proceso, []);
        $this->assertSame($contrato, $conciliacion['asignadas']);
        $proyeccion = DotacionProyeccionCalculator::build($data, 2027, ['99000001K' => false]);
        $this->assertSame($contrato, $proyeccion['reservas'][0]['asignaciones_referencia']['total_contrato']);
    }

    private function necesidades(int $cantidad, string $nombre = 'Religión Católica'): \Illuminate\Support\Collection
    {
        $curso = Curso::where('codigo', '5B')->firstOrFail();
        $plan = PlanEstudio::create(['curso_id' => $curso->id, 'anio' => 2027, 'nombre_plan' => 'Plan sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 16 / $cantidad, 'activo' => true]);
        DB::table('planes_estudio_asignaturas')->insert(['plan_estudio_id' => $plan->id,
            'asignatura' => $nombre, 'tipo_bloque' => 'tiempo_minimo', 'horas_semanales' => 16 / $cantidad]);
        foreach (range(1, $cantidad) as $i) EstablecimientoCurso::create([
            'establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd, 'curso_id' => $curso->id,
            'plan_estudio_id' => $plan->id, 'anio' => 2027, 'letra' => chr(64 + $i),
            'nombre_seccion' => '5° Básico sintético '.$i, 'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true,
        ]);
        $proceso = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        DotacionProceso2027Configuracion::first()->update(['funciones_normativas' => collect($proceso['funciones_normativas'])->mapWithKeys(fn ($f) => [$f['key'] => false])->all()]);
        $needs = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.plan_estudio'));
        foreach ($needs as $need) foreach (['99000001K', '99000002K'] as $rut) DB::table('dotacion_docente_subsectores')->updateOrInsert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need), 'docente_rut_normalizado' => $rut,
        ], ['nivel' => 'basica', 'asignatura_nombre' => $nombre]);
        return $needs;
    }

    private function guardar(array $need, float $aula, string $rut = '99000001-K', array $extra = []): void
    {
        $response = app(DotacionAsignacionController::class)->store($this->request(array_replace([
            'anio' => 2027, 'docente_rut' => $rut, 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => $need['subtipo_asignacion'],
            'necesidad_key' => $need['key'], 'establecimiento_curso_id' => $need['establecimiento_curso_id'],
            'plan_estudio_id' => $need['plan_estudio_id'], 'asignatura_nombre' => $need['titulo'], 'horas_plan_pedagogicas' => $aula,
        ], $extra)), $this->ee);
        $this->assertNotNull($response->getSession()->get('success'));
    }

    private function personal(string $rut, int $horas): void
    {
        DB::table('reemplazos_personal')->insert(['establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
            'rut' => $rut, 'nombre' => 'Docente sintético', 'anio' => 2026, 'mes' => 8, 'jornada' => $horas,
            'jornada_basica' => $horas, 'tipocontrato' => 'CONTRATA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-'.$rut]);
        DB::table('declaracion_sostenedores')->insert(['rbd' => $this->ee->rbd, 'rut' => $rut, 'nombres' => 'Docente sintético',
            'nombre_titulo' => 'Pedagogía en Religión', 'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE', 'horas_contratadas' => 44]);
    }

    private function request(array $data): Request
    {
        $request = Request::create('/asignaciones-sinteticas', 'POST', $data);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        return $request;
    }

    private function resetCaches(): void
    {
        foreach ([DotacionAsignacionCalculator::class, DotacionEstablecimientoCalculator::class] as $class) foreach (['schemaTableCache', 'schemaColumnCache'] as $propiedad) (new ReflectionProperty($class, $propiedad))->setValue(null, []);
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }
}
