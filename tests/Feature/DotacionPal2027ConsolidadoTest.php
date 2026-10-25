<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Models\Establecimiento;
use App\Support\DotacionPal2027;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;
use XMLReader;
use ZipArchive;

class DotacionPal2027ConsolidadoTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id(); $t->unsignedInteger('cod_estab')->unique(); $t->unsignedInteger('rbd')->unique();
            $t->string('nombre_establecimiento'); $t->string('comuna')->nullable(); $t->boolean('sala_cuna')->nullable(); $t->timestamps();
        });
        Schema::create('modules', function (Blueprint $t): void { $t->id(); $t->string('key'); });
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
        $this->withoutVite();
        Storage::disk('local')->put('pal-global-views/layouts/app.blade.php', '<html><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('pal-global-views'));
    }

    public static function roles(): array
    {
        return [['admin', true], ['coordinador_uatp', true], ['coordinador_gdp', true], ['supervisor_plani', true],
            ['funcionario_directivo_estab', false], ['funcionario_estab', false], ['postulante', false], ['funcionario_ac', false]];
    }

    #[DataProvider('roles')]
    public function test_rol_activo_protege_consolidado_y_boton(string $role, bool $allowed): void
    {
        if ($role !== 'admin') {
            $id = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $id, 'model_type' => auth()->user()::class, 'model_id' => auth()->id()]);
            auth()->user()->unsetRelation('roles');
        }
        $this->withSession(['active_role' => $role]);
        $this->mock(DotacionPal2027::class)->shouldNotReceive('datos');
        $response = $this->get($this->url());
        if ($allowed) { $response->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private'); }
        else { $response->assertForbidden(); $this->get($this->url(['descargar' => 1]))->assertForbidden(); }
        foreach ([2026, 2027] as $year) {
            $html = view('admin.dotacion-establecimiento.index', ['establecimientos' => new LengthAwarePaginator([], 0, 20),
                'activeRole' => $role, 'anio' => $year, 'q' => 'filtro', 'comuna' => 'Otra', 'comunas' => collect()])->render();
            $this->assertSame($allowed && $year === 2027, str_contains($html, 'Excel PAL 2027 consolidado'));
            if ($allowed && $year === 2027) {
                $this->assertStringContainsString($this->url(), $html);
                $this->assertStringNotContainsString($this->url().'?q=', $html);
            }
        }
    }

    public function test_previa_incluye_todos_sin_calcular_nominas_ni_heredar_filtros(): void
    {
        for ($i = 0; $i < 25; $i++) { $this->ee(99000 + $i, ['sala_cuna' => $i === 0 ? null : false]); }
        $this->ee(99900, ['sala_cuna' => true, 'nombre_establecimiento' => 'VTF excluido']);
        $this->mock(DotacionPal2027::class)->shouldNotReceive('datos');
        $response = $this->get($this->url(['q' => 'no existe', 'comuna' => 'no existe', 'page' => 10, 'establecimiento' => 99900]))
            ->assertOk()->assertSee('25 establecimientos incluidos')->assertSee('Escuela sintética 99000')->assertSee('Escuela sintética 99024')
            ->assertDontSee('VTF excluido')->assertSee('Descargar Excel consolidado');
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $link = (new \DOMXPath($dom))->query('//a[contains(normalize-space(.),"Descargar Excel consolidado")]')->item(0);
        $this->assertSame($this->url(['descargar' => 1]), $link->getAttribute('href'));
    }

    public function test_descarga_todos_en_un_archivo_incluyendo_establecimiento_sin_datos(): void
    {
        $this->withoutExceptionHandling();
        for ($i = 0; $i < 16; $i++) { $this->ee(99000 + $i); }
        $this->ee(99900, ['sala_cuna' => true]);
        $before = DB::table('establecimientos')->orderBy('id')->get()->toJson();
        $vistos = [];
        $this->mock(DotacionPal2027::class)->makePartial()->shouldReceive('datos')->times(16)->andReturnUsing(function ($ee) use (&$vistos) {
            $vistos[] = (int) $ee->rbd;
            return $ee->rbd === 99015 ? ['docentes' => [], 'cursos' => ['rows' => []]] : $this->data();
        });
        $response = $this->get($this->url(['descargar' => 1, 'q' => 'ninguno', 'page' => 9]))->assertOk()
            ->assertDownload('borrador_pal_2027_todos_establecimientos.xlsx')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        Storage::disk('local')->put('todos.xlsx', $response->streamedContent());
        $zip = $this->zip(Storage::disk('local')->path('todos.xlsx'));
        try {
            $ee = $this->xml($zip->getFromName('xl/worksheets/sheet3.xml'));
            $docs = $this->xml($zip->getFromName('xl/worksheets/sheet4.xml'));
            $summary = $this->xml($zip->getFromName('xl/worksheets/sheet2.xml'));
            $this->assertCount(16, $ee->xpath('//m:row[@r>=4]/m:c[starts-with(@r,"B")]/m:is/m:t'));
            $this->assertCount(15, $docs->xpath('//m:row[@r>=3]/m:c[starts-with(@r,"A")]/m:is/m:t'));
            $this->assertSame('99015', (string) $ee->xpath('//m:c[@r="B19"]/m:is/m:t')[0]);
            $this->assertStringContainsString('Sin cursos registrados', (string) $ee->xpath('//m:c[@r="C19"]/m:is/m:t')[0]);
            $this->assertSame('99014', (string) $docs->xpath('//m:c[@r="C17"]/m:is/m:t')[0]);
            $this->assertStringContainsString("'Info Establecimiento'!\$B\$4:\$B\$34", (string) $summary->xpath('//m:c[@r="A3"]/m:f')[0]);
            $this->assertSame('A3:A18', (string) $summary->xpath('//m:c[@r="A3"]/m:f')[0]['ref']);
            $this->assertEmpty($summary->xpath('//m:row[@r>=3]/m:c/m:v'));
            $this->assertEmpty($summary->xpath('//m:c[@r="A4"]/*'));
            $this->assertCount(19, $summary->xpath('//m:row[@r="18"]/m:c'));
            $this->assertStringContainsString('$A18', (string) $summary->xpath('//m:c[@r="B18"]/m:f')[0]);
            $this->assertStringNotContainsString('\\$A18', (string) $summary->xpath('//m:c[@r="B18"]/m:f')[0]);
            foreach (['xl/worksheets/sheet5.xml','xl/worksheets/sheet6.xml'] as $part) { $this->assertEmpty($this->xml($zip->getFromName($part))->xpath('//m:row[@r>=3]/m:c/m:v')); }
        } finally { $zip->close(); }
        $this->assertSame(range(99000, 99015), $vistos);
        $this->assertSame($before, DB::table('establecimientos')->orderBy('id')->get()->toJson());
    }

    public function test_amplia_110_establecimientos_1540_niveles_y_11000_filas_sin_truncar(): void
    {
        $entry = ['A' => '99000001-K', 'B' => '=Escuela sintética', 'C' => '99000', 'D' => 'Educación Básica',
            'E' => 'Docente', 'F' => 'Docente', 'G' => 'Docente aula', 'H' => 'Titular', 'K' => 38, 'M' => '65/35', 'S' => 'Borrador de prueba'];
        $curso = ['A' => '=Escuela sintética', 'B' => '99000', 'C' => '1° Básico', 'D' => 25, 'E' => 1, 'F' => 38, 'H' => 44];
        $visitados = 0;
        $reportes = (function () use ($entry, $curso, &$visitados) {
            for ($i = 0; $i < 110; $i++) {
                $visitados++;
                $entry['C'] = $curso['B'] = (string) (99000 + $i);
                yield ['cursos' => array_fill(0, 14, $curso), 'docentes' => array_fill(0, 100, $entry)];
            }
        })();
        $path = Storage::disk('local')->path('grande.xlsx');
        $stats = app(DotacionPal2027ConsolidadoExport::class)->guardarTodos($reportes, $path);
        $this->assertSame(['establecimientos' => 110, 'niveles' => 1540, 'filas_docentes' => 11000], $stats);
        $this->assertSame(110, $visitados);
        $zip = $this->zip($path);
        try {
            $xml = $zip->getFromName('xl/worksheets/sheet4.xml');
            $reader = new XMLReader; $reader->XML($xml, null, LIBXML_NONET);
            $count = 0; $first = $last = null;
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row' && (int) $reader->getAttribute('r') >= 3) {
                    $count++;
                    if ($count === 1 || $count === 11000) {
                        $row = $this->xml($reader->readOuterXml());
                        if ($count === 1) { $first = (string) $row->xpath('//m:c[@r="J3"]/m:f')[0]; }
                        else {
                            $last = (string) $row->xpath('//m:c[@r="J11002"]/m:f')[0];
                            $this->assertSame('99109', (string) $row->xpath('//m:c[@r="C11002"]/m:is/m:t')[0]);
                        }
                    }
                }
            }
            $reader->close();
            $this->assertSame(11000, $count); $this->assertSame($first, $last);
            $this->assertStringContainsString('ref="A1:S11002"', $zip->getFromName('xl/tables/table1.xml'));
            $this->assertStringContainsString('sqref="M3:M11002"', $xml);
            unset($xml);
            $ee = $this->xml($zip->getFromName('xl/worksheets/sheet3.xml'));
            $this->assertCount(1540, $ee->xpath('//m:row[@r>=4]/m:c[starts-with(@r,"B")]/m:is/m:t'));
            $this->assertSame('+E1543*F1543', (string) $ee->xpath('//m:c[@r="G1543"]/m:f')[0]);
            $summary = $this->xml($zip->getFromName('xl/worksheets/sheet2.xml'));
            $this->assertCount(19, $summary->xpath('//m:row[@r="112"]/m:c'));
            $this->assertStringContainsString('$A112', (string) $summary->xpath('//m:c[@r="B112"]/m:f')[0]);
            $this->assertStringContainsString('$A$3:$A$11002', (string) $summary->xpath('//m:c[@r="B112"]/m:f')[0]);
            $this->assertStringContainsString('$J$3:$J$11002', (string) $summary->xpath('//m:c[@r="I112"]/m:f')[0]);
            $this->assertStringContainsString('$B$4:$B$1543', (string) $summary->xpath('//m:c[@r="A3"]/m:f')[0]);
            $this->assertSame('A3:A112', (string) $summary->xpath('//m:c[@r="A3"]/m:f')[0]['ref']);
        } finally { $zip->close(); }
    }

    public function test_fallo_de_un_establecimiento_cancela_toda_la_descarga_y_limpia_temporales(): void
    {
        $this->ee(99000); $this->ee(99001);
        $this->mock(DotacionPal2027::class)->makePartial()->shouldReceive('datos')->twice()->andReturnUsing(function ($ee) {
            if ($ee->rbd === 99001) { throw new \RuntimeException('Fallo sintético'); }
            return $this->data();
        });
        $before = glob(sys_get_temp_dir().'/sga-pal-*'); sort($before);
        $this->from($this->url())->get($this->url(['descargar' => 1]))->assertRedirect($this->url())->assertSessionHasErrors('pal');
        $this->assertStringContainsString('99001', session('errors')->first('pal'));
        $after = glob(sys_get_temp_dir().'/sga-pal-*'); sort($after);
        $this->assertSame($before, $after);
    }

    public function test_valida_anio_parametros_y_catalogo_vacio_y_no_admite_invitados(): void
    {
        $this->mock(DotacionPal2027::class)->shouldNotReceive('datos');
        foreach ([['anio' => 2026], ['anio' => [2027]], ['descargar' => 'mal']] as $params) { $this->get($this->url($params))->assertRedirect()->assertSessionHasErrors(); }
        $this->get($this->url())->assertOk()->assertSee('No hay establecimientos participantes');
        $this->get($this->url(['descargar' => 1]))->assertRedirect()->assertSessionHasErrors('pal');
        auth()->logout(); $this->get($this->url(['descargar' => 1]))->assertRedirect(route('login'));
    }

    private function ee(int $rbd, array $extra = []): Establecimiento
    {
        return Establecimiento::create($extra + ['cod_estab' => $rbd, 'rbd' => $rbd, 'nombre_establecimiento' => 'Escuela sintética '.$rbd, 'comuna' => 'Prueba', 'sala_cuna' => false]);
    }

    private function data(): array
    {
        return ['resumen' => ['anio_padron' => 2026], 'cursos' => ['rows' => ['1B' => ['label' => '1° Básico', 'matricula' => 25,
            'cursos' => 1, 'horas_por_nivel' => 38, 'total_horas_contrato_equivalente' => 44, 'detalles' => [['establecimiento_curso_id' => 1,
                'nombre_seccion' => '1° Básico A', 'matricula' => 25, 'horas_plan_base' => 38, 'horas_contrato_base' => 44]]]]],
            'docentes' => [['rut' => '99000001-K', 'titulo' => 'Sin título declarado', 'funcion' => 'Docente', 'horas_contrato' => 44, 'horas_planta' => 44,
                'asignaciones' => [['id' => 1, 'docente_rut' => '99000001-K', 'estado' => 'activa', 'tipo_asignacion' => 'plan_estudio', 'subvencion' => 'General',
                    'horas_contrato' => 44, 'horas_plan_pedagogicas' => 38, 'proporcion_aplicada' => '65/35',
                    'establecimientoCurso' => ['curso' => ['codigo' => '1B', 'nivel_educativo' => 'Educación Básica']]]]]]];
    }

    private function url(array $params = []): string { return route('admin.dotacion-establecimiento.pal-2027.todos', $params); }
    private function zip(string $path): ZipArchive { $z = new ZipArchive; $this->assertTrue($z->open($path)); return $z; }
    private function xml(string $xml): \SimpleXMLElement { $x = simplexml_load_string($xml); $x->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'); return $x; }
}
