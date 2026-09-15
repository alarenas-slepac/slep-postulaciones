<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IndicadoresCursos2027MigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('alumnos_prioritarios_porcentajes', function (Blueprint $table): void {
            $table->id();
            $table->integer('establecimiento_id');
            $table->integer('anio');
            $table->decimal('porcentaje', 5, 2);
            $table->text('observacion')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['establecimiento_id', 'anio']);
        });
        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id();
            $table->integer('establecimiento_id');
            $table->integer('curso_id');
            $table->integer('plan_estudio_id')->nullable();
            $table->integer('anio');
            $table->string('letra')->nullable();
            $table->integer('rbd')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('establecimiento_curso_pie', function (Blueprint $table): void {
            $table->id();
            $table->integer('establecimiento_id');
            $table->integer('establecimiento_curso_id');
            $table->integer('curso_id')->nullable();
            $table->integer('plan_estudio_id')->nullable();
            $table->integer('anio');
            $table->integer('rbd')->nullable();
            $table->integer('necesidades_transitorias')->default(0);
            $table->integer('necesidades_permanentes')->default(0);
            $table->integer('total_pie')->default(0);
            $table->text('observacion')->nullable();
            $table->string('estado')->default('borrador');
            $table->string('regimen_calculo')->nullable();
            $table->integer('neet_calculo')->nullable();
            $table->integer('neep_calculo')->nullable();
            $table->integer('total_crono_minutos')->nullable();
            $table->integer('prof_educ_dif_minutos')->nullable();
            $table->integer('pae_minutos')->nullable();
            $table->text('calculo_observacion')->nullable();
            $table->timestamp('calculado_at')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['establecimiento_curso_id', 'anio']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('establecimiento_curso_pie');
        Schema::dropIfExists('establecimiento_cursos');
        Schema::dropIfExists('alumnos_prioritarios_porcentajes');

        parent::tearDown();
    }

    public function test_copia_indicadores_solo_para_cursos_2027_equivalentes_y_sin_sobrescribir(): void
    {
        $now = now();
        DB::table('alumnos_prioritarios_porcentajes')->insert([
            ['id' => 1, 'establecimiento_id' => 1, 'anio' => 2026, 'porcentaje' => 82.5, 'observacion' => 'Origen 2026', 'created_by' => 5, 'updated_by' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'establecimiento_id' => 2, 'anio' => 2026, 'porcentaje' => 70, 'observacion' => 'Sin cursos 2027', 'created_by' => 5, 'updated_by' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'establecimiento_id' => 3, 'anio' => 2026, 'porcentaje' => 60, 'observacion' => 'Destino existente', 'created_by' => 5, 'updated_by' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'establecimiento_id' => 3, 'anio' => 2027, 'porcentaje' => 90, 'observacion' => 'Ajuste 2027', 'created_by' => 9, 'updated_by' => 9, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('establecimiento_cursos')->insert([
            ['id' => 101, 'establecimiento_id' => 1, 'curso_id' => 10, 'plan_estudio_id' => 100, 'anio' => 2026, 'letra' => 'A', 'rbd' => 5001, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 102, 'establecimiento_id' => 1, 'curso_id' => 20, 'plan_estudio_id' => 101, 'anio' => 2026, 'letra' => 'A', 'rbd' => 5001, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 103, 'establecimiento_id' => 3, 'curso_id' => 10, 'plan_estudio_id' => 102, 'anio' => 2026, 'letra' => 'A', 'rbd' => 5003, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 201, 'establecimiento_id' => 1, 'curso_id' => 10, 'plan_estudio_id' => 200, 'anio' => 2027, 'letra' => 'A', 'rbd' => 5001, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 202, 'establecimiento_id' => 1, 'curso_id' => 20, 'plan_estudio_id' => 201, 'anio' => 2027, 'letra' => 'B', 'rbd' => 5001, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 203, 'establecimiento_id' => 3, 'curso_id' => 10, 'plan_estudio_id' => 202, 'anio' => 2027, 'letra' => 'A', 'rbd' => 5003, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('establecimiento_curso_pie')->insert([
            $this->pie(1, 1, 101, 10, 100, 2026, 4, 2, 'PIE origen'),
            $this->pie(2, 1, 102, 20, 101, 2026, 2, 1, 'No tiene sección equivalente'),
            $this->pie(3, 3, 103, 10, 102, 2026, 3, 1, 'No debe sobrescribir'),
            $this->pie(4, 3, 203, 10, 202, 2027, 8, 2, 'Ajuste 2027'),
        ]);

        $migration = require database_path('migrations/2026_09_15_120000_copy_indicadores_cursos_to_2027.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('alumnos_prioritarios_porcentajes', [
            'establecimiento_id' => 1, 'anio' => 2027, 'porcentaje' => 82.5, 'observacion' => 'Origen 2026',
        ]);
        $this->assertDatabaseMissing('alumnos_prioritarios_porcentajes', [
            'establecimiento_id' => 2, 'anio' => 2027,
        ]);
        $this->assertDatabaseHas('alumnos_prioritarios_porcentajes', [
            'establecimiento_id' => 3, 'anio' => 2027, 'porcentaje' => 90, 'observacion' => 'Ajuste 2027',
        ]);
        $this->assertSame(2, DB::table('alumnos_prioritarios_porcentajes')->where('anio', 2027)->count());

        $this->assertDatabaseHas('establecimiento_curso_pie', [
            'establecimiento_curso_id' => 201, 'anio' => 2027, 'curso_id' => 10, 'plan_estudio_id' => 200,
            'necesidades_transitorias' => 4, 'necesidades_permanentes' => 2, 'total_pie' => 6,
            'observacion' => 'PIE origen', 'prof_educ_dif_minutos' => 360,
        ]);
        $this->assertDatabaseMissing('establecimiento_curso_pie', [
            'establecimiento_curso_id' => 202, 'anio' => 2027,
        ]);
        $this->assertDatabaseHas('establecimiento_curso_pie', [
            'establecimiento_curso_id' => 203, 'anio' => 2027, 'necesidades_transitorias' => 8, 'observacion' => 'Ajuste 2027',
        ]);
        $this->assertSame(2, DB::table('establecimiento_curso_pie')->where('anio', 2027)->count());
    }

    private function pie(int $id, int $establecimientoId, int $establecimientoCursoId, int $cursoId, int $planId, int $anio, int $neet, int $neep, string $observacion): array
    {
        return [
            'id' => $id, 'establecimiento_id' => $establecimientoId, 'establecimiento_curso_id' => $establecimientoCursoId,
            'curso_id' => $cursoId, 'plan_estudio_id' => $planId, 'anio' => $anio, 'rbd' => 5000 + $establecimientoId,
            'necesidades_transitorias' => $neet, 'necesidades_permanentes' => $neep, 'total_pie' => $neet + $neep,
            'observacion' => $observacion, 'estado' => 'validado', 'regimen_calculo' => 'con_jec',
            'neet_calculo' => $neet, 'neep_calculo' => $neep, 'total_crono_minutos' => 600,
            'prof_educ_dif_minutos' => 360, 'pae_minutos' => 240, 'calculo_observacion' => 'Cálculo origen',
            'calculado_at' => now(), 'created_by' => 5, 'updated_by' => 6, 'created_at' => now(), 'updated_at' => now(),
        ];
    }
}
