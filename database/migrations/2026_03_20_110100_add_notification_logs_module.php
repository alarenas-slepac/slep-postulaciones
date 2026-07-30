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

        $moduleId = DB::table('modules')->where('key', 'admin.notification-logs')->value('id');
        if (!$moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'key' => 'admin.notification-logs',
                'name' => 'Historial de notificaciones',
                'section' => 'Administración',
                'icon' => null,
                'sort' => 75,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('roles') && Schema::hasTable('module_role')) {
            $roleId = DB::table('roles')->where('name', 'admin')->value('id');
            if ($roleId && !DB::table('module_role')->where('module_id', $moduleId)->where('role_id', $roleId)->exists()) {
                DB::table('module_role')->insert([
                    'module_id' => $moduleId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('modules') || !Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'admin.notification-logs')->value('id');
        if ($moduleId) {
            DB::table('module_role')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }
    }
};
