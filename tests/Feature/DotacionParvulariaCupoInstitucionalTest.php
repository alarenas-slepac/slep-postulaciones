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
use App\Support\DotacionContratoParvulariaCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionParvulariaCupoInstitucionalTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    private array $needs;

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
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php',
            '2026_09_22_180000_create_dotacion_proceso_2027_configuraciones_table.php',
            '2026_09_29_130000_create_dotacion_docente_subsectores_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('dotacion_docente_asignaciones', fn (Blueprint $t) => $t->string('estamento_cobertura')->default('docente'));
        Schema::table('dotacion_proceso_2027_configuraciones', fn (Blueprint $t) => $t->json('funciones_normativas')->nullable());
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id();
            $t->string('rbd');
            $t->string('rut');
            $t->integer('horas_contratadas');
            foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $field) {
                $t->string($field)->nullable();
            }
        });
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'EE sintético']);
        $nt1 = $this->course('NT1', [['Lenguaje Verbal', 5], ['Otros núcleos', 33]]);
        $this->course('NT2', [['Núcleo A', 19], ['Núcleo B', 19]]);
        for ($i = 1; $i <= 4; $i++) {
            $this->person($i, $i === 1 ? 43 : 44);
        }
        DotacionProceso2027Configuracion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'decision_combinacion' => 'sin_combinacion',
            'max_horas_bloque_1' => 500, 'max_horas_bloque_2' => 116, 'max_horas_bloque_3' => 500,
        ]);
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $this->needs = collect(data_get($data, 'asignacion.necesidades.plan_estudio'))->keyBy('titulo')->all();
        $rows = [
            $this->assignment(1, 1, $this->needs['Otros núcleos'], 13),
            $this->assignment(2, 2, $this->needs['Otros núcleos'], 20),
            $this->assignment(3, 3, $this->needs['Núcleo A'], 19),
            $this->assignment(4, 4, $this->needs['Núcleo B'], 19),
        ];
        foreach (DotacionContratoParvulariaCalculator::consolidar(collect($rows)) as $row) {
            DotacionDocenteAsignacion::create($row);
        }
        foreach (collect(data_get($data, 'asignacion.necesidades.pie_colaborativo')) as $need) {
            $docente = $need['establecimiento_curso_id'] === $nt1->id ? 1 : 3;
            DotacionDocenteAsignacion::create(array_replace($this->assignment(0, $docente, $need, 0), [
                'tipo_asignacion' => 'pie_colaborativo', 'horas_contrato' => 3,
                'proporcion_aplicada' => null, 'fuente_calculo' => 'PIE sintético',
            ]));
        }
        $process = $this->process();
        DotacionProceso2027Configuracion::first()->update([
            'funciones_normativas' => collect($process['funciones_normativas'])->mapWithKeys(fn ($f) => [$f['key'] => false])->all(),
        ]);
        foreach ($process['docentes_subsector']['asignaturas'] as $subject) {
            foreach ([1, 3] as $docente) {
                DB::table('dotacion_docente_subsectores')->insert([
                    'establecimiento_id' => $this->ee->id, 'anio' => 2027,
                    'asignatura_key' => $subject['key'], 'docente_rut_normalizado' => '9900000'.$docente.'K',
                    'nivel' => 'parvularia', 'asignatura_nombre' => $subject['nombre'],
                ]);
            }
        }
        $this->actingAs($this->testUser(1, 3));
        $this->withSession(['active_role' => 'admin']);
        $this->assertSame(116.0, $this->process()['bloques']['bloque_2']['requeridas']);
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    public function test_completa_cinco_horas_aula_con_maximo_116_y_conserva_redondeo_individual(): void
    {
        $before = $this->process();
        $this->assertSame(110.0, $before['bloques']['bloque_2']['asignadas']);
        $this->assertSame(21.0, collect($before['docentes'])->firstWhere('rut_normalizado', '99000001K')['horas_disponibles']);
        $this->assertTrue($before['asignacion_habilitada']);
        $this->store();
        $after = $this->process();
        $block = $after['bloques']['bloque_2'];
        $this->assertSame(116.0, $block['requeridas']);
        $this->assertSame(118.0, $block['asignadas']);
        $this->assertSame(116.0, $block['asignadas_para_maximo']);
        $this->assertSame(116.0, $block['contrato_comprometido']);
        $this->assertSame(0.0, $block['saldo_maximo']);
        $this->assertSame(0.0, $block['pendientes']);
        $plans = data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.plan_estudio');
        $this->assertTrue(collect($plans)->every(fn ($need) => (float) $need['horas_plan_pendientes'] === 0.0));
        $this->assertSame(30.0, collect($after['docentes'])->firstWhere('rut_normalizado', '99000001K')['horas_asignadas_total']);
        $this->assertSame(116.0, (float) DotacionProceso2027Configuracion::first()->max_horas_bloque_2);
        $html = view('admin.dotacion-establecimiento.partials._proceso_2027', [
            'proceso2027' => $after, 'establecimiento' => $this->ee, 'anio' => 2027,
            'activeRole' => 'admin', 'canManageProceso2027Maximos' => true, 'tab' => 'sobredotacion',
        ])->render();
        $this->assertStringContainsString('Asignadas para el máximo: 116 h', $html);
    }

    public function test_editar_y_reasignar_no_libera_el_redondeo_como_cupo_del_curso(): void
    {
        $this->store();
        $row = DotacionDocenteAsignacion::where('necesidad_key', $this->needs['Lenguaje Verbal']['key'])->sole();
        $controller = app(DotacionAsignacionController::class);
        foreach (['99000001-K', '99000003-K'] as $rut) {
            $controller->update($this->request(['docente_rut' => $rut, 'estamento_cobertura' => 'docente', 'horas_plan_pedagogicas' => 5]), $this->ee, $row->fresh());
            $this->assertSame(116.0, $this->process()['bloques']['bloque_2']['asignadas_para_maximo']);
            $this->assertSame(0.0, $this->process()['bloques']['bloque_2']['pendientes']);
        }
        $this->assertSame(117.0, $this->process()['bloques']['bloque_2']['asignadas']);
    }

    public function test_el_redondeo_no_completa_un_curso_con_una_hora_aula_pendiente(): void
    {
        $this->store(['horas_plan_pedagogicas' => 4]);
        $block = $this->process()['bloques']['bloque_2'];
        $this->assertSame(116.0, $block['requeridas']);
        $this->assertSame(1.45, $block['pendientes']);
        $this->assertFalse($this->process()['pasos']['asignacion']['completo']);
    }

    public function test_conserva_el_saldo_individual_del_docente(): void
    {
        DB::table('reemplazos_personal')->where('rut', '99000001-K')->update(['jornada' => 29, 'jornada_basica' => 29]);
        DB::table('declaracion_sostenedores')->where('rut', '99000001-K')->update(['horas_contratadas' => 29]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('requiere 8 h de contrato');
        $this->store();
    }

    public function test_no_permite_superar_las_cinco_horas_aula_pendientes(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('dispone de 5 hora(s) aula');
        $this->store(['horas_plan_pedagogicas' => 6]);
    }

    public function test_no_incrementa_el_maximo_si_esta_por_debajo_de_las_necesarias(): void
    {
        DotacionProceso2027Configuracion::first()->update(['max_horas_bloque_2' => 115]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('configurar máximos suficientes');
        $this->store();
    }

    public function test_pie_adicional_conserva_el_tope_institucional_tras_completar_el_plan(): void
    {
        $this->store();
        $need = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.pie_colaborativo'))->first();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La asignación supera el máximo autorizado');
        app(DotacionAsignacionController::class)->store($this->request([
            'anio' => 2027, 'docente_rut' => '99000001-K', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'pie_colaborativo', 'necesidad_key' => $need['key'],
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'horas_contrato' => 1,
        ]), $this->ee);
    }

    public function test_consultar_y_eliminar_conserva_las_asignaciones_y_restituye_el_saldo_individual(): void
    {
        $this->store();
        $antes = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        $this->process();
        $this->assertSame($antes, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
        $row = DotacionDocenteAsignacion::where('necesidad_key', $this->needs['Lenguaje Verbal']['key'])->sole();
        app(DotacionAsignacionController::class)->destroy($this->request([]), $this->ee, $row);
        $after = $this->process();
        $this->assertSame(116.0, $after['bloques']['bloque_2']['requeridas']);
        $this->assertSame(110.0, $after['bloques']['bloque_2']['asignadas']);
        $this->assertSame(21.0, collect($after['docentes'])->firstWhere('rut_normalizado', '99000001K')['horas_disponibles']);
        $this->store();
        $this->assertSame(116.0, $this->process()['bloques']['bloque_2']['asignadas_para_maximo']);
    }

    private function course(string $code, array $subjects): EstablecimientoCurso
    {
        $catalog = Curso::where('codigo', $code)->firstOrFail();
        $plan = PlanEstudio::create(['curso_id' => $catalog->id, 'anio' => 2027, 'nombre_plan' => 'Plan sintético '.$code,
            'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 38, 'horas_semanales_subtotal' => 38, 'activo' => true]);
        foreach ($subjects as [$name, $hours]) {
            DB::table('planes_estudio_asignaturas')->insert(['plan_estudio_id' => $plan->id,
                'asignatura' => $name, 'tipo_bloque' => 'plan_comun_formacion_general', 'horas_semanales' => $hours]);
        }
        $course = EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
            'curso_id' => $catalog->id, 'plan_estudio_id' => $plan->id, 'anio' => 2027, 'letra' => 'A',
            'nombre_seccion' => $code.' A', 'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true]);
        DB::table('establecimiento_curso_pie')->insert(['establecimiento_id' => $this->ee->id,
            'establecimiento_curso_id' => $course->id, 'curso_id' => $catalog->id, 'anio' => 2027,
            'necesidades_transitorias' => 1, 'total_pie' => 1]);

        return $course;
    }

    private function person(int $id, int $hours): void
    {
        $rut = '9900000'.$id.'-K';
        DB::table('reemplazos_personal')->insert(['establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
            'rut' => $rut, 'nombre' => 'Educadora sintética '.$id, 'anio' => 2026, 'mes' => 8,
            'jornada' => $hours, 'jornada_basica' => $hours, 'tipocontrato' => 'PLANTA',
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-'.$id]);
        DB::table('declaracion_sostenedores')->insert(['id' => $id, 'rbd' => $this->ee->rbd, 'rut' => $rut,
            'nombres' => 'Educadora sintética '.$id, 'nombre_titulo' => 'Pedagogía en Educación de Párvulos',
            'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE', 'horas_contratadas' => $hours]);
    }

    private function assignment(int $id, int $docente, array $need, float $aula): array
    {
        return ['anio' => 2027, 'establecimiento_id' => $this->ee->id, 'docente_rut' => '9900000'.$docente.'-K',
            'docente_rut_normalizado' => '9900000'.$docente.'K', 'docente_nombre' => 'Educadora sintética '.$docente,
            'declaracion_sostenedor_id' => $docente, 'estamento_cobertura' => 'docente', 'tipo_asignacion' => 'plan_estudio',
            'subtipo_asignacion' => $need['subtipo_asignacion'], 'necesidad_key' => $need['key'],
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'asignatura_nombre' => $need['titulo'],
            'horas_plan_pedagogicas' => $aula, 'horas_contrato' => 0, 'estado' => 'activa',
            'proporcion_aplicada' => 'NT Con JEC · base contractual 55 h',
            'fuente_calculo' => 'Regla especial Parvularia · Reparto proporcional: '.$aula.' / 38 h de plan × 55 h de contrato.'];
    }

    private function store(array $overrides = []): void
    {
        $need = $this->needs['Lenguaje Verbal'];
        app(DotacionAsignacionController::class)->store($this->request(array_replace([
            'anio' => 2027, 'docente_rut' => '99000001-K', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => $need['subtipo_asignacion'],
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'necesidad_key' => $need['key'],
            'horas_plan_pedagogicas' => 5,
        ], $overrides)), $this->ee);
    }

    private function request(array $data): Request
    {
        $request = Request::create('/asignaciones-sinteticas', 'POST', $data);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);

        return $request;
    }

    private function process(): array
    {
        return DotacionProceso2027Calculator::resumen($this->ee, 2027);
    }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) {
                (new ReflectionProperty($class, $property))->setValue(null, []);
            }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }
}
