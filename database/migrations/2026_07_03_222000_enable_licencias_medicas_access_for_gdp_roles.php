<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('modules') || !Schema::hasTable('module_role')) {
            return;
        }

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $authorizedRoles = ['admin', 'funcionario_slep', 'coordinador_gdp'];

        $moduleIds = [];
        foreach ($this->modules() as $module) {
            $moduleIds[] = $this->upsertModule($module);
        }

        $moduleRoleHasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');

        foreach ($authorizedRoles as $roleName) {
            $roleId = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if (!$roleId) {
                continue;
            }

            foreach ($moduleIds as $moduleId) {
                if (!$moduleId) {
                    continue;
                }

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

                if ($moduleRoleHasTimestamps) {
                    $payload['created_at'] = now();
                    $payload['updated_at'] = now();
                }

                DB::table('module_role')->insert($payload);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('modules') || !Schema::hasTable('module_role')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'funcionario_slep', 'coordinador_gdp'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $moduleIds = DB::table('modules')
            ->whereIn('key', ['tramites', 'tramites.licencias-medicas'])
            ->pluck('id');

        if ($roleIds->isNotEmpty() && $moduleIds->isNotEmpty()) {
            DB::table('module_role')
                ->whereIn('role_id', $roleIds)
                ->whereIn('module_id', $moduleIds)
                ->delete();
        }
    }

    private function modules(): array
    {
        return [
            [
                'key' => 'tramites',
                'name' => 'Trámites',
                'section' => 'Trámites',
                'icon' => 'bi-folder-check',
                'sort' => 38,
            ],
            [
                'key' => 'tramites.licencias-medicas',
                'name' => 'Licencias médicas',
                'section' => 'Trámites',
                'icon' => 'bi-heart-pulse',
                'sort' => 39,
            ],
        ];
    }

    private function upsertModule(array $data): ?int
    {
        $existingId = DB::table('modules')->where('key', $data['key'])->value('id');

        $payload = [];
        foreach ($data as $column => $value) {
            if (Schema::hasColumn('modules', $column)) {
                $payload[$column] = $value;
            }
        }

        if (Schema::hasColumn('modules', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        if ($existingId) {
            DB::table('modules')->where('id', $existingId)->update($payload);
            return (int) $existingId;
        }

        if (Schema::hasColumn('modules', 'created_at')) {
            $payload['created_at'] = now();
        }

        return (int) DB::table('modules')->insertGetId($payload);
    }
};
