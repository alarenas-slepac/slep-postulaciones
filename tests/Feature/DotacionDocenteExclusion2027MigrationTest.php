<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DotacionDocenteExclusion2027MigrationTest extends TestCase
{
    public function test_copia_situaciones_de_2026_a_2027_sin_sobrescribir_las_existentes(): void
    {
        Schema::create('dotacion_docente_exclusiones', function (Blueprint $table): void {
            $table->id();
            $table->integer('establecimiento_id');
            $table->integer('anio');
            $table->string('docente_rut');
            $table->string('docente_rut_normalizado');
            $table->string('docente_nombre');
            $table->string('motivo');
            $table->decimal('horas', 8, 2);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
        });

        try {
            DB::table('dotacion_docente_exclusiones')->insert([
                [
                    'id' => 1, 'establecimiento_id' => 10, 'anio' => 2026,
                    'docente_rut' => '11.111.111-1', 'docente_rut_normalizado' => '111111111',
                    'docente_nombre' => 'Docente origen', 'motivo' => 'fuero_maternal', 'horas' => 22,
                    'created_by' => 5, 'updated_by' => 6, 'created_at' => now(), 'updated_at' => now(),
                ],
                [
                    'id' => 2, 'establecimiento_id' => 10, 'anio' => 2026,
                    'docente_rut' => '22.222.222-2', 'docente_rut_normalizado' => '222222222',
                    'docente_nombre' => 'Docente con actualización', 'motivo' => 'traslado', 'horas' => 10,
                    'created_by' => 7, 'updated_by' => 7, 'created_at' => now(), 'updated_at' => now(),
                ],
                [
                    'id' => 3, 'establecimiento_id' => 10, 'anio' => 2027,
                    'docente_rut' => '22.222.222-2', 'docente_rut_normalizado' => '222222222',
                    'docente_nombre' => 'Docente con actualización', 'motivo' => 'horas_gremiales', 'horas' => 6,
                    'created_by' => 9, 'updated_by' => 9, 'created_at' => now(), 'updated_at' => now(),
                ],
            ]);

            $migration = require database_path('migrations/2026_09_15_100000_copy_dotacion_docente_exclusiones_to_2027.php');
            $migration->up();
            $migration->up();

            $this->assertDatabaseHas('dotacion_docente_exclusiones', [
                'establecimiento_id' => 10,
                'anio' => 2027,
                'docente_rut_normalizado' => '111111111',
                'motivo' => 'fuero_maternal',
                'horas' => 22,
            ]);
            $this->assertDatabaseHas('dotacion_docente_exclusiones', [
                'establecimiento_id' => 10,
                'anio' => 2027,
                'docente_rut_normalizado' => '222222222',
                'motivo' => 'horas_gremiales',
                'horas' => 6,
            ]);
            $this->assertSame(2, DB::table('dotacion_docente_exclusiones')->where('anio', 2027)->count());
        } finally {
            Schema::dropIfExists('dotacion_docente_exclusiones');
        }
    }
}
