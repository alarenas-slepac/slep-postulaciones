<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Controllers\Admin\DotacionProceso2027Controller;
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

class DotacionEpjaAsistentesTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp(); // Confirma SQLite :memory:, configuración sin caché y discos simulados.
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
        Schema::table('dotacion_docente_asignaciones', fn (Blueprint $t) => $t->string('estamento_cobertura')->default('docente'));
        Schema::table('establecimiento_curso_pie', fn (Blueprint $t) => $t->unsignedInteger('prof_educ_dif_minutos')->nullable());
        Schema::table('dotacion_proceso_2027_configuraciones', fn (Blueprint $t) => $t->json('funciones_normativas')->nullable());
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); $t->string('rbd'); $t->string('rut'); $t->integer('horas_contratadas');
            foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $field) {
                $t->string($field)->nullable();
            }
        });
        $this->ee = Establecimiento::create([
            'rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'Establecimiento sintético',
        ]);
        Curso::create(['nombre' => 'Primer nivel EPJA', 'codigo' => 'EPJA-1', 'nivel_educativo' => 'Educación Media', 'modalidad' => 'EPJA', 'activo' => true]);
        foreach ([2026, 2027] as $year) {
            $this->createCourse($year, 'EPJA-1');
            $this->createCourse($year, '1B');
        }
        $this->personal('99000001-K', 'Docente sintético', 'DOCENTE');
        $this->personal('99000002-K', 'Asistente asociado sintético', 'ASISTENTE DE LA EDUCACION');
        $this->personal('99000003-K', 'Asistente no asociado sintético', 'ASISTENTE DE LA EDUCACION');
        DotacionProceso2027Configuracion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'decision_combinacion' => 'sin_combinacion',
            'max_horas_bloque_1' => 500, 'max_horas_bloque_2' => 500, 'max_horas_bloque_3' => 500,
        ]);
        $process = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        DotacionProceso2027Configuracion::first()->update([
            'funciones_normativas' => collect($process['funciones_normativas'])->mapWithKeys(fn ($f) => [$f['key'] => false])->all(),
        ]);
        foreach ([2026, 2027] as $year) {
            foreach ($this->needs($year) as $need) {
                $this->associate($year, $need, ['99000001K']);
            }
        }
        $this->actingAs($this->testUser(1, 3));
        $this->withSession(['active_role' => 'admin']);
        $this->app['request']->setLaravelSession($this->app['session.store']);
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public static function years(): array
    {
        return [[2026], [2027]];
    }

    #[DataProvider('years')]
    public function test_asocia_asistentes_epja_por_anio_y_permite_asignar_sin_prelar_docentes_titulares(int $year): void
    {
        $need = $this->epja($year);
        $this->sync($year, $need, ['99000001-K', '99000002-K']);
        $this->assertDatabaseHas('dotacion_docente_subsectores', ['anio' => $year, 'docente_rut_normalizado' => '99000002K']);
        $this->assertDatabaseMissing('dotacion_docente_subsectores', ['anio' => $year === 2026 ? 2027 : 2026, 'docente_rut_normalizado' => '99000002K']);
        $this->store($year, $need);
        $assignment = DotacionDocenteAsignacion::sole();
        $this->assertSame('asistente', $assignment->estamento_cobertura);
        $this->assertSame('AAEE', $assignment->proporcion_aplicada);
        $this->assertEquals(2, $assignment->horas_plan_pedagogicas);
        $this->assertEquals(3, $assignment->horas_contrato);
        $this->assertNull($assignment->horas_cronologicas_aula);
        // El docente titular sigue con saldo: no impide la cobertura AAEE de EPJA.
        $this->assertEquals(0, DotacionDocenteAsignacion::where('estamento_cobertura', 'docente')->count());
    }

    #[DataProvider('years')]
    public function test_selector_muestra_solo_asistentes_asociados_y_conserva_tipo_de_cobertura(int $year): void
    {
        $need = $this->epja($year);
        $this->sync($year, $need, ['99000001K', '99000002K']);
        $data = DotacionEstablecimientoCalculator::build($this->ee, $year);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', [
            'establecimiento' => $this->ee, 'anio' => $year, 'docentes' => $data['docentes'], 'asignacion' => $data['asignacion'],
            'proceso2027' => DotacionProceso2027Calculator::resumen($this->ee, $year, $data),
            'subsectoresEpja2026' => $year === 2026 ? DotacionDocentesSubsector::resumenEpja2026($this->ee, $data) : null,
            'errors' => new ViewErrorBag,
        ])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $form = $xpath->query('//form[input[@name="necesidad_key" and @value="'.$need['key'].'"]]')->item(0);
        $this->assertNotNull($form);
        $this->assertSame(1, $xpath->query('.//select[@name="estamento_cobertura"]/option[@value="asistente"]', $form)->length);
        $options = [];
        foreach ($xpath->query('.//optgroup[@data-personal-catalogo]', $form) as $group) {
            foreach ($xpath->query('//template[@id="'.$group->getAttribute('data-personal-catalogo').'"]//option') as $option) {
                $options[$option->getAttribute('value')] = $option->getAttribute('data-estamento');
            }
        }
        $this->assertSame('asistente', $options['99000002-K']);
        $this->assertArrayNotHasKey('99000003-K', $options);
        $this->assertSame('docente', $options['99000001-K']);
        if ($year === 2026) {
            $this->assertSame(1, $xpath->query('//section[@id="dotacion-docentes-subsector"]')->length);
            $this->assertSame('2026', $xpath->query('//form[input[@name="asignatura_key"]]/input[@name="anio"]')->item(0)->getAttribute('value'));
            $this->assertSame(2, $xpath->query('//select[@name="docentes[]"]/option[@data-estamento="asistente"]')->length);
        }
    }

    #[DataProvider('years')]
    public function test_rechaza_asistente_no_asociado_aunque_se_altere_el_formulario(int $year): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no está asociado a esta asignatura EPJA');
        $this->store($year, $this->epja($year));
    }

    #[DataProvider('years')]
    public function test_reasignacion_conserva_contrato_aaee_y_rechaza_otro_asistente_no_asociado(int $year): void
    {
        $need = $this->epja($year);
        $this->sync($year, $need, ['99000002K']);
        $this->store($year, $need);
        $assignment = DotacionDocenteAsignacion::sole();
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => '99000002-K', 'estamento_cobertura' => 'asistente', 'horas_plan_pedagogicas' => 3, 'horas_contrato' => 4,
        ]), $this->ee, $assignment);
        $this->assertEquals(4, $assignment->refresh()->horas_contrato);
        try {
            app(DotacionAsignacionController::class)->update($this->request([
                'docente_rut' => '99000003-K', 'estamento_cobertura' => 'asistente', 'horas_plan_pedagogicas' => 3, 'horas_contrato' => 4,
            ]), $this->ee, $assignment);
            $this->fail('Un asistente sin asociación no debe recibir horas EPJA.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('docente_rut', $error->errors());
        }
        $this->assertSame('99000002K', $assignment->refresh()->docente_rut_normalizado);
        $this->assertEquals(4, $assignment->horas_contrato);
    }

    #[DataProvider('years')]
    public function test_conserva_limite_de_contrato_del_asistente_y_de_horas_del_plan(int $year): void
    {
        $need = $this->epja($year);
        $this->sync($year, $need, ['99000002K']);
        $this->store($year, $need, ['horas_contrato' => 43]);
        foreach ([['horas_plan_pedagogicas' => 2, 'horas_contrato' => 2], ['horas_plan_pedagogicas' => 9, 'horas_contrato' => 1]] as $excess) {
            try {
                $this->store($year, $need, $excess);
                $this->fail('No se debe sobreasignar contrato ni el plan.');
            } catch (ValidationException $error) {
                $this->assertNotEmpty($error->errors());
            }
        }
        $this->assertSame(1, DotacionDocenteAsignacion::count());
        // Editar libera el contrato de la propia asignación antes de verificar el saldo.
        $assignment = DotacionDocenteAsignacion::sole();
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => '99000002-K', 'estamento_cobertura' => 'asistente', 'horas_plan_pedagogicas' => 2, 'horas_contrato' => 44,
        ]), $this->ee, $assignment);
        $this->assertEquals(44, $assignment->refresh()->horas_contrato);
    }

    #[DataProvider('years')]
    public function test_no_permite_asociar_asistentes_a_educacion_regular(int $year): void
    {
        $need = collect($this->needs($year))->first(fn ($n) => DotacionDocentesSubsector::nivel($n) === 'basica');
        $this->expectException(ValidationException::class);
        $this->sync($year, $need, ['99000002K']);
    }

    public function test_selector_de_asociaciones_2027_ofrece_asistentes_solo_en_epja(): void
    {
        $process = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        $html = view('admin.dotacion-establecimiento.partials._docentes_subsector', [
            'establecimiento' => $this->ee, 'anio' => 2027, 'proceso' => $process, 'errors' => new ViewErrorBag,
        ])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//*[@id="dotacion-subsector-epja"]//option[@data-estamento="asistente"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="dotacion-subsector-basica"]//option[@data-estamento="asistente"]')->length);
        $this->assertSame(2, $xpath->query('//form[input[@name="asignatura_key"]]/input[@name="anio" and @value="2027"]')->length);
        $this->assertStringContainsString('Asistente de la Educación', $xpath->query('//*[@id="dotacion-subsector-epja"]')->item(0)->textContent);
    }

    #[DataProvider('years')]
    public function test_asistente_de_otro_establecimiento_no_puede_asociarse_ni_asignarse(int $year): void
    {
        $other = Establecimiento::create(['rbd' => 99998, 'cod_estab' => 'SYNTHETIC-OTHER', 'nombre_establecimiento' => 'Otro establecimiento sintético']);
        DB::table('reemplazos_personal')->where('rut', '99000003-K')->update(['establecimiento_id' => $other->id, 'rbd' => $other->rbd]);
        $need = $this->epja($year);
        try {
            $this->sync($year, $need, ['99000003K']);
            $this->fail('El padrón de otro establecimiento no debe asociarse.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('docentes', $error->errors());
        }
        $result = app(DotacionAsignacionController::class)->store($this->request([
            'anio' => $year, 'docente_rut' => '99000003-K', 'estamento_cobertura' => 'asistente', 'tipo_asignacion' => 'plan_estudio',
        ]), $this->ee);
        $this->assertTrue($result->getSession()->get('errors')->has('docente_rut'));
        $this->assertSame(0, DotacionDocenteAsignacion::count());
    }

    public function test_epja_no_omite_las_etapas_previas_2027(): void
    {
        $need = $this->epja(2027);
        $this->sync(2027, $need, ['99000002K']);
        DotacionProceso2027Configuracion::first()->update(['max_horas_bloque_1' => null]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('debe completar planes de estudio');
        $this->store(2027, $need);
    }

    public function test_directivo_no_asocia_personas_de_otro_establecimiento(): void
    {
        DB::table('roles')->insert(['id' => 4, 'name' => 'funcionario_directivo_estab', 'guard_name' => 'web']);
        $user = $this->testUser(2, 4);
        $user->setAttribute('establecimiento_id', $this->ee->id + 1);
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->sync(2026, $this->epja(2026), ['99000002K']);
    }

    #[DataProvider('years')]
    public function test_conserva_asistente_historico_y_completa_asociacion_epja(int $year): void
    {
        $need = $this->epja($year);
        $need['asignaciones'] = [['estamento_cobertura' => 'asistente', 'docente_rut' => '99.000.002-K']];
        $data = DotacionEstablecimientoCalculator::build($this->ee, $year);
        $summary = DotacionDocentesSubsector::resumen($this->ee, $year, collect([$need]), collect($data['docentes']), collect($data['asignacion']['asistentes']));
        $this->assertContains('99000002K', $summary['asignaturas']->sole()['docentes']);
        $this->assertTrue($summary['completo']);
    }

    #[DataProvider('years')]
    public function test_asociacion_historica_se_consolida_entre_cursos_de_la_misma_asignatura_epja(int $year): void
    {
        $need = $this->epja($year);
        $this->sync($year, $need, ['99000001K', '99000002K']);
        $this->store($year, $need);
        DB::table('dotacion_docente_subsectores')->where('anio', $year)->where('docente_rut_normalizado', '99000002K')->delete();
        $second = EstablecimientoCurso::findOrFail($need['establecimiento_curso_id'])->replicate();
        $second->fill(['letra' => 'B', 'nombre_seccion' => 'EPJA sintético B'])->save();
        $secondNeed = collect($this->needs($year))->firstWhere('establecimiento_curso_id', $second->id);
        $this->store($year, $secondNeed);
        $this->assertSame(2, DotacionDocenteAsignacion::where('docente_rut_normalizado', '99000002K')->count());
    }

    private function createCourse(int $year, string $code): void
    {
        $catalog = Curso::where('codigo', $code)->firstOrFail();
        $plan = PlanEstudio::create([
            'curso_id' => $catalog->id, 'anio' => $year, 'nombre_plan' => 'Plan sintético '.$code,
            'regimen_jec' => 'Sin JEC', 'horas_semanales_total' => 10, 'horas_semanales_subtotal' => 10, 'activo' => true,
        ]);
        DB::table('planes_estudio_asignaturas')->insert([
            'plan_estudio_id' => $plan->id, 'asignatura' => 'Asignatura sintética', 'tipo_bloque' => 'plan_comun_formacion_general', 'horas_semanales' => 10,
        ]);
        EstablecimientoCurso::create([
            'establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd, 'curso_id' => $catalog->id, 'plan_estudio_id' => $plan->id,
            'anio' => $year, 'letra' => 'A', 'nombre_seccion' => $code.' sintético A', 'matricula' => 20, 'regimen_jec' => 'Sin JEC', 'activo' => true,
        ]);
    }

    private function personal(string $rut, string $name, string $estamento): void
    {
        DB::table('reemplazos_personal')->insert([
            'establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd, 'rut' => $rut, 'nombre' => $name,
            'anio' => 2026, 'mes' => 8, 'jornada' => 44, 'jornada_basica' => 44, 'tipocontrato' => 'PLANTA',
            'estatuto' => $estamento, 'escalafon' => $estamento, 'row_hash' => 'synthetic-'.$rut,
        ]);
        DB::table('declaracion_sostenedores')->insert([
            'rbd' => $this->ee->rbd, 'rut' => $rut, 'nombres' => $name, 'nombre_titulo' => 'Título sintético',
            'nombre_funcion' => $estamento, 'estamento' => $estamento, 'horas_contratadas' => 44,
        ]);
    }

    private function needs(int $year): array
    {
        return collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, $year), 'asignacion.necesidades.plan_estudio'))->all();
    }

    private function epja(int $year): array
    {
        return collect($this->needs($year))->first(fn ($n) => DotacionDocentesSubsector::nivel($n) === 'epja');
    }

    private function associate(int $year, array $need, array $ruts): void
    {
        foreach ($ruts as $rut) {
            DB::table('dotacion_docente_subsectores')->insert([
                'establecimiento_id' => $this->ee->id, 'anio' => $year, 'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need),
                'nivel' => DotacionDocentesSubsector::nivel($need), 'asignatura_nombre' => $need['titulo'], 'docente_rut_normalizado' => $rut,
            ]);
        }
    }

    private function sync(int $year, array $need, array $ruts): void
    {
        app(DotacionProceso2027Controller::class)->syncDocentesSubsector($this->request([
            'anio' => $year, 'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need), 'docentes' => $ruts,
        ]), $this->ee);
    }

    private function store(int $year, array $need, array $extra = []): void
    {
        app(DotacionAsignacionController::class)->store($this->request(array_merge([
            'anio' => $year, 'docente_rut' => '99000002-K', 'estamento_cobertura' => 'asistente',
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => $need['subtipo_asignacion'], 'necesidad_key' => $need['key'],
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'plan_estudio_id' => $need['plan_estudio_id'],
            'asignatura_nombre' => $need['titulo'], 'horas_plan_pedagogicas' => 2, 'horas_contrato' => 3, 'subvencion' => 'General',
        ], $extra)), $this->ee);
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
