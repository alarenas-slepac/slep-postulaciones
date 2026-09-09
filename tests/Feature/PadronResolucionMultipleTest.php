<?php

namespace Tests\Feature;

use App\Http\Controllers\Reemplazos\PersonalImportController;
use App\Models\PadronRevision;
use App\Models\User;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronResolucionService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronResolucionMultipleTest extends TestCase
{
    private PadronRevision $revision;
    private int $checks = 0;
    private bool $stale = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('reemplazos_personal', function (Blueprint $t) { $t->id(); $t->string('rut'); });
        DB::table('reemplazos_personal')->insert([['id' => 101, 'rut' => '111111111'], ['id' => 102, 'rut' => '111111111']]);
        foreach (['2026_09_08_120000_create_padron_revisiones.php', '2026_09_08_130000_add_padron_aplicacion_segura.php'] as $file) {
            (require base_path('database/migrations/'.$file))->up();
        }
        $this->revision = PadronRevision::create(['created_by' => 1, 'archivo' => 'sintetico.xlsx',
            'archivo_hash' => str_repeat('a', 64), 'base_hash' => str_repeat('b', 64),
            'anio' => 2026, 'mes' => 8, 'resumen' => [], 'errores' => [], 'excesos' => []]);
        foreach ([1, 2, 3] as $id) {
            $this->revision->filas()->create(['id' => $id, 'fila_excel' => $id + 1, 'rut' => '111111111',
                'nombre' => 'Persona sintética', 'accion' => 'revision_manual',
                'datos' => ['tipocontrato' => 'PLANTA'], 'candidatos' => [['id' => 101], ['id' => 102]],
                'asignaciones' => [], 'observaciones' => []]);
        }
        $this->revision->filas()->create(['id' => 4, 'fila_excel' => null, 'personal_id' => 102, 'rut' => '111111111',
            'accion' => 'ausencia_por_revisar', 'datos' => [], 'candidatos' => [], 'asignaciones' => [], 'observaciones' => []]);
        $revisiones = $this->createMock(PadronRevisionService::class);
        $revisiones->method('stale')->willReturnCallback(function () { $this->checks++; return $this->stale; });
        $this->app->instance(PadronRevisionService::class, $revisiones);
    }

    private function entry(int $fila, ?int $id, array $extra = []): array
    {
        return array_replace(['fila' => $fila, 'personal_id' => $id,
            'justificacion' => 'Correspondencia sintética revisada.', 'decision_anterior' => 0], $extra);
    }

    private function resolve(array $entries): int
    {
        return app(PadronResolucionService::class)->resolverVarias($this->revision, '11.111.111-1', $entries, 1);
    }

    public function test_records_selected_rows_together_checks_base_once_and_keeps_other_rows_pending(): void
    {
        $before = DB::table('reemplazos_personal')->get()->toJson();
        $this->assertSame(2, $this->resolve([$this->entry(1, 101), $this->entry(2, 102)]));
        $this->assertSame(1, $this->checks);
        $service = app(PadronResolucionService::class);
        $summary = $service->resumen($this->revision->filas()->get(), $service->decisiones($this->revision));
        $this->assertSame('pendiente', $summary['estados'][3]);
        $this->assertSame('ausencia_vinculada', $summary['estados'][4]);
        $this->assertSame($before, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
        $this->assertNull($this->revision->fresh()->aplicada_at);
    }

    public function test_rejects_entire_batch_for_duplicate_id_or_covered_absence_in_any_order(): void
    {
        foreach ([[$this->entry(1, 101), $this->entry(2, 101)],
            [$this->entry(4, null), $this->entry(2, 102)],
            [$this->entry(2, 102), $this->entry(4, null)],
            [$this->entry(1, 101), $this->entry(2, 999)]] as $entries) {
            try { $this->resolve($entries); $this->fail('Debe rechazar el grupo.'); }
            catch (ValidationException) { $this->assertDatabaseCount('padron_revision_decisiones', 0); }
        }
    }

    public function test_same_rut_is_required_even_for_a_valid_candidate(): void
    {
        $this->revision->filas()->whereKey(2)->update(['rut' => '222222222']);
        try { $this->resolve([$this->entry(1, 101), $this->entry(2, 102)]); $this->fail('No puede mezclar RUT.'); }
        catch (ValidationException $e) {
            $this->assertStringContainsString('mismo RUT', $e->getMessage());
            $this->assertDatabaseCount('padron_revision_decisiones', 0);
        }
    }

    public function test_missing_selection_duplicate_row_unknown_row_and_short_reason_are_rejected(): void
    {
        $missing = $this->entry(2, null); unset($missing['personal_id']);
        foreach ([[$this->entry(1, 101), $missing], [$this->entry(1, 101), $this->entry(1, 102)],
            [$this->entry(1, 101), $this->entry(999, 102)], [$this->entry(1, 101), $this->entry(2, 102, ['justificacion' => 'breve'])]] as $entries) {
            try { $this->resolve($entries); $this->fail('Debe validar todas las entradas.'); }
            catch (ValidationException) { $this->assertDatabaseCount('padron_revision_decisiones', 0); }
        }
    }

    public function test_explicit_new_line_and_uncovered_absence_are_allowed_together(): void
    {
        $this->assertSame(3, $this->resolve([$this->entry(4, null), $this->entry(1, 101), $this->entry(2, null)]));
        $this->assertDatabaseCount('padron_revision_decisiones', 3);
    }

    public function test_retry_is_idempotent_and_one_stale_decision_rejects_the_whole_correction(): void
    {
        $entries = [$this->entry(1, 101), $this->entry(2, 102)];
        $this->assertSame(2, $this->resolve($entries));
        $this->assertSame(0, $this->resolve($entries));
        try { $this->resolve([$this->entry(3, null), $this->entry(2, null)]); $this->fail('Debe exigir versión vigente.'); }
        catch (ValidationException) { $this->assertDatabaseCount('padron_revision_decisiones', 2); }
    }

    public function test_id_swap_validates_final_state_and_keeps_decision_history(): void
    {
        $this->resolve([$this->entry(1, 101), $this->entry(2, 102)]);
        $service = app(PadronResolucionService::class);
        $previous = $service->decisiones($this->revision);
        $this->assertSame(2, $this->resolve([
            $this->entry(1, 102, ['decision_anterior' => $previous[1]->id]),
            $this->entry(2, 101, ['decision_anterior' => $previous[2]->id]),
        ]));
        $this->assertDatabaseCount('padron_revision_decisiones', 4);
        $this->assertSame(102, $service->decisiones($this->revision)[1]->personal_id);
    }

    public function test_stale_or_closed_review_cannot_be_resolved_in_batch(): void
    {
        foreach ([true, false] as $stale) {
            $this->stale = $stale;
            if (! $stale) { $this->revision->update(['aplicada_at' => now()]); }
            try { $this->resolve([$this->entry(1, 101)]); $this->fail('Debe rechazar revisión no editable.'); }
            catch (ValidationException) { $this->assertDatabaseCount('padron_revision_decisiones', 0); }
        }
    }

    public function test_write_failure_rolls_back_all_decisions(): void
    {
        DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $event): void {
            if (str_starts_with($event->sql, 'insert into "padron_revision_decisiones"')) {
                throw new \RuntimeException('Fallo sintético de guardado múltiple.');
            }
        });
        try { $this->resolve([$this->entry(1, 101), $this->entry(2, 102)]); $this->fail('Debe revertir todo.'); }
        catch (\RuntimeException $e) {
            $this->assertSame('Fallo sintético de guardado múltiple.', $e->getMessage());
            $this->assertDatabaseCount('padron_revision_decisiones', 0);
            $this->assertSame(0, DB::transactionLevel());
        }
    }

    public function test_batch_cannot_modify_automatic_rows_or_rows_from_another_review(): void
    {
        $this->revision->filas()->whereKey(2)->update(['accion' => 'actualizacion_propuesta']);
        try { $this->resolve([$this->entry(1, 101), $this->entry(2, 102)]); $this->fail('No debe modificar propuestas automáticas.'); }
        catch (ValidationException) { $this->assertDatabaseCount('padron_revision_decisiones', 0); }
        $other = $this->revision->replicate(); $other->save();
        $this->revision->filas()->whereKey(2)->update(['accion' => 'revision_manual', 'padron_revision_id' => $other->id]);
        try { $this->resolve([$this->entry(1, 101), $this->entry(2, 102)]); $this->fail('No debe resolver otra revisión.'); }
        catch (ValidationException) { $this->assertDatabaseCount('padron_revision_decisiones', 0); }
    }

    public function test_batch_endpoint_preserves_context_and_requires_explicit_selection(): void
    {
        $this->withoutMiddleware();
        $user = new User; $user->id = 1; $this->actingAs($user);
        $payload = ['accion' => 'resolver_varias', 'revision' => $this->revision->id, 'rut' => '111111111',
            'q' => '111111111', 'caso_rut' => '111111111', 'caso_establecimiento' => 1,
            'decisiones' => [$this->entry(1, 101), $this->entry(2, 102)]];
        $response = $this->post(route('reemplazos.personal.import.store'), $payload)->assertSessionHasNoErrors();
        $this->assertStringContainsString('avanzar_caso=1', $response->headers->get('Location'));
        $this->assertStringContainsString('q=111111111', $response->headers->get('Location'));
        $this->assertDatabaseCount('padron_revision_decisiones', 2);
        $payload['decisiones'] = [$this->entry(3, null)];
        $this->post(route('reemplazos.personal.import.store'), $payload)->assertSessionHasErrors('decisiones.0.personal_id');
        session()->forget('errors');
        $payload['decisiones'][0]['personal_id'] = 0;
        $this->post(route('reemplazos.personal.import.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('padron_revision_decisiones', 3);
    }

    public function test_partial_view_keeps_batch_selection_reason_and_original_version_after_error(): void
    {
        session()->flashInput(['decisiones' => [$this->entry(1, 101, ['decision_anterior' => 25])]]);
        $request = Request::create('/', 'GET', [
            'revision' => $this->revision->id, 'q' => '111111111', 'solo_filas' => 1,
        ]);
        $request->setLaravelSession(session()->driver());
        $this->app->instance('request', $request);
        $html = app(PersonalImportController::class)->create($request)->getContent();
        $this->assertStringContainsString('data-padron-resolver-varias', $html);
        $this->assertStringContainsString('value="101" selected', $html);
        $this->assertStringContainsString('name="decision_anterior" value="25"', $html);
        $this->assertStringContainsString('Correspondencia sintética revisada.</textarea>', $html);
    }
}
