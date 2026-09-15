<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlanEstudio2027MigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('planes_estudio', function (Blueprint $table): void {
            $table->id();
            $table->integer('curso_id');
            $table->integer('anio');
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
            $table->unique(['curso_id', 'anio', 'regimen_jec']);
        });
        Schema::create('planes_estudio_asignaturas', function (Blueprint $table): void {
            $table->id();
            $table->integer('plan_estudio_id');
            $table->string('asignatura');
            $table->decimal('horas_semanales', 6, 2)->nullable();
            $table->decimal('horas_anuales', 8, 2)->nullable();
            $table->string('tipo_bloque');
            $table->integer('orden');
            $table->timestamps();
        });
        Schema::create('planes_estudio_bloques', function (Blueprint $table): void {
            $table->id();
            $table->integer('plan_estudio_id');
            $table->string('nombre');
            $table->string('tipo_bloque');
            $table->decimal('horas_semanales', 6, 2)->nullable();
            $table->decimal('horas_anuales', 8, 2)->nullable();
            $table->boolean('permite_asignaturas_establecimiento')->default(false);
            $table->boolean('permite_asignaturas_personalizadas')->default(false);
            $table->integer('orden');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id();
            $table->integer('curso_id');
            $table->integer('plan_estudio_id')->nullable();
            $table->integer('anio');
            $table->string('regimen_jec');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('establecimiento_cursos');
        Schema::dropIfExists('planes_estudio_bloques');
        Schema::dropIfExists('planes_estudio_asignaturas');
        Schema::dropIfExists('planes_estudio');

        parent::tearDown();
    }

    public function test_copia_planes_y_detalles_a_2027_y_asigna_cursos_sin_plan(): void
    {
        $now = now();
        DB::table('planes_estudio')->insert([
            [
                'id' => 1, 'curso_id' => 10, 'anio' => 2026, 'nombre_plan' => 'Plan 2026 con JEC',
                'nivel_educativo' => 'Básica', 'modalidad' => null, 'regimen_jec' => 'Con JEC',
                'horas_semanales_total' => 38, 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 2, 'curso_id' => 10, 'anio' => 2026, 'nombre_plan' => 'Plan 2026 sin JEC',
                'nivel_educativo' => 'Básica', 'modalidad' => null, 'regimen_jec' => 'Sin JEC',
                'horas_semanales_total' => 30, 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 3, 'curso_id' => 20, 'anio' => 2026, 'nombre_plan' => 'Plan inactivo',
                'nivel_educativo' => 'Media', 'modalidad' => null, 'regimen_jec' => 'Con JEC',
                'horas_semanales_total' => 38, 'activo' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'id' => 4, 'curso_id' => 20, 'anio' => 2027, 'nombre_plan' => 'Plan 2027 definido',
                'nivel_educativo' => 'Media', 'modalidad' => null, 'regimen_jec' => 'Con JEC',
                'horas_semanales_total' => 40, 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
        DB::table('planes_estudio_asignaturas')->insert([
            'plan_estudio_id' => 1, 'asignatura' => 'Lenguaje', 'horas_semanales' => 6,
            'horas_anuales' => 228, 'tipo_bloque' => 'plan_comun', 'orden' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('planes_estudio_bloques')->insert([
            'plan_estudio_id' => 1, 'nombre' => 'Plan común', 'tipo_bloque' => 'plan_comun',
            'horas_semanales' => 32, 'horas_anuales' => 1216,
            'permite_asignaturas_establecimiento' => false, 'permite_asignaturas_personalizadas' => false,
            'orden' => 1, 'activo' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('establecimiento_cursos')->insert([
            ['id' => 101, 'curso_id' => 10, 'plan_estudio_id' => null, 'anio' => 2027, 'regimen_jec' => 'Con JEC', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 102, 'curso_id' => 10, 'plan_estudio_id' => null, 'anio' => 2027, 'regimen_jec' => 'No aplica', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 103, 'curso_id' => 20, 'plan_estudio_id' => null, 'anio' => 2027, 'regimen_jec' => 'Con JEC', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 104, 'curso_id' => 30, 'plan_estudio_id' => null, 'anio' => 2027, 'regimen_jec' => 'Con JEC', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 105, 'curso_id' => 10, 'plan_estudio_id' => 4, 'anio' => 2027, 'regimen_jec' => 'Con JEC', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $migration = require database_path('migrations/2026_09_15_110000_copy_planes_estudio_to_2027.php');
        $migration->up();
        $migration->up();

        $planConJec = DB::table('planes_estudio')->where('curso_id', 10)->where('anio', 2027)->where('regimen_jec', 'Con JEC')->first();
        $planSinJec = DB::table('planes_estudio')->where('curso_id', 10)->where('anio', 2027)->where('regimen_jec', 'Sin JEC')->first();

        $this->assertSame(3, DB::table('planes_estudio')->where('anio', 2027)->count());
        $this->assertSame('Plan 2026 con JEC', $planConJec->nombre_plan);
        $this->assertSame(1, DB::table('planes_estudio_asignaturas')->where('plan_estudio_id', $planConJec->id)->count());
        $this->assertSame(1, DB::table('planes_estudio_bloques')->where('plan_estudio_id', $planConJec->id)->count());
        $this->assertSame('Plan 2027 definido', DB::table('planes_estudio')->find(4)->nombre_plan);
        $this->assertSame((int) $planConJec->id, (int) DB::table('establecimiento_cursos')->find(101)->plan_estudio_id);
        $this->assertSame((int) $planSinJec->id, (int) DB::table('establecimiento_cursos')->find(102)->plan_estudio_id);
        $this->assertSame(4, (int) DB::table('establecimiento_cursos')->find(103)->plan_estudio_id);
        $this->assertNull(DB::table('establecimiento_cursos')->find(104)->plan_estudio_id);
        $this->assertSame(4, (int) DB::table('establecimiento_cursos')->find(105)->plan_estudio_id);
    }
}
