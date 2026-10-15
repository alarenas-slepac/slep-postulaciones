<?php

namespace Tests\Feature;

use App\Exports\DotacionInformeFuncionesExport;
use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionInformeFunciones;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionInformeFuncionesExportTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // Aislamiento antes del bootstrap; SQLite :memory: y discos simulados.
        $this->resetCalculators();
        $this->withoutVite();
        $disk = Storage::disk('local');
        $disk->put('excel-views/layouts/app.blade.php', '<html><head>@stack(\'styles\')</head><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation($disk->path('excel-views'));
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        Schema::create('modules', fn (Blueprint $t) => [$t->id(), $t->string('key')]);
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        $this->establecimiento(99999);
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
    }

    public static function roles(): array
    {
        return [['admin', true], ['coordinador_uatp', true], ['coordinador_gdp', true], ['supervisor_plani', true],
            ['funcionario_directivo_estab', false], ['postulante', false], ['funcionario_ac', false], ['coordinador_plani', false]];
    }

    #[DataProvider('roles')]
    public function test_descarga_controla_el_rol_activo_incluso_en_cuenta_con_admin(string $rol, bool $permitido): void
    {
        $user = auth()->user();
        if ($rol !== 'admin') {
            $id = DB::table('roles')->where('name', $rol)->value('id') ?? DB::table('roles')->insertGetId(['name' => $rol, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $id, 'model_type' => $user::class, 'model_id' => $user->id]);
            $user->unsetRelation('roles');
        }
        $user->forceFill(['establecimiento_id' => 1])->save();
        $this->withSession(['active_role' => $rol]);
        $service = $this->mock(DotacionInformeFunciones::class);
        if ($permitido) {
            $service->shouldReceive('fila')->once()->andReturn($this->fila());
            $this->get($this->url())->assertOk()->assertDownload()
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        } else {
            $service->shouldNotReceive('fila');
            $this->get($this->url())->assertForbidden();
        }
    }

    public function test_archivo_preserva_dos_hojas_valores_formulas_tipos_y_estilos(): void
    {
        $fila = $this->fila();
        $fila['rbd'] = '009999';
        $fila['nombre'] = '=Escuela sintética';
        $fila['comuna'] = '+Comuna sintética';
        $book = app(DotacionInformeFuncionesExport::class)->workbook([$fila], 2027, ['q' => '=BUSCAR', 'comuna' => '=COMUNA']);
        $path = Storage::disk('local')->path('informe-funciones-prueba.xlsx');
        try {
            (new Xlsx($book))->save($path);
            $loaded = IOFactory::load($path);
            try {
                $this->assertSame(['Dotación por funciones', 'Conciliación'], $loaded->getSheetNames());
                $resumen = $loaded->getSheet(0);
                $concilia = $loaded->getSheet(1);
                $this->assertSame('009999', $resumen->getCell('A7')->getValue());
                $this->assertSame('=Escuela sintética', $resumen->getCell('B7')->getValue());
                $this->assertSame('s', $resumen->getCell('B7')->getDataType());
                $this->assertSame('s', $concilia->getCell('C7')->getDataType());
                $this->assertSame('n', $resumen->getCell('H7')->getDataType());
                $this->assertEquals(30.37, $resumen->getCell('H7')->getValue());
                $this->assertSame('Horas SEP 2027 (complementarias)', $resumen->getCell('M6')->getValue());
                $this->assertSame('f', $concilia->getCell('J7')->getDataType());
                foreach (['E7' => 100, 'F7' => 97, 'G7' => 3, 'H7' => 70, 'I7' => 5, 'J7' => 75, 'K7' => 7, 'L7' => 15,
                    'M7' => 0, 'N7' => 0.37, 'O7' => 6.5, 'P7' => 2.25, 'Q7' => 100, 'R7' => 0, 'Q8' => 100, 'R8' => 0] as $cell => $expected) {
                    $this->assertEqualsWithDelta($expected, $concilia->getCell($cell)->getCalculatedValue(), 0.00001, $cell);
                }
                $this->assertEquals(100, $resumen->getCell('L8')->getCalculatedValue());
                $this->assertSame('#,##0', $resumen->getStyle('K7')->getNumberFormat()->getFormatCode());
                $this->assertSame('#,##0.00', $resumen->getStyle('L7')->getNumberFormat()->getFormatCode());
                $this->assertSame('FFF8E1', $concilia->getStyle('P7')->getFill()->getStartColor()->getRGB());
                $this->assertSame('s', $resumen->getCell('A2')->getDataType());
                $this->assertStringContainsString('Búsqueda: =BUSCAR', $resumen->getCell('A2')->getValue());
                $this->assertSame('0B3D91', $resumen->getStyle('A6')->getFill()->getStartColor()->getRGB());
                foreach ($loaded->getAllSheets() as $sheet) {
                    foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                        $cell = $sheet->getCell($coordinate);
                        if ($cell->getDataType() === 'f') {
                            $this->assertIsNumeric($cell->getCalculatedValue(), $sheet->getTitle().'!'.$coordinate);
                        }
                    }
                }
            } finally {
                $loaded->disconnectWorksheets();
            }
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_formulas_totales_vacios_y_filtros_se_guardan_correctamente(): void
    {
        $export = app(DotacionInformeFuncionesExport::class);
        foreach ([[], [$this->fila(), $this->fila()]] as $rows) {
            $book = $export->workbook($rows, 2026);
            try {
                $sheet = $book->getSheet(0);
                $concilia = $book->getSheet(1);
                $this->assertSame('C7', $sheet->getFreezePane());
                $this->assertSame('C7', $concilia->getFreezePane());
                $this->assertSame('A6:M'.(6 + count($rows)), $sheet->getAutoFilter()->getRange());
                $this->assertSame('A6:R'.(6 + count($rows)), $concilia->getAutoFilter()->getRange());
                $last = count($rows) ? 7 + count($rows) : 8;
                $this->assertEquals(count($rows) * 100, $sheet->getCell('L'.$last)->getCalculatedValue());
                $this->assertEquals(0, $concilia->getCell('R'.$last)->getCalculatedValue());
                if (! $rows) { $this->assertStringContainsString('Sin establecimientos', $sheet->getCell('A7')->getValue()); }
            } finally {
                $book->disconnectWorksheets();
            }
        }
    }

    public static function years(): array { return [[2026], [2027]]; }

    #[DataProvider('years')]
    public function test_exporta_todos_los_filtrados_aunque_se_solicite_segunda_pagina_y_libera_cache(int $year): void
    {
        for ($i = 1; $i < 15; $i++) { $this->establecimiento(99999 - $i); }
        $this->establecimiento(99980, ['sala_cuna' => true]);
        $this->establecimiento(99979, ['comuna' => 'Otra']);
        $before = DB::table('establecimientos')->orderBy('id')->get()->toJson();
        $loads = 0;
        $this->mock(DotacionInformeFunciones::class)->shouldReceive('fila')->times(15)
            ->andReturnUsing(function ($ee, $anio) use (&$loads, $year) {
                $this->assertSame($year, $anio);
                DotacionLecturaCache::recordar('referencia-prueba', function () use (&$loads) { return ++$loads; });
                return array_replace($this->fila(), ['rbd' => (string) $ee->rbd, 'nombre' => $ee->nombre_establecimiento]);
            });
        $response = $this->get($this->url(['anio' => $year, 'q' => 'Escuela', 'comuna' => 'Prueba', 'page' => 2]))->assertOk();
        $content = $response->streamedContent();
        $this->assertStringStartsWith('PK', $content);
        Storage::disk('local')->put('descarga.xlsx', $content);
        $book = IOFactory::load(Storage::disk('local')->path('descarga.xlsx'));
        try {
            $sheet = $book->getSheet(0);
            $this->assertSame('A6:M21', $sheet->getAutoFilter()->getRange());
            $this->assertEquals(1500, $sheet->getCell('L22')->getCalculatedValue());
            $this->assertStringContainsString('Establecimientos: 15', $sheet->getCell('A2')->getValue());
            $this->assertStringContainsString('Comuna: Prueba', $sheet->getCell('A2')->getValue());
        } finally { $book->disconnectWorksheets(); }
        $this->assertSame(15, $loads);
        $this->assertSame($before, DB::table('establecimientos')->orderBy('id')->get()->toJson());
    }

    public function test_boton_conserva_anio_busqueda_comuna_y_no_limita_a_pagina_actual(): void
    {
        $url = route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1, 'anio' => 2026, 'q' => 'Escuela', 'comuna' => 'Prueba', 'page' => 2]);
        $response = $this->get($url)->assertOk()->assertSee('Descargar Excel');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $link = $xpath->query('//a[contains(normalize-space(.),"Descargar Excel")]')->item(0);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['informe_funciones' => '1', 'export_excel' => '1', 'anio' => '2026', 'q' => 'Escuela', 'comuna' => 'Prueba'], $query);
    }

    public static function fallos(): array { return [['excepción'], ['marca de error']]; }

    #[DataProvider('fallos')]
    public function test_fallo_de_establecimiento_cancela_el_archivo_y_muestra_el_rbd(string $fallo): void
    {
        $this->establecimiento(99998);
        $intentos = 0;
        $service = $this->mock(DotacionInformeFunciones::class);
        $service->shouldReceive('fila')->twice()->andReturnUsing(function () use (&$intentos, $fallo) {
            if (++$intentos === 1) { return $this->fila(); }
            if ($fallo === 'marca de error') { return ['error' => true]; }
            throw new \RuntimeException('Fallo sintético');
        });
        $this->from('/informe')->get($this->url())->assertRedirect('/informe')->assertSessionHasErrors('export_excel');
        $this->assertStringContainsString('99999', session('errors')->first('export_excel'));
    }

    public function test_descarga_valida_filtros_y_anio_antes_de_calcular(): void
    {
        $this->mock(DotacionInformeFunciones::class)->shouldNotReceive('fila');
        foreach ([['anio' => 2028], ['q' => ['inválido']], ['export_excel' => ['inválido']]] as $overrides) {
            $this->from('/informe')->get($this->url($overrides))->assertRedirect('/informe')->assertSessionHasErrors();
        }
    }

    private function url(array $extra = []): string
    {
        return route('admin.dotacion-establecimiento.index', $extra + ['informe_funciones' => 1, 'anio' => 2027, 'export_excel' => 1]);
    }

    private function establecimiento(int $rbd, array $extra = []): void
    {
        Establecimiento::create($extra + ['cod_estab' => $rbd, 'rbd' => $rbd, 'nombre_establecimiento' => 'Escuela sintética '.$rbd, 'comuna' => 'Prueba']);
    }

    private function fila(): array
    {
        return [
            'rbd' => '99999', 'nombre' => 'Escuela sintética', 'comuna' => 'Prueba', 'error' => false,
            'docentes_total' => 2, 'contrato_original' => 100.0, 'contrato_total' => 100.0,
            'contrato_considerado' => 97.0, 'horas_excluidas' => 3.0,
            'asignadas' => 75.0, 'otras' => 5.0, 'reservadas' => 7.0, 'sin_asignar' => 15.0, 'sobreasignadas' => 0.0,
            'redondeo' => 0.37, 'sep' => 6.5, 'sep_sin_desglose' => 2.25, 'periodos' => ['08/2026'],
            'grupos' => ['directiva' => ['docentes' => 1, 'horas' => 10.0], 'tecnico_pedagogica' => ['docentes' => 1, 'horas' => 20.0],
                'aula' => ['docentes' => 1, 'horas' => 30.37], 'pie' => ['docentes' => 1, 'horas' => 9.63]],
        ];
    }
}
