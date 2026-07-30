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
        if (!Schema::hasTable('permiso_sin_goce_excepciones')) {
            Schema::create('permiso_sin_goce_excepciones', function (Blueprint $table) {
                $table->id();
                $table->string('rut_normalizado', 20)->unique();
                $table->string('rut_original', 30)->nullable();
                $table->string('nombre_titular')->nullable();
                $table->text('observacion')->nullable();
                $table->boolean('activo')->default(true)->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['rut_normalizado', 'activo'], 'psg_excepciones_rut_activo_idx');
            });
        }

        if (!Schema::hasTable('modules') || !Schema::hasTable('module_role') || !Schema::hasTable('roles')) {
            return;
        }

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $moduleRoleHasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');
        $now = now();

        $moduleId = DB::table('modules')->where('key', 'admin.permiso-sin-goce-excepciones')->value('id');
        if (!$moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'key' => 'admin.permiso-sin-goce-excepciones',
                'name' => 'Excepciones permiso sin goce',
                'section' => 'Operación',
                'icon' => 'bi-person-check',
                'sort' => 22,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('modules')->where('id', $moduleId)->update([
                'name' => 'Excepciones permiso sin goce',
                'section' => 'Operación',
                'icon' => 'bi-person-check',
                'sort' => 22,
                'updated_at' => $now,
            ]);
        }

        foreach (['admin', 'coordinador_gdp'] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
            if (!$roleId) {
                continue;
            }

            $exists = DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->exists();

            if (!$exists) {
                $payload = [
                    'module_id' => $moduleId,
                    'role_id' => $roleId,
                ];

                if ($moduleRoleHasTimestamps) {
                    $payload['created_at'] = $now;
                    $payload['updated_at'] = $now;
                }

                DB::table('module_role')->insert($payload);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permiso_sin_goce_excepciones');

        if (!Schema::hasTable('modules') || !Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'admin.permiso-sin-goce-excepciones')->value('id');
        if ($moduleId) {
            DB::table('module_role')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }
    }
};
