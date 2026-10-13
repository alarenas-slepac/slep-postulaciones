<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionEstablecimientoController;
use App\Models\Curso;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionDocentesRendimientoTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCalculators();
        foreach ([
            '2026_01_23_000001_create_establecimientos_table.php',
            '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_150000_create_asignaturas_table.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        // La migración histórica de estas tablas contiene una recreación. Para
        // las pruebas sólo crear tablas nuevas en SQLite :memory:.
        Schema::create('establecimiento_planes_estudio', function (Blueprint $table): void {
            $table->id();
            foreach (['establecimiento_id', 'establecimiento_curso_id', 'curso_id', 'plan_estudio_id', 'anio'] as $field) {
                $table->integer($field);
            }
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $table): void {
            $table->id();
            foreach (['establecimiento_plan_estudio_id', 'plan_estudio_bloque_id', 'asignatura_id', 'asignatura_plan_comun_id', 'orden'] as $field) {
                $table->integer($field)->nullable();
            }
            foreach (['nombre_asignatura_personalizada', 'origen', 'observacion'] as $field) {
                $table->string($field)->nullable();
            }
            $table->decimal('horas_semanales');
            $table->decimal('horas_anuales')->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public function test_carga_noventa_docentes_y_veinticuatro_cursos_sin_alterar_datos(): void
    {
        $ee = $this->dataset();
        $user = $this->testUser(1, 3);
        $request = Request::create('/admin/dotacion-establecimiento/'.$ee->id, 'GET', ['anio' => 2026, 'tab' => 'docentes']);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session')->driver());
        $this->app->instance('request', $request);
        $this->actingAs($user);
        $snapshot = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $start = hrtime(true);
        $view = app(DotacionEstablecimientoController::class)->show($request, $ee);
        $calculationSeconds = (hrtime(true) - $start) / 1e9;
        $data = $view->getData();
        $renderStart = hrtime(true);
        $html = view('admin.dotacion-establecimiento.partials._docentes', $data)->render();
        $renderSeconds = (hrtime(true) - $renderStart) / 1e9;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(90, $data['docentes']);
        $this->assertSame(24, $data['resumen']['cursos_total']);
        $this->assertEquals(3960, $data['resumen']['horas_contrato_docentes']);
        $this->assertSame(90, substr_count($html, 'data-revision-row='));
        $this->assertSame(1, collect($queries)->filter(fn ($query) => str_contains($query['query'], 'from "dotacion_docente_asignaciones"'))->count());
        $this->assertSame(1, collect($queries)->filter(fn ($query) => str_contains($query['query'], 'from "establecimiento_planes_estudio_asignaturas"'))->count());
        $this->assertEmpty($data['asignaturas']['items']);
        $this->assertSame($snapshot, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
        if (getenv('SGA_DOTACION_BENCHMARK') === '1') {
            $shapes = collect($queries)->countBy('query')->sortDesc()->take(8)->all();
            fwrite(STDOUT, "\nDOTACION_BENCHMARK ".json_encode([
                'calculation_seconds' => round($calculationSeconds, 4),
                'docentes_render_seconds' => round($renderSeconds, 4),
                'queries' => count($queries), 'query_time_ms' => round(array_sum(array_column($queries, 'time')), 2),
                'docentes_html_bytes' => strlen($html), 'peak_mb' => memory_get_peak_usage(true) / 1048576,
                'summary_hash' => hash('sha256', json_encode($data['resumen'])),
                'docentes_hash' => hash('sha256', json_encode($data['docentes'])),
                'query_shapes' => $shapes,
            ], JSON_UNESCAPED_UNICODE)."\n");
        }
    }

    public function test_omitir_consolidado_conserva_indicadores_docentes_asistentes_y_cupos(): void
    {
        $ee = $this->dataset(8, 2);
        DB::table('reemplazos_personal')->insert([
            'establecimiento_id' => $ee->id, 'rbd' => $ee->rbd, 'rut' => '99000999-K',
            'nombre' => 'Asistente sintético', 'anio' => 2026, 'mes' => 10, 'jornada' => 44,
            'tipocontrato' => 'CONTRATA', 'estatuto' => 'ASISTENTE', 'escalafon' => 'ASISTENTE', 'row_hash' => 'synthetic-assistant',
        ]);
        (require database_path('migrations/2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php'))->up();
        DB::table('dotacion_contrata_habilitaciones')->insert([
            'establecimiento_id' => $ee->id, 'anio' => 2026, 'bloque' => 'pie', 'horas' => 22,
        ]);
        $full = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($ee, 2026));
        $light = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($ee, 2026, true, false));
        $this->assertNotEmpty($full['asignaturas']['items']);
        $this->assertEmpty($light['asignaturas']['items']);
        unset($full['asignaturas'], $light['asignaturas']);
        $this->assertSame(json_encode($full), json_encode($light));
        $this->assertCount(1, $light['asistentes']);
        $this->assertCount(3, $light['asignacion']['docentes']);
    }

    public static function assignmentYears(): array
    {
        return ['histórico' => [2026], 'proceso 2027' => [2027]];
    }

    #[DataProvider('assignmentYears')]
    public function test_asignacion_grande_conserva_necesidades_y_datos(int $year): void
    {
        $ee = $this->dataset(year: $year);
        $user = $this->testUser(1, 3);
        $request = Request::create('/admin/dotacion-establecimiento/'.$ee->id, 'GET', ['anio' => $year, 'tab' => 'asignacion']);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session')->driver());
        $this->app->instance('request', $request);
        $this->actingAs($user);
        $this->app['view']->share('errors', new \Illuminate\Support\ViewErrorBag);
        $snapshot = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $start = hrtime(true);
        $data = app(DotacionEstablecimientoController::class)->show($request, $ee)->getData();
        $calculation = (hrtime(true) - $start) / 1e9;
        $calculationQueries = count(DB::getQueryLog());
        $renderStart = hrtime(true);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', $data)->render(function ($view, string $content) use ($year): string {
            if (getenv('SGA_ASIGNACION_PREVIEW') === '1') {
                $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
                $styles = $this->app['view']->yieldPushContent('styles');
                $scripts = $this->app['view']->yieldPushContent('scripts');
                $preview = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Asignación '.$year.' · 24 cursos y 90 docentes ficticios</title>'
                    .'<link rel="stylesheet" href="/build/'.$manifest['resources/scss/app.scss']['file'].'">'
                    .'<link rel="stylesheet" href="/build/'.$manifest['resources/css/dotacion-establecimiento.css']['file'].'">'.$styles.'</head><body>'
                    .'<main class="container-fluid p-3"><p class="text-muted">Prueba local · 24 cursos y 90 docentes ficticios · Año '.$year.'</p><div class="dotacion-workspace">'.$content.'</div></main>'
                    .'<script>document.addEventListener("submit",event=>{event.preventDefault();event.stopImmediatePropagation();},true);</script>'
                    .'<script type="module" src="/build/'.$manifest['resources/js/app.js']['file'].'"></script>'
                    .'<script type="module" src="/build/'.$manifest['resources/js/dotacion-asignacion.js']['file'].'"></script>'.$scripts.'</body></html>';
                file_put_contents(storage_path('framework/testing/asignacion-'.$year.'-grande-595.html'), $preview);
            }
            return $content;
        });
        $render = (hrtime(true) - $renderStart) / 1e9;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $needCount = collect($data['asignacion']['necesidades'])->sum(fn ($items) => count($items));
        $this->assertSame($needCount, substr_count($html, 'data-dotacion-need='));
        $this->assertCount(90, $data['docentes']);
        $this->assertSame(24, $data['resumen']['cursos_total']);
        $this->assertSame($snapshot, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
        $this->assertSame(0, count($queries) - $calculationQueries);
        $this->assertLessThan(6000000, strlen($html), 'Los selectores no deben repetir el padrón en cada formulario cerrado.');
        $this->assertLessThan(1600, substr_count($html, '<option '));
        preg_match_all('/data-personal-catalogo="([^"]+)"/', $html, $references);
        $this->assertNotEmpty($references[1]);
        foreach (array_unique($references[1]) as $id) {
            $this->assertSame(1, substr_count($html, 'id="'.$id.'" data-dotacion-personal-catalogo'));
        }
        if (getenv('SGA_ASIGNACION_BENCHMARK') === '1') {
            fwrite(STDOUT, "\nASIGNACION_BENCHMARK ".json_encode([
                'year' => $year, 'calculation_seconds' => round($calculation, 4), 'render_seconds' => round($render, 4),
                'queries' => count($queries), 'render_queries' => count($queries) - $calculationQueries,
                'html_bytes' => strlen($html), 'options' => substr_count($html, '<option '),
                'forms' => substr_count($html, '<form '), 'peak_mb' => memory_get_peak_usage(true) / 1048576,
                'summary_hash' => hash('sha256', json_encode($data['resumen'])),
                'query_shapes' => collect($queries)->countBy('query')->sortDesc()->take(8)->all(),
            ], JSON_UNESCAPED_UNICODE)."\n");
        }
    }

    public function test_cache_de_asignaciones_aisla_instancias_anios_y_siguientes_consultas(): void
    {
        $ee = $this->dataset(8, 1);
        DotacionLecturaCache::ejecutar(function () use ($ee): void {
            $first = DotacionAsignacionCalculator::assignmentsFor($ee, 2026);
            $first->first()->horas_contrato = 999;
            $first->first()->setAttribute('motivo_huerfana', 'Prueba sintética');
            $second = DotacionAsignacionCalculator::assignmentsFor($ee, 2026);
            $this->assertNotSame($first->first(), $second->first());
            $this->assertNotEquals(999, $second->first()->horas_contrato);
            $this->assertNull($second->first()->getAttribute('motivo_huerfana'));
            $this->assertEmpty(DotacionAsignacionCalculator::assignmentsFor($ee, 2027));
        });
        $id = DB::table('dotacion_docente_asignaciones')->insertGetId([
            'establecimiento_id' => $ee->id, 'anio' => 2026, 'docente_rut' => '99000000-K',
            'docente_rut_normalizado' => '99000000K', 'tipo_asignacion' => 'otra_funcion', 'horas_contrato' => 5,
        ]);
        $this->assertContains($id, DotacionLecturaCache::ejecutar(fn () => DotacionAsignacionCalculator::assignmentsFor($ee, 2026))->pluck('id'));
        DB::table('dotacion_docente_asignaciones')->where('id', $id)->update(['horas_contrato' => 7]);
        $this->assertEquals(7, DotacionAsignacionCalculator::assignmentsFor($ee, 2026)->firstWhere('id', $id)->horas_contrato);
    }

    public function test_personalizadas_en_lote_conserva_prioridad_directa_respaldo_y_contexto(): void
    {
        $ee = $this->dataset(24, 1);
        $courses = EstablecimientoCurso::where('establecimiento_id', $ee->id)->orderBy('id')->get();
        $a = $courses[0]; $b = $courses[1];
        $configA = DB::table('establecimiento_planes_estudio')->insertGetId([
            'establecimiento_id' => $ee->id, 'establecimiento_curso_id' => $a->id,
            'curso_id' => $a->curso_id, 'plan_estudio_id' => $a->plan_estudio_id, 'anio' => 2026,
        ]);
        $historical = DB::table('establecimiento_planes_estudio')->insertGetId([
            'establecimiento_id' => $ee->id, 'establecimiento_curso_id' => 999999,
            'curso_id' => $a->curso_id, 'plan_estudio_id' => $a->plan_estudio_id, 'anio' => 2026,
        ]);
        $otherYear = DB::table('establecimiento_planes_estudio')->insertGetId([
            'establecimiento_id' => $ee->id, 'establecimiento_curso_id' => 999998,
            'curso_id' => $a->curso_id, 'plan_estudio_id' => $a->plan_estudio_id, 'anio' => 2025,
        ]);
        foreach ([$configA => 'Directa', $historical => 'Histórica', $otherYear => 'Otro año'] as $id => $name) {
            DB::table('establecimiento_planes_estudio_asignaturas')->insert([
                'establecimiento_plan_estudio_id' => $id, 'nombre_asignatura_personalizada' => $name,
                'horas_semanales' => 1, 'orden' => 1,
            ]);
        }
        $data = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($ee, 2026));
        $needs = $data['asignacion']['necesidades']['plan_estudio'];
        $aNames = $needs->where('establecimiento_curso_id', $a->id)->pluck('titulo');
        $bNames = $needs->where('establecimiento_curso_id', $b->id)->pluck('titulo');
        $this->assertContains('Directa', $aNames);
        $this->assertNotContains('Histórica', $aNames);
        $this->assertContains('Directa', $bNames);
        $this->assertContains('Histórica', $bNames);
        $this->assertNotContains('Otro año', $bNames);
        // Incluso un vínculo directo con contexto histórico mantiene la prioridad anterior.
        DB::table('establecimiento_planes_estudio')->where('id', $configA)->update(['anio' => 2025]);
        $next = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($ee, 2026));
        $this->assertContains('Directa', $next['asignacion']['necesidades']['plan_estudio']->where('establecimiento_curso_id', $a->id)->pluck('titulo'));
    }

    public function test_asignaturas_conserva_consolidado_y_cada_necesidad_sus_asignaciones(): void
    {
        $ee = $this->dataset(8, 3);
        $user = $this->testUser(1, 3);
        $request = Request::create('/admin/dotacion-establecimiento/'.$ee->id, 'GET', ['anio' => 2026, 'tab' => 'asignaturas']);
        $request->setUserResolver(fn () => $user);
        $data = app(DotacionEstablecimientoController::class)->show($request, $ee)->getData();
        $this->assertNotEmpty($data['asignaturas']['items']);
        $assignments = DotacionAsignacionCalculator::assignmentsFor($ee, 2026);
        foreach ($data['asignacion']['necesidades']['plan_estudio'] as $need) {
            $expected = $assignments->where('necesidad_key', $need['key'])->pluck('id')->values()->all();
            $this->assertSame($expected, $need['asignaciones']->pluck('id')->all());
        }
    }

    public function test_cursos_sin_plan_no_consultan_personalizadas_y_directivo_no_accede_a_otro_establecimiento(): void
    {
        $ee = $this->dataset(8, 1);
        EstablecimientoCurso::where('establecimiento_id', $ee->id)->update(['plan_estudio_id' => null, 'anio' => 2025]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $data = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($ee, 2025));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertEmpty($data['asignacion']['necesidades']['plan_estudio']);
        $this->assertFalse(collect($queries)->contains(fn ($query) => str_contains($query['query'], 'from "establecimiento_planes_estudio_asignaturas"')));
        $request = Request::create('/admin/dotacion-establecimiento/'.$ee->id);
        $request->setUserResolver(fn () => new class {
            public int $establecimiento_id = 999999;
            public function activeRoleName(): string { return 'funcionario_directivo_estab'; }
        });
        try {
            app(DotacionEstablecimientoController::class)->show($request, $ee);
            $this->fail('Un directivo no debe consultar otro establecimiento.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function dataset(int $courseCount = 24, int $teacherCount = 90, int $year = 2026): Establecimiento
    {
        $ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'Escuela sintética']);
        $catalog = Curso::whereIn('codigo', ['1B', '2B', '3B', '4B', '5B', '6B', '7B', '8B'])->orderBy('id')->get();
        $courses = [];
        foreach ($catalog as $level) {
            $plan = PlanEstudio::create([
                'curso_id' => $level->id, 'anio' => $year, 'nombre_plan' => 'Plan sintético',
                'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 38, 'activo' => true,
            ]);
            for ($subject = 0; $subject < 12; $subject++) {
                DB::table('planes_estudio_asignaturas')->insert([
                    'plan_estudio_id' => $plan->id, 'asignatura' => 'Asignatura sintética '.$subject,
                    'horas_semanales' => 3, 'tipo_bloque' => 'tiempo_minimo', 'orden' => $subject + 1,
                ]);
            }
            for ($section = 0; $section < (int) ceil($courseCount / 8); $section++) {
                if (count($courses) >= $courseCount) break;
                $course = EstablecimientoCurso::create([
                    'establecimiento_id' => $ee->id, 'rbd' => $ee->rbd, 'curso_id' => $level->id,
                    'plan_estudio_id' => $plan->id, 'anio' => $year, 'letra' => chr(65 + $section),
                    'nombre_seccion' => $level->nombre.' '.chr(65 + $section),
                    'matricula' => 30, 'regimen_jec' => 'Con JEC', 'activo' => true,
                ]);
                $courses[] = $course;
            }
        }
        for ($i = 0; $i < $teacherCount; $i++) {
            $rut = (99000000 + $i).'-K';
            DB::table('reemplazos_personal')->insert([
                'establecimiento_id' => $ee->id, 'rbd' => $ee->rbd, 'rut' => $rut,
                'nombre' => 'Docente sintético '.$i, 'anio' => 2026, 'mes' => 10,
                'jornada' => 44, 'jornada_basica' => 44, 'tipocontrato' => 'PLANTA',
                'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-'.$i,
            ]);
            for ($j = 0; $j < 4; $j++) {
                $course = $courses[($i * 4 + $j) % count($courses)];
                $subject = 'Asignatura sintética '.(($i + $j) % 12);
                DB::table('dotacion_docente_asignaciones')->insert([
                    'establecimiento_id' => $ee->id, 'anio' => $year, 'docente_rut' => $rut,
                    'docente_rut_normalizado' => str_replace('-', '', $rut), 'docente_nombre' => 'Docente sintético '.$i,
                    'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'tiempo_minimo',
                    'establecimiento_curso_id' => $course->id, 'plan_estudio_id' => $course->plan_estudio_id,
                    'necesidad_key' => 'plan:'.md5($course->id.'|tiempo_minimo|'.$subject),
                    'asignatura_nombre' => $subject, 'horas_plan_pedagogicas' => 3,
                    'horas_contrato' => 4, 'proporcion_aplicada' => '65/35',
                ]);
            }
        }
        return $ee;
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
