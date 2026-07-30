<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('incumplimientos_laborales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
            $table->foreignId('reemplazo_personal_id')->nullable()->constrained('reemplazos_personal')->nullOnDelete();
            $table->string('funcionario_rut', 32);
            $table->string('funcionario_nombre');
            $table->unsignedInteger('funcionario_rbd')->nullable();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->unsignedInteger('dias')->default(0);
            $table->unsignedTinyInteger('horas')->default(0);
            $table->unsignedTinyInteger('minutos')->default(0);
            $table->foreignId('informado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['establecimiento_id', 'fecha_desde']);
            $table->index(['funcionario_rut', 'fecha_desde']);
            $table->index(['fecha_desde', 'fecha_hasta']);
        });

        if (Schema::hasTable('modules')) {
            $moduleId = DB::table('modules')->where('key', 'incumplimientos')->value('id');
            if (!$moduleId) {
                $moduleId = DB::table('modules')->insertGetId([
                    'key' => 'incumplimientos',
                    'name' => 'Incumplimiento Laboral',
                    'section' => 'Operación',
                    'icon' => null,
                    'sort' => 35,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('roles') && Schema::hasTable('module_role')) {
                $roleIds = DB::table('roles')
                    ->whereIn('name', ['admin', 'funcionario_estab'])
                    ->pluck('id');

                foreach ($roleIds as $roleId) {
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
        if (Schema::hasTable('modules') && Schema::hasTable('module_role')) {
            $moduleId = DB::table('modules')->where('key', 'incumplimientos')->value('id');
            if ($moduleId) {
                DB::table('module_role')->where('module_id', $moduleId)->delete();
                DB::table('modules')->where('id', $moduleId)->delete();
            }
        }

        Schema::dropIfExists('incumplimientos_laborales');
    }
};
