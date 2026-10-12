<?php

namespace Tests\Feature;

use App\Models\EstablecimientoCurso;
use App\Models\EstablecimientoCursoPie;
use App\Services\Pie\PieComparisonService;
use App\Support\PieHorasCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class PieComparisonTest extends IsolatedSecurityTestCase
{
    private const URL = '/admin/establecimiento-curso-pie-comparacion';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite(); Http::preventStrayRequests(); Mail::fake(); Queue::fake();
        config(['whatsapp.enabled' => false]);
        DB::table('roles')->insert([
            ['id' => 4, 'name' => 'coordinador_uatp', 'guard_name' => 'web'], ['id' => 5, 'name' => 'supervisor_plani', 'guard_name' => 'web'],
            ['id' => 6, 'name' => 'coordinador_gdp', 'guard_name' => 'web'], ['id' => 7, 'name' => 'funcionario_directivo_estab', 'guard_name' => 'web'],
            ['id' => 8, 'name' => 'funcionario_slep', 'guard_name' => 'web'],
        ]);
        Schema::create('modules', function (Blueprint $t) { $t->id(); $t->string('key'); $t->string('name'); $t->string('section'); $t->string('icon')->nullable(); $t->integer('sort'); $t->timestamps(); });
        Schema::create('module_role', function (Blueprint $t) { $t->unsignedBigInteger('module_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('establecimientos', function (Blueprint $t) { $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna')->nullable(); $t->timestamps(); });
        Schema::create('cursos', function (Blueprint $t) { $t->id(); $t->string('nombre'); $t->string('codigo'); $t->string('nivel_educativo')->nullable(); $t->string('modalidad')->nullable(); $t->integer('orden')->default(1); $t->boolean('activo')->default(true); $t->timestamps(); });
        Schema::create('planes_estudio', function (Blueprint $t) { $t->id(); $t->string('nombre_plan'); $t->string('nivel_educativo')->nullable(); $t->string('modalidad')->nullable(); $t->timestamps(); });
        Schema::create('establecimiento_cursos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->unsignedBigInteger('curso_id'); $t->unsignedBigInteger('plan_estudio_id')->nullable();
            $t->integer('rbd')->nullable(); $t->integer('anio'); $t->string('letra')->nullable(); $t->string('nombre_seccion')->nullable();
            $t->integer('matricula')->default(40); $t->string('regimen_jec')->nullable(); $t->boolean('activo')->default(true); $t->timestamps();
        });
        (require database_path('migrations/2026_05_25_160000_create_establecimiento_curso_pie_table.php'))->up();
        (require database_path('migrations/2026_05_25_170000_create_pie_horas_apoyo_minimo_table.php'))->up();
        (require database_path('migrations/2026_05_25_171000_add_calculo_horas_to_establecimiento_curso_pie_table.php'))->up();
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99901, 'nombre_establecimiento' => 'Establecimiento de prueba A', 'comuna' => 'Comuna de prueba'],
            ['id' => 2, 'rbd' => 99902, 'nombre_establecimiento' => 'Establecimiento de prueba B', 'comuna' => 'Comuna de prueba'],
        ]);
        DB::table('cursos')->insert([
            ['id' => 1, 'nombre' => '7° Básico', 'codigo' => '7B', 'orden' => 7],
            ['id' => 2, 'nombre' => 'Primer nivel EPJA', 'codigo' => 'EPJA1', 'orden' => 9],
        ]);
        foreach ([[101, 1, 2026, 'A'], [102, 1, 2027, 'A'], [103, 1, 2027, 'B'], [104, 1, 2028, 'A'], [201, 2, 2026, 'A'], [301, 1, 2026, 'EPJA']] as [$id, $est, $year, $letter]) {
            $course = EstablecimientoCurso::create(['establecimiento_id' => $est, 'curso_id' => $id === 301 ? 2 : 1, 'anio' => $year,
                'letra' => $letter, 'nombre_seccion' => 'Sección de prueba '.$letter, 'matricula' => 40, 'regimen_jec' => 'CON JEC', 'activo' => true]);
            DB::table('establecimiento_cursos')->where('id', $course->id)->update(['id' => $id]);
        }
        foreach ([101, 102, 201, 301] as $id) {
            $course = EstablecimientoCurso::findOrFail($id);
            EstablecimientoCursoPie::create(['establecimiento_id' => $course->establecimiento_id, 'establecimiento_curso_id' => $id,
                'curso_id' => $course->curso_id, 'anio' => $course->anio, 'necesidades_transitorias' => 5, 'necesidades_permanentes' => 0,
                'total_pie' => 5, 'estado' => 'validado', 'observacion' => 'Observación sintética a conservar'] + PieHorasCalculator::calculate($course, 5, 0));
        }
    }

    private function screen(int $establishment = 1, array $years = [2026, 2027]): array { return app(PieComparisonService::class)->screen($establishment, $years); }
    private function payload(array $changes = [101 => [6, 1], 102 => [0, 2]]): array
    {
        $screen = $this->screen(); $rows = [];
        foreach ($changes as $id => [$neet, $neep]) $rows[] = ['course_id' => $id, 'neet' => $neet, 'neep' => $neep, 'version' => $screen['rows'][$id]['version']];
        return ['establecimiento_id' => 1, 'anio_a' => 2026, 'anio_b' => 2027, 'rows' => $rows, 'expected_rows' => count($rows), 'rules_token' => $screen['rules_token']];
    }
    private function stored(int $courseId): array { return DB::table('establecimiento_curso_pie')->where('establecimiento_curso_id', $courseId)->first() ? (array) DB::table('establecimiento_curso_pie')->where('establecimiento_curso_id', $courseId)->first() : []; }

    public function test_guests_cannot_access_or_save(): void
    {
        $this->get(self::URL)->assertRedirect('/login'); $this->put(self::URL, [])->assertRedirect('/login');
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_other_roles_cannot_consult_or_save(int $role): void
    {
        $before = $this->stored(101); $this->actingAs($this->testUser(role: $role));
        $this->getJson(self::URL)->assertForbidden(); $this->putJson(self::URL, $this->payload())->assertForbidden();
        $this->assertSame($before, $this->stored(101));
    }
    public static function unauthorizedRoles(): array { return [[1], [2], [5], [6], [7], [8]]; }

    public function test_both_authorized_roles_can_compare_without_get_writes(): void
    {
        $before = DB::table('establecimiento_curso_pie')->get()->toArray();
        foreach ([3, 4] as $role) {
            $this->actingAs($this->testUser($role, $role))->get(self::URL.'?establecimiento_id=1&anio_a=2026&anio_b=2027')
                ->assertOk()->assertSee('Comparación PIE entre años')->assertSee('Guardar todas las modificaciones')
                ->assertSee('Totales 2026')->assertSee('Totales 2027')->assertSee('rows[0][neet]', false)->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->assertEquals($before, DB::table('establecimiento_curso_pie')->get()->toArray()); Mail::assertNothingSent(); Queue::assertNothingPushed(); Http::assertNothingSent();
    }

    public function test_active_role_module_and_verification_are_required(): void
    {
        $user = $this->testUser(role: 4); $this->actingAs($user);
        DB::table('module_role')->where('role_id', 4)->delete();
        $this->getJson(self::URL)->assertForbidden(); $this->putJson(self::URL, $this->payload())->assertForbidden();
        $admin = $this->testUser(2, 3); $admin->assignRole('supervisor_plani');
        $this->actingAs($admin)->withSession(['active_role' => 'supervisor_plani'])->getJson(self::URL)->assertForbidden();
        $this->withSession(['active_role' => 'admin']); $admin->forceFill(['email_verified_at' => null])->save();
        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_selection_empty_states_and_different_year_validation(): void
    {
        $this->actingAs($this->testUser(role: 3))->get(self::URL)->assertOk()->assertSee('Selecciona un establecimiento')->assertDontSee('Guardar todas las modificaciones');
        $this->get(self::URL.'?establecimiento_id=1&anio_a=2029&anio_b=2030')->assertOk()->assertSee('No hay cursos activos');
        $this->getJson(self::URL.'?establecimiento_id=1&anio_a=2026&anio_b=2026')->assertUnprocessable()->assertJsonValidationErrors('anio_b');
    }

    public function test_level_and_letter_are_matched_without_promoting_courses_or_hiding_unmatched_sections(): void
    {
        $screen = $this->screen(); $this->assertCount(3, $screen['groups']);
        $group = collect($screen['groups'])->first(fn ($g) => $g['label'] === '7° Básico' && $g['letter'] === 'A');
        $this->assertSame([101], $group['years'][2026]); $this->assertSame([102], $group['years'][2027]);
        $groupB = collect($screen['groups'])->first(fn ($g) => $g['letter'] === 'B');
        $this->assertSame([], $groupB['years'][2026]); $this->assertSame([103], $groupB['years'][2027]);
        $this->assertArrayNotHasKey(201, $screen['rows']); $this->assertArrayNotHasKey(104, $screen['rows']);
    }

    public function test_both_years_save_atomically_recalculate_and_preserve_historical_metadata(): void
    {
        $user = $this->testUser(role: 4); $other = $this->stored(201); $payload = $this->payload();
        $response = $this->actingAs($user)->putJson(self::URL, $payload)->assertOk()->assertJsonPath('saved', 2);
        $this->assertStringContainsString('anio_a=2026', $response->json('redirect'));
        $first = $this->stored(101); $second = $this->stored(102);
        $this->assertSame(7, $first['total_pie']); $this->assertSame(5, $first['neet_calculo']);
        $this->assertSame(2, $second['total_pie']); $this->assertSame(360, $second['total_crono_minutos']);
        $this->assertSame(360, $second['prof_educ_dif_minutos']); $this->assertSame(0, $second['pae_minutos']);
        foreach ([$first, $second] as $record) { $this->assertSame('validado', $record['estado']); $this->assertSame('Observación sintética a conservar', $record['observacion']); $this->assertNull($record['created_by']); $this->assertSame($user->id, $record['updated_by']); }
        $this->assertSame($other, $this->stored(201)); Mail::assertNothingSent(); Queue::assertNothingPushed(); Http::assertNothingSent();
    }

    public function test_courses_without_pie_create_only_when_counts_change(): void
    {
        $user = $this->testUser(role: 3); $this->actingAs($user);
        $this->putJson(self::URL, $this->payload([103 => [0, 0]]))->assertOk()->assertJsonPath('saved', 0);
        $this->assertSame([], $this->stored(103));
        $this->putJson(self::URL, $this->payload([103 => [5, 0]]))->assertOk()->assertJsonPath('saved', 1);
        $record = $this->stored(103); $this->assertSame('borrador', $record['estado']);
        $this->assertSame($user->id, $record['created_by']); $this->assertSame(600, $record['total_crono_minutos']);
    }

    #[DataProvider('invalidCounts')]
    public function test_invalid_quantity_in_one_row_preserves_the_entire_batch($neet, $neep): void
    {
        $payload = $this->payload(); $payload['rows'][1]['neet'] = $neet; $payload['rows'][1]['neep'] = $neep;
        $before = $this->stored(101);
        $this->actingAs($this->testUser(role: 3))->putJson(self::URL, $payload)->assertUnprocessable();
        $this->assertSame($before, $this->stored(101));
    }
    public static function invalidCounts(): array { return [[-1, 0], [1.5, 0], ['', 0], [10000, 0], [39, 2]]; }

    public function test_cross_establishment_and_unselected_year_are_rejected_without_changes(): void
    {
        $this->actingAs($this->testUser(role: 3)); $before = $this->stored(101);
        foreach ([[201, 2, [2026, 2027]], [104, 1, [2026, 2028]]] as [$id, $est, $years]) {
            $payload = $this->payload(); $row = $this->screen($est, $years)['rows'][$id];
            $payload['rows'][1] = ['course_id' => $id, 'neet' => 4, 'neep' => 1, 'version' => $row['version']];
            $this->putJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors('rows.1.course_id');
            $this->assertSame($before, $this->stored(101));
        }
    }

    public function test_stale_record_course_and_catalog_are_rejected_before_any_write(): void
    {
        $this->actingAs($this->testUser(role: 3)); $before = $this->stored(101); $payload = $this->payload();
        DB::table('establecimiento_curso_pie')->where('establecimiento_curso_id', 102)->update(['observacion' => 'Otra edición sintética']);
        $this->putJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors('rows.1.version');
        $this->assertSame($before, $this->stored(101));
        $payload = $this->payload(); DB::table('establecimiento_cursos')->where('id', 102)->update(['matricula' => 35]);
        $this->putJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors('rows.1.version');
        $this->assertSame($before, $this->stored(101));
        $payload = $this->payload(); DB::table('pie_horas_apoyo_minimo')->where('id', 1)->update(['total_crono_minutos' => 123]);
        $this->putJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors('rules_token');
        $this->assertSame($before, $this->stored(101));
    }

    public function test_truncated_duplicate_or_extra_fields_cannot_partially_save(): void
    {
        $this->actingAs($this->testUser(role: 3)); $before = $this->stored(101);
        $payload = $this->payload(); unset($payload['expected_rows']); $this->putJson(self::URL, $payload)->assertUnprocessable();
        $payload = $this->payload(); array_pop($payload['rows']); $this->putJson(self::URL, $payload)->assertUnprocessable();
        $payload = $this->payload(); $payload['rows'][1] = $payload['rows'][0]; $this->putJson(self::URL, $payload)->assertUnprocessable();
        $payload = $this->payload(); $payload['rows'][0]['total_crono_minutos'] = 99999; $this->putJson(self::URL, $payload)->assertUnprocessable();
        $this->assertSame($before, $this->stored(101));
    }

    public function test_inconsistent_pie_identity_is_read_only_and_cannot_be_overwritten(): void
    {
        DB::table('establecimiento_curso_pie')->where('establecimiento_curso_id', 102)->update(['curso_id' => 2]);
        $this->assertFalse($this->screen()['rows'][102]['editable']);
        $this->actingAs($this->testUser(role: 3))->putJson(self::URL, $this->payload())->assertUnprocessable()->assertJsonValidationErrors('rows.1.course_id');
        $this->assertSame(5, $this->stored(101)['necesidades_transitorias']);
    }

    public function test_null_historical_course_identity_is_preserved_when_parent_link_is_valid(): void
    {
        DB::table('establecimiento_curso_pie')->where('establecimiento_curso_id', 101)->update(['curso_id' => null]);
        $this->actingAs($this->testUser(role: 3))->putJson(self::URL, $this->payload([101 => [6, 0]]))->assertOk();
        $this->assertNull($this->stored(101)['curso_id']);
    }

    public function test_unchanged_records_keep_their_saved_calculation_and_update_metadata(): void
    {
        DB::table('establecimiento_curso_pie')->where('establecimiento_curso_id', 101)->update(['total_crono_minutos' => 500]);
        $before = $this->stored(101); $this->assertSame(500, $this->screen()['rows'][101]['calculation']['total_crono_minutos']);
        $this->actingAs($this->testUser(role: 3))->putJson(self::URL, $this->payload([101 => [5, 0]]))->assertOk()->assertJsonPath('saved', 0);
        $this->assertSame($before, $this->stored(101));
    }

    public function test_preview_matrix_matches_existing_database_calculator_with_epja_zero_and_missing_rules(): void
    {
        $screen = $this->screen();
        foreach ([101, 301] as $id) foreach ([0, 1, 5] as $neet) foreach ([0, 1, 31, 32] as $neep) {
            $expected = PieHorasCalculator::calculate(EstablecimientoCurso::findOrFail($id), $neet, $neep);
            $actual = $screen['matrix'][$screen['rows'][$id]['rule_key']][$neet][$neep];
            foreach (PieComparisonService::HOUR_FIELDS as $field) $this->assertSame($expected[$field], $actual[$field]);
        }
        $this->assertSame(420, $screen['matrix']['sin_jec:epja'][5][0]['total_crono_minutos']);
        $this->assertNull($screen['matrix']['con_jec'][5][32]['total_crono_minutos']);
    }

    public function test_database_failure_in_second_update_rolls_back_first_update(): void
    {
        $payload = $this->payload(); $user = $this->testUser(role: 3); $first = $this->stored(101); $second = $this->stored(102);
        $name = 'eloquent.updating: '.EstablecimientoCursoPie::class;
        Event::listen($name, function ($record) { if ($record->establecimiento_curso_id === 102) throw new \RuntimeException('synthetic-write-failure'); });
        try {
            try { app(PieComparisonService::class)->save(1, [2026, 2027], $payload['rows'], $payload['rules_token'], $user->id); $this->fail('El lote debía revertirse.'); }
            catch (\RuntimeException $e) { $this->assertSame('synthetic-write-failure', $e->getMessage()); }
        } finally { Event::forget($name); }
        $this->assertSame($first, $this->stored(101)); $this->assertSame($second, $this->stored(102));
    }

    public function test_inactive_courses_and_repeated_stale_creation_cannot_overwrite_records(): void
    {
        $this->actingAs($this->testUser(role: 3)); $payload = $this->payload([103 => [5, 0]]);
        $this->putJson(self::URL, $payload)->assertOk(); $before = $this->stored(103);
        $this->putJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors('rows.0.version');
        $this->assertSame($before, $this->stored(103));
        $payload = $this->payload([101 => [6, 0]]); DB::table('establecimiento_cursos')->where('id', 101)->update(['activo' => false]);
        $this->putJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors('rows.0.course_id');
    }

    public function test_native_form_error_retains_quantities_and_original_version_until_reload(): void
    {
        $this->actingAs($this->testUser(role: 3)); $payload = $this->payload([101 => [6, 1]]); $oldVersion = $payload['rows'][0]['version'];
        DB::table('establecimiento_curso_pie')->where('establecimiento_curso_id', 101)->update(['necesidades_transitorias' => 7]);
        $url = self::URL.'?establecimiento_id=1&anio_a=2026&anio_b=2027';
        $this->from($url)->put(self::URL, $payload)->assertRedirect($url)->assertSessionHasErrors('rows.0.version');
        $this->get($url)->assertOk()->assertSee('data-version value="'.$oldVersion.'"', false)->assertSee('value="6"', false);
        $this->assertSame(7, $this->stored(101)['necesidades_transitorias']);
    }

    public function test_unknown_hours_above_table_limit_remain_null_while_epja_uses_sin_jec(): void
    {
        $this->actingAs($this->testUser(role: 3))->putJson(self::URL, $this->payload([101 => [1, 32], 301 => [6, 0]]))->assertOk();
        $this->assertSame(33, $this->stored(101)['total_pie']); $this->assertNull($this->stored(101)['total_crono_minutos']);
        $this->assertSame('sin_jec', $this->stored(301)['regimen_calculo']); $this->assertSame(420, $this->stored(301)['total_crono_minutos']);
    }

    public function test_comparison_link_is_visible_only_for_the_two_requested_active_roles(): void
    {
        $this->actingAs($this->testUser(role: 3));
        $props = ['items' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25),
            'summary' => array_fill_keys(['registros', 'neet', 'neep', 'total', 'total_crono_minutos', 'prof_educ_dif_minutos', 'pae_minutos'], 0),
            'establecimientos' => collect(), 'cursos' => collect(), 'estados' => EstablecimientoCursoPie::ESTADOS,
            'canEditPie' => true, 'anio' => '2026', 'establecimientoId' => '1', 'cursoId' => '', 'estado' => '', 'registro' => '', 'q' => '',
            'formatMinutes' => fn ($minutes) => PieHorasCalculator::formatMinutes($minutes)];
        foreach (['admin', 'coordinador_uatp', 'supervisor_plani', 'funcionario_directivo_estab'] as $role) {
            $html = view('admin.establecimiento-curso-pie.index', $props + ['activeRole' => $role])->render();
            if (in_array($role, ['admin', 'coordinador_uatp'], true)) $this->assertStringContainsString('Comparar años', $html);
            else $this->assertStringNotContainsString('Comparar años', $html);
        }
    }
}
