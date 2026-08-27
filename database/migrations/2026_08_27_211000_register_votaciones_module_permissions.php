<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'votaciones.admin', 'votaciones.manage-jornadas', 'votaciones.manage-grupos',
        'votaciones.manage-rutas', 'votaciones.operate-group',
        'votaciones.report-incidents', 'votaciones.view-history',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $all = Role::whereIn('name', ['admin', 'coordinador_gdp', 'funcionario_slep'])->get();
        foreach ($all as $role) {
            $role->givePermissionTo(self::PERMISSIONS);
        }
        $operators = Role::whereIn('name', ['funcionario_ac'])->get();
        foreach ($operators as $role) {
            $role->givePermissionTo(['votaciones.operate-group', 'votaciones.report-incidents']);
        }

        if (! Schema::hasTable('modules') || ! Schema::hasTable('module_role')) {
            return;
        }
        $now = now();
        $moduleId = DB::table('modules')->where('key', 'votaciones')->value('id');
        $payload = ['name' => 'Votaciones CCAF y Mutualidades', 'section' => 'Operación', 'icon' => 'bi-geo-alt-fill', 'sort' => 18];
        if (Schema::hasColumn('modules', 'updated_at')) {
            $payload['updated_at'] = $now;
        }
        if ($moduleId) {
            DB::table('modules')->where('id', $moduleId)->update($payload);
        } else {
            $payload['key'] = 'votaciones';
            if (Schema::hasColumn('modules', 'created_at')) {
                $payload['created_at'] = $now;
            }
            $moduleId = DB::table('modules')->insertGetId($payload);
        }
        $roleIds = DB::table('roles')->where('guard_name', 'web')->whereIn('name', ['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario_ac'])->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('module_role')->insertOrIgnore(['module_id' => $moduleId, 'role_id' => $roleId]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('modules') && Schema::hasTable('module_role')) {
            $id = DB::table('modules')->where('key', 'votaciones')->value('id');
            if ($id) {
                DB::table('module_role')->where('module_id', $id)->delete();
                DB::table('modules')->where('id', $id)->delete();
            }
        }
        if (Schema::hasTable('permissions')) {
            Permission::whereIn('name', self::PERMISSIONS)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
