<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Padron\PadronDatosActualizacionService;
use App\Services\Padron\PadronDatosExcel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class PadronDatosActualizacionTest extends IsolatedSecurityTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $this->admin = $this->testUser(1, 3);
        $this->actingAs($this->admin)->withSession(['active_role' => 'admin']);
        Schema::create('modules', function (Blueprint $t) { $t->id(); $t->string('key'); });
        Schema::create('establecimientos', function (Blueprint $t) { $t->id(); $t->integer('rbd'); });
        DB::table('establecimientos')->insert([['id' => 1, 'rbd' => 99999], ['id' => 2, 'rbd' => 99998]]);
        (require base_path('database/migrations/2026_01_29_000001_create_reemplazos_personal_table.php'))->up();
        Schema::table('reemplazos_personal', function (Blueprint $t) {
            $t->date('fecha_antiguedad')->nullable(); $t->integer('bienios')->nullable();
            $t->string('tramo')->nullable(); $t->boolean('vigente')->default(true);
        });
        Schema::create('padron_aplicacion_control', fn (Blueprint $t) => $t->id());
        DB::table('padron_aplicacion_control')->insert(['id' => 1]);
        (require base_path('database/migrations/2026_09_14_140000_create_padron_individual_cambios.php'))->up();
        foreach ([
            [1, '11111111-1', 9, 1], [2, '11.111.111-1', 9, 2],
            [3, '11111111-1', 8, 1], [4, '22222222-2', 9, 1], [5, '33333333-3', 8, 1],
        ] as [$id, $rut, $mes, $ee]) {
            DB::table('reemplazos_personal')->insert([
                'id' => $id, 'rut' => $rut, 'anio' => 2026, 'mes' => $mes,
                'nombre' => 'Persona sintética '.$id, 'establecimiento_id' => $ee, 'rbd' => 100000 - $ee,
                'fecha_antiguedad' => '2010-03-01', 'fecha_nacimiento' => '1980-01-01',
                'fecha_ingreso' => '2026-03-01', 'jornada' => 22, 'jornada_basica' => 22, 'jornada_media' => 0,
                'tipocontrato' => 'PLANTA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'financiamiento' => 'SUB.GENERAL',
                'bienios' => 4, 'tramo' => 'Temprano', 'row_hash' => hash('sha256', 'datos-prueba-'.$id),
                'created_by' => $this->admin->id, 'created_at' => '2026-09-01 00:00:00', 'updated_at' => '2026-09-01 00:00:00',
            ]);
        }
    }

    public function test_actualiza_cuatro_campos_en_todas_las_lineas_del_ultimo_mes_sin_tocar_otros_datos(): void
    {
        $antes = DB::table('reemplazos_personal')->orderBy('id')->get()->keyBy('id');
        $response = $this->cargar(['rut', 'FECHA_NACIMIENTO', 'fechaing', 'Tramo', 'Bienios'], [
            [' 11.111.111 - 1 ', '02/01/1981', '2005-03-01', 'Experto II', 0],
        ], array_keys(PadronDatosExcel::CAMPOS));
        $response->assertRedirect(route('reemplazos.personal.import'))->assertSessionHas('actualizacion_datos.registros_actualizados', 2);
        $response->assertSessionHas('actualizacion_datos.ruts_actualizados', 1)->assertSessionHas('actualizacion_datos.omitidos', 0);
        foreach ([1, 2] as $id) {
            $row = (array) DB::table('reemplazos_personal')->find($id);
            $this->assertSame('1981-01-02', $row['fecha_nacimiento']);
            $this->assertSame('2005-03-01', $row['fecha_antiguedad']);
            $this->assertSame('Experto 2', $row['tramo']); $this->assertSame(0, $row['bienios']);
            foreach (['rut', 'row_hash', 'jornada', 'jornada_basica', 'fecha_ingreso', 'tipocontrato', 'vigente', 'created_by', 'created_at'] as $campo) {
                $this->assertSame($antes[$id]->{$campo}, $row[$campo]);
            }
        }
        foreach ([3, 4, 5] as $id) { $this->assertEquals($antes[$id], DB::table('reemplazos_personal')->find($id)); }
        $this->assertDatabaseCount('reemplazos_personal', 5);
        $this->assertDatabaseCount('padron_individual_cambios', 2);
        $audit = DB::table('padron_individual_cambios')->first();
        $this->assertSame('2010-03-01', json_decode($audit->antes, true)['fecha_antiguedad']);
        $this->assertSame('2005-03-01', json_decode($audit->despues, true)['fecha_antiguedad']);
        $this->assertSame($this->admin->id, $audit->usuario_id);
    }

    public function test_omite_no_encontrados_incluso_si_existen_solo_en_mes_anterior_y_exporta_informe(): void
    {
        $this->cargar(['rut', 'fecha_antiguedad'], [
            ['11111111-1', '2001-01-01'], ['33333333-3', '2002-01-01'], ['44444444-4', '2003-01-01'],
        ])->assertSessionHas('actualizacion_datos.omitidos', 2)->assertSessionHas('actualizacion_datos.registros_actualizados', 2);
        $reporte = session('actualizacion_datos.reporte');
        $response = $this->get(route('reemplazos.personal.datos.omitidos', $reporte))->assertOk()->assertDownload('registros_omitidos_202609.xlsx');
        $book = $this->leerRespuesta($response->streamedContent());
        $sheet = $book->getSheet(0);
        $this->assertSame(['Fila Excel', 'RUT', 'Motivo de omisión'], $sheet->rangeToArray('A1:C1')[0]);
        $this->assertSame('33333333-3', $sheet->getCell('B2')->getValue());
        $this->assertSame('44444444-4', $sheet->getCell('B3')->getValue());
        $this->assertStringContainsString('09/2026', $sheet->getCell('C2')->getValue());
        $book->disconnectWorksheets();
        $path = app(PadronDatosActualizacionService::class)->reportePath($this->admin->id, $reporte);
        Storage::disk('local')->assertExists($path); Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 5, 'fecha_antiguedad' => '2010-03-01']);
    }

    public function test_carga_con_todos_omitidos_no_falla_ni_crea_personal(): void
    {
        $this->cargar(['rut', 'fecha_antiguedad'], [['44444444-4', '2000-01-01'], ['RUT-INVALIDO', '2000-01-01']])
            ->assertRedirect()->assertSessionHas('actualizacion_datos.omitidos', 2)->assertSessionHas('actualizacion_datos.registros_actualizados', 0);
        $this->assertDatabaseCount('reemplazos_personal', 5); $this->assertDatabaseCount('padron_individual_cambios', 0);
    }

    public function test_no_encontrados_con_datos_invalidos_o_repetidos_se_omiten_sin_detener_carga(): void
    {
        $this->cargar(['rut', 'fecha_antiguedad'], [
            ['11111111-1', '2000-01-01'], ['44444444-4', 'Fecha desconocida'], ['44.444.444-4', ''],
        ])->assertSessionHas('actualizacion_datos.omitidos', 2)->assertSessionHas('actualizacion_datos.registros_actualizados', 2);
    }

    public function test_procesa_mas_de_un_lote_con_busquedas_agrupadas_y_filas_correctas_en_informe(): void
    {
        $rows = [['11111111-1', '2000-01-01']];
        for ($i = 70000000; $i < 70000600; $i++) { $rows[] = [$i.'-'.\App\Support\RutChile::dv($i), '2001-01-01']; }
        $consultas = 0;
        DB::listen(function (QueryExecuted $q) use (&$consultas) { if (str_starts_with($q->sql, 'select') && str_contains($q->sql, 'CHAR(9)')) { $consultas++; } });
        $this->cargar(['rut', 'fecha_antiguedad'], $rows)->assertSessionHas('actualizacion_datos.omitidos', 600)->assertSessionHas('actualizacion_datos.registros_actualizados', 2);
        $this->assertLessThanOrEqual(3, $consultas);
        $reporte = session('actualizacion_datos.reporte');
        $path = app(PadronDatosActualizacionService::class)->reportePath($this->admin->id, $reporte);
        $datos = json_decode(Storage::disk('local')->get($path), true);
        $this->assertCount(600, $datos['omitidos']);
        $this->assertSame(602, $datos['omitidos'][599][0]);
    }

    public function test_vacios_y_campos_no_seleccionados_se_conservan_y_repetir_carga_no_duplica_auditoria(): void
    {
        $response = $this->cargar(['rut', 'fecha_antiguedad', 'Tramo', 'Bienios', 'jornada'], [['11111111-1', '', 'Avanzado', 10, 99]]);
        $response->assertSessionHas('actualizacion_datos.sin_cambios', 2)->assertSessionHas('actualizacion_datos.registros_actualizados', 0);
        $this->assertDatabaseCount('padron_individual_cambios', 0);
        $this->cargar(['rut', 'fecha_antiguedad'], [['11111111-1', '2001-01-01']])->assertSessionHas('actualizacion_datos.registros_actualizados', 2);
        $this->cargar(['rut', 'fecha_antiguedad'], [['11111111-1', '2001-01-01']])->assertSessionHas('actualizacion_datos.registros_actualizados', 0);
        $this->assertDatabaseCount('padron_individual_cambios', 2);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 1, 'tramo' => 'Temprano', 'bienios' => 4, 'jornada' => 22]);
    }

    public function test_fechas_excel_y_xls_se_aceptan(): void
    {
        $this->cargar(['rut', 'fecha_antiguedad'], [['11111111-1', ExcelDate::PHPToExcel(new \DateTimeImmutable('2003-02-01'))]], ['fecha_antiguedad'], 'xls')
            ->assertSessionHas('actualizacion_datos.registros_actualizados', 2);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 1, 'fecha_antiguedad' => '2003-02-01']);
    }

    public function test_rut_con_k_minuscula_y_separadores_encuentra_registro(): void
    {
        $numero = 10000000;
        while (\App\Support\RutChile::dv($numero) !== 'K') { $numero++; }
        $row = (array) DB::table('reemplazos_personal')->find(4);
        $row['id'] = 6; $row['rut'] = \App\Support\Rut::format($numero.'K'); $row['row_hash'] = hash('sha256', 'datos-prueba-6');
        DB::table('reemplazos_personal')->insert($row);
        $this->cargar(['rut', 'fecha_antiguedad'], [[$numero.' - k', '2000-01-01']])->assertSessionHas('actualizacion_datos.registros_actualizados', 1);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 6, 'fecha_antiguedad' => '2000-01-01']);
    }

    public static function invalidos(): array
    {
        return [
            [['rut', 'fecha_antiguedad'], [['11111111-1', '31/02/2000']]],
            [['rut', 'fecha_antiguedad'], [['11111111-1', '=DATE(2000,1,1)']]],
            [['rut', 'fecha_antiguedad'], [['11111111-1', '2000-01-01'], ['11.111.111-1', '2001-01-01']]],
            [['rut', 'Tramo'], [['11111111-1', 'Incorrecto']]],
            [['rut', 'Bienios'], [['11111111-1', -1]]],
            [['rut', 'Bienios'], [['11111111-1', 1.5]]],
            [['rut', 'FECHA_NACIMIENTO'], [['11111111-1', '2100-01-01']]],
            [['rut', 'fechaing', 'fecha_antiguedad'], [['11111111-1', '2000-01-01', '2000-01-01']]],
        ];
    }

    #[DataProvider('invalidos')]
    public function test_datos_invalidos_impiden_actualizacion_parcial(array $headers, array $rows): void
    {
        $campos = array_map(fn ($h) => $h === 'fechaing' ? 'fecha_antiguedad' : strtolower($h), array_slice($headers, 1));
        $campos = array_values(array_unique($campos));
        $antes = DB::table('reemplazos_personal')->get()->toJson();
        $this->cargar($headers, $rows, $campos)->assertSessionHasErrors('excel_actualizacion');
        $this->assertSame($antes, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertDatabaseCount('padron_individual_cambios', 0);
    }

    public function test_plantilla_dinamica_contiene_solo_campos_elegidos_y_no_personas(): void
    {
        foreach ([['fecha_antiguedad'], ['fecha_nacimiento', 'tramo', 'bienios']] as $campos) {
            $response = $this->get(route('reemplazos.personal.datos.plantilla', ['campos' => $campos]))->assertOk()->assertDownload('plantilla_actualizacion_personal.xlsx');
            $book = $this->leerRespuesta($response->streamedContent());
            $sheet = $book->getSheet(0);
            $this->assertSame(['rut', ...array_map(fn ($c) => PadronDatosExcel::CAMPOS[$c]['columna'], $campos)], $sheet->toArray()[0]);
            $this->assertSame(1, $sheet->getHighestDataRow());
            $this->assertSame('Instrucciones', $book->getSheet(1)->getTitle());
            $book->disconnectWorksheets();
        }
        $this->getJson(route('reemplazos.personal.datos.plantilla', ['campos' => ['jornada']]))->assertUnprocessable()->assertJsonValidationErrors('campos.0');
        $this->getJson(route('reemplazos.personal.datos.plantilla'))->assertUnprocessable()->assertJsonValidationErrors('campos');
    }

    public function test_permisos_son_admin_activo_y_reporte_solo_para_quien_lo_genero(): void
    {
        $this->cargar(['rut', 'fecha_antiguedad'], [['44444444-4', '2000-01-01']]);
        $reporte = session('actualizacion_datos.reporte');
        $otroAdmin = $this->testUser(2, 3);
        $this->actingAs($otroAdmin)->withSession(['active_role' => 'admin']);
        $this->get(route('reemplazos.personal.datos.omitidos', $reporte))->assertNotFound();
        DB::table('model_has_roles')->insert(['model_type' => User::class, 'model_id' => $this->admin->id, 'role_id' => 2]);
        $this->admin->unsetRelation('roles');
        $this->actingAs($this->admin)->withSession(['active_role' => 'funcionario_ac']);
        $this->get(route('reemplazos.personal.datos.plantilla', ['campos' => ['bienios']]))->assertForbidden();
        $this->post(route('reemplazos.personal.datos.actualizar'))->assertForbidden();
        $this->get(route('reemplazos.personal.datos.omitidos', $reporte))->assertForbidden();
        $usuario = $this->testUser(3, 2);
        $this->actingAs($usuario)->withSession(['active_role' => 'funcionario_ac']);
        $this->get(route('reemplazos.personal.datos.plantilla', ['campos' => ['bienios']]))->assertForbidden();
    }

    public function test_mes_cambiado_y_excel_sin_columnas_se_rechazan(): void
    {
        $this->cargar(['rut', 'fecha_antiguedad'], [['11111111-1', '2000-01-01']], ['fecha_antiguedad'], 'xlsx', 202608)->assertSessionHasErrors('excel_actualizacion');
        $this->cargar(['rut', 'Bienios'], [['11111111-1', 8]], ['fecha_antiguedad'])->assertSessionHasErrors('excel_actualizacion');
        $this->assertDatabaseCount('padron_individual_cambios', 0);
    }

    public function test_fallo_de_auditoria_revierte_datos_y_copias_de_documentos(): void
    {
        $this->crearDocumento();
        $antes = DB::table('reemplazos_personal')->get()->toJson();
        $fallar = true;
        DB::listen(function (QueryExecuted $q) use (&$fallar) {
            if ($fallar && str_starts_with($q->sql, 'insert into "padron_individual_cambios"')) { $fallar = false; throw new \RuntimeException('Fallo sintético de auditoría'); }
        });
        try {
            app(PadronDatosActualizacionService::class)->actualizar([['rut' => '111111111', 'rut_excel' => '11111111-1', 'fila' => 2, 'datos' => ['bienios' => 5]]], ['bienios'], 202609, $this->admin);
            $this->fail('Debe revertir la actualización.');
        } catch (\RuntimeException $e) { $this->assertSame('Fallo sintético de auditoría', $e->getMessage()); }
        $this->assertSame($antes, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertNull(DB::table('solicitudes_reemplazo')->value('padron_personal_snapshot'));
        $this->assertDatabaseCount('padron_individual_cambios', 0);
    }

    public function test_conserva_copia_historica_y_adquiere_control_antes_de_leer_personal(): void
    {
        $this->crearDocumento();
        $queries = [];
        DB::listen(function (QueryExecuted $q) use (&$queries) { if (DB::transactionLevel() > 0) { $queries[] = $q->sql; } });
        $this->cargar(['rut', 'fecha_antiguedad'], [['11111111-1', '2000-01-01']])->assertSessionHas('actualizacion_datos.registros_actualizados', 2);
        $this->assertStringContainsString('padron_aplicacion_control', $queries[0]);
        $snapshot = json_decode(DB::table('solicitudes_reemplazo')->value('padron_personal_snapshot'), true);
        $this->assertSame('2010-03-01', $snapshot['personal']['fecha_antiguedad']);
        $this->assertSame(1, DB::table('solicitudes_reemplazo')->value('reemplazo_personal_id'));
    }

    public function test_si_no_se_guarda_informe_no_se_aplican_cambios(): void
    {
        $file = $this->archivo(['rut', 'fecha_antiguedad'], [['11111111-1', '2000-01-01'], ['44444444-4', '2000-01-01']]);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('exists')->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        $this->post(route('reemplazos.personal.datos.actualizar'), ['campos' => ['fecha_antiguedad'], 'periodo' => 202609, 'excel_actualizacion' => $file])->assertSessionHasErrors('excel_actualizacion');
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 1, 'fecha_antiguedad' => '2010-03-01']);
        $this->assertDatabaseCount('padron_individual_cambios', 0);
    }

    public function test_modal_y_resumen_siguen_estructura_y_muestran_campos_y_periodo(): void
    {
        Storage::disk('local')->put('ui/layouts/app.blade.php', '@yield("content") @stack("scripts")');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('ui'));
        $html = view('reemplazos.personal.import', ['errors' => new ViewErrorBag])->render();
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$html); $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//button[@data-bs-target="#actualizar-datos-personal"]')->length);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(4, $xpath->query('//*[@data-padron-datos-modal]//input[@name="campos[]"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-padron-datos-modal]//input[@name="periodo" and @value="202609"]')->length);
        $this->assertSame(1, $xpath->query('//a[@data-padron-template and @data-template-url]')->length);
        $this->assertStringContainsString('campos', $xpath->query('//a[@data-padron-template]')->item(0)->getAttribute('href'));
        $this->assertStringNotContainsString('_token', $xpath->query('//a[@data-padron-template]')->item(0)->getAttribute('href'));
        foreach ($xpath->query('//*[@data-padron-datos-modal]//input[not(@type="hidden")]') as $input) {
            $this->assertSame(1, $xpath->query('//label[@for="'.$input->getAttribute('id').'"]')->length);
        }
        $this->assertStringContainsString('modal-dialog-scrollable', $html);
        $this->assertStringContainsString('Los RUT no encontrados se omiten', $html);
    }

    private function crearDocumento(): void
    {
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) { $t->id(); $t->integer('reemplazo_personal_id'); $t->json('padron_personal_snapshot')->nullable(); });
        DB::table('solicitudes_reemplazo')->insert(['id' => 1, 'reemplazo_personal_id' => 1]);
    }

    private function cargar(array $headers, array $rows, array $campos = ['fecha_antiguedad'], string $tipo = 'xlsx', int $periodo = 202609)
    {
        return $this->post(route('reemplazos.personal.datos.actualizar'), ['campos' => $campos, 'periodo' => $periodo, 'excel_actualizacion' => $this->archivo($headers, $rows, $tipo)]);
    }

    private function archivo(array $headers, array $rows, string $tipo = 'xlsx'): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([$headers, ...$rows], null, 'A1', true);
        $path = Storage::disk('local')->path('entrada-'.bin2hex(random_bytes(6)).'.'.$tipo);
        ($tipo === 'xls' ? new Xls($book) : new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return new UploadedFile($path, 'datos-prueba.'.$tipo, null, null, true);
    }

    private function leerRespuesta(string $content): Spreadsheet
    {
        $path = 'respuesta-'.bin2hex(random_bytes(6)).'.xlsx';
        Storage::disk('local')->put($path, $content);
        return IOFactory::load(Storage::disk('local')->path($path));
    }
}
