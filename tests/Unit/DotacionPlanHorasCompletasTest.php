<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\DotacionCursoCombinado;
use App\Models\DotacionCursoCombinadoMiembro;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Models\PlanEstudioAsignatura;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionPlanEstudioResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DotacionPlanHorasCompletasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $migration = require database_path('migrations/2026_05_25_183000_create_docente_horas_proporciones_table.php');
        $migration->up();
        $migration = require database_path('migrations/2026_07_23_160000_create_dotacion_cursos_combinados_tables.php');
        $migration->up();
        DotacionCursoCombinadoCalculator::clearCache();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dotacion_curso_combinado_asignaturas');
        Schema::dropIfExists('dotacion_curso_combinado_miembros');
        Schema::dropIfExists('dotacion_cursos_combinados');
        Schema::dropIfExists('docente_horas_proporciones');
        Schema::dropIfExists('establecimiento_cursos');
        Schema::dropIfExists('planes_estudio_bloques');
        Schema::dropIfExists('planes_estudio_asignaturas');
        Schema::dropIfExists('planes_estudio');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('establecimientos');
        DotacionCursoCombinadoCalculator::clearCache();

        parent::tearDown();
    }

    public function test_completa_solo_la_diferencia_cuando_el_desglose_del_plan_es_incompleto(): void
    {
        [$establecimiento, $curso, $plan] = $this->createCourseWithPlan('Con JEC', true, 38);
        PlanEstudioAsignatura::query()->create([
            'plan_estudio_id' => $plan->id,
            'asignatura' => 'Plan comun configurado',
            'horas_semanales' => 30,
            'tipo_bloque' => 'tiempo_minimo',
            'orden' => 1,
        ]);

        $necesidades = $this->planNeeds($establecimiento);
        $respaldo = $necesidades->firstWhere('subtipo_asignacion', 'plan_sin_desglose');

        $this->assertSame(38.0, round((float) $necesidades->sum('horas_plan_requeridas'), 2));
        $this->assertNotNull($respaldo);
        $this->assertSame(8.0, (float) $respaldo['horas_plan_requeridas']);
        $this->assertSame(30.0, (float) $respaldo['horas_plan_desglosadas']);
        $this->assertFalse($respaldo['plan_referencial_estimado']);
        $this->assertSame((int) $plan->id, (int) $respaldo['plan_estudio_id']);
        $this->assertSame((int) $plan->id, (int) $curso->plan_estudio_id);
        $this->assertSame(
            'plan:'.md5($curso->id.'|plan_sin_desglose|'.$plan->id),
            $respaldo['key']
        );
    }

    public function test_usa_el_plan_referencial_sin_persistirlo_si_el_curso_no_tiene_asociacion(): void
    {
        [$establecimiento, $curso, $plan] = $this->createCourseWithPlan('No aplica', false, 32, 'Sin JEC');

        $resuelto = DotacionPlanEstudioResolver::resolve($curso);
        $necesidades = $this->planNeeds($establecimiento);
        $respaldo = $necesidades->firstWhere('subtipo_asignacion', 'plan_sin_desglose');

        $this->assertSame((int) $plan->id, (int) $resuelto?->id);
        $this->assertTrue(DotacionPlanEstudioResolver::isReferential($curso, $resuelto));
        $this->assertSame(32.0, round((float) $necesidades->sum('horas_plan_requeridas'), 2));
        $this->assertNotNull($respaldo);
        $this->assertSame(32.0, (float) $respaldo['horas_plan_requeridas']);
        $this->assertTrue($respaldo['plan_referencial_estimado']);
        $this->assertNull($curso->fresh()->plan_estudio_id);

        $method = new ReflectionMethod(DotacionEstablecimientoCalculator::class, 'horasCurso');
        $horas = $method->invoke(null, $curso->fresh());
        $this->assertSame(32.0, $horas['horas']);
        $this->assertStringContainsString('referencial', $horas['fuente']);
    }

    public function test_clasifica_como_libre_disposicion_la_diferencia_de_ese_bloque(): void
    {
        [$establecimiento, $curso, $plan] = $this->createCourseWithPlan('Con JEC', true, 38, 'Con JEC', 6.5);
        PlanEstudioAsignatura::query()->create([
            'plan_estudio_id' => $plan->id,
            'asignatura' => 'Plan común configurado',
            'horas_semanales' => 31.5,
            'tipo_bloque' => 'tiempo_minimo',
            'orden' => 1,
        ]);

        $necesidades = $this->planNeeds($establecimiento);
        $libreDisposicion = $necesidades->firstWhere('subtipo_asignacion', 'libre_disposicion');

        $this->assertSame(38.0, round((float) $necesidades->sum('horas_plan_requeridas'), 2));
        $this->assertNotNull($libreDisposicion);
        $this->assertSame('Horas de libre disposición', $libreDisposicion['titulo']);
        $this->assertSame(6.5, (float) $libreDisposicion['horas_plan_requeridas']);
        $this->assertFalse($necesidades->contains(
            fn ($item) => ($item['subtipo_asignacion'] ?? null) === 'plan_sin_desglose'
        ));
        $this->assertSame((int) $curso->id, (int) $libreDisposicion['establecimiento_curso_id']);
        $this->assertSame(
            'plan:'.md5($curso->id.'|plan_sin_desglose|'.$plan->id),
            $libreDisposicion['key']
        );
    }

    public function test_las_horas_estimadas_se_consolidan_en_un_grupo_combinado(): void
    {
        [$establecimiento, $primerCurso] = $this->createCourseWithPlan('No aplica', false, 38, 'Sin JEC', 6);
        $segundoCurso = EstablecimientoCurso::query()->create([
            'establecimiento_id' => $establecimiento->id,
            'rbd' => $establecimiento->rbd,
            'curso_id' => $primerCurso->curso_id,
            'plan_estudio_id' => null,
            'anio' => 2026,
            'letra' => 'B',
            'nombre_seccion' => 'Curso de prueba B',
            'matricula' => 18,
            'regimen_jec' => 'No aplica',
            'activo' => true,
        ]);
        $grupo = DotacionCursoCombinado::query()->create([
            'establecimiento_id' => $establecimiento->id,
            'anio' => 2026,
            'nombre' => 'Grupo combinado de prueba',
            'proporcion' => 'auto',
            'activo' => true,
        ]);
        foreach ([$primerCurso, $segundoCurso] as $curso) {
            DotacionCursoCombinadoMiembro::query()->create([
                'dotacion_curso_combinado_id' => $grupo->id,
                'establecimiento_curso_id' => $curso->id,
            ]);
        }
        DotacionCursoCombinadoCalculator::clearCache();

        $necesidades = $this->planNeeds($establecimiento);

        $this->assertCount(2, $necesidades);
        $this->assertTrue($necesidades->every(
            fn ($item) => (int) $item['dotacion_curso_combinado_id'] === (int) $grupo->id
        ));
        $this->assertSame(38.0, round((float) $necesidades->sum('horas_plan_requeridas'), 2));
        $this->assertSame(76.0, round((float) $necesidades->sum('horas_plan_brutas'), 2));
        $this->assertSame(38.0, round((float) $necesidades->sum('horas_plan_reduccion'), 2));
        $this->assertSame(
            ['Horas de libre disposición', 'Horas del plan común sin desglose'],
            $necesidades->pluck('titulo')->sort()->values()->all()
        );
    }

    private function planNeeds(Establecimiento $establecimiento)
    {
        $method = new ReflectionMethod(DotacionAsignacionCalculator::class, 'necesidadesPlanEstudio');

        return $method->invoke(null, $establecimiento, 2026, collect());
    }

    private function createCourseWithPlan(
        string $regimenCurso,
        bool $associatePlan,
        float $total,
        string $regimenPlan = 'Con JEC',
        ?float $libreDisposicion = null
    ): array {
        $establecimiento = Establecimiento::query()->create([
            'rbd' => random_int(1000, 9999),
            'nombre_establecimiento' => 'Establecimiento de prueba',
        ]);
        $cursoCatalogo = Curso::query()->create([
            'nombre' => 'Curso de prueba',
            'codigo' => 'TEST-'.random_int(1000, 9999),
            'nivel_educativo' => 'Educacion Basica',
            'orden' => 1,
            'activo' => true,
        ]);
        $plan = PlanEstudio::query()->create([
            'curso_id' => $cursoCatalogo->id,
            'anio' => 2026,
            'nombre_plan' => 'Plan de prueba',
            'regimen_jec' => $regimenPlan,
            'horas_semanales_subtotal' => $libreDisposicion !== null ? $total - $libreDisposicion : null,
            'horas_semanales_libre_disposicion' => $libreDisposicion,
            'horas_semanales_total' => $total,
            'activo' => true,
        ]);
        $curso = EstablecimientoCurso::query()->create([
            'establecimiento_id' => $establecimiento->id,
            'rbd' => $establecimiento->rbd,
            'curso_id' => $cursoCatalogo->id,
            'plan_estudio_id' => $associatePlan ? $plan->id : null,
            'anio' => 2026,
            'letra' => 'A',
            'nombre_seccion' => 'Curso de prueba A',
            'matricula' => 20,
            'regimen_jec' => $regimenCurso,
            'activo' => true,
        ]);

        return [$establecimiento, $curso, $plan];
    }

    private function createTables(): void
    {
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('rbd')->nullable();
            $table->string('nombre_establecimiento');
            $table->timestamps();
        });
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('codigo');
            $table->string('nivel_educativo');
            $table->string('modalidad')->nullable();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('planes_estudio', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curso_id');
            $table->unsignedSmallInteger('anio');
            $table->string('nombre_plan');
            $table->string('nivel_educativo')->nullable();
            $table->string('modalidad')->nullable();
            $table->string('regimen_jec');
            $table->decimal('horas_semanales_subtotal', 6, 2)->nullable();
            $table->decimal('horas_semanales_libre_disposicion', 6, 2)->nullable();
            $table->decimal('horas_semanales_total', 6, 2)->nullable();
            $table->decimal('horas_anuales_total', 8, 2)->nullable();
            $table->string('decreto_referencia')->nullable();
            $table->text('observacion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('planes_estudio_asignaturas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_estudio_id');
            $table->string('asignatura');
            $table->decimal('horas_semanales', 6, 2)->nullable();
            $table->decimal('horas_anuales', 8, 2)->nullable();
            $table->string('tipo_bloque')->default('asignatura');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();
        });
        Schema::create('planes_estudio_bloques', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_estudio_id');
            $table->string('nombre');
            $table->string('tipo_bloque');
            $table->decimal('horas_semanales', 6, 2)->nullable();
            $table->decimal('horas_anuales', 8, 2)->nullable();
            $table->boolean('permite_asignaturas_establecimiento')->default(false);
            $table->boolean('permite_asignaturas_personalizadas')->default(false);
            $table->unsignedSmallInteger('orden')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->unsignedInteger('rbd')->nullable();
            $table->foreignId('curso_id');
            $table->foreignId('plan_estudio_id')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->string('letra')->nullable();
            $table->string('nombre_seccion');
            $table->unsignedSmallInteger('matricula')->default(0);
            $table->string('regimen_jec');
            $table->string('fuente')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }
}
