<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\PlanEstudio;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionPlanEstudioResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class PlanEstudioValidationTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_10_15_160000_allow_named_plan_estudio_variants.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('modules', function (Blueprint $table): void {
            $table->id(); $table->string('key');
        });
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id(); $table->integer('rbd'); $table->string('nombre_establecimiento'); $table->string('comuna')->nullable();
        });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']);
        (require database_path('migrations/2026_05_18_190000_create_establecimiento_cursos_table.php'))->up();
        Schema::create('establecimiento_planes_estudio', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('establecimiento_curso_id')->unique();
            $table->unsignedBigInteger('plan_estudio_id');
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('plan_estudio_bloque_id');
        });
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
    }

    public function test_crea_plan_nuevo_con_bloques_y_asignaturas(): void
    {
        $payload = $this->payload();
        $this->post(route('admin.planes-estudio.store'), $payload)
            ->assertRedirect(route('admin.planes-estudio.index'))->assertSessionHasNoErrors();
        $plan = PlanEstudio::sole();
        $this->assertSame($payload['curso_id'], $plan->curso_id);
        $this->assertSame('Educación Básica', $plan->nivel_educativo);
        $this->assertSame('Básica', $plan->modalidad);
        $this->assertSame('38.00', $plan->horas_semanales_total);
        $this->assertSame('Bloque sintético', $plan->bloques->sole()->nombre);
        $this->assertSame('Asignatura sintética', $plan->asignaturas->sole()->asignatura);
    }

    public static function estados(): array
    {
        return ['activo' => [true], 'inactivo' => [false]];
    }

    #[DataProvider('estados')]
    public function test_duplicado_vuelve_al_formulario_con_motivo_y_datos_sin_modificar_plan_existente(bool $activo): void
    {
        $plan = $this->createPlan(['activo' => $activo]);
        $before = $plan->getAttributes();
        $blockBefore = $plan->bloques->sole()->getAttributes();
        $subjectBefore = $plan->asignaturas->sole()->getAttributes();
        $payload = $this->payload(['observacion' => 'Observación sintética recuperada', 'activo' => 0]);
        $response = $this->from(route('admin.planes-estudio.create'))
            ->post(route('admin.planes-estudio.store'), $payload);

        $response->assertRedirect(route('admin.planes-estudio.create'))
            ->assertSessionHasErrors('nombre_plan');
        foreach (['curso_id', 'anio', 'regimen_jec', 'nombre_plan', 'activo', 'bloques.0.nombre', 'asignaturas.0.asignatura'] as $field) {
            $response->assertSessionHasInput($field, data_get($payload, $field));
        }
        $this->assertStringContainsString('Ya existe un plan con ese nombre', session('errors')->first('nombre_plan'));
        $this->assertSame(1, PlanEstudio::count());
        $this->assertSame($before, $plan->fresh()->getAttributes());
        $this->assertSame($blockBefore, $plan->fresh()->bloques->sole()->getAttributes());
        $this->assertSame($subjectBefore, $plan->fresh()->asignaturas->sole()->getAttributes());

        // La vista ya usa errores de sesión y old(): se verifica el formulario real.
        $html = view('admin.planes-estudio._form', [
            'plan' => new PlanEstudio, 'cursos' => Curso::orderBy('orden')->get(), 'errors' => session('errors'),
        ])->render();
        $this->assertStringContainsString('Ya existe un plan con ese nombre', $html);
        $this->assertStringContainsString('Observación sintética recuperada', $html);
        $this->assertStringContainsString('Bloque sintético', $html);
        $this->assertStringContainsString('Asignatura sintética', $html);
    }

    public function test_duplicado_json_entrega_error_de_validacion_estructurado(): void
    {
        $this->createPlan();
        $this->postJson(route('admin.planes-estudio.store'), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('nombre_plan')
            ->assertJsonPath('errors.nombre_plan.0', 'Ya existe un plan con ese nombre para el curso, año y régimen JEC seleccionados. Use un nombre distinto para identificar la variante o edite el plan existente.');
        $this->assertSame(1, PlanEstudio::count());
    }

    public function test_editar_el_mismo_plan_conserva_su_combinacion_y_bloques(): void
    {
        $plan = $this->createPlan();
        $payload = $this->payload(['nombre_plan' => 'Plan sintético actualizado']);
        $blockId = $plan->bloques->sole()->id;
        $payload['bloques'][0]['id'] = $blockId;
        $this->put(route('admin.planes-estudio.update', $plan), $payload)
            ->assertRedirect(route('admin.planes-estudio.index'))->assertSessionHasNoErrors();
        $this->assertSame(1, PlanEstudio::count());
        $this->assertSame('Plan sintético actualizado', $plan->fresh()->nombre_plan);
        $this->assertSame($blockId, $plan->fresh()->bloques->sole()->id);
    }

    public function test_edicion_en_conflicto_vuelve_a_editar_sin_perder_bloques_ni_asignaturas(): void
    {
        $this->createPlan();
        $other = $this->createPlan(['anio' => 2028]);
        $before = $other->getAttributes();
        $blockBefore = $other->bloques->sole()->getAttributes();
        $subjectBefore = $other->asignaturas->sole()->getAttributes();
        $payload = $this->payload(['observacion' => 'Cambio sintético en conflicto']);
        $this->from(route('admin.planes-estudio.edit', $other))
            ->put(route('admin.planes-estudio.update', $other), $payload)
            ->assertRedirect(route('admin.planes-estudio.edit', $other))
            ->assertSessionHasErrors('nombre_plan')->assertSessionHasInput('observacion', $payload['observacion']);
        $this->assertSame(2, PlanEstudio::count());
        $this->assertSame($before, $other->fresh()->getAttributes());
        $this->assertSame($blockBefore, $other->fresh()->bloques->sole()->getAttributes());
        $this->assertSame($subjectBefore, $other->fresh()->asignaturas->sole()->getAttributes());
    }

    public static function combinacionesDistintas(): array
    {
        return ['otro año' => ['anio', 2028], 'otro régimen' => ['regimen_jec', 'Sin JEC'], 'otro curso' => ['curso_id', null]];
    }

    #[DataProvider('combinacionesDistintas')]
    public function test_acepta_combinaciones_distintas(string $field, mixed $value): void
    {
        $this->createPlan();
        $value ??= Curso::where('codigo', '2B')->sole()->id;
        $this->post(route('admin.planes-estudio.store'), $this->payload([$field => $value]))
            ->assertRedirect(route('admin.planes-estudio.index'))->assertSessionHasNoErrors();
        $this->assertSame(2, PlanEstudio::count());
    }

    public function test_otros_errores_siguen_volviendo_al_formulario_con_los_datos(): void
    {
        $this->from(route('admin.planes-estudio.create'))
            ->post(route('admin.planes-estudio.store'), $this->payload(['horas_semanales_total' => 0]))
            ->assertRedirect(route('admin.planes-estudio.create'))
            ->assertSessionHasErrors('horas_semanales_total')
            ->assertSessionHasInput('bloques.0.nombre', 'Bloque sintético');
        $this->assertSame(0, PlanEstudio::count());
    }

    public static function cursosConVariantes(): array
    {
        return [['1B'], ['8B'], ['1M'], ['4M-HC'], ['4M-TP']];
    }

    #[DataProvider('cursosConVariantes')]
    public function test_conviven_plan_regular_y_43_horas_en_basica_y_media(string $code): void
    {
        $course = Curso::where('codigo', $code)->sole();
        $standard = $this->createPlan(['curso_id' => $course->id]);
        $before = $standard->getAttributes();
        $variant = $this->createPlan([
            'curso_id' => $course->id, 'nombre_plan' => 'Plan 43 horas',
            'horas_semanales_subtotal' => 37, 'horas_semanales_total' => 43,
        ]);
        $this->assertSame(2, PlanEstudio::count());
        $this->assertSame($before, $standard->fresh()->getAttributes());
        $this->assertSame('43.00', $variant->horas_semanales_total);
        $this->assertNotSame($standard->bloques->sole()->id, $variant->bloques->sole()->id);
        $this->assertNotSame($standard->asignaturas->sole()->id, $variant->asignaturas->sole()->id);
    }

    public function test_seleccion_explicita_conserva_43_horas_y_otra_seccion_sigue_con_plan_regular(): void
    {
        $standard = $this->createPlan();
        $this->post(route('admin.establecimiento-cursos.store'), $this->coursePayload($standard->id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $regularCourse = EstablecimientoCurso::sole();
        $variant = $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        $this->post(route('admin.establecimiento-cursos.store'), $this->coursePayload($variant->id, 'B'))
            ->assertRedirect()->assertSessionHasNoErrors();
        $variantCourse = EstablecimientoCurso::where('letra', 'B')->sole();
        $this->assertSame($standard->id, DotacionPlanEstudioResolver::resolve($regularCourse->fresh())->id);
        $this->assertSame($variant->id, DotacionPlanEstudioResolver::resolve($variantCourse)->id);
        $this->assertEquals(43, DotacionEstablecimientoCalculator::horasCurso($variantCourse)['horas']);
        $this->assertEquals(38, DotacionEstablecimientoCalculator::horasCurso($regularCourse->fresh())['horas']);
        $view = app(\App\Http\Controllers\Admin\EstablecimientoCursoController::class)->edit($variantCourse);
        $this->assertCount(2, $view->getData()['planes']);
        $html = view('admin.establecimiento-cursos._form', $view->getData())->render();
        $this->assertStringContainsString('43,00 h/semana', $html);
        $this->assertStringContainsString('Plan 43 horas', $html);
    }

    public function test_asociacion_automatica_y_referencia_no_eligen_una_variante_arbitraria(): void
    {
        $standard = $this->createPlan();
        $this->post(route('admin.establecimiento-cursos.store'), $this->coursePayload(null))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($standard->id, EstablecimientoCurso::sole()->plan_estudio_id);
        $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        $this->from('/admin/establecimiento-cursos/create')
            ->post(route('admin.establecimiento-cursos.store'), $this->coursePayload(null, 'B'))
            ->assertRedirect('/admin/establecimiento-cursos/create')->assertSessionHasErrors('plan_estudio_id');
        $this->assertSame(1, EstablecimientoCurso::count());
        $withoutPlan = new EstablecimientoCurso($this->coursePayload(null, 'B'));
        $this->assertNull(DotacionPlanEstudioResolver::resolve($withoutPlan));
    }

    public function test_rechaza_plan_asociado_de_otro_curso_o_anio(): void
    {
        $wrong = $this->createPlan(['anio' => 2028]);
        $this->post(route('admin.establecimiento-cursos.store'), $this->coursePayload($wrong->id))
            ->assertSessionHasErrors('plan_estudio_id');
        $this->assertSame(0, EstablecimientoCurso::count());
    }

    public function test_cambiar_variante_no_desvincula_la_configuracion_ya_registrada(): void
    {
        $standard = $this->createPlan();
        $this->post(route('admin.establecimiento-cursos.store'), $this->coursePayload($standard->id))->assertRedirect();
        $course = EstablecimientoCurso::sole();
        DB::table('establecimiento_planes_estudio')->insert([
            'establecimiento_curso_id' => $course->id, 'plan_estudio_id' => $standard->id,
        ]);
        $variant = $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        $this->put(route('admin.establecimiento-cursos.update', $course), $this->coursePayload($variant->id))
            ->assertSessionHasErrors('plan_estudio_id');
        $this->assertSame($standard->id, $course->fresh()->plan_estudio_id);
        $this->assertSame($standard->id, (int) DB::table('establecimiento_planes_estudio')->value('plan_estudio_id'));

        $this->put(route('admin.establecimiento-cursos.update', $course), $this->coursePayload($standard->id) + ['nombre_seccion' => 'Sección sintética'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Sección sintética', $course->fresh()->nombre_seccion);
    }

    public function test_migracion_conserva_ids_y_asociaciones_y_es_idempotente(): void
    {
        $migration = require database_path('migrations/2026_10_15_160000_allow_named_plan_estudio_variants.php');
        $migration->down();
        $standard = $this->createPlan();
        $this->post(route('admin.establecimiento-cursos.store'), $this->coursePayload($standard->id))->assertRedirect();
        $before = $standard->getAttributes();
        $blockId = $standard->bloques->sole()->id;
        $migration->up();
        $migration->up();
        $this->assertFalse(Schema::hasIndex('planes_estudio', 'planes_estudio_curso_anio_regimen_unique'));
        $this->assertTrue(Schema::hasIndex('planes_estudio', 'planes_estudio_curso_anio_regimen_nombre_unique'));
        $this->assertSame($before, $standard->fresh()->getAttributes());
        $this->assertSame($blockId, $standard->fresh()->bloques->sole()->id);
        $this->assertSame($standard->id, EstablecimientoCurso::sole()->plan_estudio_id);
        $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        try {
            $migration->down();
            $this->fail('La reversión no debe eliminar variantes ni cambiar sus asociaciones.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Hay variantes', $error->getMessage());
        }
        $this->assertSame(2, PlanEstudio::count());
        $this->assertSame($standard->id, EstablecimientoCurso::sole()->plan_estudio_id);
    }

    public function test_carga_masiva_actualiza_cada_variante_por_nombre_y_sus_detalles(): void
    {
        $standard = $this->createPlan();
        $variant = $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        $this->importSheets([
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'NOMBRE_PLAN', 'HORAS_SEMANALES_TOTAL'],
                [2027, '1B', 'Con JEC', 'Plan sintético', 38], [2027, '1B', 'Con JEC', 'Plan 43 horas', 43]],
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'NOMBRE_PLAN', 'NOMBRE_BLOQUE', 'HORAS_SEMANALES'],
                [2027, '1B', 'Con JEC', 'Plan sintético', 'Bloque regular importado', 32],
                [2027, '1B', 'Con JEC', 'Plan 43 horas', 'Bloque 43 importado', 37]],
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'NOMBRE_PLAN', 'ASIGNATURA', 'HORAS_SEMANALES'],
                [2027, '1B', 'Con JEC', 'Plan sintético', 'Asignatura regular importada', 32],
                [2027, '1B', 'Con JEC', 'Plan 43 horas', 'Asignatura 43 importada', 37]],
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('import_errors', []);
        $this->assertSame(2, PlanEstudio::count());
        $this->assertSame('38.00', $standard->fresh()->horas_semanales_total);
        $this->assertSame('43.00', $variant->fresh()->horas_semanales_total);
        $this->assertSame('Bloque regular importado', $standard->fresh()->bloques->sole()->nombre);
        $this->assertSame('Bloque 43 importado', $variant->fresh()->bloques->sole()->nombre);
        $this->assertSame('Asignatura regular importada', $standard->fresh()->asignaturas->sole()->asignatura);
        $this->assertSame('Asignatura 43 importada', $variant->fresh()->asignaturas->sole()->asignatura);
    }

    public function test_plantilla_anterior_identifica_la_unica_variante_del_resumen(): void
    {
        $standard = $this->createPlan();
        $variant = $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        $this->importSheets([
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'NOMBRE_PLAN', 'HORAS_SEMANALES_TOTAL'],
                [2027, '1B', 'Con JEC', 'Plan 43 horas', 43]],
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'NOMBRE_BLOQUE'], [2027, '1B', 'Con JEC', 'Bloque 43 heredado']],
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'ASIGNATURA'], [2027, '1B', 'Con JEC', 'Asignatura 43 heredada']],
        ])->assertRedirect()->assertSessionHas('import_errors', []);
        $this->assertSame('Bloque sintético', $standard->fresh()->bloques->sole()->nombre);
        $this->assertSame('Bloque 43 heredado', $variant->fresh()->bloques->sole()->nombre);
        $this->assertSame('Asignatura 43 heredada', $variant->fresh()->asignaturas->sole()->asignatura);
    }

    public function test_carga_ambigua_omite_detalles_sin_sobrescribir_variantes(): void
    {
        $standard = $this->createPlan();
        $variant = $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        $this->importSheets([
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'NOMBRE_PLAN', 'HORAS_SEMANALES_TOTAL'],
                [2027, '1B', 'Con JEC', 'Plan sintético', 38], [2027, '1B', 'Con JEC', 'Plan 43 horas', 43]],
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'NOMBRE_BLOQUE'], [2027, '1B', 'Con JEC', 'Bloque ambiguo']],
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'ASIGNATURA'], [2027, '1B', 'Con JEC', 'Asignatura ambigua']],
        ])->assertRedirect()->assertSessionHas('import_errors', fn ($errors) => count($errors) === 2 && str_contains(implode(' ', $errors), 'NOMBRE_PLAN'));
        foreach ([$standard, $variant] as $plan) {
            $this->assertSame('Bloque sintético', $plan->fresh()->bloques->sole()->nombre);
            $this->assertSame('Asignatura sintética', $plan->fresh()->asignaturas->sole()->asignatura);
        }
    }

    public function test_resumen_sin_nombre_omite_combinacion_ambigua_sin_modificar_planes(): void
    {
        $standard = $this->createPlan();
        $variant = $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        $before = PlanEstudio::orderBy('id')->get()->map->getAttributes()->all();
        $this->importSheets([
            [['ANIO', 'CURSO', 'REGIMEN_JEC', 'HORAS_SEMANALES_TOTAL'], [2027, '1B', 'Con JEC', 45]],
        ])->assertRedirect()->assertSessionHas('import_errors', fn ($errors) => count($errors) === 1 && str_contains($errors[0], 'NOMBRE_PLAN'));
        $this->assertSame($before, PlanEstudio::orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_importar_cursos_conserva_la_variante_asociada(): void
    {
        $this->createPlan();
        $variant = $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        $this->post(route('admin.establecimiento-cursos.store'), $this->coursePayload($variant->id))->assertRedirect();
        $file = $this->workbook([[['RBD', 'CURSO', 'LETRA', 'MATRICULA', 'JEC'], [99999, '1B', 'A', 20, 'Con JEC']]]);
        $this->post(route('admin.establecimiento-cursos.import.store'), ['archivo' => $file, 'anio' => 2027])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('import_errors', []);
        $this->assertSame($variant->id, EstablecimientoCurso::sole()->plan_estudio_id);
    }

    public function test_importar_cursos_conserva_asociacion_historica_a_plan_inactivo(): void
    {
        $standard = $this->createPlan();
        $this->post(route('admin.establecimiento-cursos.store'), $this->coursePayload($standard->id))->assertRedirect();
        $standard->update(['activo' => false]);
        $this->createPlan(['nombre_plan' => 'Plan 43 horas', 'horas_semanales_total' => 43]);
        $file = $this->workbook([[['RBD', 'CURSO', 'LETRA', 'MATRICULA', 'JEC'], [99999, '1B', 'A', 20, 'Con JEC']]]);
        $this->post(route('admin.establecimiento-cursos.import.store'), ['archivo' => $file, 'anio' => 2027])
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('import_errors', []);
        $this->assertSame($standard->id, EstablecimientoCurso::sole()->plan_estudio_id);
        $view = app(\App\Http\Controllers\Admin\EstablecimientoCursoController::class)->edit(EstablecimientoCurso::sole());
        $this->assertTrue($view->getData()['planes']->contains('id', $standard->id));
        $html = view('admin.establecimiento-cursos._form', $view->getData())->render();
        $this->assertStringContainsString('Inactivo (asociado)', $html);
    }

    private function importSheets(array $sheets): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('admin.planes-estudio.import.store'), ['archivo' => $this->workbook($sheets)]);
    }

    private function workbook(array $sheets): UploadedFile
    {
        $book = new Spreadsheet;
        foreach ($sheets as $index => $matrix) {
            ($index === 0 ? $book->getActiveSheet() : $book->createSheet())->fromArray($matrix);
        }
        $path = Storage::disk('local')->path('planes-sinteticos.xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'planes-sinteticos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function coursePayload(?int $planId, string $letter = 'A'): array
    {
        return ['establecimiento_id' => 1, 'curso_id' => Curso::where('codigo', '1B')->sole()->id,
            'anio' => 2027, 'regimen_jec' => 'Con JEC', 'plan_estudio_id' => $planId,
            'letra' => $letter, 'matricula' => 20, 'activo' => 1];
    }

    private function createPlan(array $overrides = []): PlanEstudio
    {
        $this->post(route('admin.planes-estudio.store'), $this->payload($overrides))
            ->assertRedirect(route('admin.planes-estudio.index'))->assertSessionHasNoErrors();

        return PlanEstudio::latest('id')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'curso_id' => Curso::where('codigo', '1B')->sole()->id,
            'anio' => 2027, 'regimen_jec' => 'Con JEC', 'nombre_plan' => 'Plan sintético',
            'horas_semanales_subtotal' => 32, 'horas_semanales_libre_disposicion' => 6,
            'horas_semanales_total' => 38, 'activo' => 1,
            'bloques' => [[
                'nombre' => 'Bloque sintético', 'tipo_bloque' => 'libre_disposicion',
                'horas_semanales' => 6, 'orden' => 1, 'activo' => 1,
                'permite_asignaturas_establecimiento' => 1, 'permite_asignaturas_personalizadas' => 1,
            ]],
            'asignaturas' => [[
                'asignatura' => 'Asignatura sintética', 'tipo_bloque' => 'asignatura',
                'horas_semanales' => 32, 'orden' => 1,
            ]],
        ], $overrides);
    }
}
