<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('roles') || !Schema::hasTable('module_role')) {
            return;
        }

        DB::table('modules')->updateOrInsert(
            ['key' => 'admin.establecimiento-planes'],
            [
                'name' => 'Configuración de Planes por Establecimiento',
                'section' => 'Catálogos',
                'icon' => null,
                'sort' => 30,
                'updated_at' => now(),
            ]
        );

        $moduleId = DB::table('modules')->where('key', 'admin.establecimiento-planes')->value('id');
        if (!$moduleId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'funcionario_directivo_estab'])
            ->pluck('id');

        $hasTimestamps = Schema::hasColumn('module_role', 'created_at')
            && Schema::hasColumn('module_role', 'updated_at');

        foreach ($roleIds as $roleId) {
            $payload = [
                'module_id' => $moduleId,
                'role_id' => $roleId,
            ];

            $values = [];
            if ($hasTimestamps) {
                $values = [
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('module_role')->updateOrInsert($payload, $values);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('roles') || !Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'admin.establecimiento-planes')->value('id');
        $roleId = DB::table('roles')->where('name', 'funcionario_directivo_estab')->value('id');

        if ($moduleId && $roleId) {
            DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->delete();
        }
    }
};
