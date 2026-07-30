<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viaticos_reembolsos_valores', function (Blueprint $table) {
            $table->id();
            $table->string('estamento', 50);
            $table->string('cargo_funcion');
            $table->date('vigente_desde');
            $table->date('vigente_hasta');
            $table->unsignedInteger('valor_100');
            $table->unsignedInteger('valor_40');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['estamento', 'cargo_funcion', 'vigente_desde', 'vigente_hasta'], 'viaticos_valores_unique_tramo');
            $table->index(['estamento', 'cargo_funcion', 'vigente_desde', 'vigente_hasta'], 'viaticos_valores_lookup_idx');
            $table->index('activo');
        });

        $now = now();
        $rows = $this->initialRows($now);
        foreach ($rows as $row) {
            DB::table('viaticos_reembolsos_valores')->updateOrInsert(
                [
                    'estamento' => $row['estamento'],
                    'cargo_funcion' => $row['cargo_funcion'],
                    'vigente_desde' => $row['vigente_desde'],
                    'vigente_hasta' => $row['vigente_hasta'],
                ],
                $row
            );
        }

        $this->registerModule();
    }

    public function down(): void
    {
        $this->unregisterModule();
        Schema::dropIfExists('viaticos_reembolsos_valores');
    }

    protected function initialRows($now): array
    {
        $periodos = [
            ['desde' => '2025-01-01', 'hasta' => '2025-12-31', 'director' => [79523, 31809], 'alto' => [64538, 25815], 'base' => [48003, 19201]],
            ['desde' => '2026-01-01', 'hasta' => '2026-05-31', 'director' => [81113, 32445], 'alto' => [65829, 26331], 'base' => [48963, 19585]],
            ['desde' => '2026-06-01', 'hasta' => '2026-12-31', 'director' => [82249, 32899], 'alto' => [66751, 26700], 'base' => [49648, 19859]],
        ];

        $cargos = [
            ['Docente', 'Director', 'director'],
            ['Docente', 'Docente Directivo', 'alto'],
            ['Docente', 'Docentes', 'alto'],
            ['AAEE', 'Directora Junji', 'alto'],
            ['AAEE', 'Educadora de Párvulos', 'alto'],
            ['AAEE', 'Profesional', 'alto'],
            ['AAEE', 'Técnico', 'alto'],
            ['AAEE', 'Administrativo', 'base'],
            ['AAEE', 'Auxiliar', 'base'],
        ];

        $rows = [];
        foreach ($periodos as $periodo) {
            foreach ($cargos as [$estamento, $cargo, $grupo]) {
                [$valor100, $valor40] = $periodo[$grupo];
                $rows[] = [
                    'estamento' => $estamento,
                    'cargo_funcion' => $cargo,
                    'vigente_desde' => $periodo['desde'],
                    'vigente_hasta' => $periodo['hasta'],
                    'valor_100' => $valor100,
                    'valor_40' => $valor40,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $rows;
    }

    protected function registerModule(): void
    {
        if (!Schema::hasTable('modules')) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->updateOrInsert(
            ['key' => 'admin.viaticos-reembolsos'],
            [
                'name' => 'Viáticos y Reembolsos',
                'section' => 'Catálogos',
                'icon' => null,
                'sort' => 24,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $module = DB::table('modules')->where('key', 'admin.viaticos-reembolsos')->first();
        if (!$module || !Schema::hasTable('roles') || !Schema::hasTable('module_role')) {
            return;
        }

        $roleNames = ['admin', 'coordinador_gdp', 'funcionario_daf', 'supervisor_plani', 'coordinador_plani'];
        $roleIds = DB::table('roles')->whereIn('name', $roleNames)->pluck('id');
        $hasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('module_role')
                ->where('module_id', $module->id)
                ->where('role_id', $roleId)
                ->exists();

            if (!$exists) {
                $payload = [
                    'module_id' => $module->id,
                    'role_id' => $roleId,
                ];
                if ($hasTimestamps) {
                    $payload['created_at'] = $now;
                    $payload['updated_at'] = $now;
                }
                DB::table('module_role')->insert($payload);
            }
        }
    }

    protected function unregisterModule(): void
    {
        if (!Schema::hasTable('modules')) {
            return;
        }

        $module = DB::table('modules')->where('key', 'admin.viaticos-reembolsos')->first();
        if (!$module) {
            return;
        }

        if (Schema::hasTable('module_role')) {
            DB::table('module_role')->where('module_id', $module->id)->delete();
        }

        DB::table('modules')->where('id', $module->id)->delete();
    }
};
