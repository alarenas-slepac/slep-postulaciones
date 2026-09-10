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
            $t->boolean('vigente')->default(true); $t->date('fecha_antiguedad')->nullable(); $t->date('fecha_termino')->nullable();
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
            '2026_09_10_120000_create_padron_bajas_asignaciones'] as $migration) {
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

    private function revision(): PadronRevision
    {
        $service = app(PadronRevisionService::class);
        $snapshot = (new \ReflectionMethod($service, 'snapshot'))->invoke($service, 202609);
        $report = app(PadronConciliador::class)->reconcile([
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
