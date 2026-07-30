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
        if (!Schema::hasTable('bolsa_trabajo_ofertas')) {
            Schema::create('bolsa_trabajo_ofertas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->nullOnDelete();
                $table->string('comuna', 120);
                $table->string('estamento', 20);
                $table->foreignId('area_desempeno_id')->nullable()->constrained('areas_desempeno')->nullOnDelete();
                $table->string('calidad_contractual', 30);
                $table->unsignedSmallInteger('cantidad_horas');
                $table->date('inicio_trabajo_aproximado');
                $table->date('fecha_inicio_postulaciones');
                $table->time('hora_inicio_postulaciones');
                $table->date('fecha_termino_postulaciones');
                $table->time('hora_termino_postulaciones');
                $table->string('correo_contacto', 190);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['estamento', 'comuna'], 'bt_ofertas_estamento_comuna_idx');
            });
        }

        if (!Schema::hasTable('bolsa_trabajo_postulaciones')) {
            Schema::create('bolsa_trabajo_postulaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bolsa_trabajo_oferta_id')->constrained('bolsa_trabajo_ofertas')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('postulant_profile_id')->nullable()->constrained('postulant_profiles')->nullOnDelete();
                $table->string('estado', 30)->default('postulado');
                $table->text('observacion')->nullable();
                $table->timestamps();
                $table->unique(['bolsa_trabajo_oferta_id', 'user_id'], 'bt_postulaciones_oferta_user_unique');
            });
        }

        if (!Schema::hasTable('modules') || !Schema::hasTable('module_role') || !Schema::hasTable('roles')) {
            return;
        }

        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $moduleRoleHasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');

        $modules = [
            'gestion.bolsa-trabajo' => [
                'name' => 'Bolsa de Trabajo',
                'section' => 'Operación',
                'icon' => 'bi-briefcase',
                'sort' => 58,
                'roles' => ['admin', 'funcionario_slep'],
            ],
            'postulant.ofertas-laborales' => [
                'name' => 'Ofertas Laborales',
                'section' => 'Postulación',
                'icon' => 'bi-megaphone',
                'sort' => 36,
                'roles' => ['postulante', 'funcionario'],
            ],
        ];

        foreach ($modules as $key => $meta) {
            $moduleId = DB::table('modules')->where('key', $key)->value('id');
            if (!$moduleId) {
                $moduleId = DB::table('modules')->insertGetId([
                    'key' => $key,
                    'name' => $meta['name'],
                    'section' => $meta['section'],
                    'icon' => $meta['icon'],
                    'sort' => $meta['sort'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($meta['roles'] as $roleName) {
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
                        $payload['created_at'] = now();
                        $payload['updated_at'] = now();
                    }

                    DB::table('module_role')->insert($payload);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bolsa_trabajo_postulaciones')) {
            Schema::dropIfExists('bolsa_trabajo_postulaciones');
        }
        if (Schema::hasTable('bolsa_trabajo_ofertas')) {
            Schema::dropIfExists('bolsa_trabajo_ofertas');
        }

        if (!Schema::hasTable('modules') || !Schema::hasTable('module_role') || !Schema::hasTable('roles')) {
            return;
        }

        $keys = ['gestion.bolsa-trabajo', 'postulant.ofertas-laborales'];
        $moduleIds = DB::table('modules')->whereIn('key', $keys)->pluck('id');
        if ($moduleIds->isNotEmpty()) {
            DB::table('module_role')->whereIn('module_id', $moduleIds->all())->delete();
            DB::table('modules')->whereIn('id', $moduleIds->all())->delete();
        }
    }
};
