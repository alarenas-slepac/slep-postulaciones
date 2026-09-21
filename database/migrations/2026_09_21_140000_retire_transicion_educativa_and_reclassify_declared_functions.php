<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_funciones_reglas')) {
            $values = ['vigente' => false];
            if (Schema::hasColumn('dotacion_funciones_reglas', 'updated_at')) {
                $values['updated_at'] = now();
            }

            DB::table('dotacion_funciones_reglas')
                ->where('codigo', 'transicion_educativa')
                ->update($values);
        }

        if (! Schema::hasTable('dotacion_funciones_establecimiento')) {
            return;
        }

        $funcionesTecnicoPedagogicas = DB::table('dotacion_funciones_establecimiento')
            ->where('categoria', 'tecnico_pedagogica')
            ->pluck('id');

        if ($funcionesTecnicoPedagogicas->isEmpty()) {
            return;
        }

        $values = ['categoria' => 'otras_funciones_docentes'];
        if (Schema::hasColumn('dotacion_funciones_establecimiento', 'updated_at')) {
            $values['updated_at'] = now();
        }
        DB::table('dotacion_funciones_establecimiento')
            ->whereIn('id', $funcionesTecnicoPedagogicas)
            ->update($values);

        if (! Schema::hasTable('dotacion_docente_asignaciones')
            || ! Schema::hasColumn('dotacion_docente_asignaciones', 'dotacion_funcion_id')) {
            return;
        }

        foreach ($funcionesTecnicoPedagogicas->chunk(500) as $ids) {
            $asignaciones = [
                'tipo_asignacion' => 'otra_funcion',
                'subtipo_asignacion' => 'otras_funciones_docentes',
            ];
            if (Schema::hasColumn('dotacion_docente_asignaciones', 'updated_at')) {
                $asignaciones['updated_at'] = now();
            }

            DB::table('dotacion_docente_asignaciones')
                ->whereIn('dotacion_funcion_id', $ids->all())
                ->update($asignaciones);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dotacion_funciones_reglas')) {
            DB::table('dotacion_funciones_reglas')
                ->where('codigo', 'transicion_educativa')
                ->update(['vigente' => true]);
        }
    }
};
