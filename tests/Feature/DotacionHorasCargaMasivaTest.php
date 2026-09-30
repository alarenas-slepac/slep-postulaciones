<?php

namespace Tests\Feature;

use App\Exports\DotacionConvivenciaHorasExport;
use App\Exports\DotacionMaximosBloqueExport;
use App\Http\Controllers\Admin\DotacionConvivenciaHorasController;
use App\Http\Controllers\Admin\DotacionMaximosBloqueController;
use App\Imports\DotacionConvivenciaHorasImport;
use App\Imports\DotacionMaximosBloqueImport;
use App\Support\DotacionConvivenciaAnual;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DotacionHorasCargaMasivaTest extends TestCase
{
    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
        });
        Schema::create('establecimiento_cursos', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            $t->boolean('activo')->default(true); $t->integer('matricula');
        });
        Schema::create('dotacion_funciones_reglas', function (Blueprint $t): void {
            $t->id(); $t->string('codigo'); $t->integer('horas_fijas'); $t->boolean('vigente')->default(true);
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            $t->integer('dotacion_funcion_regla_id')->nullable(); $t->string('asignatura_nombre')->nullable();
            $t->string('tipo_asignacion')->default('funcion_tecnico_pedagogica');
            $t->string('estado')->default('activa'); $t->decimal('horas_contrato', 8, 2);
        });
        Schema::create('dotacion_proceso_2027_configuraciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            foreach ([1, 2, 3] as $i) { $t->decimal('max_horas_bloque_'.$i, 8, 2)->nullable(); }
            $t->json('funciones_normativas')->nullable(); $t->string('decision_combinacion')->nullable();
            $t->integer('maximos_configurados_by')->nullable(); $t->timestamp('maximos_configurados_at')->nullable();
            $t->timestamps(); $t->unique(['establecimiento_id', 'anio']);
        });
        (require database_path('migrations/2026_09_30_180000_create_dotacion_convivencia_horas_table.php'))->up();
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99998, 'nombre_establecimiento' => 'Establecimiento sintético A'],
            ['id' => 2, 'rbd' => 99999, 'nombre_establecimiento' => '=Establecimiento sintético B'],
        ]);
        DB::table('dotacion_funciones_reglas')->insert(['id' => 1, 'codigo' => 'encargado_convivencia', 'horas_fijas' => 44]);
        DB::table('establecimiento_cursos')->insert([
            ['establecimiento_id' => 1, 'anio' => 2027, 'activo' => true, 'matricula' => 33],
            ['establecimiento_id' => 1, 'anio' => 2027, 'activo' => true, 'matricula' => 7],
            ['establecimiento_id' => 1, 'anio' => 2027, 'activo' => false, 'matricula' => 50],
            ['establecimiento_id' => 1, 'anio' => 2026, 'activo' => true, 'matricula' => 60],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $path) { if (is_file($path)) { unlink($path); } }
        parent::tearDown();
    }

    private function request(string $role, int $anio = 2027, ?string $path = null): Request
    {
        $files = $path ? ['archivo' => new UploadedFile($path, 'plantilla.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)] : [];
        $request = Request::create('/carga-masiva', $path ? 'POST' : 'GET', ['anio' => $anio], [], $files);
        $request->setUserResolver(fn () => new class($role) {
            public int $id = 7;
            public function __construct(private string $role) {}
            public function activeRoleName(): string { return $this->role; }
        });
        $request->setLaravelSession(app('session')->driver());
        return $request;
    }

    private function excel(Spreadsheet $book): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dotacion_horas_');
        $this->archivos[] = $path;
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return $path;
    }

    private function convivencia(): Spreadsheet
    {
        return (new DotacionConvivenciaHorasExport)->workbook(DotacionConvivenciaAnual::filas(2027), 2027);
    }

    private function maximos(): Spreadsheet
    {
        return (new DotacionMaximosBloqueExport)->workbook(app(DotacionMaximosBloqueController::class)->filas(2027), 2027);
    }

    public function test_plantilla_todos_los_rbd_matricula_anual_y_asignaciones_activas_precargadas(): void
    {
        DB::table('dotacion_docente_asignaciones')->insert([
            ['establecimiento_id' => 1, 'anio' => 2027, 'dotacion_funcion_regla_id' => 1, 'estado' => 'activa', 'horas_contrato' => 20],
            ['establecimiento_id' => 1, 'anio' => 2027, 'dotacion_funcion_regla_id' => 1, 'estado' => 'activa', 'horas_contrato' => 17.5],
            ['establecimiento_id' => 1, 'anio' => 2027, 'dotacion_funcion_regla_id' => 1, 'estado' => 'inactiva', 'horas_contrato' => 4],
            ['establecimiento_id' => 1, 'anio' => 2026, 'dotacion_funcion_regla_id' => 1, 'estado' => 'activa', 'horas_contrato' => 44],
        ]);
        $book = $this->convivencia();
        $sheet = $book->getActiveSheet();
        $this->assertSame(3, $sheet->getHighestDataRow());
        $this->assertSame('99998', $sheet->getCell('A2')->getValue());
        $this->assertSame(40, $sheet->getCell('C2')->getValue());
        $this->assertSame(37.5, $sheet->getCell('D2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B3')->getDataType());
        $this->assertSame(0, $sheet->getCell('C3')->getValue());
        $this->assertSame(2027, $book->getSheetByName('Instrucciones')->getCell('B1')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_importa_cero_y_decimales_audita_y_no_modifica_otro_anio_ni_matricula(): void
    {
        DB::table('dotacion_convivencia_horas')->insert(['establecimiento_id' => 1, 'anio' => 2026, 'horas' => 44]);
        $book = $this->convivencia();
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('C2', 999); // Referencia, no se importa.
        $sheet->setCellValue('D2', '18,50')->setCellValue('D3', 0);
        $resultado = (new DotacionConvivenciaHorasImport)->import($this->excel($book), 2027, 7);
        $this->assertSame(2, $resultado['cargadas']);
        $this->assertSame(18.5, DotacionConvivenciaAnual::horas(1, 2027));
        $this->assertSame(0.0, DotacionConvivenciaAnual::horas(2, 2027));
        $this->assertSame(44.0, DotacionConvivenciaAnual::horas(1, 2026));
        $this->assertSame(33, DB::table('establecimiento_cursos')->where('id', 1)->value('matricula'));
        $this->assertDatabaseHas('dotacion_convivencia_horas', ['establecimiento_id' => 1, 'anio' => 2027, 'created_by' => 7, 'updated_by' => 7]);
        $this->assertSame(18.5, DotacionConvivenciaAnual::filas(2027)->first()['horas']);
    }

    public function test_rechaza_archivo_completo_si_una_fila_tiene_horas_invalidas(): void
    {
        $book = $this->convivencia();
        $book->getActiveSheet()->setCellValue('D2', 18)->setCellValue('D3', 45);
        try {
            (new DotacionConvivenciaHorasImport)->import($this->excel($book), 2027, 7);
            $this->fail('No debe guardar parcialmente.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Fila 3', $e->errors()['archivo'][0]);
            $this->assertSame(0, DB::table('dotacion_convivencia_horas')->count());
        }
    }

    public function test_rechaza_rbd_desconocido_duplicado_formulas_y_precision_invalida(): void
    {
        foreach ([['A3', '88888'], ['A3', '99998'], ['D3', '=20+2'], ['D3', '22.001'], ['D3', '-1']] as [$celda, $valor]) {
            $book = $this->convivencia();
            $book->getActiveSheet()->setCellValue($celda, $valor);
            try {
                (new DotacionConvivenciaHorasImport)->import($this->excel($book), 2027, 7);
                $this->fail('Debe rechazar '.$celda.'='.$valor);
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors()['archivo']);
                $this->assertSame(0, DB::table('dotacion_convivencia_horas')->count());
            }
        }
    }

    public function test_rechaza_plantilla_de_otro_anio_o_tipo(): void
    {
        $path = $this->excel($this->convivencia());
        try {
            (new DotacionConvivenciaHorasImport)->import($path, 2026, 7);
            $this->fail('Debe rechazar otro año.');
        } catch (ValidationException $e) { $this->assertStringContainsString('año o tipo', $e->errors()['archivo'][0]); }
        $this->expectException(ValidationException::class);
        (new DotacionMaximosBloqueImport)->import($path, 2027, 7);
    }

    public function test_maximos_precargados_carga_parcial_por_celda_conserva_funciones_y_anios(): void
    {
        DB::table('dotacion_proceso_2027_configuraciones')->insert([
            ['establecimiento_id' => 1, 'anio' => 2027, 'max_horas_bloque_1' => 611, 'max_horas_bloque_2' => 116, 'max_horas_bloque_3' => 224, 'funciones_normativas' => '{"historica":true}', 'decision_combinacion' => 'sin_combinacion'],
            ['establecimiento_id' => 1, 'anio' => 2026, 'max_horas_bloque_1' => 600, 'max_horas_bloque_2' => 100, 'max_horas_bloque_3' => 200, 'funciones_normativas' => null, 'decision_combinacion' => null],
        ]);
        $book = $this->maximos();
        $sheet = $book->getActiveSheet();
        $this->assertSame(611.0, $sheet->getCell('D2')->getValue());
        $this->assertSame(40, $sheet->getCell('C2')->getValue());
        $sheet->setCellValue('D2', 620.5)->setCellValue('E2', null)->setCellValue('F2', 0);
        $resultado = (new DotacionMaximosBloqueImport)->import($this->excel($book), 2027, 7);
        $this->assertSame(['cargadas' => 1, 'omitidas' => 1], $resultado);
        $this->assertDatabaseHas('dotacion_proceso_2027_configuraciones', ['establecimiento_id' => 1, 'anio' => 2027,
            'max_horas_bloque_1' => 620.5, 'max_horas_bloque_2' => 116, 'max_horas_bloque_3' => 0, 'maximos_configurados_by' => 7,
            'funciones_normativas' => '{"historica":true}', 'decision_combinacion' => 'sin_combinacion']);
        $this->assertDatabaseHas('dotacion_proceso_2027_configuraciones', ['establecimiento_id' => 1, 'anio' => 2026, 'max_horas_bloque_1' => 600]);
    }

    public function test_maximos_no_se_guardan_si_otro_bloque_es_invalido(): void
    {
        $book = $this->maximos();
        $book->getActiveSheet()->setCellValue('D2', 611)->setCellValue('E2', 116)->setCellValue('F2', -1);
        try {
            (new DotacionMaximosBloqueImport)->import($this->excel($book), 2027, 7);
            $this->fail('Todos los bloques deben validarse antes de guardar.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('columna F', $e->errors()['archivo'][0]);
            $this->assertSame(0, DB::table('dotacion_proceso_2027_configuraciones')->count());
        }
    }

    public function test_cargas_vacias_conservan_valores_y_actualizacion_conserva_creador(): void
    {
        DB::table('dotacion_convivencia_horas')->insert(['establecimiento_id' => 1, 'anio' => 2027, 'horas' => 44, 'created_by' => 1]);
        $book = $this->convivencia();
        $book->getActiveSheet()->setCellValue('D2', null)->setCellValue('D3', 22);
        $this->assertSame(['cargadas' => 1, 'omitidas' => 1], (new DotacionConvivenciaHorasImport)->import($this->excel($book), 2027, 7));
        $this->assertSame(44.0, DotacionConvivenciaAnual::horas(1, 2027));
        $book = $this->convivencia();
        $book->getActiveSheet()->setCellValue('D2', 24);
        (new DotacionConvivenciaHorasImport)->import($this->excel($book), 2027, 7);
        $this->assertDatabaseHas('dotacion_convivencia_horas', ['establecimiento_id' => 1, 'anio' => 2027, 'horas' => 24, 'created_by' => 1, 'updated_by' => 7]);
    }

    public function test_solo_cuatro_roles_autorizados_acceden_a_ambas_cargas_y_plantillas(): void
    {
        foreach (DotacionConvivenciaAnual::ROLES as $role) {
            foreach ([DotacionConvivenciaHorasController::class, DotacionMaximosBloqueController::class] as $controller) {
                $vista = app($controller)->index($this->request($role));
                $this->assertSame(2027, $vista->getData()['anio']);
                $this->assertCount(2, $vista->getData()['filas']);
                $this->assertStringContainsString('2027.xlsx', app($controller)->plantilla($this->request($role))->headers->get('Content-Disposition'));
            }
        }
        foreach (['funcionario_directivo_estab', 'funcionario_slep', 'coordinador_plani'] as $role) {
            foreach ([DotacionConvivenciaHorasController::class, DotacionMaximosBloqueController::class] as $controller) {
                foreach (['index', 'plantilla', 'store'] as $action) {
                    try { app($controller)->{$action}($this->request($role)); $this->fail('Debe rechazar '.$role); }
                    catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
                }
            }
        }
    }

    public function test_subida_real_xlsx_y_respuesta_conservan_anio(): void
    {
        $convivencia = $this->convivencia();
        $convivencia->getActiveSheet()->setCellValue('D2', 22);
        $respuesta = app(DotacionConvivenciaHorasController::class)->store($this->request('admin', 2027, $this->excel($convivencia)));
        $this->assertStringContainsString('anio=2027', $respuesta->getTargetUrl());
        $this->assertSame(22.0, DotacionConvivenciaAnual::horas(1, 2027));
        $maximos = $this->maximos();
        $maximos->getActiveSheet()->setCellValue('D2', 611)->setCellValue('E2', 116)->setCellValue('F2', 224);
        $respuesta = app(DotacionMaximosBloqueController::class)->store($this->request('supervisor_plani', 2027, $this->excel($maximos)));
        $this->assertStringContainsString('anio=2027', $respuesta->getTargetUrl());
        $this->assertDatabaseHas('dotacion_proceso_2027_configuraciones', ['establecimiento_id' => 1, 'anio' => 2027, 'max_horas_bloque_1' => 611, 'max_horas_bloque_2' => 116, 'max_horas_bloque_3' => 224]);
    }

    public function test_rutas_tienen_middleware_de_los_cuatro_roles_y_migracion_es_repetible(): void
    {
        foreach (['convivencia', 'maximos'] as $tipo) {
            foreach (['index', 'plantilla', 'store'] as $action) {
                $route = app('router')->getRoutes()->getByName('admin.dotacion-funciones.'.$tipo.'.'.$action);
                $this->assertContains('ensure.role:'.implode('|', DotacionConvivenciaAnual::ROLES), $route->gatherMiddleware());
            }
        }
        (require database_path('migrations/2026_09_30_180000_create_dotacion_convivencia_horas_table.php'))->up();
        $this->assertTrue(Schema::hasIndex('dotacion_convivencia_horas', 'dch_est_anio_unique'));
    }
}
