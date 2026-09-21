<?php

namespace Tests\Feature;

use App\Services\Pie\PieCourseTransferService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PieCourseTransferServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTables();
        $this->seedRecords();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('establecimiento_curso_pie');
        Schema::dropIfExists('pie_horas_apoyo_minimo');
        Schema::dropIfExists('establecimiento_cursos');
        Schema::dropIfExists('cursos');

        parent::tearDown();
    }

    public function test_transfer_consolidates_and_distributes_pie_by_level_structure(): void
    {
        $service = app(PieCourseTransferService::class);

        $resultado = $service->transferForEstablishments([1, 2], 2026, 2027, 99);

        $this->assertDatabaseHas('establecimiento_curso_pie', [
            'establecimiento_curso_id' => 201,
            'anio' => 2027,
            'necesidades_transitorias' => 8,
            'necesidades_permanentes' => 4,
            'total_pie' => 12,
            'created_by' => 99,
        ]);
        $this->assertDatabaseHas('establecimiento_curso_pie', [
            'establecimiento_curso_id' => 203,
            'anio' => 2027,
            'necesidades_transitorias' => 3,
            'necesidades_permanentes' => 1,
            'total_pie' => 4,
        ]);
        $this->assertDatabaseHas('establecimiento_curso_pie', [
            'establecimiento_curso_id' => 204,
            'anio' => 2027,
            'necesidades_transitorias' => 2,
            'necesidades_permanentes' => 1,
            'total_pie' => 3,
        ]);
        $this->assertSame(2, $resultado['establecimientos_procesados']);
        $this->assertSame(0, $resultado['establecimientos_con_error']);
        $this->assertSame(2, $resultado['niveles_procesados']);
        $this->assertSame(3, $resultado['registros_creados']);
        $this->assertSame(0, $resultado['registros_actualizados']);
        $this->assertSame(0, $resultado['registros_omitidos_por_igualdad']);
        $this->assertSame(3, DB::table('establecimiento_curso_pie')->where('anio', 2027)->count());
    }

    public function test_transfer_overwrites_destination_records_when_neet_or_neep_changes(): void
    {
        DB::table('establecimiento_curso_pie')->insert($this->pie(9, 1, 201, 2027, 7, 1));

        $resultado = app(PieCourseTransferService::class)->transfer(1, 2026, 2027, 99);

        $this->assertSame(1, $resultado['niveles_procesados']);
        $this->assertSame(0, $resultado['niveles_omitidos']);
        $this->assertSame(0, $resultado['registros_creados']);
        $this->assertSame(1, $resultado['registros_actualizados']);
        $this->assertSame(0, $resultado['registros_omitidos_por_igualdad']);
        $this->assertDatabaseHas('establecimiento_curso_pie', [
            'establecimiento_curso_id' => 201,
            'anio' => 2027,
            'necesidades_transitorias' => 8,
            'necesidades_permanentes' => 4,
            'total_pie' => 12,
            'updated_by' => 99,
        ]);
        $this->assertSame(1, DB::table('establecimiento_curso_pie')->where('establecimiento_curso_id', 201)->where('anio', 2027)->count());
    }

    public function test_transfer_omits_destination_records_when_neet_and_neep_are_equal(): void
    {
        DB::table('establecimiento_curso_pie')->insert($this->pie(9, 1, 201, 2027, 8, 4));

        $resultado = app(PieCourseTransferService::class)->transfer(1, 2026, 2027, 99);

        $this->assertSame(0, $resultado['niveles_procesados']);
        $this->assertSame(1, $resultado['niveles_omitidos']);
        $this->assertSame(0, $resultado['registros_creados']);
        $this->assertSame(0, $resultado['registros_actualizados']);
        $this->assertSame(1, $resultado['registros_omitidos_por_igualdad']);
        $this->assertDatabaseHas('establecimiento_curso_pie', [
            'id' => 9,
            'establecimiento_curso_id' => 201,
            'anio' => 2027,
            'necesidades_transitorias' => 8,
            'necesidades_permanentes' => 4,
            'updated_by' => null,
        ]);
    }

    private function createTables(): void
    {
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('codigo');
            $table->string('nivel_educativo')->nullable();
            $table->string('modalidad')->nullable();
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
            $table->string('regimen_jec')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('establecimiento_curso_pie', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('establecimiento_curso_id');
            $table->unsignedBigInteger('curso_id')->nullable();
            $table->unsignedBigInteger('plan_estudio_id')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('rbd')->nullable();
            $table->unsignedSmallInteger('necesidades_transitorias')->default(0);
            $table->unsignedSmallInteger('necesidades_permanentes')->default(0);
            $table->unsignedSmallInteger('total_pie')->default(0);
            $table->text('observacion')->nullable();
            $table->string('estado')->default('borrador');
            $table->string('regimen_calculo')->nullable();
            $table->unsignedSmallInteger('neet_calculo')->nullable();
            $table->unsignedSmallInteger('neep_calculo')->nullable();
            $table->unsignedInteger('total_crono_minutos')->nullable();
            $table->unsignedInteger('prof_educ_dif_minutos')->nullable();
            $table->unsignedInteger('pae_minutos')->nullable();
            $table->text('calculo_observacion')->nullable();
            $table->timestamp('calculado_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['establecimiento_curso_id', 'anio']);
        });
        Schema::create('pie_horas_apoyo_minimo', function (Blueprint $table): void {
            $table->id();
            $table->string('regimen_jec');
            $table->unsignedSmallInteger('neet_cantidad_base')->default(5);
            $table->unsignedSmallInteger('neet_horas_base_minutos')->default(0);
            $table->unsignedSmallInteger('neep_cantidad');
            $table->unsignedInteger('neep_horas_minutos')->default(0);
            $table->unsignedInteger('total_crono_minutos');
            $table->unsignedInteger('prof_educ_dif_minutos');
            $table->unsignedInteger('pae_minutos');
            $table->boolean('vigente')->default(true);
            $table->timestamps();
        });
    }

    private function seedRecords(): void
    {
        $now = now();
        DB::table('cursos')->insert(['id' => 1, 'nombre' => '7° Básico', 'codigo' => '7B', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('pie_horas_apoyo_minimo')->insert([
            ['regimen_jec' => 'con_jec', 'neep_cantidad' => 1, 'total_crono_minutos' => 600, 'prof_educ_dif_minutos' => 360, 'pae_minutos' => 240, 'vigente' => true, 'created_at' => $now, 'updated_at' => $now],
            ['regimen_jec' => 'con_jec', 'neep_cantidad' => 4, 'total_crono_minutos' => 720, 'prof_educ_dif_minutos' => 480, 'pae_minutos' => 240, 'vigente' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('establecimiento_cursos')->insert([
            $this->curso(101, 1, 2026, 'A'), $this->curso(102, 1, 2026, 'B'), $this->curso(201, 1, 2027, 'A'),
            $this->curso(103, 2, 2026, 'A'), $this->curso(203, 2, 2027, 'A'), $this->curso(204, 2, 2027, 'B'),
        ]);
        DB::table('establecimiento_curso_pie')->insert([
            $this->pie(1, 1, 101, 2026, 5, 2), $this->pie(2, 1, 102, 2026, 3, 2), $this->pie(3, 2, 103, 2026, 5, 2),
        ]);
    }

    private function curso(int $id, int $establecimientoId, int $anio, string $letra): array
    {
        return ['id' => $id, 'establecimiento_id' => $establecimientoId, 'rbd' => 5000 + $establecimientoId, 'curso_id' => 1, 'anio' => $anio, 'letra' => $letra, 'nombre_seccion' => '7° Básico '.$letra, 'matricula' => 30, 'regimen_jec' => 'Con JEC', 'activo' => true, 'created_at' => now(), 'updated_at' => now()];
    }

    private function pie(int $id, int $establecimientoId, int $establecimientoCursoId, int $anio, int $neet, int $neep): array
    {
        return ['id' => $id, 'establecimiento_id' => $establecimientoId, 'establecimiento_curso_id' => $establecimientoCursoId, 'curso_id' => 1, 'anio' => $anio, 'rbd' => 5000 + $establecimientoId, 'necesidades_transitorias' => $neet, 'necesidades_permanentes' => $neep, 'total_pie' => $neet + $neep, 'estado' => 'validado', 'created_at' => now(), 'updated_at' => now()];
    }
}
