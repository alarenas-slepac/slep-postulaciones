<?php

namespace Tests\Feature;

use App\Http\Controllers\Tramites\LicenciaMedicaController;
use App\Models\FuncionarioAcAutorizado;
use App\Models\LicenciaMedica;
use App\Models\LicenciaMedicaHistorial;
use App\Models\LicenciaMedicaImportacion;
use App\Models\LicenciaMedicaImportacionError;
use App\Models\User;
use App\Services\LicenciasMedicas\LicenciaDiasLaboralesService;
use App\Services\LicenciasMedicas\LicenciaEstadoService;
use App\Services\LicenciasMedicas\LicenciaFuncionarioResolver;
use App\Services\LicenciasMedicas\LicenciaSeguimientoImportService;
use App\Services\LicenciasMedicas\RutNormalizer;
use App\Support\RutChile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PadronLicenciasVigenciaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Storage::fake('local');
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id')->nullable();
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon'] as $field) { $t->string($field); }
            $t->integer('anio'); $t->integer('mes'); $t->boolean('vigente')->default(true);
        });
        Schema::create('padron_revisiones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->integer('mes'); $t->timestamp('aplicada_at')->nullable();
        });
        Schema::create('licencias_feriados', function (Blueprint $t) {
            $t->id(); $t->date('fecha'); $t->boolean('activo');
        });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->softDeletes(); });
        foreach ([new LicenciaMedica, new LicenciaMedicaHistorial, new LicenciaMedicaImportacion,
            new LicenciaMedicaImportacionError, new FuncionarioAcAutorizado] as $model) {
            $this->tablaDocumento($model);
        }
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética A', 'comuna' => 'Comuna A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Escuela sintética B', 'comuna' => 'Comuna B'],
        ]);
        // Aísla la plantilla del módulo de menús y permisos ajenos al caso.
        Storage::disk('local')->put('views/layouts/app.blade.php', '@yield("content")');
        View::getFinder()->prependLocation(Storage::disk('local')->path('views'));
        View::share('errors', new ViewErrorBag);
    }

    private function tablaDocumento(Model $model): void
    {
        Schema::create($model->getTable(), function (Blueprint $t) use ($model) {
            $t->id();
            foreach ($model->getFillable() as $field) {
                if ($field === 'created_at') { continue; }
                if (str_ends_with($field, '_id') || str_ends_with($field, '_by') || str_starts_with($field, 'total_') || $field === 'intentos_reproceso') {
                    $t->integer($field)->nullable();
                } else {
                    $t->text($field)->nullable();
                }
            }
            $t->timestamps();
            if ($model instanceof LicenciaMedica) { $t->unique('folio_licencia'); }
        });
    }

    private function rut(int $body = 11111111): string { return $body.'-'.RutChile::dv($body); }

    private function personal(int $id = 101, array $changes = []): void
    {
        DB::table('reemplazos_personal')->insert(array_replace([
            'id' => $id, 'establecimiento_id' => 1, 'rut' => $this->rut(),
            'nombre' => 'Persona sintética', 'tipocontrato' => 'CONTRATA',
            'estatuto' => 'DOCENTE', 'escalafon' => 'AULA', 'anio' => 2026, 'mes' => 8, 'vigente' => true,
        ], $changes));
    }

    private function resolve(?string $rut = null): array
    {
        $normal = RutNormalizer::normalize($rut ?? $this->rut());
        return app(LicenciaFuncionarioResolver::class)->resolve($normal['normalizado'], $normal['rut'], 'Dependencia manual', 'Comuna manual');
    }

    private function fila(string $folio = '12345', ?string $rut = null, string $estado = 'Autorizada'): array
    {
        return [$folio, '9', $rut ?? $this->rut(), 'Persona sintética del Excel', 'Dependencia manual', 'Comuna manual', $estado];
    }

    private function importar(array $rows, string $format = 'Xlsx', ?LicenciaSeguimientoImportService $servicio = null): array
    {
        $path = 'pruebas/licencias_'.uniqid().'.'.strtolower($format);
        Storage::disk('local')->makeDirectory('pruebas');
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('2026')->fromArray([
            ['N LICENCIA', 'DV', 'RUT', 'NOMBRE', 'DEPENDENCIA', 'COMUNA', 'PRIMER ESTADO'], ...$rows,
        ]);
        IOFactory::createWriter($book, $format)->save(Storage::disk('local')->path($path));
        $book->disconnectWorksheets();
        return ($servicio ?? app(LicenciaSeguimientoImportService::class))->import(Storage::disk('local')->path($path), 99, basename($path), $path);
    }

    public function test_baja_no_recupera_mes_anterior_y_conserva_datos_manuales(): void
    {
        $this->personal(101, ['mes' => 7]);
        $this->personal(102, ['vigente' => false]);
        $result = $this->resolve();
        $this->assertNull($result['establecimiento_id']);
        $this->assertSame('sin_asociacion', $result['fuente']);
        $this->assertSame('Dependencia manual', $result['establecimiento_nombre']);
        $this->assertStringContainsString('históricos', $result['advertencia']);
        $this->assertNull($result['periodo']);
    }

    public function test_cargas_parciales_respetan_periodo_del_establecimiento_y_completas_imponen_piso(): void
    {
        $this->personal();
        $this->personal(102, ['establecimiento_id' => 2, 'mes' => 9, 'rut' => $this->rut(22222222)]);
        $this->assertSame(1, $this->resolve()['establecimiento_id']);
        $this->assertSame('2026-08', $this->resolve()['periodo']);
        DB::table('padron_revisiones')->insert(['anio' => 2026, 'mes' => 9]);
        $this->assertSame(1, $this->resolve()['establecimiento_id']);
        DB::table('padron_revisiones')->update(['aplicada_at' => now()]);
        $this->assertNull($this->resolve()['establecimiento_id']);
    }

    public function test_traslado_y_reincorporacion_conservan_id_y_resuelven_nueva_dependencia(): void
    {
        $this->personal();
        DB::table('reemplazos_personal')->where('id', 101)->update(['establecimiento_id' => 2, 'mes' => 9]);
        $this->assertSame(2, $this->resolve()['establecimiento_id']);
        DB::table('reemplazos_personal')->where('id', 101)->update(['vigente' => false]);
        $this->assertNull($this->resolve()['establecimiento_id']);
        DB::table('reemplazos_personal')->where('id', 101)->update(['vigente' => true, 'anio' => 2027, 'mes' => 1]);
        $this->assertSame('2027-01', $this->resolve()['periodo']);
        $this->assertDatabaseCount('reemplazos_personal', 1);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101]);
    }

    public function test_multiples_rbd_y_huerfanos_actuales_no_se_asignan_arbitrariamente(): void
    {
        $this->personal();
        $this->personal(102, ['establecimiento_id' => 2, 'mes' => 9]);
        $this->assertStringContainsString('más de un establecimiento', $this->resolve()['advertencia']);
        DB::table('reemplazos_personal')->where('id', 102)->update(['establecimiento_id' => null]);
        $this->assertStringContainsString('sin establecimiento válido', $this->resolve()['advertencia']);
        DB::table('reemplazos_personal')->where('id', 102)->update(['establecimiento_id' => 777]);
        $this->assertNull($this->resolve()['establecimiento_id']);
        DB::table('reemplazos_personal')->where('id', 102)->update(['establecimiento_id' => null, 'mes' => 7]);
        $this->assertSame(1, $this->resolve()['establecimiento_id']);
    }

    public function test_reemplazos_y_suplencias_no_se_excluyen_y_contratos_mixtos_no_inventan_calidad(): void
    {
        $this->personal(101, ['tipocontrato' => 'REEMPLAZO']);
        $this->assertSame('REEMPLAZO', $this->resolve()['calidad_juridica']);
        $this->personal(102, ['tipocontrato' => 'SUPLENCIA']);
        $result = $this->resolve();
        $this->assertSame(1, $result['establecimiento_id']);
        $this->assertNull($result['calidad_juridica']);
        $this->assertNotNull($result['advertencia']);
        DB::table('reemplazos_personal')->where('id', 101)->update(['vigente' => false]);
        $this->assertSame('SUPLENCIA', $this->resolve()['calidad_juridica']);
    }

    public function test_rut_exacto_no_confunde_prefijos_o_dv_distinto_y_rechaza_vacio(): void
    {
        $this->personal(101, ['rut' => $this->rut(1111111)]);
        $this->assertNull($this->resolve($this->rut(111111))['establecimiento_id']);
        $this->assertSame(1, $this->resolve($this->rut(1111111))['establecimiento_id']);
        $resolver = app(LicenciaFuncionarioResolver::class);
        foreach ([[null, null], ['11111111-0', '11111111'], [$this->rut(), '22222222']] as [$rut, $body]) {
            $this->assertNull($resolver->resolve($rut, $body)['establecimiento_id']);
        }
    }

    public function test_ac_conserva_prioridad_de_identidad_sin_confundir_autorizacion_con_contrato(): void
    {
        $this->personal();
        FuncionarioAcAutorizado::create([
            'run' => '11111111', 'dv' => RutChile::dv(11111111), 'rut_normalizado' => null,
            'nombres' => 'Persona AC sintética', 'estado_autorizacion' => 'inactivo', 'periodo_nomina' => '2026-08',
        ]);
        $result = $this->resolve();
        $this->assertSame('administracion_central', $result['tipo_dependencia']);
        $this->assertSame('funcionarios_ac_autorizados', $result['fuente']);
        $this->assertNull($result['establecimiento_id']);
        $this->assertSame('sin_asociacion', $this->resolve($this->rut(1111111))['fuente']);
    }

    public function test_esquema_legacy_sin_vigencia_y_padron_ausente(): void
    {
        $this->personal();
        Schema::table('reemplazos_personal', fn (Blueprint $t) => $t->dropColumn('vigente'));
        Schema::drop('padron_revisiones');
        $this->assertSame(1, $this->resolve()['establecimiento_id']);
        Schema::drop('reemplazos_personal');
        $this->assertStringContainsString('no disponible', $this->resolve()['advertencia']);
    }

    public function test_reimportacion_xlsx_y_xls_preserva_identidad_dependencia_y_archivo_tras_traslado(): void
    {
        $this->personal();
        foreach (['Xlsx' => '12345', 'Xls' => '23456'] as $format => $folio) {
            DB::table('reemplazos_personal')->update(['establecimiento_id' => 1]);
            $this->importar([$this->fila($folio)], $format);
            $licencia = LicenciaMedica::where('cuerpo_licencia', $folio)->firstOrFail();
            $licencia->update(['origen_ingreso' => 'digital_pdf', 'tipo_documento_ingreso' => 'digital',
                'archivo_licencia_path' => 'respaldo-sintetico.pdf', 'fecha_inicio' => '2026-08-03', 'fecha_termino' => '2026-08-04']);
            Storage::disk('local')->put('respaldo-sintetico.pdf', 'respaldo sintético');
            $antes = $licencia->only(['id', 'rut_normalizado', 'nombre_funcionario', 'establecimiento_id', 'establecimiento_nombre',
                'comuna', 'calidad_juridica', 'periodo_reemplazos_usado', 'fuente_asociacion_funcionario',
                'origen_ingreso', 'tipo_documento_ingreso', 'archivo_licencia_path', 'created_by']);
            DB::table('reemplazos_personal')->update(['establecimiento_id' => 2, 'mes' => 9, 'nombre' => 'Nombre cambiado sintético']);
            $row = $this->fila($folio, estado: 'Rechazada'); $row[3] = 'Nombre distinto en planilla';
            $result = $this->importar([$row], $format);
            $this->assertSame(1, $result['totales']['actualizadas']);
            $this->assertSame($antes, $licencia->fresh()->only(array_keys($antes)));
            $this->assertSame('rechazada', $licencia->fresh()->estado_compin_codigo);
            $this->assertSame('respaldo sintético', Storage::disk('local')->get('respaldo-sintetico.pdf'));
            $this->assertSame(0, $result['resumen']['asociaciones_por_revisar']['total']);
            $this->assertSame(1, $this->importar([$row], $format)['totales']['duplicadas']);
        }
    }

    public function test_importacion_rechaza_folio_de_otro_rut_y_permite_corregir_sin_reasignar(): void
    {
        $this->personal();
        $this->importar([$this->fila()]);
        $licencia = LicenciaMedica::firstOrFail();
        $antes = $licencia->getAttributes();
        $result = $this->importar([$this->fila(rut: $this->rut(22222222)), $this->fila('23456')]);
        $this->assertSame(1, $result['totales']['omitidas']);
        $this->assertSame(1, $result['totales']['importadas']);
        $this->assertSame($antes, $licencia->fresh()->getAttributes());
        $error = LicenciaMedicaImportacionError::where('importacion_id', $result['importacion_id'])->firstOrFail();
        $this->assertSame('folio_rut_conflictivo', $error->codigo_error);
        $service = app(LicenciaSeguimientoImportService::class);
        $service->corregirError($error, ['rut' => $this->rut()], 99);
        $resuelto = $service->reprocesarError($error->fresh(), 99);
        $this->assertSame('resuelto', $resuelto->estado);
        $this->assertSame($licencia->id, $resuelto->licencia_medica_id);
        $this->assertDatabaseCount('licencias_medicas', 2);
    }

    public function test_importacion_admite_exfuncionario_con_advertencia_persistente_y_visible(): void
    {
        $this->personal(101, ['vigente' => false]);
        $result = $this->importar([$this->fila()]);
        $this->assertSame(1, $result['totales']['importadas']);
        $this->assertSame(0, $result['totales']['omitidas']);
        $this->assertSame(1, $result['resumen']['asociaciones_por_revisar']['total']);
        $licencia = LicenciaMedica::firstOrFail();
        $this->assertNull($licencia->establecimiento_id);
        $this->assertSame('Dependencia manual', $licencia->establecimiento_nombre);
        $this->assertDatabaseHas('licencias_medicas_historial', ['licencia_medica_id' => $licencia->id, 'accion' => 'advertencia_asociacion']);
        session()->put('import_result', $result);
        $html = view('tramites.licencias-medicas.importar-seguimiento')->render();
        $this->assertStringContainsString('Asociaciones por revisar: 1', $html);
        $this->assertStringContainsString('antecedentes históricos', $html);
    }

    public function test_importador_reutilizado_no_conserva_cache_tras_una_baja(): void
    {
        $this->personal();
        $service = app(LicenciaSeguimientoImportService::class);
        $this->importar([$this->fila()], servicio: $service);
        DB::table('reemplazos_personal')->update(['vigente' => false]);
        $result = $this->importar([$this->fila('23456')], servicio: $service);
        $this->assertSame(1, $result['resumen']['asociaciones_por_revisar']['total']);
        $this->assertNull(LicenciaMedica::where('cuerpo_licencia', '23456')->firstOrFail()->establecimiento_id);
    }

    public function test_reproceso_refresca_vigencia_y_resumen_de_advertencias(): void
    {
        $this->personal();
        $service = app(LicenciaSeguimientoImportService::class);
        $this->importar([$this->fila()], servicio: $service);
        $resultado = $this->importar([$this->fila('23456', '11111111-0')], servicio: $service);
        $error = LicenciaMedicaImportacionError::where('importacion_id', $resultado['importacion_id'])->firstOrFail();
        DB::table('reemplazos_personal')->update(['vigente' => false]);
        $service->corregirError($error, ['rut' => $this->rut()], 99);
        $resuelto = $service->reprocesarError($error->fresh(), 99);
        $this->assertSame('resuelto', $resuelto->estado);
        $this->assertNull(LicenciaMedica::findOrFail($resuelto->licencia_medica_id)->establecimiento_id);
        $resumen = LicenciaMedicaImportacion::findOrFail($resultado['importacion_id'])->resumen_json;
        $this->assertSame(1, $resumen['asociaciones_por_revisar']['total']);
        $this->assertSame(0, $resumen['reprocesos']['pendientes']);
    }

    public function test_ingreso_digital_usa_padron_actual_y_conserva_respaldo(): void
    {
        $this->personal(101, ['tipocontrato' => 'REEMPLAZO']);
        Storage::disk('local')->put('tmp/sintetico.pdf', 'PDF sintético de prueba');
        session()->put('licencia_medica_archivo_temporal', ['path' => 'tmp/sintetico.pdf', 'nombre' => 'sintetico.pdf', 'mime' => 'application/pdf']);
        session()->put('licencia_medica_extracted', ['estado' => 'procesado', 'datos' => []]);
        $user = new User; $user->forceFill(['id' => 99]);
        $request = Request::create('/licencia', 'POST', [
            'tipo_documento_ingreso' => 'digital', 'tipo_ingreso_licencia' => '3', 'cuerpo_licencia' => '56789',
            'dv_licencia' => '9', 'rut_funcionario_input' => $this->rut(), 'nombre_funcionario' => 'Identidad digital sintética',
            'fecha_inicio' => '2026-08-03', 'fecha_termino' => '2026-08-04', 'estado_administrativo_codigo' => 'ingresada',
        ]);
        $request->setUserResolver(fn () => $user);
        $response = app(LicenciaMedicaController::class)->store($request);
        $this->assertSame(302, $response->getStatusCode());
        $licencia = LicenciaMedica::firstOrFail();
        $this->assertSame(1, $licencia->establecimiento_id);
        $this->assertSame('REEMPLAZO', $licencia->calidad_juridica);
        $this->assertSame('digital_pdf', $licencia->origen_ingreso);
        $this->assertSame('PDF sintético de prueba', Storage::disk('local')->get($licencia->archivo_licencia_path));
        $this->assertNull(session('licencia_medica_archivo_temporal'));
    }

    public function test_reimportacion_no_rellena_identidad_historica_nula_desde_padron_actual(): void
    {
        $this->importar([$this->fila()]);
        $this->personal();
        $result = $this->importar([$this->fila(estado: 'Rechazada')]);
        $this->assertSame(1, $result['totales']['actualizadas']);
        $this->assertNull(LicenciaMedica::firstOrFail()->establecimiento_id);
        $this->assertSame('sin_asociacion', LicenciaMedica::firstOrFail()->fuente_asociacion_funcionario);
    }

    public function test_ingreso_manual_y_cambios_de_estado_no_reescriben_identidad_tras_baja(): void
    {
        $this->personal();
        $user = new User; $user->forceFill(['id' => 99]);
        $request = Request::create('/licencia', 'POST', [
            'tipo_documento_ingreso' => 'escaneada', 'tipo_ingreso_licencia' => '3', 'cuerpo_licencia' => '34567',
            'dv_licencia' => '9', 'rut_funcionario_input' => $this->rut(), 'nombre_funcionario' => 'Identidad manual sintética',
            'fecha_inicio' => '2026-08-03', 'fecha_termino' => '2026-08-04', 'estado_administrativo_codigo' => 'ingresada',
        ], [], ['archivo_licencia' => UploadedFile::fake()->create('licencia.pdf', 1, 'application/pdf')]);
        $request->setUserResolver(fn () => $user);
        $response = app(LicenciaMedicaController::class)->store($request);
        $this->assertSame(302, $response->getStatusCode());
        $licencia = LicenciaMedica::firstOrFail();
        $this->assertSame(1, $licencia->establecimiento_id);
        $antes = $licencia->only(['id', 'nombre_funcionario', 'rut_normalizado', 'establecimiento_id', 'establecimiento_nombre', 'archivo_licencia_path']);
        DB::table('reemplazos_personal')->update(['vigente' => false]);
        app(LicenciaEstadoService::class)->cambiar($licencia, 'compin', 'autorizada', 99, 'Resolución sintética verificada.');
        app(LicenciaMedicaController::class)->recalcularDias($licencia->fresh(), app(LicenciaDiasLaboralesService::class));
        $this->assertSame($antes, $licencia->fresh()->only(array_keys($antes)));
        Storage::disk('local')->assertExists($licencia->archivo_licencia_path);
        $licencia->refresh()->load('historial.usuario');
        $estados = app(LicenciaEstadoService::class);
        $html = view('tramites.licencias-medicas.show', [
            'licencia' => $licencia, 'opcionesEstado' => collect($estados->dimensiones())->mapWithKeys(fn ($d) => [$d => $estados->opciones($d)])->all(),
            'permisos' => ['digitacion' => true, 'configuracion' => false],
            'puedeGestionarEstado' => ['administrativo' => false, 'compin' => false, 'recuperacion' => false],
        ])->render();
        $this->assertStringContainsString('Identidad manual sintética', $html);
        $this->assertStringContainsString('Escuela sintética A', $html);

        $request->merge(['cuerpo_licencia' => '45678']);
        $request->files->set('archivo_licencia', UploadedFile::fake()->create('otra.pdf', 1, 'application/pdf'));
        $response = app(LicenciaMedicaController::class)->store($request);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('antecedentes históricos', session('advertencia_asociacion'));
        $nueva = LicenciaMedica::where('cuerpo_licencia', '45678')->firstOrFail();
        $this->assertNull($nueva->establecimiento_id);
        $this->assertDatabaseHas('licencias_medicas_historial', ['licencia_medica_id' => $nueva->id, 'accion' => 'advertencia_asociacion']);
    }
}
