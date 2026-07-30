<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('roles') || !Schema::hasTable('module_role')) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->where('key', 'gestion.bolsa-trabajo')->value('id');

        if (!$moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'key' => 'gestion.bolsa-trabajo',
                'name' => 'Bolsa de Trabajo',
                'section' => 'Operación',
                'icon' => 'bi-briefcase',
                'sort' => 58,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('modules')->where('id', $moduleId)->update([
                'name' => 'Bolsa de Trabajo',
                'section' => 'Operación',
                'icon' => 'bi-briefcase',
                'sort' => 58,
                'updated_at' => $now,
            ]);
        }

        $hasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');

        foreach (['admin', 'funcionario_slep'] as $roleName) {
            $roleId = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if (!$roleId) {
                continue;
            }

            $exists = DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->exists();

            if (!$exists) {
                $payload = [
                    'module_id' => $moduleId,
                    'role_id' => $roleId,
                ];

                if ($hasTimestamps) {
                    $payload['created_at'] = $now;
                    $payload['updated_at'] = $now;
                }

                DB::table('module_role')->insert($payload);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('roles') || !Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'gestion.bolsa-trabajo')->value('id');

        if (!$moduleId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'funcionario_slep'])
            ->where('guard_name', 'web')
            ->pluck('id')
            ->all();

        if (!empty($roleIds)) {
            DB::table('module_role')
                ->where('module_id', $moduleId)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }
    }
};
