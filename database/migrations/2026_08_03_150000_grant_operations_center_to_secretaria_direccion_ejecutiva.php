<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const MODULE_KEY = 'centro-operaciones';

    private const ROLE_NAME = 'secretaria_direccion_ejecutiva';

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->forgetPermissionCache();
        $role = Role::findOrCreate(self::ROLE_NAME, 'web');

        if (! Schema::hasTable('modules') || ! Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', self::MODULE_KEY)->value('id');

        if (! $moduleId) {
            return;
        }

        $payload = [
            'module_id' => $moduleId,
            'role_id' => $role->id,
        ];

        if (Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at')) {
            $payload['created_at'] = now();
            $payload['updated_at'] = now();
        }

        DB::table('module_role')->insertOrIgnore($payload);
        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', self::MODULE_KEY)->value('id');
        $roleId = DB::table('roles')
            ->where('name', self::ROLE_NAME)
            ->where('guard_name', 'web')
            ->value('id');

        if ($moduleId && $roleId) {
            DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->delete();
        }

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
