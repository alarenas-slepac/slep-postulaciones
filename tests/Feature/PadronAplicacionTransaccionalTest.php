<?php

namespace Tests\Feature;

use App\Models\PadronRevision;
use App\Models\SolicitudReemplazo;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronHistorialService;
use App\Services\Padron\PadronResolucionService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronAplicacionTransaccionalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('rbd');
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon', 'financiamiento'] as $field) {
                $t->string($field);
            }
            foreach (['fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'fecha_antiguedad'] as $field) {
                $t->date($field)->nullable();
            }
            foreach (['anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media', 'bienios'] as $field) {
                $t->integer($field)->nullable();
            }
            $t->string('tramo')->nullable(); $t->boolean('vigente')->default(true);
            $t->string('row_hash')->unique(); $t->string('source_filename')->nullable();
            $t->integer('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->integer('establecimiento_id');
            $t->foreignId('reemplazos_personal_id')->constrained('reemplazos_personal');
            $t->string('docente_rut'); $t->string('estado'); $t->decimal('horas_contrato');
        });
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->foreignId('reemplazo_personal_id')->constrained('reemplazos_personal');
                $t->string('estado')->default('cerrado'); $t->timestamps();
            });
        }
        (require base_path('database/migrations/2026_09_08_120000_create_padron_revisiones.php'))->up();
        (require base_path('database/migrations/2026_09_08_130000_add_padron_aplicacion_segura.php'))->up();
        (require base_path('database/migrations/2026_09_08_150000_add_padron_snapshot_to_documentos.php'))->up();
        (require base_path('database/migrations/2026_09_08_160000_create_padron_periodo_versiones.php'))->up();
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999]);
        $this->personal(101);
        $this->personal(102, ['rut' => '222222222']);
        $this->personal(103, ['rut' => '333333333', 'vigente' => false]);
        $this->personal(91, ['anio' => 2025, 'mes' => 12]);
        $this->personal(92, ['mes' => 7]);
        DB::table('dotacion_docente_asignaciones')->insert([
            'id' => 501, 'anio' => 2026, 'establecimiento_id' => 1, 'reemplazos_personal_id' => 101,
            'docente_rut' => '111111111', 'estado' => 'activa', 'horas_contrato' => 20,
        ]);
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            DB::table($table)->insert(['id' => 1, 'reemplazo_personal_id' => 101, 'updated_at' => '2026-08-01 00:00:00']);
            DB::table($table)->insert(['id' => 2, 'reemplazo_personal_id' => 102]);
        }
    }

    private function data(array $changes = []): array
    {
        return array_replace(['rut' => '111111111', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA', 'tipocontrato' => 'CONTRATA',
            'financiamiento' => 'REGULAR', 'anio' => 2026, 'mes' => 9, 'jornada' => 30,
            'jornada_basica' => 30, 'jornada_media' => 0, 'bienios' => 3,
            'fecha_nacimiento' => '1980-01-01', 'fecha_ingreso' => '2020-03-01',
            'fecha_termino' => null, 'fecha_antiguedad' => null], $changes);
    }

    private function personal(int $id, array $changes = []): void
    {
        DB::table('reemplazos_personal')->insert(array_replace($this->data([
            'mes' => 8, 'jornada' => 44, 'jornada_basica' => 44, 'fecha_antiguedad' => '2010-01-01',
        ]), ['id' => $id, 'establecimiento_id' => 1, 'row_hash' => 'sintetico-'.$id,
            'created_by' => 8, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-08-01 00:00:00'], $changes));
    }

    private function revision(?array $incoming = null): PadronRevision
    {
        $incoming ??= [$this->data(), $this->data(['rut' => '333333333', 'jornada' => 24, 'jornada_basica' => 24]), $this->data(['rut' => '444444444'])];
        $period = $incoming[0]['anio'] * 100 + $incoming[0]['mes'];
        $snapshot = (new \ReflectionMethod(PadronRevisionService::class, 'snapshot'))->invoke(app(PadronRevisionService::class), $period);
        $report = app(PadronConciliador::class)->reconcile(
            array_map(fn ($data, $i) => ['datos' => $data, 'fila_excel' => $i + 2, 'observaciones' => []], $incoming, array_keys($incoming)),
            $snapshot['personal'], $snapshot['establecimientos'], $snapshot['asignaciones'], $snapshot['declaraciones'],
        );
        $revision = PadronRevision::create(['archivo' => 'sintetico.xlsx', 'archivo_hash' => str_repeat('a', 64),
            'base_hash' => $snapshot['hash'], 'created_by' => 1, 'anio' => $incoming[0]['anio'], 'mes' => $incoming[0]['mes'],
            'resumen' => $report['resumen'], 'errores' => $report['errores'], 'excesos' => $report['excesos']]);
        $revision->filas()->createMany($report['filas']);
        return $revision;
    }

    private function writer(): PadronAplicacionService
    {
        // Únicamente esta instancia de prueba alcanza la escritura. No modifica
        // la constante de producción ni registra un binding accesible por rutas.
        return new class(app(PadronRevisionService::class)) extends PadronAplicacionService {
            public function disponible(): bool
            {
                return app()->environment('testing') && DB::connection()->getDatabaseName() === ':memory:';
            }
        };
    }

    private function apply(PadronRevision $revision): PadronRevision
    {
        $writer = $this->writer();
        return $writer->aplicar($revision, 7, $writer->plan($revision)['confirmacion_hash']);
    }

    private function state(): array
    {
        $state = [];
        foreach (['reemplazos_personal', 'dotacion_docente_asignaciones', 'padron_personal_cambios', 'padron_periodo_versiones', 'padron_periodo_personal', ...PadronHistorialService::DOCUMENTOS] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        return $state;
    }

    public function test_atomic_application_preserves_ids_history_and_only_explicit_absences(): void
    {
        $revision = $this->revision();
        $before = $this->state();
        $plan = $this->writer()->plan($revision);
        $this->assertSame([102], $plan['bajas']);
        $this->assertSame([], $plan['errores']);
        $this->apply($revision);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'jornada' => 30, 'mes' => 9,
            'fecha_antiguedad' => '2010-01-01', 'row_hash' => 'sintetico-101', 'created_by' => 8, 'created_at' => '2026-01-01 00:00:00']);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 102, 'vigente' => false]);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 103, 'vigente' => true, 'jornada' => 24]);
        foreach ([91, 92] as $id) {
            $this->assertDatabaseHas('reemplazos_personal', ['id' => $id, 'vigente' => true, 'updated_at' => '2026-08-01 00:00:00']);
        }
        $this->assertDatabaseCount('reemplazos_personal', 6);
        $this->assertSame(44, SolicitudReemplazo::findOrFail(1)->funcionarioTitular->jornada);
        $this->assertSame('2026-08-01 00:00:00', DB::table('solicitudes_reemplazo')->where('id', 1)->value('updated_at'));
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            $this->assertNotNull(DB::table($table)->where('id', 2)->value('padron_personal_snapshot'));
        }
        $this->assertSame($before['dotacion_docente_asignaciones'], $this->state()['dotacion_docente_asignaciones']);
        $this->assertSame(['actualizacion', 'reactivacion', 'incorporacion', 'desactivacion'], DB::table('padron_personal_cambios')->orderBy('id')->pluck('accion')->all());
        $audit = DB::table('padron_personal_cambios')->where('personal_id', 101)->first();
        $this->assertSame(44, json_decode($audit->antes, true)['jornada']);
        $this->assertSame(30, json_decode($audit->despues, true)['jornada']);
        $this->assertSame(7, $revision->fresh()->aplicada_por);
        $this->assertSame([101, 103, 104], \App\Models\ReemplazoPersonal::padronVigente(2026)->orderBy('id')->pluck('id')->all());
        $periodos = app(\App\Services\Padron\PadronPeriodoService::class);
        $this->assertSame(44, $periodos->consultaMensual(2026, 8)->where('id', 101)->firstOrFail()->jornada);
        $this->assertTrue($periodos->consultaMensual(2026, 8)->where('id', 102)->firstOrFail()->vigente);
        $this->assertSame(30, $periodos->consultaMensual(2026, 9)->where('id', 101)->firstOrFail()->jornada);
        $this->assertDatabaseCount('padron_periodo_versiones', 4); // 2025/12, julio, agosto y septiembre.
    }

    public function test_identical_retry_does_not_duplicate_people_audit_or_snapshots(): void
    {
        $revision = $this->revision();
        $token = $this->writer()->plan($revision)['confirmacion_hash'];
        $this->writer()->aplicar($revision, 7, $token);
        $state = $this->state();
        $this->writer()->aplicar($revision, 9, $token); // Instancia anterior a aplicada_at.
        $this->assertSame($state, $this->state());
        $this->assertSame(7, $revision->fresh()->aplicada_por);
    }

    public function test_second_review_of_same_base_is_rejected_after_first_commits(): void
    {
        $first = $this->revision();
        $second = $this->revision();
        $this->assertSame($first->base_hash, $second->base_hash);
        $token = $this->writer()->plan($second)['confirmacion_hash'];
        $this->apply($first);
        $state = $this->state();
        try {
            $this->writer()->aplicar($second, 9, $token);
            $this->fail('Una carga anterior no puede sobrescribir otra aplicación.');
        } catch (ValidationException $e) {
            $this->assertSame($state, $this->state());
            $this->assertNull($second->fresh()->aplicada_at);
        }
    }

    public function test_mid_write_failure_rolls_back_personnel_audit_snapshots_and_completion(): void
    {
        $revision = $this->revision();
        $before = $this->state();
        $audits = 0;
        DB::listen(function (QueryExecuted $event) use (&$audits) {
            if (str_starts_with($event->sql, 'insert into "padron_personal_cambios"') && ++$audits === 2) {
                throw new \RuntimeException('Fallo sintético entre escrituras.');
            }
        });
        try {
            $this->apply($revision);
            $this->fail('Debe revertir toda la operación.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo sintético entre escrituras.', $e->getMessage());
        }
        $this->assertSame($before, $this->state());
        $this->assertNull($revision->fresh()->aplicada_at);
        $this->assertSame(0, DB::transactionLevel());
        $this->apply($revision);
        $this->assertDatabaseCount('padron_personal_cambios', 4);
    }

    public function test_missing_history_migration_rolls_back_partial_freeze(): void
    {
        Schema::table('cometidos_funcionarios', fn (Blueprint $t) => $t->dropColumn('padron_personal_snapshot'));
        $revision = $this->revision();
        $before = $this->state();
        try {
            $this->apply($revision);
            $this->fail('No puede aplicar sin protección histórica.');
        } catch (ValidationException $e) {
            $this->assertSame($before, $this->state());
            $this->assertNull($revision->fresh()->aplicada_at);
        }
    }

    public function test_missing_period_history_schema_prevents_personnel_application(): void
    {
        Schema::drop('padron_periodo_personal'); // Solo la base efímera de pruebas.
        $revision = $this->revision();
        $before = DB::table('reemplazos_personal')->orderBy('id')->get()->toJson();
        try {
            $this->apply($revision);
            $this->fail('No se puede aplicar sin versiones mensuales.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('historial por período', $e->getMessage());
        }
        $this->assertSame($before, DB::table('reemplazos_personal')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('padron_personal_cambios', 0);
        $this->assertDatabaseCount('padron_periodo_versiones', 0);
        $this->assertNull($revision->fresh()->aplicada_at);
    }

    public function test_changed_authorization_after_screen_confirmation_is_rejected(): void
    {
        $revision = $this->revision([$this->data(['jornada' => 45])]);
        $token = $this->writer()->plan($revision)['confirmacion_hash'];
        app(PadronRevisionService::class)->authorize($revision, '111111111', 'Autorización sintética de jornada.', 1);
        $before = $this->state();
        try {
            $this->writer()->aplicar($revision, 7, $token);
            $this->fail('Debe exigir nueva confirmación.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('confirmación', $e->getMessage());
            $this->assertSame($before, $this->state());
        }
    }

    public function test_changed_decision_invalidates_confirmation_even_if_base_is_unchanged(): void
    {
        $this->personal(105, ['financiamiento' => 'PIE']);
        $revision = $this->revision([$this->data()]);
        $fila = $revision->filas->firstWhere('fila_excel', 2);
        $resolution = app(PadronResolucionService::class);
        $resolution->resolver($revision, $fila->id, 101, 'Decisión sintética inicial válida.', 1);
        $token = $this->writer()->plan($revision)['confirmacion_hash'];
        $decisionId = $resolution->decisiones($revision)[$fila->id]->id;
        $resolution->resolver($revision, $fila->id, 105, 'Decisión sintética corregida válida.', 1, $decisionId);
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('confirmación');
        $this->writer()->aplicar($revision, 7, $token);
    }

    public function test_personnel_changes_after_analysis_are_rejected_without_writes(): void
    {
        $revision = $this->revision();
        DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => 35]);
        $before = $this->state();
        try {
            $this->apply($revision);
            $this->fail('No puede sobrescribir cambios posteriores.');
        } catch (ValidationException $e) {
            $this->assertSame($before, $this->state());
        }
    }

    public function test_document_activity_requires_only_new_final_confirmation_and_freezes_new_references(): void
    {
        $revision = $this->revision();
        $writer = $this->writer();
        $token = $writer->plan($revision)['confirmacion_hash'];
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            DB::table($table)->where('id', 1)->update(['estado' => 'aprobado']);
            DB::table($table)->insert(['id' => 3, 'reemplazo_personal_id' => 101]);
            // Una referencia reasignada debe congelar el contrato vigente del nuevo ID.
            DB::table($table)->where('id', 2)->update(['reemplazo_personal_id' => 101]);
        }
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
        $before = $this->state();
        try {
            $writer->aplicar($revision, 7, $token);
            $this->fail('Las dependencias nuevas requieren revisar el plan final.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Recargue la misma revisión', $e->getMessage());
            $this->assertSame($before, $this->state());
        }
        $this->apply($revision);
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            foreach (DB::table($table)->get() as $document) {
                $copy = json_decode($document->padron_personal_snapshot, true)['personal'];
                $this->assertSame(101, $copy['id']);
                $this->assertSame(44, $copy['jornada']);
                $this->assertSame(101, $document->reemplazo_personal_id);
            }
            $this->assertSame('aprobado', DB::table($table)->where('id', 1)->value('estado'));
        }
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'jornada' => 30]);
    }

    public function test_live_assignment_to_absent_contract_blocks_application_without_expiring_review(): void
    {
        $revision = $this->revision();
        $writer = $this->writer();
        $this->assertSame([], $writer->plan($revision)['errores']);
        DB::table('dotacion_docente_asignaciones')->insert([
            'id' => 502, 'anio' => 2026, 'establecimiento_id' => 1, 'reemplazos_personal_id' => 102,
            'docente_rut' => '222222222', 'estado' => 'activa', 'horas_contrato' => 2,
        ]);
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
        $plan = $writer->plan($revision);
        $this->assertSame(1, $plan['conflictos']['bloqueantes']);
        $before = $this->state();
        try {
            $writer->aplicar($revision, 7, $plan['confirmacion_hash']);
            $this->fail('Una revisión editable no autoriza perder vínculos activos.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ID contractual quedaría sin seleccionar', $e->getMessage());
            $this->assertSame($before, $this->state());
        }
        DB::table('dotacion_docente_asignaciones')->where('id', 502)->update(['estado' => 'inactiva']);
        $this->assertSame([], $writer->plan($revision)['errores']);
        $this->apply($revision);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 502, 'reemplazos_personal_id' => 102]);
    }

    public function test_payload_cannot_overwrite_ids_or_internal_contract_metadata(): void
    {
        $revision = $this->revision([$this->data(['id' => 999, 'row_hash' => 'invalido', 'created_by' => 999])]);
        $this->apply($revision);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'row_hash' => 'sintetico-101', 'created_by' => 8]);
        $this->assertDatabaseMissing('reemplazos_personal', ['id' => 999]);
    }

    public function test_missing_control_row_or_invalid_token_prevents_application(): void
    {
        $revision = $this->revision();
        $before = $this->state();
        try {
            $this->writer()->aplicar($revision, 7);
            $this->fail('Debe exigir confirmación.');
        } catch (ValidationException $e) {
            $this->assertSame($before, $this->state());
        }
        DB::table('padron_aplicacion_control')->delete(); // Solo fixture SQLite.
        $this->expectException(\Illuminate\Database\RecordNotFoundException::class);
        $this->apply($revision);
    }

    public function test_new_year_cannot_overwrite_contracts_or_deactivate_previous_years(): void
    {
        $revision = $this->revision([$this->data(['anio' => 2027, 'mes' => 1])]);
        $before = $this->state();
        $idsAntes = \App\Models\ReemplazoPersonal::padronVigente(2026)->orderBy('id')->pluck('id')->all();
        $plan = $this->writer()->plan($revision);
        $this->assertStringContainsString('Cambiar el año 2026 a 2027', implode(' ', $plan['errores']));
        $this->assertStringContainsString('No se puede desactivar una versión del año 2026', implode(' ', $plan['errores']));
        try {
            $this->apply($revision);
            $this->fail('La copia de documentos no basta para proteger la consulta anual.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Dotación histórica', $e->getMessage());
        }
        $this->assertSame($before, $this->state());
        $this->assertSame($idsAntes, \App\Models\ReemplazoPersonal::padronVigente(2026)->orderBy('id')->pluck('id')->all());
        $this->assertNull($revision->fresh()->aplicada_at);
    }

    public function test_annual_guard_rechecks_actual_id_not_the_incoming_previous_values(): void
    {
        $revision = $this->revision([$this->data(['anio' => 2027, 'mes' => 1, 'jornada' => 45])]);
        $revision->filas()->where('fila_excel', 2)->update(['anterior' => $this->data(['anio' => 2027])]);
        app(PadronRevisionService::class)->authorize($revision, '111111111', 'Autorización sintética de exceso, no de historia.', 1);
        $before = $this->state();
        try {
            $this->apply($revision);
            $this->fail('La autorización de horas y los datos anteriores de pantalla no eluden la protección anual.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Cambiar el año 2026 a 2027', $e->getMessage());
        }
        $this->assertSame($before, $this->state());
    }

    public function test_manually_confirming_a_previous_year_absence_does_not_bypass_history_guard(): void
    {
        $revision = $this->revision([$this->data(['anio' => 2027, 'mes' => 1])]);
        $absence = $revision->filas()->whereNull('fila_excel')->where('personal_id', 102)->firstOrFail();
        $absence->update(['accion' => 'ausencia_por_revisar']);
        app(PadronResolucionService::class)->resolver($revision, $absence->id, null, 'Confirmación sintética de ausencia entre años.', 1);
        $this->assertStringContainsString('ID 102: protección de Dotación histórica', implode(' ', $this->writer()->plan($revision)['errores']));
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 102, 'vigente' => true, 'anio' => 2026]);
    }

    public function test_annual_guard_also_covers_assistants_without_assignments_or_documents(): void
    {
        $this->personal(105, ['rut' => '555555555', 'estatuto' => 'ASISTENTE', 'escalafon' => 'AUXILIAR']);
        $revision = $this->revision([$this->data(['rut' => '555555555', 'anio' => 2027, 'mes' => 1,
            'estatuto' => 'ASISTENTE', 'escalafon' => 'AUXILIAR'])]);
        $this->assertSame(105, $revision->filas->firstWhere('fila_excel', 2)->personal_id);
        $this->assertStringContainsString('ID 105: protección de Dotación histórica', implode(' ', $this->writer()->plan($revision)['errores']));
    }

    public function test_overwriting_year_loses_the_annual_row_even_when_document_snapshot_exists(): void
    {
        // Reproducción aislada del consumidor que justifica el bloqueo, no usa el aplicador.
        $this->assertContains(101, \App\Models\ReemplazoPersonal::padronVigente(2026)->pluck('id')->all());
        DB::transaction(fn () => app(PadronHistorialService::class)->congelarReferencias([101]));
        DB::table('reemplazos_personal')->where('id', 101)->update(['anio' => 2027, 'mes' => 1]);
        $this->assertSame(2026, SolicitudReemplazo::findOrFail(1)->funcionarioTitular->anio);
        $this->assertNotContains(101, \App\Models\ReemplazoPersonal::padronVigente(2026)->pluck('id')->all());
    }

    public function test_nested_transaction_is_not_accepted_as_application_boundary(): void
    {
        $revision = $this->revision();
        $this->expectException(ValidationException::class);
        DB::transaction(fn () => $this->apply($revision));
    }

    public function test_actual_service_remains_blocked_even_with_complete_schema_and_valid_confirmation(): void
    {
        $revision = $this->revision();
        $service = app(PadronAplicacionService::class);
        $this->assertFalse($service->disponible());
        $this->expectException(ValidationException::class);
        $service->aplicar($revision, 7, $service->plan($revision)['confirmacion_hash']);
    }

    public function test_omitted_replacement_is_not_written_and_old_id_requires_explicit_absence_resolution(): void
    {
        $old = $this->data(['rut' => '555555555', 'tipocontrato' => 'REEMPLAZO', 'jornada' => 44, 'jornada_basica' => 44,
            'fecha_ingreso' => '2026-07-01', 'fecha_termino' => '2026-07-31']);
        $latest = array_replace($old, ['fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2026-08-31']);
        $this->personal(105, array_replace($old, ['mes' => 8]));
        $this->personal(106, array_replace($latest, ['mes' => 8]));
        DB::table('solicitudes_reemplazo')->insert(['id' => 3, 'reemplazo_personal_id' => 105]);
        $revision = $this->revision([$this->data(), $old, $latest]);
        $filas = $revision->filas;
        $omitida = $filas->firstWhere('fila_excel', 3);
        $absence = $filas->whereNull('fila_excel')->firstWhere('personal_id', 105);
        $resolution = app(PadronResolucionService::class);
        $summary = $resolution->resumen($filas, collect());
        $this->assertSame('omitida_por_vigencia', $summary['estados'][$omitida->id]);
        $this->assertArrayNotHasKey($omitida->id, $summary['selecciones']);
        $view = app(\App\Http\Controllers\Reemplazos\PersonalImportController::class)->create(
            \Illuminate\Http\Request::create('/prueba-padron', 'GET', ['revision' => $revision->id, 'accion_filtro' => 'reemplazo_anterior_omitido'])
        );
        $this->assertSame(1, $view->getData()['filas']->total());
        $this->assertSame($omitida->id, $view->getData()['filas']->first()->id);
        try {
            $resolution->resolver($revision, $omitida->id, null, 'No puede activar una omisión desde correspondencia.', 1);
            $this->fail('Una omisión no es una decisión de correspondencia.');
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('padron_revision_decisiones', 0);
        }
        $before = $this->state();
        $plan = $this->writer()->plan($revision);
        $this->assertCount(2, $plan['destinos']);
        $this->assertNotEmpty($plan['errores']);
        $this->assertSame($before, $this->state());
        $resolution->resolver($revision, $absence->id, null, 'Confirmación sintética de reemplazo anterior.', 1);
        $this->assertSame([], $this->writer()->plan($revision)['errores']);
        $this->apply($revision);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 105, 'vigente' => false, 'mes' => 8, 'jornada' => 44, 'row_hash' => 'sintetico-105']);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 106, 'vigente' => true, 'mes' => 9, 'jornada' => 44, 'row_hash' => 'sintetico-106']);
        $this->assertDatabaseCount('reemplazos_personal', 7);
        $this->assertSame(105, SolicitudReemplazo::findOrFail(3)->funcionarioTitular->id);
        $this->assertSame('2026-07-01', SolicitudReemplazo::findOrFail(3)->funcionarioTitular->fecha_ingreso->toDateString());
        $this->assertSame($before['dotacion_docente_asignaciones'], $this->state()['dotacion_docente_asignaciones']);
        $this->assertSame(1, DB::table('padron_personal_cambios')->where('personal_id', 105)->where('accion', 'desactivacion')->count());
    }

    public function test_forged_omission_of_a_regular_contract_cannot_reach_application(): void
    {
        $revision = $this->revision();
        $revision->filas()->where('fila_excel', 2)->update(['accion' => 'reemplazo_anterior_omitido']);
        $before = $this->state();
        try {
            $this->apply($revision);
            $this->fail('La omisión debe corresponder a la selección real de reemplazos.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('selección de reemplazos', $e->getMessage());
            $this->assertSame($before, $this->state());
        }
    }

    public function test_replacement_to_regular_transition_keeps_the_id_and_the_previous_document_contract(): void
    {
        $old = $this->data(['rut' => '555555555', 'tipocontrato' => 'REEMPLAZO', 'jornada' => 44, 'jornada_basica' => 44,
            'fecha_ingreso' => '2026-07-01', 'fecha_termino' => '2026-07-31']);
        $new = array_replace($old, ['tipocontrato' => 'CONTRATA', 'jornada' => 22, 'jornada_basica' => 22,
            'fecha_ingreso' => '2026-08-01', 'fecha_termino' => null]);
        $this->personal(105, array_replace($old, ['mes' => 8]));
        DB::table('solicitudes_reemplazo')->insert(['id' => 3, 'reemplazo_personal_id' => 105]);
        $revision = $this->revision([$this->data(), $old, $new]);
        $this->assertSame([], $revision->errores);
        $this->assertSame([], $revision->excesos);
        $this->assertSame(105, $revision->filas->firstWhere('fila_excel', 4)->personal_id);
        $plan = $this->writer()->plan($revision);
        $this->assertSame([], $plan['errores']);
        $this->assertCount(2, $plan['destinos']);
        $this->assertNotContains(105, $plan['bajas']);
        $this->apply($revision);
        $this->assertDatabaseCount('reemplazos_personal', 6);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 105, 'tipocontrato' => 'CONTRATA', 'jornada' => 22,
            'row_hash' => 'sintetico-105', 'vigente' => true]);
        $historical = SolicitudReemplazo::findOrFail(3)->funcionarioTitular;
        $this->assertSame(105, $historical->id);
        $this->assertSame('REEMPLAZO', $historical->tipocontrato);
        $this->assertSame(44, $historical->jornada);
    }

    public function test_missing_replacement_dates_cannot_be_bypassed_with_an_excess_authorization(): void
    {
        $revision = $this->revision([$this->data(),
            $this->data(['rut' => '555555555', 'tipocontrato' => 'REEMPLAZO', 'jornada' => 44]),
            $this->data(['rut' => '555555555', 'tipocontrato' => 'REEMPLAZO', 'jornada' => 44, 'financiamiento' => 'SEP']),
        ]);
        $this->assertNotEmpty($revision->errores);
        $before = $this->state();
        try {
            app(PadronRevisionService::class)->authorize($revision, '555555555', 'No debe sustituir la revisión de fechas.', 1);
            $this->fail('La autorización no debe ocultar el error de selección.');
        } catch (ValidationException $e) {
            $this->assertSame($before, $this->state());
            $this->assertDatabaseCount('padron_revision_autorizaciones', 0);
        }
    }

    public function test_change_during_plan_read_does_not_produce_a_confirmation_for_unseen_data(): void
    {
        $revision = $this->revision();
        $changed = false;
        DB::listen(function (QueryExecuted $event) use ($revision, &$changed) {
            // Se intercala después de capturar la versión inicial del plan.
            if (! $changed && str_contains($event->sql, 'from "dotacion_docente_asignaciones"')) {
                $changed = true;
                DB::table('padron_revisiones')->where('id', $revision->id)->update(['archivo' => 'otra-version-sintetica.xlsx']);
            }
        });
        $plan = $this->writer()->plan($revision);
        $this->assertTrue($changed);
        $this->assertContains('La revisión cambió mientras se calculaba el plan. Recargue antes de confirmar.', $plan['errores']);
        $this->assertFalse($this->writer()->confirmacionVigente($revision, $plan['confirmacion_hash']));
    }
}
