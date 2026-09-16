<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EstablecimientoCursoController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EstablecimientoCursoPlanFilterTest extends TestCase
{
    public function test_index_filters_courses_by_the_existence_of_an_associated_plan(): void
    {
        $this->createTables();

        try {
            $this->seedRecords();

            $withPlan = app(EstablecimientoCursoController::class)->index(
                Request::create('/admin/establecimiento-cursos', 'GET', ['anio' => 2027, 'estado_plan' => 'con_plan'])
            );
            $withoutPlan = app(EstablecimientoCursoController::class)->index(
                Request::create('/admin/establecimiento-cursos', 'GET', ['anio' => 2027, 'estado_plan' => 'sin_plan'])
            );

            $this->assertSame([10], $withPlan->getData()['items']->getCollection()->pluck('id')->all());
            $this->assertSame([11], $withoutPlan->getData()['items']->getCollection()->pluck('id')->all());
            $this->assertSame('con_plan', $withPlan->getData()['estadoPlan']);
            $this->assertSame('sin_plan', $withoutPlan->getData()['estadoPlan']);
        } finally {
            Schema::dropIfExists('establecimiento_cursos');
            Schema::dropIfExists('planes_estudio');
            Schema::dropIfExists('cursos');
            Schema::dropIfExists('establecimientos');
        }
    }

    private function createTables(): void
    {
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('rbd');
            $table->string('nombre_establecimiento');
            $table->string('comuna')->nullable();
            $table->timestamps();
        });

        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('codigo');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('planes_estudio', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_plan');
            $table->string('regimen_jec')->nullable();
            $table->decimal('horas_semanales_total', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedInteger('rbd')->nullable();
            $table->unsignedBigInteger('curso_id');
            $table->unsignedBigInteger('plan_estudio_id')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->string('letra')->nullable();
            $table->string('nombre_seccion')->nullable();
            $table->unsignedInteger('matricula')->default(0);
            $table->string('regimen_jec');
            $table->string('fuente')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    private function seedRecords(): void
    {
        $now = now();

        DB::table('establecimientos')->insert([
            'id' => 1,
            'rbd' => 5001,
            'nombre_establecimiento' => 'Establecimiento de prueba',
            'comuna' => 'Concepción',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('cursos')->insert([
            'id' => 1,
            'nombre' => 'Primero Básico',
            'codigo' => '1B',
            'orden' => 1,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('planes_estudio')->insert([
            'id' => 1,
            'nombre_plan' => 'Plan Primero Básico 2027',
            'regimen_jec' => 'Con JEC',
            'horas_semanales_total' => 38,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('establecimiento_cursos')->insert([
            [
                'id' => 10,
                'establecimiento_id' => 1,
                'rbd' => 5001,
                'curso_id' => 1,
                'plan_estudio_id' => 1,
                'anio' => 2027,
                'letra' => 'A',
                'nombre_seccion' => '1° Básico A',
                'matricula' => 25,
                'regimen_jec' => 'Con JEC',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 11,
                'establecimiento_id' => 1,
                'rbd' => 5001,
                'curso_id' => 1,
                'plan_estudio_id' => null,
                'anio' => 2027,
                'letra' => 'B',
                'nombre_seccion' => '1° Básico B',
                'matricula' => 24,
                'regimen_jec' => 'Con JEC',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
