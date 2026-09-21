<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (Schema::hasTable('dotacion_funciones_reglas')) {
            DB::table('dotacion_funciones_reglas')->updateOrInsert(
                ['codigo' => 'director_adp'],
                [
                    'categoria' => 'directiva',
                    'nombre' => 'Director(a) ADP',
                    'tipo_regla' => 'director_adp',
                    'horas_fijas' => 44,
                    'horas_minimas' => null,
                    'horas_maximas' => null,
                    'umbral_matricula' => null,
                    'horas_bajo_umbral' => null,
                    'horas_sobre_umbral' => null,
                    'permite_multiples' => false,
                    'declarable' => false,
                    'obligatoria' => false,
                    'requiere_validacion' => true,
                    'fundamento' => 'Función directiva normativa para establecimientos cuyo Director(a) ADP haya sido habilitado por la administración competente.',
                    'vigente' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if (! Schema::hasTable('modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'admin.dotacion-funciones')->value('id');
        $roleId = DB::table('roles')->where('name', 'supervisor_plani')->value('id');
        if (! $moduleId || ! $roleId) {
            return;
        }

        $values = [];
        if (Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at')) {
            $values = ['created_at' => $now, 'updated_at' => $now];
        }

        DB::table('module_role')->updateOrInsert([
            'module_id' => $moduleId,
            'role_id' => $roleId,
        ], $values);
    }

    public function down(): void
    {
        if (Schema::hasTable('dotacion_funciones_reglas')) {
            DB::table('dotacion_funciones_reglas')
                ->where('codigo', 'director_adp')
                ->delete();
        }

        if (! Schema::hasTable('modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'admin.dotacion-funciones')->value('id');
        $roleId = DB::table('roles')->where('name', 'supervisor_plani')->value('id');
        if (! $moduleId || ! $roleId) {
            return;
        }

        DB::table('module_role')
            ->where('module_id', $moduleId)
            ->where('role_id', $roleId)
            ->delete();
    }
};
