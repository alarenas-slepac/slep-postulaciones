<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DotacionProceso2027PlanConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->integer('rbd');
            $table->string('nombre_establecimiento');
        });
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('planes_estudio', function (Blueprint $table): void {
            $table->id();
            $table->decimal('horas_semanales_libre_disposicion', 8, 2)->nullable();
        });
        Schema::create('planes_estudio_bloques', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_estudio_id');
            $table->string('tipo_bloque');
            $table->decimal('horas_semanales', 8, 2);
            $table->boolean('permite_asignaturas_establecimiento')->default(false);
            $table->boolean('permite_asignaturas_personalizadas')->default(false);
            $table->boolean('activo')->default(true);
        });
        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('curso_id');
            $table->unsignedBigInteger('plan_estudio_id')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->string('letra')->nullable();
            $table->string('nombre_seccion')->nullable();
            $table->integer('matricula')->default(0);
            $table->boolean('activo')->default(true);
        });
        Schema::create('establecimiento_planes_estudio', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('establecimiento_curso_id');
            $table->unsignedBigInteger('plan_estudio_id');
            $table->unsignedBigInteger('curso_id');
            $table->unsignedSmallInteger('anio');
            $table->string('estado');
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_plan_estudio_id');
            $table->unsignedBigInteger('plan_estudio_bloque_id');
            $table->decimal('horas_semanales', 8, 2);
        });

        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento de prueba']);
        DB::table('cursos')->insert(['id' => 1, 'nombre' => 'NT1']);
        DB::table('planes_estudio')->insert(['id' => 1, 'horas_semanales_libre_disposicion' => 6]);
        DB::table('planes_estudio_bloques')->insert([
            'id' => 1,
            'plan_estudio_id' => 1,
            'tipo_bloque' => 'libre_disposicion',
            'horas_semanales' => 6,
            'permite_asignaturas_establecimiento' => true,
            'permite_asignaturas_personalizadas' => true,
            'activo' => true,
        ]);
        DB::table('establecimiento_cursos')->insert([
            'id' => 1,
            'establecimiento_id' => 1,
            'curso_id' => 1,
            'plan_estudio_id' => 1,
            'anio' => 2027,
            'letra' => 'A',
            'nombre_seccion' => 'NT1 A',
            'matricula' => 20,
            'activo' => true,
        ]);
        DB::table('establecimiento_planes_estudio')->insert([
            'id' => 1,
            'establecimiento_id' => 1,
            'establecimiento_curso_id' => 1,
            'plan_estudio_id' => 1,
            'curso_id' => 1,
            'anio' => 2027,
            'estado' => 'enviado',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('establecimiento_planes_estudio_asignaturas');
        Schema::dropIfExists('establecimiento_planes_estudio');
        Schema::dropIfExists('establecimiento_cursos');
        Schema::dropIfExists('planes_estudio_bloques');
        Schema::dropIfExists('planes_estudio');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('establecimientos');

        parent::tearDown();
    }

    public function test_exige_las_horas_de_libre_disposicion_antes_de_completar_planes(): void
    {
        $establecimiento = Establecimiento::findOrFail(1);
        $primero = DotacionProceso2027Calculator::resumen($establecimiento, 2027, $this->data());

        $this->assertFalse($primero['pasos']['planes']['completo']);
        $this->assertSame(1, $primero['estado_planes']['libre_disposicion_pendiente']);

        DB::table('establecimiento_planes_estudio_asignaturas')->insert([
            'establecimiento_plan_estudio_id' => 1,
            'plan_estudio_bloque_id' => 1,
            'horas_semanales' => 6,
        ]);
        $segundo = DotacionProceso2027Calculator::resumen($establecimiento, 2027, $this->data());

        $this->assertTrue($segundo['pasos']['planes']['completo']);
        $this->assertSame(0, $segundo['estado_planes']['libre_disposicion_pendiente']);
    }

    private function data(): array
    {
        return [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => ['necesidades' => [], 'asignaciones' => []],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ];
    }
}
