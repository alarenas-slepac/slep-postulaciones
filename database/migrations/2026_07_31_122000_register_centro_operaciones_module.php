<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->where('key', 'centro-operaciones')->value('id');
        $payload = [
            'name' => 'Centro de Operaciones',
            'section' => 'Operación',
            'icon' => 'bi-broadcast-pin',
            'sort' => 5,
            'updated_at' => $now,
        ];

        if ($moduleId) {
            DB::table('modules')->where('id', $moduleId)->update($payload);
        } else {
            $payload['key'] = 'centro-operaciones';
            $payload['created_at'] = $now;
            $moduleId = DB::table('modules')->insertGetId($payload);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', [
                'admin',
                'director_ejecutivo',
                'funcionario_slep',
                'coordinador_gdp',
                'coordinador_uatp',
                'funcionario_directivo_estab',
            ])
            ->pluck('id');

        $pivotHasTimestamps = Schema::hasColumn('module_role', 'created_at')
            && Schema::hasColumn('module_role', 'updated_at');

        foreach ($roleIds as $roleId) {
            $row = ['module_id' => $moduleId, 'role_id' => $roleId];
            if ($pivotHasTimestamps) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }

            DB::table('module_role')->insertOrIgnore($row);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'centro-operaciones')->value('id');
        if (! $moduleId) {
            return;
        }

        if (Schema::hasTable('module_role')) {
            DB::table('module_role')->where('module_id', $moduleId)->delete();
        }

        DB::table('modules')->where('id', $moduleId)->delete();
    }
};
