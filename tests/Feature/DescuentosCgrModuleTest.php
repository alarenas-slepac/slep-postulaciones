<?php

namespace Tests\Feature;

use App\Exports\DescuentosCgrMensualExport;
use App\Http\Controllers\Remuneraciones\DescuentoCgrController;
use App\Mail\DescuentoCgrEtapaMail;
use App\Http\Requests\Remuneraciones\GuardarDescuentoCgrRequest;
use App\Models\DescuentoCgr;
use App\Models\DescuentoCgrNotificacion;
use App\Models\UtmValor;
use App\Services\Remuneraciones\CronogramaDescuentoCgrService;
use App\Services\Remuneraciones\DescuentoCgrPdfService;
use App\Services\Remuneraciones\DescuentoCgrWorkflowService;
use App\Services\Remuneraciones\DescuentoCgrCertificadoService;
use App\Services\Remuneraciones\DescuentoCgrExpedienteService;
use App\Services\Remuneraciones\ReemplazoPersonalRutService;
use App\Services\Remuneraciones\UtmImportService;
use App\Support\ModuleRegistry;
use App\Support\SlepUiRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

        Schema::dropIfExists('funcionarios_ac_autorizados');
        Schema::create('funcionarios_ac_autorizados', function (Blueprint $table) {
            $table->id();
            $table->string('periodo_nomina', 20)->nullable();
            $table->string('run_normalizado', 30)->nullable();
            $table->string('rut_normalizado', 30)->nullable();
            $table->string('nombres', 180)->nullable();
            $table->string('apellido_paterno', 120)->nullable();
            $table->string('apellido_materno', 120)->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('descuentos_cgr_documentos_mensuales');
        Schema::dropIfExists('descuentos_cgr');
        Schema::create('descuentos_cgr', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 12);
            $table->string('nombre');
            $table->string('origen_funcionario', 30)->nullable()->index();
            $table->string('estado', 32)->default('ingresado');
            $table->string('institucion_reintegro')->nullable();
            $table->string('estamento_funcionario')->nullable();
            $table->timestamp('enviado_finanzas_en')->nullable();
            $table->timestamp('enviado_auditoria_en')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamp('certificado_generado_en')->nullable();
            $table->unsignedBigInteger('certificado_generado_por_id')->nullable();
            $table->string('certificado_firmado_path')->nullable();
            $table->string('certificado_firmado_nombre')->nullable();
            $table->timestamp('certificado_firmado_en')->nullable();
            $table->unsignedBigInteger('certificado_firmado_por_id')->nullable();
            $table->string('numero_resolucion', 100);
            $table->string('numero_resolucion_clave', 100)->nullable()->unique();
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

        Schema::create('descuentos_cgr_archivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('descuento_cgr_id');
            $table->unsignedSmallInteger('numero_cuota');
            $table->string('tipo', 32);
            $table->uuid('grupo_archivo');
            $table->string('path');
            $table->string('nombre_original');
            $table->unsignedBigInteger('tamano');
            $table->string('folio')->nullable();
            $table->date('fecha_reintegro')->nullable();
            $table->unsignedBigInteger('monto_reintegro_pesos')->nullable();
            $table->unsignedBigInteger('cargado_por_id')->nullable();
            $table->timestamps();
            $table->unique(['descuento_cgr_id', 'numero_cuota', 'tipo']);
        });

        Schema::create('descuentos_cgr_notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 32)->unique();
            $table->text('correos_adicionales')->nullable();
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
        Schema::dropIfExists('descuentos_cgr_notificaciones');
        Schema::dropIfExists('descuentos_cgr_archivos');
        Schema::dropIfExists('descuentos_cgr_documentos_mensuales');
        Schema::dropIfExists('descuentos_cgr');
        Schema::dropIfExists('funcionarios_ac_autorizados');
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
        $this->assertSame('el padrón de reemplazos personal', $resultado['fuente']);
        $this->assertSame(ReemplazoPersonalRutService::ORIGEN_ESTABLECIMIENTO, $resultado['origen']);
    }

    public function test_busqueda_prioriza_funcionario_ac_y_admite_run_normalizado_historico(): void
    {
        \App\Models\ReemplazoPersonal::create([
            'rut' => '12345678-5',
            'nombre' => 'Nombre desde reemplazos personal',
            'anio' => 2026,
            'mes' => 8,
        ]);

        DB::table('funcionarios_ac_autorizados')->insert([
            'periodo_nomina' => '2026-08',
            'run_normalizado' => '123456785',
            'nombres' => '  Persona   Administración ',
            'apellido_paterno' => ' Central ',
            'apellido_materno' => ' Autorizada ',
        ]);

        $resultado = app(ReemplazoPersonalRutService::class)->buscar('12.345.678-5');

        $this->assertSame('12345678-5', $resultado['rut']);
        $this->assertSame('Persona Administración Central Autorizada', $resultado['nombre']);
        $this->assertSame('2026-08', $resultado['periodo']);
        $this->assertSame('funcionarios autorizados de Administración Central', $resultado['fuente']);
        $this->assertSame(ReemplazoPersonalRutService::ORIGEN_ADMINISTRACION_CENTRAL, $resultado['origen']);
    }

    public function test_busqueda_funcionario_ac_usa_rut_normalizado_del_esquema_actual(): void
    {
        DB::table('funcionarios_ac_autorizados')->insert([
            'rut_normalizado' => '111111111',
            'nombres' => 'Nombre',
            'apellido_paterno' => 'Apellido Uno',
            'apellido_materno' => 'Apellido Dos',
        ]);

        $resultado = app(ReemplazoPersonalRutService::class)->buscar('11.111.111-1');

        $this->assertSame('Nombre Apellido Uno Apellido Dos', $resultado['nombre']);
        $this->assertNull($resultado['periodo']);
    }

    public function test_rutas_permisos_y_navegacion_del_modulo(): void
    {
        foreach (['descuentos-cgr.index', 'descuentos-cgr.create', 'descuentos-cgr.funcionario.buscar', 'descuentos-cgr.destroy', 'descuentos-cgr.informe.pdf', 'descuentos-cgr.cronograma.pdf', 'descuentos-cgr.utm.index', 'descuentos-cgr.utm.importar'] as $nombre) {
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

    public function test_listado_filtra_tipo_de_funcionario_y_busca_por_nombre_o_rut(): void
    {
        $administracionCentral = $this->crearDescuento([
            'nombre' => 'Ana Administración Central',
            'rut' => '12345678-5',
            'numero_resolucion' => 'CGR-AC-2026',
            'origen_funcionario' => ReemplazoPersonalRutService::ORIGEN_ADMINISTRACION_CENTRAL,
        ]);
        $establecimiento = $this->crearDescuento([
            'nombre' => 'Bruno Escuela Ejemplo',
            'rut' => '11111111-1',
            'numero_resolucion' => 'CGR-EST-2026',
            'origen_funcionario' => ReemplazoPersonalRutService::ORIGEN_ESTABLECIMIENTO,
        ]);
        $this->crearDescuento([
            'nombre' => 'Registro Histórico',
            'rut' => '99999999-9',
            'numero_resolucion' => 'CGR-HIST-2026',
            'origen_funcionario' => null,
        ]);

        $vistaAc = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', [
            'origen' => ReemplazoPersonalRutService::ORIGEN_ADMINISTRACION_CENTRAL,
            'buscar' => 'Ana',
        ]));
        $this->assertSame([$administracionCentral->id], $vistaAc->getData()['descuentos']->pluck('id')->all());
        $this->assertStringContainsString('Administración Central', $vistaAc->render());
        $this->assertStringNotContainsString('Exportar Excel mensual', $vistaAc->render());

        $vistaEstablecimiento = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', [
            'origen' => ReemplazoPersonalRutService::ORIGEN_ESTABLECIMIENTO,
            'buscar' => '11.111.111-1',
        ]));
        $this->assertSame([$establecimiento->id], $vistaEstablecimiento->getData()['descuentos']->pluck('id')->all());

        $vistaSinClasificar = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', [
            'origen' => 'sin_clasificar',
        ]));
        $this->assertSame(['Registro Histórico'], $vistaSinClasificar->getData()['descuentos']->pluck('nombre')->all());
    }

    public function test_listado_filtra_ultimo_mes_y_mes_de_cualquier_cuota_incluso_al_cruzar_de_anio(): void
    {
        $cruzaAnio = $this->crearDescuento([
            'numero_resolucion' => 'CRUZA-2025',
            'fecha_primer_descuento' => '2025-11-01',
            'numero_cuotas' => 3,
        ]);
        $soloEnero = $this->crearDescuento([
            'numero_resolucion' => 'ENERO-2026',
            'fecha_primer_descuento' => '2026-01-01',
            'numero_cuotas' => 1,
        ]);
        $posterior = $this->crearDescuento([
            'numero_resolucion' => 'FEBRERO-2026',
            'fecha_primer_descuento' => '2026-02-01',
            'numero_cuotas' => 2,
        ]);

        $ultimoEnero = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', ['ultimo_mes' => '2026-01']));
        $this->assertEqualsCanonicalizing([$cruzaAnio->id, $soloEnero->id], $ultimoEnero->getData()['descuentos']->pluck('id')->all());

        $descuentoDiciembre = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', ['mes_descuento' => '2025-12']));
        $this->assertSame([$cruzaAnio->id], $descuentoDiciembre->getData()['descuentos']->pluck('id')->all());

        $ambos = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', [
            'ultimo_mes' => '2026-01',
            'mes_descuento' => '2026-01',
        ]));
        $this->assertEqualsCanonicalizing([$cruzaAnio->id, $soloEnero->id], $ambos->getData()['descuentos']->pluck('id')->all());
        $this->assertNotContains($posterior->id, $ambos->getData()['descuentos']->pluck('id')->all());
    }

    public function test_filtros_de_cada_pestana_se_restauran_y_se_limpian_por_separado(): void
    {
        $ingresado = $this->crearDescuento(['nombre' => 'Persona Ingresada', 'numero_resolucion' => 'ING-2026']);
        $finanzas = $this->crearDescuento(['nombre' => 'Persona Finanzas', 'numero_resolucion' => 'FIN-2026', 'estado' => 'descuentos_realizados']);
        $sesion = app('session')->driver();
        $listar = static function (array $parametros) use ($sesion) {
            $solicitud = Request::create('/descuentos-cgr', 'GET', $parametros);
            $solicitud->setLaravelSession($sesion);

            return app(DescuentoCgrController::class)->index($solicitud)->getData();
        };

        $vistaIngresados = $listar(['estado' => 'ingresado', 'filtrar' => 1, 'buscar' => 'Ingresada', 'ultimo_mes' => '2026-02']);
        $this->assertSame([$ingresado->id], $vistaIngresados['descuentos']->pluck('id')->all());

        $vistaFinanzas = $listar(['estado' => 'descuentos_realizados', 'filtrar' => 1, 'buscar' => 'Finanzas']);
        $this->assertSame([$finanzas->id], $vistaFinanzas['descuentos']->pluck('id')->all());
        $this->assertSame('', $vistaFinanzas['ultimoMes']);

        $restaurada = $listar(['estado' => 'ingresado']);
        $this->assertSame('Ingresada', $restaurada['buscar']);
        $this->assertSame('2026-02', $restaurada['ultimoMes']);
        $this->assertSame([$ingresado->id], $restaurada['descuentos']->pluck('id')->all());

        $limpia = $listar(['estado' => 'ingresado', 'limpiar' => 1]);
        $this->assertSame('', $limpia['buscar']);
        $this->assertSame('', $limpia['ultimoMes']);
        $this->assertSame('Finanzas', $listar(['estado' => 'descuentos_realizados'])['buscar']);
    }

    public function test_indicadores_de_ingresados_y_finanzas_respetan_filtros_y_documentos_por_cuota(): void
    {
        $incompleto = $this->crearDescuento(['numero_resolucion' => 'KPI-ING-1', 'nombre' => 'KPI Incompleto', 'numero_cuotas' => 2, 'deuda_definitiva_pesos' => 100000]);
        $completo = $this->crearDescuento(['numero_resolucion' => 'KPI-ING-2', 'nombre' => 'KPI Completo', 'numero_cuotas' => 1, 'deuda_definitiva_pesos' => 200000]);
        $this->crearDescuento(['numero_resolucion' => 'OTRO-ING', 'nombre' => 'Otro registro', 'deuda_definitiva_pesos' => 900000]);
        $this->registrarArchivoKpi($incompleto, 1, 'liquidacion');
        $this->registrarArchivoKpi($completo, 1, 'liquidacion');

        $ingresados = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', ['buscar' => 'KPI']))->getData()['indicadores'];
        $this->assertSame(['registros' => 2, 'deuda_pesos' => 300000, 'cuotas' => 3, 'documentos' => 2, 'listos' => 1, 'firmados' => 0, 'cierres_mes' => 0], $ingresados);

        $finanzas = $this->crearDescuento(['numero_resolucion' => 'KPI-FIN-1', 'estado' => 'descuentos_realizados', 'numero_cuotas' => 2]);
        $this->registrarArchivoKpi($finanzas, 1, 'sigfe');
        $this->registrarArchivoKpi($finanzas, 2, 'sigfe');
        $this->registrarArchivoKpi($finanzas, 1, 'tgr');
        $datosFinanzas = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', ['estado' => 'descuentos_realizados']))->getData();
        $this->assertSame(1, $datosFinanzas['indicadores']['registros']);
        $this->assertSame(2, $datosFinanzas['indicadores']['cuotas']);
        $this->assertSame(3, $datosFinanzas['indicadores']['documentos']);
        $this->assertSame(0, $datosFinanzas['indicadores']['listos']);

        $this->registrarArchivoKpi($finanzas, 2, 'tgr');
        $finanzasCompletas = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', ['estado' => 'descuentos_realizados']))->getData()['indicadores'];
        $this->assertSame(4, $finanzasCompletas['documentos']);
        $this->assertSame(1, $finanzasCompletas['listos']);
    }

    public function test_indicadores_de_auditoria_y_finalizados_reflejan_firmas_y_cierres_del_mes(): void
    {
        $auditoria = $this->crearDescuento(['numero_resolucion' => 'KPI-AUD-1', 'estado' => 'en_auditoria', 'numero_cuotas' => 2]);
        $firmado = $this->crearDescuento(['numero_resolucion' => 'KPI-AUD-2', 'estado' => 'en_auditoria', 'certificado_firmado_path' => 'certificados/firmado.pdf']);
        $this->registrarArchivoKpi($auditoria, 1, 'liquidacion_validada');
        $this->registrarArchivoKpi($firmado, 1, 'liquidacion_validada');
        $auditoriaIndicadores = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', ['estado' => 'en_auditoria']))->getData()['indicadores'];
        $this->assertSame(2, $auditoriaIndicadores['registros']);
        $this->assertSame(3, $auditoriaIndicadores['cuotas']);
        $this->assertSame(2, $auditoriaIndicadores['documentos']);
        $this->assertSame(1, $auditoriaIndicadores['firmados']);

        $this->crearDescuento(['numero_resolucion' => 'KPI-CER-1', 'estado' => 'cerrado', 'numero_cuotas' => 2, 'cerrado_en' => now()]);
        $this->crearDescuento(['numero_resolucion' => 'KPI-CER-2', 'estado' => 'cerrado', 'cerrado_en' => now()->subMonth()]);
        $vistaCerrados = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET', ['estado' => 'cerrado']));
        $cerrados = $vistaCerrados->getData();
        $this->assertSame(2, $cerrados['indicadores']['registros']);
        $this->assertSame(3, $cerrados['indicadores']['cuotas']);
        $this->assertSame(1, $cerrados['indicadores']['cierres_mes']);
        $this->assertStringContainsString('Cerrados este mes', $vistaCerrados->render());
    }

    public function test_indicadores_incluyen_registros_fuera_de_la_primera_pagina(): void
    {
        for ($numero = 1; $numero <= 21; $numero++) {
            $this->crearDescuento(['numero_resolucion' => 'KPI-PAG-'.$numero, 'deuda_definitiva_pesos' => 1000]);
        }

        $datos = app(DescuentoCgrController::class)->index(Request::create('/descuentos-cgr', 'GET'))->getData();
        $this->assertCount(20, $datos['descuentos']);
        $this->assertSame(21, $datos['indicadores']['registros']);
        $this->assertSame(21000, $datos['indicadores']['deuda_pesos']);
        $this->assertSame(21, $datos['indicadores']['cuotas']);
    }

    public function test_migracion_clasifica_registros_historicos_sin_inventar_origenes(): void
    {
        DB::table('funcionarios_ac_autorizados')->insert([
            'rut_normalizado' => '123456785',
            'nombres' => 'Persona Central',
        ]);
        DB::table('reemplazos_personal')->insert([
            'rut' => '11111111-1',
            'nombre' => 'Persona Establecimiento',
            'anio' => 2026,
            'mes' => 8,
        ]);

        $administracionCentral = $this->crearDescuento([
            'rut' => '12345678-5',
            'numero_resolucion' => 'HIST-AC-2026',
        ]);
        $establecimiento = $this->crearDescuento([
            'rut' => '11111111-1',
            'numero_resolucion' => 'HIST-EST-2026',
        ]);
        $sinClasificar = $this->crearDescuento([
            'rut' => '99999999-9',
            'numero_resolucion' => 'HIST-SIN-2026',
        ]);

        Schema::table('descuentos_cgr', function (Blueprint $table) {
            $table->dropIndex(['origen_funcionario']);
            $table->dropColumn('origen_funcionario');
        });
        $migracion = require database_path('migrations/2026_08_27_190000_add_origen_funcionario_to_descuentos_cgr_table.php');
        $migracion->up();

        $this->assertDatabaseHas('descuentos_cgr', [
            'id' => $administracionCentral->id,
            'origen_funcionario' => ReemplazoPersonalRutService::ORIGEN_ADMINISTRACION_CENTRAL,
        ]);
        $this->assertDatabaseHas('descuentos_cgr', [
            'id' => $establecimiento->id,
            'origen_funcionario' => ReemplazoPersonalRutService::ORIGEN_ESTABLECIMIENTO,
        ]);
        $this->assertDatabaseHas('descuentos_cgr', [
            'id' => $sinClasificar->id,
            'origen_funcionario' => null,
        ]);
    }

    public function test_exportador_mensual_incluye_solo_cuotas_del_periodo_y_formatea_excel(): void
    {
        UtmValor::create(['anio' => 2026, 'mes' => 2, 'valor' => 69611]);
        UtmValor::create(['anio' => 2026, 'mes' => 3, 'valor' => 69889]);
        $incluido = $this->crearDescuento([
            'rut' => '12345678-5',
            'nombre' => 'Persona con descuento vigente',
            'numero_resolucion' => 'EXPORT-2026',
            'deuda_equivalente_utm' => 4.1120,
            'cuota_utm' => 2.0560,
            'numero_cuotas' => 2,
            'tasa_interes_mensual' => 1,
            'fecha_primer_descuento' => '2026-02-01',
        ]);
        $this->crearDescuento([
            'rut' => '11111111-1',
            'nombre' => 'Persona con descuento futuro',
            'numero_resolucion' => 'FUTURO-2026',
            'fecha_primer_descuento' => '2026-04-01',
        ]);

        $periodo = CarbonImmutable::create(2026, 3, 1);
        $exportador = app(DescuentosCgrMensualExport::class);
        $filas = $exportador->rowsForPeriod($periodo);

        $this->assertCount(1, $filas);
        $fila = $filas->first();
        $this->assertSame($incluido->numero_resolucion, $fila['numero_resolucion']);
        $this->assertSame('12.345.678-5', $fila['rut']);
        $this->assertSame('03-2026', $fila['mes']);
        $this->assertSame(69889.0, $fila['valor_utm']);
        $this->assertEqualsWithDelta(2.056, $fila['saldo_inicial_utm'], 0.000001);
        $this->assertEqualsWithDelta(2.056, $fila['capital_utm'], 0.000001);
        $this->assertEqualsWithDelta(0.0, $fila['saldo_final_utm'], 0.000001);
        $this->assertEqualsWithDelta(143691.784, $fila['saldo_inicial_pesos'], 0.000001);
        $this->assertEqualsWithDelta(1436.91784, $fila['interes_pesos'], 0.000001);
        $this->assertEqualsWithDelta(145128.70184, $fila['descuento_pesos'], 0.000001);

        $libro = $exportador->workbook($filas, $periodo);
        $hoja = $libro->getActiveSheet();
        $this->assertSame('RUT', $hoja->getCell('A1')->getValue());
        $this->assertSame('Descuento total', $hoja->getCell('L1')->getValue());
        $this->assertSame('12.345.678-5', $hoja->getCell('A2')->getValue());
        $this->assertSame('EXPORT-2026', $hoja->getCell('C2')->getValue());
        $this->assertSame(145128.70184, $hoja->getCell('L2')->getValue());
        $libro->disconnectWorksheets();

        $solicitud = Request::create('/descuentos-cgr', 'GET', [
            'exportar' => 1,
            'mes_exportacion' => '2026-03',
        ]);
        $solicitud->setUserResolver(fn () => new class {
            public function hasAnyRole(array $roles): bool { return true; }
        });
        $respuesta = app(DescuentoCgrController::class)->index($solicitud);
        $this->assertInstanceOf(StreamedResponse::class, $respuesta);
        $this->assertStringContainsString('descuentos_cgr_2026_03_', (string) $respuesta->headers->get('content-disposition'));
    }

    public function test_resolucion_no_se_puede_duplicar_y_edicion_ignora_el_registro_actual(): void
    {
        $descuento = $this->crearDescuento();
        $this->assertSame('4553-2026', $descuento->numero_resolucion_clave);
        DB::table('descuentos_cgr')->insert([
            'rut' => '11111111-1',
            'nombre' => 'Registro Histórico Duplicado',
            'numero_resolucion' => $descuento->numero_resolucion,
            'numero_resolucion_clave' => null,
            'deuda_definitiva_pesos' => 100000,
            'deuda_equivalente_utm' => 2,
            'cuota_utm' => 1,
            'numero_cuotas' => 2,
            'tasa_interes_anual' => 0,
            'tasa_interes_mensual' => 0,
            'fecha_primer_descuento' => '2026-02-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $datos = [
            'rut' => '12345678-5',
            'numero_resolucion' => $descuento->numero_resolucion,
            'deuda_definitiva_pesos' => 142000,
            'deuda_equivalente_utm' => 2.0560,
            'cuota_utm' => 2.0560,
            'numero_cuotas' => 1,
            'tasa_interes_anual' => 12,
            'tasa_interes_mensual' => 1,
            'fecha_primer_descuento' => '2026-02-01',
            'resolucion_pdf' => UploadedFile::fake()->create('resolucion.pdf', 10, 'application/pdf'),
        ];

        $crear = GuardarDescuentoCgrRequest::create('/descuentos-cgr', 'POST', $datos);
        $crear->setRouteResolver(fn () => null);
        $validadorCrear = Validator::make($datos, $crear->rules(), $crear->messages(), $crear->attributes());

        $this->assertTrue($validadorCrear->fails());
        $this->assertSame(
            'La resolución ingresada ya está registrada en Descuentos CGR.',
            $validadorCrear->errors()->first('numero_resolucion')
        );

        $rutaEdicion = new class($descuento)
        {
            public function __construct(private readonly DescuentoCgr $descuento) {}

            public function parameter(string $nombre): mixed
            {
                return $nombre === 'descuentoCgr' ? $this->descuento : null;
            }
        };
        $editar = GuardarDescuentoCgrRequest::create('/descuentos-cgr/'.$descuento->id, 'PUT', $datos);
        $editar->setRouteResolver(fn () => $rutaEdicion);
        $validadorEditar = Validator::make($datos, $editar->rules(), $editar->messages(), $editar->attributes());

        $this->assertFalse($validadorEditar->fails());
    }

    public function test_eliminacion_borra_descuento_cronograma_persistido_y_resolucion_pdf(): void
    {
        Storage::fake('local');
        $descuento = $this->crearDescuento();
        Storage::disk('local')->put($descuento->resolucion_pdf_path, '%PDF-resolucion');
        $documento = $descuento->documentosMensuales()->create([
            'numero_cuota' => 1,
            'periodo' => '2026-02-01',
            'codigo_verificacion' => 'CGR-M-PRUEBA-ELIMINACION',
            'documento_hash' => str_repeat('a', 64),
            'documento_emitido_en' => now(),
        ]);

        $solicitud = Request::create('/descuentos-cgr/'.$descuento->id, 'DELETE');
        $solicitud->setUserResolver(fn () => new class {
            public function hasAnyRole(array $roles): bool { return true; }
        });
        request()->setUserResolver($solicitud->getUserResolver());
        $respuesta = app(DescuentoCgrController::class)->destroy($descuento);

        $this->assertSame(route('descuentos-cgr.index'), $respuesta->getTargetUrl());
        $this->assertDatabaseMissing('descuentos_cgr', ['id' => $descuento->id]);
        $this->assertDatabaseMissing('descuentos_cgr_documentos_mensuales', ['id' => $documento->id]);
        Storage::disk('local')->assertMissing($descuento->resolucion_pdf_path);
    }

    public function test_vistas_ofrecen_eliminacion_con_confirmacion(): void
    {
        foreach (['index', 'show'] as $vista) {
            $contenido = file_get_contents(resource_path("views/remuneraciones/descuentos-cgr/{$vista}.blade.php"));

            $this->assertStringContainsString("route('descuentos-cgr.destroy'", $contenido);
            $this->assertStringContainsString("@method('DELETE')", $contenido);
            $this->assertStringContainsString('Esta acción no se puede deshacer', $contenido);
        }
    }

    public function test_informe_pdf_genera_verificacion_y_detecta_cambios_posteriores(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('descuentos-cgr/resoluciones/2026/resolucion.pdf', '%PDF-resolucion');
        UtmValor::create(['anio' => 2026, 'mes' => 2, 'valor' => 69611]);

        $descuento = DescuentoCgr::create([
            'rut' => '12345678-5',
            'nombre' => 'Persona Ejemplo',
            'origen_funcionario' => ReemplazoPersonalRutService::ORIGEN_ESTABLECIMIENTO,
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

    public function test_logos_de_informes_pdf_conservan_su_proporcion_original(): void
    {
        foreach (['informe', 'mensual'] as $vista) {
            $contenido = file_get_contents(resource_path("views/pdf/descuentos-cgr/{$vista}.blade.php"));

            $this->assertStringContainsString('.logo { height: auto; width: 80px; }', $contenido);
            $this->assertStringNotContainsString('max-height:', $contenido);
        }
    }

    public function test_flujo_exige_respaldo_por_cuota_y_permite_comprobante_compartido(): void
    {
        Storage::fake('local');
        $descuento = $this->crearDescuento(['numero_cuotas' => 2]);
        $flujo = app(DescuentoCgrWorkflowService::class);

        $flujo->guardarArchivos($descuento, 'liquidacion', [[
            'archivo' => UploadedFile::fake()->create('liquidacion-1.pdf', 10, 'application/pdf'), 'cuotas' => [1],
        ]], 1);
        $this->assertFalse($flujo->completos($descuento, ['liquidacion']));
        try {
            $flujo->avanzar($descuento, 'ingresado', 'descuentos_realizados', ['liquidacion'], 'enviado_finanzas_en', 'funcionario_daf', 'finanzas');
            $this->fail('El envío sin todas las liquidaciones debió fallar.');
        } catch (ValidationException) {
            $this->assertSame('ingresado', $descuento->fresh()->estadoActual());
        }

        $flujo->guardarArchivos($descuento, 'liquidacion', [[
            'archivo' => UploadedFile::fake()->create('liquidacion-2.pdf', 10, 'application/pdf'), 'cuotas' => [2],
        ]], 1);
        $flujo->avanzar($descuento, 'ingresado', 'descuentos_realizados', ['liquidacion'], 'enviado_finanzas_en', 'funcionario_daf', 'finanzas');
        $this->assertSame('descuentos_realizados', $descuento->fresh()->estadoActual());

        foreach (['sigfe', 'tgr'] as $tipo) {
            $flujo->guardarArchivos($descuento, $tipo, [[
                'archivo' => UploadedFile::fake()->create($tipo.'.pdf', 10, 'application/pdf'), 'cuotas' => [1, 2],
            ]], 2, ['folio' => 'F-1', 'fecha_reintegro' => '2026-05-01', 'monto_reintegro_pesos' => 100000]);
        }
        $this->assertTrue($flujo->completos($descuento, ['sigfe', 'tgr']));
        $this->assertSame(0, $descuento->archivos()->where('tipo', 'transferencia_institucion')->count());
        $this->assertSame(1, $descuento->archivos()->where('tipo', 'sigfe')->distinct()->count('grupo_archivo'));
        $flujo->avanzar($descuento, 'descuentos_realizados', 'en_auditoria', ['sigfe', 'tgr'], 'enviado_auditoria_en', 'auditoria_slep', 'auditoria');
        $this->assertSame('en_auditoria', $descuento->fresh()->estadoActual());

        $this->expectException(ValidationException::class);
        $flujo->guardarArchivos($descuento, 'sigfe', [[
            'archivo' => UploadedFile::fake()->create('tarde.pdf', 10, 'application/pdf'), 'cuotas' => [1],
        ]], 2);
    }

    public function test_finanzas_asocia_y_corrige_transferencia_opcional_en_varias_cuotas(): void
    {
        Storage::fake('local');
        $descuento = $this->crearDescuento(['numero_cuotas' => 2, 'estado' => 'descuentos_realizados']);
        $flujo = app(DescuentoCgrWorkflowService::class);

        $flujo->guardarArchivos($descuento, 'transferencia_institucion', [[
            'archivo' => UploadedFile::fake()->create('transferencia.pdf', 10, 'application/pdf'),
            'cuotas' => [1, 2],
        ]], 2, ['folio' => 'TR-1', 'fecha_reintegro' => '2026-03-15', 'monto_reintegro_pesos' => 150000]);

        $archivos = $descuento->archivos()->where('tipo', 'transferencia_institucion')->orderBy('numero_cuota')->get();
        $this->assertCount(2, $archivos);
        $this->assertSame($archivos[0]->grupo_archivo, $archivos[1]->grupo_archivo);
        $this->assertSame('TR-1', $archivos[0]->folio);
        $this->assertSame('2026-03-15', $archivos[0]->fecha_reintegro->format('Y-m-d'));
        $this->assertSame(150000, $archivos[0]->monto_reintegro_pesos);
        Storage::disk('local')->assertExists($archivos[0]->path);

        $flujo->guardarArchivos($descuento, 'transferencia_institucion', [[
            'archivo' => UploadedFile::fake()->create('transferencia-corregida.pdf', 10, 'application/pdf'),
            'cuotas' => [1],
        ]], 2, ['folio' => 'TR-2', 'fecha_reintegro' => '2026-03-16', 'monto_reintegro_pesos' => 75000]);

        $this->assertSame('TR-2', $descuento->archivos()->where('tipo', 'transferencia_institucion')->where('numero_cuota', 1)->value('folio'));
        $this->assertSame('TR-1', $descuento->archivos()->where('tipo', 'transferencia_institucion')->where('numero_cuota', 2)->value('folio'));
        Storage::disk('local')->assertExists($archivos[0]->path);

        $descuento->update(['estado' => 'en_auditoria']);
        $this->expectException(ValidationException::class);
        $flujo->guardarArchivos($descuento, 'transferencia_institucion', [[
            'archivo' => UploadedFile::fake()->create('fuera-de-etapa.pdf', 10, 'application/pdf'),
            'cuotas' => [1],
        ]], 2);
    }

    public function test_expediente_zip_organiza_documentos_y_no_duplica_comprobantes_compartidos(): void
    {
        Storage::fake('local');
        $descuento = $this->crearDescuento(['numero_cuotas' => 3, 'estado' => 'en_auditoria']);
        $documentos = [
            ['liquidacion', [1], 'liquidacion.pdf'],
            ['sigfe', [1, 3], 'sigfe-compartido.pdf'],
            ['tgr', [2], 'tgr.pdf'],
            ['transferencia_institucion', [1, 2, 3], 'transferencia-compartida.pdf'],
            ['liquidacion_validada', [2], 'validada.pdf'],
        ];

        foreach ($documentos as [$tipo, $cuotas, $nombre]) {
            $path = 'descuentos-cgr/pruebas/'.$nombre;
            Storage::disk('local')->put($path, 'PDF de prueba: '.$tipo);
            $grupo = (string) \Illuminate\Support\Str::uuid();
            foreach ($cuotas as $cuota) {
                $descuento->archivos()->create([
                    'numero_cuota' => $cuota,
                    'tipo' => $tipo,
                    'grupo_archivo' => $grupo,
                    'path' => $path,
                    'nombre_original' => $nombre,
                    'tamano' => 20,
                    'folio' => 'F-1',
                ]);
            }
        }

        $path = app(DescuentoCgrExpedienteService::class)->generar($descuento);
        $zip = new \ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $nombres = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombres[] = $zip->getNameIndex($i);
            }

            $pdfs = array_values(array_filter($nombres, fn (string $nombre) => str_ends_with($nombre, '.pdf')));
            $this->assertCount(5, $pdfs);
            $this->assertContains('01-liquidaciones/liquidacion-12345678-5-02-2026.pdf', $pdfs);
            $this->assertContains('02-comprobantes-sigfe/comprobante-sigfe-12345678-5-02-2026_a_04-2026-2-meses.pdf', $pdfs);
            $this->assertContains('04-transferencias-otras-instituciones/comprobante-transferencia-12345678-5-02-2026_a_04-2026-3-meses.pdf', $pdfs);
            $this->assertSame('PDF de prueba: sigfe', $zip->getFromName('02-comprobantes-sigfe/comprobante-sigfe-12345678-5-02-2026_a_04-2026-2-meses.pdf'));
            $indice = $zip->getFromName('indice-documentos.csv');
            $this->assertStringContainsString('02-2026, 04-2026', $indice);
            $this->assertStringContainsString('1, 3', $indice);
        } finally {
            $zip->close();
            @unlink($path);
        }
    }

    public function test_descarga_de_expediente_solo_aparece_en_las_etapas_permitidas(): void
    {
        $ruta = app('router')->getRoutes()->getByName('descuentos-cgr.expediente.zip');
        $this->assertNotNull($ruta);
        $this->assertContains('ensure.role:admin|funcionario_slep|funcionario_daf|auditoria_slep', $ruta->gatherMiddleware());

        $descuento = $this->crearDescuento(['estado' => 'en_auditoria']);
        $usuario = \Mockery::mock();
        $usuario->shouldReceive('hasAnyRole')->andReturn(false, true, true);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $usuario);
        $controlador = app(\App\Http\Controllers\Remuneraciones\DescuentoCgrWorkflowController::class);

        try {
            $controlador->descargarExpediente($request, $descuento, app(DescuentoCgrExpedienteService::class));
            $this->fail('Finanzas no debe descargar el expediente en Auditoría.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }

        $descuento->update(['estado' => 'cerrado']);
        Storage::fake('local');
        $respuesta = $controlador->descargarExpediente($request, $descuento, app(DescuentoCgrExpedienteService::class));
        $this->assertSame('application/zip', $respuesta->headers->get('Content-Type'));
        @unlink($respuesta->getFile()->getPathname());
    }

    public function test_certificado_reemplaza_solo_contenido_y_repite_fila_de_plantilla(): void
    {
        UtmValor::create(['anio' => 2026, 'mes' => 2, 'valor' => 69611]);
        UtmValor::create(['anio' => 2026, 'mes' => 3, 'valor' => 69889]);
        $descuento = $this->crearDescuento(['numero_cuotas' => 2, 'estamento_funcionario' => 'Docente']);
        $auditor = new \App\Models\User(['nombres' => 'Auditor', 'apellido_paterno' => 'Ejemplo']);
        $contenido = app(DescuentoCgrCertificadoService::class)->generar($descuento, $auditor);
        $temporal = tempnam(sys_get_temp_dir(), 'cgr_test_');
        file_put_contents($temporal, $contenido);
        $plantilla = new \ZipArchive;
        $generado = new \ZipArchive;
        try {
            $this->assertTrue($plantilla->open(resource_path('templates/descuentos-cgr/certificado-auditoria.docx')));
            $this->assertTrue($generado->open($temporal));
            $xml = $generado->getFromName('word/document.xml');
            $this->assertStringContainsString('Auditor Ejemplo', $xml);
            $this->assertStringContainsString('Docente', $xml);
            $this->assertStringNotContainsString('{Nombre completo}', $xml);
            $this->assertStringNotContainsString('{Capital $}', $xml);
            $this->assertSame($plantilla->getFromName('word/styles.xml'), $generado->getFromName('word/styles.xml'));
            $this->assertSame($plantilla->getFromName('word/media/image1.png'), $generado->getFromName('word/media/image1.png'));
        } finally {
            $plantilla->close();
            $generado->close();
            @unlink($temporal);
        }
    }

    public function test_certificado_incluye_transferencia_solo_si_finanzas_cargo_comprobante(): void
    {
        UtmValor::create(['anio' => 2026, 'mes' => 2, 'valor' => 69611]);
        UtmValor::create(['anio' => 2026, 'mes' => 3, 'valor' => 69889]);
        $descuento = $this->crearDescuento([
            'numero_cuotas' => 2,
            'institucion_reintegro' => 'Institución de prueba',
        ]);
        $auditor = new \App\Models\User(['nombres' => 'Auditor']);
        $servicio = app(DescuentoCgrCertificadoService::class);
        $sinTransferencia = $this->textoCertificado($servicio->generar($descuento, $auditor));
        $this->assertStringNotContainsString('comprobantes de transferencia a', $sinTransferencia);

        $descuento->archivos()->create([
            'numero_cuota' => 2,
            'tipo' => 'transferencia_institucion',
            'grupo_archivo' => (string) \Illuminate\Support\Str::uuid(),
            'path' => 'descuentos-cgr/pruebas/transferencia.pdf',
            'nombre_original' => 'transferencia.pdf',
            'tamano' => 100,
            'folio' => 'TR-1',
            'fecha_reintegro' => '2026-03-15',
            'monto_reintegro_pesos' => 150000,
        ]);
        $conTransferencia = $this->textoCertificado($servicio->generar($descuento, $auditor));
        $this->assertStringContainsString('comprobantes de transferencia a Institución de prueba correspondientes a marzo 2026', $conTransferencia);
    }

    public function test_certificado_no_declara_reintegro_completo_si_falta_valor_utm(): void
    {
        $descuento = $this->crearDescuento();
        $auditor = new \App\Models\User(['nombres' => 'Auditor']);

        $this->expectException(ValidationException::class);
        app(DescuentoCgrCertificadoService::class)->generar($descuento, $auditor);
    }

    public function test_notificaciones_aceptan_varios_correos_sin_duplicados_y_rutas_restringen_acciones(): void
    {
        DescuentoCgrNotificacion::create([
            'evento' => 'finanzas',
            'correos_adicionales' => "uno@example.test; dos@example.test\nUNO@example.test",
        ]);
        $this->assertSame(['uno@example.test', 'dos@example.test'], DescuentoCgrNotificacion::destinatarios('finanzas', 'funcionario_daf'));

        foreach ([
            'descuentos-cgr.liquidaciones' => 'ensure.role:admin|funcionario_slep',
            'descuentos-cgr.comprobantes' => 'ensure.role:admin|funcionario_daf',
            'descuentos-cgr.auditoria.liquidaciones' => 'ensure.role:admin|auditoria_slep',
            'descuentos-cgr.auditoria.cerrar' => 'ensure.role:admin|auditoria_slep',
            'descuentos-cgr.notificaciones.update' => 'ensure.role:admin',
        ] as $nombre => $middleware) {
            $ruta = app('router')->getRoutes()->getByName($nombre);
            $this->assertNotNull($ruta);
            $this->assertContains($middleware, $ruta->gatherMiddleware());
        }
    }

    public function test_migracion_agrega_estado_ingresado_a_registros_historicos(): void
    {
        Schema::dropIfExists('descuentos_cgr_archivos');
        Schema::dropIfExists('descuentos_cgr_notificaciones');
        Schema::dropIfExists('descuentos_cgr');
        Schema::create('descuentos_cgr', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
        });
        $id = DB::table('descuentos_cgr')->insertGetId(['nombre' => 'Registro histórico de prueba']);
        $migracion = require database_path('migrations/2026_09_25_150000_add_flujo_reintegro_descuentos_cgr.php');
        $migracion->up();

        $this->assertSame('ingresado', DB::table('descuentos_cgr')->where('id', $id)->value('estado'));
        $this->assertTrue(Schema::hasTable('descuentos_cgr_archivos'));
        $this->assertTrue(Schema::hasTable('descuentos_cgr_notificaciones'));
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('descuentos_cgr', 'estado'));
    }

    public function test_detalle_muestra_estado_cronograma_y_respaldo_pendiente(): void
    {
        $descuento = $this->crearDescuento();
        $html = app(DescuentoCgrController::class)->show($descuento, app(CronogramaDescuentoCgrService::class))->render();

        $this->assertStringContainsString('Avance del cronograma', $html);
        $this->assertStringContainsString('Ingresado', $html);
        $this->assertStringContainsString('Liquidación', $html);
        $this->assertStringContainsString('Pendiente', $html);
    }

    public function test_controles_de_finanzas_y_auditoria_se_renderizan_por_etapa(): void
    {
        $descuentoCgr = $this->crearDescuento();
        $calculo = app(CronogramaDescuentoCgrService::class)->calcular($descuentoCgr);
        $archivosPorCuota = collect();
        $completos = fn (string $tipo) => false;
        $errors = new \Illuminate\Support\ViewErrorBag;

        $estado = 'descuentos_realizados';
        $puedeRegistrar = false;
        $puedeFinanzas = true;
        $puedeAuditoria = false;
        $finanzas = view('remuneraciones.descuentos-cgr._workflow', compact('descuentoCgr', 'calculo', 'archivosPorCuota', 'completos', 'errors', 'estado', 'puedeRegistrar', 'puedeFinanzas', 'puedeAuditoria'))->render();
        $this->assertStringContainsString('Comprobante de reintegro SIGFE', $finanzas);
        $this->assertStringContainsString('Comprobante de reintegro a TGR', $finanzas);
        $this->assertStringContainsString('Comprobante de transferencia a otra institución', $finanzas);
        $this->assertStringContainsString('Esta carga es opcional', $finanzas);

        $estado = 'en_auditoria';
        $puedeFinanzas = false;
        $puedeAuditoria = true;
        $auditoria = view('remuneraciones.descuentos-cgr._workflow', compact('descuentoCgr', 'calculo', 'archivosPorCuota', 'completos', 'errors', 'estado', 'puedeRegistrar', 'puedeFinanzas', 'puedeAuditoria'))->render();
        $this->assertStringContainsString('Liquidaciones validadas', $auditoria);
        $this->assertStringContainsString('Generar certificado Word', $auditoria);
    }

    public function test_correo_de_cambio_de_etapa_utiliza_layout_institucional(): void
    {
        $descuento = $this->crearDescuento(['estado' => 'descuentos_realizados']);
        $html = (new DescuentoCgrEtapaMail($descuento, 'finanzas'))->render();

        $this->assertStringContainsString('Descuento CGR para Finanzas', $html);
        $this->assertStringContainsString('Plataforma SLEP', $html);
        $this->assertStringContainsString($descuento->numero_resolucion, $html);
    }

    private function crearDescuento(array $atributos = []): DescuentoCgr
    {
        return DescuentoCgr::create(array_merge([
            'rut' => '12345678-5',
            'nombre' => 'Persona Ejemplo',
            'origen_funcionario' => ReemplazoPersonalRutService::ORIGEN_ESTABLECIMIENTO,
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
        ], $atributos));
    }

    private function registrarArchivoKpi(DescuentoCgr $descuento, int $cuota, string $tipo): void
    {
        DB::table('descuentos_cgr_archivos')->insert([
            'descuento_cgr_id' => $descuento->id,
            'numero_cuota' => $cuota,
            'tipo' => $tipo,
            'grupo_archivo' => (string) \Illuminate\Support\Str::uuid(),
            'path' => "descuentos-cgr/pruebas/{$tipo}-{$descuento->id}-{$cuota}.pdf",
            'nombre_original' => 'documento.pdf',
            'tamano' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function textoCertificado(string $contenido): string
    {
        $temporal = tempnam(sys_get_temp_dir(), 'cgr_xml_');
        file_put_contents($temporal, $contenido);
        $zip = new \ZipArchive;
        try {
            $this->assertTrue($zip->open($temporal));

            $documento = new \DOMDocument;
            $documento->loadXML((string) $zip->getFromName('word/document.xml'));

            return $documento->textContent;
        } finally {
            $zip->close();
            @unlink($temporal);
        }
    }
}
