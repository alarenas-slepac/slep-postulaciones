<?php

namespace Tests\Feature;

use App\Exports\DotacionInformePlanesExport;
use App\Models\Curso;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionCursosPlanesResumenCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionInformeFunciones;
use App\Support\DotacionInformePlanes;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionInformePlanesExportTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp(); // Confirma SQLite :memory:, caché aislada y discos simulados antes de usar datos.
        $this->resetCalculators();
        $this->withoutVite();
        $disk = Storage::disk('local');
        $disk->put('planes-views/layouts/app.blade.php', '<html><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation($disk->path('planes-views'));
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
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) {
                (new ReflectionProperty($class, $name))->setValue(null, []);
            }
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
    public function test_permiso_del_rol_activo_en_descarga_y_boton(string $role, bool $allowed): void
    {
        $user = auth()->user();
        if ($role !== 'admin') {
            $id = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $id, 'model_type' => $user::class, 'model_id' => $user->id]);
            $user->unsetRelation('roles');
        }
        $user->forceFill(['establecimiento_id' => $this->ee->id])->save();
        $this->withSession(['active_role' => $role]);
        $service = $this->mock(DotacionInformePlanes::class);
        if ($allowed) {
            $service->shouldReceive('fila')->once()->andReturn($this->fila());
            $this->get($this->url())->assertOk()->assertDownload()
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        } else {
            $service->shouldNotReceive('fila');
            $this->get($this->url())->assertForbidden();
        }
        $html = view('admin.dotacion-establecimiento.index', [
            'establecimientos' => new LengthAwarePaginator([], 0, 10), 'activeRole' => $role,
            'anio' => 2027, 'q' => 'Escuela', 'comuna' => 'Prueba', 'comunas' => collect(),
        ])->render();
        $this->assertSame($allowed, str_contains($html, 'Excel planes de estudio'));
    }

    public static function years(): array { return [[2026], [2027]]; }

    #[DataProvider('years')]
    public function test_descarga_todos_los_filtrados_del_anio_sin_paginar_ni_cargar_informe_docente(int $year): void
    {
        for ($i = 1; $i < 15; $i++) { $this->establecimiento(99999 - $i); }
        $this->establecimiento(99980, ['sala_cuna' => true]);
        $this->establecimiento(99979, ['comuna' => 'Otra']);
        $before = DB::table('establecimientos')->orderBy('id')->get()->toJson();
        $loads = 0;
        $this->mock(DotacionInformeFunciones::class)->shouldNotReceive('fila');
        $this->mock(DotacionInformePlanes::class)->shouldReceive('fila')->times(15)
            ->andReturnUsing(function ($ee, $anio) use ($year, &$loads) {
                $this->assertSame($year, $anio);
                DotacionLecturaCache::recordar('prueba-planes', function () use (&$loads) { return ++$loads; });
                return array_replace($this->fila(), ['rbd' => (string) $ee->rbd, 'nombre' => $ee->nombre_establecimiento]);
            });
        $response = $this->get($this->url(['anio' => $year, 'q' => 'Escuela', 'comuna' => 'Prueba', 'page' => 2]))->assertOk();
        $this->assertStringContainsString('dotacion_planes_consolidado_'.$year, $response->headers->get('Content-Disposition'));
        Storage::disk('local')->put('planes.xlsx', $response->streamedContent());
        $book = IOFactory::load(Storage::disk('local')->path('planes.xlsx'));
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame('A6:K21', $sheet->getAutoFilter()->getRange());
            $this->assertEquals(9000, $sheet->getCell('G22')->getCalculatedValue());
            $this->assertEquals(11475, $sheet->getCell('J22')->getCalculatedValue());
            $this->assertStringContainsString('Establecimientos: 15', $sheet->getCell('A2')->getValue());
            $this->assertSame('Con JEC / Sin JEC', $sheet->getCell('D7')->getValue());
        } finally { $book->disconnectWorksheets(); }
        $this->assertSame(15, $loads);
        $this->assertSame($before, DB::table('establecimientos')->orderBy('id')->get()->toJson());
    }

    public function test_excel_conserva_tipos_totales_y_textos_sin_interpretarlos_como_formulas(): void
    {
        $fila = array_replace($this->fila(), ['rbd' => '009999', 'nombre' => '=Escuela', 'comuna' => '+Comuna', 'observaciones' => '=aviso']);
        foreach ([[], [$fila, $fila]] as $rows) {
            $book = app(DotacionInformePlanesExport::class)->workbook($rows, 2027, ['q' => '=BUSCAR']);
            $path = Storage::disk('local')->path('formato.xlsx');
            try {
                (new Xlsx($book))->save($path);
                $loaded = IOFactory::load($path);
                try {
                    $sheet = $loaded->getActiveSheet();
                    $this->assertSame('C7', $sheet->getFreezePane());
                    $this->assertSame('0B3D91', $sheet->getStyle('A6')->getFill()->getStartColor()->getRGB());
                    $this->assertSame('s', $sheet->getCell('A2')->getDataType());
                    $this->assertStringContainsString('Búsqueda: =BUSCAR', $sheet->getCell('A2')->getValue());
                    $total = $rows ? 9 : 8;
                    foreach (['E' => 398, 'F' => 16, 'G' => 600, 'H' => 717, 'I' => 48, 'J' => 765] as $col => $value) {
                        $this->assertEquals($value * count($rows), $sheet->getCell($col.$total)->getCalculatedValue());
                    }
                    if ($rows) {
                        $this->assertSame('009999', $sheet->getCell('A7')->getValue());
                        foreach (['B', 'C', 'K'] as $col) { $this->assertSame('s', $sheet->getCell($col.'7')->getDataType()); }
                        $this->assertSame('n', $sheet->getCell('G7')->getDataType());
                        $this->assertSame('#,##0.00', $sheet->getStyle('G7')->getNumberFormat()->getFormatCode());
                    } else { $this->assertStringContainsString('Sin establecimientos', $sheet->getCell('A7')->getValue()); }
                } finally { $loaded->disconnectWorksheets(); }
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_boton_del_informe_conserva_filtros_y_omite_pagina(): void
    {
        $response = $this->get(route('admin.dotacion-establecimiento.index', [
            'informe_funciones' => 1, 'anio' => 2027, 'q' => 'Escuela', 'comuna' => 'Prueba', 'page' => 2,
        ]))->assertOk()->assertSee('Excel planes de estudio');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $link = (new \DOMXPath($dom))->query('//a[contains(normalize-space(.),"Excel planes de estudio")]')->item(0);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['informe_funciones' => '1', 'export_planes' => '1', 'anio' => '2027', 'q' => 'Escuela', 'comuna' => 'Prueba'], $query);
    }

    public function test_fallo_cancela_descarga_y_filtros_invalidos_no_calculan(): void
    {
        $service = $this->mock(DotacionInformePlanes::class);
        $service->shouldReceive('fila')->once()->andThrow(new \RuntimeException('Fallo sintético'));
        $this->from('/informe')->get($this->url())->assertRedirect('/informe')->assertSessionHasErrors('export_planes');
        $this->assertStringContainsString('99999', session('errors')->first('export_planes'));
        foreach ([['anio' => 2028], ['export_planes' => ['inválido']], ['q' => str_repeat('x', 151)]] as $overrides) {
            $this->get($this->url($overrides))->assertRedirect()->assertSessionHasErrors();
        }
    }

    #[DataProvider('years')]
    public function test_fuente_real_respeta_cursos_anio_matricula_y_combinacion_activa(int $year): void
    {
        $this->tablasPlanes();
        $curso = Curso::where('codigo', '1B')->firstOrFail();
        $plan = PlanEstudio::create(['curso_id' => $curso->id, 'anio' => $year, 'nombre_plan' => 'Plan sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 38, 'activo' => true]);
        $plan->asignaturas()->create(['asignatura' => 'Lenguaje', 'horas_semanales' => 38]);
        $first = EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'curso_id' => $curso->id,
            'plan_estudio_id' => $plan->id, 'anio' => $year, 'nombre_seccion' => '1B A', 'letra' => 'A',
            'regimen_jec' => 'Con JEC', 'matricula' => 20, 'activo' => true]);
        foreach ([['B', $year, 10, true, 'Sin JEC'], ['C', $year - 1, 50, true, 'Sin JEC'],
            ['D', $year, 0, true, 'Sin JEC'], ['E', $year, 50, false, 'Sin JEC']] as [$letra, $anio, $matricula, $activo, $jec]) {
            $copy = $first->replicate()->forceFill(['letra' => $letra, 'anio' => $anio, 'matricula' => $matricula, 'activo' => $activo, 'regimen_jec' => $jec]);
            $copy->save();
        }
        $service = app(DotacionInformePlanes::class);
        $before = DotacionLecturaCache::ejecutar(fn () => $service->fila($this->ee, $year));
        $this->assertSame(30, $before['matricula']);
        $this->assertSame(2, $before['cursos']);
        $this->assertSame(76.0, $before['horas_plan']);
        $this->assertSame('Con JEC / Sin JEC', $before['modalidad_jec']);
        $group = DB::table('dotacion_cursos_combinados')->insertGetId(['establecimiento_id' => $this->ee->id,
            'anio' => $year, 'nombre' => 'Grupo sintético', 'proporcion' => 'auto', 'activo' => true]);
        foreach (EstablecimientoCurso::where('anio', $year)->whereIn('letra', ['A', 'B'])->pluck('id') as $id) {
            DB::table('dotacion_curso_combinado_miembros')->insert(['dotacion_curso_combinado_id' => $group, 'establecimiento_curso_id' => $id]);
        }
        $after = DotacionLecturaCache::ejecutar(fn () => $service->fila($this->ee, $year));
        $this->assertSame(38.0, $after['horas_plan']);
        $this->assertSame(30, $after['matricula']);
        $this->assertSame(2, $after['cursos']);
        $this->assertSame('Con JEC / Sin JEC', $after['modalidad_jec']);
        $source = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($this->ee, $year, false, false));
        $this->assertEquals(data_get($source, 'cursos.resumen_cursos_planes.totales.contrato_mas_trabajo_colaborativo_pie'), $after['contrato_mas_pie']);
        $this->assertSame(5, EstablecimientoCurso::count());
    }

    public function test_total_de_referencia_incluye_refuerzo_una_vez_y_detecta_modalidades_faltantes(): void
    {
        $rows = [];
        foreach ([['NT1', 14, 38, 55, 3, 'Con JEC'], ['NT2', 13, 38, 55, 3, 'Con JEC'],
            ['basica', 371, 516, 598, 42, 'Sin JEC']] as [$key, $matricula, $horas, $contrato, $pie, $jec]) {
            $rows[$key] = ['label' => $key, 'detalles' => [['establecimiento_curso_id' => count($rows) + 1,
                'regimen_jec' => $jec, 'matricula' => $matricula, 'horas' => $horas,
                'horas_contrato_equivalente_redondeado' => $contrato, 'trabajo_colaborativo_pie' => $pie]]];
        }
        $rows['NT1']['detalles'][0] += ['horas_plan_refuerzo_ld_otro_docente' => 8, 'horas_contrato_refuerzo_ld_otro_docente' => 9];
        $rows['NT1']['detalles'][0]['horas'] += 8;
        $rows['NT1']['detalles'][0]['horas_contrato_equivalente_redondeado'] += 9;
        $cursos = ['rows' => $rows, 'grupos' => ['todos' => ['niveles' => array_keys($rows)]]];
        $cursos['resumen_cursos_planes'] = DotacionCursosPlanesResumenCalculator::build($cursos, []);
        $result = app(DotacionInformePlanes::class)->resumir($cursos);
        $this->assertSame(600.0, $result['horas_plan']);
        $this->assertSame(717.0, $result['contrato_equivalente']);
        $this->assertSame(48.0, $result['trabajo_colaborativo_pie']);
        $this->assertSame(765.0, $result['contrato_mas_pie']);
        $cursos['rows']['NT1']['detalles'][0]['regimen_jec'] = '';
        $result = app(DotacionInformePlanes::class)->resumir($cursos);
        $this->assertStringContainsString('Sin información', $result['modalidad_jec']);
        $this->assertStringContainsString('sin régimen', $result['observaciones']);
        $cursos['rows']['NT1']['detalles'][0]['regimen_jec'] = 'No aplica';
        $this->assertStringContainsString('No aplica', app(DotacionInformePlanes::class)->resumir($cursos)['modalidad_jec']);
    }

    private function tablasPlanes(): void
    {
        foreach (['2026_01_29_000001_create_reemplazos_personal_table.php', '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php', '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php', '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php', '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    private function fila(): array
    {
        return ['rbd' => '99999', 'nombre' => 'Escuela sintética', 'comuna' => 'Prueba', 'modalidad_jec' => 'Con JEC / Sin JEC',
            'matricula' => 398, 'cursos' => 16, 'horas_plan' => 600.0, 'contrato_equivalente' => 717.0,
            'trabajo_colaborativo_pie' => 48.0, 'contrato_mas_pie' => 765.0, 'observaciones' => ''];
    }

    private function establecimiento(int $rbd, array $extra = []): Establecimiento
    {
        return Establecimiento::create($extra + ['cod_estab' => $rbd, 'rbd' => $rbd, 'nombre_establecimiento' => 'Escuela sintética '.$rbd, 'comuna' => 'Prueba']);
    }

    private function url(array $extra = []): string
    {
        return route('admin.dotacion-establecimiento.index', $extra + ['informe_funciones' => 1, 'anio' => 2027, 'export_planes' => 1]);
    }
}
