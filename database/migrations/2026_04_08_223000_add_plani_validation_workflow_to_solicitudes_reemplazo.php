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
        if (Schema::hasTable('solicitudes_reemplazo')) {
            Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
                if (!Schema::hasColumn('solicitudes_reemplazo', 'justificacion_tecnica_uatp')) {
                    $table->text('justificacion_tecnica_uatp')->nullable()->after('motivo_rechazo');
                }
                if (!Schema::hasColumn('solicitudes_reemplazo', 'plani_motivo_rechazo')) {
                    $table->text('plani_motivo_rechazo')->nullable()->after('justificacion_tecnica_uatp');
                }
                if (!Schema::hasColumn('solicitudes_reemplazo', 'plani_decision_user_id')) {
                    $table->unsignedBigInteger('plani_decision_user_id')->nullable()->after('plani_motivo_rechazo');
                }
                if (!Schema::hasColumn('solicitudes_reemplazo', 'plani_decision_at')) {
                    $table->timestamp('plani_decision_at')->nullable()->after('plani_decision_user_id');
                }
            });
        }

        if (Schema::hasTable('roles')) {
            $exists = DB::table('roles')->where('name', 'supervisor_plani')->where('guard_name', 'web')->exists();
            if (!$exists) {
                DB::table('roles')->insert([
                    'name' => 'supervisor_plani',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('modules') && Schema::hasTable('module_role')) {
            $roleId = DB::table('roles')->where('name', 'supervisor_plani')->where('guard_name', 'web')->value('id');
            $moduleIds = DB::table('modules')
                ->whereIn('key', ['gestion.solicitudes-reemplazo', 'messages'])
                ->pluck('id');

            $hasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');

            foreach ($moduleIds as $moduleId) {
                $exists = DB::table('module_role')
                    ->where('module_id', $moduleId)
                    ->where('role_id', $roleId)
                    ->exists();

                if (!$exists && $roleId) {
                    $payload = [
                        'module_id' => $moduleId,
                        'role_id' => $roleId,
                    ];

                    if ($hasTimestamps) {
                        $payload['created_at'] = now();
                        $payload['updated_at'] = now();
                    }

                    DB::table('module_role')->insert($payload);
                }
            }
        }

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solicitudes_reemplazo')) {
            Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
                foreach (['justificacion_tecnica_uatp', 'plani_motivo_rechazo', 'plani_decision_user_id', 'plani_decision_at'] as $column) {
                    if (Schema::hasColumn('solicitudes_reemplazo', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('roles') && Schema::hasTable('modules') && Schema::hasTable('module_role')) {
            $roleId = DB::table('roles')->where('name', 'supervisor_plani')->where('guard_name', 'web')->value('id');
            $moduleIds = DB::table('modules')
                ->whereIn('key', ['gestion.solicitudes-reemplazo', 'messages'])
                ->pluck('id');

            if ($roleId && $moduleIds->isNotEmpty()) {
                DB::table('module_role')
                    ->where('role_id', $roleId)
                    ->whereIn('module_id', $moduleIds->all())
                    ->delete();
            }
        }

        if (Schema::hasTable('roles')) {
            DB::table('roles')->where('name', 'supervisor_plani')->where('guard_name', 'web')->delete();
        }

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
