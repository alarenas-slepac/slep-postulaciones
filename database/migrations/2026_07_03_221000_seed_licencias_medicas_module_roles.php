<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $roles = [
            'digitador_licencias',
            'analista_licencias',
            'analista_smc',
            'administrador_licencias',
        ];

        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        if (!Schema::hasTable('modules') || !Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'tramites')->value('id');
        if (!$moduleId) {
            $payload = [
                'key' => 'tramites',
                'name' => 'Trámites',
                'section' => 'Trámites',
                'icon' => 'bi-folder-check',
                'sort' => 38,
            ];
            if (Schema::hasColumn('modules', 'created_at')) $payload['created_at'] = now();
            if (Schema::hasColumn('modules', 'updated_at')) $payload['updated_at'] = now();
            $moduleId = DB::table('modules')->insertGetId($payload);
        }

        $moduleRoleHasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');
        $authorizedRoles = array_merge(['admin', 'funcionario_slep', 'coordinador_gdp'], $roles);

        foreach ($authorizedRoles as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
            if (!$roleId) continue;

            $exists = DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->exists();

            if ($exists) continue;

            $payload = ['module_id' => $moduleId, 'role_id' => $roleId];
            if ($moduleRoleHasTimestamps) {
                $payload['created_at'] = now();
                $payload['updated_at'] = now();
            }
            DB::table('module_role')->insert($payload);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) return;

        $roles = ['digitador_licencias', 'analista_licencias', 'analista_smc', 'administrador_licencias'];
        $roleIds = DB::table('roles')->whereIn('name', $roles)->where('guard_name', 'web')->pluck('id');

        if (Schema::hasTable('module_role') && $roleIds->isNotEmpty()) {
            DB::table('module_role')->whereIn('role_id', $roleIds)->delete();
        }

        DB::table('roles')->whereIn('id', $roleIds)->delete();
    }
};
