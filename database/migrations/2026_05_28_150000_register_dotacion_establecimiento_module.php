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
        $moduleId = DB::table('modules')->where('key', 'admin.dotacion-establecimiento')->value('id');
        $payload = [
            'name' => 'Dotación establecimiento',
            'section' => 'Catálogos',
            'icon' => null,
            'sort' => 33,
            'updated_at' => $now,
        ];

        if ($moduleId) {
            DB::table('modules')->where('id', $moduleId)->update($payload);
        } else {
            $payload['key'] = 'admin.dotacion-establecimiento';
            $payload['created_at'] = $now;
            $moduleId = DB::table('modules')->insertGetId($payload);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $roles = DB::table('roles')
            ->whereIn('name', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'])
            ->pluck('id');

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

    public function down(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('module_role')) {
            return;
        }

        $moduleId = DB::table('modules')->where('key', 'admin.dotacion-establecimiento')->value('id');
        if ($moduleId) {
            DB::table('module_role')->where('module_id', $moduleId)->delete();
            DB::table('modules')->where('id', $moduleId)->delete();
        }
    }
};
