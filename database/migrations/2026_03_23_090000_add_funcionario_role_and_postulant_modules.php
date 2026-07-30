<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $funcionarioRoleId = DB::table('roles')->where('name', 'funcionario')->where('guard_name', 'web')->value('id');
        if (!$funcionarioRoleId) {
            $funcionarioRoleId = DB::table('roles')->insertGetId([
                'name' => 'funcionario',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!Schema::hasTable('module_role')) {
            return;
        }

        $moduleRoleHasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');

        $postulanteRoleId = DB::table('roles')->where('name', 'postulante')->where('guard_name', 'web')->value('id');
        if (!$postulanteRoleId) {
            return;
        }

        $moduleIds = DB::table('module_role')
            ->where('role_id', $postulanteRoleId)
            ->pluck('module_id')
            ->unique()
            ->values();

        if ($moduleIds->isEmpty() && Schema::hasTable('modules')) {
            $moduleIds = DB::table('modules')
                ->whereIn('key', ['postulant.profile', 'postulant.documents', 'postulant.reemplazos', 'messages'])
                ->pluck('id')
                ->unique()
                ->values();
        }

        foreach ($moduleIds as $moduleId) {
            $exists = DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $funcionarioRoleId)
                ->exists();

            if (!$exists) {
                $payload = [
                    'module_id' => $moduleId,
                    'role_id' => $funcionarioRoleId,
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
        if (!Schema::hasTable('roles') || !Schema::hasTable('module_role')) {
            return;
        }

        $funcionarioRoleId = DB::table('roles')->where('name', 'funcionario')->where('guard_name', 'web')->value('id');
        $postulanteRoleId = DB::table('roles')->where('name', 'postulante')->where('guard_name', 'web')->value('id');

        if (!$funcionarioRoleId || !$postulanteRoleId) {
            return;
        }

        $moduleIds = DB::table('module_role')
            ->where('role_id', $postulanteRoleId)
            ->pluck('module_id')
            ->unique()
            ->values();

        if ($moduleIds->isEmpty() && Schema::hasTable('modules')) {
            $moduleIds = DB::table('modules')
                ->whereIn('key', ['postulant.profile', 'postulant.documents', 'postulant.reemplazos', 'messages'])
                ->pluck('id')
                ->unique()
                ->values();
        }

        if ($moduleIds->isNotEmpty()) {
            DB::table('module_role')
                ->where('role_id', $funcionarioRoleId)
                ->whereIn('module_id', $moduleIds->all())
                ->delete();
        }
    }
};
