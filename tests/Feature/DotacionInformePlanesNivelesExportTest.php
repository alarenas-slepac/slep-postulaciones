<?php

namespace Tests\Feature;

use App\Exports\DotacionInformePlanesNivelesExport;
use App\Models\Curso;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionInformeFunciones;
use App\Support\DotacionInformePlanes;
use App\Support\DotacionInformePlanesNiveles;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionInformePlanesNivelesExportTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp(); // Antes del bootstrap: SQLite en memoria, config sin caché y discos simulados.
        $this->resetCalculators();
        $this->withoutVite();
        Storage::disk('local')->put('niveles-views/layouts/app.blade.php', '<html><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('niveles-views'));
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        Schema::create('modules', function (Blueprint $t) {
            $t->id(); $t->string('key'); $t->string('name')->nullable(); $t->string('section')->nullable();
            $t->string('icon')->nullable(); $t->integer('sort')->default(0); $t->timestamps();
        });
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        $this->ee = $this->establecimiento(99999);
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    private function resetCalculators(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) { (new ReflectionProperty($class, $name))->setValue(null, []); }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }

    public static function roles(): array
    {
        return [['admin', true], ['coordinador_uatp', true], ['coordinador_gdp', true], ['supervisor_plani', true],
            ['funcionario_directivo_estab', false], ['postulante', false], ['funcionario_ac', false], ['coordinador_plani', false]];
    }

    #[DataProvider('roles')]
    public function test_descarga_y_boton_respetan_rol_activo_aunque_la_cuenta_tenga_admin(string $role, bool $allowed): void
    {
        $user = auth()->user();
        if ($role !== 'admin') {
            $id = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $id, 'model_type' => $user::class, 'model_id' => $user->id]);
            $user->unsetRelation('roles');
        }
        $user->forceFill(['establecimiento_id' => $this->ee->id])->save();
        $this->withSession(['active_role' => $role]);
        $service = $this->mock(DotacionInformePlanesNiveles::class);
        if ($allowed) {
            $service->shouldReceive('fila')->once()->andReturn($this->filasMock());
            $this->get($this->url())->assertOk()->assertDownload();
        } else {
            $service->shouldNotReceive('fila');
            $this->get($this->url())->assertForbidden();
        }
        $html = view('admin.dotacion-establecimiento.index', ['establecimientos' => new LengthAwarePaginator([], 0, 10),
            'activeRole' => $role, 'anio' => 2027, 'q' => '', 'comuna' => '', 'comunas' => collect()])->render();
        $this->assertSame($allowed, str_contains($html, 'Excel por niveles'));
    }

    public static function clasificaciones(): array
    {
        return [
            ['NT1', 'Educación Parvularia', '', 'parvularia'], ['NT2', '', '', 'parvularia'],
            ['1B', 'Educación Básica', '', 'basica'], ['8B', '', '', 'basica'],
            ['1M', 'Educación Media', 'Común', 'media'], ['2M', 'Educación Media', 'Común', 'media'],
            ['3M-HC', 'Educación Media', 'Humanístico-Científica', 'media_hc'],
            ['4M-HC', 'Educación Media', '', 'media_hc'], ['3M-TP', 'Educación Media', 'Técnico-Profesional', 'media_tp'],
            ['4M-TP', 'Educación Media', '', 'media_tp'], ['EPJA-1B', 'Educación Básica Adultos', '', 'epja'],
            ['EPJA-3M-HC', 'Educación Media Adultos', 'Humanístico-Científica', 'epja'],
            ['LABORAL', 'Educación Especial', '', 'especial'], ['ESP-3M-HC', 'Educación Especial', 'HC', 'especial'],
            ['3M-ART', 'Educación Media', 'Artística', null], ['SIN-CATALOGO', '', '', null],
        ];
    }

    #[DataProvider('clasificaciones')]
    public function test_clasificacion_del_catalogo_prioriza_epja_y_especial(string $codigo, string $nivel, string $modalidad, ?string $expected): void
    {
        $course = (new EstablecimientoCurso)->setRelation('curso', (new Curso)->forceFill([
            'codigo' => $codigo, 'nombre' => $codigo, 'nivel_educativo' => $nivel, 'modalidad' => $modalidad,
        ]));
        $this->assertSame($expected, app(DotacionInformePlanesNiveles::class)->clasificar($course));
    }

    public static function years(): array { return [[2026], [2027]]; }

    #[DataProvider('years')]
    public function test_exporta_ocho_hojas_todos_los_filtrados_una_vez_y_con_cache_por_establecimiento(int $year): void
    {
        for ($i = 1; $i < 15; $i++) { $this->establecimiento(99999 - $i); }
        $this->establecimiento(99980, ['sala_cuna' => true]);
        $this->establecimiento(99979, ['comuna' => 'Otra']);
        $before = DB::table('establecimientos')->orderBy('id')->get()->toJson();
        $this->mock(DotacionInformeFunciones::class)->shouldNotReceive('fila');
        $loads = 0;
        $this->mock(DotacionInformePlanesNiveles::class)->shouldReceive('fila')->times(15)
            ->andReturnUsing(function ($ee, $anio) use ($year, &$loads) {
                $this->assertSame($year, $anio);
                DotacionLecturaCache::recordar('prueba-niveles', function () use (&$loads) { return ++$loads; });
                return $this->filasMock((string) $ee->rbd);
            });
        $response = $this->get($this->url(['anio' => $year, 'q' => 'Escuela', 'comuna' => 'Prueba', 'page' => 2]))
            ->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('dotacion_planes_por_niveles_'.$year, $response->headers->get('Content-Disposition'));
        Storage::disk('local')->put('niveles.xlsx', $response->streamedContent());
        $book = IOFactory::load(Storage::disk('local')->path('niveles.xlsx'));
        try {
            $this->assertSame(['Global', 'Parvularia', 'Básica', 'Media', 'Media HC', 'Media TP', 'EPJA', 'Especial'], $book->getSheetNames());
            foreach (['Global' => 100, 'Básica' => 40, 'Media' => 60] as $title => $hours) {
                $sheet = $book->getSheetByName($title);
                $this->assertSame('A6:K21', $sheet->getAutoFilter()->getRange());
                $this->assertEquals($hours * 15, $sheet->getCell('G22')->getCalculatedValue());
                $this->assertStringContainsString('Establecimientos: 15', $sheet->getCell('A2')->getValue());
                $this->assertSame('C7', $sheet->getFreezePane());
                $this->assertSame('s', $sheet->getCell('A7')->getDataType());
                $this->assertSame('s', $sheet->getCell('B7')->getDataType());
            }
            $this->assertStringContainsString('Sin establecimientos', $book->getSheetByName('EPJA')->getCell('A7')->getValue());
            $this->assertEquals(0, $book->getSheetByName('EPJA')->getCell('G8')->getCalculatedValue());
            $this->assertSame('=Escuela sintética', $book->getActiveSheet()->getCell('B7')->getValue());
        } finally { $book->disconnectWorksheets(); }
        $this->assertSame(15, $loads);
        $this->assertSame($before, DB::table('establecimientos')->orderBy('id')->get()->toJson());
    }

    public function test_sin_resultados_conserva_las_ocho_hojas_y_totales_cero(): void
    {
        $book = app(DotacionInformePlanesNivelesExport::class)->workbook([], 2027, ['q' => '=texto']);
        try {
            $this->assertCount(8, $book->getAllSheets());
            foreach ($book->getAllSheets() as $sheet) {
                $this->assertEquals(0, $sheet->getCell('J8')->getCalculatedValue());
                $this->assertSame('s', $sheet->getCell('A2')->getDataType());
                $this->assertSame('0B3D91', $sheet->getStyle('A6')->getFill()->getStartColor()->getRGB());
            }
        } finally { $book->disconnectWorksheets(); }
    }

    public function test_botones_conservan_filtros_y_la_pagina_no_limita_el_excel(): void
    {
        $response = $this->get(route('admin.dotacion-establecimiento.index', [
            'informe_funciones' => 1, 'anio' => 2027, 'q' => 'Escuela', 'comuna' => 'Prueba', 'page' => 2,
        ]))->assertOk()->assertSee('Excel por niveles');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $link = (new \DOMXPath($dom))->query('//a[contains(normalize-space(.),"Excel por niveles")]')->item(0);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['informe_funciones' => '1', 'export_planes_niveles' => '1', 'anio' => '2027', 'q' => 'Escuela', 'comuna' => 'Prueba'], $query);
    }

    public function test_fallo_tras_una_fila_cancela_archivo_y_valida_filtros_antes_de_calcular(): void
    {
        $this->establecimiento(99998);
        $calls = 0;
        $this->mock(DotacionInformePlanesNiveles::class)->shouldReceive('fila')->twice()->andReturnUsing(function () use (&$calls) {
            if (++$calls === 1) { return $this->filasMock(); }
            throw new \RuntimeException('Fallo sintético');
        });
        $this->from('/informe')->get($this->url())->assertRedirect('/informe')->assertSessionHasErrors('export_planes_niveles');
        $this->assertStringContainsString('99999', session('errors')->first('export_planes_niveles'));
        foreach ([['anio' => 2028], ['export_planes_niveles' => ['inválido']], ['q' => str_repeat('x', 151)]] as $data) {
            $this->get($this->url($data))->assertRedirect()->assertSessionHasErrors();
        }
    }

    #[DataProvider('years')]
    public function test_fuente_real_separa_siete_niveles_dentro_del_mismo_establecimiento(int $year): void
    {
        $courses = $this->cursosReales($year);
        $service = app(DotacionInformePlanesNiveles::class);
        $rows = DotacionLecturaCache::ejecutar(fn () => $service->fila($this->ee, $year));
        $this->assertSame(array_keys(DotacionInformePlanesNiveles::NIVELES), array_keys($rows['niveles']));
        foreach (['parvularia' => 2, 'basica' => 1, 'media' => 2, 'media_hc' => 2, 'media_tp' => 1, 'epja' => 2, 'especial' => 1] as $key => $count) {
            $this->assertSame($count, $rows['niveles'][$key]['cursos'], $key);
        }
        $this->assertSame(76.0, $rows['niveles']['media']['horas_plan']);
        $this->assertSame(76.0, $rows['niveles']['media_hc']['horas_plan']);
        $this->assertSame('Sin JEC', $rows['niveles']['media_tp']['modalidad_jec']);
        $this->assertSame('Con JEC / Sin JEC', $rows['global']['modalidad_jec']);
        $source = DotacionLecturaCache::ejecutar(fn () => app(DotacionInformePlanes::class)->fila($this->ee, $year));
        foreach (['matricula', 'cursos', 'horas_plan', 'contrato_equivalente', 'trabajo_colaborativo_pie', 'contrato_mas_pie'] as $key) {
            $this->assertEquals($source[$key], $rows['global'][$key]);
            $this->assertEquals($source[$key], collect($rows['niveles'])->sum($key));
        }
        // Los cursos del año anterior, sin matrícula e inactivos no agregan hojas ni horas.
        $this->assertCount(11, $courses);
    }

    public function test_combinado_nt_y_refuerzo_real_se_incluyen_una_vez_en_parvularia(): void
    {
        $courses = $this->cursosReales(2027);
        foreach (['NT1', 'NT2'] as $code) {
            DB::table('dotacion_docente_asignaciones')->insert(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
                'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K', 'tipo_asignacion' => 'plan_estudio',
                'subtipo_asignacion' => 'libre_disposicion', 'establecimiento_curso_id' => $courses[$code]->id, 'horas_plan_pedagogicas' => 4]);
        }
        $this->combinar([$courses['NT1'], $courses['NT2']]);
        $rows = DotacionLecturaCache::ejecutar(fn () => app(DotacionInformePlanesNiveles::class)->fila($this->ee, 2027));
        $this->assertSame(42.0, $rows['niveles']['parvularia']['horas_plan']);
        $this->assertSame(3.0, $rows['niveles']['parvularia']['trabajo_colaborativo_pie']);
        $this->assertSame(2, $rows['niveles']['parvularia']['cursos']);
        $this->assertArrayNotHasKey('sin_desglose', $rows['niveles']);
        $this->assertEquals($rows['global']['contrato_mas_pie'], collect($rows['niveles'])->sum('contrato_mas_pie'));
        $this->assertSame(2, DB::table('dotacion_docente_asignaciones')->count());
    }

    public function test_grupo_entre_niveles_y_curso_sin_clasificar_no_duplican_ni_inventan_reparto(): void
    {
        $courses = $this->cursosReales(2027);
        $this->combinar([$courses['1B'], $courses['1M']]);
        $this->curso('3M-ART', 2027, 'Educación Media', 'Artística');
        $rows = DotacionLecturaCache::ejecutar(fn () => app(DotacionInformePlanesNiveles::class)->fila($this->ee, 2027));
        $this->assertSame(1, $rows['niveles']['basica']['cursos']);
        $this->assertSame(0.0, $rows['niveles']['basica']['horas_plan']);
        $this->assertSame(38.0, $rows['niveles']['media']['horas_plan']); // Sólo 2.º medio independiente.
        $this->assertSame(76.0, $rows['niveles']['sin_desglose']['horas_plan']); // Grupo + Artística.
        $this->assertSame(1, $rows['niveles']['sin_desglose']['cursos']); // Sólo Artística, sin repetir integrantes.
        foreach (['matricula', 'cursos', 'horas_plan', 'contrato_equivalente', 'trabajo_colaborativo_pie', 'contrato_mas_pie'] as $key) {
            $this->assertEquals($rows['global'][$key], collect($rows['niveles'])->sum($key));
        }
        $book = app(DotacionInformePlanesNivelesExport::class)->workbook([$rows], 2027);
        try {
            $this->assertCount(9, $book->getAllSheets());
            $this->assertNotNull($book->getSheetByName('Sin desglose'));
            $this->assertStringContainsString('Sin desglose', $book->getSheetByName('Básica')->getCell('K7')->getValue());
            $this->assertEquals(76, $book->getSheetByName('Sin desglose')->getCell('G8')->getCalculatedValue());
        } finally { $book->disconnectWorksheets(); }
    }

    private function cursosReales(int $year): array
    {
        foreach (['2026_01_29_000001_create_reemplazos_personal_table.php', '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php', '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php', '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php', '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $courses = [];
        foreach (['NT1', 'NT2', '1B', '1M', '2M', '3M-HC', '4M-HC', '3M-TP'] as $code) {
            $courses[$code] = $this->curso($code, $year);
        }
        $courses['epja_b'] = $this->curso('EPJA-1B', $year, 'Educación Básica Adultos', 'EPJA');
        $courses['epja_m'] = $this->curso('EPJA-3M-HC', $year, 'Educación Media Adultos', 'Humanístico-Científica');
        $courses['especial'] = $this->curso('ESP-3M-HC', $year, 'Educación Especial', 'HC');
        foreach (['NT1', 'NT2', '1B'] as $code) {
            DB::table('establecimiento_curso_pie')->insert(['establecimiento_id' => $this->ee->id,
                'establecimiento_curso_id' => $courses[$code]->id, 'anio' => $year, 'total_pie' => 1]);
        }
        foreach ([['B', $year - 1, true, 20], ['C', $year, false, 20], ['D', $year, true, 0]] as [$letra, $anio, $activo, $matricula]) {
            $courses['1B']->replicate()->forceFill(compact('letra', 'anio', 'activo', 'matricula'))->save();
        }
        return $courses;
    }

    private function curso(string $code, int $year, string $nivel = '', string $modalidad = ''): EstablecimientoCurso
    {
        $catalogo = Curso::where('codigo', $code)->first() ?? Curso::create(['codigo' => $code, 'nombre' => $code,
            'nivel_educativo' => $nivel, 'modalidad' => $modalidad, 'activo' => true]);
        $jec = $code === '3M-TP' ? 'Sin JEC' : 'Con JEC';
        $plan = PlanEstudio::create(['curso_id' => $catalogo->id, 'anio' => $year, 'nombre_plan' => 'Plan sintético '.$code,
            'regimen_jec' => $jec, 'horas_semanales_total' => 38, 'activo' => true]);
        $plan->asignaturas()->create(['asignatura' => 'Lenguaje', 'horas_semanales' => 38]);
        return EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'curso_id' => $catalogo->id,
            'plan_estudio_id' => $plan->id, 'anio' => $year, 'nombre_seccion' => $code.' A', 'letra' => 'A',
            'regimen_jec' => $jec, 'matricula' => 20, 'activo' => true]);
    }

    private function combinar(array $courses): void
    {
        $id = DB::table('dotacion_cursos_combinados')->insertGetId(['establecimiento_id' => $this->ee->id,
            'anio' => 2027, 'nombre' => 'Grupo sintético', 'proporcion' => 'auto', 'activo' => true]);
        foreach ($courses as $course) {
            DB::table('dotacion_curso_combinado_miembros')->insert(['dotacion_curso_combinado_id' => $id, 'establecimiento_curso_id' => $course->id]);
        }
    }

    private function filasMock(string $rbd = '99999'): array
    {
        $global = ['rbd' => $rbd, 'nombre' => '=Escuela sintética', 'comuna' => 'Prueba', 'modalidad_jec' => 'Con JEC / Sin JEC',
            'matricula' => 40, 'cursos' => 2, 'horas_plan' => 100.0, 'contrato_equivalente' => 120.0,
            'trabajo_colaborativo_pie' => 6.0, 'contrato_mas_pie' => 126.0, 'observaciones' => ''];
        return ['global' => $global, 'niveles' => [
            'basica' => array_replace($global, ['matricula' => 20, 'cursos' => 1, 'horas_plan' => 40.0, 'contrato_equivalente' => 48.0, 'trabajo_colaborativo_pie' => 3.0, 'contrato_mas_pie' => 51.0]),
            'media' => array_replace($global, ['matricula' => 20, 'cursos' => 1, 'horas_plan' => 60.0, 'contrato_equivalente' => 72.0, 'trabajo_colaborativo_pie' => 3.0, 'contrato_mas_pie' => 75.0]),
        ]];
    }

    private function establecimiento(int $rbd, array $extra = []): Establecimiento
    {
        return Establecimiento::create($extra + ['cod_estab' => $rbd, 'rbd' => $rbd, 'nombre_establecimiento' => 'Escuela sintética '.$rbd, 'comuna' => 'Prueba']);
    }

    private function url(array $extra = []): string
    {
        return route('admin.dotacion-establecimiento.index', $extra + ['informe_funciones' => 1, 'anio' => 2027, 'export_planes_niveles' => 1]);
    }
}
