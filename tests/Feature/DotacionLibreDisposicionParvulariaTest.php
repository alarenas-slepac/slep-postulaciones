<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionFuncionEstablecimiento;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionDocentesSubsector;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionFuncionesNormativas2027;
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
        Schema::table('establecimiento_curso_pie', function (Blueprint $t): void {
            $t->unsignedInteger('prof_educ_dif_minutos')->nullable();
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
        $this->associate('99000002K');
        $this->actingAs($this->testUser(1, 3));
        $this->withSession(['active_role' => 'admin']);
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public function test_especialista_asociado_puede_asignarse_sin_prelar_ni_justificar(): void
    {
        $process = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        $this->assertTrue($process['asignacion_habilitada']);
        $this->assertGreaterThanOrEqual(1, collect($process['docentes'])->firstWhere('rut_normalizado', '99000001K')['horas_titulares_disponibles']);
        $this->assertTrue(DB::table('dotacion_docente_subsectores')->where('docente_rut_normalizado', '99000002K')->exists());

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

    public function test_religion_permite_contrata_asociada_sin_prelar_al_crear_y_reasignar(): void
    {
        $need = $this->basicSubject('Religión Católica');
        $this->store([], $need);
        $saved = DotacionDocenteAsignacion::sole();
        $this->assertSame('99000002K', $saved->docente_rut_normalizado);
        $this->assertNull($saved->excepcion_prelacion);
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => '99000003-K', 'estamento_cobertura' => 'docente', 'horas_plan_pedagogicas' => 2,
        ]), $this->ee, $saved);
        $this->assertSame('99000003K', $saved->fresh()->docente_rut_normalizado);
    }

    public function test_nombre_religion_en_formulario_no_exime_otra_asignatura(): void
    {
        $need = $this->basicSubject('Matemática');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Esta asignatura aún tiene docentes');
        $this->store(['asignatura_nombre' => 'Religión'], $need);
    }

    public function test_pie_colaborativo_permite_elegir_sin_prelar_al_crear_y_editar(): void
    {
        $this->differentialNeed();
        $need = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.pie_colaborativo'))->sole();
        app(DotacionAsignacionController::class)->store($this->request([
            'anio' => 2027, 'docente_rut' => '99000002-K', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'pie_colaborativo', 'subtipo_asignacion' => $need['subtipo_asignacion'],
            'necesidad_key' => $need['key'], 'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'horas_contrato' => 3,
        ]), $this->ee);
        $saved = DotacionDocenteAsignacion::sole();
        $this->assertSame('99000002K', $saved->docente_rut_normalizado);
        $this->assertNull($saved->excepcion_prelacion);
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => '99000003-K', 'estamento_cobertura' => 'docente', 'horas_contrato' => 3,
        ]), $this->ee, $saved);
        $this->assertSame('99000003K', $saved->fresh()->docente_rut_normalizado);
    }

    public function test_reserva_titular_agotada_no_bloquea_otro_titular_por_saldo_contrata(): void
    {
        $need = $this->basicSubject('Matemática');
        $this->mixedContract();
        $this->protectedSituation('horas_gremiales');
        DB::table('reemplazos_personal')->where('rut', '99000003-K')->update(['tipocontrato' => 'PLANTA']);
        try {
            $this->store(['docente_rut' => '99000003-K'], $need);
            $this->fail('Antes de reservar, el docente protegido con saldo titular debe tener prioridad.');
        } catch (ValidationException $error) { $this->assertStringContainsString('prioridad superior', $error->getMessage()); }
        app(DotacionAsignacionController::class)->storeReserva($this->request([
            'anio' => 2027, 'docente_rut' => '99000002-K', 'horas_contrato' => 8,
        ]), $this->ee);
        $protected = collect(DotacionProceso2027Calculator::resumen($this->ee, 2027)['docentes'])->firstWhere('rut_normalizado', '99000002K');
        $this->assertEquals(0, $protected['horas_titulares_disponibles']);
        $this->assertEquals(36, $protected['horas_contrata_disponibles']);
        $xpath = $this->assignmentXpath();
        $form = $xpath->query('//form[input[@name="necesidad_key" and @value="'.$need['key'].'"]]')->item(0);
        $visibleRuts = [];
        foreach ($this->catalogOptions($xpath, $form) as $option) { $visibleRuts[] = $option->getAttribute('value'); }
        $this->assertNotContains('99000002-K', $visibleRuts);
        $this->assertContains('99000003-K', $visibleRuts);
        $this->store(['docente_rut' => '99000003-K'], $need);
        $this->assertSame('99000003K', DotacionDocenteAsignacion::where('tipo_asignacion', 'plan_estudio')->sole()->docente_rut_normalizado);
        $this->assertEquals(8, DotacionDocenteAsignacion::where('tipo_asignacion', 'reserva_no_normativa')->sole()->horas_contrato);
    }

    public static function protectedReasons(): array
    {
        return [['fuero_maternal'], ['horas_gremiales'], ['horas_lactancia', true]];
    }

    #[DataProvider('protectedReasons')]
    public function test_reserva_contrata_con_situacion_registrada_y_limite_de_saldo(string $motivo, bool $fuero = false): void
    {
        $this->protectedSituation($motivo, $fuero);
        app(DotacionAsignacionController::class)->storeReserva($this->request([
            'anio' => 2027, 'docente_rut' => '99000002-K', 'horas_contrato' => 4,
        ]), $this->ee);
        $saved = DotacionDocenteAsignacion::sole();
        $this->assertSame('contrata', $saved->subtipo_asignacion);
        $this->assertEquals(4, $saved->horas_contrato);
        $this->expectException(ValidationException::class);
        app(DotacionAsignacionController::class)->storeReserva($this->request([
            'anio' => 2027, 'docente_rut' => '99000002-K', 'horas_contrato' => 41,
        ]), $this->ee);
    }

    public function test_reserva_no_puede_simular_fuero_en_el_formulario(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La contrata sólo se permite');
        app(DotacionAsignacionController::class)->storeReserva($this->request([
            'anio' => 2027, 'docente_rut' => '99000002-K', 'horas_contrato' => 4,
            'motivo' => 'fuero_maternal', 'posee_fuero_maternal' => true,
        ]), $this->ee);
    }

    public function test_reserva_mixta_consume_primero_titulares_del_docente_y_luego_contrata_protegida(): void
    {
        $this->mixedContract();
        $this->protectedSituation('fuero_maternal');
        foreach ([8, 4] as $horas) {
            app(DotacionAsignacionController::class)->storeReserva($this->request([
                'anio' => 2027, 'docente_rut' => '99000002-K', 'horas_contrato' => $horas,
            ]), $this->ee);
        }
        $this->assertSame(['titular', 'contrata'], DotacionDocenteAsignacion::orderBy('id')->pluck('subtipo_asignacion')->all());
        $docente = collect(DotacionProceso2027Calculator::resumen($this->ee, 2027)['docentes'])->firstWhere('rut_normalizado', '99000002K');
        $this->assertEquals(32, $docente['horas_disponibles']);
        $this->assertEquals(32, $docente['horas_contrata_disponibles']);
    }

    public function test_selector_religion_y_pie_colaborativo_muestran_seleccion_sin_prelacion(): void
    {
        $religion = $this->basicSubject('Religión');
        $this->differentialNeed();
        $pie = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.pie_colaborativo'))->sole();
        $xpath = $this->assignmentXpath();
        foreach ([$religion, $pie] as $need) {
            $form = $xpath->query('//form[input[@name="necesidad_key" and @value="'.$need['key'].'"]]')->item(0);
            $this->assertNotNull($form);
            $this->assertStringContainsString('sin prelación', $form->textContent);
            $this->assertSame(0, $xpath->query('.//input[@name="excepcion_prelacion"]', $form)->length);
            $ruts = [];
            foreach ($this->catalogOptions($xpath, $form) as $option) { $ruts[] = $option->getAttribute('value'); }
            $this->assertContains('99000001-K', $ruts);
            $this->assertContains('99000002-K', $ruts);
        }
        $form = $xpath->query('//form[input[@name="necesidad_key" and @value="'.$religion['key'].'"]]')->item(0);
        $this->assertSame('', $xpath->query('.//select[@name="docente_rut"]', $form)->item(0)->getAttribute('data-fase-plan'));
    }

    public function test_religion_conserva_asociacion_y_saldo_contractual(): void
    {
        $need = $this->basicSubject('Religión');
        DB::table('reemplazos_personal')->where('rut', '99000002-K')->update(['jornada' => 1, 'jornada_basica' => 1]);
        try {
            $this->store([], $need);
            $this->fail('Religión debe conservar el saldo contractual.');
        } catch (ValidationException $error) { $this->assertStringContainsString('dispone de 1 hora(s)', $error->getMessage()); }
        DB::table('dotacion_docente_subsectores')->where('asignatura_key', DotacionDocentesSubsector::keyParaNecesidad($need))->where('docente_rut_normalizado', '99000002K')->delete();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('El docente no está asociado');
        $this->store([], $need);
    }

    public function test_pie_colaborativo_sin_prelacion_conserva_restriccion_de_coordinador_pie(): void
    {
        $this->differentialNeed();
        $need = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.pie_colaborativo'))->sole();
        DB::table('declaracion_sostenedores')->where('rut', '99000002-K')->update(['nombre_funcion' => 'COORDINADOR PIE']);
        $response = app(DotacionAsignacionController::class)->store($this->request([
            'anio' => 2027, 'docente_rut' => '99000002-K', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'pie_colaborativo', 'necesidad_key' => $need['key'],
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'horas_contrato' => 3,
        ]), $this->ee);
        $this->assertTrue($response->getSession()->get('errors')->has('docente_rut'));
        $this->assertSame(0, DotacionDocenteAsignacion::count());
    }

    private function assignmentXpath(): \DOMXPath
    {
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $this->app['view']->share('errors', new ViewErrorBag);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', [
            'establecimiento' => $this->ee, 'anio' => 2027, 'docentes' => $data['docentes'],
            'asignacion' => $data['asignacion'], 'proceso2027' => DotacionProceso2027Calculator::resumen($this->ee, 2027, $data),
        ])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }

    private function basicSubject(string $nombre): array
    {
        $course = $this->createCourse('1B');
        DB::table('planes_estudio_asignaturas')->where('plan_estudio_id', $course->plan_estudio_id)
            ->where('tipo_bloque', 'plan_comun_formacion_general')->update(['asignatura' => $nombre]);
        $this->configureStages();
        $need = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.plan_estudio'))->firstWhere('titulo', $nombre);
        foreach (['99000002K', '99000003K'] as $rut) {
            DB::table('dotacion_docente_subsectores')->insert([
                'establecimiento_id' => $this->ee->id, 'anio' => 2027,
                'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need), 'nivel' => 'basica',
                'asignatura_nombre' => $nombre, 'docente_rut_normalizado' => $rut,
            ]);
        }
        return $need;
    }

    private function mixedContract(): void
    {
        $original = (array) DB::table('reemplazos_personal')->where('rut', '99000002-K')->sole();
        DB::table('reemplazos_personal')->where('id', $original['id'])->update(['tipocontrato' => 'PLANTA', 'jornada' => 8, 'jornada_basica' => 8]);
        unset($original['id']);
        $original['jornada'] = $original['jornada_basica'] = 36;
        $original['row_hash'] = 'synthetic-mixed-contract';
        DB::table('reemplazos_personal')->insert($original);
    }

    private function protectedSituation(string $motivo, bool $fuero = false): void
    {
        Schema::create('dotacion_docente_exclusiones', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->integer('anio');
            $t->string('docente_rut'); $t->string('docente_rut_normalizado'); $t->string('docente_nombre');
            $t->string('motivo'); $t->decimal('horas', 8, 2)->default(0); $t->boolean('posee_fuero_maternal')->default(false);
            $t->timestamps();
        });
        DB::table('dotacion_docente_exclusiones')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut' => '99000002-K',
            'docente_rut_normalizado' => '99000002K', 'docente_nombre' => 'Especialista sintético B',
            'motivo' => $motivo, 'posee_fuero_maternal' => $fuero, 'horas' => 0,
        ]);
        $this->resetCalculators();
    }

    public function test_reasignacion_admite_otro_especialista_asociado(): void
    {
        $this->store();
        $this->associate('99000003K');
        $assignment = DotacionDocenteAsignacion::sole();
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => '99000003-K', 'estamento_cobertura' => 'docente', 'horas_plan_pedagogicas' => 2,
        ]), $this->ee, $assignment);
        $this->assertSame('99000003K', $assignment->fresh()->docente_rut_normalizado);
        $this->assertNull($assignment->fresh()->excepcion_prelacion);
        $this->assertEquals(0, DotacionEstablecimientoCalculator::docentes($this->ee, 2027)->firstWhere('rut_normalizado', '99000002K')['horas_asignadas_total']);
    }

    public function test_libre_disposicion_admite_profesor_de_ingles_tercero_en_el_orden(): void
    {
        $this->personal('99000004-K', 'Educadora sintética B', 'PLANTA', 'Pedagogía en Educación de Párvulos');
        DB::table('declaracion_sostenedores')->where('rut', '99000002-K')->update(['nombres' => 'Profesor de Inglés sintético', 'nombre_titulo' => 'Pedagogía en Inglés']);
        DB::table('declaracion_sostenedores')->where('rut', '99000003-K')->update(['nombres' => 'Z docente sintético']);
        $process = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        $this->assertSame('99000002K', collect($process['docentes'])->values()[2]['rut_normalizado']);
        $this->store();
        $this->assertSame('99000002K', DotacionDocenteAsignacion::sole()->docente_rut_normalizado);
    }

    public static function functionCategories(): array
    {
        return [
            'directiva' => ['directiva', 'funcion_directiva'],
            'técnico pedagógica' => ['tecnico_pedagogica', 'funcion_tecnico_pedagogica'],
            'planes' => ['planes_programas', 'plan_normativo'],
            'otras funciones' => ['otras_funciones_docentes', 'otra_funcion'],
        ];
    }

    #[DataProvider('functionCategories')]
    public function test_funcion_permite_docente_de_menor_prioridad_sin_excepcion(string $category, string $type): void
    {
        $this->coverPlan();
        $need = $this->declaredFunction($category);
        $this->assertTrue(DotacionProceso2027Calculator::resumen($this->ee, 2027)['funciones_no_normativas_habilitadas']);
        $response = app(DotacionAsignacionController::class)->store($this->request([
            'anio' => 2027, 'docente_rut' => '99000002-K', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => $type, 'subtipo_asignacion' => $need['subtipo_asignacion'],
            'necesidad_key' => $need['key'], 'dotacion_funcion_id' => $need['dotacion_funcion_id'],
            'horas_contrato' => 4,
        ]), $this->ee);
        $this->assertNotNull($response->getSession()->get('success'));
        $saved = DotacionDocenteAsignacion::where('tipo_asignacion', $type)->sole();
        $this->assertSame('99000002K', $saved->docente_rut_normalizado);
        $this->assertNull($saved->excepcion_prelacion);
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => '99000003-K', 'estamento_cobertura' => 'docente', 'horas_contrato' => 4,
        ]), $this->ee, $saved);
        $this->assertSame('99000003K', $saved->fresh()->docente_rut_normalizado);
    }

    public function test_bolsa_excluye_otros_titulos_de_la_prelacion(): void
    {
        $need = $this->differentialNeed();
        $this->personal('99000005-K', 'Diferencial sintética', 'CONTRATA', 'Pedagogía en Educación Diferencial');
        $this->assignDifferential($need, '99000005-K');
        $this->assertSame('99000005K', DotacionDocenteAsignacion::sole()->docente_rut_normalizado);
    }

    public function test_bolsa_rechaza_otro_titulo_aunque_se_fuerce_el_formulario(): void
    {
        $need = $this->differentialNeed();
        try {
            $this->assignDifferential($need, '99000001-K');
            $this->fail('Un título de otra especialidad no puede recibir la bolsa diferencial.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('docente_rut', $exception->errors());
        }
        $this->assertSame(0, DotacionDocenteAsignacion::count());
    }

    public function test_bolsa_admite_sin_titulo_y_mantiene_preferencia_dentro_del_grupo(): void
    {
        $need = $this->differentialNeed();
        $this->personal('99000005-K', 'Diferencial sintética', 'CONTRATA', 'Pedagogía en Educación Diferencial');
        $this->personal('99000006-K', 'Sin título sintético', 'PLANTA', '');
        try {
            $this->assignDifferential($need, '99000005-K');
            $this->fail('La bolsa debe respetar la prelación de los candidatos elegibles.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('prioridad superior', $exception->getMessage());
        }
        $this->assignDifferential($need, '99000006-K');
        $this->assertSame('99000006K', DotacionDocenteAsignacion::sole()->docente_rut_normalizado);
    }

    public function test_bolsa_reasignacion_rechaza_otro_titulo_sin_modificar_el_registro(): void
    {
        $need = $this->differentialNeed();
        $this->personal('99000005-K', 'Diferencial sintética', 'CONTRATA', 'Pedagogía en Educación Diferencial');
        $this->assignDifferential($need, '99000005-K');
        $saved = DotacionDocenteAsignacion::sole();
        $before = $saved->getAttributes();
        try {
            app(DotacionAsignacionController::class)->update($this->request([
                'docente_rut' => '99000001-K', 'estamento_cobertura' => 'docente', 'horas_contrato' => 2,
            ]), $this->ee, $saved);
            $this->fail('La reasignación debe conservar la restricción de título de la bolsa.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('sólo admite', $exception->getMessage());
        }
        $this->assertSame($before, $saved->fresh()->getAttributes());
    }

    public function test_funcion_normativa_sin_prelar_conserva_sus_horas_definidas(): void
    {
        $process = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        $potential = collect($process['funciones_normativas'])->first(fn ($item) =>
            ($item['subtipo'] ?? '') === 'tecnico_pedagogica'
            && (float) ($item['horas_potenciales'] ?? 0) >= 4
        );
        $this->assertNotNull($potential);
        $settings = DotacionProceso2027Configuracion::first()->funciones_normativas;
        $settings[$potential['key']] = true;
        $settings[DotacionFuncionesNormativas2027::HORAS_KEY] = [$potential['codigo'] => 4];
        DotacionProceso2027Configuracion::first()->update(['funciones_normativas' => $settings]);
        $need = DotacionAsignacionCalculator::funcionNormativaParaAsignacion($this->ee, 2027, [
            'necesidad_key' => $potential['key'],
        ]);
        $this->assertEquals(4, $need['horas_contrato_requeridas']);
        $fields = [
            'anio' => 2027, 'docente_rut' => '99000002-K', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => $need['tipo_asignacion'], 'subtipo_asignacion' => $need['subtipo_asignacion'],
            'necesidad_key' => $need['key'], 'dotacion_funcion_regla_id' => $need['dotacion_funcion_regla_id'],
            'horas_contrato' => 4,
        ];
        app(DotacionAsignacionController::class)->store($this->request($fields), $this->ee);
        $this->assertSame('99000002K', DotacionDocenteAsignacion::sole()->docente_rut_normalizado);
        try {
            $fields['horas_contrato'] = 0.25;
            app(DotacionAsignacionController::class)->store($this->request($fields), $this->ee);
            $this->fail('No se puede superar la definición normativa al quitar la prelación.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('horas_contrato', $exception->errors());
        }
        $this->assertSame(1, DotacionDocenteAsignacion::count());
    }

    public function test_selector_bolsa_filtra_perfiles_y_funcion_conserva_orden_informativo(): void
    {
        $bag = $this->differentialNeed();
        $this->personal('99000005-K', 'Diferencial sintética', 'CONTRATA', 'Educación Diferencial');
        $this->personal('99000006-K', 'Sin título sintético', 'PLANTA', '');
        $function = $this->declaredFunction('otras_funciones_docentes');
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $this->app['view']->share('errors', new ViewErrorBag);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', [
            'establecimiento' => $this->ee, 'anio' => 2027, 'docentes' => $data['docentes'],
            'asignacion' => $data['asignacion'], 'proceso2027' => DotacionProceso2027Calculator::resumen($this->ee, 2027, $data),
        ])->render(function ($view, string $content): string {
            if (getenv('SGA_PRELACION_PREVIEW') === '1') {
                $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
                $styles = $this->app['view']->yieldPushContent('styles');
                $scripts = $this->app['view']->yieldPushContent('scripts');
                $preview = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Asignación 2027 · Datos ficticios</title>'
                    .'<link rel="stylesheet" href="/build/'.$manifest['resources/scss/app.scss']['file'].'">'
                    .'<link rel="stylesheet" href="/build/'.$manifest['resources/css/dotacion-establecimiento.css']['file'].'">'.$styles.'</head><body>'
                    .'<main class="container-fluid p-3"><p class="text-muted">Prueba con datos ficticios.</p><div class="dotacion-workspace">'.$content.'</div></main>'
                    .'<script>document.addEventListener("submit",function(event){event.preventDefault();event.stopImmediatePropagation();},true);</script>'
                    .'<script type="module" src="/build/'.$manifest['resources/js/app.js']['file'].'"></script>'
                    .'<script type="module" src="/build/'.$manifest['resources/js/dotacion-asignacion.js']['file'].'"></script>'.$scripts.'</body></html>';
                file_put_contents(storage_path('framework/testing/prelacion-2027-preview.html'), $preview);
            }
            return $content;
        });
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $bagForm = $xpath->query('//form[input[@name="necesidad_key" and @value="'.$bag['key'].'"]]')->item(0);
        $this->assertNotNull($bagForm);
        $options = $this->catalogOptions($xpath, $bagForm);
        $this->assertSame(2, $options->length);
        $this->assertSame('99000006-K', $options[0]->getAttribute('value'));
        $this->assertSame('99000005-K', $options[1]->getAttribute('value'));
        $functionForm = $xpath->query('//form[input[@name="necesidad_key" and @value="'.$function['key'].'"]]')->item(0);
        $this->assertNotNull($functionForm);
        $this->assertStringContainsString('Orden informativo', $functionForm->textContent);
        $this->assertStringContainsString('no bloquea', $functionForm->textContent);
        $functionOptions = $this->catalogOptions($xpath, $functionForm);
        $this->assertSame('99000001-K', $functionOptions[0]->getAttribute('value'));
        $this->assertSame('99000002-K', $functionOptions[4]->getAttribute('value'));
        $this->assertTrue($functionOptions[4]->hasAttribute('data-prioridad-label'));
    }

    public function test_cupo_general_por_no_renovacion_debe_asociarse_y_puede_asignarse_y_editarse(): void
    {
        foreach (['2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php', '2026_10_13_200000_add_dotacion_no_renovaciones.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $need = $this->basicSubject('Religión');
        $this->store([], $need);
        $previous = DotacionDocenteAsignacion::sole();
        app(\App\Services\Dotacion\NoRenovacionService::class)->reemplazar($this->ee, 2027, 'plan_estudio', '99000002-K', 'No renovación sintética justificada.', auth()->id());
        $cupo = DB::table('dotacion_contrata_habilitaciones')->sole();
        $rut = 'VACANTE-'.$cupo->id;
        $this->assertSame('no_renovada', $previous->fresh()->estado);
        $this->assertFalse(DB::table('dotacion_docente_subsectores')->where('docente_rut_normalizado', 'VACANTE_'.$cupo->id)->exists());
        try {
            $this->store(['docente_rut' => $rut], $need);
            $this->fail('Debe asociar expresamente el cupo a la asignatura.');
        } catch (ValidationException $error) { $this->assertStringContainsString('no está asociado', $error->getMessage()); }
        app(\App\Http\Controllers\Admin\DotacionProceso2027Controller::class)->syncDocentesSubsector($this->request([
            'anio' => 2027, 'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need), 'docentes' => [$rut],
        ]), $this->ee);
        $this->store(['docente_rut' => $rut], $need);
        $saved = DotacionDocenteAsignacion::where('estado', 'activa')->sole();
        $this->assertSame('VACANTE_'.$cupo->id, $saved->docente_rut_normalizado);
        $this->assertEquals(2, $saved->horas_plan_pedagogicas);
        $this->assertGreaterThan(0, $saved->horas_contrato);
        $virtual = app(\App\Services\Dotacion\ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, $rut);
        $this->assertSame('Pedagogía en Educación Física', $virtual['titulo']);
        $this->assertGreaterThan(0, $virtual['horas_asignadas_total']);
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => $rut, 'horas_plan_pedagogicas' => 3,
        ]), $this->ee, $saved);
        $this->assertEquals(3, $saved->fresh()->horas_plan_pedagogicas);
        $xpath = $this->assignmentXpath();
        $form = $xpath->query('//form[input[@name="necesidad_key" and @value="'.$need['key'].'"]]')->item(0);
        $options = $this->catalogOptions($xpath, $form);
        $values = [];
        foreach ($options as $option) { $values[] = $option->getAttribute('value'); }
        $this->assertContains($rut, $values);
        $this->assertNotContains('99000002-K', $values);
    }

    private function coverPlan(): void
    {
        $this->personal('99000007-K', 'Titular sintético de cobertura', 'PLANTA', 'Pedagogía en Educación Básica');
        $need = $this->need(false);
        DB::table('dotacion_docente_subsectores')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need),
            'nivel' => 'parvularia', 'asignatura_nombre' => $need['titulo'], 'docente_rut_normalizado' => '99000007K',
        ]);
        $this->store(['docente_rut' => '99000007-K', 'horas_plan_pedagogicas' => 32], $need);
        $this->store(['horas_plan_pedagogicas' => 6]);
    }

    private function declaredFunction(string $category): array
    {
        $function = DotacionFuncionEstablecimiento::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'categoria' => $category,
            'nombre_funcion' => 'Función sintética '.$category, 'horas_declaradas' => 4, 'estado' => 'borrador',
        ]);
        return collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.funciones'))
            ->firstWhere('dotacion_funcion_id', $function->id);
    }

    private function differentialNeed(): array
    {
        DB::table('establecimiento_curso_pie')->insert([
            'establecimiento_id' => $this->ee->id, 'establecimiento_curso_id' => $this->course->id,
            'curso_id' => $this->course->curso_id, 'anio' => 2027, 'total_pie' => 6,
            'necesidades_transitorias' => 5, 'necesidades_permanentes' => 1, 'prof_educ_dif_minutos' => 600,
        ]);
        $this->configureStages();
        return collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.pie_educadora_diferencial'))->sole();
    }

    private function assignDifferential(array $need, string $rut): void
    {
        app(DotacionAsignacionController::class)->store($this->request([
            'anio' => 2027, 'docente_rut' => $rut, 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'pie_educadora_diferencial', 'subtipo_asignacion' => 'bolsa_total',
            'necesidad_key' => $need['key'], 'horas_contrato' => 2,
        ]), $this->ee);
    }

    public function test_curso_combinado_nt1_nt2_admite_especialista_asociado_sin_prelacion(): void
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

    public function test_selector_es_individual_y_muestra_solo_docentes_asociados(): void
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
        $this->assertSame(0, $xpath->query('.//input[@name="excepcion_prelacion"]', $form)->length);
        $this->assertStringContainsString('Elija un docente asociado a esta asignatura', $form->textContent);
        $this->assertStringContainsString('No se exige prelación ni justificación', $form->textContent);
        $this->assertSame(0, $xpath->query('.//option[@data-estamento="docente"]', $select)->length);
        $options = $this->catalogOptions($xpath, $select);
        $this->assertSame(2, $options->length);
        $this->assertSame('99000001-K', $options->item(0)->getAttribute('value'));
        $this->assertSame('99000002-K', $options->item(1)->getAttribute('value'));
    }

    private function catalogOptions(\DOMXPath $xpath, \DOMNode $scope): \DOMNodeList
    {
        $paths = [];
        foreach ($xpath->query('.//optgroup[@data-personal-catalogo]', $scope) as $reference) {
            $paths[] = '//template[@id="'.$reference->getAttribute('data-personal-catalogo').'"]//option[@data-estamento="docente"]';
        }
        return $xpath->query(implode(' | ', $paths), $scope);
    }

    public function test_libre_disposicion_rechaza_docente_no_asociado_a_la_asignatura(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('El docente no está asociado a esta asignatura');
        $this->store(['docente_rut' => '99000003-K']);
    }

    public function test_reasignacion_no_admite_docente_no_asociado_y_conserva_asignacion(): void
    {
        $this->store();
        $assignment = DotacionDocenteAsignacion::sole();
        $before = $assignment->getAttributes();
        try {
            app(DotacionAsignacionController::class)->update($this->request([
                'docente_rut' => '99000003-K', 'estamento_cobertura' => 'docente', 'horas_plan_pedagogicas' => 2,
            ]), $this->ee, $assignment);
            $this->fail('Una reasignación requiere un docente asociado a la asignatura.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('El docente no está asociado', $exception->getMessage());
        }
        $this->assertSame($before, $assignment->fresh()->getAttributes());
        $this->assertSame(1, DotacionDocenteAsignacion::count());
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

    private function associate(string $rut): void
    {
        $need = $this->need();
        DB::table('dotacion_docente_subsectores')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need),
            'nivel' => 'parvularia', 'asignatura_nombre' => $need['titulo'],
            'docente_rut_normalizado' => $rut,
        ]);
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
