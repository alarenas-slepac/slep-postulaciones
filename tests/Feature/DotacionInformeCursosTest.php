<?php

namespace Tests\Feature;

use App\Exports\DotacionInformeCursosExport;
use App\Models\Curso;
use App\Models\DotacionCursoCombinado;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionInformeCursos;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionInformeCursosTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp(); // Antes del bootstrap fuerza SQLite :memory: y rechaza caché real; discos simulados.
        $this->withoutVite();
        Storage::disk('local')->put('cursos-views/layouts/app.blade.php', '<!doctype html><html><head>@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('cursos-views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        Schema::create('modules', function (Blueprint $t) { $t->id(); $t->string('key'); });
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        Schema::create('planes_estudio', fn (Blueprint $t) => $t->id());
        foreach (['2026_01_23_000001_create_establecimientos_table.php', '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->ee = $this->establecimiento(99001);
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
    }

    private function establecimiento(int $rbd, array $extra = []): Establecimiento
    {
        return Establecimiento::create($extra + ['cod_estab' => $rbd, 'rbd' => $rbd, 'nombre_establecimiento' => 'Escuela de prueba '.$rbd, 'comuna' => 'Prueba']);
    }

    private function curso(string $codigo, string $letra = 'A', array $extra = []): EstablecimientoCurso
    {
        $catalogo = Curso::where('codigo', $codigo)->firstOrFail();
        return EstablecimientoCurso::create($extra + ['establecimiento_id' => $this->ee->id, 'curso_id' => $catalogo->id,
            'anio' => 2027, 'letra' => $letra, 'nombre_seccion' => $catalogo->nombre.' '.$letra,
            'matricula' => 20, 'activo' => true, 'regimen_jec' => 'Con JEC']);
    }

    private function grupo(array $cursos, array $extra = []): DotacionCursoCombinado
    {
        $grupo = DotacionCursoCombinado::create($extra + ['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'nombre' => 'Grupo de prueba', 'activo' => true]);
        foreach ($cursos as $curso) { $grupo->miembros()->create(['establecimiento_curso_id' => $curso->id]); }
        return $grupo;
    }

    private function informe(?array $ids = null, int $anio = 2027): array
    {
        $service = app(DotacionInformeCursos::class);
        $catalogo = $service->niveles($anio);
        return $service->construir(Establecimiento::where('sala_cuna', false)->get(), $anio, $catalogo, $ids ?? $catalogo->modelKeys());
    }

    private function url(array $extra = []): string
    {
        return route('admin.dotacion-establecimiento.index', $extra + ['informe_cursos' => 1, 'anio' => 2027]);
    }

    public static function roles(): array
    {
        return [['admin', true], ['coordinador_uatp', true], ['coordinador_gdp', true], ['supervisor_plani', true],
            ['funcionario_directivo_estab', false], ['funcionario_ac', false], ['postulante', false]];
    }

    #[DataProvider('roles')]
    public function test_rol_activo_protege_vista_excel_y_boton(string $role, bool $allowed): void
    {
        $user = auth()->user();
        if ($role !== 'admin') {
            $id = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $id, 'model_type' => $user::class, 'model_id' => $user->id]);
            $user->unsetRelation('roles');
        }
        $user->forceFill(['establecimiento_id' => $this->ee->id])->save();
        $this->withSession(['active_role' => $role]);
        foreach ([[], ['export_cursos' => 1]] as $extra) {
            $response = $this->get($this->url($extra));
            if ($allowed) { $response->assertOk(); } else { $response->assertForbidden(); }
        }
        $html = view('admin.dotacion-establecimiento.index', [
            'establecimientos' => new LengthAwarePaginator([], 0, 20), 'activeRole' => $role,
            'anio' => 2027, 'q' => 'Prueba', 'comuna' => 'Prueba', 'comunas' => collect(),
        ])->render();
        $this->assertSame($allowed, str_contains($html, 'Informe de cursos por nivel'));
    }

    public function test_un_grupo_cuenta_uno_y_dos_grupos_cuentan_dos_sin_repetir_miembros(): void
    {
        $this->grupo([$this->curso('NT1'), $this->curso('NT2')]);
        $this->grupo([$this->curso('NT1', 'B'), $this->curso('NT2', 'B')]);
        $regular = $this->curso('1B');
        $result = $this->informe();
        $this->assertSame(2, $result['combinados']);
        $this->assertSame(1, $result['regulares']);
        $this->assertSame(3, $result['total']);
        $this->assertSame(1, $result['filas'][0]['conteos']['nivel_'.$regular->curso_id]);
        $combos = array_filter($result['columnas'], fn ($c) => $c['combinado']);
        $this->assertCount(1, $combos);
        $this->assertSame('NT1 + NT2 (combinado)', reset($combos)['label']);
        $this->assertSame(2, $result['totales'][array_key_first($combos)]);
        $this->assertSame(3, array_sum($result['filas'][0]['conteos']));
    }

    public function test_seleccionar_parte_o_todo_un_combinado_lo_cuenta_una_vez(): void
    {
        $nt1 = $this->curso('NT1'); $nt2 = $this->curso('NT2');
        $this->grupo([$nt1, $nt2]);
        foreach ([[$nt1->curso_id], [$nt2->curso_id], [$nt1->curso_id, $nt2->curso_id]] as $ids) {
            $result = $this->informe($ids);
            $this->assertSame(1, $result['total']);
            $this->assertSame(1, $result['combinados']);
            foreach ($ids as $id) { $this->assertSame(0, $result['totales']['nivel_'.$id]); }
        }
        $basic = $this->curso('1B');
        $result = $this->informe([$basic->curso_id]);
        $this->assertSame(0, $result['combinados']);
        $this->assertSame(1, $result['total']);
    }

    public function test_combinado_entre_ensenanzas_y_dos_secciones_del_mismo_nivel(): void
    {
        $this->grupo([$this->curso('NT2'), $this->curso('1B')]);
        $this->grupo([$this->curso('2B'), $this->curso('2B', 'B')]);
        $result = $this->informe();
        $this->assertSame(2, $result['total']);
        $this->assertContains('NT2 + 1° Básico (combinado)', array_column($result['columnas'], 'label'));
        $this->assertContains('2° Básico (combinado)', array_column($result['columnas'], 'label'));
    }

    public static function years(): array { return [[2026], [2027]]; }

    #[DataProvider('years')]
    public function test_respeta_anio_actividad_matricula_y_grupos_inactivos(int $anio): void
    {
        $primero = $this->curso('1B', 'A', ['anio' => $anio]);
        $segundo = $this->curso('2B', 'A', ['anio' => $anio]);
        $this->grupo([$primero, $segundo], ['anio' => $anio, 'activo' => false]);
        $this->curso('3B', 'A', ['anio' => $anio, 'matricula' => 0]);
        $this->curso('4B', 'A', ['anio' => $anio, 'activo' => false]);
        $this->curso('5B', 'A', ['anio' => $anio - 1]);
        $result = $this->informe(anio: $anio);
        $this->assertSame(2, $result['total']);
        $this->assertSame(0, $result['combinados']);
    }

    public function test_excel_todos_los_filtrados_sin_paginacion_conserva_ceros_y_no_escribe(): void
    {
        for ($i = 2; $i <= 25; $i++) { $this->establecimiento(99000 + $i); }
        $this->establecimiento(99998, ['sala_cuna' => true]);
        $this->establecimiento(99999, ['comuna' => 'Otra']);
        $curso = $this->curso('1B');
        $antes = DB::table('establecimiento_cursos')->get()->toJson();
        $response = $this->get($this->url(['export_cursos' => 1, 'comuna' => 'Prueba', 'q' => 'Escuela', 'page' => 2,
            'seleccion' => 1, 'niveles' => [$curso->curso_id]]))->assertOk()->assertDownload();
        Storage::disk('local')->put('cursos.xlsx', $response->streamedContent());
        $book = IOFactory::load(Storage::disk('local')->path('cursos.xlsx'));
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame('A6:F31', $sheet->getAutoFilter()->getRange());
            $this->assertEquals(1, $sheet->getCell('D32')->getValue());
            $this->assertEquals(1, $sheet->getCell('E32')->getValue());
            $this->assertSame('1° Básico', $sheet->getCell('D6')->getValue());
            $this->assertEquals(0, $sheet->getCell('E31')->getValue());
            $this->assertSame('D7', $sheet->getFreezePane());
        } finally { $book->disconnectWorksheets(); }
        $this->assertSame($antes, DB::table('establecimiento_cursos')->get()->toJson());
        $this->get($this->url(['comuna' => 'Prueba', 'q' => '99001']))->assertOk()->assertViewHas('informe', fn ($r) => count($r['filas']) === 1);
    }

    public function test_excel_textos_literales_totales_combinados_y_rbd_con_ceros(): void
    {
        $this->grupo([$this->curso('NT1'), $this->curso('NT2')], ['nombre' => '=Grupo de prueba']);
        $result = $this->informe();
        $result['filas'][0]['rbd'] = '0099001';
        $result['filas'][0]['nombre'] = '=Escuela de prueba';
        $catalogo = app(DotacionInformeCursos::class)->niveles(2027);
        $book = app(DotacionInformeCursosExport::class)->workbook($result, $catalogo,
            ['anio' => 2027, 'q' => '=texto', 'comuna' => 'Prueba', 'seleccionados' => $catalogo->modelKeys()]);
        try {
            $path = Storage::disk('local')->path('cursos-textos.xlsx');
            (new Xlsx($book))->save($path);
            $loaded = IOFactory::load($path);
            try {
                $sheet = $loaded->getActiveSheet();
                $this->assertSame('0099001', $sheet->getCell('A7')->getValue());
                $this->assertSame('s', $sheet->getCell('B7')->getDataType());
                $this->assertSame('=Escuela de prueba', $sheet->getCell('B7')->getValue());
                $this->assertSame(1, (int) $sheet->getCell(Coordinate::stringFromColumnIndex(count($result['columnas']) + 4).'7')->getValue());
            } finally { $loaded->disconnectWorksheets(); }
        } finally { $book->disconnectWorksheets(); }
    }

    public function test_catalogo_historico_inactivo_y_otros_niveles(): void
    {
        foreach (['EPJA' => 'Educación de Adultos', 'ESP' => 'Educación Especial'] as $code => $nivel) {
            Curso::create(['nombre' => $code, 'codigo' => $code, 'nivel_educativo' => $nivel, 'activo' => false]);
            $this->curso($code);
        }
        $catalogo = app(DotacionInformeCursos::class)->niveles(2027);
        $this->assertTrue($catalogo->contains('codigo', 'EPJA'));
        $this->assertTrue($catalogo->contains('codigo', 'ESP'));
        $this->assertSame(2, $this->informe()['total']);
        $this->assertFalse(app(DotacionInformeCursos::class)->niveles(2026)->contains('codigo', 'EPJA'));
    }

    public function test_un_combinado_no_incorpora_miembros_ajenos_inactivos_o_sin_matricula(): void
    {
        $otro = $this->establecimiento(99002);
        $this->grupo([$this->curso('NT1'), $this->curso('NT2', 'A', ['matricula' => 0]),
            $this->curso('1B', 'A', ['establecimiento_id' => $otro->id]),
            $this->curso('2B', 'A', ['activo' => false]), $this->curso('3B', 'A', ['anio' => 2026])]);
        $result = $this->informe();
        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['combinados']);
        $this->assertSame(1, $result['regulares']);
        $this->assertContains('NT1 (combinado)', array_column($result['columnas'], 'label'));
    }

    public function test_lectura_territorial_por_lotes_sin_nominas_ni_consultas_por_establecimiento(): void
    {
        $this->curso('1B');
        for ($i = 2; $i <= 60; $i++) {
            $ee = $this->establecimiento(99000 + $i);
            $this->curso('1B', 'A', ['establecimiento_id' => $ee->id]);
        }
        DB::enableQueryLog();
        try {
            $result = $this->informe();
            $queries = DB::getQueryLog();
        } finally { DB::disableQueryLog(); DB::flushQueryLog(); }
        $this->assertSame(60, $result['total']);
        $this->assertCount(60, $result['filas']);
        $this->assertLessThanOrEqual(8, count($queries));
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/reemplazos_personal|dotacion_docente_asignaciones|planes_estudio/i', $query['query']);
        }
    }

    public static function invalidos(): array
    {
        return [[['seleccion' => 1]], [['niveles' => ['']]], [['niveles' => 'NT1']], [['niveles' => [999999]]],
            [['niveles' => [1, 1]]], [['anio' => 2019]], [['anio' => 'texto']], [['q' => str_repeat('x', 151)]]];
    }

    #[DataProvider('invalidos')]
    public function test_rechaza_filtros_invalidos(array $filtro): void
    {
        $this->from('/informe')->get($this->url($filtro))->assertRedirect('/informe')->assertSessionHasErrors();
    }

    public function test_conflicto_de_grupos_no_produce_un_excel_con_doble_conteo(): void
    {
        $curso = $this->curso('1B');
        $this->grupo([$curso, $this->curso('2B')]);
        $this->grupo([$curso, $this->curso('3B')]);
        $this->from('/informe')->get($this->url(['export_cursos' => 1]))->assertRedirect('/informe')->assertSessionHasErrors('informe_cursos');
    }

    public function test_la_vista_muestra_errores_sin_fallar_por_una_seleccion_malformada(): void
    {
        $this->from($this->url())->get($this->url(['seleccion' => 1, 'niveles' => [['invalido']]]))
            ->assertRedirect($this->url())->assertSessionHasErrors('niveles.0');
        $this->get($this->url())->assertOk()->assertSee('alert-danger', false);
    }

    public function test_boton_conserva_filtros_y_no_pagina_y_vista_ofrece_seleccion(): void
    {
        $html = view('admin.dotacion-establecimiento.index', [
            'establecimientos' => new LengthAwarePaginator([], 0, 20), 'activeRole' => 'admin',
            'anio' => 2026, 'q' => 'Escuela', 'comuna' => 'Prueba', 'comunas' => collect(),
        ])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $link = (new \DOMXPath($dom))->query('//a[contains(normalize-space(.), "Informe de cursos por nivel")]')->item(0);
        parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['informe_cursos' => '1', 'anio' => '2026', 'q' => 'Escuela', 'comuna' => 'Prueba'], $query);
        $this->get($this->url())->assertOk()->assertSee('name="niveles[]"', false)->assertSee('Descargar Excel')->assertSee('Seleccionar todos');
        auth()->logout();
        $this->get($this->url())->assertRedirect(route('login'));
    }

    public function test_sin_establecimientos_y_sin_tablas_de_combinados_mantiene_historicos(): void
    {
        $this->curso('1B');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::drop('dotacion_curso_combinado_miembros');
        Schema::drop('dotacion_cursos_combinados');
        $this->assertSame(1, $this->informe()['total']);
        $this->get($this->url(['q' => 'Inexistente']))->assertOk()->assertSee('No se encontraron establecimientos');
        $this->get($this->url(['q' => 'Inexistente', 'export_cursos' => 1]))->assertOk()->assertDownload();
    }
}
