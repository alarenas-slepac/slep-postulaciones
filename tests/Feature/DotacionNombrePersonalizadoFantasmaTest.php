<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\Asignatura;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionCursoCombinado;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionNombrePersonalizadoFantasmaTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;
    private EstablecimientoCurso $curso;
    private int $detalleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
        foreach (['2026_01_23_000001_create_establecimientos_table.php', '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_18_130000_create_cursos_table.php', '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_150000_create_asignaturas_table.php', '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php', '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php', '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('establecimiento_planes_estudio', function (Blueprint $t): void {
            $t->id();
            foreach (['establecimiento_id', 'establecimiento_curso_id', 'curso_id', 'plan_estudio_id', 'anio'] as $field) $t->integer($field);
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $t): void {
            $t->id();
            foreach (['establecimiento_plan_estudio_id', 'plan_estudio_bloque_id', 'asignatura_id', 'asignatura_plan_comun_id', 'orden'] as $field) $t->integer($field)->nullable();
            foreach (['nombre_asignatura_personalizada', 'origen', 'observacion'] as $field) $t->string($field)->nullable();
            $t->decimal('horas_semanales', 8, 2); $t->decimal('horas_anuales', 8, 2)->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $field) (new ReflectionProperty($class, $field))->setValue(null, []);
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }

    public function test_renombrar_personalizada_con_clave_oficial_estable_marca_horas_anteriores_como_fantasmas(): void
    {
        $this->configurar();
        $antes = $this->necesidades()->firstWhere('titulo', 'Taller matemático anterior');
        $asignacion = $this->asignar($antes);
        $this->assertSame(2.0, $this->necesidades()->firstWhere('key', $antes['key'])['horas_plan_asignadas']);
        $this->renombrar('Taller matemático nuevo');
        $snapshot = $this->snapshot();
        $needs = $this->necesidades();
        $actual = $needs->firstWhere('titulo', 'Taller matemático nuevo');
        $this->assertSame($antes['key'], $actual['key'], 'El caso conserva la clave oficial aunque cambie el nombre personalizado.');
        $this->assertCount(1, $this->fantasmas($needs));
        $this->assertSame($asignacion->id, $this->fantasmas($needs)->first()->id);
        $this->assertStringContainsString('nombre', $this->fantasmas($needs)->first()->motivo_huerfana);
        $this->assertSame(0.0, $actual['horas_plan_asignadas']);
        $this->assertSame(2.0, $actual['horas_plan_pendientes']);
        $this->assertSame(2.0, (float) DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027)->sum('horas_contrato'));
        $this->assertSame($snapshot, $this->snapshot(), 'La detección no debe editar ni eliminar registros.');
    }

    public function test_nueva_asignacion_cubre_solo_el_nombre_vigente_sin_reutilizar_horas_anteriores(): void
    {
        $this->configurar();
        $old = $this->asignar($this->necesidades()->firstWhere('titulo', 'Taller matemático anterior'));
        $this->renombrar('Taller matemático nuevo');
        $new = $this->asignar($this->necesidades()->firstWhere('titulo', 'Taller matemático nuevo'));
        $needs = $this->necesidades();
        $actual = $needs->firstWhere('titulo', 'Taller matemático nuevo');
        $this->assertEquals([$old->id], $this->fantasmas($needs)->pluck('id')->all());
        $this->assertEquals([$new->id], $actual['asignaciones']->pluck('id')->all());
        $this->assertSame(2.0, $actual['horas_plan_asignadas']);
        $this->assertSame(0.0, $actual['horas_plan_pendientes']);
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 2);
    }

    public function test_quitar_nombre_personalizado_no_reasigna_la_carga_antigua_al_nombre_oficial(): void
    {
        $this->configurar();
        $this->asignar($this->necesidades()->firstWhere('titulo', 'Taller matemático anterior'));
        $this->renombrar(null);
        $needs = $this->necesidades();
        $this->assertCount(1, $this->fantasmas($needs));
        $this->assertSame(0.0, $needs->firstWhere('titulo', 'Matemática')['horas_plan_asignadas']);
    }

    public function test_libre_disposicion_y_acompanamiento_conservan_las_filas_para_revision_manual(): void
    {
        $this->configurar('libre_disposicion');
        $need = $this->necesidades()->firstWhere('titulo', 'Taller matemático anterior');
        $this->asignar($need);
        $this->asignar($need, 'acompanamiento_parvularia');
        $this->renombrar('Actividad nueva');
        $snapshot = $this->snapshot();
        $needs = $this->necesidades();
        $this->assertCount(2, $this->fantasmas($needs));
        $this->assertSame(0.0, $needs->firstWhere('titulo', 'Actividad nueva')['horas_plan_asignadas']);
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_mantener_nombre_no_crea_fantasmas_ni_depende_del_id_recreado_del_detalle(): void
    {
        $this->configurar();
        $need = $this->necesidades()->firstWhere('titulo', 'Taller matemático anterior');
        $this->asignar($need);
        $detalle = (array) DB::table('establecimiento_planes_estudio_asignaturas')->find($this->detalleId);
        DB::table('establecimiento_planes_estudio_asignaturas')->where('id', $this->detalleId)->delete();
        unset($detalle['id']);
        DB::table('establecimiento_planes_estudio_asignaturas')->insert($detalle);
        $needs = $this->necesidades();
        $this->assertCount(0, $this->fantasmas($needs));
        $this->assertSame(2.0, $needs->firstWhere('key', $need['key'])['horas_plan_asignadas']);
    }

    public function test_curso_combinado_con_clave_historica_separa_el_taller_renombrado(): void
    {
        [$grupo, $need] = $this->configurarCombinado();
        $row = $this->asignar(array_replace($need, ['asignatura_nombre' => 'Taller matemático anterior']));
        $row->update(['dotacion_curso_combinado_id' => $grupo->id]);
        $this->assertCount(0, $this->fantasmas($this->necesidades()));
        $this->renombrar('Taller matemático nuevo');
        $snapshot = $this->snapshot();
        $needs = $this->necesidades();
        $actual = $needs->firstWhere('dotacion_curso_combinado_id', $grupo->id);
        $this->assertSame($need['key'], $actual['key']);
        $this->assertCount(1, $this->fantasmas($needs));
        $this->assertSame(0.0, $actual['horas_plan_asignadas']);
        $this->assertSame($snapshot, $this->snapshot());
    }

    private function configurarCombinado(string $codigo = '1M'): array
    {
        $this->configurar('libre_disposicion', $codigo);
        $asigId = DB::table('establecimiento_planes_estudio_asignaturas')->where('id', $this->detalleId)->value('asignatura_id');
        DB::table('establecimiento_planes_estudio_asignaturas')->where('id', $this->detalleId)->update(['asignatura_plan_comun_id' => $asigId]);
        $otro = $this->curso->replicate(); $otro->letra = 'B'; $otro->nombre_seccion = '1M B'; $otro->save();
        $grupo = DotacionCursoCombinado::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027, 'nombre' => 'Grupo sintético', 'activo' => true]);
        foreach ([$this->curso->id, $otro->id] as $id) $grupo->miembros()->create(['establecimiento_curso_id' => $id]);
        $materiaKey = DotacionCursoCombinadoCalculator::subjectKey(['plan_comun_asociado' => 'Matemática']);
        $grupo->asignaturas()->create(['asignatura_key' => $materiaKey, 'asignatura_nombre' => 'Matemática', 'modalidad' => 'personalizada', 'horas_personalizadas' => 4]);
        $need = $this->necesidades()->firstWhere('dotacion_curso_combinado_id', $grupo->id);

        return [$grupo, $need];
    }

    public function test_eliminar_fantasma_no_descuenta_de_nuevo_cobertura_que_sostiene_acompanamiento_vigente(): void
    {
        [$grupo, $need] = $this->configurarCombinado('NT1');
        $ghost = $this->asignar(array_replace($need, ['asignatura_nombre' => 'Taller matemático anterior']));
        $this->renombrar('Taller matemático nuevo');
        $currentNeed = $this->necesidades()->firstWhere('dotacion_curso_combinado_id', $grupo->id);
        $external = $this->asignar($currentNeed);
        $acomp = $this->asignar($currentNeed, 'acompanamiento_parvularia');
        $acomp->update(['horas_plan_pedagogicas' => 1, 'horas_contrato' => 1]);
        $need = $this->necesidades()->firstWhere('key', $currentNeed['key']);
        $this->assertSame(2.0, $need['horas_externas_libre_disposicion']);
        $this->assertSame(1.0, $need['horas_acompanamiento_asignadas']);
        app(DotacionAsignacionController::class)->destroy($this->request(), $this->ee, $ghost);
        $this->assertNull($ghost->fresh());
        $this->assertNotNull($external->fresh());
        $this->assertNotNull($acomp->fresh());
        try {
            app(DotacionAsignacionController::class)->destroy($this->request(), $this->ee, $external);
            $this->fail('Debe seguir protegiendo la cobertura del acompañamiento vigente.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('horas_plan_pedagogicas', $e->errors());
        }
    }

    public function test_acompanamiento_huerfano_se_puede_eliminar_manualmente_sin_necesidad_vigente(): void
    {
        $this->configurar('libre_disposicion', 'NT1');
        $oldNeed = $this->necesidades()->firstWhere('titulo', 'Taller matemático anterior');
        $ghost = $this->asignar($oldNeed, 'acompanamiento_parvularia');
        $this->renombrar('Actividad nueva');
        $currentNeed = $this->necesidades()->firstWhere('titulo', 'Actividad nueva');
        $current = $this->asignar($currentNeed);
        $this->assertCount(1, $this->fantasmas($this->necesidades()));
        app(DotacionAsignacionController::class)->destroy($this->request(), $this->ee, $ghost);
        $this->assertNull($ghost->fresh());
        $this->assertNotNull($current->fresh());
    }

    public function test_editar_fantasma_no_libera_horas_que_no_cubrían_la_asignatura_nueva(): void
    {
        $this->configurar();
        $ghost = $this->asignar($this->necesidades()->firstWhere('titulo', 'Taller matemático anterior'));
        $this->renombrar('Actividad nueva');
        $current = $this->necesidades()->firstWhere('titulo', 'Actividad nueva');
        $this->asignar($current);
        $this->expectException(ValidationException::class);
        (new ReflectionMethod(DotacionAsignacionController::class, 'validatePlanHoursAvailable'))->invoke(
            app(DotacionAsignacionController::class), $this->ee, ['anio' => 2027, 'tipo_asignacion' => 'plan_estudio',
                'necesidad_key' => $current['key'], 'horas_plan_pedagogicas' => 1], $ghost);
    }

    public function test_ajustes_de_mayusculas_tildes_y_espacios_no_inventan_un_cambio_de_asignatura(): void
    {
        $this->configurar();
        $need = $this->necesidades()->firstWhere('titulo', 'Taller matemático anterior');
        $this->asignar($need);
        $this->renombrar(' TALLER MATEMATICO  ANTERIOR ');
        $needs = $this->necesidades();
        $this->assertCount(0, $this->fantasmas($needs));
        $this->assertSame(2.0, $needs->firstWhere('key', $need['key'])['horas_plan_asignadas']);
    }

    private function configurar(string $tipo = 'plan_comun_formacion_general', string $codigo = '1M'): void
    {
        $this->ee = Establecimiento::create(['cod_estab' => 'SINTETICO', 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']);
        $curso = Curso::where('codigo', $codigo)->firstOrFail();
        $plan = PlanEstudio::create(['curso_id' => $curso->id, 'anio' => 2027, 'nombre_plan' => 'Plan sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_subtotal' => 2, 'horas_semanales_libre_disposicion' => 0, 'horas_semanales_total' => 2, 'activo' => true]);
        $plan->asignaturas()->create(['asignatura' => 'Matemática', 'tipo_bloque' => 'asignatura', 'horas_semanales' => 2]);
        $this->curso = EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'curso_id' => $curso->id,
            'plan_estudio_id' => $plan->id, 'anio' => 2027, 'letra' => 'A', 'nombre_seccion' => '1M A', 'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true]);
        $bloque = $plan->bloques()->create(['nombre' => 'Bloque configurado', 'tipo_bloque' => $tipo, 'horas_semanales' => 2,
            'activo' => true, 'permite_asignaturas_establecimiento' => true, 'permite_asignaturas_personalizadas' => true]);
        $asignatura = Asignatura::firstOrCreate(['nombre' => 'Matemática'], ['tipo_asignatura' => 'obligatoria', 'activo' => true]);
        $config = DB::table('establecimiento_planes_estudio')->insertGetId(['establecimiento_id' => $this->ee->id,
            'establecimiento_curso_id' => $this->curso->id, 'curso_id' => $curso->id, 'plan_estudio_id' => $plan->id, 'anio' => 2027]);
        $this->detalleId = DB::table('establecimiento_planes_estudio_asignaturas')->insertGetId(['establecimiento_plan_estudio_id' => $config,
            'plan_estudio_bloque_id' => $bloque->id, 'asignatura_id' => $asignatura->id,
            'nombre_asignatura_personalizada' => 'Taller matemático anterior', 'horas_semanales' => 2, 'orden' => 1]);
    }

    private function renombrar(?string $name): void
    {
        DB::table('establecimiento_planes_estudio_asignaturas')->where('id', $this->detalleId)->update(['nombre_asignatura_personalizada' => $name]);
    }

    private function asignar(array $need, string $tipo = 'plan_estudio'): DotacionDocenteAsignacion
    {
        return DotacionDocenteAsignacion::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente sintético',
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'plan_estudio_id' => $need['plan_estudio_id'],
            'plan_bloque_id' => $need['plan_bloque_id'] ?? null, 'asignatura_id' => $need['asignatura_id'] ?? null,
            'tipo_asignacion' => $tipo, 'subtipo_asignacion' => $need['subtipo_asignacion'], 'necesidad_key' => $need['key'],
            'asignatura_nombre' => $need['asignatura_nombre'], 'horas_plan_pedagogicas' => 2, 'horas_contrato' => 2, 'estado' => 'activa']);
    }

    private function necesidades()
    {
        return DotacionLecturaCache::ejecutar(fn () => DotacionAsignacionCalculator::necesidades($this->ee, 2027,
            [], [], DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027))['plan_estudio']);
    }

    private function fantasmas($needs)
    {
        return (new ReflectionMethod(DotacionAsignacionCalculator::class, 'asignacionesHuerfanas'))->invoke(null,
            DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027), ['plan_estudio' => $needs]);
    }

    private function snapshot(): string
    {
        return DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
    }

    private function request(): Request
    {
        $request = Request::create('/asignaciones', 'DELETE');
        $request->setUserResolver(fn () => new class {
            public function activeRoleName(): string { return 'admin'; }
        });
        $request->setLaravelSession($this->app['session.store']);

        return $request;
    }
}
