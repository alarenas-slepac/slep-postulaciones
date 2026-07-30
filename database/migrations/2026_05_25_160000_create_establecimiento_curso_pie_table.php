<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establecimiento_curso_pie', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->foreignId('establecimiento_curso_id')->constrained('establecimiento_cursos')->cascadeOnDelete();
            $table->foreignId('curso_id')->nullable()->constrained('cursos')->nullOnDelete();
            $table->foreignId('plan_estudio_id')->nullable()->constrained('planes_estudio')->nullOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('rbd')->nullable();
            $table->unsignedSmallInteger('necesidades_transitorias')->default(0);
            $table->unsignedSmallInteger('necesidades_permanentes')->default(0);
            $table->unsignedSmallInteger('total_pie')->default(0);
            $table->text('observacion')->nullable();
            $table->string('estado', 40)->default('borrador')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['establecimiento_curso_id', 'anio'], 'ec_pie_curso_anio_unique');
            $table->index(['establecimiento_id', 'anio'], 'ec_pie_estab_anio_idx');
        });

        $this->registerModule();
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimiento_curso_pie');

        if (Schema::hasTable('modules')) {
            $moduleId = DB::table('modules')->where('key', 'admin.establecimiento-curso-pie')->value('id');
            if ($moduleId) {
                if (Schema::hasTable('module_role')) {
                    DB::table('module_role')->where('module_id', $moduleId)->delete();
                }
                DB::table('modules')->where('id', $moduleId)->delete();
            }
        }
    }

    private function registerModule(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->where('key', 'admin.establecimiento-curso-pie')->value('id');
        $payload = [
            'name' => 'Estudiantes PIE por curso',
            'section' => 'Catálogos',
            'icon' => null,
            'sort' => 30,
            'updated_at' => $now,
        ];

        if ($moduleId) {
            DB::table('modules')->where('id', $moduleId)->update($payload);
        } else {
            $payload['key'] = 'admin.establecimiento-curso-pie';
            $payload['created_at'] = $now;
            $moduleId = DB::table('modules')->insertGetId($payload);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $roles = DB::table('roles')
            ->whereIn('name', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'])
            ->pluck('id', 'name');

        $hasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');

        foreach ($roles as $roleId) {
            $exists = DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->exists();

            if (! $exists) {
                $row = ['module_id' => $moduleId, 'role_id' => $roleId];
                if ($hasTimestamps) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                DB::table('module_role')->insert($row);
            }
        }
    }
};
