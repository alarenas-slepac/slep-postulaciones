<?php

namespace Tests\Feature;

use App\Models\LicenciaMedica;
use App\Models\LicenciaMedicaImportacion;
use App\Models\LicenciaMedicaImportacionError;
use App\Services\LicenciasMedicas\LicenciaEstadoMasivoService;
use App\Services\LicenciasMedicas\LicenciaEstadoService;
use App\Services\LicenciasMedicas\LicenciaSeguimientoImportService;
use App\Services\LicenciasMedicas\RutNormalizer;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class LicenciasMedicasModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        Schema::dropIfExists('licencias_medicas_importacion_errores');
        Schema::dropIfExists('licencias_medicas_historial');
        Schema::dropIfExists('licencias_medicas');
        Schema::dropIfExists('licencias_medicas_importaciones');

        Schema::create('licencias_medicas_importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->string('dimension_estado')->nullable();
            $table->string('nombre_archivo');
            $table->string('archivo_path');
            $table->string('periodo')->nullable();
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('total_importadas')->default(0);
            $table->unsignedInteger('total_actualizadas')->default(0);
            $table->unsignedInteger('total_omitidas')->default(0);
            $table->unsignedInteger('total_duplicadas')->default(0);
            $table->unsignedInteger('total_inconsistencias')->default(0);
            $table->json('resumen_json')->nullable();
            $table->char('huella_prevalidacion', 64)->nullable();
            $table->string('estado');
            $table->timestamp('prevalidado_at')->nullable();
            $table->timestamp('confirmado_at')->nullable();
            $table->timestamp('revertido_at')->nullable();
            $table->unsignedBigInteger('revertido_por')->nullable();
            $table->unsignedBigInteger('subido_por')->nullable();
            $table->timestamps();
        });

        Schema::create('licencias_medicas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_ingreso_licencia', 1)->nullable();
            $table->string('cuerpo_licencia', 20)->nullable();
            $table->string('dv_licencia', 1)->nullable();
            $table->string('folio_licencia', 40)->nullable();
            $table->string('rut_funcionario', 20)->nullable();
            $table->string('dv_funcionario', 1)->nullable();
            $table->string('rut_normalizado', 20)->nullable();
            $table->string('rut_formateado', 20)->nullable();
            $table->string('nombre_funcionario')->nullable();
            $table->string('tipo_dependencia')->nullable();
            $table->string('estado_actual')->nullable();
            $table->string('estado_compin')->nullable();
            $table->string('estado_administrativo_codigo')->nullable();
            $table->string('estado_compin_codigo')->nullable();
            $table->string('estado_recuperacion_codigo')->nullable();
            $table->string('estado_notificacion')->nullable();
            $table->string('estado_alerta')->nullable();
            $table->string('origen_ingreso')->nullable();
            $table->string('tipo_documento_ingreso')->nullable();
            $table->string('fuente_asociacion_funcionario')->nullable();
            $table->string('origen_planilla_anio')->nullable();
            $table->unsignedBigInteger('importacion_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('licencias_medicas_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('licencia_medica_id');
            $table->string('accion', 80);
            $table->text('descripcion')->nullable();
            $table->string('estado_dimension', 30)->nullable();
            $table->string('estado_anterior', 80)->nullable();
            $table->string('estado_nuevo', 80)->nullable();
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('origen', 40)->nullable();
            $table->unsignedBigInteger('importacion_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('licencias_medicas_importacion_errores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('importacion_id');
            $table->string('hoja')->nullable();
            $table->unsignedInteger('fila')->nullable();
            $table->string('codigo_error');
            $table->text('motivo');
            $table->string('folio_recibido')->nullable();
            $table->string('rut_recibido')->nullable();
            $table->json('valores_originales')->nullable();
            $table->json('valores_corregidos')->nullable();
            $table->string('estado')->default('pendiente');
            $table->unsignedSmallInteger('intentos_reproceso')->default(0);
            $table->text('ultimo_error')->nullable();
            $table->string('resultado_reproceso')->nullable();
            $table->unsignedBigInteger('licencia_medica_id')->nullable();
            $table->unsignedBigInteger('corregido_por')->nullable();
            $table->timestamp('corregido_at')->nullable();
            $table->unsignedBigInteger('reprocesado_por')->nullable();
            $table->timestamp('reprocesado_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('licencias_medicas_importacion_errores');
        Schema::dropIfExists('licencias_medicas_historial');
        Schema::dropIfExists('licencias_medicas');
        Schema::dropIfExists('licencias_medicas_importaciones');
        parent::tearDown();
    }

    public function test_normalizador_valida_el_digito_verificador_del_rut(): void
    {
        $valido = RutNormalizer::normalize('12.345.678-5');
        $invalido = RutNormalizer::normalize('12.345.678-0');

        $this->assertTrue($valido['valido']);
        $this->assertSame('123456785', $valido['normalizado']);
        $this->assertFalse($invalido['valido']);
    }

    public function test_catalogo_normaliza_estados_historicos_en_dimensiones_separadas(): void
    {
        $servicio = app(LicenciaEstadoService::class);

        $this->assertSame('recibida', $servicio->normalizar('administrativo', '1- Otorgada'));
        $this->assertSame('autorizada', $servicio->normalizar('compin', '1- Otorgada'));
        $this->assertSame('en_cobro', $servicio->normalizar('recuperacion', 'En gestión de cobro'));
        $this->assertSame('otro', $servicio->normalizar('administrativo', 'Estado histórico no catalogado'));
        $this->assertNull($servicio->normalizarEstricto('administrativo', 'Estado histórico no catalogado'));
    }

    public function test_cambio_de_estado_actualiza_solo_su_dimension_y_deja_historial(): void
    {
        $licencia = LicenciaMedica::create([
            'estado_actual' => 'Ingresada',
            'estado_administrativo_codigo' => 'ingresada',
            'estado_compin_codigo' => 'sin_informacion',
            'estado_recuperacion_codigo' => 'no_evaluada',
        ]);

        app(LicenciaEstadoService::class)->cambiar(
            $licencia,
            LicenciaEstadoService::COMPIN,
            'autorizada',
            99,
            'Resolución oficial revisada en prueba.'
        );

        $licencia->refresh();
        $this->assertSame('ingresada', $licencia->estado_administrativo_codigo);
        $this->assertSame('autorizada', $licencia->estado_compin_codigo);
        $this->assertSame('Autorizada', $licencia->estado_compin);
        $this->assertDatabaseHas('licencias_medicas_historial', [
            'licencia_medica_id' => $licencia->id,
            'accion' => 'cambio_estado',
            'estado_dimension' => 'compin',
            'estado_anterior' => 'sin_informacion',
            'estado_nuevo' => 'autorizada',
            'origen' => 'manual',
            'user_id' => 99,
        ]);
    }

    public function test_no_permite_repetir_el_mismo_estado(): void
    {
        $licencia = LicenciaMedica::create([
            'estado_actual' => 'Ingresada',
            'estado_administrativo_codigo' => 'ingresada',
        ]);

        $this->expectException(ValidationException::class);
        app(LicenciaEstadoService::class)->cambiar(
            $licencia,
            LicenciaEstadoService::ADMINISTRATIVO,
            'ingresada',
            99,
            'Intento de repetición de estado.'
        );
    }

    public function test_importacion_incompleta_queda_marcada_como_fallida(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'licencias_fallida_').'.xlsx';
        file_put_contents($ruta, 'contenido que no es un archivo XLSX');

        try {
            app(LicenciaSeguimientoImportService::class)->import(
                $ruta,
                99,
                'seguimiento_prueba.xlsx',
                'licencias_medicas/importaciones/seguimiento_prueba.xlsx'
            );
            $this->fail('La importación inválida debió fallar.');
        } catch (\RuntimeException) {
            $this->assertDatabaseHas('licencias_medicas_importaciones', [
                'nombre_archivo' => 'seguimiento_prueba.xlsx',
                'estado' => 'fallido',
            ]);
        } finally {
            @unlink($ruta);
        }
    }

    public function test_reconstruye_errores_historicos_sin_reaplicar_filas_validas(): void
    {
        $path = $this->crearPlanillaSeguimiento([
            ['', '9', '12.345.678-5', 'Funcionario de prueba'],
            ['12345', '9', '12.345.678-5', 'Funcionario válido'],
        ]);
        $importacion = LicenciaMedicaImportacion::create([
            'tipo' => 'seguimiento_excel',
            'nombre_archivo' => basename($path),
            'archivo_path' => $path,
            'total_filas' => 2,
            'total_importadas' => 1,
            'total_omitidas' => 1,
            'total_inconsistencias' => 1,
            'resumen_json' => ['tipo_ingreso_default' => '3'],
            'estado' => 'procesado',
            'subido_por' => 99,
        ]);

        $cantidad = app(LicenciaSeguimientoImportService::class)->indexarErrores($importacion);

        $this->assertSame(1, $cantidad);
        $this->assertDatabaseHas('licencias_medicas_importacion_errores', [
            'importacion_id' => $importacion->id,
            'hoja' => '2026',
            'fila' => 2,
            'codigo_error' => 'folio_invalido',
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseCount('licencias_medicas', 0);
    }

    public function test_corrige_y_reprocesa_una_fila_rechazada_con_trazabilidad(): void
    {
        $importacion = LicenciaMedicaImportacion::create([
            'tipo' => 'seguimiento_excel',
            'nombre_archivo' => 'seguimiento_historico.xlsx',
            'archivo_path' => 'licencias_medicas/importaciones/seguimiento_historico.xlsx',
            'total_filas' => 1,
            'total_omitidas' => 1,
            'total_inconsistencias' => 1,
            'resumen_json' => ['tipo_ingreso_default' => '3'],
            'estado' => 'procesado',
            'subido_por' => 99,
        ]);
        $error = LicenciaMedicaImportacionError::create([
            'importacion_id' => $importacion->id,
            'hoja' => '2026',
            'fila' => 25,
            'codigo_error' => 'rut_invalido',
            'motivo' => 'RUT del funcionario inválido o vacío.',
            'folio_recibido' => '12345-9',
            'rut_recibido' => '12.345.678-0',
            'valores_originales' => [
                'licencia' => '12345',
                'dv' => '9',
                'rut' => '12.345.678-0',
                'nombre' => 'Funcionario de prueba',
            ],
            'estado' => 'pendiente',
        ]);
        $servicio = app(LicenciaSeguimientoImportService::class);

        $servicio->corregirError($error, ['rut' => '12.345.678-5'], 77);
        $resuelto = $servicio->reprocesarError($error->fresh(), 77);

        $this->assertSame('resuelto', $resuelto->estado);
        $this->assertSame('importadas', $resuelto->resultado_reproceso);
        $this->assertSame(1, $resuelto->intentos_reproceso);
        $this->assertNotNull($resuelto->licencia_medica_id);
        $this->assertDatabaseHas('licencias_medicas', [
            'folio_licencia' => '3-12345-9',
            'rut_normalizado' => '123456785',
            'importacion_id' => $importacion->id,
        ]);
        $this->assertDatabaseHas('licencias_medicas_importaciones', [
            'id' => $importacion->id,
            'total_importadas' => 1,
            'total_omitidas' => 0,
            'total_inconsistencias' => 0,
        ]);
        $this->assertDatabaseHas('licencias_medicas_historial', [
            'licencia_medica_id' => $resuelto->licencia_medica_id,
            'origen' => 'importacion_excel',
            'importacion_id' => $importacion->id,
            'user_id' => 77,
        ]);
    }

    public function test_actualizacion_masiva_prevalida_confirma_y_revierte_la_carga_completa(): void
    {
        $licencia = $this->crearLicenciaParaActualizacionMasiva();
        $path = $this->crearPlanillaEstados([
            ['3-12345-9', '12.345.678-5', 'Autorizada', 'Resolución oficial revisada.'],
        ]);
        $importacion = $this->crearImportacionMasiva($path);
        $servicio = app(LicenciaEstadoMasivoService::class);

        $servicio->prevalidar($importacion, 'compin');
        $importacion->refresh();

        $this->assertSame('prevalidado', $importacion->estado);
        $this->assertSame(1, $importacion->resumen_json['actualizables']);
        $this->assertSame('sin_informacion', $licencia->fresh()->estado_compin_codigo);

        $servicio->confirmar($importacion, 99, 'Confirmación general de prueba.');

        $this->assertSame('autorizada', $licencia->fresh()->estado_compin_codigo);
        $this->assertSame('procesado', $importacion->fresh()->estado);
        $this->assertDatabaseHas('licencias_medicas_historial', [
            'licencia_medica_id' => $licencia->id,
            'estado_dimension' => 'compin',
            'estado_anterior' => 'sin_informacion',
            'estado_nuevo' => 'autorizada',
            'origen' => 'actualizacion_masiva',
            'importacion_id' => $importacion->id,
        ]);

        $servicio->revertir($importacion->fresh(), 99, 'Reversa completa de prueba.');

        $this->assertSame('sin_informacion', $licencia->fresh()->estado_compin_codigo);
        $this->assertSame('revertido', $importacion->fresh()->estado);
        $this->assertDatabaseHas('licencias_medicas_historial', [
            'licencia_medica_id' => $licencia->id,
            'estado_dimension' => 'compin',
            'estado_anterior' => 'autorizada',
            'estado_nuevo' => 'sin_informacion',
            'origen' => 'reversion_importacion',
            'importacion_id' => $importacion->id,
        ]);
    }

    public function test_confirmacion_masiva_se_bloquea_si_el_estado_cambio_despues_de_prevalidar(): void
    {
        $licencia = $this->crearLicenciaParaActualizacionMasiva();
        $path = $this->crearPlanillaEstados([
            ['3-12345-9', '12.345.678-5', 'Autorizada', null],
        ]);
        $importacion = $this->crearImportacionMasiva($path);
        $servicio = app(LicenciaEstadoMasivoService::class);
        $servicio->prevalidar($importacion, 'compin');
        $licencia->update(['estado_compin_codigo' => 'en_tramite']);

        try {
            $servicio->confirmar($importacion->fresh(), 99, 'Confirmación que debe bloquearse.');
            $this->fail('La confirmación debió detectar el cambio posterior.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('archivo_estados', $exception->errors());
        }

        $this->assertSame('en_tramite', $licencia->fresh()->estado_compin_codigo);
        $this->assertSame('prevalidado', $importacion->fresh()->estado);
        $this->assertDatabaseCount('licencias_medicas_historial', 0);
    }

    public function test_prevalidacion_masiva_reporta_rut_estado_y_conflictos_sin_aplicar_cambios(): void
    {
        $licencia = $this->crearLicenciaParaActualizacionMasiva();
        $path = $this->crearPlanillaEstados([
            ['3-12345-9', '12.345.678-0', 'Autorizada', null],
            ['3-12345-9', '12.345.678-5', 'Estado inexistente', null],
            ['3-12345-9', '12.345.678-5', 'Autorizada', null],
            ['3-12345-9', '12.345.678-5', 'Rechazada', null],
        ]);
        $importacion = $this->crearImportacionMasiva($path);

        app(LicenciaEstadoMasivoService::class)->prevalidar($importacion, 'compin');
        $resumen = $importacion->fresh()->resumen_json;

        $this->assertSame(0, $resumen['actualizables']);
        $this->assertSame(4, $resumen['inconsistencias']);
        $this->assertCount(4, $resumen['muestras_inconsistencias']);
        $this->assertSame('sin_informacion', $licencia->fresh()->estado_compin_codigo);
        $this->assertDatabaseCount('licencias_medicas_historial', 0);
    }

    public function test_reversa_masiva_se_bloquea_si_hay_un_cambio_posterior(): void
    {
        $licencia = $this->crearLicenciaParaActualizacionMasiva();
        $path = $this->crearPlanillaEstados([
            ['3-12345-9', '12.345.678-5', 'Autorizada', null],
        ]);
        $importacion = $this->crearImportacionMasiva($path);
        $masivo = app(LicenciaEstadoMasivoService::class);
        $masivo->prevalidar($importacion, 'compin');
        $masivo->confirmar($importacion->fresh(), 99, 'Confirmación masiva de prueba.');

        app(LicenciaEstadoService::class)->cambiar(
            $licencia->fresh(),
            'compin',
            'rechazada',
            99,
            'Cambio manual posterior a la carga.'
        );

        try {
            $masivo->revertir($importacion->fresh(), 99, 'Intento de reversa bloqueado.');
            $this->fail('La reversa debió bloquearse por el cambio posterior.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reversion', $exception->errors());
        }

        $this->assertSame('rechazada', $licencia->fresh()->estado_compin_codigo);
        $this->assertSame('procesado', $importacion->fresh()->estado);
    }

    public function test_rutas_aplican_la_matriz_de_roles_especializados(): void
    {
        $lectura = $this->middlewareDeRuta('tramites.licencias-medicas.index');
        $digitacion = $this->middlewareDeRuta('tramites.licencias-medicas.create');
        $importacion = $this->middlewareDeRuta('tramites.licencias-medicas.importar-seguimiento');
        $actualizacionMasiva = $this->middlewareDeRuta('tramites.licencias-medicas.actualizaciones.index');
        $erroresImportacion = $this->middlewareDeRuta('tramites.licencias-medicas.errores.index');
        $reprocesoError = $this->middlewareDeRuta('tramites.licencias-medicas.errores.reprocesar');
        $confirmacionMasiva = $this->middlewareDeRuta('tramites.licencias-medicas.actualizaciones.confirmar');
        $reversionMasiva = $this->middlewareDeRuta('tramites.licencias-medicas.actualizaciones.revertir');
        $configuracion = $this->middlewareDeRuta('tramites.licencias-medicas.feriados.index');
        $seguimiento = $this->middlewareDeRuta('tramites.licencias-medicas.estado.update');

        foreach (['digitador_licencias', 'analista_licencias', 'analista_smc', 'administrador_licencias'] as $rol) {
            $this->assertStringContainsString($rol, $lectura);
        }

        $this->assertStringContainsString('digitador_licencias', $digitacion);
        $this->assertStringNotContainsString('analista_smc', $digitacion);
        $this->assertStringContainsString('analista_smc', $importacion);
        $this->assertStringNotContainsString('digitador_licencias', $importacion);
        $this->assertStringContainsString('analista_smc', $actualizacionMasiva);
        $this->assertStringContainsString('analista_smc', $erroresImportacion);
        $this->assertStringContainsString('administrador_licencias', $reprocesoError);
        $this->assertStringNotContainsString('digitador_licencias', $reprocesoError);
        $this->assertStringContainsString('administrador_licencias', $confirmacionMasiva);
        $this->assertStringNotContainsString('digitador_licencias', $reversionMasiva);
        $this->assertStringContainsString('administrador_licencias', $configuracion);
        $this->assertStringNotContainsString('analista_smc', $configuracion);
        $this->assertStringContainsString('analista_licencias', $seguimiento);
        $this->assertStringContainsString('analista_smc', $seguimiento);
    }

    public function test_navegacion_muestra_el_modulo_a_cada_rol_especializado(): void
    {
        $usuario = new class
        {
            public function canModule(string $module, ?string $role = null): bool
            {
                return true;
            }
        };

        foreach (['digitador_licencias', 'analista_licencias', 'analista_smc', 'administrador_licencias'] as $rol) {
            $labels = collect(SlepUiRegistry::menuGroups($usuario, $rol))->flatten(1)->pluck('label');
            $this->assertContains('Licencias Médicas', $labels, "El menú no muestra el módulo para {$rol}.");
        }
    }

    private function middlewareDeRuta(string $nombre): string
    {
        $ruta = app('router')->getRoutes()->getByName($nombre);
        $this->assertNotNull($ruta, "No se encontró la ruta {$nombre}.");

        return implode('|', $ruta->gatherMiddleware());
    }

    private function crearLicenciaParaActualizacionMasiva(): LicenciaMedica
    {
        return LicenciaMedica::create([
            'tipo_ingreso_licencia' => '3',
            'cuerpo_licencia' => '12345',
            'dv_licencia' => '9',
            'folio_licencia' => '3-12345-9',
            'rut_normalizado' => '123456785',
            'estado_actual' => 'Ingresada',
            'estado_administrativo_codigo' => 'ingresada',
            'estado_compin_codigo' => 'sin_informacion',
            'estado_recuperacion_codigo' => 'no_evaluada',
        ]);
    }

    private function crearPlanillaEstados(array $rows): string
    {
        $path = 'licencias_medicas/pruebas/estados_'.uniqid().'.xlsx';
        Storage::disk('local')->makeDirectory(dirname($path));
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['FOLIO_LICENCIA', 'RUT', 'ESTADO', 'OBSERVACION'],
            ...$rows,
        ]);
        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($path));
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function crearPlanillaSeguimiento(array $rows): string
    {
        $path = 'licencias_medicas/pruebas/seguimiento_'.uniqid().'.xlsx';
        Storage::disk('local')->makeDirectory(dirname($path));
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('2026');
        $sheet->fromArray([
            ['N LICENCIA', 'DV', 'RUT', 'NOMBRE'],
            ...$rows,
        ]);
        (new Xlsx($spreadsheet))->save(Storage::disk('local')->path($path));
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function crearImportacionMasiva(string $path): LicenciaMedicaImportacion
    {
        return LicenciaMedicaImportacion::create([
            'tipo' => LicenciaEstadoMasivoService::TIPO_IMPORTACION,
            'dimension_estado' => 'compin',
            'nombre_archivo' => basename($path),
            'archivo_path' => $path,
            'estado' => 'procesando',
            'subido_por' => 99,
        ]);
    }
}
