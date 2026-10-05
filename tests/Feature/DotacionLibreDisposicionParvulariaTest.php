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
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionDocentesSubsector;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionPlanTitularPrimero;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionLibreDisposicionParvulariaTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;
    private EstablecimientoCurso $course;

    protected function setUp(): void
    {
        // Aislamiento previo al bootstrap, SQLite :memory: y discos simulados.
        parent::setUp();
        $this->resetCalculators();
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
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->string('estamento_cobertura')->default('docente');
        });
        Schema::table('dotacion_proceso_2027_configuraciones', function (Blueprint $t): void {
            $t->json('funciones_normativas')->nullable();
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); $t->string('rbd'); $t->string('rut'); $t->integer('horas_contratadas');
            foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $field) {
                $t->string($field)->nullable();
            }
        });
        $this->ee = Establecimiento::create([
            'rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'Establecimiento sintético',
        ]);
        $this->course = $this->createCourse('NT1');
        $this->personal('99000001-K', 'Educadora sintética', 'PLANTA', 'Pedagogía en Educación de Párvulos');
        $this->personal('99000002-K', 'Especialista sintético B', 'CONTRATA', 'Pedagogía en Educación Física');
        $this->personal('99000003-K', 'Especialista sintético A', 'CONTRATA', 'Pedagogía en Música');
        DotacionProceso2027Configuracion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'decision_combinacion' => 'sin_combinacion',
            'max_horas_bloque_1' => 500, 'max_horas_bloque_2' => 500, 'max_horas_bloque_3' => 500,
        ]);
        $this->configureStages();
        $this->actingAs($this->testUser(1, 3));
        $this->withSession(['active_role' => 'admin']);
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public function test_especialista_no_asociado_puede_asignarse_sin_prelar_ni_justificar(): void
    {
        $process = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        $this->assertTrue($process['asignacion_habilitada']);
        $this->assertGreaterThanOrEqual(1, collect($process['docentes'])->firstWhere('rut_normalizado', '99000001K')['horas_titulares_disponibles']);
        $this->assertFalse(DB::table('dotacion_docente_subsectores')->where('docente_rut_normalizado', '99000002K')->exists());

        $response = $this->store();
        $assignment = DotacionDocenteAsignacion::sole();

        $this->assertNotNull($response->getSession()->get('success'));
        $this->assertSame('99000002K', $assignment->docente_rut_normalizado);
        $this->assertSame('General', $assignment->subvencion);
        $this->assertNull($assignment->excepcion_prelacion);
        $this->assertEquals(2, $assignment->horas_plan_pedagogicas);
        $this->assertGreaterThan(0, $assignment->horas_contrato);
        $this->assertStringContainsString('65/35', $assignment->proporcion_aplicada);
        $after = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        $this->assertGreaterThan(0, $after['bloques']['bloque_1']['asignadas']);
        $this->assertEquals(0, $after['bloques']['bloque_2']['asignadas']);
    }

    public function test_reasignacion_admite_otro_especialista_no_asociado(): void
    {
        $this->store();
        $assignment = DotacionDocenteAsignacion::sole();
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => '99000003-K', 'estamento_cobertura' => 'docente', 'horas_plan_pedagogicas' => 2,
        ]), $this->ee, $assignment);
        $this->assertSame('99000003K', $assignment->fresh()->docente_rut_normalizado);
        $this->assertNull($assignment->fresh()->excepcion_prelacion);
        $this->assertEquals(0, DotacionEstablecimientoCalculator::docentes($this->ee, 2027)->firstWhere('rut_normalizado', '99000002K')['horas_asignadas_total']);
    }

    public function test_curso_combinado_nt1_nt2_admite_especialista_sin_asociacion(): void
    {
        $second = $this->createCourse('NT2');
        $group = DB::table('dotacion_cursos_combinados')->insertGetId([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'nombre' => 'NT1 + NT2 sintético', 'proporcion' => 'auto', 'activo' => true,
        ]);
        foreach ([$this->course->id, $second->id] as $id) {
            DB::table('dotacion_curso_combinado_miembros')->insert([
                'dotacion_curso_combinado_id' => $group, 'establecimiento_curso_id' => $id,
            ]);
        }
        DotacionCursoCombinadoCalculator::clearCache();
        DotacionProceso2027Configuracion::first()->update(['decision_combinacion' => 'combinaciones_configuradas']);
        $this->configureStages();
        $this->store();
        $assignment = DotacionDocenteAsignacion::sole();
        $this->assertSame($group, $assignment->dotacion_curso_combinado_id);
        $this->assertSame('99000002K', $assignment->docente_rut_normalizado);
        $this->assertNull($assignment->excepcion_prelacion);
    }

    public function test_selector_es_individual_e_incluye_especialistas_no_asociados(): void
    {
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $this->app['view']->share('errors', new ViewErrorBag);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', [
            'establecimiento' => $this->ee, 'anio' => 2027, 'docentes' => $data['docentes'],
            'asignacion' => $data['asignacion'], 'proceso2027' => DotacionProceso2027Calculator::resumen($this->ee, 2027, $data),
        ])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form[input[@name="necesidad_key" and @value="'.$this->need()['key'].'"]]')->item(0);
        $this->assertNotNull($form);
        $select = $xpath->query('.//select[@name="docente_rut"]', $form)->item(0);
        $this->assertFalse($select->hasAttribute('multiple'));
        $this->assertSame('', $select->getAttribute('data-fase-plan'));
        $this->assertSame(1, $xpath->query('.//option[@value="99000002-K"]', $select)->length);
        $this->assertSame(1, $xpath->query('.//option[@value="99000001-K"]', $select)->length);
        $this->assertSame(0, $xpath->query('.//input[@name="excepcion_prelacion"]', $form)->length);
        $this->assertStringContainsString('No se exige asociación previa a esta asignatura, prelación ni justificación', $form->textContent);
        $options = $xpath->query('.//option[@data-estamento="docente"]', $select);
        $this->assertSame('99000001-K', $options->item(0)->getAttribute('value'));
        $this->assertSame('99000003-K', $options->item(1)->getAttribute('value'));
    }

    public function test_subtipo_enviado_no_exime_asignatura_obligatoria(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('El docente no está asociado');
        $this->store(['subtipo_asignacion' => 'libre_disposicion'], $this->need(false));
    }

    public function test_asignatura_obligatoria_conserva_titulares_primero(): void
    {
        $need = $this->need(false);
        DB::table('dotacion_docente_subsectores')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need),
            'nivel' => 'parvularia', 'asignatura_nombre' => $need['titulo'],
            'docente_rut_normalizado' => '99000002K',
        ]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Esta asignatura aún tiene docentes');
        $this->store([], $need);
    }

    public function test_libre_seleccion_conserva_maximo_autorizado(): void
    {
        DotacionProceso2027Configuracion::first()->update(['max_horas_bloque_1' => 0]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La asignación supera el máximo autorizado');
        $this->store();
    }

    public function test_libre_seleccion_conserva_saldo_individual(): void
    {
        DB::table('reemplazos_personal')->where('rut', '99000002-K')->update(['jornada' => 1, 'jornada_basica' => 1]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('dispone de 1 hora(s)');
        $this->store(['horas_plan_pedagogicas' => 6]);
    }

    public function test_libre_seleccion_conserva_cobertura_maxima_del_plan(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La asignatura dispone de 6 hora(s) aula');
        $this->store(['horas_plan_pedagogicas' => 7]);
    }

    public function test_docente_fuera_del_establecimiento_no_puede_asignarse(): void
    {
        $response = $this->store(['docente_rut' => '99000099-K']);
        $this->assertTrue($response->getSession()->get('errors')->has('docente_rut'));
        $this->assertSame(0, DotacionDocenteAsignacion::count());
    }

    public static function needs(): array
    {
        return [
            'NT1 libre disposición con JEC' => ['NT1', 'plan_estudio', 'libre_disposicion', true, false, true],
            'NT2 libre disposición con JEC' => ['NT2', 'plan_estudio', 'libre_disposicion', true, false, true],
            'NT combinado libre disposición' => ['NT1', 'plan_estudio', 'curso_combinado', true, true, true],
            'NT combinado plan común' => ['NT1', 'plan_estudio', 'curso_combinado', true, false, false],
            'NT sin JEC' => ['NT1', 'plan_estudio', 'libre_disposicion', false, false, false],
            'básica libre disposición' => ['1B', 'plan_estudio', 'libre_disposicion', true, false, false],
            'NT asignatura obligatoria' => ['NT1', 'plan_estudio', 'plan_comun_formacion_general', true, false, false],
            'NT acompañamiento' => ['NT1', 'acompanamiento_parvularia', 'libre_disposicion', true, false, false],
        ];
    }

    #[DataProvider('needs')]
    public function test_excepcion_se_limita_a_necesidad_nt_de_libre_disposicion_con_jec(string $code, string $type, string $subtype, bool $jec, bool $combinedLd, bool $expected): void
    {
        $course = new EstablecimientoCurso(['regimen_jec' => $jec ? 'Con JEC' : 'Sin JEC']);
        $course->setRelation('curso', Curso::where('codigo', $code)->firstOrFail());
        $course->setRelation('planEstudio', null);
        $this->assertSame($expected, DotacionPlanTitularPrimero::permiteSeleccionLibre([
            'curso' => $course, 'tipo_asignacion' => $type, 'subtipo_asignacion' => $subtype,
            'curso_combinado' => $subtype === 'curso_combinado', 'curso_combinado_libre_disposicion' => $combinedLd,
        ]));
    }

    private function createCourse(string $code): EstablecimientoCurso
    {
        $catalog = Curso::where('codigo', $code)->firstOrFail();
        $plan = PlanEstudio::create([
            'curso_id' => $catalog->id, 'anio' => 2027, 'nombre_plan' => 'Plan sintético '.$code,
            'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 38,
            'horas_semanales_subtotal' => 32, 'horas_semanales_libre_disposicion' => 6, 'activo' => true,
        ]);
        foreach ([['Núcleo sintético', 'plan_comun_formacion_general', 32], ['Deporte sintético', 'libre_disposicion', 6]] as [$name, $type, $hours]) {
            DB::table('planes_estudio_asignaturas')->insert([
                'plan_estudio_id' => $plan->id, 'asignatura' => $name, 'tipo_bloque' => $type, 'horas_semanales' => $hours,
            ]);
        }
        return EstablecimientoCurso::create([
            'establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
            'curso_id' => $catalog->id, 'plan_estudio_id' => $plan->id, 'anio' => 2027, 'letra' => 'A',
            'nombre_seccion' => $code.' sintético A', 'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true,
        ]);
    }

    private function personal(string $rut, string $name, string $type, string $title): void
    {
        DB::table('reemplazos_personal')->insert([
            'establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
            'rut' => $rut, 'nombre' => $name, 'anio' => 2026, 'mes' => 8, 'jornada' => 44,
            'jornada_basica' => 44, 'tipocontrato' => $type, 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-'.$rut,
        ]);
        DB::table('declaracion_sostenedores')->insert([
            'rbd' => $this->ee->rbd, 'rut' => $rut, 'nombres' => $name,
            'nombre_titulo' => $title, 'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE', 'horas_contratadas' => 44,
        ]);
    }

    private function configureStages(): void
    {
        $process = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        DotacionProceso2027Configuracion::first()->update([
            'funciones_normativas' => collect($process['funciones_normativas'])->mapWithKeys(fn ($f) => [$f['key'] => false])->all(),
        ]);
        foreach ($process['docentes_subsector']['asignaturas'] as $subject) {
            DB::table('dotacion_docente_subsectores')->updateOrInsert([
                'establecimiento_id' => $this->ee->id, 'anio' => 2027,
                'asignatura_key' => $subject['key'], 'docente_rut_normalizado' => '99000001K',
            ], ['nivel' => 'parvularia', 'asignatura_nombre' => $subject['nombre']]);
        }
    }

    private function need(bool $ld = true): array
    {
        $items = data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.plan_estudio');
        return collect($items)->first(fn ($item) => DotacionPlanTitularPrimero::permiteSeleccionLibre($item) === $ld);
    }

    private function store(array $overrides = [], ?array $need = null): \Illuminate\Http\RedirectResponse
    {
        $need ??= $this->need();
        return app(DotacionAsignacionController::class)->store($this->request(array_merge([
            'anio' => 2027, 'docente_rut' => '99000002-K', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => $need['subtipo_asignacion'],
            'necesidad_key' => $need['key'], 'establecimiento_curso_id' => $need['establecimiento_curso_id'],
            'dotacion_curso_combinado_id' => $need['dotacion_curso_combinado_id'] ?? null,
            'dotacion_curso_combinado_asignatura_id' => $need['dotacion_curso_combinado_asignatura_id'] ?? null,
            'plan_estudio_id' => $need['plan_estudio_id'], 'asignatura_nombre' => $need['titulo'],
            'horas_plan_pedagogicas' => 2, 'subvencion' => 'General',
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

    private function resetCalculators(): void
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
