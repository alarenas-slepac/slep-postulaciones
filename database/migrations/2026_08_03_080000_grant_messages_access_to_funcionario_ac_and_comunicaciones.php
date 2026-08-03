<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROLE_NAMES = [
        'funcionario_ac',
        'comunicaciones',
    ];

    public function up(): void
    {
        if (
            ! Schema::hasTable('modules')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('module_role')
        ) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'messages')->value('id');

        if (! $moduleId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', self::ROLE_NAMES)
            ->pluck('id');

        $pivotHasTimestamps = Schema::hasColumn('module_role', 'created_at')
            && Schema::hasColumn('module_role', 'updated_at');
        $now = now();

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

    public function down(): void
    {
        if (
            ! Schema::hasTable('modules')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('module_role')
        ) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'messages')->value('id');
        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', self::ROLE_NAMES)
            ->pluck('id');

        if ($moduleId && $roleIds->isNotEmpty()) {
            DB::table('module_role')
                ->where('module_id', $moduleId)
                ->whereIn('role_id', $roleIds->all())
                ->delete();
        }
    }
};
