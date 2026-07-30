<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('modules')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'declaracion')->value('id');
        if (!$moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'key' => 'declaracion',
                'name' => 'Declaración de Sostenedores',
                'section' => 'Operación',
                'icon' => 'bi bi-file-earmark-text',
                'sort' => 28,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('roles') && Schema::hasTable('module_role')) {
            $roleNames = ['admin', 'funcionario_estab'];
            $roleIds = DB::table('roles')->whereIn('name', $roleNames)->pluck('id');
            $hasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');
            foreach ($roleIds as $roleId) {
                $exists = DB::table('module_role')->where('module_id', $moduleId)->where('role_id', $roleId)->exists();
                if (!$exists) {
                    $payload = ['module_id' => $moduleId, 'role_id' => $roleId];
                    if ($hasTimestamps) {
                        $payload['created_at'] = now();
                        $payload['updated_at'] = now();
                    }
                    DB::table('module_role')->insert($payload);
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('module_role')) {
            return;
        }
        $moduleId = DB::table('modules')->where('key', 'declaracion')->value('id');
        if (!$moduleId) {
            return;
        }
        DB::table('module_role')->where('module_id', $moduleId)->delete();
        DB::table('modules')->where('id', $moduleId)->delete();
    }
};
