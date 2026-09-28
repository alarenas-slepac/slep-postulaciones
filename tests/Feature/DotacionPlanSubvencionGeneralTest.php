<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DotacionPlanSubvencionGeneralTest extends TestCase
{
    public function test_normaliza_solo_asignaciones_de_plan_existentes(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_asignacion');
            $table->string('subtipo_asignacion')->nullable();
            $table->string('subvencion')->nullable();
        });
        DB::table('dotacion_docente_asignaciones')->insert([
            ['tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'tiempo_minimo', 'subvencion' => 'SEP'],
            ['tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'libre_disposicion', 'subvencion' => 'Libre disposición'],
            ['tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'curso_combinado', 'subvencion' => null],
            ['tipo_asignacion' => 'pie_colaborativo', 'subtipo_asignacion' => null, 'subvencion' => 'PIE'],
        ]);

        $migration = require database_path('migrations/2026_09_28_130000_normalize_plan_estudio_subvencion_general.php');
        $migration->up();
        $migration->up();

        $this->assertSame(
            ['General', 'General', 'General', 'PIE'],
            DB::table('dotacion_docente_asignaciones')->orderBy('id')->pluck('subvencion')->all()
        );
    }
}
