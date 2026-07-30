<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('funcionarios_viatico_anexo')) {
            return;
        }

        Schema::create('funcionarios_viatico_anexo', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 32);
            $table->string('rut_body', 16)->unique();
            $table->string('rut_dv', 1);
            $table->string('nombre_completo')->nullable();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
            $table->string('establecimiento_nombre')->nullable();
            $table->string('estamento')->nullable();
            $table->string('cargo_funcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->text('observacion')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_at')->nullable();
            $table->timestamps();

            $table->index(['activo', 'rut_body'], 'funcionarios_viatico_anexo_activo_rut_index');
            $table->index(['establecimiento_id', 'activo'], 'funcionarios_viatico_anexo_estab_activo_index');
        });


        if (Schema::hasTable('modules')) {
            DB::table('modules')->updateOrInsert(
                ['key' => 'admin.funcionarios-viatico-anexo'],
                [
                    'name' => 'Funcionarios viático por anexo',
                    'section' => 'Catálogos',
                    'icon' => null,
                    'sort' => 24,
                    'updated_at' => now(),
                ]
            );
            $moduleId = DB::table('modules')->where('key', 'admin.funcionarios-viatico-anexo')->value('id');

            if ($moduleId && Schema::hasTable('roles') && Schema::hasTable('module_role')) {
                $roleIds = DB::table('roles')
                    ->whereIn('name', ['admin', 'supervisor_plani', 'coordinador_plani'])
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
                    DB::table('module_role')->updateOrInsert([
                        'module_id' => $moduleId,
                        'role_id' => $roleId,
                    ], []);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modules')) {
            $moduleId = DB::table('modules')->where('key', 'admin.funcionarios-viatico-anexo')->value('id');
            if ($moduleId && Schema::hasTable('module_role')) {
                DB::table('module_role')->where('module_id', $moduleId)->delete();
            }
            if ($moduleId) {
                DB::table('modules')->where('id', $moduleId)->delete();
            }
        }

        Schema::dropIfExists('funcionarios_viatico_anexo');
    }
};
