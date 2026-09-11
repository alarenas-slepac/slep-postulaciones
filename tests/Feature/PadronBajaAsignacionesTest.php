<?php

namespace Tests\Feature;

use App\Models\PadronRevision;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronBajaAsignacionesService;
use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronConflictosAsignacionService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronBajaAsignacionesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('rbd');
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon', 'financiamiento'] as $field) { $t->string($field); }
            foreach (['anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media'] as $field) { $t->integer($field); }
            $t->date('fecha_antiguedad')->nullable(); $t->boolean('vigente')->default(true);
            $t->string('row_hash')->unique(); $t->string('source_filename')->nullable();
            $t->integer('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('anio')->default(2026); $t->integer('establecimiento_id')->default(1);
            $t->foreignId('reemplazos_personal_id')->nullable()->constrained('reemplazos_personal');
            $t->string('docente_rut')->nullable(); $t->string('docente_rut_normalizado')->nullable();
            $t->string('estado')->default('activa'); $t->decimal('horas_contrato')->default(1);
            $t->string('tipo_asignacion')->default('plan_estudio'); $t->string('necesidad_key')->default('necesidad-sintetica');
            $t->text('observacion')->nullable(); $t->integer('updated_by')->nullable(); $t->timestamps();
        });
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) {
            $t->id(); $t->foreignId('reemplazo_personal_id')->constrained('reemplazos_personal');
            $t->string('estado')->default('cerrado'); $t->timestamps();
        });
        foreach (['2026_09_08_120000_create_padron_revisiones', '2026_09_08_130000_add_padron_aplicacion_segura',
            '2026_09_08_150000_add_padron_snapshot_to_documentos', '2026_09_08_160000_create_padron_periodo_versiones',
            '2026_09_10_120000_create_padron_bajas_asignaciones', '2026_09_11_120000_add_padron_traslados_asignaciones'] as $migration) {
            (require base_path('database/migrations/'.$migration.'.php'))->up();
        }
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Escuela sintética B'],
        ]);
        DB::table('reemplazos_personal')->insert([
            ['id' => 101, 'establecimiento_id' => 1, 'row_hash' => 'sintetico-101'] + $this->data(['mes' => 8]),
            ['id' => 102, 'establecimiento_id' => 1, 'row_hash' => 'sintetico-102'] + $this->data(['rut' => '222222222', 'mes' => 8]),
        ]);
        $this->assignment(501, ['horas_contrato' => 35]);
        $this->assignment(502, ['horas_contrato' => 2, 'reemplazos_personal_id' => null]);
        $this->assignment(503, ['anio' => 2025, 'horas_contrato' => 9]);
        $this->assignment(504, ['estado' => 'inactiva', 'horas_contrato' => 8]);
        DB::table('solicitudes_reemplazo')->insert(['id' => 1, 'reemplazo_personal_id' => 101]);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['rut' => '111111111', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'tipocontrato' => 'PLANTA',
            'financiamiento' => 'REGULAR', 'anio' => 2026, 'mes' => 9, 'jornada' => 36,
            'jornada_basica' => 36, 'jornada_media' => 0], $changes);
    }

    private function assignment(int $id, array $changes = []): void
    {
        DB::table('dotacion_docente_asignaciones')->insert(array_replace([
            'id' => $id, 'reemplazos_personal_id' => 101, 'docente_rut' => '11.111.111-1',
        ], $changes));
    }

    private function revision(?array $incoming = null): PadronRevision
    {
        $incoming ??= [$this->data(['rut' => '222222222'])];
        $snapshot = (new \ReflectionMethod(PadronRevisionService::class, 'snapshot'))->invoke(app(PadronRevisionService::class), 202609);
        $report = app(PadronConciliador::class)->reconcile(
            array_map(fn ($data, $i) => ['datos' => $data, 'fila_excel' => $i + 2, 'observaciones' => []], $incoming, array_keys($incoming)),
            $snapshot['personal'], $snapshot['establecimientos'], $snapshot['asignaciones'], $snapshot['declaraciones'],
        );
        $revision = PadronRevision::create(['archivo' => 'sintetico.xlsx', 'archivo_hash' => str_repeat('a', 64),
            'base_hash' => $snapshot['hash'], 'created_by' => 1, 'anio' => 2026, 'mes' => 9,
            'resumen' => $report['resumen'], 'errores' => $report['errores'], 'excesos' => $report['excesos']]);
        $revision->filas()->createMany($report['filas']);
        return $revision;
    }

    private function candidato(PadronRevision $revision): array
    {
        return app(PadronConflictosAsignacionService::class)->analizar($revision)['bajas_asignaciones']['111111111'];
    }

    private function confirmar(PadronRevision $revision): void
    {
        $c = $this->candidato($revision);
        app(PadronBajaAsignacionesService::class)->registrar($revision, '111111111', $c['alcance_hash'], $c['ultima_id'], 'Retiro sintético confirmado por administración.', 7, true);
    }

    private function writer(): PadronAplicacionService
    {
        return new class(app(PadronRevisionService::class)) extends PadronAplicacionService {
            public function disponible(): bool
            {
                return app()->environment('testing') && DB::connection()->getDatabaseName() === ':memory:';
            }
        };
    }

    private function estado(): array
    {
        $out = [];
        foreach (['reemplazos_personal', 'dotacion_docente_asignaciones', 'solicitudes_reemplazo', 'padron_personal_cambios',
            'padron_asignacion_cambios', 'padron_periodo_versiones', 'padron_periodo_personal', 'padron_revision_decisiones'] as $table) {
            $out[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        return $out;
    }

    private function rechaza(callable $action): void
    {
        try { $action(); $this->fail('Debe rechazar la operación.'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }

    public function test_confirmation_is_explicit_deferred_and_can_be_revoked_with_history(): void
    {
        $revision = $this->revision();
        $before = $this->estado();
        $c = $this->candidato($revision);
        $this->assertTrue($c['elegible']);
        $this->assertFalse($c['confirmada']);
        $this->assertSame(37.0, $c['horas']);
        $this->assertSame([501, 502], array_keys($c['alcance']['asignaciones']));
        $this->assertNotEmpty($this->writer()->plan($revision)['errores']);
        $this->confirmar($revision);
        $this->assertSame($before, $this->estado());
        $this->assertSame([], $this->writer()->plan($revision)['errores']);
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
        $c = $this->candidato($revision);
        $this->assertTrue($c['confirmada']);
        app(PadronBajaAsignacionesService::class)->registrar($revision, '111111111', $c['alcance_hash'], $c['ultima_id'], 'Se retira la confirmación sintética.', 7, false);
        $this->assertFalse($this->candidato($revision)['confirmada']);
        $this->assertNotEmpty($this->writer()->plan($revision)['errores']);
        $this->assertDatabaseCount('padron_bajas_asignaciones', 2);
        $this->assertSame($before, $this->estado());
    }

    public function test_application_releases_only_confirmed_annual_assignments_preserves_history_and_is_idempotent(): void
    {
        $revision = $this->revision();
        $this->confirmar($revision);
        $antes = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->keyBy('id');
        $writer = $this->writer();
        $token = $writer->plan($revision)['confirmacion_hash'];
        $writer->aplicar($revision, 7, $token);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'vigente' => false, 'row_hash' => 'sintetico-101']);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 102, 'vigente' => true, 'mes' => 9]);
        foreach ([501, 502] as $id) {
            $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => $id, 'estado' => 'inactiva', 'updated_by' => 7, 'necesidad_key' => 'necesidad-sintetica']);
            $audit = DB::table('padron_asignacion_cambios')->where('asignacion_id', $id)->first();
            $this->assertSame((array) $antes[$id], json_decode($audit->antes, true));
            $this->assertSame('inactiva', json_decode($audit->despues, true)['estado']);
        }
        $this->assertSame(0.0, (float) DB::table('dotacion_docente_asignaciones')->where('anio', 2026)->where('estado', 'activa')->sum('horas_contrato'));
        foreach ([503, 504] as $id) { $this->assertEquals($antes[$id], DB::table('dotacion_docente_asignaciones')->find($id)); }
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 4);
        $this->assertDatabaseCount('padron_asignacion_cambios', 2);
        $doc = DB::table('solicitudes_reemplazo')->find(1);
        $this->assertSame(101, (int) $doc->reemplazo_personal_id);
        $this->assertNotNull($doc->padron_personal_snapshot);
        $this->assertSame(36, (int) json_decode($doc->padron_personal_snapshot, true)['personal']['jornada']);
        $this->assertDatabaseHas('padron_periodo_personal', ['personal_id' => 101, 'vigente' => true]);
        $after = $this->estado();
        $writer->aplicar($revision, 7, $token);
        $this->assertSame($after, $this->estado());
    }

    public function test_failure_after_assignment_write_rolls_back_contracts_history_and_release_together(): void
    {
        $revision = $this->revision();
        $this->confirmar($revision);
        $before = $this->estado();
        $inject = true;
        DB::listen(static function (QueryExecuted $event) use (&$inject): void {
            if ($inject && str_starts_with(strtolower($event->sql), 'insert into "padron_asignacion_cambios"')) {
                throw new \RuntimeException('fallo-sintetico-auditoria');
            }
        });
        try {
            $writer = $this->writer();
            $writer->aplicar($revision, 7, $writer->plan($revision)['confirmacion_hash']);
            $this->fail('Debe provocar rollback.');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo-sintetico-auditoria', $e->getMessage());
        } finally { $inject = false; }
        $this->assertSame($before, $this->estado());
        $this->assertNull($revision->fresh()->aplicada_at);
    }

    public function test_scope_changes_require_new_confirmation_and_reject_the_old_final_token(): void
    {
        $revision = $this->revision();
        $this->confirmar($revision);
        $c = $this->candidato($revision);
        $writer = $this->writer();
        $token = $writer->plan($revision)['confirmacion_hash'];
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['observacion' => 'Cambio sintético de alcance']);
        $this->assertTrue($this->candidato($revision)['desactualizada']);
        $this->assertNotEmpty($writer->plan($revision)['errores']);
        $this->rechaza(fn () => app(PadronBajaAsignacionesService::class)->registrar($revision, '111111111', $c['alcance_hash'], $c['ultima_id'], 'Confirmación con alcance anterior.', 7, true));
        $this->confirmar($revision);
        $this->assertSame([], $writer->plan($revision)['errores']);
        $before = $this->estado();
        $this->rechaza(fn () => $writer->aplicar($revision, 7, $token));
        $this->assertSame($before, $this->estado());
    }

    public function test_same_rut_anywhere_in_file_is_not_a_complete_retirement(): void
    {
        foreach ([['rbd' => 99998], ['tipocontrato' => 'REEMPLAZO'], ['tipocontrato' => 'DESCONOCIDO']] as $change) {
            $revision = $this->revision([$this->data(['rut' => '222222222']), $this->data($change)]);
            $this->assertArrayNotHasKey('111111111', app(PadronConflictosAsignacionService::class)->analizar($revision)['bajas_asignaciones']);
            $this->rechaza(fn () => app(PadronBajaAsignacionesService::class)->registrar($revision, '111111111', str_repeat('a', 64), 0, 'No es una baja completa del archivo.', 7, true));
        }
    }

    public function test_contradictory_identity_or_other_contractual_establishment_cannot_be_released(): void
    {
        foreach ([['reemplazos_personal_id' => 102], ['docente_rut_normalizado' => '222222222'], ['establecimiento_id' => 2]] as $change) {
            DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(array_replace([
                'reemplazos_personal_id' => 101, 'docente_rut_normalizado' => null, 'establecimiento_id' => 1,
            ], $change));
            $revision = $this->revision();
            $this->assertFalse($this->candidato($revision)['elegible']);
            $this->rechaza(fn () => $this->confirmar($revision));
        }
    }

    public function test_complete_retirement_covers_all_establishments_but_not_other_people(): void
    {
        DB::table('reemplazos_personal')->insert(['id' => 103, 'establecimiento_id' => 2, 'row_hash' => 'sintetico-103']
            + $this->data(['rbd' => 99998, 'mes' => 8]));
        $this->assignment(505, ['reemplazos_personal_id' => 103, 'establecimiento_id' => 2, 'horas_contrato' => 3]);
        $this->assignment(506, ['reemplazos_personal_id' => 102, 'docente_rut' => '222222222', 'horas_contrato' => 4]);
        $revision = $this->revision();
        $c = $this->candidato($revision);
        $this->assertSame([101, 103], $c['alcance']['bajas']);
        $this->assertSame(2, $c['establecimientos']);
        $this->assertSame(40.0, $c['horas']);
        $this->confirmar($revision);
        $writer = $this->writer();
        $writer->aplicar($revision, 7, $writer->plan($revision)['confirmacion_hash']);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 103, 'vigente' => false]);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 505, 'estado' => 'inactiva']);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 506, 'estado' => 'activa']);
        $this->assertDatabaseCount('padron_asignacion_cambios', 3);
    }

    public function test_adding_an_assignment_invalidates_only_its_retirement_confirmation(): void
    {
        $revision = $this->revision();
        $this->confirmar($revision);
        $this->assignment(505, ['reemplazos_personal_id' => 102, 'docente_rut' => '222222222', 'horas_contrato' => 3]);
        $this->assertTrue($this->candidato($revision)['confirmada']);
        $this->assignment(506, ['horas_contrato' => 3]);
        $this->assertFalse($this->candidato($revision)['confirmada']);
        $this->assertTrue($this->candidato($revision)['desactualizada']);
        $this->assertSame(40.0, $this->candidato($revision)['horas']);
        $this->assertNotEmpty($this->writer()->plan($revision)['errores']);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 501, 'estado' => 'activa']);
    }

    public function test_pending_absences_and_old_year_contracts_cannot_bypass_retirement_validation(): void
    {
        $revision = $this->revision();
        $revision->filas()->whereNull('fila_excel')->update(['accion' => 'ausencia_por_revisar']);
        $this->assertFalse($this->candidato($revision)['elegible']);
        $this->rechaza(fn () => $this->confirmar($revision));
        $revision->filas()->whereNull('fila_excel')->update(['accion' => 'baja_propuesta']);
        DB::table('reemplazos_personal')->where('id', 101)->update(['anio' => 2025]);
        $this->assertFalse($this->candidato($revision)['elegible']);
        $this->rechaza(fn () => $this->confirmar($revision));
        $this->assertDatabaseCount('padron_bajas_asignaciones', 0);
    }

    public function test_confirmation_versions_retries_validation_and_closed_review(): void
    {
        $revision = $this->revision();
        $c = $this->candidato($revision);
        $this->confirmar($revision);
        $service = app(PadronBajaAsignacionesService::class);
        $service->registrar($revision, '111111111', $c['alcance_hash'], 0, 'Retiro sintético confirmado por administración.', 7, true);
        $this->assertDatabaseCount('padron_bajas_asignaciones', 1);
        $this->rechaza(fn () => $service->registrar($revision, '111111111', $c['alcance_hash'], 0, 'Otra confirmación desde pestaña antigua.', 8, true));
        $this->rechaza(fn () => $service->registrar($revision, '111111111', $c['alcance_hash'], 1, '   ', 7, true));
        $revision->forceFill(['aplicada_at' => now()])->save();
        $this->rechaza(fn () => $this->confirmar($revision));
        $this->assertDatabaseCount('padron_bajas_asignaciones', 1);
    }

    public function test_existing_admin_route_requires_explicit_acceptance_and_renders_deferred_scope(): void
    {
        $revision = $this->revision();
        $this->withoutMiddleware();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $this->get(route('reemplazos.personal.import', ['revision' => $revision->id]))->assertOk()
            ->assertSee('Confirmar baja y liberar asignaciones al aplicar')->assertSee('37 h');
        $this->actingAs((new \App\Models\User)->forceFill(['id' => 7]));
        $c = $this->candidato($revision);
        $payload = ['accion' => 'confirmar_baja_asignaciones', 'revision' => $revision->id, 'rut' => '111111111',
            'alcance_hash' => $c['alcance_hash'], 'decision_anterior' => 0, 'justificacion' => 'Retiro sintético verificado.', 'conflictos_page' => 1];
        $this->post(route('reemplazos.personal.import.store'), $payload)->assertSessionHasErrors('confirmar_alcance');
        $this->assertDatabaseCount('padron_bajas_asignaciones', 0);
        $this->post(route('reemplazos.personal.import.store'), $payload + ['confirmar_alcance' => 1])->assertRedirect();
        $this->assertDatabaseCount('padron_bajas_asignaciones', 1);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 501, 'estado' => 'activa']);
        app('auth')->forgetGuards();
        $this->get(route('reemplazos.personal.import', ['revision' => $revision->id]))->assertOk()
            ->assertSee('Liberación confirmada, pendiente de aplicar.')->assertSee('Retirar confirmación de liberación');
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['establecimiento_id' => 2]);
        $this->get(route('reemplazos.personal.import', ['revision' => $revision->id]))->assertOk()
            ->assertSee('El alcance cambió.')->assertSee('Retirar confirmación de liberación');
    }
    public function test_transfer_can_confirm_deferred_release_of_source_assignments_without_deactivating_contract(): void
    {
        // El funcionario mantiene el contrato y aparece en el RBD 99998, pero
        // sus dos asignaciones activas todavía pertenecen al RBD 99999.
        DB::table('dotacion_docente_asignaciones')->where('id', 502)->update([
            'reemplazos_personal_id' => 101, 'docente_rut' => null, 'docente_rut_normalizado' => null, 'establecimiento_id' => 1,
        ]);
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['docente_rut' => null, 'docente_rut_normalizado' => null]);
        $revision = $this->revision([$this->data(['rbd' => 99998])]);
        $diagnosis = app(PadronConflictosAsignacionService::class)->analizar($revision);
        $key = '111111111|1';
        $traslado = $diagnosis['traslados_asignaciones'][$key];
        $this->assertTrue($traslado['elegible']);
        $this->assertFalse($traslado['confirmada']);
        $this->assertSame(2, $traslado['destino']);
        $this->assertSame([501, 502], array_keys($traslado['alcance']['asignaciones']));
        $this->assertNotEmpty($diagnosis['grupos']);
        $this->assertNotEmpty($diagnosis['grupos'][0]['motivos']);

        $service = app(PadronBajaAsignacionesService::class);
        $service->registrarTraslado($revision, '111111111', 1, 2, $traslado['alcance_hash'], $traslado['ultima_id'],
            'Traslado al RBD destino verificado por Dotación.', 7, true);
        $resolved = app(PadronConflictosAsignacionService::class)->analizar($revision);
        $this->assertSame(0, $resolved['grupos_bloqueantes']);
        $this->assertCount(1, $resolved['grupos']);
        $this->assertTrue($resolved['traslados_asignaciones'][$key]['confirmada']);
        $this->assertSame([], $this->writer()->plan($revision)['errores']);

        $before = DB::table('reemplazos_personal')->find(101);
        $writer = $this->writer();
        $writer->aplicar($revision, 7, $writer->plan($revision)['confirmacion_hash']);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'vigente' => true, 'establecimiento_id' => 2]);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 501, 'estado' => 'inactiva']);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 502, 'estado' => 'inactiva']);
        $this->assertDatabaseCount('padron_asignacion_cambios', 2);
        $this->assertDatabaseHas('padron_bajas_asignaciones', ['tipo' => 'traslado', 'rut' => '111111111', 'confirmada' => true]);
        $this->assertSame($before->vigente, DB::table('reemplazos_personal')->find(101)->vigente);
    }

    public function test_admin_route_requires_explicit_transfer_scope_acceptance(): void
    {
        DB::table('dotacion_docente_asignaciones')->where('id', 502)->update([
            'reemplazos_personal_id' => 101, 'docente_rut' => null, 'docente_rut_normalizado' => null, 'establecimiento_id' => 1,
        ]);
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['docente_rut' => null, 'docente_rut_normalizado' => null]);
        $revision = $this->revision([$this->data(['rbd' => 99998])]);
        $candidate = app(PadronConflictosAsignacionService::class)->analizar($revision)['traslados_asignaciones']['111111111|1'];
        $this->withoutMiddleware();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $this->get(route('reemplazos.personal.import', ['revision' => $revision->id]))->assertOk()
            ->assertSee('Liberación de asignaciones por traslado');
        $this->actingAs((new \App\Models\User)->forceFill(['id' => 7]));
        $payload = ['accion' => 'confirmar_traslado_asignaciones', 'revision' => $revision->id, 'rut' => '111111111',
            'origen' => 1, 'destino' => 2, 'alcance_hash' => $candidate['alcance_hash'], 'decision_anterior' => 0,
            'justificacion' => 'Traslado verificado por administración.', 'conflictos_page' => 1];
        $this->post(route('reemplazos.personal.import.store'), $payload)->assertSessionHasErrors('confirmar_alcance');
        $this->post(route('reemplazos.personal.import.store'), $payload + ['confirmar_alcance' => 1])->assertRedirect();
        $this->assertDatabaseHas('padron_bajas_asignaciones', ['tipo' => 'traslado', 'rut' => '111111111', 'confirmada' => true]);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 501, 'estado' => 'activa']);
    }
}
