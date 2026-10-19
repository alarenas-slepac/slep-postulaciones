<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Controllers\Admin\DotacionCursoCombinadoController;
use App\Models\Asignatura;
use App\Models\Curso;
use App\Models\DotacionCursoCombinado;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionDocentesSubsector;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionLecturaCache;
use App\Support\DotacionPlanTitularPrimero;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionCombinadoPlanConfiguradoTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    private DotacionCursoCombinado $grupo;

    private PlanEstudio $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
        foreach (['2026_01_23_000001_create_establecimientos_table.php', '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php', '2026_05_18_150000_create_asignaturas_table.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php', '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php', '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('dotacion_docente_asignaciones', fn (Blueprint $t) => $t->string('estamento_cobertura')->default('docente'));
        // Sólo tablas nuevas en SQLite :memory:, sin recrear tablas históricas.
        Schema::create('establecimiento_planes_estudio', function (Blueprint $t): void {
            $t->id();
            $t->string('estado')->default('enviado');
            foreach (['establecimiento_id', 'establecimiento_curso_id', 'curso_id', 'plan_estudio_id', 'anio'] as $field) {
                $t->integer($field);
            }
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $t): void {
            $t->id();
            foreach (['establecimiento_plan_estudio_id', 'plan_estudio_bloque_id', 'asignatura_id', 'asignatura_plan_comun_id', 'orden'] as $field) {
                $t->integer($field)->nullable();
            }
            foreach (['nombre_asignatura_personalizada', 'origen', 'observacion'] as $field) {
                $t->string($field)->nullable();
            }
            $t->decimal('horas_semanales', 8, 2);
            $t->decimal('horas_anuales', 8, 2)->nullable();
        });
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SINTETICO', 'nombre_establecimiento' => 'EE sintético']);
        $nt1 = Curso::where('codigo', 'NT1')->firstOrFail();
        $this->plan = PlanEstudio::create(['curso_id' => $nt1->id, 'anio' => 2027, 'nombre_plan' => 'NT con JEC sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_subtotal' => 32, 'horas_semanales_libre_disposicion' => 6,
            'horas_semanales_total' => 38, 'activo' => true]);
        $base = $this->plan->bloques()->create(['nombre' => 'Tiempo mínimo obligatorio', 'tipo_bloque' => 'plan_comun_formacion_general',
            'horas_semanales' => 32, 'activo' => true]);
        $ld = $this->plan->bloques()->create(['nombre' => 'Horas de libre disposición', 'tipo_bloque' => 'libre_disposicion',
            'horas_semanales' => 6, 'activo' => true]);
        foreach (['Lenguaje Verbal' => 6, 'Lenguajes Artísticos' => 4, 'Convivencia y Ciudadanía' => 4,
            'Corporalidad y Movimiento' => 2, 'Identidad y Autonomía' => 4, 'Comprensión del Entorno Sociocultural' => 2,
            'Exploración del Entorno Natural' => 2, 'Pensamiento Matemático' => 8] as $name => $hours) {
            $this->plan->asignaturas()->create(['asignatura' => $name, 'tipo_bloque' => 'asignatura', 'horas_semanales' => $hours]);
            Asignatura::create(['codigo' => substr(sha1($name), 0, 12), 'nombre' => $name, 'tipo_asignatura' => 'obligatoria', 'activo' => true]);
        }
        $this->plan->asignaturas()->create(['asignatura' => 'Horas de libre disposición', 'tipo_bloque' => 'libre_disposicion', 'horas_semanales' => 6]);
        $this->grupo = DotacionCursoCombinado::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'nombre' => 'NT1 y NT2 sintético', 'proporcion' => '65_35', 'activo' => true]);
        foreach (['NT1', 'NT2'] as $code) {
            $catalogo = Curso::where('codigo', $code)->firstOrFail();
            $course = EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'curso_id' => $catalogo->id,
                'plan_estudio_id' => $this->plan->id, 'anio' => 2027, 'letra' => 'A', 'nombre_seccion' => $code.' A',
                'matricula' => 10, 'regimen_jec' => 'Con JEC', 'activo' => true]);
            $this->grupo->miembros()->create(['establecimiento_curso_id' => $course->id]);
            $config = DB::table('establecimiento_planes_estudio')->insertGetId(['establecimiento_id' => $this->ee->id,
                'establecimiento_curso_id' => $course->id, 'curso_id' => $catalogo->id, 'plan_estudio_id' => $this->plan->id, 'anio' => 2027]);
            foreach ($this->plan->asignaturas->where('tipo_bloque', 'asignatura') as $subject) {
                DB::table('establecimiento_planes_estudio_asignaturas')->insert(['establecimiento_plan_estudio_id' => $config,
                    'plan_estudio_bloque_id' => $base->id, 'asignatura_id' => Asignatura::where('nombre', $subject->asignatura)->value('id'),
                    'horas_semanales' => $subject->horas_semanales, 'orden' => 1]);
            }
            foreach (['Inglés' => 'Lenguaje Verbal', 'Educación Física' => 'Corporalidad y Movimiento'] as $name => $canonical) {
                $id = Asignatura::where('nombre', $canonical)->value('id');
                DB::table('establecimiento_planes_estudio_asignaturas')->insert(['establecimiento_plan_estudio_id' => $config,
                    'plan_estudio_bloque_id' => $ld->id, 'asignatura_id' => $id, 'asignatura_plan_comun_id' => $id,
                    'nombre_asignatura_personalizada' => $name, 'horas_semanales' => 3, 'orden' => 1]);
            }
        }
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    public function test_nt1_nt2_muestran_32_h_comunes_y_6_h_libres_sin_duplicar_el_plan(): void
    {
        $before = DB::table('establecimiento_planes_estudio_asignaturas')->get()->toJson();
        $needs = $this->needs();
        $this->assertCount(10, $needs);
        $this->assertCount(10, $needs->pluck('key')->unique());
        foreach (['Lenguaje Verbal' => 6, 'Corporalidad y Movimiento' => 2, 'Inglés' => 3, 'Educación Física' => 3] as $name => $hours) {
            $this->assertEquals($hours, $needs->firstWhere('titulo', $name)['horas_plan_requeridas']);
        }
        $this->assertEquals(38, $needs->sum('horas_plan_requeridas'));
        $libres = $needs->where('curso_combinado_libre_disposicion', true);
        $this->assertEquals(6, $libres->sum('horas_plan_requeridas'));
        $this->assertEquals(32, $needs->where('curso_combinado_libre_disposicion', false)->sum('horas_plan_requeridas'));
        $summary = DotacionCursoCombinadoCalculator::summary($this->ee, 2027, $needs);
        $this->assertEquals(76, $summary['resumen']['horas_brutas']);
        $this->assertEquals(38, $summary['resumen']['reduccion']);
        $this->assertEquals(44, $summary['resumen']['horas_contrato']);
        $this->assertSame($before, DB::table('establecimiento_planes_estudio_asignaturas')->get()->toJson());
    }

    public function test_solo_libre_disposicion_habilita_seleccion_libre_y_usa_el_nombre_configurado(): void
    {
        foreach ($this->needs() as $need) {
            $libre = in_array($need['titulo'], ['Inglés', 'Educación Física'], true);
            $this->assertSame($libre, DotacionPlanTitularPrimero::permiteSeleccionLibre($need));
            if ($libre) {
                $this->assertSame('libre_disposicion', $need['subtipo_asignacion']);
                $this->assertSame($need['titulo'], $need['asignatura_nombre']);
                $this->assertEquals(3, $need['curso_combinado_horas_libre_disposicion']);
            }
        }
    }

    public function test_guardar_modalidades_y_asignar_no_vuelve_a_fusionar_la_libre_disposicion(): void
    {
        $initial = $this->needs();
        $this->actingAs($this->testUser(1, 3));
        $this->withSession(['active_role' => 'admin']);
        $request = Request::create('/combinacion-sintetica', 'PUT', ['anio' => 2027,
            'nombre' => $this->grupo->nombre, 'proporcion' => '65_35', 'activo' => true,
            'curso_ids' => $this->grupo->miembros()->pluck('establecimiento_curso_id')->all(),
            'asignaturas' => $initial->map(fn ($need) => ['key' => $need['curso_combinado_asignatura_key'],
                'nombre' => $need['titulo'], 'modalidad' => 'conjunta'])->all()]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        app(DotacionCursoCombinadoController::class)->update($request, $this->ee, $this->grupo);
        $this->assertSame(10, $this->grupo->asignaturas()->count());
        foreach (['Corporalidad y Movimiento', 'Educación Física'] as $name) {
            $this->assignment($initial->firstWhere('titulo', $name), 2);
        }
        $after = $this->needs();
        $this->assertCount(10, $after);
        $this->assertSame($initial->pluck('key')->all(), $after->pluck('key')->all());
        $this->assertEquals(0, $after->firstWhere('titulo', 'Corporalidad y Movimiento')['horas_plan_pendientes']);
        $this->assertEquals(1, $after->firstWhere('titulo', 'Educación Física')['horas_plan_pendientes']);
        $this->assertEquals(4, $after->sum('horas_plan_asignadas'));
    }

    public function test_reglas_conjuntas_por_defecto_no_ocultan_la_libre_disposicion(): void
    {
        foreach (['Lenguaje Verbal', 'Corporalidad y Movimiento'] as $name) {
            $this->grupo->asignaturas()->create(['asignatura_key' => DotacionCursoCombinadoCalculator::subjectKey(['titulo' => $name]),
                'asignatura_nombre' => $name, 'modalidad' => 'conjunta']);
        }
        $this->assertCount(10, $this->needs());
    }

    public function test_dos_talleres_asociados_al_mismo_nucleo_conservan_sus_nombres_y_horas(): void
    {
        $rows = DB::table('establecimiento_planes_estudio_asignaturas')->where('nombre_asignatura_personalizada', 'Inglés')->get();
        foreach ($rows as $row) {
            DB::table('establecimiento_planes_estudio_asignaturas')->where('id', $row->id)->update(['horas_semanales' => 1.5]);
            $copy = (array) $row;
            unset($copy['id']);
            DB::table('establecimiento_planes_estudio_asignaturas')->insert(array_merge($copy,
                ['nombre_asignatura_personalizada' => 'Taller de Música sintético', 'horas_semanales' => 1.5]));
        }
        $needs = $this->needs();
        $this->assertCount(11, $needs);
        $this->assertEquals(1.5, $needs->firstWhere('titulo', 'Inglés')['horas_plan_requeridas']);
        $this->assertEquals(1.5, $needs->firstWhere('titulo', 'Taller de Música sintético')['horas_plan_requeridas']);
        $this->assertEquals(38, $needs->sum('horas_plan_requeridas'));
    }

    public function test_mismo_nombre_en_plan_comun_y_libre_conserva_dos_claves_y_sus_reglas(): void
    {
        DB::table('establecimiento_planes_estudio_asignaturas')->where('nombre_asignatura_personalizada', 'Inglés')
            ->update(['nombre_asignatura_personalizada' => 'Lenguaje Verbal']);
        $rows = $this->needs()->where('titulo', 'Lenguaje Verbal');
        $this->assertCount(2, $rows);
        $this->assertCount(2, $rows->pluck('key')->unique());
        $this->assertEquals(6, $rows->firstWhere('curso_combinado_libre_disposicion', false)['horas_plan_requeridas']);
        $this->assertEquals(3, $rows->firstWhere('curso_combinado_libre_disposicion', true)['horas_plan_requeridas']);
    }

    public function test_modalidad_de_ingles_no_modifica_el_nucleo_comun_ni_educacion_fisica(): void
    {
        $english = $this->needs()->firstWhere('titulo', 'Inglés');
        $this->assertNotNull($english);
        $this->grupo->asignaturas()->create(['asignatura_key' => $english['curso_combinado_asignatura_key'],
            'asignatura_nombre' => 'Inglés', 'modalidad' => 'separada']);
        $needs = $this->needs();
        $this->assertEquals(6, $needs->firstWhere('titulo', 'Inglés')['horas_plan_requeridas']);
        $this->assertEquals(6, $needs->firstWhere('titulo', 'Lenguaje Verbal')['horas_plan_requeridas']);
        $this->assertEquals(3, $needs->firstWhere('titulo', 'Educación Física')['horas_plan_requeridas']);
        $this->assertEquals(41, $needs->sum('horas_plan_requeridas'));
    }

    public function test_una_modalidad_historica_explicita_conserva_su_clave_y_sus_horas(): void
    {
        $key = DotacionCursoCombinadoCalculator::subjectKey(['titulo' => 'Corporalidad y Movimiento']);
        $this->grupo->asignaturas()->create(['asignatura_key' => $key, 'asignatura_nombre' => 'Corporalidad y Movimiento',
            'modalidad' => 'personalizada', 'horas_personalizadas' => 4]);
        $need = $this->needs()->firstWhere('titulo', 'Corporalidad y Movimiento');
        $this->assertSame(DotacionCursoCombinadoCalculator::needKey($this->grupo->id, $key), $need['key']);
        $this->assertEquals(4, $need['horas_plan_requeridas']);
        $this->assertFalse($need['curso_combinado_libre_disposicion']);
        $this->assertStringContainsString('Educación Física', $need['fuente']);
        $this->assertEquals(37, $this->needs()->sum('horas_plan_requeridas'));
    }

    public function test_asignaciones_historicas_mixtas_no_se_borran_ni_se_acreditan_dos_veces(): void
    {
        $key = DotacionCursoCombinadoCalculator::needKey($this->grupo->id,
            DotacionCursoCombinadoCalculator::subjectKey(['titulo' => 'Corporalidad y Movimiento']));
        $this->assignment(['key' => $key, 'titulo' => 'Corporalidad y Movimiento',
            'establecimiento_curso_id' => $this->grupo->miembros()->first()->establecimiento_curso_id], 5);
        $needs = $this->needs();
        $need = $needs->firstWhere('key', $key);
        $this->assertNotNull($need);
        $this->assertEquals(5, $need['horas_plan_asignadas']);
        $this->assertEquals(0, $need['horas_plan_pendientes']);
        $this->assertEquals(5, $needs->sum('horas_plan_asignadas'));
        $this->assertSame(1, DotacionDocenteAsignacion::count());
    }

    public function test_no_permite_asignar_cuatro_horas_a_libre_disposicion_de_tres(): void
    {
        $need = $this->needs()->firstWhere('titulo', 'Educación Física');
        $this->assertNotNull($need);
        $this->expectException(ValidationException::class);
        (new ReflectionMethod(DotacionAsignacionController::class, 'validatePlanHoursAvailable'))->invoke(
            new DotacionAsignacionController, $this->ee, ['anio' => 2027, 'tipo_asignacion' => 'plan_estudio',
                'subtipo_asignacion' => 'curso_combinado', 'necesidad_key' => $need['key'], 'horas_plan_pedagogicas' => 4]);
    }

    public function test_payload_del_especialista_conserva_libre_disposicion_y_nombre_real(): void
    {
        $need = $this->needs()->firstWhere('titulo', 'Educación Física');
        $this->assertNotNull($need);
        $docente = ['rut' => '99000001-K', 'rut_normalizado' => '99000001K', 'nombre' => 'Especialista sintético',
            'nombre_titulo' => 'Pedagogía en Educación Física'];
        $request = Request::create('/', 'POST');
        $payload = (new ReflectionMethod(DotacionAsignacionController::class, 'buildPayload'))->invoke(
            new DotacionAsignacionController, $request, $this->ee, $docente, ['anio' => 2027,
                'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'curso_combinado',
                'necesidad_key' => $need['key'], 'dotacion_curso_combinado_id' => $this->grupo->id,
                'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'asignatura_nombre' => 'Nombre enviado inválido',
                'horas_plan_pedagogicas' => 3]);
        $this->assertSame('libre_disposicion', $payload['subtipo_asignacion']);
        $this->assertSame('Educación Física', $payload['asignatura_nombre']);
        $this->assertSame('General', $payload['subvencion']);
        $this->assertSame($this->grupo->id, $payload['dotacion_curso_combinado_id']);
    }

    public function test_matriz_muestra_la_libre_disposicion_en_edicion_y_consulta(): void
    {
        $summary = DotacionCursoCombinadoCalculator::summary($this->ee, 2027, $this->needs());
        foreach ([false, true] as $canManage) {
            $html = view('admin.dotacion-establecimiento.partials._cursos_combinados', ['establecimiento' => $this->ee,
                'anio' => 2027, 'cursosCombinados' => $summary, 'canManageCursosCombinados' => $canManage,
                'errors' => new ViewErrorBag])->render();
            $this->assertStringContainsString('Inglés', $html);
            $this->assertStringContainsString('Educación Física', $html);
            $this->assertStringContainsString('Libre disposición', $html);
        }
    }

    public function test_guarda_tres_horas_de_educacion_fisica_con_especialista_aunque_haya_educadoras_titulares(): void
    {
        foreach (['2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_09_22_180000_create_dotacion_proceso_2027_configuraciones_table.php',
            '2026_09_29_130000_create_dotacion_docente_subsectores_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
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
        foreach (['99000001-K' => ['PLANTA', 'Pedagogía en Educación de Párvulos'],
            '99000002-K' => ['PLANTA', 'Pedagogía en Educación de Párvulos'],
            '99000003-K' => ['CONTRATA', 'Pedagogía en Educación Física']] as $rut => [$type, $title]) {
            DB::table('reemplazos_personal')->insert(['establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
                'rut' => $rut, 'nombre' => 'Docente sintético '.$rut, 'anio' => 2026, 'mes' => 8, 'jornada' => 44,
                'jornada_basica' => 44, 'tipocontrato' => $type, 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-'.$rut]);
            DB::table('declaracion_sostenedores')->insert(['rbd' => $this->ee->rbd, 'rut' => $rut,
                'nombres' => 'Docente sintético '.$rut, 'nombre_titulo' => $title, 'nombre_funcion' => 'DOCENTE',
                'estamento' => 'DOCENTE', 'horas_contratadas' => 44]);
        }
        $config = DotacionProceso2027Configuracion::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'decision_combinacion' => 'combinaciones_configuradas',
            'max_horas_bloque_1' => 500, 'max_horas_bloque_2' => 500, 'max_horas_bloque_3' => 500]);
        $this->resetCaches();
        $process = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        $config->update(['funciones_normativas' => collect($process['funciones_normativas'])->mapWithKeys(fn ($f) => [$f['key'] => false])->all()]);
        foreach ($this->needs() as $need) {
            foreach (['99000001K', '99000002K', '99000003K'] as $rut) {
                DB::table('dotacion_docente_subsectores')->insert(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
                    'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need), 'docente_rut_normalizado' => $rut,
                    'nivel' => 'parvularia', 'asignatura_nombre' => $need['titulo']]);
            }
        }
        $this->assertTrue(DotacionProceso2027Calculator::resumen($this->ee, 2027)['asignacion_habilitada']);
        $this->actingAs($this->testUser(1, 3));
        $this->withSession(['active_role' => 'admin']);
        $need = $this->needs()->firstWhere('titulo', 'Educación Física');
        $request = Request::create('/asignaciones-sinteticas', 'POST', ['anio' => 2027,
            'docente_rut' => '99000003-K', 'estamento_cobertura' => 'docente', 'tipo_asignacion' => 'plan_estudio',
            'subtipo_asignacion' => $need['subtipo_asignacion'], 'necesidad_key' => $need['key'],
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'dotacion_curso_combinado_id' => $this->grupo->id,
            'asignatura_nombre' => $need['titulo'], 'horas_plan_pedagogicas' => 3]);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        $response = app(DotacionAsignacionController::class)->store($request, $this->ee);
        $this->assertNotNull($response->getSession()->get('success'));
        $saved = DotacionDocenteAsignacion::sole();
        $this->assertSame('99000003K', $saved->docente_rut_normalizado);
        $this->assertSame('libre_disposicion', $saved->subtipo_asignacion);
        $this->assertSame('Educación Física', $saved->asignatura_nombre);
        $this->assertNull($saved->excepcion_prelacion);
        $this->assertEquals(3, $saved->horas_plan_pedagogicas);
        $this->assertEquals(0, $this->needs()->firstWhere('titulo', 'Educación Física')['horas_plan_pendientes']);
        $this->assertEquals(2, $this->needs()->firstWhere('titulo', 'Corporalidad y Movimiento')['horas_plan_pendientes']);
    }

    private function needs(): Collection
    {
        return DotacionLecturaCache::ejecutar(fn () => DotacionAsignacionCalculator::necesidades($this->ee, 2027,
            [], [], DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027))['plan_estudio']);
    }

    private function assignment(array $need, float $hours): void
    {
        DotacionDocenteAsignacion::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente sintético',
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'dotacion_curso_combinado_id' => $this->grupo->id,
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => $need['subtipo_asignacion'] ?? 'curso_combinado',
            'necesidad_key' => $need['key'], 'asignatura_nombre' => $need['titulo'],
            'horas_plan_pedagogicas' => $hours, 'horas_contrato' => $hours, 'estado' => 'activa']);
    }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) {
                (new ReflectionProperty($class, $name))->setValue(null, []);
            }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }
}
