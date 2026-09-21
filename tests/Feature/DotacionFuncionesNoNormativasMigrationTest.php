<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DotacionFuncionesNoNormativasMigrationTest extends TestCase
{
    public function test_retira_transicion_y_reclasifica_funciones_declaradas_y_sus_asignaciones(): void
    {
        Schema::create('dotacion_funciones_reglas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->boolean('vigente')->default(true);
        });
        Schema::create('dotacion_funciones_establecimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('categoria');
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('dotacion_funcion_id')->nullable();
            $table->string('tipo_asignacion');
            $table->string('subtipo_asignacion')->nullable();
        });

        try {
            DB::table('dotacion_funciones_reglas')->insert(['codigo' => 'transicion_educativa', 'vigente' => true]);
            DB::table('dotacion_funciones_establecimiento')->insert(['id' => 44, 'categoria' => 'tecnico_pedagogica']);
            DB::table('dotacion_docente_asignaciones')->insert([
                'dotacion_funcion_id' => 44,
                'tipo_asignacion' => 'funcion_tecnico_pedagogica',
                'subtipo_asignacion' => 'tecnico_pedagogica',
            ]);

            $migration = require database_path('migrations/2026_09_21_140000_retire_transicion_educativa_and_reclassify_declared_functions.php');
            $migration->up();

            $this->assertDatabaseHas('dotacion_funciones_reglas', ['codigo' => 'transicion_educativa', 'vigente' => false]);
            $this->assertDatabaseHas('dotacion_funciones_establecimiento', ['id' => 44, 'categoria' => 'otras_funciones_docentes']);
            $this->assertDatabaseHas('dotacion_docente_asignaciones', [
                'dotacion_funcion_id' => 44,
                'tipo_asignacion' => 'otra_funcion',
                'subtipo_asignacion' => 'otras_funciones_docentes',
            ]);
        } finally {
            Schema::dropIfExists('dotacion_docente_asignaciones');
            Schema::dropIfExists('dotacion_funciones_establecimiento');
            Schema::dropIfExists('dotacion_funciones_reglas');
        }
    }
}
