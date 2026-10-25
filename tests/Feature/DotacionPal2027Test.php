<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027Export;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionPal2027;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;
use ZipArchive;

class DotacionPal2027Test extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        Schema::create('modules', function (Blueprint $t) { $t->id(); $t->string('key'); });
        $this->ee = Establecimiento::create(['cod_estab' => 99999, 'dv' => 'K', 'rbd' => 99999, 'nombre_establecimiento' => '=Escuela sintética', 'comuna' => 'Comuna de prueba']);
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
        $this->withoutVite();
        Storage::disk('local')->put('pal-views/layouts/app.blade.php', '<html><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('pal-views'));
    }

    public static function roles(): array
    {
        return [['admin', true], ['coordinador_uatp', true], ['coordinador_gdp', true], ['supervisor_plani', true],
            ['funcionario_directivo_estab', false], ['funcionario_estab', false], ['postulante', false], ['funcionario_ac', false]];
    }

    #[DataProvider('roles')]
    public function test_verifica_rol_activo_antes_de_consultar_personal(string $role, bool $allowed): void
    {
        if ($role !== 'admin') {
            $id = DB::table('roles')->where('name', $role)->value('id') ?? DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
            DB::table('model_has_roles')->insert(['role_id' => $id, 'model_type' => auth()->user()::class, 'model_id' => auth()->id()]);
            auth()->user()->unsetRelation('roles');
        }
        $this->withSession(['active_role' => $role]);
        $mock = $this->mock(DotacionPal2027::class)->makePartial();
        if ($allowed) {
            $mock->shouldReceive('datos')->once()->andReturn($this->data());
            $this->get($this->url())->assertOk()->assertSee('Descargar borrador Excel')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        } else {
            $mock->shouldNotReceive('datos');
            $this->get($this->url(['descargar' => 1]))->assertForbidden();
        }
    }

    public function test_rechaza_otros_anios_vtf_y_parametros_invalidos(): void
    {
        $this->mock(DotacionPal2027::class)->shouldNotReceive('datos');
        foreach ([['anio' => 2026], ['anio' => [2027]], ['descargar' => 'invalido']] as $params) {
            $this->get($this->url($params))->assertRedirect()->assertSessionHasErrors();
        }
        $this->ee->update(['sala_cuna' => true]);
        $this->get($this->url())->assertNotFound();
    }

    public function test_sin_login_no_entrega_padron(): void
    {
        auth()->logout();
        $this->get($this->url(['descargar' => 1]))->assertRedirect(route('login'));
    }

    public function test_descarga_privada_respeta_template_y_textos_y_no_escribe_en_la_base(): void
    {
        $this->withoutExceptionHandling();
        $this->mock(DotacionPal2027::class)->makePartial()->shouldReceive('datos')->once()->andReturn($this->data());
        $before = DB::table('establecimientos')->get()->toJson();
        $response = $this->get($this->url(['descargar' => 1]))->assertOk()->assertDownload('borrador_pal_2027_rbd_99999.xlsx');
        Storage::disk('local')->put('pal.xlsx', $response->streamedContent());
        $zip = $this->zip(Storage::disk('local')->path('pal.xlsx'));
        $original = $this->zip(app(DotacionPal2027Export::class)->template());
        try {
            foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml', 'xl/worksheets/sheet5.xml', 'xl/worksheets/sheet6.xml', 'xl/worksheets/sheet7.xml', 'xl/worksheets/sheet8.xml', 'xl/worksheets/sheet9.xml'] as $part) {
                $this->assertSame($original->getFromName($part), $zip->getFromName($part), $part);
            }
            foreach (['xl/worksheets/sheet3.xml', 'xl/worksheets/sheet4.xml'] as $part) {
                $this->assertSame($this->formulas($original->getFromName($part)), $this->formulas($zip->getFromName($part)));
            }
            $ee = $this->xml($zip->getFromName('xl/worksheets/sheet3.xml'));
            $this->assertSame('inlineStr', $ee->xpath('//m:c[@r="A4"]')[0]['t']->__toString());
            $this->assertSame('=Escuela sintética', (string) $ee->xpath('//m:c[@r="A4"]/m:is/m:t')[0]);
            $this->assertSame('44', (string) $ee->xpath('//m:c[@r="H4"]/m:v')[0]);
            $this->assertSame('38', (string) $ee->xpath('//m:c[@r="F4"]/m:v')[0]);
            $this->assertGreaterThan(20, (float) $ee->xpath('//m:row[@r="4"]')[0]['ht']);
            $oldStyles = $this->xml($original->getFromName('xl/styles.xml'))->xpath('//m:cellXfs/m:xf');
            $newStyles = $this->xml($zip->getFromName('xl/styles.xml'))->xpath('//m:cellXfs/m:xf');
            foreach ($oldStyles as $i => $style) { $this->assertSame($style->asXML(), $newStyles[$i]->asXML()); }
            $docs = $this->xml($zip->getFromName('xl/worksheets/sheet4.xml'));
            $this->assertSame('38', (string) $docs->xpath('//m:c[@r="K3"]/m:v')[0]);
            $this->assertSame('65/35', (string) $docs->xpath('//m:c[@r="M3"]/m:is/m:t')[0]);
            $this->assertEmpty($docs->xpath('//m:c[@r="A4"]/*'));
        } finally { $zip->close(); $original->close(); }
        $this->assertSame($before, DB::table('establecimientos')->get()->toJson());
    }

    public function test_contratos_titular_primero_pendientes_y_financiamientos_sin_duplicar(): void
    {
        $doc = $this->docente(['horas_contrato' => 44, 'horas_planta' => 20, 'horas_contrata' => 24,
            'asignaciones' => [$this->asignacion(['id' => 1, 'tipo_asignacion' => 'funcion_directiva', 'horas_contrato' => 22]),
                $this->asignacion(['id' => 2, 'tipo_asignacion' => 'pie_colaborativo', 'subvencion' => 'PIE', 'horas_contrato' => 3]),
                $this->asignacion(['id' => 3, 'tipo_asignacion' => 'otra_funcion', 'subvencion' => 'SEP', 'horas_contrato' => 5])]]);
        $rows = $this->reporte(['docentes' => [$doc]])['docentes'];
        $this->assertSame(['Titular','Plazo Fijo','Plazo Fijo','Plazo Fijo'], array_column($rows, 'H'));
        $this->assertEquals(22, array_sum(array_column($rows, 'R')));
        $this->assertEquals(3, array_sum(array_column($rows, 'P')));
        $this->assertEquals(5, array_sum(array_column($rows, 'Q')));
        $this->assertStringContainsString('14 h', $rows[0]['S']);
        $this->assertEquals(30, array_sum(array_column($rows, 'R')) + array_sum(array_column($rows, 'P')) + array_sum(array_column($rows, 'Q')));
    }

    public function test_parvularia_y_reparto_no_representable_se_marcan_sin_forzar_tablas(): void
    {
        $a = $this->asignacion(['proporcion_aplicada' => 'NT Con JEC · base contractual 55 h', 'horas_contrato' => 55, 'horas_plan_pedagogicas' => 38]);
        $doc = $this->docente(['horas_contrato' => 55, 'horas_planta' => 55, 'asignaciones' => [$a]]);
        $row = $this->reporte(['docentes' => [$doc]])['docentes'][0];
        $this->assertStringContainsString('INCOMPATIBLE:', $row['S']);
        $this->assertNull($row['K']); $this->assertNull($row['M']); $this->assertNull($row['R']);
        $split = $this->reporte(['docentes' => [$this->docente(['horas_planta' => 20, 'horas_contrata' => 24])]])['docentes'];
        $this->assertCount(2, $split);
        foreach ($split as $r) { $this->assertStringContainsString('INCOMPATIBLE:', $r['S']); }
    }

    public function test_funciones_sin_nivel_no_inventan_datos_y_art69_es_expreso(): void
    {
        $doc = $this->docente(['niveles_declarados' => 'Sin declaración', 'asignaciones' => [],
            'exclusion_docente' => ['motivo' => 'horas_catedra_art_69']]);
        $row = $this->reporte(['docentes' => [$doc]])['docentes'][0];
        $this->assertSame('', $row['D']); $this->assertSame('si', $row['I']);
        $this->assertStringContainsString('44 h', $row['S']);
        $this->assertNull($row['R']);
    }

    public function test_planes_mixtos_y_combinados_no_simulan_una_carga_uniforme(): void
    {
        $data = $this->data();
        $data['cursos']['rows']['1B']['horas_variable'] = true;
        $data['cursos_combinados']['grupos'] = [['activo' => true, 'miembros' => [['id' => 1]]]];
        $row = $this->reporte($data)['cursos'][0];
        $this->assertNull($row['F']); $this->assertNull($row['H']);
        $this->assertSame(25, $row['D']);
        $this->assertStringContainsString('INCOMPATIBLE', $row['C']);
    }

    public function test_220_filas_amplian_formulas_tabla_resumen_y_validaciones_sin_truncar(): void
    {
        $report = $this->reporte($this->data());
        $report['docentes'] = array_fill(0, 220, $report['docentes'][0]);
        $path = Storage::disk('local')->path('largo.xlsx');
        app(DotacionPal2027Export::class)->guardar($report, $path);
        $zip = $this->zip($path);
        try {
            $docs = $zip->getFromName('xl/worksheets/sheet4.xml');
            $formulas = $this->formulas($docs);
            $this->assertSame($formulas['J3'], $formulas['J222']);
            $this->assertSame($formulas['N3'], $formulas['N222']);
            $this->assertSame($formulas['O3'], $formulas['O222']);
            $this->assertStringContainsString('ref="A1:S222"', $zip->getFromName('xl/tables/table1.xml'));
            $this->assertStringContainsString('sqref="M3:M222"', $docs);
            $summary = $zip->getFromName('xl/worksheets/sheet2.xml');
            $this->assertStringContainsString('$A$3:$A$222', $summary);
            $this->assertStringNotContainsString('$A$3:$A$176', $summary);
            $this->assertCount(220, $this->xml($docs)->xpath('//m:row[@r>=3]/m:c[starts-with(@r,"A")]/m:is/m:t'));
        } finally { $zip->close(); }
    }

    public function test_plantilla_sin_ejemplos_caches_personales_ni_cambios_de_hojas(): void
    {
        $zip = $this->zip(app(DotacionPal2027Export::class)->template());
        try {
            foreach (['xl/worksheets/sheet5.xml', 'xl/worksheets/sheet6.xml'] as $part) {
                $xml = $this->xml($zip->getFromName($part));
                $this->assertEmpty($xml->xpath('//m:row[@r>=3]/m:c/m:v'));
            }
            $xml = $this->xml($zip->getFromName('xl/worksheets/sheet4.xml'));
            $this->assertEmpty($xml->xpath('//m:row[@r>=3]/m:c/m:v'));
            $this->assertCount(9, $this->xml($zip->getFromName('xl/workbook.xml'))->xpath('//m:sheet'));
            $this->assertStringContainsString('fullCalcOnLoad="1"', $zip->getFromName('xl/workbook.xml'));
            $this->assertStringContainsString('SGA SLEP', $zip->getFromName('docProps/core.xml'));
            $persons = $zip->getFromName('xl/persons/person.xml');
            $this->assertStringContainsString('displayName="SGA"', $persons);
            $this->assertStringContainsString('userId="sga@example.invalid"', $persons);
            $this->assertStringContainsString('00000000-0000-4000-8000-', $persons);
        } finally { $zip->close(); }
    }

    private function url(array $params = []): string { return route('admin.dotacion-establecimiento.pal-2027', [$this->ee, ...$params]); }
    private function reporte(array $data): array { return app(DotacionPal2027::class)->construir($this->ee, $data, app(DotacionPal2027Export::class)->tablas()); }
    private function data(): array
    {
        return ['docentes' => [$this->docente()], 'cursos' => ['resumen_cursos_planes' => ['totales' => ['horas' => 38, 'horas_contrato_equivalente' => 44]], 'rows' => ['1B' => ['label' => '1° Básico', 'matricula' => 25,
            'cursos' => 1, 'horas_por_nivel' => 38, 'total_horas_contrato_equivalente' => 44, 'detalles' => [['establecimiento_curso_id' => 1]]]]]];
    }
    private function docente(array $changes = []): array
    {
        return array_replace(['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Sin título declarado',
            'funcion' => 'Docente', 'horas_contrato' => 44, 'horas_planta' => 44, 'horas_contrata' => 0,
            'niveles_declarados' => 'Básica', 'asignaciones' => [$this->asignacion()]], $changes);
    }
    private function asignacion(array $changes = []): DotacionDocenteAsignacion
    {
        $curso = (new EstablecimientoCurso)->setRelation('curso', (new Curso)->forceFill(['codigo' => '1B', 'nivel_educativo' => 'Educación Básica']));
        return (new DotacionDocenteAsignacion)->forceFill(array_replace(['id' => 1, 'docente_rut' => '99000001-K',
            'tipo_asignacion' => 'plan_estudio', 'subvencion' => 'General', 'horas_contrato' => 44,
            'horas_plan_pedagogicas' => 38, 'proporcion_aplicada' => '65/35', 'estado' => 'activa'], $changes))->setRelation('establecimientoCurso', $curso);
    }
    private function zip(string $path): ZipArchive { $zip = new ZipArchive; $this->assertTrue($zip->open($path)); return $zip; }
    private function xml(string $xml): \SimpleXMLElement { $d = simplexml_load_string($xml); $d->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'); return $d; }
    private function formulas(string $xml): array
    {
        $fs = []; foreach ($this->xml($xml)->xpath('//m:c[m:f]') as $cell) { $fs[(string) $cell['r']] = (string) $cell->f; } return $fs;
    }
}
