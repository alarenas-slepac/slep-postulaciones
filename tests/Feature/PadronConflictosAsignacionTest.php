<?php

namespace Tests\Feature;

use App\Http\Controllers\Reemplazos\PersonalImportController;
use App\Models\PadronRevision;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronConflictosAsignacionService;
use App\Services\Padron\PadronResolucionService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronConflictosAsignacionTest extends TestCase
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
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon', 'financiamiento'] as $field) {
                $t->string($field);
            }
            $t->integer('anio'); $t->integer('mes'); $t->decimal('jornada');
            $t->decimal('jornada_basica'); $t->decimal('jornada_media'); $t->boolean('vigente')->default(true);
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('anio')->default(2026); $t->integer('establecimiento_id')->default(1);
            $t->integer('reemplazos_personal_id')->nullable(); $t->string('docente_rut')->nullable();
            $t->string('docente_rut_normalizado')->nullable(); $t->string('estado')->default('activa');
            $t->string('estamento_cobertura')->nullable(); $t->decimal('horas_contrato')->default(20);
            $t->string('tipo_asignacion')->default('plan_estudio'); $t->string('asignatura_nombre')->nullable();
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->string('rbd'); $t->decimal('horas_contratadas')->nullable();
            $t->string('estamento')->nullable();
        });
        Schema::create('dotacion_docente_exclusiones', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            $t->string('docente_rut'); $t->string('docente_rut_normalizado')->nullable(); $t->decimal('horas');
        });
        (require base_path('database/migrations/2026_09_08_120000_create_padron_revisiones.php'))->up();
        (require base_path('database/migrations/2026_09_08_130000_add_padron_aplicacion_segura.php'))->up();
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Escuela sintética B'],
        ]);
        DB::table('reemplazos_personal')->insert(['id' => 101, 'establecimiento_id' => 1] + $this->data(['mes' => 8]));
        $this->assignment(501);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['rut' => '111111111', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA', 'tipocontrato' => 'CONTRATA',
            'financiamiento' => 'REGULAR', 'anio' => 2026, 'mes' => 9, 'jornada' => 44,
            'jornada_basica' => 44, 'jornada_media' => 0], $changes);
    }

    private function assignment(int $id, array $changes = []): void
    {
        DB::table('dotacion_docente_asignaciones')->insert(array_replace([
            'id' => $id, 'reemplazos_personal_id' => 101, 'docente_rut' => '11.111.111-1',
        ], $changes));
    }

    private function revision(array $incoming): PadronRevision
    {
        $report = app(PadronConciliador::class)->reconcile(
            array_map(fn ($data, $i) => ['datos' => $data, 'fila_excel' => $i + 2, 'observaciones' => []], $incoming, array_keys($incoming)),
            DB::table('reemplazos_personal')->get()->map(fn ($r) => (array) $r)->all(), [99999 => 1, 99998 => 2],
        );
        $snapshot = new \ReflectionMethod(PadronRevisionService::class, 'snapshot');
        $hash = $snapshot->invoke(app(PadronRevisionService::class), 202609)['hash'];
        $revision = PadronRevision::create(['archivo' => 'sintetico.xlsx', 'archivo_hash' => str_repeat('a', 64),
            'base_hash' => $hash, 'created_by' => 1, 'anio' => 2026, 'mes' => 9,
            'resumen' => $report['resumen'], 'errores' => $report['errores'], 'excesos' => $report['excesos']]);
        $revision->filas()->createMany($report['filas']);
        return $revision;
    }

    private function diagnosis(PadronRevision $revision): array
    {
        return app(PadronConflictosAsignacionService::class)->analizar($revision);
    }

    public function test_unchanged_contract_and_inactive_or_other_year_assignments_do_not_block(): void
    {
        $this->assignment(502, ['estado' => 'inactiva', 'horas_contrato' => 99]);
        $this->assignment(503, ['anio' => 2025, 'horas_contrato' => 99]);
        $diagnosis = $this->diagnosis($this->revision([$this->data()]));
        $this->assertSame([], $diagnosis['errores']);
        $this->assertSame(1, $diagnosis['asignaciones_revisadas']);
        $this->assertSame(0, $diagnosis['bloqueantes']);
    }

    public function test_reduction_blocks_total_coverage_without_double_counting_basic_and_media(): void
    {
        $this->assignment(502, ['horas_contrato' => 15]);
        $revision = $this->revision([$this->data(['jornada' => 30, 'jornada_basica' => 20, 'jornada_media' => 10])]);
        $diagnosis = $this->diagnosis($revision);
        $this->assertSame(2, $diagnosis['bloqueantes']);
        $this->assertSame(35.0, $diagnosis['items'][0]['total_asignadas']);
        $this->assertSame(30.0, $diagnosis['items'][0]['cobertura']['horas']);
        $this->assertArrayHasKey('cobertura_insuficiente', $diagnosis['items'][0]['motivos']);
        $this->assertNotEmpty(app(PadronAplicacionService::class)->plan($revision)['errores']);
    }

    public function test_latest_declaration_has_priority_not_sum_and_zero_falls_back(): void
    {
        DB::table('declaracion_sostenedores')->insert([
            ['id' => 1, 'rut' => '111111111', 'rbd' => '99999', 'horas_contratadas' => 10, 'estamento' => 'DOCENTE'],
            ['id' => 2, 'rut' => '111111111', 'rbd' => '99999', 'horas_contratadas' => 40, 'estamento' => 'DOCENTE'],
        ]);
        $revision = $this->revision([$this->data(['jornada' => 15])]);
        $diagnosis = $this->diagnosis($revision);
        $this->assertSame(0, $diagnosis['bloqueantes']);
        $this->assertSame(40.0, $diagnosis['items'][0]['cobertura']['horas']);
        $this->assertSame('Declaración de Sostenedores', $diagnosis['items'][0]['cobertura']['fuente']);
        DB::table('declaracion_sostenedores')->where('id', 2)->update(['horas_contratadas' => 0]);
        $diagnosis = $this->diagnosis($revision);
        $this->assertSame(1, $diagnosis['bloqueantes']);
        $this->assertSame(15.0, $diagnosis['items'][0]['cobertura']['horas']);
    }

    public function test_lower_declaration_and_docente_exclusions_reduce_coverage(): void
    {
        DB::table('declaracion_sostenedores')->insert(['rut' => '111111111', 'rbd' => '99999', 'horas_contratadas' => 25]);
        DB::table('dotacion_docente_exclusiones')->insert([
            'establecimiento_id' => 1, 'anio' => 2026, 'docente_rut' => '11111111-1', 'horas' => 10,
        ]);
        $diagnosis = $this->diagnosis($this->revision([$this->data()]));
        $this->assertSame(15.0, $diagnosis['items'][0]['cobertura']['horas']);
        $this->assertSame(15.0, $diagnosis['grupos'][0]['cobertura_actual']['horas']);
        $this->assertSame(0, $diagnosis['bloqueantes']);
        $this->assertSame('preexistente', $diagnosis['grupos'][0]['comparacion']['estado']);
        $this->assertSame(5.0, $diagnosis['grupos'][0]['comparacion']['exceso_actual']);
    }

    public function test_transfer_and_absence_keep_id_conflict_even_with_another_contract_or_declaration(): void
    {
        $revision = $this->revision([$this->data(['rbd' => 99998])]);
        $this->assertArrayHasKey('traslado', $this->diagnosis($revision)['items'][0]['motivos']);
        $this->assertArrayHasKey('sin_contrato_regular', $this->diagnosis($revision)['items'][0]['motivos']);
        DB::table('declaracion_sostenedores')->insert(['rut' => '111111111', 'rbd' => '99999', 'horas_contratadas' => 44]);
        $absence = $this->revision([$this->data(['rut' => '222222222'])]);
        $diagnosis = $this->diagnosis($absence);
        $this->assertArrayHasKey('id_sin_destino', $diagnosis['items'][0]['motivos']);
        $this->assertSame(0.0, $diagnosis['items'][0]['cobertura']['horas']);
    }

    public function test_replacement_and_assistant_change_do_not_cover_docente_assignments(): void
    {
        foreach (['REEMPLAZO', 'SUPLENCIA'] as $contract) {
            $diagnosis = $this->diagnosis($this->revision([$this->data(['tipocontrato' => $contract])]));
            $this->assertArrayHasKey('contrato_excluido', $diagnosis['items'][0]['motivos']);
        }
        $diagnosis = $this->diagnosis($this->revision([$this->data(['estatuto' => 'ASISTENTE', 'escalafon' => 'AUXILIAR'])]));
        $this->assertArrayHasKey('estamento', $diagnosis['items'][0]['motivos']);
        $this->assertSame('asistente', $diagnosis['items'][0]['cobertura']['estamento']);
    }

    public function test_assistant_assignments_respect_declared_estamento_and_contract_hours(): void
    {
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['estamento_cobertura' => 'asistente']);
        DB::table('declaracion_sostenedores')->insert(['rut' => '111111111', 'rbd' => '99999', 'horas_contratadas' => 40, 'estamento' => 'ASISTENTE']);
        $this->assertSame(0, $this->diagnosis($this->revision([$this->data()]))['bloqueantes']);
        DB::table('declaracion_sostenedores')->update(['estamento' => 'DOCENTE']);
        $this->assertSame(1, $this->diagnosis($this->revision([$this->data()]))['bloqueantes']);
    }

    public function test_changes_with_sufficient_coverage_are_not_a_blanket_block(): void
    {
        $diagnosis = $this->diagnosis($this->revision([$this->data(['tipocontrato' => 'TITULAR', 'jornada' => 30])]));
        $this->assertSame(0, $diagnosis['bloqueantes']);
        $this->assertSame(1, $diagnosis['avisos']);
        $this->assertCount(2, $diagnosis['items'][0]['avisos']);
    }

    public function test_rut_links_without_id_are_checked_and_rut_disagreement_is_blocked(): void
    {
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['reemplazos_personal_id' => null]);
        $this->assertSame(1, $this->diagnosis($this->revision([$this->data(['jornada' => 10])]))['bloqueantes']);
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['reemplazos_personal_id' => 101, 'docente_rut' => '222222222']);
        $this->assertArrayHasKey('rut_incompatible', $this->diagnosis($this->revision([$this->data()]))['items'][0]['motivos']);
    }

    public function test_contract_components_sum_only_within_same_establishment_and_not_per_assignment(): void
    {
        DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => 20, 'jornada_basica' => 20]);
        DB::table('reemplazos_personal')->insert(['id' => 102, 'establecimiento_id' => 1] + $this->data(['mes' => 8, 'financiamiento' => 'PIE', 'jornada' => 24, 'jornada_basica' => 24]));
        $this->assignment(502, ['reemplazos_personal_id' => 102, 'horas_contrato' => 24]);
        $rows = [$this->data(['jornada' => 20, 'jornada_basica' => 20]), $this->data(['financiamiento' => 'PIE', 'jornada' => 24, 'jornada_basica' => 24])];
        $this->assertSame(0, $this->diagnosis($this->revision($rows))['bloqueantes']);
        $this->assignment(503, ['establecimiento_id' => 2, 'reemplazos_personal_id' => null, 'horas_contrato' => 10]);
        $diagnosis = $this->diagnosis($this->revision($rows));
        $this->assertSame(1, $diagnosis['bloqueantes']);
        $this->assertSame(99998, $diagnosis['items'][0]['rbd']);
        $this->assertSame(0.0, $diagnosis['items'][0]['cobertura']['horas']);
    }

    public function test_formatted_declaration_from_other_rbd_does_not_override_local_coverage(): void
    {
        DB::table('declaracion_sostenedores')->insert(['rut' => '11.111.111-1', 'rbd' => '99998', 'horas_contratadas' => 44]);
        $diagnosis = $this->diagnosis($this->revision([$this->data(['jornada' => 10])]));
        $this->assertSame(10.0, $diagnosis['items'][0]['cobertura']['horas']);
        $this->assertSame('Padrón propuesto', $diagnosis['items'][0]['cobertura']['fuente']);
    }

    public function test_mixed_and_multiple_assistant_lines_are_not_presumed_usable_coverage(): void
    {
        DB::table('reemplazos_personal')->where('id', 101)->update(['estatuto' => 'ASISTENTE', 'escalafon' => 'AUXILIAR']);
        DB::table('reemplazos_personal')->insert(['id' => 102, 'establecimiento_id' => 1] + $this->data(['mes' => 8, 'financiamiento' => 'PIE', 'estatuto' => 'ASISTENTE', 'escalafon' => 'AUXILIAR']));
        DB::table('dotacion_docente_asignaciones')->update(['estamento_cobertura' => 'asistente']);
        $rows = [$this->data(['estatuto' => 'ASISTENTE', 'escalafon' => 'AUXILIAR']), $this->data(['financiamiento' => 'PIE', 'estatuto' => 'ASISTENTE', 'escalafon' => 'AUXILIAR'])];
        $diagnosis = $this->diagnosis($this->revision($rows));
        $this->assertArrayHasKey('composicion', $diagnosis['items'][0]['motivos']);
        $this->assertSame(0.0, $diagnosis['items'][0]['cobertura']['horas']);
        DB::table('declaracion_sostenedores')->insert(['rut' => '111111111', 'rbd' => '99999', 'horas_contratadas' => 40, 'estamento' => 'ASISTENTE']);
        $this->assertSame(0, $this->diagnosis($this->revision($rows))['bloqueantes']);
    }

    public function test_contradictory_statute_is_blocked_until_authoritative_declaration_exists(): void
    {
        $revision = $this->revision([$this->data(['estatuto' => 'ASISTENTE', 'escalafon' => 'DOCENTE AULA'])]);
        $this->assertArrayHasKey('composicion', $this->diagnosis($revision)['items'][0]['motivos']);
        DB::table('declaracion_sostenedores')->insert(['rut' => '111111111', 'rbd' => '99999', 'horas_contratadas' => 40, 'estamento' => 'DOCENTE']);
        $this->assertSame(0, $this->diagnosis($revision)['bloqueantes']);
    }

    public function test_manual_id_resolution_recalculates_without_moving_assignments(): void
    {
        DB::table('reemplazos_personal')->insert(['id' => 102, 'establecimiento_id' => 1] + $this->data(['mes' => 8, 'financiamiento' => 'PIE']));
        $revision = $this->revision([$this->data(['jornada' => 30])]);
        $fila = $revision->filas->firstWhere('fila_excel', 2);
        $this->assertSame('revision_manual', $fila->accion);
        $this->assertArrayHasKey('correspondencia', $this->diagnosis($revision)['items'][0]['motivos']);
        $before = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        app(PadronResolucionService::class)->resolver($revision, $fila->id, 101, 'Coincidencia sintética verificada por ID.', 1);
        $this->assertSame(0, $this->diagnosis($revision)['bloqueantes']);
        $decision = app(PadronResolucionService::class)->decisiones($revision)[$fila->id];
        app(PadronResolucionService::class)->resolver($revision, $fila->id, 102, 'Corrección sintética de correspondencia.', 1, $decision->id);
        $this->assertArrayHasKey('id_sin_destino', $this->diagnosis($revision)['items'][0]['motivos']);
        $this->assertSame($before, DB::table('dotacion_docente_asignaciones')->get()->toJson());
    }

    public function test_authorizing_over_44_does_not_override_assignment_conflicts(): void
    {
        $revision = $this->revision([$this->data(['jornada' => 45, 'rbd' => 99998])]);
        app(PadronRevisionService::class)->authorize($revision, '111111111', 'Exceso sintético autorizado con motivo.', 1);
        $this->assertNotEmpty(app(PadronAplicacionService::class)->plan($revision)['errores']);
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
        $this->expectException(ValidationException::class);
        app(PadronAplicacionService::class)->aplicar($revision, 1);
    }

    public function test_correcting_dotacion_refreshes_conflicts_in_the_same_review(): void
    {
        $revision = $this->revision([$this->data(['jornada' => 10])]);
        $this->assertSame(1, $this->diagnosis($revision)['bloqueantes']);
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['horas_contrato' => 10]);
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
        $this->assertSame(0, $this->diagnosis($revision)['bloqueantes']);
        $new = $this->revision([$this->data(['jornada' => 10])]);
        $this->assertFalse(app(PadronRevisionService::class)->stale($new));
        $this->assertSame(0, $this->diagnosis($new)['bloqueantes']);
        $this->assertSame($revision->base_hash, $new->base_hash);
    }

    public function test_changes_in_coverage_invalidate_final_confirmation_not_manual_review(): void
    {
        foreach (['asignacion', 'declaracion', 'exclusion'] as $change) {
            $revision = $this->revision([$this->data()]);
            $aplicador = app(PadronAplicacionService::class);
            $token = $aplicador->plan($revision)['confirmacion_hash'];
            if ($change === 'asignacion') {
                DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['estamento_cobertura' => 'asistente']);
            } elseif ($change === 'declaracion') {
                DB::table('declaracion_sostenedores')->insert(['rut' => '111111111', 'rbd' => '99999', 'horas_contratadas' => 20, 'estamento' => 'ASISTENTE']);
            } else {
                DB::table('dotacion_docente_exclusiones')->insert(['establecimiento_id' => 1, 'anio' => 2026, 'docente_rut' => '111111111', 'horas' => 10]);
            }
            $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
            $this->assertFalse($aplicador->confirmacionVigente($revision, $token));
        }
    }

    public function test_preexisting_excess_without_contract_id_is_one_warning_with_all_assignment_details(): void
    {
        DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => 33, 'jornada_basica' => 33]);
        DB::table('declaracion_sostenedores')->insert(['rut' => '111111111', 'rbd' => '99999', 'horas_contratadas' => 33]);
        DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['reemplazos_personal_id' => null, 'horas_contrato' => 2]);
        $this->assignment(502, ['reemplazos_personal_id' => null, 'docente_rut' => '11111111-1', 'horas_contrato' => 32]);
        $revision = $this->revision([$this->data(['jornada' => 33, 'jornada_basica' => 33])]);
        $diagnosis = $this->diagnosis($revision);
        $this->assertSame([], $diagnosis['errores']);
        $this->assertSame(0, $diagnosis['grupos_bloqueantes']);
        $this->assertSame(1, $diagnosis['grupos_avisos']);
        $this->assertSame(2, $diagnosis['avisos']);
        $this->assertCount(1, $diagnosis['grupos']);
        $group = $diagnosis['grupos'][0];
        $this->assertSame(34.0, $group['total_asignadas']);
        $this->assertSame(['estado' => 'preexistente', 'comparable' => true, 'exceso_actual' => 1.0, 'exceso_propuesto' => 1.0], $group['comparacion']);
        $this->assertCount(1, $group['avisos']);
        $this->assertCount(2, $group['asignaciones']);
        $this->assertSame([], app(PadronAplicacionService::class)->plan($revision)['errores']);
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
        view()->share('errors', new ViewErrorBag);
        $view = app(PersonalImportController::class)->create(Request::create('/', 'GET', ['revision' => $revision->id]));
        $this->assertCount(1, $view->getData()['conflictosPaginados']);
        $html = $view->render();
        $this->assertStringContainsString('Ver 2 asignaciones', $html);
        $this->assertStringContainsString('Aviso: no bloquea por este caso', $html);
        $this->assertSame(1, substr_count($html, 'Total asignado: 34 h'));
        $this->assertSame(1, substr_count($html, 'Exceso preexistente: 1 h antes'));
    }

    public function test_current_vs_proposed_coverage_distinguishes_new_aggravated_and_improved_excess(): void
    {
        DB::table('dotacion_docente_asignaciones')->update(['horas_contrato' => 34]);
        foreach ([[44, 33, 'nuevo', 0, 1, 1], [33, 32, 'agravado', 1, 2, 1], [32, 33, 'preexistente', 2, 1, 0]] as [$actual, $proposed, $state, $before, $after, $blocks]) {
            DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => $actual, 'jornada_basica' => $actual]);
            $diagnosis = $this->diagnosis($this->revision([$this->data(['jornada' => $proposed, 'jornada_basica' => $proposed])]));
            $this->assertSame($blocks, $diagnosis['grupos_bloqueantes']);
            $this->assertSame(['estado' => $state, 'comparable' => true, 'exceso_actual' => (float) $before, 'exceso_propuesto' => (float) $after], $diagnosis['grupos'][0]['comparacion']);
        }
    }

    public function test_unknown_inactive_or_replacement_baseline_cannot_waive_coverage_block(): void
    {
        DB::table('dotacion_docente_asignaciones')->update(['reemplazos_personal_id' => null, 'horas_contrato' => 34]);
        foreach ([['vigente' => false], ['anio' => 2025], ['tipocontrato' => 'REEMPLAZO'], ['tipocontrato' => 'SUPLENCIA']] as $change) {
            DB::table('reemplazos_personal')->where('id', 101)->update(array_replace(['vigente' => true, 'anio' => 2026, 'tipocontrato' => 'CONTRATA'], $change));
            $diagnosis = $this->diagnosis($this->revision([$this->data(['jornada' => 33, 'jornada_basica' => 33])]));
            $this->assertSame('no_comparable', $diagnosis['grupos'][0]['comparacion']['estado']);
            $this->assertNull($diagnosis['grupos'][0]['comparacion']['exceso_actual']);
            $this->assertSame(1, $diagnosis['grupos_bloqueantes']);
            $this->assertArrayHasKey('cobertura_insuficiente', $diagnosis['items'][0]['motivos']);
        }
    }

    public function test_preexisting_excess_does_not_waive_a_lost_contract_id(): void
    {
        DB::table('dotacion_docente_asignaciones')->update(['reemplazos_personal_id' => 999, 'horas_contrato' => 45]);
        $diagnosis = $this->diagnosis($this->revision([$this->data()]));
        $this->assertSame('preexistente', $diagnosis['grupos'][0]['comparacion']['estado']);
        $this->assertSame(1, $diagnosis['grupos_bloqueantes']);
        $this->assertSame(0, $diagnosis['grupos_avisos']);
        $this->assertArrayHasKey('id_sin_destino', $diagnosis['items'][0]['motivos']);
        $this->assertArrayNotHasKey('cobertura_insuficiente', $diagnosis['items'][0]['motivos']);
        $this->assertNotEmpty($diagnosis['errores']);
    }

    public function test_group_details_include_unflagged_assignments_and_do_not_merge_establishments(): void
    {
        $this->assignment(502, ['reemplazos_personal_id' => 999, 'horas_contrato' => 1]);
        $this->assignment(503, ['establecimiento_id' => 2, 'reemplazos_personal_id' => null, 'horas_contrato' => 10]);
        $diagnosis = $this->diagnosis($this->revision([$this->data()]));
        $this->assertSame(2, $diagnosis['grupos_bloqueantes']);
        $this->assertSame(2, $diagnosis['bloqueantes']);
        $groups = collect($diagnosis['grupos'])->keyBy('establecimiento_id');
        $this->assertCount(2, $groups[1]['asignaciones']);
        $this->assertFalse($groups[1]['asignaciones'][0]['bloqueante']);
        $this->assertSame(21.0, $groups[1]['total_asignadas']);
        $this->assertSame(44.0, $groups[1]['cobertura']['horas']);
        $this->assertSame(0.0, $groups[2]['cobertura']['horas']);
        $this->assertSame(0.0, $groups[2]['cobertura_actual']['horas']);
    }

    public function test_repeated_coverage_block_is_reported_once_per_group_not_per_assignment(): void
    {
        foreach (range(502, 521) as $id) {
            $this->assignment($id, ['horas_contrato' => 1]);
        }
        $revision = $this->revision([$this->data(['jornada' => 10, 'jornada_basica' => 10])]);
        $diagnosis = $this->diagnosis($revision);
        $this->assertSame(21, $diagnosis['bloqueantes']);
        $this->assertSame(1, $diagnosis['grupos_bloqueantes']);
        $this->assertCount(1, $diagnosis['errores']);
        $this->assertCount(21, $diagnosis['grupos'][0]['asignaciones']);
        view()->share('errors', new ViewErrorBag);
        $view = app(PersonalImportController::class)->create(Request::create('/', 'GET', ['revision' => $revision->id]));
        $this->assertSame(1, $view->getData()['conflictosPaginados']->total());
        $this->assertStringContainsString('Ver 21 asignaciones', $view->render());
    }

    public function test_current_coverage_preserves_decimal_hours(): void
    {
        DB::table('reemplazos_personal')->update(['jornada' => 33.5, 'jornada_basica' => 33.5]);
        DB::table('dotacion_docente_asignaciones')->update(['horas_contrato' => 34]);
        $diagnosis = $this->diagnosis($this->revision([$this->data(['jornada' => 33, 'jornada_basica' => 33])]));
        $this->assertSame(33.5, $diagnosis['grupos'][0]['cobertura_actual']['horas']);
        $this->assertSame('agravado', $diagnosis['grupos'][0]['comparacion']['estado']);
        $this->assertSame(0.5, $diagnosis['grupos'][0]['comparacion']['exceso_actual']);
    }

    public function test_baseline_uses_annual_history_without_reading_the_mutated_current_contract(): void
    {
        (require base_path('database/migrations/2026_09_08_160000_create_padron_periodo_versiones.php'))->up();
        DB::table('reemplazos_personal')->update(['jornada' => 33, 'jornada_basica' => 33]);
        DB::table('dotacion_docente_asignaciones')->update(['reemplazos_personal_id' => null, 'horas_contrato' => 34]);
        $archive = $this->revision([$this->data()]);
        DB::transaction(function () use ($archive): void {
            $periodos = app(\App\Services\Padron\PadronPeriodoService::class);
            $periodos->antesDeAplicar($archive, 1);
            // Solo fixture SQLite: el contrato actual cambia de año y jornada.
            DB::table('reemplazos_personal')->update(['anio' => 2027, 'mes' => 1, 'jornada' => 44]);
        });
        $diagnosis = $this->diagnosis($this->revision([$this->data(['jornada' => 33, 'jornada_basica' => 33])]));
        $this->assertSame(33.0, $diagnosis['grupos'][0]['cobertura_actual']['horas']);
        $this->assertSame('preexistente', $diagnosis['grupos'][0]['comparacion']['estado']);
        $this->assertSame(0, $diagnosis['grupos_bloqueantes']);
    }

    public function test_older_contract_is_not_resurrected_below_a_complete_load_floor(): void
    {
        (require base_path('database/migrations/2026_09_08_160000_create_padron_periodo_versiones.php'))->up();
        DB::table('dotacion_docente_asignaciones')->update(['reemplazos_personal_id' => null, 'horas_contrato' => 45]);
        $applied = $this->revision([$this->data()]);
        // Estado de fixture explícito: otras pruebas usan el esquema anterior
        // y Eloquent conserva en caché las columnas asignables del modelo.
        $applied->forceFill(['aplicada_at' => now(), 'aplicada_por' => 1])->save();
        $this->assertNotNull($applied->fresh()->aplicada_at, 'La revisión de prueba debe quedar aplicada.');
        $diagnosis = $this->diagnosis($this->revision([$this->data()]));
        $this->assertSame(0.0, $diagnosis['grupos'][0]['cobertura_actual']['horas']);
        $this->assertSame('no_comparable', $diagnosis['grupos'][0]['comparacion']['estado']);
        $this->assertSame(1, $diagnosis['grupos_bloqueantes']);
    }

    public function test_conflict_view_shows_all_counts_with_independent_pagination_without_writes(): void
    {
        foreach (range(502, 521) as $id) {
            $this->assignment($id, ['reemplazos_personal_id' => null, 'docente_rut' => (string) (220000000 + $id)]);
        }
        $revision = $this->revision([$this->data(['jornada' => 10])]);
        $before = DB::table('reemplazos_personal')->get()->toJson();
        $assignments = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        view()->share('errors', new ViewErrorBag);
        $view = app(PersonalImportController::class)->create(Request::create('/', 'GET', [
            'revision' => $revision->id, 'q' => 'Sin coincidencias', 'conflictos_page' => 2,
        ]));
        $this->assertSame(21, $view->getData()['conflictos']['bloqueantes']);
        $this->assertSame(21, $view->getData()['conflictos']['grupos_bloqueantes']);
        $this->assertCount(1, $view->getData()['conflictosPaginados']);
        $html = $view->render();
        $this->assertStringContainsString('Conflictos con asignaciones de Dotación', $html);
        $this->assertStringContainsString('Revisar Dotación', $html);
        $this->assertStringContainsString('No habilitada en esta etapa.', $html);
        $this->assertStringNotContainsString('name="accion" value="aplicar"', $html);
        $this->assertSame($before, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertSame($assignments, DB::table('dotacion_docente_asignaciones')->get()->toJson());
        $this->assertDatabaseCount('padron_personal_cambios', 0);
    }
}
