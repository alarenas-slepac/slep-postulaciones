<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const MODULE_ROLES = [
        'centro-operaciones' => ['comunicaciones', 'gabinete_slep'],
        'messages' => ['gabinete_slep'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->forgetPermissionCache();

        Role::findOrCreate('comunicaciones', 'web');
        Role::findOrCreate('gabinete_slep', 'web');

        if (! Schema::hasTable('modules') || ! Schema::hasTable('module_role')) {
            return;
        }

        $pivotHasTimestamps = Schema::hasColumn('module_role', 'created_at')
            && Schema::hasColumn('module_role', 'updated_at');
        $now = now();

        foreach (self::MODULE_ROLES as $moduleKey => $roleNames) {
            $moduleId = DB::table('modules')->where('key', $moduleKey)->value('id');

            if (! $moduleId) {
                continue;
            }

            $roleIds = DB::table('roles')
                ->where('guard_name', 'web')
                ->whereIn('name', $roleNames)
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                $payload = [
                    'module_id' => $moduleId,
                    'role_id' => $roleId,
                ];

                if ($pivotHasTimestamps) {
                    $payload['created_at'] = $now;
                    $payload['updated_at'] = $now;
                }

                DB::table('module_role')->insertOrIgnore($payload);
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (Schema::hasTable('modules') && Schema::hasTable('roles') && Schema::hasTable('module_role')) {
            foreach (self::MODULE_ROLES as $moduleKey => $roleNames) {
                $moduleId = DB::table('modules')->where('key', $moduleKey)->value('id');
                $roleIds = DB::table('roles')
                    ->where('guard_name', 'web')
                    ->whereIn('name', $roleNames)
                    ->pluck('id');

                if ($moduleId && $roleIds->isNotEmpty()) {
                    DB::table('module_role')
                        ->where('module_id', $moduleId)
                        ->whereIn('role_id', $roleIds->all())
                        ->delete();
                }
            }
        }

        $this->deleteGabineteRoleIfUnused();
        $this->forgetPermissionCache();
    }

    private function deleteGabineteRoleIfUnused(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', 'gabinete_slep')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $roleId) {
            return;
        }

        $hasUsers = Schema::hasTable('model_has_roles')
            && DB::table('model_has_roles')->where('role_id', $roleId)->exists();
        $hasModules = Schema::hasTable('module_role')
            && DB::table('module_role')->where('role_id', $roleId)->exists();
        $hasPermissions = Schema::hasTable('role_has_permissions')
            && DB::table('role_has_permissions')->where('role_id', $roleId)->exists();

        if (! $hasUsers && ! $hasModules && ! $hasPermissions) {
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
