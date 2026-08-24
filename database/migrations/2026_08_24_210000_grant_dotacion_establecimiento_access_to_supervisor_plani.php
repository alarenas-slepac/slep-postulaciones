<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'admin.dotacion-establecimiento')->value('id');
        $roleId = DB::table('roles')->where('name', 'supervisor_plani')->value('id');
        if (! $moduleId || ! $roleId) {
            return;
        }

        $values = [];
        if (Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at')) {
            $values = ['created_at' => now(), 'updated_at' => now()];
        }

        DB::table('module_role')->updateOrInsert([
            'module_id' => $moduleId,
            'role_id' => $roleId,
        ], $values);
    }

    public function down(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'admin.dotacion-establecimiento')->value('id');
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
