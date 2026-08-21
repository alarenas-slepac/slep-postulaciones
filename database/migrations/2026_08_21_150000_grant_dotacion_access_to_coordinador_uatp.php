<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{name: string, sort: int}>
     */
    private array $modules = [
        'admin.cursos' => ['name' => 'Cursos', 'sort' => 26],
        'admin.planes-estudio' => ['name' => 'Planes de Estudio', 'sort' => 27],
        'admin.establecimiento-cursos' => ['name' => 'Cursos por Establecimiento', 'sort' => 28],
        'admin.establecimiento-curso-pie' => ['name' => 'Estudiantes PIE por curso', 'sort' => 29],
        'admin.establecimiento-planes' => ['name' => 'Configuración de Planes por Establecimiento', 'sort' => 30],
        'admin.asignaturas' => ['name' => 'Asignaturas', 'sort' => 30],
        'admin.asignaturas-personalizadas' => ['name' => 'Asignaturas Personalizadas', 'sort' => 31],
        'admin.dotacion-funciones' => ['name' => 'Dotación funciones y planes', 'sort' => 32],
        'admin.dotacion-establecimiento' => ['name' => 'Dotación establecimiento', 'sort' => 33],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $roleId = DB::table('roles')->where('name', 'coordinador_uatp')->value('id');
        if (! $roleId) {
            return;
        }

        $now = now();
        $pivotHasTimestamps = Schema::hasColumn('module_role', 'created_at')
            && Schema::hasColumn('module_role', 'updated_at');

        foreach ($this->modules as $key => $meta) {
            $moduleId = DB::table('modules')->where('key', $key)->value('id');
            if (! $moduleId) {
                $moduleId = DB::table('modules')->insertGetId([
                    'key' => $key,
                    'name' => $meta['name'],
                    'section' => 'Catálogos',
                    'icon' => null,
                    'sort' => $meta['sort'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (! $moduleId) {
                continue;
            }

            $assignment = [
                'module_id' => $moduleId,
                'role_id' => $roleId,
            ];
            $values = $pivotHasTimestamps
                ? ['created_at' => $now, 'updated_at' => $now]
                : [];

            DB::table('module_role')->updateOrInsert($assignment, $values);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $roleId = DB::table('roles')->where('name', 'coordinador_uatp')->value('id');
        if (! $roleId) {
            return;
        }

        $moduleIds = DB::table('modules')
            ->whereIn('key', array_keys($this->modules))
            ->pluck('id');

        DB::table('module_role')
            ->where('role_id', $roleId)
            ->whereIn('module_id', $moduleIds)
            ->delete();
    }
};
