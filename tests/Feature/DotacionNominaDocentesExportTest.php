<?php

namespace Tests\Feature;

use App\Exports\DotacionNominaDocentesExport;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Models\User;
use App\Services\Dotacion\EdicionBloqueoService;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionNominaDocentes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionNominaDocentesExportTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCalculatorCaches();
        $this->withoutMiddleware([EnsureModuleAccess::class, CoordinarEscrituraPadron::class]);
        $this->withoutVite();
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        Schema::create('establecimientos', function (Blueprint $t) { $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna'); $t->boolean('sala_cuna')->default(false); });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética A', 'comuna' => 'Comuna de prueba', 'sala_cuna' => false],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Escuela sintética B', 'comuna' => 'Comuna de prueba', 'sala_cuna' => false],
            ['id' => 3, 'rbd' => 99997, 'nombre_establecimiento' => 'Sala cuna sintética', 'comuna' => 'Comuna de prueba', 'sala_cuna' => true],
        ]);
        foreach ([4 => 'funcionario_directivo_estab', 5 => 'coordinador_uatp', 6 => 'coordinador_gdp', 7 => 'supervisor_plani', 8 => 'funcionario_slep'] as $id => $name) { DB::table('roles')->insert(['id' => $id, 'name' => $name, 'guard_name' => 'web']); }
        $this->ee = Establecimiento::findOrFail(1);
    }

    protected function tearDown(): void
    {
        $this->resetCalculatorCaches();
        parent::tearDown();
    }

    private function resetCalculatorCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, \App\Support\DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) { (new \ReflectionProperty($class, $property))->setValue(null, []); }
        }
        (new \ReflectionProperty(\App\Support\DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        \App\Support\DocenteHorasNoLectivasCalculator::clearExceptionCache();
        \App\Support\DotacionCursoCombinadoCalculator::clearCache();
    }

    private function docente(array $changes = []): array
    {
        return array_replace([
            'rut' => '99000001-K', 'rut_normalizado' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Pedagogía de prueba',
            'tramo' => 'Inicial', 'fecha_antiguedad' => '2010-01-01', 'horas_contrato_base' => 44, 'horas_contrato' => 40,
            'horas_planta' => 40, 'horas_contrata' => 4, 'horas_aula' => 20, 'horas_asignadas_total' => 30,
            'horas_reservadas_no_normativas' => 4, 'diferencia' => 10, 'mes' => 8, 'anio' => 2026,
        ], $changes);
    }

    private function informe(array $changes = []): array
    {
        return array_replace([
            'filas' => (new DotacionNominaDocentes)->filas(collect([$this->docente()]), [], collect()),
            'periodos_padron' => '08/2026', 'justificaciones_disponibles' => true,
        ], $changes);
    }

    private function justificacion(string $bloque, string $tipo, float $horas, string $texto): DotacionSobredotacionJustificacion
    {
        return new DotacionSobredotacionJustificacion(['establecimiento_id' => 1, 'anio' => 2027, 'bloque' => $bloque,
            'docente_rut_normalizado' => '99000001-K', 'tipo_horas' => $tipo, 'horas_detectadas' => $horas, 'justificacion' => $texto]);
    }

    public function test_prelacion_reutiliza_grupos_antiguedad_y_nombre_sin_incluir_cupos_o_asistentes(): void
    {
        $people = collect([
            $this->docente(['nombre' => 'Contrata sin fecha', 'rut' => '99000007-K', 'horas_planta' => 0, 'fecha_antiguedad' => null]),
            $this->docente(['nombre' => 'Titular Inicial', 'rut' => '99000005-K', 'fecha_antiguedad' => '1980-01-01']),
            $this->docente(['nombre' => 'Fuero', 'rut' => '99000002-K', 'fecha_antiguedad' => '2005-01-01', 'exclusion_docente' => ['motivo' => 'fuero_maternal']]),
            $this->docente(['nombre' => 'No Regido', 'rut' => '99000003-K', 'horas_planta' => 0, 'tramo' => 'No Regido Ley 20903']),
            $this->docente(['nombre' => 'Titular Experto', 'rut' => '99000004-K', 'tramo' => 'Experto 2']),
            $this->docente(['nombre' => 'Gremial', 'rut' => '99000001-K', 'fecha_antiguedad' => '2000-01-01', 'exclusion_docente' => ['motivo' => 'horas_gremiales']]),
            $this->docente(['nombre' => 'Contrata con fecha', 'rut' => '99000006-K', 'horas_planta' => 0]),
            $this->docente(['nombre' => 'Cupo virtual', 'cupo_contrata_id' => 9]),
            $this->docente(['nombre' => 'Asistente de prueba', 'estamento_cobertura' => 'asistente']),
        ]);
        $rows = app(DotacionNominaDocentes::class)->filas($people, [], collect());
        $this->assertSame(['Gremial', 'Fuero', 'No Regido', 'Titular Experto', 'Titular Inicial', 'Contrata con fecha', 'Contrata sin fecha'], $rows->pluck('nombre')->all());
        $this->assertStringStartsWith('2.', $rows[2]['prelacion']);
        $tied = collect([$this->docente(['nombre' => 'Zeta']), $this->docente(['nombre' => 'Alfa'])]);
        $this->assertSame(['Alfa', 'Zeta'], app(DotacionNominaDocentes::class)->filas($tied, [], collect())->pluck('nombre')->all());
    }

    public function test_separa_etapa_7_por_bloque_y_tipo_y_descarta_justificaciones_vacias_o_desactualizadas(): void
    {
        $vacantes = ['vacantes_por_bloque' => [
            'plan_estudio' => ['items' => [['rut' => '99.000.001-K', 'horas_sobredotacion_planta' => 6, 'horas_sobredotacion_contrata' => 2]]],
            'parvularia' => ['items' => [['rut' => '99000001-K', 'horas_sobredotacion_planta' => 1, 'horas_sobredotacion_contrata' => 0.01]]],
            'pie' => ['items' => [['rut' => '99000001-K', 'horas_sobredotacion_contrata' => 3.5]]],
        ]];
        $registros = collect([
            $this->justificacion('plan_estudio', 'titular', 6, 'Justificación sintética de supresión.'),
            $this->justificacion('plan_estudio', 'contrata', 2, '  '),
            $this->justificacion('parvularia', 'titular', 99, 'Justificación anterior sintética.'),
            $this->justificacion('pie', 'contrata', 3.5, 'Justificación sintética PIE.'),
        ]);
        $rows = app(DotacionNominaDocentes::class)->filas(collect([$this->docente(), $this->docente(['rut' => '99000002-K'])]), $vacantes, $registros);
        $this->assertEquals(9.5, $rows[0]['con_justificacion']); $this->assertEquals(3, $rows[0]['sin_justificacion']);
        $this->assertStringContainsString('registrada para 99', $rows[0]['detalle_justificaciones']);
        $this->assertStringContainsString('Supresión titular', $rows[0]['detalle_justificaciones']);
        $this->assertEquals(0, $rows[1]['con_justificacion']); $this->assertEquals(0, $rows[1]['sin_justificacion']);
    }

    public function test_reservas_redondeo_nt_saldos_negativos_y_datos_desconocidos_coinciden_con_la_vista(): void
    {
        $source = app(DotacionNominaDocentes::class);
        $row = $source->filas(collect([$this->docente()]), [], collect())->first();
        $this->assertEquals(44, $row['contrato_original']); $this->assertEquals(40, $row['contrato_considerado']);
        $this->assertEquals(26, $row['contrato_asignado']); $this->assertEquals(4, $row['reservadas']); $this->assertEquals(10, $row['pendientes']);
        $rounded = $source->filas(collect([$this->docente(['horas_contrato' => 44, 'horas_asignadas_total' => 43.35, 'horas_reservadas_no_normativas' => 0, 'diferencia' => .65, 'redondeo_parvularia' => .65])]), [], collect())->first();
        $this->assertEquals(44, $rounded['contrato_asignado']); $this->assertEquals(0, $rounded['pendientes']);
        $over = $source->filas(collect([$this->docente(['diferencia' => -2])]), [], collect())->first(); $this->assertEquals(-2, $over['pendientes']);
        $unknown = $source->filas(collect([$this->docente(['horas_contrato_base' => null, 'horas_contrato' => null, 'diferencia' => null])]), [], collect())->first();
        $this->assertNull($unknown['contrato_original']); $this->assertNull($unknown['contrato_considerado']); $this->assertNull($unknown['pendientes']);
        $book = app(DotacionNominaDocentesExport::class)->workbook($this->ee, 2027, $this->informe(['filas' => collect([$unknown])]));
        try { $this->assertNull($book->getActiveSheet()->getCell('H8')->getValue()); $this->assertNull($book->getActiveSheet()->getCell('M8')->getValue()); }
        finally { $book->disconnectWorksheets(); }
    }

    public function test_xlsx_contiene_columnas_fechas_numeros_filtros_totales_y_textos_seguros(): void
    {
        $rows = app(DotacionNominaDocentes::class)->filas(collect([$this->docente(['nombre' => '=SUM(1,2)', 'titulo' => '=HYPERLINK("https://example.test")'])]), [], collect());
        $book = app(DotacionNominaDocentesExport::class)->workbook($this->ee, 2028, $this->informe(['filas' => $rows]));
        $path = Storage::disk('local')->path('nomina-sintetica.xlsx'); (new Xlsx($book))->save($path); $book->disconnectWorksheets();
        $loaded = IOFactory::load($path);
        try {
            $sheet = $loaded->getSheet(0);
            $this->assertSame(2, $loaded->getSheetCount()); $this->assertSame('Nómina docente', $sheet->getTitle());
            $this->assertStringContainsString('2028', $sheet->getCell('H6')->getValue());
            $this->assertSame('08/2026', str_replace('Proceso 2028 · Fuente contractual: ', '', $sheet->getCell('A3')->getValue()));
            $this->assertSame('=SUM(1,2)', $sheet->getCell('C7')->getValue()); $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C7')->getDataType());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D7')->getDataType());
            $this->assertSame('2010-01-01', Date::excelToDateTimeObject($sheet->getCell('G7')->getValue())->format('Y-m-d'));
            $this->assertSame('dd-mm-yyyy', $sheet->getStyle('G7')->getNumberFormat()->getFormatCode());
            $this->assertEquals(26, $sheet->getCell('K7')->getValue()); $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('K7')->getDataType());
            $this->assertEquals(10, $sheet->getCell('M8')->getValue());
            $this->assertSame('A6:P7', $sheet->getAutoFilter()->getRange()); $this->assertSame('D7', $sheet->getFreezePane());
            $this->assertSame('Definiciones', $loaded->getSheet(1)->getTitle());
        } finally { $loaded->disconnectWorksheets(); }
    }

    public function test_exportacion_vacia_no_inventa_docentes(): void
    {
        $book = app(DotacionNominaDocentesExport::class)->workbook($this->ee, 2026, $this->informe(['filas' => collect(), 'justificaciones_disponibles' => false]));
        try {
            $sheet = $book->getActiveSheet(); $this->assertStringContainsString('No hay docentes', $sheet->getCell('A7')->getValue());
            $this->assertEquals(0, $sheet->getCell('H8')->getValue()); $this->assertSame('A6:P6', $sheet->getAutoFilter()->getRange());
        } finally { $book->disconnectWorksheets(); }
    }

    public static function roles(): array { return [[3, 'admin', true], [4, 'funcionario_directivo_estab', true], [5, 'coordinador_uatp', true], [6, 'coordinador_gdp', true], [7, 'supervisor_plani', true], [8, 'funcionario_slep', false], [1, 'postulante', false]]; }

    #[DataProvider('roles')]
    public function test_permisos_y_anio_de_la_descarga(int $id, string $rol, bool $allowed): void
    {
        $user = $this->testUser($id, $id); $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => $rol]);
        $mock = $this->mock(DotacionNominaDocentes::class);
        if ($allowed) { $mock->shouldReceive('datos')->once()->withArgs(fn ($ee, $year) => $ee->id === 1 && $year === 2028)->andReturn($this->informe()); }
        else { $mock->shouldNotReceive('datos'); }
        $response = $this->get(route('admin.dotacion-establecimiento.docentes.export', [1, 'anio' => 2028]));
        if ($allowed) {
            $response->assertOk()->assertDownload('nomina_docentes_prelacion_rbd_99999_2028.xlsx')->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringStartsWith('PK', $response->streamedContent());
        } else { $response->assertForbidden(); }
    }

    public function test_directivo_incluso_multirrol_no_exporta_otro_establecimiento_ni_salas_cuna(): void
    {
        $user = $this->testUser(4, 4); $user->forceFill(['establecimiento_id' => 1])->save();
        DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => User::class, 'model_id' => $user->id]); $user->unsetRelation('roles');
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->mock(DotacionNominaDocentes::class)->shouldNotReceive('datos');
        $this->get(route('admin.dotacion-establecimiento.docentes.export', [2, 'anio' => 2027]))->assertForbidden();
        $this->get(route('admin.dotacion-establecimiento.docentes.export', [3, 'anio' => 2027]))->assertNotFound();
        $user->forceFill(['establecimiento_id' => null])->save();
        $this->get(route('admin.dotacion-establecimiento.docentes.export', 1))->assertForbidden();
    }

    public function test_descarga_continua_disponible_con_edicion_bloqueada_y_rechaza_anios_invalidos(): void
    {
        (require base_path('database/migrations/2026_11_02_140000_create_dotacion_edicion_bloqueos.php'))->up();
        $admin = $this->testUser(10, 3); app(EdicionBloqueoService::class)->guardar(null, true, 0, null, $admin);
        $user = $this->testUser(4, 4); $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->mock(DotacionNominaDocentes::class)->shouldReceive('datos')->once()->andReturn($this->informe());
        $this->get(route('admin.dotacion-establecimiento.docentes.export', [1, 'anio' => 2027]))->assertOk()->assertDownload();
        $this->getJson(route('admin.dotacion-establecimiento.docentes.export', [1, 'anio' => 'invalido']))->assertUnprocessable()->assertJsonValidationErrors('anio');
    }

    public static function years(): array { return [[2026], [2027]]; }

    #[DataProvider('years')]
    public function test_fuente_real_sin_docentes_entrega_nomina_vacia_sin_escrituras(int $year): void
    {
        $this->createDotacionTables();
        $writes = [];
        DB::listen(function (\Illuminate\Database\Events\QueryExecuted $event) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $event->sql)) { $writes[] = $event->sql; }
        });
        $informe = (new DotacionNominaDocentes)->datos($this->ee, $year);
        $this->assertCount(0, $informe['filas']); $this->assertFalse($informe['justificaciones_disponibles']);
        $this->assertSame([], $writes);
    }

    public function test_fuente_real_usa_padron_y_solo_justificaciones_del_establecimiento_y_anio(): void
    {
        $this->createDotacionTables();
        (require database_path('migrations/2026_09_29_190000_create_dotacion_sobredotacion_justificaciones_table.php'))->up();
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id();
            foreach (['rbd', 'rut', 'nombres', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $field) { $t->string($field); }
        });
        DB::table('reemplazos_personal')->insert([
            'establecimiento_id' => 1, 'rbd' => 99999, 'rut' => '99000001-K', 'nombre' => 'Docente sintético',
            'anio' => 2026, 'mes' => 10, 'jornada' => 44, 'tipocontrato' => 'CONTRATA',
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-export',
        ]);
        DB::table('declaracion_sostenedores')->insert([
            'rbd' => '99999', 'rut' => '99000001-K', 'nombres' => 'Docente sintético',
            'nombre_titulo' => 'Pedagogía sintética', 'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE',
        ]);
        $registro = $this->justificacion('plan_estudio', 'contrata', 44, 'Respaldo sintético.')->getAttributes();
        DotacionSobredotacionJustificacion::create(array_replace($registro, ['establecimiento_id' => 2]));
        DotacionSobredotacionJustificacion::create(array_replace($registro, ['anio' => 2026]));
        $source = new DotacionNominaDocentes;
        $informe = $source->datos($this->ee, 2027);
        $this->assertCount(1, $informe['filas']); $this->assertSame('10/2026', $informe['periodos_padron']);
        $row = $informe['filas']->first();
        $this->assertSame('Pedagogía sintética', $row['titulo']); $this->assertEquals(44, $row['contrato_considerado']);
        $this->assertEquals(44, $row['pendientes']); $this->assertEquals(0, $row['con_justificacion']); $this->assertEquals(44, $row['sin_justificacion']);
        $actual = DotacionSobredotacionJustificacion::create($registro);
        $this->assertEquals(44, $source->datos($this->ee, 2027)['filas']->first()['con_justificacion']);
        $actual->update(['horas_detectadas' => 43]);
        $this->assertEquals(44, $source->datos($this->ee, 2027)['filas']->first()['sin_justificacion']);
    }

    private function createDotacionTables(): void
    {
        foreach ([
            '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }
}
