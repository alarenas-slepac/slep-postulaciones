<?php

namespace Tests\Feature;

use App\Models\DescuentoCgr;
use App\Models\UtmValor;
use App\Services\Remuneraciones\CronogramaDescuentoCgrService;
use App\Services\Remuneraciones\ReemplazoPersonalRutService;
use App\Services\Remuneraciones\UtmImportService;
use App\Support\ModuleRegistry;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DescuentosCgrModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
    }

    protected function tearDown(): void
    {
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
        foreach (['descuentos-cgr.index', 'descuentos-cgr.create', 'descuentos-cgr.funcionario.buscar', 'descuentos-cgr.utm.index', 'descuentos-cgr.utm.importar'] as $nombre) {
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
}
