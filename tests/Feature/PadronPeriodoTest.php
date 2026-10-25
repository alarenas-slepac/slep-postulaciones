<?php

namespace Tests\Feature;

use App\Http\Controllers\ReemplazosController;
use App\Models\Establecimiento;
use App\Models\PadronRevision;
use App\Models\ReemplazoPersonal;
use App\Models\ReemplazoPersonalHistorico;
use App\Models\User;
use App\Services\Padron\PadronPeriodoService;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronPeriodoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->resetCaches();
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
            $t->string('comuna')->nullable();
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id');
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon', 'financiamiento', 'row_hash', 'source_filename'] as $campo) {
                $t->string($campo)->nullable();
            }
            foreach (['anio', 'mes', 'rbd', 'jornada', 'jornada_basica', 'jornada_media'] as $campo) {
                $t->integer($campo);
            }
            $t->date('fecha_ingreso')->nullable(); $t->boolean('vigente')->default(true); $t->timestamps();
        });
        Schema::create('reemplazos_personal_bloqueos', function (Blueprint $t) {
            $t->id(); $t->integer('reemplazo_personal_id'); $t->boolean('activo'); $t->string('motivo');
            $t->integer('establecimiento_id')->nullable(); $t->integer('rbd')->nullable();
            foreach (['rut', 'nombre', 'observacion'] as $campo) { $t->string($campo)->nullable(); }
            $t->integer('bloqueado_por')->nullable(); $t->integer('desbloqueado_por')->nullable();
            $t->timestamp('desbloqueado_at')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_09_08_120000_create_padron_revisiones.php'))->up();
        (require base_path('database/migrations/2026_09_08_130000_add_padron_aplicacion_segura.php'))->up();
        (require base_path('database/migrations/2026_09_08_160000_create_padron_periodo_versiones.php'))->up();
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Escuela sintética B'],
        ]);
        $this->personal(101);
        $this->personal(102, ['rut' => '222222222', 'estatuto' => 'ASISTENTE', 'escalafon' => 'AUXILIAR', 'jornada' => 30]);
        $this->personal(103, ['rut' => '333333333', 'tipocontrato' => 'REEMPLAZO']);
        $this->personal(104, ['rut' => '444444444', 'vigente' => false]);
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $campo) {
                (new \ReflectionProperty($class, $campo))->setValue(null, []);
            }
        }
    }

    private function personal(int $id, array $cambios = []): void
    {
        DB::table('reemplazos_personal')->insert(array_replace([
            'id' => $id, 'rut' => '111111111', 'nombre' => 'Persona sintética '.$id,
            'establecimiento_id' => 1, 'rbd' => 99999, 'anio' => 2026, 'mes' => 8,
            'jornada' => 44, 'jornada_basica' => 44, 'jornada_media' => 0,
            'tipocontrato' => 'CONTRATA', 'estatuto' => 'DOCENTE', 'escalafon' => 'AULA',
            'financiamiento' => 'REGULAR', 'row_hash' => 'sintetico-'.$id,
            'fecha_ingreso' => '2020-03-01', 'fecha_antiguedad' => '2010-03-01',
            'source_filename' => 'sintetico-agosto.xlsx', 'created_at' => '2026-08-01 00:00:00',
        ], $cambios));
    }

    private function revision(int $anio = 2026, int $mes = 9): PadronRevision
    {
        return PadronRevision::create([
            'anio' => $anio, 'mes' => $mes, 'created_by' => 1, 'archivo' => 'sintetico.xlsx',
            'archivo_hash' => str_repeat('a', 64), 'base_hash' => str_repeat('b', 64),
            'resumen' => [], 'errores' => [], 'excesos' => [],
        ]);
    }

    /** Simula cambios solo en memoria para probar el almacenamiento, no habilita el aplicador. */
    private function capturarCambio(callable $cambio, int $anio = 2026, int $mes = 9): void
    {
        $revision = $this->revision($anio, $mes);
        DB::transaction(function () use ($revision, $cambio): void {
            $service = app(PadronPeriodoService::class);
            $service->antesDeAplicar($revision, 1);
            $cambio();
            $service->despuesDeAplicar($revision, 1);
            $revision->update(['aplicada_at' => now(), 'aplicada_por' => 1]);
        });
    }

    public function test_migration_and_reads_do_not_copy_or_modify_personnel(): void
    {
        $service = app(PadronPeriodoService::class);
        $this->assertSame(4, $service->consultaMensual(2026, 8)->count());
        $this->assertFalse($service->esHistorico(2026, 8));
        $this->assertSame(3, $service->consultaAnual(1, 2026)->count());
        $service->periodos(); $service->huella();
        $this->assertDatabaseCount('padron_periodo_versiones', 0);
        $this->assertDatabaseCount('padron_periodo_personal', 0);
        $this->assertDatabaseCount('reemplazos_personal', 4);
    }

    public function test_monthly_copy_preserves_ids_absences_and_contract_fields_after_transfer(): void
    {
        $this->capturarCambio(function () {
            DB::table('reemplazos_personal')->where('id', 101)->update([
                'establecimiento_id' => 2, 'rbd' => 99998, 'mes' => 9, 'jornada' => 22, 'tipocontrato' => 'TITULAR',
            ]);
            DB::table('reemplazos_personal')->where('id', '<>', 101)->update(['vigente' => false]);
        });
        $service = app(PadronPeriodoService::class);
        $hist = $service->consultaMensual(2026, 8)->where('id', 101)->firstOrFail();
        $this->assertInstanceOf(ReemplazoPersonalHistorico::class, $hist);
        $this->assertSame(101, $hist->id);
        $this->assertSame(1, $hist->establecimiento_id);
        $this->assertSame(44, $hist->jornada);
        $this->assertSame('CONTRATA', $hist->tipocontrato);
        $this->assertSame('2010-03-01', $hist->fecha_antiguedad->toDateString());
        $this->assertTrue($service->consultaMensual(2026, 8)->where('id', 102)->first()->vigente);
        $this->assertSame(0, $service->consultaAnual(1, 2026)->count());
        $this->assertSame([101], $service->consultaAnual(2, 2026)->pluck('id')->all());
        $this->assertSame(22, $service->consultaMensual(2026, 9)->first()->jornada);
        $this->assertDatabaseCount('reemplazos_personal', 4);
    }

    public function test_yearly_read_keeps_previous_teachers_and_assistants_after_id_changes_year(): void
    {
        $est = Establecimiento::findOrFail(1);
        $before = DotacionEstablecimientoCalculator::docentes($est, 2026);
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update([
            'anio' => 2027, 'mes' => 1, 'jornada' => 20, 'establecimiento_id' => 2, 'rbd' => 99998,
        ]), 2027, 1);
        $after = DotacionEstablecimientoCalculator::docentes($est, 2026);
        $this->assertSame($before->pluck('rut')->all(), $after->pluck('rut')->all());
        $this->assertSame(44.0, $after->sum('horas_contrato'));
        $this->assertSame(30.0, DotacionEstablecimientoCalculator::asistentes($est, 2026)->sum('horas_contrato'));
        $this->assertSame(0, DotacionEstablecimientoCalculator::docentes($est, 2027)->count());
        $this->assertSame(20.0, DotacionEstablecimientoCalculator::docentes(Establecimiento::findOrFail(2), 2027)->sum('horas_contrato'));
        $this->assertSame([101, 102, 103], app(PadronPeriodoService::class)->consultaAnual(1, 2026)->orderBy('id')->pluck('id')->all());
    }

    public function test_same_period_correction_appends_versions_and_current_manual_changes_stay_visible(): void
    {
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update(['jornada' => 40]), 2026, 8);
        $first = DB::table('padron_periodo_versiones')->where('origen', 'aplicada')->first();
        $service = app(PadronPeriodoService::class);
        $hash = $service->huella();
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update(['jornada' => 35]), 2026, 8);
        $this->assertNotSame($hash, $service->huella());
        $this->assertDatabaseCount('padron_periodo_versiones', 4);
        $this->assertSame(40, $service->consultaVersion($first->id)->first()->jornada);
        $this->assertSame($first->huella, DB::table('padron_periodo_versiones')->where('id', $first->id)->value('huella'));
        DB::table('reemplazos_personal')->update(['jornada' => 32]);
        $this->assertSame(32, $service->consultaMensual(2026, 8)->first()->jornada);
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update(['mes' => 9]));
        $this->assertSame(32, $service->consultaMensual(2026, 8)->first()->jornada);
        $this->assertSame(40, $service->consultaVersion($first->id)->first()->jornada);
    }

    public function test_empty_complete_period_never_resurrects_old_rows_even_in_later_year(): void
    {
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update(['vigente' => false]));
        $this->assertSame(0, app(PadronPeriodoService::class)->consultaMensual(2026, 9)->count());
        $this->assertSame(0, app(PadronPeriodoService::class)->consultaAnual(1, 2026)->count());
        $this->capturarCambio(fn () => $this->personal(105, ['anio' => 2027, 'mes' => 1]), 2027, 1);
        $this->assertSame(0, app(PadronPeriodoService::class)->consultaAnual(1, 2026)->count());
        $this->assertSame(0, app(PadronPeriodoService::class)->consultaMensual(2026, 9)->count());
    }

    public function test_historical_personal_cannot_be_saved_to_current_table(): void
    {
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update(['mes' => 9]));
        $hist = app(PadronPeriodoService::class)->consultaMensual(2026, 8)->first();
        $this->expectException(\LogicException::class);
        $hist->save();
    }

    public function test_missing_migration_keeps_legacy_reading_and_rejects_snapshot_write(): void
    {
        Schema::drop('padron_periodo_personal'); // Solo SQLite efímero, nunca base real.
        $service = app(PadronPeriodoService::class);
        $this->assertSame(4, $service->consultaMensual(2026, 8)->count());
        $this->assertSame(3, $service->consultaAnual(1, 2026)->count());
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => $service->antesDeAplicar($this->revision(), 1));
    }

    public function test_snapshots_require_transaction(): void
    {
        $this->expectException(ValidationException::class);
        app(PadronPeriodoService::class)->antesDeAplicar($this->revision(), 1);
    }

    public function test_invalid_base_period_aborts_capture_without_partial_baseline(): void
    {
        $this->personal(105, ['mes' => 13]);
        try {
            $this->capturarCambio(fn () => null);
            $this->fail('No debe descartar silenciosamente períodos inválidos.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('período válido', $e->getMessage());
        }
        $this->assertDatabaseCount('padron_periodo_versiones', 0);
        $this->assertDatabaseCount('padron_periodo_personal', 0);
    }

    public function test_yearly_version_preserves_roster_hours_and_components_with_existing_declaration(): void
    {
        DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => 30]);
        $this->personal(105, ['jornada' => 14, 'financiamiento' => 'SEP']);
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update(['anio' => 2027, 'mes' => 1, 'jornada' => 10]), 2027, 1);
        $est = Establecimiento::findOrFail(1);
        $docente = DotacionEstablecimientoCalculator::docentes($est, 2026)->first();
        $this->assertSame(44.0, $docente['horas_contrato']);
        $this->assertSame(2, $docente['registros_contrato']);
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->integer('rbd');
            $t->string('estamento'); $t->integer('horas_contratadas');
        });
        DB::table('declaracion_sostenedores')->insert([
            'rut' => '111111111', 'rbd' => 99999, 'estamento' => 'DOCENTE', 'horas_contratadas' => 40,
        ]);
        $this->resetCaches();
        $docente = DotacionEstablecimientoCalculator::docentes($est, 2026)->first();
        $this->assertSame(44.0, $docente['horas_contrato']);
        $this->assertSame('reemplazos_personal', $docente['fuente_contrato']);
        $this->assertSame(2, $docente['registros_contrato']);
        $this->assertSame(40, (int) $docente['declaracion']->horas_contratadas);
    }

    public function test_incomplete_versions_are_not_exposed_as_history(): void
    {
        $revision = $this->revision();
        DB::table('padron_periodo_versiones')->insert([
            'periodo' => 202608, 'padron_revision_id' => $revision->id, 'origen' => 'previa',
            'usuario_id' => 1, 'created_at' => now(),
        ]);
        $this->personal(105, ['mes' => 9]);
        $this->assertFalse(app(PadronPeriodoService::class)->esHistorico(2026, 8));
        $this->assertSame(4, app(PadronPeriodoService::class)->consultaMensual(2026, 8)->count());
    }

    public function test_history_capture_is_batched_and_failure_rolls_back_all_versions(): void
    {
        for ($i = 105; $i < 310; $i++) {
            $this->personal($i);
        }
        $chunks = 0;
        DB::listen(function (QueryExecuted $event) use (&$chunks): void {
            if (str_starts_with($event->sql, 'insert into "padron_periodo_personal"') && ++$chunks === 2) {
                throw new \RuntimeException('Fallo sintético de captura.');
            }
        });
        try {
            $this->capturarCambio(fn () => null);
            $this->fail('Debe revertir la captura parcial.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo sintético de captura.', $e->getMessage());
        }
        $this->assertSame(2, $chunks);
        $this->assertDatabaseCount('padron_periodo_versiones', 0);
        $this->assertDatabaseCount('padron_periodo_personal', 0);
    }

    public function test_monthly_controller_search_pagination_and_export_use_old_contract(): void
    {
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update([
            'establecimiento_id' => 2, 'rbd' => 99998, 'mes' => 9, 'nombre' => 'Nombre actual', 'jornada' => 20,
        ]));
        $controller = app(ReemplazosController::class);
        $request = Request::create('/padron', 'GET', ['periodo' => '2026-08', 'establecimiento_id' => 1, 'q' => 'sintética 101']);
        $view = $controller->index($request)->getData();
        $this->assertTrue($view['filters']['historico']);
        $this->assertSame(1, $view['personal']->total());
        $this->assertSame(101, $view['personal']->first()->id);
        $this->assertSame(44, $view['personal']->first()->jornada);
        $this->assertSame('sintetico-agosto.xlsx', $view['latestFile']);
        $this->assertSame(['2026-09', '2026-08'], $view['periodOptions']->pluck('key')->all());
        ob_start();
        $controller->export($request)->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Persona sintética 101', $csv);
        $this->assertStringContainsString('Escuela sintética A', $csv);
        $this->assertStringNotContainsString('Nombre actual', $csv);
        $this->assertStringNotContainsString('Persona sintética 102', $csv);
    }

    public function test_forced_establishment_still_limits_historical_results(): void
    {
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update(['mes' => 9, 'establecimiento_id' => 2, 'rbd' => 99998]));
        $user = new class extends User {
            public function hasRole($roles, ?string $guard = null): bool { return $roles === 'funcionario_estab'; }
            public function establecimiento(): \Illuminate\Database\Eloquent\Relations\BelongsTo {
                return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
            }
        };
        $user->establecimiento_id = 2;
        $request = Request::create('/padron', 'GET', ['periodo' => '2026-08', 'establecimiento_id' => 1]);
        $request->setUserResolver(fn () => $user);
        $view = app(ReemplazosController::class)->index($request)->getData();
        $this->assertSame(0, $view['personal']->total());
        $this->assertSame(2, $view['filters']['establecimiento_id']);
    }

    public function test_historical_action_is_rejected_before_touching_current_personal(): void
    {
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update(['mes' => 9]));
        $request = Request::create('/padron/101', 'POST', ['establecimiento_id' => 2, 'return' => ['periodo' => '2026-08']]);
        try {
            app(ReemplazosController::class)->updatePersonal($request, ReemplazoPersonal::findOrFail(101));
            $this->fail('No puede editar el contrato actual desde un mes archivado.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('solo lectura', $e->getMessage());
        }
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'mes' => 9, 'establecimiento_id' => 1]);
    }

    public function test_invalid_period_context_returns_validation_not_a_server_error(): void
    {
        $this->expectException(ValidationException::class);
        app(ReemplazosController::class)->editPersonal(
            Request::create('/padron/101/edit', 'GET', ['periodo' => ['2026-08']]),
            ReemplazoPersonal::findOrFail(101),
        );
    }

    public function test_monthly_blade_compiles_to_valid_php(): void
    {
        $compiled = app('blade.compiler')->compileString(file_get_contents(resource_path('views/reemplazos/index.blade.php')));
        $this->assertNotEmpty(token_get_all($compiled, TOKEN_PARSE));
    }

    public function test_block_follows_stable_id_and_verifies_archived_origin_without_copying(): void
    {
        DB::table('reemplazos_personal_bloqueos')->insert([
            'reemplazo_personal_id' => 101, 'activo' => true, 'motivo' => 'Bloqueo sintético',
        ]);
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update(['mes' => 9, 'establecimiento_id' => 2, 'rbd' => 99998]));
        $request = Request::create('/padron', 'POST', ['periodo_origen' => '2026-08', 'periodo_destino' => '2026-09']);
        $request->setLaravelSession(app('session')->driver());
        $response = app(ReemplazosController::class)->traspasarBloqueosPersonal($request);
        $summary = $response->getSession()->get('traspaso_bloqueos_resumen');
        $this->assertSame(1, $summary['ya_existian']);
        $this->assertSame(0, $summary['traspasados']);
        $this->assertDatabaseCount('reemplazos_personal_bloqueos', 1);
        $view = app(ReemplazosController::class)->index(Request::create('/padron', 'GET', ['periodo' => '2026-08']))->getData();
        $this->assertSame(1, $view['summary']['bloqueados']);
        $this->assertSame('Bloqueo sintético', $view['personal']->firstWhere('id', 101)->bloqueoActivo->motivo);
    }

    private function prepararComunas(): void
    {
        DB::table('establecimientos')->where('id', 1)->update(['comuna' => 'Comuna A']);
        DB::table('establecimientos')->where('id', 2)->update(['comuna' => 'Comuna B']);
        DB::table('establecimientos')->insert([
            ['id' => 3, 'rbd' => 99997, 'nombre_establecimiento' => 'Escuela sintética C', 'comuna' => ' Comuna A '],
            ['id' => 4, 'rbd' => 99996, 'nombre_establecimiento' => 'Escuela sin comuna', 'comuna' => null],
            ['id' => 5, 'rbd' => 99995, 'nombre_establecimiento' => 'Escuela comuna vacía', 'comuna' => ''],
        ]);
        $this->personal(105, ['establecimiento_id' => 2, 'rbd' => 99998, 'nombre' => 'Persona comuna B',
            'source_filename' => 'comuna-b.xlsx', 'updated_at' => '2026-08-30 00:00:00']);
        $this->personal(106, ['establecimiento_id' => 3, 'rbd' => 99997,
            'source_filename' => 'comuna-a.xlsx', 'updated_at' => '2026-08-20 00:00:00']);
        $this->personal(107, ['establecimiento_id' => 4, 'rbd' => 99996, 'nombre' => 'Persona sin comuna']);
        $this->personal(108, ['establecimiento_id' => 5, 'rbd' => 99995, 'nombre' => 'Persona comuna vacía']);
    }

    public function test_comuna_incluye_todos_los_contratos_y_establecimientos_con_resumen_coherente(): void
    {
        $this->prepararComunas();
        DB::table('reemplazos_personal_bloqueos')->insert(['reemplazo_personal_id' => 101, 'activo' => true, 'motivo' => 'Bloqueo sintético']);
        $view = app(ReemplazosController::class)->index(Request::create('/reemplazos', 'GET', ['comuna' => 'Comuna A']))->getData();
        $this->assertSame(['Comuna A', 'Comuna B'], $view['comunas']->all());
        $this->assertSame('Comuna A', $view['filters']['comuna']);
        $this->assertSame(5, $view['personal']->total());
        $this->assertEqualsCanonicalizing([101, 102, 103, 104, 106], $view['personal']->pluck('id')->all());
        $this->assertSame(5, $view['summary']['total']);
        $this->assertSame(2, $view['summary']['establecimientos']);
        $this->assertSame(2, $view['summary']['bloqueados']);
        $this->assertEqualsCanonicalizing([1, 3], $view['resumenEstablecimientos']->pluck('establecimiento_id')->all());
        $this->assertEquals(5, $view['resumenEstablecimientos']->sum('total_registros'));
        $this->assertSame('comuna-a.xlsx', $view['latestFile']);
    }

    public function test_comuna_se_combina_con_busqueda_y_establecimiento_y_no_amplia_filtros_sin_resultados(): void
    {
        $this->prepararComunas();
        $controller = app(ReemplazosController::class);
        $view = $controller->index(Request::create('/reemplazos', 'GET', [
            'comuna' => 'Comuna A', 'establecimiento_id' => 3, 'q' => 'sintética 106',
        ]))->getData();
        $this->assertSame([106], $view['personal']->pluck('id')->all());
        foreach ([['comuna' => 'Comuna A', 'establecimiento_id' => 2], ['comuna' => 'Comuna inexistente']] as $filtros) {
            $empty = $controller->index(Request::create('/reemplazos', 'GET', $filtros))->getData();
            $this->assertSame(0, $empty['personal']->total());
            $this->assertSame(0, $empty['summary']['total']);
            $this->assertNull($empty['latestFile']);
        }
        $all = $controller->index(Request::create('/reemplazos', 'GET'))->getData();
        $this->assertSame(8, $all['personal']->total()); // Incluye comunas nulas/vacías al limpiar el filtro.
    }

    public function test_paginacion_conserva_comuna_y_csv_exporta_todos_los_registros_filtrados(): void
    {
        $this->prepararComunas();
        for ($id = 120; $id < 140; $id++) { $this->personal($id, ['establecimiento_id' => 3, 'rbd' => 99997]); }
        $request = Request::create('/reemplazos', 'GET', ['periodo' => '2026-08', 'comuna' => 'Comuna A', 'per_page' => 15]);
        $this->app->instance('request', $request);
        $controller = app(ReemplazosController::class);
        $view = $controller->index($request)->getData();
        $this->assertCount(15, $view['personal']);
        $this->assertSame(25, $view['personal']->total());
        parse_str(parse_url($view['personal']->nextPageUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('Comuna A', $query['comuna']);
        $this->assertSame('2026-08', $query['periodo']);
        ob_start();
        $controller->export($request)->sendContent();
        $csv = ob_get_clean();
        $this->assertCount(26, preg_split('/\r?\n/', trim($csv)));
        $this->assertStringContainsString('Persona sintética 139', $csv);
        $this->assertStringContainsString('Escuela sintética A', $csv);
        $this->assertStringContainsString('Escuela sintética C', $csv);
        $this->assertStringNotContainsString('Persona comuna B', $csv);
        $this->assertStringNotContainsString('Persona sin comuna', $csv);
        $this->assertStringNotContainsString('Persona comuna vacía', $csv);
    }

    public function test_comuna_filtra_el_establecimiento_del_contrato_archivado_y_no_el_actual(): void
    {
        $this->prepararComunas();
        $this->capturarCambio(fn () => DB::table('reemplazos_personal')->update([
            'mes' => 9, 'establecimiento_id' => 2, 'rbd' => 99998, 'nombre' => 'Nombre actual en otra comuna',
        ]));
        $request = Request::create('/reemplazos', 'GET', ['periodo' => '2026-08', 'comuna' => 'Comuna A']);
        $controller = app(ReemplazosController::class);
        $view = $controller->index($request)->getData();
        $this->assertTrue($view['filters']['historico']);
        $this->assertSame(5, $view['personal']->total());
        $this->assertEqualsCanonicalizing([1, 3], $view['personal']->pluck('establecimiento_id')->unique()->all());
        ob_start(); $controller->export($request)->sendContent(); $csv = ob_get_clean();
        $this->assertStringContainsString('Persona sintética 106', $csv);
        $this->assertStringNotContainsString('Nombre actual en otra comuna', $csv);
    }

    public function test_comuna_no_permite_consultar_otros_establecimientos_ni_ofrece_catalogo_sin_asignacion(): void
    {
        $this->prepararComunas();
        $user = new class extends User {
            public function hasRole($roles, ?string $guard = null): bool { return $roles === 'funcionario_estab'; }
        };
        $user->establecimiento_id = 2;
        foreach (['Comuna A' => 0, 'Comuna B' => 1] as $comuna => $count) {
            $request = Request::create('/reemplazos', 'GET', ['comuna' => $comuna, 'establecimiento_id' => 1]);
            $request->setUserResolver(fn () => $user);
            $view = app(ReemplazosController::class)->index($request)->getData();
            $this->assertSame($count, $view['personal']->total());
            $this->assertSame(2, $view['filters']['establecimiento_id']);
            $this->assertSame(['Comuna B'], $view['comunas']->all());
            ob_start(); app(ReemplazosController::class)->export($request)->sendContent(); $csv = ob_get_clean();
            $this->assertStringNotContainsString('Persona sintética 101', $csv);
        }
        $user->establecimiento_id = null;
        $view = app(ReemplazosController::class)->index($request)->getData();
        $this->assertSame(0, $view['personal']->total());
        $this->assertTrue($view['establecimientos']->isEmpty());
        $this->assertTrue($view['comunas']->isEmpty());
    }

    public function test_comuna_rechaza_valores_no_escalares_y_demasiado_largos(): void
    {
        foreach ([['Comuna A'], str_repeat('a', 121)] as $invalid) {
            try {
                app(ReemplazosController::class)->index(Request::create('/reemplazos', 'GET', ['comuna' => $invalid]));
                $this->fail('Debe validar comuna antes de consultar el padrón.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('comuna', $exception->errors());
            }
            try {
                app(ReemplazosController::class)->editPersonal(Request::create('/reemplazos/101/edit', 'GET', ['comuna' => $invalid]), ReemplazoPersonal::findOrFail(101));
                $this->fail('Debe validar la comuna al preparar el retorno de edición.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('comuna', $exception->errors());
            }
        }
    }

    public function test_edicion_y_retorno_al_padron_conservan_la_comuna(): void
    {
        $this->prepararComunas();
        $personal = ReemplazoPersonal::findOrFail(101);
        $controller = app(ReemplazosController::class);
        $edit = $controller->editPersonal(Request::create('/reemplazos/101/edit', 'GET', ['comuna' => 'Comuna A']), $personal)->getData();
        $this->assertSame('Comuna A', $edit['returnFilters']['comuna']);
        $response = $controller->updatePersonal(Request::create('/reemplazos/101', 'PUT', [
            'establecimiento_id' => 3, 'return' => ['periodo' => '2026-08', 'comuna' => 'Comuna A'],
        ]), $personal);
        parse_str(parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
        $this->assertSame('Comuna A', $query['comuna']);
        $this->assertSame(3, $personal->fresh()->establecimiento_id);
    }

    public function test_bloquear_y_desbloquear_conservan_comuna_y_paginacion(): void
    {
        $this->prepararComunas();
        $controller = app(ReemplazosController::class);
        $personal = ReemplazoPersonal::findOrFail(101);
        $return = ['periodo' => '2026-08', 'comuna' => 'Comuna A', 'per_page' => 15, 'page' => 2];
        $block = Request::create('/reemplazos/101/bloquear', 'POST', ['motivo' => 'Bloqueo sintético', 'return' => $return]);
        $block->setUserResolver(fn () => (new User)->forceFill(['id' => 1]));
        $unblock = Request::create('/reemplazos/101/desbloquear', 'DELETE', ['return' => $return]);
        $unblock->setUserResolver($block->getUserResolver());
        foreach ([$controller->bloquearPersonal($block, $personal), $controller->desbloquearPersonal($unblock, $personal)] as $response) {
            parse_str(parse_url($response->getTargetUrl(), PHP_URL_QUERY), $query);
            $this->assertSame('Comuna A', $query['comuna']);
            $this->assertSame('2', $query['page']);
            $this->assertSame('15', $query['per_page']);
        }
        $this->assertDatabaseHas('reemplazos_personal_bloqueos', ['reemplazo_personal_id' => 101, 'activo' => false]);
    }
}
