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

        $moduleId = DB::table('modules')->where('key', 'incumplimientos')->value('id');
        $roleId = DB::table('roles')->where('name', 'funcionario_slep')->value('id');

        if (!$moduleId || !$roleId) {
            return;
        }

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

    public function down(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('roles') || !Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'incumplimientos')->value('id');
        $roleId = DB::table('roles')->where('name', 'funcionario_slep')->value('id');

        if ($moduleId && $roleId) {
            DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->delete();
        }
    }
};
