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
        if (! Schema::hasTable('roles')) {
            return;
        }

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Role::findOrCreate('comunicaciones', 'web');

        if (! Schema::hasTable('modules') || ! Schema::hasTable('module_role')) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->where('key', 'admin.admision-escolar')->value('id');
        $modulePayload = [
            'name' => 'Admisión Escolar',
            'section' => 'Administración',
            'icon' => 'bi-buildings',
            'sort' => 15,
        ];

        if (Schema::hasColumn('modules', 'updated_at')) {
            $modulePayload['updated_at'] = $now;
        }

        if ($moduleId) {
            DB::table('modules')->where('id', $moduleId)->update($modulePayload);
        } else {
            $modulePayload['key'] = 'admin.admision-escolar';
            if (Schema::hasColumn('modules', 'created_at')) {
                $modulePayload['created_at'] = $now;
            }
            $moduleId = DB::table('modules')->insertGetId($modulePayload);
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['admin', 'coordinador_uatp', 'comunicaciones'])
            ->pluck('id');

        $pivotHasTimestamps = Schema::hasColumn('module_role', 'created_at')
            && Schema::hasColumn('module_role', 'updated_at');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->exists();

            if ($exists) {
                continue;
            }

            $payload = [
                'module_id' => $moduleId,
                'role_id' => $roleId,
            ];

            if ($pivotHasTimestamps) {
                $payload['created_at'] = $now;
                $payload['updated_at'] = $now;
            }

            DB::table('module_role')->insert($payload);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modules')) {
            $moduleId = DB::table('modules')->where('key', 'admin.admision-escolar')->value('id');
            if ($moduleId) {
                if (Schema::hasTable('module_role')) {
                    DB::table('module_role')->where('module_id', $moduleId)->delete();
                }
                DB::table('modules')->where('id', $moduleId)->delete();
            }
        }

        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', 'comunicaciones')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $roleId) {
            return;
        }

        $assigned = Schema::hasTable('model_has_roles')
            && DB::table('model_has_roles')->where('role_id', $roleId)->exists();

        if (! $assigned) {
            if (Schema::hasTable('module_role')) {
                DB::table('module_role')->where('role_id', $roleId)->delete();
            }
            if (Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            }
            DB::table('roles')->where('id', $roleId)->delete();
        }

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
