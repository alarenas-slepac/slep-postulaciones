<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027Export;
use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionPal2027;
use App\Support\DotacionPalParvularia65;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
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

    public function test_saldo_titular_en_otros_conserva_nivel_pendiente_y_art69_expreso(): void
    {
        $doc = $this->docente(['niveles_declarados' => 'Sin declaración', 'asignaciones' => [],
            'exclusion_docente' => ['motivo' => 'horas_catedra_art_69']]);
        $row = $this->reporte(['docentes' => [$doc]])['docentes'][0];
        $this->assertSame('', $row['D']); $this->assertSame('si', $row['I']);
        $this->assertStringContainsString('44 h', $row['S']);
        $this->assertSame(44.0, $row['R']);
        $this->assertSame('Otros (Talleristas / Apoyo / etc.)', $row['G']);
    }

    public static function nivelesPal(): array
    {
        return [
            'EPJA básico 1' => [['codigo' => 'EPJA_BASICA_N1', 'nivel_educativo' => 'EPJA Básica'], 'Educación Básica'],
            'EPJA básico 2' => [['codigo' => 'EPJA_BASICA_N2', 'nivel_educativo' => 'EPJA Básica'], 'Educación Básica'],
            'EPJA básico 3' => [['codigo' => 'EPJA_BASICA_N3', 'nivel_educativo' => 'EPJA Básica'], 'Educación Básica'],
            'EPJA medio 1' => [['codigo' => 'EPJA_MEDIA_N1', 'nivel_educativo' => 'EPJA Media'], 'Educación Media'],
            'EPJA medio 2' => [['codigo' => 'EPJA_MEDIA_N2', 'nivel_educativo' => 'EPJA Media'], 'Educación Media'],
            'EPJA nombre básico histórico' => [['nombre' => '1er Nivel Básico', 'modalidad' => 'EPJA'], 'Educación Básica'],
            'EPJA nombre medio histórico' => [['nombre' => '2° Nivel Medio', 'modalidad' => 'Adultos'], 'Educación Media'],
            'básica regular' => [['codigo' => '8B'], 'Educación Básica'],
            'media HC regular' => [['codigo' => '3M-HC'], 'Educación Media'],
            'media TP regular' => [['codigo' => '4M-TP'], 'Educación Media'],
            'curso laboral' => [['codigo' => 'ESPECIAL_LABORAL_1', 'nivel_educativo' => 'Educación Especial'], 'Educación Básica'],
            'EPJA sin nivel' => [['codigo' => 'EPJA_SIN_DETALLE', 'modalidad' => 'EPJA'], ''],
        ];
    }

    #[DataProvider('nivelesPal')]
    public function test_nivel_pal_segun_curso_asignado_epja_y_ensenanza_regular(array $curso, string $esperado): void
    {
        $asignacion = $this->asignacionCurso($curso);
        $doc = $this->docente(['niveles_declarados' => ['Parvularia'], 'asignaciones' => [$asignacion]]);
        $antes = json_encode($doc);
        $row = $this->reporte(['docentes' => [$doc]])['docentes'][0];
        $this->assertSame($esperado, $row['D']);
        if ($esperado === '') {
            $this->assertNull($row['K']); $this->assertNull($row['L']);
            $this->assertStringContainsString('falta desglose del nivel', $row['S']);
        } else {
            $this->assertSame(38.0, $row[$esperado === 'Educación Media' ? 'L' : 'K']);
            $this->assertNull($row[$esperado === 'Educación Media' ? 'K' : 'L']);
            $this->assertStringNotContainsString('INCOMPATIBLE:', $row['S']);
        }
        $this->assertSame($antes, json_encode($doc));
    }

    public function test_escuela_especial_informa_basica_para_todos_sin_alterar_horas_ni_conversion_parvularia(): void
    {
        $this->ee->especial = true;
        $docs = [
            $this->docente(['asignaciones' => [$this->asignacionCurso(['codigo' => 'ESPECIAL_LABORAL_2'])]]),
            $this->docente(['asignaciones' => [$this->asignacion(['tipo_asignacion' => 'funcion_directiva'])]]),
            $this->docente(['niveles_declarados' => ['Media'], 'asignaciones' => []]),
            $this->docente(['titulo' => 'Pedagogía en Educación de Párvulos',
                'asignaciones' => [$this->asignacionNt(['horas_contrato' => 37, 'horas_plan_pedagogicas' => 25])]]),
        ];
        $rows = $this->reporte(['docentes' => $docs])['docentes'];
        $this->assertCount(5, $rows);
        $this->assertSame(array_fill(0, 5, 'Educación Básica'), array_column($rows, 'D'));
        $this->assertSame(38.0, $rows[0]['K']);
        $this->assertSame(44.0, $rows[1]['R']); $this->assertSame(44.0, $rows[2]['R']);
        $this->assertSame(32.0, $rows[3]['K']); $this->assertSame('65/35', $rows[3]['M']);
        $this->assertSame(7.0, $rows[4]['R']);
    }

    public function test_docente_basica_y_media_usa_opcion_mixta_y_conserva_horas_por_nivel_en_ambos_excel(): void
    {
        $docs = [];
        foreach ([['1B', '4M-TP'], ['EPJA_BASICA_N3', 'EPJA_MEDIA_N2']] as [$basica, $media]) {
            $docs[] = $this->docente(['horas_contrato' => 88, 'horas_planta' => 88, 'niveles_declarados' => ['Parvularia'], 'asignaciones' => [
                $this->asignacionCurso(['codigo' => $basica], ['id' => 1]),
                $this->asignacionCurso(['codigo' => $media], ['id' => 2]),
                $this->asignacionNt(['id' => 3, 'estado' => 'anulada']),
                $this->asignacionNt(['id' => 4, 'horas_contrato' => 0, 'horas_plan_pedagogicas' => 0]),
                $this->asignacionNt(['id' => 5, 'estamento_cobertura' => 'asistente']),
            ]]);
        }
        $report = $this->reporte(['docentes' => $docs]);
        $this->assertCount(4, $report['docentes']);
        $this->assertSame(array_fill(0, 4, 'Ed. Básica/Media'), array_column($report['docentes'], 'D'));
        $this->assertSame([38.0, null, 38.0, null], array_column($report['docentes'], 'K'));
        $this->assertSame([null, 38.0, null, 38.0], array_column($report['docentes'], 'L'));
        $this->assertSame(0, $report['incompatibles']);
        foreach ([false, true] as $global) {
            $path = Storage::disk('local')->path($global ? 'niveles-todos.xlsx' : 'niveles.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)', 'Tabla 65-35'])->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)');
                foreach ([3, 4, 5, 6] as $r) {
                    $this->assertSame('Ed. Básica/Media', (string) $sheet->getCell('D'.$r)->getValue());
                    $this->assertEquals(44, $sheet->getCell('J'.$r)->getCalculatedValue());
                }
                $this->assertEquals(38, $sheet->getCell('K3')->getValue());
                $this->assertEquals(38, $sheet->getCell('L4')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_funciones_y_pie_sin_curso_no_cambian_el_nivel_de_las_clases(): void
    {
        $pie = $this->asignacion(['id' => 2, 'tipo_asignacion' => 'pie_educadora_diferencial', 'subvencion' => 'PIE',
            'horas_contrato' => 3, 'horas_plan_pedagogicas' => 0])->setRelation('establecimientoCurso', null);
        $funcion = $this->asignacionCurso(['codigo' => '4M-TP'], ['id' => 3, 'tipo_asignacion' => 'funcion_directiva', 'horas_contrato' => 2]);
        $doc = $this->docente(['horas_contrato' => 49, 'horas_planta' => 49, 'niveles_declarados' => ['Media'],
            'asignaciones' => [$this->asignacion(), $pie, $funcion]]);
        $rows = $this->reporte(['docentes' => [$doc]])['docentes'];
        $this->assertSame(array_fill(0, 2, 'Educación Básica'), array_column($rows, 'D'));
        $this->assertSame(38.0, $rows[0]['K']);
        $this->assertSame(3.0, $rows[0]['P']); $this->assertSame(2.0, $rows[1]['R']);
    }

    public function test_parvularia_y_basica_conservan_sus_opciones_y_la_conversion_sin_categoria_inventada(): void
    {
        $doc = $this->docente(['titulo' => 'Pedagogía en Educación de Párvulos', 'horas_contrato' => 81, 'horas_planta' => 81,
            'asignaciones' => [$this->asignacionNt(['id' => 1, 'horas_contrato' => 37, 'horas_plan_pedagogicas' => 25]), $this->asignacion(['id' => 2])]]);
        $rows = $this->reporte(['docentes' => [$doc]])['docentes'];
        $this->assertSame(['Educación Parvularia', 'Educación Básica'], array_column($rows, 'D'));
        $this->assertSame([32.0, 38.0], array_column($rows, 'K'));
        $this->assertEquals(81, $this->contratoRepresentado($rows));
    }

    public function test_consolida_basica_media_y_pie_de_igual_funcion_y_calidad_en_ambos_excel(): void
    {
        $doc = $this->docente(['horas_contrato' => 47, 'horas_planta' => 47, 'asignaciones' => [
            $this->asignacionCurso(['codigo' => '1B'], ['id' => 1, 'horas_contrato' => 22, 'horas_plan_pedagogicas' => 19]),
            $this->asignacionCurso(['codigo' => '1M'], ['id' => 2, 'horas_contrato' => 22, 'horas_plan_pedagogicas' => 19]),
            $this->asignacion(['id' => 3, 'tipo_asignacion' => 'pie_colaborativo', 'subvencion' => 'PIE', 'horas_contrato' => 3, 'horas_plan_pedagogicas' => 0]),
        ]]);
        $antes = json_encode($doc);
        $report = $this->reporte(['docentes' => [$doc]]);
        $this->assertCount(1, $report['docentes']); $this->assertSame(1, $report['personas']);
        $row = $report['docentes'][0];
        $this->assertSame('Ed. Básica/Media', $row['D']); $this->assertSame('Docente aula', $row['G']);
        $this->assertSame(19.0, $row['K']); $this->assertSame(19.0, $row['L']); $this->assertSame(3.0, $row['P']);
        $this->assertStringContainsString('3 filas de origen', $row['S']);
        $this->assertSame($antes, json_encode($doc));
        foreach ([false, true] as $global) {
            $path = Storage::disk('local')->path($global ? 'consolidado-filas-todos.xlsx' : 'consolidado-filas.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)', 'Tabla 65-35', 'Tabla 60-40'])->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)');
                $this->assertNull($sheet->getCell('A4')->getValue());
                $this->assertEquals(19, $sheet->getCell('K3')->getValue()); $this->assertEquals(19, $sheet->getCell('L3')->getValue());
                $this->assertEquals(28.5, $sheet->getCell('N3')->getCalculatedValue());
                $this->assertEquals(15.5, $sheet->getCell('O3')->getCalculatedValue());
                $this->assertEquals(47, $sheet->getCell('J3')->getCalculatedValue());
                $this->assertStringStartsWith('=', $sheet->getCell('J3')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_proporciones_distintas_permanecen_en_filas_separadas_aunque_coincidan_funcion_y_calidad(): void
    {
        $contrato60 = app(DotacionPal2027Export::class)->tablas()['60/40']['19.0000']['contrato'];
        $doc = $this->docente(['horas_contrato' => 22 + $contrato60, 'horas_planta' => 22 + $contrato60, 'asignaciones' => [
            $this->asignacion(['id' => 1, 'horas_contrato' => 22, 'horas_plan_pedagogicas' => 19]),
            $this->asignacion(['id' => 2, 'horas_contrato' => $contrato60, 'horas_plan_pedagogicas' => 19, 'proporcion_aplicada' => '60/40']),
        ]]);
        $rows = $this->reporte(['docentes' => [$doc]])['docentes'];
        $this->assertCount(2, $rows); $this->assertSame(['65/35', '60/40'], array_column($rows, 'M'));
        foreach ($rows as $row) {
            $this->assertStringContainsString('se conservan filas separadas', $row['S']);
            $this->assertStringNotContainsString('INCOMPATIBLE:', $row['S']);
        }
    }

    public function test_basica_y_media_6040_separadas_conservan_formula_y_contrato_en_ambos_excel(): void
    {
        $doc = $this->docente(['horas_contrato' => 23, 'horas_planta' => 23, 'asignaciones' => [
            $this->asignacionCurso(['codigo' => '1B'], ['id' => 1, 'proporcion_aplicada' => '60/40', 'horas_plan_pedagogicas' => 8, 'horas_contrato' => 10]),
            $this->asignacionCurso(['codigo' => '1M'], ['id' => 2, 'proporcion_aplicada' => '60/40', 'horas_plan_pedagogicas' => 8, 'horas_contrato' => 10]),
            $this->asignacion(['id' => 3, 'tipo_asignacion' => 'pie_colaborativo', 'subvencion' => 'PIE', 'horas_contrato' => 3, 'horas_plan_pedagogicas' => 0]),
        ]]);
        $report = $this->reporte(['docentes' => [$doc]]);
        $this->assertCount(3, $report['docentes']);
        $this->assertSame(['60/40', '60/40', null], array_column($report['docentes'], 'M'));
        $this->assertSame(0, $report['incompatibles']); $this->assertSame(1, $report['personas']);
        foreach ([false, true] as $global) {
            $path = Storage::disk('local')->path($global ? 'excepcion-6040-todos.xlsx' : 'excepcion-6040.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)', 'Tabla 65-35', 'Tabla 60-40'])->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)');
                $this->assertEquals(8, $sheet->getCell('K3')->getValue()); $this->assertEquals(8, $sheet->getCell('L4')->getValue());
                foreach ([3, 4] as $r) {
                    $this->assertSame('60/40', (string) $sheet->getCell('M'.$r)->getValue());
                    $this->assertEquals(10, $sheet->getCell('J'.$r)->getCalculatedValue());
                    $this->assertEquals(6, $sheet->getCell('N'.$r)->getCalculatedValue());
                    $this->assertEquals(4, $sheet->getCell('O'.$r)->getCalculatedValue());
                    $this->assertStringContainsString('60/40 queda excluida', (string) $sheet->getCell('S'.$r)->getValue());
                    $this->assertStringStartsWith('=', $sheet->getCell('J'.$r)->getValue());
                }
                $this->assertEquals(3, $sheet->getCell('J5')->getCalculatedValue());
                $this->assertNull($sheet->getCell('A6')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_situaciones_2027_incorporan_excluidas_en_funcion_otros_con_observaciones(): void
    {
        foreach (\App\Models\DotacionDocenteExclusion::MOTIVOS as $motivo => $label) {
            if ($motivo === 'proceso_bir') { continue; }
            $doc = $this->docente(['horas_contrato_base' => 44, 'horas_contrato' => 20, 'horas_excluidas' => 24,
                'exclusion_docente' => ['motivo' => $motivo, 'motivo_label' => $label, 'horas' => 24],
                'asignaciones' => [$this->asignacion(['tipo_asignacion' => 'funcion_directiva', 'horas_contrato' => 20])]]);
            $antes = json_encode($doc);
            $rows = $this->reporte(['docentes' => [$doc]])['docentes'];
            $this->assertCount(2, $rows);
            $this->assertSame(20.0, $rows[0]['R']);
            $this->assertSame(24.0, $rows[1]['R']);
            $this->assertSame('Otros (Talleristas / Apoyo / etc.)', $rows[1]['G']);
            $this->assertStringContainsString('24 h por situación 2027: '.$label, $rows[1]['S']);
            $this->assertNull($rows[0]['K']); $this->assertNull($rows[0]['P']);
            $this->assertStringContainsString('Situación 2027: '.$label, $rows[0]['S']);
            $this->assertStringContainsString('Contrato completo del docente: 44 h', $rows[0]['S']);
            $this->assertStringContainsString('Horas consideradas para la situación contractual: 20 h', $rows[0]['S']);
            $this->assertStringContainsString('Horas no necesarias por la situación: 24 h', $rows[0]['S']);
            $this->assertSame($antes, json_encode($doc));
        }
        // Los DTO históricos conservan la base como suma de consideradas y excluidas.
        unset($doc['horas_contrato_base'], $doc['horas_excluidas']);
        $this->assertStringContainsString('Contrato completo del docente: 44 h', $this->reporte(['docentes' => [$doc]])['docentes'][0]['S']);
        $doc['horas_contrato'] = 0; $doc['exclusion_docente']['horas'] = 44; $doc['asignaciones'] = [];
        $row = $this->reporte(['docentes' => [$doc]])['docentes'][0];
        $this->assertStringContainsString('Contrato completo del docente: 44 h', $row['S']);
        $this->assertStringContainsString('situación contractual: 0 h', $row['S']);
        $this->assertSame(44.0, $row['R']);
    }

    public function test_bir_2027_se_excluye_de_personas_y_excel_individual_y_consolidado(): void
    {
        $normal = $this->docente(['rut' => '99000002-8', 'horas_contrato_base' => 44, 'horas_contrato' => 20,
            'exclusion_docente' => ['motivo' => 'horas_gremiales', 'horas' => 24],
            'asignaciones' => [$this->asignacion(['tipo_asignacion' => 'funcion_directiva', 'horas_contrato' => 20])]]);
        $bir = $this->docente(['exclusion_docente' => ['motivo' => 'proceso_bir', 'horas_traspaso_bir' => 12]]);
        $report = $this->reporte(['docentes' => [$bir, $normal]]);
        $this->assertSame(1, $report['personas']);
        $this->assertSame(['99000002-8', '99000002-8'], array_column($report['docentes'], 'A'));
        $this->assertStringContainsString('1 docente(s) con situación Proceso BIR 2027', implode(' ', $report['advertencias']));
        foreach ([false, true] as $global) {
            $path = Storage::disk('local')->path($global ? 'situaciones-todos.xlsx' : 'situaciones.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $book = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Funcionario (DOCENTE)'])->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)');
                $this->assertSame('99000002-8', (string) $sheet->getCell('A3')->getValue());
                $this->assertSame('Otros (Talleristas / Apoyo / etc.)', (string) $sheet->getCell('G4')->getValue());
                $this->assertEquals(24, $sheet->getCell('J4')->getCalculatedValue());
                $this->assertNull($sheet->getCell('A5')->getValue());
                $this->assertStringContainsString('Contrato completo del docente: 44 h', (string) $sheet->getCell('S3')->getValue());
                $this->assertEquals(20, $sheet->getCell('J3')->getCalculatedValue());
            } finally { $book->disconnectWorksheets(); }
        }
        $soloBir = $this->reporte(['docentes' => [$bir]]);
        $this->assertSame(0, $soloBir['personas']); $this->assertSame([], $soloBir['docentes']);
    }

    public function test_parvularia_conserva_37_contrato_3_pie_e_incorpora_4_titulares_sin_asignar_en_otros(): void
    {
        $aula = $this->asignacionNt(['horas_contrato' => 37, 'horas_plan_pedagogicas' => 25]);
        $pie = $this->asignacionNt(['id' => 2, 'tipo_asignacion' => 'pie_colaborativo', 'subvencion' => 'PIE', 'horas_contrato' => 3, 'horas_plan_pedagogicas' => 0]);
        $antes = $aula->getAttributes();
        $report = $this->reporte(['docentes' => [$this->docente(['titulo' => 'Pedagogía en Educación de Párvulos', 'asignaciones' => [$aula, $pie]])]]);
        $this->assertCount(2, $report['docentes']);
        $row = $report['docentes'][0];
        $this->assertSame(32.0, $row['K']); $this->assertSame('65/35', $row['M']);
        $this->assertSame('Educación Parvularia', $row['D']); $this->assertSame('Educadora de Parvulos', $row['E']);
        $this->assertStringContainsString('25 h aula real y 37 h contrato', $row['S']);
        $this->assertStringContainsString('4 h', $row['S']);
        $this->assertStringNotContainsString('INCOMPATIBLE:', $row['S']);
        $this->assertEquals(3, $row['P']);
        $this->assertSame(4.0, $report['docentes'][1]['R']);
        $this->assertSame('Otros (Talleristas / Apoyo / etc.)', $report['docentes'][1]['G']);
        $this->assertSame(0, $report['incompatibles']); $this->assertSame($antes, $aula->getAttributes());
        foreach ([false, true] as $consolidado) {
            $path = Storage::disk('local')->path($consolidado ? 'parv-global.xlsx' : 'parv.xlsx');
            if ($consolidado) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $reader = IOFactory::createReader('Xlsx');
            $reader->setLoadSheetsOnly(['Info Funcionario (DOCENTE)','Tabla 65-35','Tabla 60-40']);
            $reader->setReadFilter(new class implements IReadFilter {
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool { return $row <= 94; }
            });
            $book = $reader->load($path);
            try {
                $sheet = $book->getSheetByName('Info Funcionario (DOCENTE)');
                $this->assertEquals(40, $sheet->getCell('J3')->getCalculatedValue());
                $this->assertEquals(24, $sheet->getCell('N3')->getCalculatedValue());
                $this->assertEquals(13, $sheet->getCell('O3')->getCalculatedValue());
                $this->assertEquals(4, $sheet->getCell('J4')->getCalculatedValue());
                $this->assertEquals(44, $sheet->getCell('J3')->getCalculatedValue() + $sheet->getCell('J4')->getCalculatedValue());
                $this->assertStringStartsWith('=', $sheet->getCell('J3')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public static function contratosParvularia(): array
    {
        return [[31, 27, 'NT Sin JEC · base contractual 31 h'], [35, 30, 'NT Sin JEC · base contractual 35 h'],
            [37, 32, 'NT Con JEC · base contractual 55 h'], [44, 38, 'NT JEC · CPEIP 65/35']];
    }

    #[DataProvider('contratosParvularia')]
    public function test_inversion_65_35_conserva_contrato_para_titulo_y_aula_parvularia(int $contrato, int $aula, string $regla): void
    {
        $doc = $this->docente(['titulo' => 'PEDAGOGIA EN EDUCACION DE PARVULOS', 'horas_contrato' => $contrato, 'horas_planta' => $contrato,
            'asignaciones' => [$this->asignacionNt(['horas_contrato' => $contrato, 'horas_plan_pedagogicas' => 20, 'proporcion_aplicada' => $regla])]]);
        $row = $this->reporte(['docentes' => [$doc]])['docentes'][0];
        $this->assertEquals($aula, $row['K']); $this->assertSame('65/35', $row['M']);
        $this->assertStringNotContainsString('INCOMPATIBLE:', $row['S']);
        $this->assertEquals($contrato, app(DotacionPal2027Export::class)->tablas()['65/35'][number_format($aula, 4, '.', '')]['contrato']);
    }

    public function test_reparte_equivalencia_entre_titular_y_contrata_y_admite_mas_de_44_h_sin_duplicar(): void
    {
        $doc = $this->docente(['titulo' => 'Pedagogía en Educación de Párvulos', 'horas_planta' => 20, 'horas_contrata' => 24,
            'asignaciones' => [$this->asignacionNt(['horas_contrato' => 44, 'horas_plan_pedagogicas' => 30.4])]]);
        $rows = $this->reporte(['docentes' => [$doc]])['docentes'];
        $this->assertSame(['Titular','Plazo Fijo'], array_column($rows, 'H'));
        $this->assertEquals(44, $this->contratoRepresentado($rows));
        $doc['horas_contrato'] = $doc['horas_planta'] = 55; $doc['horas_contrata'] = 0;
        $doc['asignaciones'] = [$this->asignacionNt(['horas_contrato' => 55, 'horas_plan_pedagogicas' => 38])];
        $rows = $this->reporte(['docentes' => [$doc]])['docentes'];
        $this->assertCount(2, $rows); $this->assertEquals(55, $this->contratoRepresentado($rows));
        foreach ($rows as $row) { $this->assertStringContainsString('de 2', $row['S']); }
    }

    public function test_suma_reglas_especiales_de_nt1_y_nt2_antes_de_convertir_y_conserva_referencia(): void
    {
        $doc = $this->docente(['titulo' => 'Pedagogía en Educación de Párvulos', 'asignaciones' => [
            $this->asignacionNt(['id' => 1, 'horas_contrato' => 12.5, 'horas_plan_pedagogicas' => 8, 'proporcion_aplicada' => 'NT Sin JEC · base contractual 31 h']),
            $this->asignacionNt(['id' => 2, 'horas_contrato' => 24.5, 'horas_plan_pedagogicas' => 17, 'proporcion_aplicada' => 'NT Sin JEC · base contractual 35 h'])]]);
        $rows = $this->reporte(['docentes' => [$doc]])['docentes'];
        $this->assertCount(2, $rows); $this->assertSame(32.0, $rows[0]['K']);
        $this->assertStringContainsString('25 h aula real y 37 h contrato', $rows[0]['S']);
        $this->assertStringContainsString('contractual 31 h / NT Sin JEC', $rows[0]['S']);
    }

    public function test_conversion_exige_titulo_nivel_financiamiento_y_regla_y_no_oculta_excesos(): void
    {
        $a = $this->asignacionNt(['horas_contrato' => 37, 'horas_plan_pedagogicas' => 25]);
        $doc = $this->docente(['titulo' => 'Pedagogía en Educación de Párvulos', 'asignaciones' => [$a]]);
        foreach (['Sin título declarado','Pedagogía en Inglés'] as $titulo) {
            $row = $this->reporte(['docentes' => [array_replace($doc, ['titulo' => $titulo])]])['docentes'][0];
            $this->assertNull($row['K']); $this->assertStringContainsString('INCOMPATIBLE:', $row['S']);
        }
        $a->setRelation('establecimientoCurso', $this->asignacion()->establecimientoCurso);
        $this->assertNull($this->reporte(['docentes' => [$doc]])['docentes'][0]['K']);
        $doc['asignaciones'] = [$this->asignacionNt(['subvencion' => 'PIE', 'horas_contrato' => 37])];
        $row = $this->reporte(['docentes' => [$doc]])['docentes'][0];
        $this->assertNull($row['K']); $this->assertEquals(37, $row['P']);
        $doc['asignaciones'] = [$this->asignacionNt(['tipo_asignacion' => 'funcion_directiva', 'horas_contrato' => 37])];
        $row = $this->reporte(['docentes' => [$doc]])['docentes'][0];
        $this->assertNull($row['K']); $this->assertEquals(37, $row['R']);
        $doc['asignaciones'] = [$this->asignacionNt(['proporcion_aplicada' => 'Otra regla especial', 'horas_contrato' => 37])];
        $this->assertNull($this->reporte(['docentes' => [$doc]])['docentes'][0]['K']);
        $doc['asignaciones'] = [$this->asignacionNt(['horas_contrato' => 55, 'horas_plan_pedagogicas' => 38])];
        $rows = $this->reporte(['docentes' => [$doc]])['docentes'];
        $this->assertSame(38.0, $rows[0]['K']);
        $this->assertStringContainsString('exceden el contrato', $rows[1]['S']); $this->assertNull($rows[1]['K']);
    }

    public function test_todas_las_horas_enteras_hasta_132_tienen_equivalencia_exacta_y_no_se_redondean_fracciones(): void
    {
        $tabla = app(DotacionPal2027Export::class)->tablas()['65/35'];
        for ($horas = 1; $horas <= 132; $horas++) {
            $bloques = DotacionPalParvularia65::convertir($horas, $tabla);
            $this->assertNotNull($bloques); $this->assertEquals($horas, array_sum(array_column($bloques, 'contrato')));
            foreach ($bloques as $bloque) {
                $this->assertGreaterThan(0, $bloque['aula']);
                $this->assertEquals($bloque['contrato'], $tabla[number_format($bloque['aula'], 4, '.', '')]['contrato']);
            }
        }
        foreach ([0, -1, 0.5, 37.5, INF, NAN, 10001] as $valor) { $this->assertNull(DotacionPalParvularia65::convertir($valor, $tabla)); }
        $this->assertNull(DotacionPalParvularia65::convertir(37, []));
    }

    public function test_planes_mixtos_y_combinados_no_simulan_una_carga_uniforme(): void
    {
        $data = $this->data();
        $segundo = $data['cursos']['rows']['1B'];
        $segundo['label'] = '2° Básico';
        $segundo['detalles'][0] = array_replace($segundo['detalles'][0], ['establecimiento_curso_id' => 2,
            'nombre_seccion' => '2° Básico A', 'horas_plan_base' => 40, 'horas_contrato_base' => 46]);
        $data['cursos']['rows']['2B'] = $segundo;
        $data['cursos_combinados']['grupos'] = [['activo' => true, 'nombre' => 'Multigrado A & B',
            'miembros' => [['id' => 1, 'label' => '1° Básico A', 'matricula' => 25], ['id' => 2, 'label' => '2° Básico A', 'matricula' => 25]],
            'totales' => ['horas_requeridas' => 46, 'horas_contrato' => 54, 'asignadas' => 9, 'pendientes' => 37]]];
        $report = $this->reporte($data);
        $this->assertCount(1, $report['cursos']);
        $row = $report['cursos'][0];
        $this->assertSame(46.0, $row['F']); $this->assertSame(54.0, $row['H']);
        $this->assertSame(50, $row['D']); $this->assertSame(1, $row['E']);
        $this->assertStringContainsString('Multigrado A & B', $row['C']);
        $this->assertStringContainsString('1° Básico A + 2° Básico A', $row['C']);
        $this->assertStringNotContainsString('INCOMPATIBLE', $row['C']);
        foreach ([false, true] as $global) {
            $path = Storage::disk('local')->path($global ? 'combinados-todos.xlsx' : 'combinados.xlsx');
            if ($global) { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$report], $path); }
            else { app(DotacionPal2027Export::class)->guardar($report, $path); }
            $reader = IOFactory::createReader('Xlsx')->setLoadSheetsOnly(['Info Establecimiento']);
            $book = $reader->load($path);
            try {
                $sheet = $book->getSheetByName('Info Establecimiento');
                $this->assertSame($row['C'], (string) $sheet->getCell('C4')->getValue());
                $this->assertEquals(46, $sheet->getCell('G4')->getCalculatedValue());
                $this->assertEquals(54, $sheet->getCell('H4')->getValue());
                $this->assertSame('=+E4*F4', $sheet->getCell('G4')->getValue());
            } finally { $book->disconnectWorksheets(); }
        }
    }

    public function test_cursos_con_planes_distintos_tienen_sus_propias_horas_sin_promediar_y_sin_asignadas(): void
    {
        $data = $this->data();
        $row = &$data['cursos']['rows']['1B'];
        $row['cursos'] = 2; $row['matricula'] = 45;
        $row['detalles'][0] += ['horas' => 42, 'horas_plan_refuerzo_ld_otro_docente' => 4,
            'horas_contrato_equivalente_redondeado' => 49, 'horas_contrato_refuerzo_ld_otro_docente' => 5, 'horas_asignadas' => 6];
        $row['detalles'][] = array_replace($row['detalles'][0], ['establecimiento_curso_id' => 2, 'nombre_seccion' => '1° Básico B',
            'matricula' => 20, 'horas_plan_base' => 40, 'horas_contrato_base' => 46, 'horas' => 44,
            'horas_contrato_equivalente_redondeado' => 51, 'horas_asignadas' => 0]);
        unset($row);
        $rows = $this->reporte($data)['cursos'];
        $this->assertCount(2, $rows);
        $this->assertSame([38.0,40.0], array_column($rows, 'F'));
        $this->assertSame([44.0,46.0], array_column($rows, 'H'));
        $this->assertSame([25,20], array_column($rows, 'D'));
        $this->assertSame([1,1], array_column($rows, 'E'));
        $this->assertSame(['1° Básico A','1° Básico B'], array_column($rows, 'C'));
        // Datos históricos del DTO: quitar las claves base conserva el plan
        // restando el refuerzo contabilizado, sin usar las horas asignadas.
        unset($data['cursos']['rows']['1B']['detalles'][0]['horas_plan_base'], $data['cursos']['rows']['1B']['detalles'][0]['horas_contrato_base']);
        $this->assertSame(38.0, $this->reporte($data)['cursos'][0]['F']);
        $this->assertSame(44.0, $this->reporte($data)['cursos'][0]['H']);
    }

    public function test_combina_una_vez_por_grupo_conserva_cursos_independientes_e_ignora_grupos_inactivos(): void
    {
        $data = $this->data();
        $base = $data['cursos']['rows']['1B'];
        for ($i = 1; $i <= 4; $i++) {
            $nivel = $base; $nivel['label'] = $i.'° Básico';
            $nivel['detalles'][0]['establecimiento_curso_id'] = $i;
            $nivel['detalles'][0]['nombre_seccion'] = $i.'° Básico A';
            $data['cursos']['rows'][$i.'B'] = $nivel;
        }
        $data['cursos']['rows']['1B']['cursos'] = 2;
        $data['cursos']['rows']['1B']['detalles'][] = array_replace($base['detalles'][0], ['establecimiento_curso_id' => 5, 'nombre_seccion' => '1° Básico B']);
        $data['cursos_combinados']['grupos'] = [
            ['nombre' => 'Grupo compartido', 'activo' => true, 'miembros' => [['id'=>1], ['id'=>2]], 'totales' => ['horas_requeridas'=>38, 'horas_contrato'=>44]],
            ['nombre' => 'Grupo inactivo', 'activo' => false, 'miembros' => [['id'=>3], ['id'=>4]], 'totales' => ['horas_requeridas'=>60, 'horas_contrato'=>70]],
        ];
        $rows = $this->reporte($data)['cursos'];
        $this->assertCount(4, $rows);
        $this->assertSame('1° Básico B', $rows[0]['C']);
        $this->assertSame(['3° Básico','4° Básico'], [$rows[1]['C'],$rows[2]['C']]);
        $this->assertStringContainsString('1° Básico A + 2° Básico A', $rows[3]['C']);
        $this->assertStringNotContainsString('Grupo inactivo', implode(' ', array_column($rows, 'C')));
        $this->assertEquals(125, array_sum(array_column($rows, 'D')));
        $this->assertEquals(152, array_sum(array_map(fn ($r) => $r['E'] * $r['F'], $rows)));
    }

    public function test_combinacion_sin_plan_queda_pendiente_y_doble_pertenencia_no_duplica_horas(): void
    {
        $data = $this->data();
        $data['cursos_combinados']['grupos'] = [['nombre'=>'Grupo pendiente', 'activo'=>true, 'miembros'=>[['id'=>1]]]];
        $row = $this->reporte($data)['cursos'][0];
        $this->assertStringContainsString('Curso combinado', $row['C']);
        $this->assertStringContainsString('plan pendiente', $row['C']);
        $this->assertStringNotContainsString('INCOMPATIBLE', $row['C']);
        $this->assertNull($row['F']); $this->assertNull($row['H']);
        $data['cursos_combinados']['grupos'][] = ['nombre'=>'Segundo grupo', 'activo'=>true, 'miembros'=>[['id'=>1]]];
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->reporte($data);
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
            'cursos' => 1, 'horas_por_nivel' => 38, 'total_horas_contrato_equivalente' => 44, 'detalles' => [['establecimiento_curso_id' => 1,
                'nombre_seccion' => '1° Básico A', 'matricula' => 25, 'horas_plan_base' => 38, 'horas_contrato_base' => 44]]]]]];
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
    private function asignacionCurso(array $catalogo, array $changes = []): DotacionDocenteAsignacion
    {
        return $this->asignacion($changes)->setRelation('establecimientoCurso', (new EstablecimientoCurso)
            ->setRelation('curso', (new Curso)->forceFill($catalogo)));
    }
    private function asignacionNt(array $changes = []): DotacionDocenteAsignacion
    {
        $curso = (new EstablecimientoCurso)->setRelation('curso', (new Curso)->forceFill(['codigo' => 'NT1', 'nivel_educativo' => 'Educación Parvularia']));
        return $this->asignacion(['proporcion_aplicada' => 'NT Con JEC · base contractual 55 h', ...$changes])->setRelation('establecimientoCurso', $curso);
    }
    private function contratoRepresentado(array $rows): float
    {
        $tabla = app(DotacionPal2027Export::class)->tablas()['65/35'];
        return (float) array_sum(array_map(fn ($r) => $r['K'] === null ? 0 : $tabla[number_format($r['K'], 4, '.', '')]['contrato'], $rows));
    }
    private function zip(string $path): ZipArchive { $zip = new ZipArchive; $this->assertTrue($zip->open($path)); return $zip; }
    private function xml(string $xml): \SimpleXMLElement { $d = simplexml_load_string($xml); $d->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'); return $d; }
    private function formulas(string $xml): array
    {
        $fs = []; foreach ($this->xml($xml)->xpath('//m:c[m:f]') as $cell) { $fs[(string) $cell['r']] = (string) $cell->f; } return $fs;
    }
}
