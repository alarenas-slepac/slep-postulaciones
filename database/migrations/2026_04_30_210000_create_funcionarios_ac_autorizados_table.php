<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('funcionarios_ac_autorizados')) {
            Schema::create('funcionarios_ac_autorizados', function (Blueprint $table) {
                $table->id();
                $table->string('periodo_nomina', 20)->nullable();
                $table->string('accion_sistema', 80)->default('autorizar_y_asignar_si_existe');
                $table->string('run', 20);
                $table->string('dv', 2);
                $table->string('rut_normalizado', 30)->unique();
                $table->string('apellido_paterno', 120)->nullable();
                $table->string('apellido_materno', 120)->nullable();
                $table->string('nombres', 180)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->string('unidad_departamento', 190)->nullable();
                $table->string('cargo_funcion', 190)->nullable();
                $table->string('comuna', 120)->nullable();
                $table->string('calidad_juridica', 80)->nullable();
                $table->string('estado_autorizacion', 30)->default('activo')->index();
                $table->date('fecha_inicio_autorizacion')->nullable();
                $table->date('fecha_fin_autorizacion')->nullable();
                $table->boolean('enviar_notificacion')->default(false);
                $table->text('observaciones')->nullable();
                $table->foreignId('registered_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('registered_at')->nullable();
                $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('imported_at')->nullable();
                $table->text('last_import_message')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('roles')) {
            $roleId = DB::table('roles')
                ->where('name', 'funcionario_ac')
                ->where('guard_name', 'web')
                ->value('id');

            if (!$roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => 'funcionario_ac',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('modules') && Schema::hasTable('module_role')) {
                $moduleId = DB::table('modules')->where('key', 'tramites')->value('id');

                if ($moduleId) {
                    $exists = DB::table('module_role')
                        ->where('module_id', $moduleId)
                        ->where('role_id', $roleId)
                        ->exists();

                    if (!$exists) {
                        DB::table('module_role')->insert([
                            'module_id' => $moduleId,
                            'role_id' => $roleId,
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionarios_ac_autorizados');

        if (Schema::hasTable('roles')) {
            $roleId = DB::table('roles')->where('name', 'funcionario_ac')->where('guard_name', 'web')->value('id');
            if ($roleId && Schema::hasTable('module_role')) {
                DB::table('module_role')->where('role_id', $roleId)->delete();
            }
        }
    }
};
