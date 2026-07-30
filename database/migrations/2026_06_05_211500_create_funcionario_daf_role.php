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

        $role = Role::findOrCreate('funcionario_daf', 'web');

        if (!Schema::hasTable('modules') || !Schema::hasTable('module_role')) {
            return;
        }

        $moduleKeys = [
            'tramites',
            'admin.viaticos-reembolsos',
        ];

        $moduleIds = DB::table('modules')
            ->whereIn('key', $moduleKeys)
            ->pluck('id');

        $hasTimestamps = Schema::hasColumn('module_role', 'created_at')
            && Schema::hasColumn('module_role', 'updated_at');

        foreach ($moduleIds as $moduleId) {
            $exists = DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $role->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $payload = [
                'module_id' => $moduleId,
                'role_id' => $role->id,
            ];

            if ($hasTimestamps) {
                $payload['created_at'] = now();
                $payload['updated_at'] = now();
            }

            DB::table('module_role')->insert($payload);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', 'funcionario_daf')
            ->where('guard_name', 'web')
            ->value('id');

        if (!$roleId) {
            return;
        }

        if (Schema::hasTable('module_role')) {
            DB::table('module_role')->where('role_id', $roleId)->delete();
        }

        DB::table('roles')->where('id', $roleId)->delete();
    }
};
