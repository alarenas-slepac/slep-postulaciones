<?php

namespace Tests\Feature;

use App\Models\PadronRevision;
use App\Services\Padron\{PadronAplicacionService, PadronBajaAsignacionesService, PadronConciliador, PadronConflictosAsignacionService, PadronResolucionService, PadronRevisionService};
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronConservarAusentesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function (Blueprint $t) { $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('rbd');
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon', 'financiamiento'] as $key) { $t->string($key); }
            foreach (['anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media'] as $key) { $t->integer($key); }
            $t->boolean('vigente')->default(true); $t->date('fecha_antiguedad')->nullable(); $t->date('fecha_termino')->nullable(); $t->date('fecha_ingreso')->nullable();
            $t->string('row_hash')->unique(); $t->string('source_filename')->nullable(); $t->timestamps();
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->integer('establecimiento_id');
            $t->foreignId('reemplazos_personal_id')->nullable()->constrained('reemplazos_personal');
            $t->string('docente_rut'); $t->string('estado'); $t->decimal('horas_contrato');
        });
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) {
            $t->id(); $t->foreignId('reemplazo_personal_id')->constrained('reemplazos_personal'); $t->string('estado'); $t->timestamps();
        });
        foreach (['2026_09_08_120000_create_padron_revisiones', '2026_09_08_130000_add_padron_aplicacion_segura',
            '2026_09_08_150000_add_padron_snapshot_to_documentos', '2026_09_08_160000_create_padron_periodo_versiones',
            '2026_09_10_120000_create_padron_bajas_asignaciones', '2026_09_11_120000_add_padron_traslados_asignaciones'] as $migration) {
            (require base_path('database/migrations/'.$migration.'.php'))->up();
        }
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética']);
        foreach ([101 => 35, 102 => 22, 103 => 3] as $id => $hours) {
            DB::table('reemplazos_personal')->insert($this->data($id === 102 ? '222222222' : '111111111', $hours) + [
                'id' => $id, 'establecimiento_id' => 1, 'vigente' => true, 'row_hash' => 'sintetico-'.$id,
                'source_filename' => 'origen-sintetico.xlsx', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-08-01 00:00:00',
            ]);
        }
        DB::table('reemplazos_personal')->where('id', 103)->update(['tipocontrato' => 'CONTRATA']);
        foreach ([501 => [101, 35], 502 => [103, 3], 503 => [null, 2]] as $id => [$personal, $hours]) {
            DB::table('dotacion_docente_asignaciones')->insert(['id' => $id, 'reemplazos_personal_id' => $personal,
                'anio' => 2026, 'establecimiento_id' => 1, 'docente_rut' => '111111111', 'horas_contrato' => $hours, 'estado' => 'activa']);
        }
        DB::table('solicitudes_reemplazo')->insert(['id' => 1, 'reemplazo_personal_id' => 101, 'estado' => 'cerrado']);
    }

    private function data(string $rut, int $hours): array
    {
        return ['rut' => $rut, 'nombre' => 'Persona sintética', 'rbd' => 99999, 'anio' => 2026, 'mes' => 8,
            'tipocontrato' => 'PLANTA', 'financiamiento' => 'SUB.GENERAL', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE',
            'jornada' => $hours, 'jornada_basica' => $hours, 'jornada_media' => 0];
    }

    private function revision(?array $incoming = null): PadronRevision
    {
        $service = app(PadronRevisionService::class);
        $snapshot = (new \ReflectionMethod($service, 'snapshot'))->invoke($service, 202609);
        $report = app(PadronConciliador::class)->reconcile($incoming ?? [
            ['fila_excel' => 2, 'datos' => array_replace($this->data('222222222', 22), ['mes' => 9]), 'observaciones' => []],
        ], $snapshot['personal'], $snapshot['establecimientos'], $snapshot['asignaciones']);
        $revision = PadronRevision::create(['archivo' => 'carga-sintetica.xlsx', 'archivo_hash' => str_repeat('a', 64),
            'base_hash' => $snapshot['hash'], 'created_by' => 1, 'anio' => 2026, 'mes' => 9,
            'resumen' => $report['resumen'], 'errores' => $report['errores'], 'excesos' => $report['excesos']]);
        $revision->filas()->createMany($report['filas']);
        return $revision;
    }

    private function entries(PadronRevision $revision, array $ids = [101, 103]): array
    {
        $decisions = app(PadronResolucionService::class)->decisiones($revision);
        return $revision->filas()->whereNull('fila_excel')->whereIn('personal_id', $ids)->orderBy('id')->get()->map(fn ($fila) => [
            'fila' => $fila->id, 'personal_id' => (int) $fila->personal_id,
            'justificacion' => 'Omisión del archivo revisada: conservar el contrato vigente.',
            'decision_anterior' => (int) ($decisions->get($fila->id)?->id ?? 0),
        ])->all();
    }

    private function conservar(PadronRevision $revision, array $ids = [101, 103]): int
    {
        return app(PadronResolucionService::class)->resolverVarias($revision, '111111111', $this->entries($revision, $ids), 7);
    }

    private function writer(): PadronAplicacionService
    {
        return new class(app(PadronRevisionService::class)) extends PadronAplicacionService {
            public function disponible(): bool { return app()->environment('testing') && DB::connection()->getDatabaseName() === ':memory:'; }
        };
    }

    private function estado(): array
    {
        $out = [];
        foreach (['reemplazos_personal', 'dotacion_docente_asignaciones', 'solicitudes_reemplazo', 'padron_personal_cambios',
            'padron_periodo_versiones', 'padron_periodo_personal', 'padron_revision_filas', 'padron_asignacion_cambios'] as $table) {
            $out[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        return $out;
    }

    private function rechaza(callable $action): void
    {
        try { $action(); $this->fail('Debe rechazar la operación.'); }
        catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
    }

    public function test_batch_conservation_is_readonly_preserves_both_ids_and_restores_coverage(): void
    {
        $revision = $this->revision();
        $before = $this->estado();
        $this->assertNotEmpty($this->writer()->plan($revision)['errores']);
        $this->assertSame(2, $this->conservar($revision));
        $plan = $this->writer()->plan($revision->fresh());
        $this->assertSame([], $plan['errores']);
        $this->assertSame([], $plan['bajas']);
        $this->assertEqualsCanonicalizing([101, 102, 103], array_column($plan['destinos'], 'id'));
        $group = $plan['conflictos']['grupos'][0];
        $this->assertSame(40.0, $group['total_asignadas']);
        $this->assertSame(38.0, $group['cobertura']['horas']);
        $this->assertSame('preexistente', $group['comparacion']['estado']);
        $this->assertFalse($group['bloqueante']);
        $this->assertSame([], $plan['conflictos']['bajas_asignaciones']);
        $this->assertSame($before, $this->estado());
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
        $this->assertSame(0, $this->conservar($revision));
        $this->assertDatabaseCount('padron_revision_decisiones', 2);
    }

    public function test_single_conservation_does_not_release_other_contract_assignment(): void
    {
        $revision = $this->revision();
        $this->conservar($revision, [101]);
        $plan = $this->writer()->plan($revision->fresh());
        $this->assertNotEmpty($plan['errores']);
        $this->assertSame([], $plan['bajas']);
        $this->assertSame([], $plan['conflictos']['bajas_asignaciones']);
        $this->conservar($revision, [103]);
        $this->assertSame([], $this->writer()->plan($revision->fresh())['errores']);
    }

    public function test_partial_conservation_requires_all_absence_decisions_even_for_rut_only_assignments(): void
    {
        DB::table('dotacion_docente_asignaciones')->update(['reemplazos_personal_id' => null]);
        $revision = $this->revision();
        $this->conservar($revision, [101]);
        $plan = $this->writer()->plan($revision->fresh());
        $this->assertSame([], $plan['bajas']);
        $this->assertStringContainsString('ID 103: confirme su baja', implode(' ', $plan['errores']));
        $this->assertTrue($plan['conflictos']['grupos'][0]['bloqueante']);
        $this->withoutMiddleware();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $this->get(route('reemplazos.personal.import', ['revision' => $revision->id, 'q' => '111111111']))
            ->assertOk()->assertSee('Ya se conservó otro contrato de este RUT.');
        $this->conservar($revision, [103]);
        $this->assertSame([], $this->writer()->plan($revision->fresh())['errores']);
    }

    public function test_application_only_changes_month_and_timestamp_for_carried_ids_with_history_and_retry(): void
    {
        $revision = $this->revision();
        $this->conservar($revision);
        $before = DB::table('reemplazos_personal')->whereIn('id', [101, 103])->get()->keyBy('id');
        $assignments = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        $writer = $this->writer();
        $token = $writer->plan($revision->fresh())['confirmacion_hash'];
        $writer->aplicar($revision, 7, $token);
        foreach ([101, 103] as $id) {
            $after = (array) DB::table('reemplazos_personal')->find($id);
            $expected = (array) $before[$id];
            $expected['mes'] = 9;
            $expected['updated_at'] = $after['updated_at'];
            $this->assertSame($expected, $after);
            $this->assertDatabaseHas('padron_periodo_personal', ['personal_id' => $id, 'mes' => 8, 'vigente' => true]);
            $this->assertDatabaseHas('padron_periodo_personal', ['personal_id' => $id, 'mes' => 9, 'vigente' => true]);
        }
        $this->assertSame(8, json_decode(DB::table('solicitudes_reemplazo')->find(1)->padron_personal_snapshot, true)['personal']['mes']);
        $this->assertSame($assignments, DB::table('dotacion_docente_asignaciones')->get()->toJson());
        $this->assertDatabaseCount('reemplazos_personal', 3);
        $this->assertDatabaseCount('padron_asignacion_cambios', 0);
        $applied = $this->estado();
        $writer->aplicar($revision, 7, $token);
        $this->assertSame($applied, $this->estado());
    }

    public function test_failure_rolls_back_carried_contracts_and_history(): void
    {
        $revision = $this->revision();
        $this->conservar($revision);
        $before = $this->estado();
        $inject = true;
        DB::listen(static function (QueryExecuted $event) use (&$inject): void {
            if ($inject && str_starts_with(strtolower($event->sql), 'insert into "padron_personal_cambios"')) { throw new \RuntimeException('fallo-sintetico'); }
        });
        try {
            $writer = $this->writer();
            $writer->aplicar($revision, 7, $writer->plan($revision->fresh())['confirmacion_hash']);
            $this->fail('Debe provocar rollback.');
        } catch (\RuntimeException $exception) { $this->assertSame('fallo-sintetico', $exception->getMessage()); }
        finally { $inject = false; }
        $this->assertSame($before, $this->estado());
        $this->assertDatabaseCount('padron_revision_decisiones', 2);
    }

    public function test_release_confirmation_must_be_withdrawn_before_conservation(): void
    {
        $revision = $this->revision();
        $service = app(PadronBajaAsignacionesService::class);
        $scope = app(PadronConflictosAsignacionService::class)->analizar($revision)['bajas_asignaciones']['111111111'];
        $service->registrar($revision, '111111111', $scope['alcance_hash'], 0, 'Retiro sintético por ausencia verificada.', 7, true);
        $this->rechaza(fn () => $this->conservar($revision));
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
        $scope = app(PadronConflictosAsignacionService::class)->analizar($revision)['bajas_asignaciones']['111111111'];
        $service->registrar($revision, '111111111', $scope['alcance_hash'], $scope['ultima_id'], 'Se mantiene el funcionario en el padrón.', 7, false);
        $this->conservar($revision);
        $this->rechaza(fn () => $service->registrar($revision, '111111111', $scope['alcance_hash'], $scope['ultima_id'] + 1, 'Intento de baja incompatible.', 7, true));
    }

    public function test_conservation_can_be_reversed_without_losing_audit_or_data(): void
    {
        $revision = $this->revision();
        $this->conservar($revision);
        $before = $this->estado();
        $entries = $this->entries($revision);
        foreach ($entries as &$entry) { $entry['personal_id'] = null; $entry['justificacion'] = 'Retirar conservación y mantener baja propuesta.'; }
        unset($entry);
        app(PadronResolucionService::class)->resolverVarias($revision, '111111111', $entries, 7);
        $this->assertEqualsCanonicalizing([101, 103], $this->writer()->plan($revision->fresh())['bajas']);
        $this->assertNotEmpty($this->writer()->plan($revision->fresh())['errores']);
        $this->assertDatabaseCount('padron_revision_decisiones', 4);
        $this->assertSame($before, $this->estado());
    }

    public function test_conserved_hours_are_included_in_44_hour_authorization(): void
    {
        DB::table('reemplazos_personal')->where('id', 103)->update(['jornada' => 10, 'jornada_basica' => 10]);
        $revision = $this->revision();
        $this->conservar($revision);
        $revision->refresh();
        $this->assertSame(45.0, (float) $revision->excesos['111111111']['total']);
        $this->assertEqualsCanonicalizing([101, 103], $revision->excesos['111111111']['ids_conservados']);
        $this->assertStringContainsString('faltan autorización', implode(' ', $this->writer()->plan($revision)['errores']));
        app(PadronRevisionService::class)->authorize($revision, '111111111', 'Excepción sintética revisada y autorizada.', 7);
        $this->assertSame([], $this->writer()->plan($revision->fresh())['errores']);
    }

    public function test_invalid_id_stale_closed_or_expired_contract_never_changes_decisions(): void
    {
        $revision = $this->revision();
        $entries = $this->entries($revision);
        $entries[1]['personal_id'] = 101;
        $this->rechaza(fn () => app(PadronResolucionService::class)->resolverVarias($revision, '111111111', $entries, 7));
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
        DB::table('reemplazos_personal')->where('id', 103)->update(['fecha_termino' => '2026-08-31']);
        $this->rechaza(fn () => $this->conservar($revision)); // Base modificada.
        $expired = $this->revision();
        $this->rechaza(fn () => $this->conservar($expired)); // Validación atómica de ambas líneas.
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
        $revision->forceFill(['aplicada_at' => now()])->save();
        $this->rechaza(fn () => $this->conservar($revision));
    }

    public function test_historical_planta_batch_preserves_42_hours_ids_decisions_and_monthly_history(): void
    {
        DB::table('reemplazos_personal')->where('id', 103)->update(['tipocontrato' => 'PLANTA PIE', 'financiamiento' => 'PIE']);
        DB::table('reemplazos_personal')->insert(array_replace((array) DB::table('reemplazos_personal')->find(103), [
            'id' => 104, 'row_hash' => 'sintetico-104', 'tipocontrato' => 'PLANTA SEP', 'financiamiento' => 'SEP', 'jornada' => 4, 'jornada_basica' => 4,
        ]));
        DB::table('dotacion_docente_asignaciones')->where('id', 503)->update(['horas_contrato' => 1]);
        DB::table('dotacion_docente_asignaciones')->insert(['id' => 504, 'reemplazos_personal_id' => 104, 'anio' => 2026,
            'establecimiento_id' => 1, 'docente_rut' => '111111111', 'horas_contrato' => 4, 'estado' => 'activa']);
        $revision = $this->revision();
        $before = $this->estado();
        $baseHash = $revision->base_hash;
        $this->conservar($revision, [101]);
        $savedDecision = DB::table('padron_revision_decisiones')->first();
        $this->assertTrue($this->writer()->plan($revision->fresh())['conflictos']['grupos'][0]['bloqueante']);
        $this->assertSame(2, $this->conservar($revision, [103, 104]));
        $plan = $this->writer()->plan($revision->fresh());
        $this->assertSame([], $plan['errores']);
        $this->assertSame([], $plan['bajas']);
        $this->assertSame(42.0, $plan['conflictos']['grupos'][0]['cobertura']['horas']);
        $this->assertSame(43.0, $plan['conflictos']['grupos'][0]['total_asignadas']);
        $this->assertSame('preexistente', $plan['conflictos']['grupos'][0]['comparacion']['estado']);
        $this->assertFalse($plan['conflictos']['grupos'][0]['bloqueante']);
        $this->assertStringNotContainsString('Contrato:', implode(' ', $plan['conflictos']['grupos'][0]['avisos']));
        $this->assertSame($before, $this->estado());
        $this->assertSame($baseHash, $revision->fresh()->base_hash);
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision->fresh()));
        $this->assertEquals($savedDecision, DB::table('padron_revision_decisiones')->find($savedDecision->id));
        $this->assertSame(0, $this->conservar($revision, [103, 104]));
        $this->withoutMiddleware();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $this->get(route('reemplazos.personal.import', ['revision' => $revision->id, 'q' => '111111111']))
            ->assertOk()->assertSee('PLANTA PIE → PLANTA PIE')->assertSee('PLANTA SEP → PLANTA SEP')->assertSee('mes: 8 → 9');
        $personal = DB::table('reemplazos_personal')->whereIn('id', [101, 103, 104])->get()->keyBy('id');
        $writer = $this->writer();
        $writer->aplicar($revision, 7, $plan['confirmacion_hash']);
        foreach ($personal as $id => $old) {
            $after = (array) DB::table('reemplazos_personal')->find($id);
            $this->assertSame(array_replace((array) $old, ['mes' => 9, 'updated_at' => $after['updated_at']]), $after);
            $this->assertDatabaseHas('padron_periodo_personal', ['personal_id' => $id, 'mes' => 8, 'tipocontrato' => $old->tipocontrato]);
            $this->assertDatabaseHas('padron_periodo_personal', ['personal_id' => $id, 'mes' => 9, 'tipocontrato' => $old->tipocontrato]);
        }
        $this->assertSame($before['dotacion_docente_asignaciones'], $this->estado()['dotacion_docente_asignaciones']);
        $this->assertDatabaseCount('reemplazos_personal', 4);
        $this->assertDatabaseCount('padron_asignacion_cambios', 0);
    }

    public static function historicalTypes(): array
    {
        $cases = [];
        foreach (['PLANTA', 'CONTRATA', 'INDEFINIDO', 'PLAZO FIJO'] as $base) {
            foreach (['PIE', 'SEP'] as $funding) { $cases[$base.' '.$funding] = [$base.' '.$funding, $funding]; }
        }
        $cases['espacios y minúsculas'] = [' planta   pie ', ' pie '];
        return $cases;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('historicalTypes')]
    public function test_historical_recognition_is_only_for_conservation_and_never_rewrites_the_type(string $type, string $funding): void
    {
        $service = app(PadronResolucionService::class);
        $revision = $this->revision();
        $original = array_replace((array) DB::table('reemplazos_personal')->find(103), ['tipocontrato' => $type, 'financiamiento' => $funding]);
        $data = $service->datosConservados($revision, $original);
        $this->assertSame($type, $data['tipocontrato']);
        $this->assertSame($funding, $data['financiamiento']);
        $this->assertSame(9, $data['mes']);
        $this->assertSame('regular', $service->tipoPropuesto((object) ['fila_excel' => null, 'accion' => 'conservacion_propuesta', 'datos' => $data]));
        $this->assertSame('por_clasificar', $service->tipoPropuesto((object) ['fila_excel' => 2, 'accion' => 'revision_manual', 'datos' => $data]));
        $this->assertSame('por_clasificar', PadronConciliador::tipo($data));
    }

    public static function invalidConservations(): array
    {
        return [
            'sufijo incompatible' => [['tipocontrato' => 'PLANTA PIE', 'financiamiento' => 'SEP'], 'no es regular reconocido'],
            'sin financiamiento' => [['tipocontrato' => 'PLANTA SEP', 'financiamiento' => ''], 'no es regular reconocido'],
            'tipo desconocido' => [['tipocontrato' => 'OTRO PIE', 'financiamiento' => 'PIE'], 'no es regular reconocido'],
            'reemplazo' => [['tipocontrato' => 'REEMPLAZO PIE', 'financiamiento' => 'PIE'], 'no es regular reconocido'],
            'suplencia' => [['tipocontrato' => 'SUPLENCIA'], 'no es regular reconocido'],
            'otro año' => [['anio' => 2025], 'año contractual 2025'],
            'mes posterior' => [['mes' => 10], 'mes contractual 10 es posterior'],
            'mes inválido' => [['mes' => 0], 'mes contractual no es válido'],
            'inactivo' => [['vigente' => false], 'contrato está inactivo'],
            'jornada negativa' => [['jornada' => -1], 'jornada contractual debe ser numérica'],
            'terminado' => [['fecha_termino' => '2026-08-31'], 'terminó el 2026-08-31'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidConservations')]
    public function test_conservation_error_identifies_contract_and_reason(array $changes, string $message): void
    {
        $revision = $this->revision();
        $original = array_replace((array) DB::table('reemplazos_personal')->find(103), $changes);
        try {
            app(PadronResolucionService::class)->datosConservados($revision, $original);
            $this->fail('Debe rechazar la conservación.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ID 103:', $exception->getMessage());
            $this->assertStringContainsString($message, $exception->getMessage());
        }
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
    }

    private function revisionActualizacion(int $horas = 44, bool $docente = false): PadronRevision
    {
        DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => $horas, 'jornada_basica' => $horas,
            'estatuto' => $docente ? 'DOCENTE' : 'AAEE', 'escalafon' => 'PROFESIONAL', 'tipocontrato' => 'PLAZO FIJO',
            'fecha_ingreso' => '2026-10-01', 'fecha_termino' => '2026-12-31']);
        DB::table('reemplazos_personal')->where('id', 103)->update(['rut' => '333333333']);
        // Solo fixtures en SQLite :memory:, ya verificado en setUp.
        DB::table('dotacion_docente_asignaciones')->whereIn('id', [502, 503])->delete();
        Schema::table('dotacion_docente_asignaciones', fn (Blueprint $t) => $t->string('estamento_cobertura')->default($docente ? 'docente' : 'asistente'));
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['horas_contrato' => 44]);
        $incoming = DB::table('reemplazos_personal')->orderBy('id')->get()->map(function ($person, $index) {
            $data = array_intersect_key((array) $person, array_flip([...\App\Services\Padron\PadronExcelReader::REQUIRED, 'tramo', 'fecha_antiguedad']));
            $data['mes'] = 9;
            if ($person->id === 101) {
                $data = array_replace($data, ['tipocontrato' => 'REEMPLAZO', 'jornada' => 44, 'jornada_basica' => 44, 'fecha_ingreso' => '2026-03-05',
                    'fecha_termino' => '2026-09-30', 'nombre' => 'Nombre sintético del Excel', 'financiamiento' => 'SUB.GENERAL',
                    'fecha_antiguedad' => '2025-01-01']);
            }
            return ['fila_excel' => $index + 2, 'datos' => $data, 'observaciones' => []];
        })->all();
        return $this->revision($incoming);
    }

    public function test_keep_previous_aaee_data_instead_of_excel_update_changes_only_month_on_apply(): void
    {
        $revision = $this->revisionActualizacion();
        $row = $revision->filas()->where('personal_id', 101)->firstOrFail();
        $this->assertSame('actualizacion_propuesta', $row->accion);
        $before = $this->estado();
        $old = (array) DB::table('reemplazos_personal')->find(101);
        $this->assertNotEmpty($this->writer()->plan($revision)['errores']);
        $this->withoutMiddleware();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $url = route('reemplazos.personal.import', ['revision' => $revision->id, 'q' => '111111111']);
        $this->get($url)->assertOk()->assertSee('Conservar datos anteriores; solo actualizar mes · ID 101');
        $this->actingAs((new \App\Models\User)->forceFill(['id' => 7]));
        $this->post(route('reemplazos.personal.import.store'), ['accion' => 'resolver', 'revision' => $revision->id,
            'fila' => $row->id, 'personal_id' => 101, 'decision_anterior' => 0, 'justificacion' => 'Conservar contrato actual verificado.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        app('auth')->forgetGuards();
        $plan = $this->writer()->plan($revision->fresh());
        $this->assertSame([], $plan['errores']);
        $this->assertCount(3, $plan['destinos']); // No sumar Excel y versión conservada.
        $this->assertSame([], $plan['bajas']);
        $this->assertSame($before, $this->estado());
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
        $this->get($url)->assertOk()->assertSee('Propuesta original del Excel (no se aplicará)')
            ->assertSee('mes: 8 → 9')->assertDontSee('Conservación propuesta (ausente del Excel)');
        $this->writer()->aplicar($revision, 7, $plan['confirmacion_hash']);
        $after = (array) DB::table('reemplazos_personal')->find(101);
        $this->assertSame(array_replace($old, ['mes' => 9, 'updated_at' => $after['updated_at']]), $after);
        $this->assertSame($before['dotacion_docente_asignaciones'], $this->estado()['dotacion_docente_asignaciones']);
        $this->assertSame($before['padron_revision_filas'], $this->estado()['padron_revision_filas']);
        $this->assertDatabaseHas('padron_periodo_personal', ['personal_id' => 101, 'mes' => 8, 'tipocontrato' => 'PLAZO FIJO']);
        $this->assertDatabaseHas('padron_periodo_personal', ['personal_id' => 101, 'mes' => 9, 'tipocontrato' => 'PLAZO FIJO']);
        $this->assertDatabaseCount('reemplazos_personal', 3);
        $this->assertDatabaseCount('padron_asignacion_cambios', 0);
    }

    public function test_revert_conservation_restores_excel_without_new_contract_or_losing_decision_history(): void
    {
        $revision = $this->revisionActualizacion();
        $row = $revision->filas()->where('personal_id', 101)->firstOrFail();
        $service = app(PadronResolucionService::class);
        $before = $this->estado();
        $reason = 'Conservar datos originales verificados.';
        $service->resolver($revision, $row->id, 101, $reason, 7);
        $service->resolver($revision, $row->id, 101, $reason, 7); // Reenvío idempotente.
        $this->assertDatabaseCount('padron_revision_decisiones', 1);
        $version = (int) $service->decisiones($revision)->get($row->id)->id;
        $this->rechaza(fn () => $service->resolver($revision, $row->id, null, 'Retomar datos del Excel verificados.', 7, 0));
        $service->resolver($revision, $row->id, null, 'Retomar datos del Excel verificados.', 7, $version);
        $plan = $this->writer()->plan($revision->fresh());
        $this->assertNotEmpty($plan['errores']);
        $destination = collect($plan['destinos'])->firstWhere('id', 101);
        $this->assertSame('REEMPLAZO', $destination['fila']->datos['tipocontrato']);
        $this->assertCount(3, $plan['destinos']);
        $this->assertSame($before, $this->estado());
        $this->assertDatabaseCount('padron_revision_decisiones', 2);
    }

    public function test_update_conservation_rejects_another_id_and_keeps_estamento_checks(): void
    {
        $revision = $this->revisionActualizacion();
        $row = $revision->filas()->where('personal_id', 101)->firstOrFail();
        $service = app(PadronResolucionService::class);
        $this->rechaza(fn () => $service->resolver($revision, $row->id, 102, 'No se debe permitir otro ID.', 7));
        $this->assertDatabaseCount('padron_revision_decisiones', 0);
        $service->resolver($revision, $row->id, 101, 'Conservar datos originales verificados.', 7);
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['estamento_cobertura' => 'docente']);
        $this->assertStringContainsString('la cobertura propuesta corresponde a asistente', implode(' ', $this->writer()->plan($revision->fresh())['errores']));
    }

    public function test_batch_can_keep_an_update_and_an_absence_for_the_same_rut(): void
    {
        $revision = $this->revision([
            ['fila_excel' => 2, 'datos' => array_replace($this->data('111111111', 35), ['mes' => 9, 'nombre' => 'Nombre cambiado']), 'observaciones' => []],
            ['fila_excel' => 3, 'datos' => array_replace($this->data('222222222', 22), ['mes' => 9]), 'observaciones' => []],
        ]);
        $row = $revision->filas()->whereNotNull('fila_excel')->where('personal_id', 101)->firstOrFail();
        $this->assertSame('actualizacion_propuesta', $row->accion);
        $entries = $this->entries($revision, [103]);
        $entries[] = ['fila' => $row->id, 'personal_id' => 101, 'justificacion' => 'Conservar datos anteriores del contrato.', 'decision_anterior' => 0];
        $service = app(PadronResolucionService::class);
        $this->assertSame(2, $service->resolverVarias($revision, '111111111', $entries, 7));
        $plan = $this->writer()->plan($revision->fresh());
        $this->assertSame([], $plan['errores']);
        $this->assertCount(3, $plan['destinos']);
        $this->assertSame(38.0, $plan['conflictos']['grupos'][0]['cobertura']['horas']);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'mes' => 8, 'nombre' => 'Persona sintética']);
    }

    public function test_retained_update_hours_require_authorization_when_above_44(): void
    {
        $revision = $this->revisionActualizacion(50, true);
        $this->assertSame([], $revision->excesos);
        $row = $revision->filas()->where('personal_id', 101)->firstOrFail();
        app(PadronResolucionService::class)->resolver($revision, $row->id, 101, 'Conservar contrato anterior verificado.', 7);
        $revision->refresh();
        $this->assertSame(50.0, (float) $revision->excesos['111111111']['total']);
        $this->assertStringContainsString('faltan autorización', implode(' ', $this->writer()->plan($revision)['errores']));
        app(PadronRevisionService::class)->authorize($revision, '111111111', 'Excepción de jornada sintética autorizada.', 7);
        $this->assertSame([], $this->writer()->plan($revision->fresh())['errores']);
    }

    public function test_admin_form_offers_explicit_batch_conservation_and_displays_proposed_month(): void
    {
        $revision = $this->revision();
        $this->withoutMiddleware();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $url = route('reemplazos.personal.import', ['revision' => $revision->id, 'q' => '111111111']);
        $this->get($url)->assertOk()->assertSee('Conservar este ID en el período de carga · ID 101')->assertSee('Conservar este ID en el período de carga · ID 103');
        $this->actingAs((new \App\Models\User)->forceFill(['id' => 7]));
        $this->post(route('reemplazos.personal.import.store'), ['accion' => 'resolver_varias', 'revision' => $revision->id,
            'rut' => '111111111', 'q' => '111111111', 'decisiones' => $this->entries($revision)])->assertSessionHasNoErrors()->assertRedirect();
        app('auth')->forgetGuards();
        $this->get($url)->assertOk()->assertSee('Conservación propuesta (ausente del Excel)')->assertSee('mes: 8 → 9')
            ->assertDontSee('IDs contractuales a dar de baja:');
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'mes' => 8]);
    }
}
