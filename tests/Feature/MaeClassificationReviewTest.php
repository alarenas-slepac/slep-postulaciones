<?php

namespace Tests\Feature;

use App\Models\MaeCarga;
use App\Models\MaeCargaClasificacion;
use App\Models\MaeHomologacionColumna;
use App\Services\MaeImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MaeClassificationReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->softDeletes();
        });
        \Illuminate\Support\Facades\DB::table('users')->insert(['id' => 77]);

        Schema::create('mae_cargas', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('dominio');
            $table->string('comuna_origen')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('es_vigente')->default(false);
            $table->unsignedBigInteger('reemplaza_carga_id')->nullable();
            $table->string('motivo_reemplazo')->nullable();
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->string('hash_archivo', 64);
            $table->string('estado', 50);
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('filas_omitidas')->default(0);
            $table->unsignedInteger('filas_observadas')->default(0);
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('subido_por')->nullable();
            $table->timestamp('procesado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mae_registros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mae_carga_id');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('dominio');
            $table->string('comuna_origen')->nullable();
            $table->string('rut');
            $table->string('nombre_completo')->nullable();
            $table->decimal('dias_trab', 10, 2)->nullable();
            $table->json('datos_trabajador_json')->nullable();
            $table->decimal('total_haberes', 15, 2)->nullable();
            $table->decimal('monto_imponible', 15, 2)->nullable();
            $table->decimal('monto_tributable', 15, 2)->nullable();
            $table->decimal('imposiciones', 15, 2)->nullable();
            $table->decimal('salud', 15, 2)->nullable();
            $table->decimal('impuesto', 15, 2)->nullable();
            $table->decimal('total_descuentos_homologados', 15, 2)->default(0);
            $table->decimal('total_aportes_patronales', 15, 2)->default(0);
            $table->decimal('total_otros_descuentos', 15, 2)->default(0);
            $table->text('observaciones_importacion')->nullable();
            $table->json('raw_row_json')->nullable();
            $table->timestamps();
        });

        Schema::create('mae_registro_descuentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mae_registro_id');
            $table->unsignedInteger('orden_columna');
            $table->string('columna_origen');
            $table->string('columna_normalizada');
            $table->string('campo_canonico')->nullable();
            $table->string('grupo')->nullable();
            $table->string('subgrupo')->nullable();
            $table->string('tipo_movimiento')->default('descuento');
            $table->boolean('es_aporte_patronal')->default(false);
            $table->decimal('valor', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('mae_registro_otros_descuentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mae_registro_id');
            $table->string('columna_origen');
            $table->string('columna_normalizada');
            $table->decimal('valor', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('mae_homologacion_columnas', function (Blueprint $table) {
            $table->id();
            $table->string('columna_origen');
            $table->string('columna_normalizada');
            $table->string('campo_canonico')->nullable();
            $table->string('grupo')->nullable();
            $table->string('subgrupo')->nullable();
            $table->string('seccion_archivo')->nullable();
            $table->string('tipo_movimiento')->default('descuento');
            $table->boolean('es_aporte_patronal')->default(false);
            $table->boolean('es_guardable')->default(true);
            $table->boolean('guardar_en_resumen')->default(false);
            $table->boolean('guardar_en_detalle')->default(false);
            $table->unsignedInteger('prioridad')->default(100);
            $table->boolean('activo')->default(true);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_08_21_120000_create_mae_carga_clasificaciones_table.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('mae_carga_clasificaciones');
        Schema::dropIfExists('mae_homologacion_columnas');
        Schema::dropIfExists('mae_registro_otros_descuentos');
        Schema::dropIfExists('mae_registro_descuentos');
        Schema::dropIfExists('mae_registros');
        Schema::dropIfExists('mae_cargas');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_la_correccion_manual_se_guarda_en_la_carga_y_en_la_homologacion_general(): void
    {
        $carga = $this->createPendingReviewLoad();
        $classification = $this->createClassification($carga);

        $dispatched = app(MaeImportService::class)->confirmDiscountClassifications(
            $carga,
            [$classification->id => 'administrativo'],
            77
        );

        $this->assertTrue($dispatched);
        $this->assertSame('pendiente', $carga->fresh()->estado);
        $this->assertDatabaseHas('mae_carga_clasificaciones', [
            'id' => $classification->id,
            'categoria_detectada' => 'otros',
            'categoria_seleccionada' => 'administrativo',
            'grupo' => 'descuento',
            'subgrupo' => 'administrativo',
            'confirmado_por' => 77,
        ]);
        $this->assertDatabaseHas('mae_homologacion_columnas', [
            'columna_normalizada' => 'DESCUENTO ESPECIAL',
            'grupo' => 'descuento',
            'subgrupo' => 'administrativo',
            'guardar_en_detalle' => true,
            'prioridad' => 1000,
        ]);
    }

    public function test_confirmar_dos_veces_no_vuelve_a_habilitar_el_encolado(): void
    {
        $carga = $this->createPendingReviewLoad();
        $classification = $this->createClassification($carga);
        $service = app(MaeImportService::class);

        $this->assertTrue($service->confirmDiscountClassifications(
            $carga,
            [$classification->id => 'otros'],
            77
        ));
        $this->assertFalse($service->confirmDiscountClassifications(
            $carga->fresh(),
            [$classification->id => 'otros'],
            77
        ));
        $this->assertSame(1, MaeHomologacionColumna::query()->count());
    }

    public function test_el_analisis_de_encabezados_prepara_la_categoria_antes_de_importar(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('imports');
        $carga = $this->createPendingReviewLoad();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([[
            'RUT',
            'NOMBRE',
            'COMUNA',
            'DIAS TRAB',
            'TOTAL HABERES',
            'MONTO IMPONIBLE',
            'MONTO TRIBUTABLE',
            'DSCTO CGR REX',
            'TOTAL DESCUENTOS',
        ]]);
        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($carga->ruta_archivo));
        $spreadsheet->disconnectWorksheets();

        app(MaeImportService::class)->prepareDiscountClassifications($carga);

        $this->assertDatabaseHas('mae_carga_clasificaciones', [
            'mae_carga_id' => $carga->id,
            'orden_columna' => 8,
            'columna_normalizada' => 'DSCTO CGR REX',
            'categoria_detectada' => 'administrativo',
            'fuente_deteccion' => 'automatica',
        ]);
        $this->assertSame(1, MaeCargaClasificacion::query()->count());
        $this->assertSame('pendiente_revision', $carga->fresh()->estado);
    }

    public function test_la_importacion_usa_la_categoria_confirmada_para_el_detalle(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('imports');
        $carga = $this->createPendingReviewLoad();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['RUT', 'NOMBRE', 'COMUNA', 'DIAS TRAB', 'TOTAL HABERES', 'MONTO IMPONIBLE', 'MONTO TRIBUTABLE', 'DESCUENTO ESPECIAL', 'TOTAL DESCUENTOS'],
            ['00000000-0', 'Registro sintético', 'Coronel', 30, 1000000, 800000, 700000, 12500, 12500],
        ]);
        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($carga->ruta_archivo));
        $spreadsheet->disconnectWorksheets();

        $service = app(MaeImportService::class);
        $service->prepareDiscountClassifications($carga);
        $classification = $carga->clasificaciones()->firstOrFail();
        $service->confirmDiscountClassifications($carga, [$classification->id => 'judicial'], 77);
        $service->processMaeCarga($carga->id);

        $this->assertDatabaseHas('mae_registro_descuentos', [
            'columna_normalizada' => 'DESCUENTO ESPECIAL',
            'grupo' => 'descuento',
            'subgrupo' => 'judicial',
            'tipo_movimiento' => 'descuento',
            'es_aporte_patronal' => false,
            'valor' => 12500,
        ]);
        $this->assertSame('procesado', $carga->fresh()->estado);
        $this->assertSame(12500.0, (float) $carga->registros()->firstOrFail()->total_descuentos_homologados);
    }

    public function test_una_correccion_manual_se_propone_en_la_siguiente_carga_aunque_el_encabezado_sugiera_otra_categoria(): void
    {
        $primeraCarga = $this->createPendingReviewLoad();
        $classification = MaeCargaClasificacion::query()->create([
            'mae_carga_id' => $primeraCarga->id,
            'orden_columna' => 8,
            'columna_origen' => 'Descuento empleador especial',
            'columna_normalizada' => 'DESCUENTO EMPLEADOR ESPECIAL',
            'campo_canonico' => 'descuento_empleador_especial',
            'categoria_detectada' => 'aporte_patronal',
            'categoria_seleccionada' => 'aporte_patronal',
            'fuente_deteccion' => 'automatica',
            'grupo' => 'aporte_patronal',
            'subgrupo' => 'empleador',
            'tipo_movimiento' => 'aporte_patronal',
            'es_aporte_patronal' => true,
        ]);
        $service = app(MaeImportService::class);
        $service->confirmDiscountClassifications($primeraCarga, [$classification->id => 'judicial'], 77);

        Storage::fake('local');
        Storage::disk('local')->makeDirectory('imports');
        $segundaCarga = $this->createPendingReviewLoad();
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([[
            'RUT', 'NOMBRE', 'COMUNA', 'DIAS TRAB', 'TOTAL HABERES', 'MONTO IMPONIBLE', 'MONTO TRIBUTABLE', 'Descuento empleador especial', 'TOTAL DESCUENTOS',
        ]]);
        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($segundaCarga->ruta_archivo));
        $spreadsheet->disconnectWorksheets();

        $service->prepareDiscountClassifications($segundaCarga);
        $siguientePropuesta = $segundaCarga->clasificaciones()->firstOrFail();

        $this->assertSame('homologacion', $siguientePropuesta->fuente_deteccion);
        $this->assertSame('judicial', $siguientePropuesta->categoria_detectada);
        $this->assertFalse($siguientePropuesta->es_aporte_patronal);
    }

    private function createPendingReviewLoad(): MaeCarga
    {
        return MaeCarga::query()->create([
            'anio' => 2026,
            'mes' => 8,
            'dominio' => 'Coronel',
            'version' => 1,
            'es_vigente' => false,
            'nombre_archivo' => 'mae-prueba.xlsx',
            'ruta_archivo' => 'imports/mae-prueba.xlsx',
            'hash_archivo' => str_repeat('a', 64),
            'estado' => 'pendiente_revision',
            'subido_por' => null,
        ]);
    }

    private function createClassification(MaeCarga $carga): MaeCargaClasificacion
    {
        return MaeCargaClasificacion::query()->create([
            'mae_carga_id' => $carga->id,
            'orden_columna' => 10,
            'columna_origen' => 'Descuento especial',
            'columna_normalizada' => 'DESCUENTO ESPECIAL',
            'campo_canonico' => 'descuento_especial',
            'categoria_detectada' => 'otros',
            'categoria_seleccionada' => 'otros',
            'fuente_deteccion' => 'automatica',
            'grupo' => 'descuento',
            'subgrupo' => 'otros',
            'tipo_movimiento' => 'descuento',
            'es_aporte_patronal' => false,
        ]);
    }
}
