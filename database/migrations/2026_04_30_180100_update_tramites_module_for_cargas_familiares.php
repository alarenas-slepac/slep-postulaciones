<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('modules')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'tramites')->value('id');
        if (!$moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'key' => 'tramites',
                'name' => 'Trámites',
                'section' => 'Postulación',
                'icon' => 'bi bi-folder-check',
                'sort' => 38,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('roles') && Schema::hasTable('module_role')) {
            $roleIds = DB::table('roles')
                ->whereIn('name', ['postulante', 'funcionario', 'coordinador_gdp', 'funcionario_slep'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                $exists = DB::table('module_role')
                    ->where('module_id', $moduleId)
                    ->where('role_id', $roleId)
                    ->exists();

                if (!$exists) {
                    DB::table('module_role')->insert([
                        'module_id' => $moduleId,
                        'role_id' => $roleId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Conservador: no se revocan permisos existentes del módulo tramites.
    }
};
