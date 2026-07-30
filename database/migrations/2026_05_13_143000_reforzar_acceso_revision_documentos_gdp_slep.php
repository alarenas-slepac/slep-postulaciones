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

        $moduleId = DB::table('modules')->where('key', 'admin.documents')->value('id');
        if (!$moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'key' => 'admin.documents',
                'name' => 'Revisión documental',
                'section' => 'Revisión',
                'icon' => null,
                'sort' => 65,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['funcionario_slep', 'coordinador_gdp'])
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

    public function down(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('roles') || !Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'admin.documents')->value('id');
        if (!$moduleId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['funcionario_slep', 'coordinador_gdp'])
            ->pluck('id');

        if ($roleIds->isNotEmpty()) {
            DB::table('module_role')
                ->where('module_id', $moduleId)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }
    }
};
