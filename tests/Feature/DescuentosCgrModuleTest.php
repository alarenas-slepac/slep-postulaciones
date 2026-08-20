<?php

namespace Tests\Feature;

use App\Models\DescuentoCgr;
use App\Models\UtmValor;
use App\Services\Remuneraciones\CronogramaDescuentoCgrService;
use App\Services\Remuneraciones\DescuentoCgrPdfService;
use App\Services\Remuneraciones\ReemplazoPersonalRutService;
use App\Services\Remuneraciones\UtmImportService;
use App\Support\ModuleRegistry;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DescuentosCgrModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);

        Schema::dropIfExists('utm_valores');
        Schema::create('utm_valores', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->decimal('valor', 12, 2);
            $table->unsignedBigInteger('creado_por_id')->nullable();
            $table->unsignedBigInteger('actualizado_por_id')->nullable();
            $table->timestamps();
            $table->unique(['anio', 'mes']);
        });

        Schema::dropIfExists('reemplazos_personal');
        Schema::create('reemplazos_personal', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 20);
            $table->string('nombre');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->timestamps();
        });

        Schema::dropIfExists('descuentos_cgr_documentos_mensuales');
        Schema::dropIfExists('descuentos_cgr');
        Schema::create('descuentos_cgr', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 12);
            $table->string('nombre');
            $table->string('numero_resolucion', 100);
            $table->date('fecha_resolucion')->nullable();
            $table->unsignedBigInteger('deuda_definitiva_pesos');
            $table->decimal('deuda_equivalente_utm', 14, 4);
            $table->decimal('cuota_utm', 14, 4);
            $table->unsignedSmallInteger('numero_cuotas');
            $table->decimal('tasa_interes_anual', 8, 4);
            $table->decimal('tasa_interes_mensual', 8, 4);
            $table->date('fecha_primer_descuento');
            $table->string('resolucion_pdf_path')->nullable();
            $table->string('resolucion_pdf_nombre')->nullable();
            $table->unsignedBigInteger('resolucion_pdf_tamano')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('codigo_verificacion', 40)->nullable()->unique();
            $table->char('documento_hash', 64)->nullable();
            $table->timestamp('documento_emitido_en')->nullable();
            $table->unsignedBigInteger('creado_por_id')->nullable();
            $table->unsignedBigInteger('actualizado_por_id')->nullable();
            $table->timestamps();
        });

        Schema::create('descuentos_cgr_documentos_mensuales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('descuento_cgr_id');
            $table->unsignedSmallInteger('numero_cuota');
            $table->date('periodo');
            $table->string('codigo_verificacion', 40)->unique();
            $table->char('documento_hash', 64);
            $table->timestamp('documento_emitido_en');
            $table->timestamps();
            $table->unique(['descuento_cgr_id', 'numero_cuota']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('descuentos_cgr_documentos_mensuales');
        Schema::dropIfExists('descuentos_cgr');
        Schema::dropIfExists('reemplazos_personal');
        Schema::dropIfExists('utm_valores');
        parent::tearDown();
    }

    public function test_cronograma_reproduce_formulas_de_la_planilla_referencia(): void
    {
        foreach ([2 => 69611, 3 => 69889, 4 => 69889, 5 => 70588] as $mes => $valor) {
            UtmValor::create(['anio' => 2026, 'mes' => $mes, 'valor' => $valor]);
        }

        $descuento = new DescuentoCgr([
            'deuda_equivalente_utm' => 8.2240,
            'cuota_utm' => 2.0560,
            'numero_cuotas' => 4,
            'tasa_interes_mensual' => 1.0,
            'fecha_primer_descuento' => '2026-02-01',
        ]);

        $resultado = app(CronogramaDescuentoCgrService::class)->calcular($descuento);

        $this->assertCount(4, $resultado['filas']);
        $this->assertSame([], $resultado['utm_faltantes']);
        $this->assertEqualsWithDelta(0.0, $resultado['saldo_final_utm'], 0.000001);
        $this->assertEqualsWithDelta(575632.712, $resultado['totales']['capital_pesos'], 0.000001);
        $this->assertEqualsWithDelta(14360.68712, $resultado['totales']['interes_pesos'], 0.000001);
        $this->assertEqualsWithDelta(589993.39912, $resultado['totales']['descuento_pesos'], 0.000001);
    }

    public function test_cronograma_marca_periodos_sin_utm_sin_inventar_montos_en_pesos(): void
    {
        UtmValor::create(['anio' => 2026, 'mes' => 2, 'valor' => 69611]);

        $resultado = app(CronogramaDescuentoCgrService::class)->calcular(new DescuentoCgr([
            'deuda_equivalente_utm' => 4.112,
            'cuota_utm' => 2.056,
            'numero_cuotas' => 2,
            'tasa_interes_mensual' => 1,
            'fecha_primer_descuento' => '2026-02-01',
        ]));

        $this->assertSame(['03-2026'], $resultado['utm_faltantes']);
        $this->assertNull($resultado['filas'][1]['capital_pesos']);
        $this->assertTrue($resultado['filas'][1]['pendiente_utm']);
    }

    public function test_importacion_utm_es_atomica_frente_a_periodos_duplicados(): void
    {
        UtmValor::create(['anio' => 2026, 'mes' => 2, 'valor' => 69611]);
        $ruta = tempnam(sys_get_temp_dir(), 'utm_test_');
        file_put_contents($ruta, "ANIO,MES,VALOR_UTM\n2026,3,69889\n2026,2,69611\n");
        $archivo = new UploadedFile($ruta, 'valores.csv', 'text/csv', null, true);

        try {
            app(UtmImportService::class)->importar($archivo, 1);
            $this->fail('La importación debió rechazar el periodo existente.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('2026-02', implode(' ', $exception->errors()['archivo']));
        } finally {
            @unlink($ruta);
        }

        $this->assertDatabaseMissing('utm_valores', ['anio' => 2026, 'mes' => 3]);
    }

    public function test_busqueda_normaliza_rut_con_puntos_y_obtiene_nombre_del_periodo_mas_reciente(): void
    {
        \App\Models\ReemplazoPersonal::create([
            'rut' => '12345678-5',
            'nombre' => 'Persona Ejemplo Anterior',
            'anio' => 2025,
            'mes' => 12,
        ]);
        \App\Models\ReemplazoPersonal::create([
            'rut' => '12345678-5',
            'nombre' => '  Persona   Ejemplo   Vigente  ',
            'anio' => 2026,
            'mes' => 8,
        ]);

        $resultado = app(ReemplazoPersonalRutService::class)->buscar('12.345.678-5');

        $this->assertSame('12345678-5', $resultado['rut']);
        $this->assertSame('Persona Ejemplo Vigente', $resultado['nombre']);
        $this->assertSame('2026-08', $resultado['periodo']);
    }

    public function test_rutas_permisos_y_navegacion_del_modulo(): void
    {
        foreach (['descuentos-cgr.index', 'descuentos-cgr.create', 'descuentos-cgr.funcionario.buscar', 'descuentos-cgr.informe.pdf', 'descuentos-cgr.cronograma.pdf', 'descuentos-cgr.utm.index', 'descuentos-cgr.utm.importar'] as $nombre) {
            $ruta = app('router')->getRoutes()->getByName($nombre);
            $middlewares = implode('|', $ruta?->gatherMiddleware() ?? []);
            $this->assertNotNull($ruta, "No se encontró la ruta {$nombre}.");
            $this->assertStringContainsString('ensure.role:admin|funcionario_slep', $middlewares);
        }

        $this->assertSame('Remuneraciones', ModuleRegistry::defaultMeta('descuentos-cgr')['section']);
        $this->assertSame('Descuentos CGR', ModuleRegistry::defaultMeta('descuentos-cgr')['name']);

        $usuario = new class
        {
            public function canModule(string $module, ?string $role = null): bool
            {
                return false;
            }
        };
        $grupos = SlepUiRegistry::menuGroups($usuario, 'funcionario_slep');
        $labels = collect($grupos)->flatten(1)->pluck('label');
        $this->assertArrayHasKey('Remuneraciones', $grupos);
        $this->assertContains('Descuentos CGR', $labels);
        $this->assertContains('Valores UTM', $labels);
    }

    public function test_informe_pdf_genera_verificacion_y_detecta_cambios_posteriores(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('descuentos-cgr/resoluciones/2026/resolucion.pdf', '%PDF-resolucion');
        UtmValor::create(['anio' => 2026, 'mes' => 2, 'valor' => 69611]);

        $descuento = DescuentoCgr::create([
            'rut' => '12345678-5',
            'nombre' => 'Persona Ejemplo',
            'numero_resolucion' => '4553-2026',
            'fecha_resolucion' => '2026-01-15',
            'deuda_definitiva_pesos' => 142000,
            'deuda_equivalente_utm' => 2.0560,
            'cuota_utm' => 2.0560,
            'numero_cuotas' => 1,
            'tasa_interes_anual' => 12,
            'tasa_interes_mensual' => 1,
            'fecha_primer_descuento' => '2026-02-01',
            'resolucion_pdf_path' => 'descuentos-cgr/resoluciones/2026/resolucion.pdf',
            'resolucion_pdf_nombre' => 'resolucion.pdf',
            'resolucion_pdf_tamano' => 15,
        ]);

        $servicio = app(DescuentoCgrPdfService::class);
        $pdf = $servicio->generar($descuento);
        $descuento->refresh();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('/^CGR-[A-F0-9]{20}$/', $descuento->codigo_verificacion);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $descuento->documento_hash);
        $this->assertNotNull($descuento->documento_emitido_en);
        $this->assertTrue($servicio->verificarIntegridad($descuento)['integro']);

        $this->get(route('descuentos-cgr.verificar', $descuento->codigo_verificacion))
            ->assertOk()
            ->assertSee('Documento válido e íntegro.');

        $descuento->update(['deuda_definitiva_pesos' => 143000]);
        $this->assertFalse($servicio->verificarIntegridad($descuento->fresh())['integro']);
    }

    public function test_pdf_mensual_identifica_cuota_y_verifica_sus_valores(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('descuentos-cgr/resoluciones/2026/resolucion.pdf', '%PDF-resolucion');
        UtmValor::create(['anio' => 2026, 'mes' => 2, 'valor' => 69611]);

        $descuento = DescuentoCgr::create([
            'rut' => '12345678-5',
            'nombre' => 'Persona Ejemplo',
            'numero_resolucion' => '4553-2026',
            'fecha_resolucion' => '2026-01-15',
            'deuda_definitiva_pesos' => 142000,
            'deuda_equivalente_utm' => 2.0560,
            'cuota_utm' => 2.0560,
            'numero_cuotas' => 1,
            'tasa_interes_anual' => 12,
            'tasa_interes_mensual' => 1,
            'fecha_primer_descuento' => '2026-02-01',
            'resolucion_pdf_path' => 'descuentos-cgr/resoluciones/2026/resolucion.pdf',
            'resolucion_pdf_nombre' => 'resolucion.pdf',
            'resolucion_pdf_tamano' => 15,
        ]);

        $servicio = app(DescuentoCgrPdfService::class);
        $resultado = $servicio->generarMensual($descuento, 1);
        $documento = $resultado['documento']->fresh();

        $this->assertStringStartsWith('%PDF', $resultado['contenido']);
        $this->assertSame('2026-02-01', $documento->periodo->toDateString());
        $this->assertSame(1, $documento->numero_cuota);
        $this->assertMatchesRegularExpression('/^CGR-M-[A-F0-9]{20}$/', $documento->codigo_verificacion);
        $this->assertTrue($servicio->verificarIntegridadMensual($documento)['integro']);

        $this->get(route('descuentos-cgr.mensual.verificar', $documento->codigo_verificacion))
            ->assertOk()
            ->assertSee('Documento mensual válido e íntegro.');

        UtmValor::query()->where(['anio' => 2026, 'mes' => 2])->update(['valor' => 70000]);
        $this->assertFalse($servicio->verificarIntegridadMensual($documento->fresh())['integro']);
        $this->assertDatabaseHas('descuentos_cgr_documentos_mensuales', [
            'descuento_cgr_id' => $descuento->id,
            'numero_cuota' => 1,
        ]);
    }
}
