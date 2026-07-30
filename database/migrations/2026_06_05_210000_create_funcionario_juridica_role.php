<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $role = Role::findOrCreate('funcionario_juridica', 'web');

        if (Schema::hasTable('modules') && Schema::hasTable('module_role')) {
            $moduleId = DB::table('modules')->where('key', 'tramites')->value('id');

            if ($moduleId) {
                $exists = DB::table('module_role')
                    ->where('module_id', $moduleId)
                    ->where('role_id', $role->id)
                    ->exists();

                if (!$exists) {
                    DB::table('module_role')->insert([
                        'module_id' => $moduleId,
                        'role_id' => $role->id,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', 'funcionario_juridica')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$roleId) {
            return;
        }

        if (Schema::hasTable('model_has_roles')) {
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();
        }

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
        }

        if (Schema::hasTable('module_role')) {
            DB::table('module_role')->where('role_id', $roleId)->delete();
        }

        DB::table('roles')->where('id', $roleId)->delete();
    }
};
