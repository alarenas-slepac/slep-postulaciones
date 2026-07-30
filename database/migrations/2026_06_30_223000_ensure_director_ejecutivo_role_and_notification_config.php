<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $role = Role::findOrCreate('director_ejecutivo', 'web');

        if (Schema::hasTable('modules') && Schema::hasTable('module_role')) {
            $moduleIds = DB::table('modules')
                ->whereIn('key', ['tramites'])
                ->pluck('id');

            $hasTimestamps = Schema::hasColumn('module_role', 'created_at')
                && Schema::hasColumn('module_role', 'updated_at');

            foreach ($moduleIds as $moduleId) {
                $exists = DB::table('module_role')
                    ->where('module_id', $moduleId)
                    ->where('role_id', $role->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $payload = [
                    'module_id' => $moduleId,
                    'role_id' => $role->id,
                ];

                if ($hasTimestamps) {
                    $payload['created_at'] = now();
                    $payload['updated_at'] = now();
                }

                DB::table('module_role')->insert($payload);
            }
        }

        if (Schema::hasTable('cometidos_notificaciones_configuracion')) {
            DB::table('cometidos_notificaciones_configuracion')->updateOrInsert(
                ['clave' => 'director_ejecutivo_notificacion'],
                [
                    'nombre' => 'Director Ejecutivo - decisión por falta de presupuesto',
                    'descripcion' => 'Correo(s) de respaldo para notificar al Director Ejecutivo cuando un cometido requiere decisión por falta de disponibilidad presupuestaria. Se usa si no existe usuario con rol director_ejecutivo o si el rol no tiene correo asociado.',
                    'correos' => (string) env('DIRECTOR_EJECUTIVO_EMAIL', ''),
                    'activo' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $emailDirector = strtolower(trim((string) env('DIRECTOR_EJECUTIVO_EMAIL', '')));
        if ($emailDirector !== '' && Schema::hasTable('users')) {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$emailDirector])
                ->first();

            if ($user && ! $user->hasRole('director_ejecutivo')) {
                $user->assignRole('director_ejecutivo');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cometidos_notificaciones_configuracion')) {
            DB::table('cometidos_notificaciones_configuracion')
                ->where('clave', 'director_ejecutivo_notificacion')
                ->delete();
        }

        if (! Schema::hasTable('roles')) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', 'director_ejecutivo')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $roleId) {
            return;
        }

        if (Schema::hasTable('module_role')) {
            DB::table('module_role')->where('role_id', $roleId)->delete();
        }

        if (Schema::hasTable('model_has_roles')) {
            DB::table('model_has_roles')->where('role_id', $roleId)->delete();
        }

        if (Schema::hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
        }

        DB::table('roles')->where('id', $roleId)->delete();
    }
};
