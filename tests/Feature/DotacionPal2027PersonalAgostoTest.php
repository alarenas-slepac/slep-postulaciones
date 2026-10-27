<?php

namespace Tests\Feature;

use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Exports\DotacionPal2027Export;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Support\DotacionPal2027;
use App\Support\DotacionPal2027Consolidado;
use App\Support\DotacionPalPersonal2027;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;
use ZipArchive;

class DotacionPal2027PersonalAgostoTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;
    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id(); $t->unsignedInteger('cod_estab')->unique(); $t->unsignedInteger('rbd')->unique();
            $t->string('nombre_establecimiento'); $t->string('comuna')->nullable(); $t->boolean('sala_cuna')->nullable();
            foreach (['pre_escolar', 'basica', 'media'] as $flag) { $t->boolean($flag)->default(false); }
            $t->timestamps();
        });
        (require database_path('migrations/2026_01_29_000001_create_reemplazos_personal_table.php'))->up();
        Schema::table('reemplazos_personal', fn (Blueprint $t) => $t->boolean('vigente')->default(true));
        Schema::create('modules', function (Blueprint $t): void { $t->id(); $t->string('key'); });
        $this->ee = $this->ee(99000, ['basica' => true]);
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
        $this->withoutVite();
        Storage::disk('local')->put('pal-personal-views/layouts/app.blade.php', '<html><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('pal-personal-views'));
    }

    public function test_agrupa_financiamientos_y_rut_equivalentes_sin_repetir_lineas_ni_sumar_pie_sep_dos_veces(): void
    {
        $this->persona(['jornada' => 36]);
        $this->persona(['rut' => '99.000.001-k', 'jornada' => 4, 'financiamiento' => 'PIE']);
        $this->persona(['rut' => '99000001k', 'jornada' => 4, 'financiamiento' => 'SEP']);
        $this->persona(['rut' => '99.000.001-K', 'jornada' => 36]);
        $before = DB::table('reemplazos_personal')->get()->toJson();
        $reporte = $this->personal();
        $this->assertCount(1, $reporte['asistentes']); $this->assertEmpty($reporte['vtf']);
        $fila = $reporte['asistentes'][0];
        $this->assertSame('99000001-K', $fila['A']); $this->assertSame('99000', $fila['C']);
        $this->assertSame('Educación Básica', $fila['D']); $this->assertSame('Técnicos', $fila['G']);
        $this->assertSame('Indefinido', $fila['H']); $this->assertSame(44, $fila['I']);
        $this->assertNull($fila['J']); $this->assertSame(4, $fila['K']); $this->assertSame(4, $fila['L']);
        $this->assertStringContainsString('08/2026', $fila['M']);
        $this->assertStringContainsString('Persona sintética', $fila['M']);
        $this->assertSame($before, DB::table('reemplazos_personal')->get()->toJson());
    }

    public static function contratosExcluidos(): array
    {
        return [['REEMPLAZO'], ['reemplazo parcial'], ['SUPLENCIA'], ['Suplente'], ['PLAZO FIJO SUPLENCIA']];
    }

    #[DataProvider('contratosExcluidos')]
    public function test_excluye_reemplazos_y_suplencias_en_ambas_hojas(string $contrato): void
    {
        $this->persona(['tipocontrato' => $contrato]);
        $this->persona(['tipocontrato' => $contrato, 'financiamiento' => 'JUNJI']);
        $this->assertSame(['asistentes' => [], 'vtf' => []], $this->personal());
    }

    public function test_usa_solo_agosto_2026_aunque_se_hayan_cargado_meses_posteriores(): void
    {
        $historia = $this->persona(['jornada' => 22]);
        DB::table('reemplazos_personal')->where('id', $historia->id)->update(['vigente' => false]);
        foreach ([['mes' => 7], ['mes' => 9], ['anio' => 2027], ['anio' => 2025], ['estatuto' => 'DOCENTE'], ['estatuto' => 'ADMIN CENTRAL']] as $extra) {
            $this->persona($extra + ['jornada' => 44]);
        }
        $fila = $this->personal()['asistentes'];
        $this->assertCount(1, $fila); $this->assertSame(22, $fila[0]['I']);
    }

    public function test_junji_se_identifica_por_financiamiento_estatuto_o_jardin_sin_duplicarse_en_ae(): void
    {
        $this->persona(['estatuto' => ' aaee ', 'financiamiento' => ' junji ']);
        $this->persona(['rut' => '99000002-K', 'estatuto' => 'junji', 'financiamiento' => 'GENERAL']);
        $jardin = $this->ee(99001, ['sala_cuna' => true]);
        $this->persona(['rbd' => 99001, 'establecimiento_id' => null]);
        $this->assertEmpty($this->personal()['asistentes']);
        $this->assertCount(2, $this->personal()['vtf']);
        $filas = app(DotacionPalPersonal2027::class)->construir($jardin);
        $this->assertEmpty($filas['asistentes']); $this->assertCount(1, $filas['vtf']);
    }

    public static function cargosVtf(): array
    {
        return [
            ['DIRECTORA SC Y JARDIN', 'DIRECTOR/A', 'Función Directiva', 'Educadora de Parvulos'],
            ['DIRECTORA Y EDUCADORA DE PARVULOS', 'DIRECTOR/A', 'Función Directiva', 'Educadora de Parvulos'],
            ['ENCARGADA Y EDUCADORA', 'DIRECTOR/A', 'Función Directiva', 'Educadora de Parvulos'],
            ['EDUCADORA DE PARVULOS', 'EDUCADOR/A', 'Educadora de Párvulos', 'Educadora de Parvulos'],
            ['TECNICO', 'TÉCNICO', 'Técnico de Educación Parvularia', 'Asistente de la Educación'],
            ['AUXILIAR', 'AUXILIAR', 'Otros asistentes de la educación', 'Asistente de la Educación'],
            ['ADMINISTRATIVO', 'ADMINISTRATIVO', 'Otros asistentes de la educación', 'Asistente de la Educación'],
            ['FONOAUDIOLOGA(O)', 'PROFESIONAL', 'Otros asistentes de la educación', 'Asistente de la Educación'],
            ['TERAPEUTA OCUPACIONAL', 'PROFESIONAL', 'Otros asistentes de la educación', 'Asistente de la Educación'],
            ['PROFESIONAL', 'PROFESIONAL', 'Otros asistentes de la educación', 'Asistente de la Educación'],
        ];
    }

    #[DataProvider('cargosVtf')]
    public function test_mapea_cargos_junji_a_las_opciones_nativas_sin_perder_la_descripcion(string $origen, ?string $cargo, string $funcion, string $estamento): void
    {
        $this->persona(['financiamiento' => 'JUNJI', 'escalafon' => $origen]);
        $fila = $this->personal()['vtf'][0];
        $this->assertSame($cargo, $fila['E']); $this->assertSame($funcion, $fila['F']); $this->assertSame($estamento, $fila['D']);
        $this->assertSame(44, $fila['H']); $this->assertNull($fila['I']);
        $this->assertStringContainsString($origen, $fila['J']);
    }

    public function test_separa_calidades_cargos_y_establecimientos_y_no_inventa_datos_lectivos(): void
    {
        $this->persona(['tipocontrato' => 'INDEFINIDO PIE', 'escalafon' => 'PROFESIONAL', 'jornada' => 20]);
        $this->persona(['tipocontrato' => 'PLAZO FIJO', 'escalafon' => 'AUXILIAR', 'jornada' => 4]);
        $this->persona(['tipocontrato' => 'OTRO', 'escalafon' => 'SIN CLASIFICAR', 'jornada' => null, 'jornada_basica' => 10, 'jornada_media' => 2]);
        $otro = $this->ee(99002);
        $this->persona(['rbd' => 99002, 'establecimiento_id' => null, 'jornada' => 6]);
        $filas = $this->personal()['asistentes'];
        $this->assertCount(3, $filas); $this->assertSame(36, array_sum(array_column($filas, 'I')));
        $this->assertSame(['Indefinido', 'Plazo Fijo', 'Otros'], array_column($filas, 'H'));
        $this->assertSame(['Profesionales', 'Auxiliares', null], array_column($filas, 'G'));
        $this->assertNull($filas[2]['J']); $this->assertStringContainsString('pendiente de clasificación', $filas[2]['M']);
        $this->assertSame(6, app(DotacionPalPersonal2027::class)->construir($otro)['asistentes'][0]['I']);
    }

    public function test_no_atribuye_nivel_a_aaee_de_un_establecimiento_con_parvularia_y_basica(): void
    {
        $this->ee->update(['pre_escolar' => true]);
        $this->persona(['jornada_basica' => 44, 'jornada_media' => 0]);
        $fila = $this->personal()['asistentes'][0];
        $this->assertNull($fila['D']); $this->assertStringContainsString('Nivel no individualizado', $fila['M']);
    }

    public function test_previa_y_consolidado_incluyen_jardines_elegibles_sin_proyectar_plan_docente(): void
    {
        $jardin = $this->ee(99001, ['sala_cuna' => true]);
        $this->ee(99002, ['sala_cuna' => true]); // Sin fuente de agosto.
        $this->persona(['rbd' => $jardin->rbd, 'establecimiento_id' => null, 'financiamiento' => 'JUNJI']);
        $this->persona(['rbd' => 99002, 'tipocontrato' => 'REEMPLAZO']);
        $this->mock(DotacionPal2027::class)->shouldReceive('datos')->once()->withArgs(fn ($e) => $e->id === $this->ee->id)->andReturn([])
            ->shouldReceive('construir')->once()->andReturn(['cursos' => [], 'docentes' => []]);
        $this->get(route('admin.dotacion-establecimiento.pal-2027.todos'))->assertOk()
            ->assertSee('2 establecimientos incluidos')->assertSee('Escuela sintética 99001')->assertDontSee('Escuela sintética 99002');
        $reportes = iterator_to_array(app(DotacionPal2027Consolidado::class)->reportes([]));
        $this->assertCount(2, $reportes); $this->assertEmpty($reportes[1]['docentes']);
        $this->assertCount(1, $reportes[1]['vtf']); $this->assertStringContainsString('Jardín VTF', $reportes[1]['cursos'][0]['C']);
        $this->assertNull($reportes[1]['cursos'][0]['D']);
    }

    public static function exportadores(): array { return [['individual'], ['consolidado']]; }

    #[DataProvider('exportadores')]
    public function test_descarga_ambas_hojas_extiende_resumen_validaciones_y_preserva_formulas_y_objetos(string $tipo): void
    {
        $this->persona(['nombre' => '=Persona sintética', 'financiamiento' => 'PIE']);
        $this->persona(['rut' => '99000002-K', 'financiamiento' => 'JUNJI', 'escalafon' => 'DIRECTORA SC Y JARDIN']);
        $reporte = $this->personal() + ['docentes' => [], 'cursos' => [['A' => 'Escuela sintética', 'B' => '99000', 'C' => '1° Básico', 'D' => 20, 'E' => 1, 'F' => 38, 'H' => 44]]];
        $reporte['asistentes'] = array_fill(0, 250, $reporte['asistentes'][0]);
        $reporte['vtf'] = array_fill(0, 180, $reporte['vtf'][0]);
        $path = Storage::disk('local')->path('personal-'.$tipo.'.xlsx');
        if ($tipo === 'individual') { app(DotacionPal2027Export::class)->guardar($reporte, $path); }
        else { app(DotacionPal2027ConsolidadoExport::class)->guardarTodos([$reporte], $path); }
        $zip = new ZipArchive; $original = new ZipArchive;
        $this->assertTrue($zip->open($path)); $this->assertTrue($original->open(app(DotacionPal2027Export::class)->template()));
        try {
            $ae = $this->xml($zip->getFromName('xl/worksheets/sheet5.xml'));
            $vtf = $this->xml($zip->getFromName('xl/worksheets/sheet6.xml'));
            $resumen = $zip->getFromName('xl/worksheets/sheet2.xml');
            $this->assertSame('99000001-K', (string) $ae->xpath('//m:c[@r="A252"]/m:is/m:t')[0]);
            $this->assertSame('44', (string) $ae->xpath('//m:c[@r="I252"]/m:v')[0]);
            $this->assertSame('44', (string) $ae->xpath('//m:c[@r="K252"]/m:v')[0]);
            $this->assertEmpty($ae->xpath('//m:c[@r="J3"]/*'));
            $this->assertStringContainsString('=Persona sintética', (string) $ae->xpath('//m:c[@r="M3"]/m:is/m:t')[0]);
            $this->assertEmpty($ae->xpath('//m:c/m:f')); $this->assertEmpty($vtf->xpath('//m:c/m:f'));
            $this->assertSame('99000002-K', (string) $vtf->xpath('//m:c[@r="A182"]/m:is/m:t')[0]);
            $this->assertSame('Función Directiva', (string) $vtf->xpath('//m:c[@r="F182"]/m:is/m:t')[0]);
            $this->assertStringContainsString("'Info Funcionario(AE)'!\$A\$3:\$A\$252", $resumen);
            $this->assertStringContainsString("'Info Funcionario (VTF)'!\$A\$3:\$A\$182", $resumen);
            foreach (['D','E','G','H'] as $col) { $this->assertCount(1, $ae->xpath('//m:dataValidation[@sqref="'.$col.'3:'.$col.'252"]')); }
            foreach (['D','E','F','G'] as $col) { $this->assertCount(1, $vtf->xpath('//m:dataValidation[@sqref="'.$col.'3:'.$col.'182"]')); }
            $opciones = (string) $vtf->xpath('//m:dataValidation[@sqref="E3:E182"]/m:formula1')[0];
            $this->assertSame('"ADMINISTRATIVO,AUXILIAR,TÉCNICO,EDUCADOR/A,DIRECTOR/A,PROFESIONAL"', $opciones);
            $this->assertGreaterThanOrEqual(16.5, (float) $vtf->xpath('//m:col[@min="5" and @max="5"]')[0]['width']);
            foreach (['xl/worksheets/sheet1.xml', 'xl/worksheets/sheet7.xml', 'xl/worksheets/sheet8.xml', 'xl/worksheets/sheet9.xml'] as $parte) {
                $this->assertSame($original->getFromName($parte), $zip->getFromName($parte));
            }
            $formulaOriginal = $this->xml($original->getFromName('xl/worksheets/sheet2.xml'));
            $formulaNueva = $this->xml($resumen);
            $old = (string) $formulaOriginal->xpath('//m:c[@r="J3"]/m:f')[0];
            $new = (string) $formulaNueva->xpath('//m:c[@r="J3"]/m:f')[0];
            $this->assertSame(str_replace('$100', '$252', $old), $new);
            $this->assertGreaterThan(30, (float) $ae->xpath('//m:row[@r="3"]')[0]['ht']);
        } finally { $zip->close(); $original->close(); }
    }

    public function test_ruta_individual_integra_personal_historico_y_mantiene_descarga_privada(): void
    {
        $this->persona();
        $this->mock(DotacionPal2027::class)->shouldReceive('datos')->once()->andReturn([])
            ->shouldReceive('construir')->once()->andReturn(['cursos' => [], 'docentes' => []]);
        $response = $this->get(route('admin.dotacion-establecimiento.pal-2027', [$this->ee, 'descargar' => 1]))
            ->assertOk()->assertDownload('borrador_pal_2027_rbd_99000.xlsx')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        Storage::disk('local')->put('ruta.xlsx', $response->streamedContent());
        $zip = new ZipArchive; $this->assertTrue($zip->open(Storage::disk('local')->path('ruta.xlsx')));
        try { $this->assertStringContainsString('99000001-K', $zip->getFromName('xl/worksheets/sheet5.xml')); }
        finally { $zip->close(); }
    }

    public function test_vtf_admite_profesional_aunque_no_haya_funcionarios_cargados(): void
    {
        $path = Storage::disk('local')->path('vacio.xlsx');
        app(DotacionPal2027Export::class)->guardar(['docentes' => [], 'cursos' => []], $path);
        $zip = new ZipArchive; $this->assertTrue($zip->open($path));
        try {
            $xml = $this->xml($zip->getFromName('xl/worksheets/sheet6.xml'));
            $opciones = (string) $xml->xpath('//m:dataValidation[@sqref="E3:E133"]/m:formula1')[0];
            $this->assertSame(1, substr_count($opciones, 'PROFESIONAL'));
            $this->assertEmpty($xml->xpath('//m:row[@r>=3]/m:c/m:v'));
        } finally { $zip->close(); }
    }

    public function test_un_fallo_despues_de_escribir_ae_vtf_cancela_y_limpia_sus_temporales(): void
    {
        $this->persona();
        $this->persona(['financiamiento' => 'JUNJI']);
        $reporte = $this->personal() + ['cursos' => [], 'docentes' => []];
        $reportes = (function () use ($reporte) {
            yield $reporte;
            throw new \RuntimeException('Fallo sintético posterior al personal');
        })();
        $antes = glob(sys_get_temp_dir().'/sga-pal-*'); sort($antes);
        try {
            app(DotacionPal2027ConsolidadoExport::class)->descargar($reportes);
            $this->fail('No debe entregar una descarga parcial.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fallo sintético posterior al personal', $error->getMessage());
        }
        $despues = glob(sys_get_temp_dir().'/sga-pal-*'); sort($despues);
        $this->assertSame($antes, $despues);
    }

    private function persona(array $extra = []): ReemplazoPersonal
    {
        return ReemplazoPersonal::create($extra + ['establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
            'rut' => '99000001-K', 'nombre' => 'Persona sintética', 'anio' => 2026, 'mes' => 8,
            'tipocontrato' => 'INDEFINIDO', 'financiamiento' => 'SUB.GENERAL', 'estatuto' => 'AAEE',
            'escalafon' => 'TECNICO', 'jornada' => 44, 'row_hash' => hash('sha256', 'sintetico-'.++$this->secuencia)]);
    }

    private function ee(int $rbd, array $extra = []): Establecimiento
    {
        return Establecimiento::create($extra + ['cod_estab' => $rbd, 'rbd' => $rbd,
            'nombre_establecimiento' => 'Escuela sintética '.$rbd, 'comuna' => 'Prueba', 'sala_cuna' => false]);
    }

    private function personal(): array { return app(DotacionPalPersonal2027::class)->construir($this->ee); }
    private function xml(string $xml): \SimpleXMLElement
    {
        $doc = simplexml_load_string($xml); $doc->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'); return $doc;
    }
}
