<?php

namespace App\Http\Middleware;

use App\Models\Module;
use App\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $routeName = optional($request->route())->getName();
        if (!$routeName) return $next($request);

        $moduleKey = ModuleRegistry::moduleKeyFromRouteName($routeName);
        if (!$moduleKey) return $next($request);

        // Si el módulo aún no está registrado, lo dejamos pasar (o puedes denegar por defecto).
        $module = Module::where('key', $moduleKey)->first();
        if (!$module) return $next($request);

        // Admin bypass (opcional)
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return $next($request);
        }

        // Este módulo combina control por rol (acciones) y visibilidad por módulo.
        // En algunos entornos la asignación visual por módulo puede quedar desalineada
        // con el acceso real por rol; evitamos falsos 403 para los roles habilitados.
        if (
            $moduleKey === 'admin.restricted-ruts'
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['coordinador_gdp', 'funcionario_slep'])
        ) {
            return $next($request);
        }

        // Los tickets de incidencia aplican un alcance propio por responsable,
        // subdirección o establecimiento en TicketController.
        if (
            str_starts_with($routeName, 'centro-operaciones.tickets.')
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['admin', 'director_ejecutivo', 'secretaria_direccion_ejecutiva', 'comunicaciones', 'gabinete_slep', 'funcionario_ac', 'funcionario_directivo_estab'])
        ) {
            return $next($request);
        }

        // Mis Cargas Familiares tiene control fino en sus propias rutas y controlador.
        // El módulo padre se resuelve como "tramites"; por eso se permite pasar a los
        // roles autorizados para este submódulo aunque no tengan habilitado todo Tramites.
        if (
            str_starts_with($routeName, 'tramites.cargas-familiares.')
            && method_exists($user, 'hasAnyRole')
        ) {
            $cargasRoles = array_values(array_unique(array_merge(
                (array) config('cargas_familiares.acceso_solicitantes.roles_habilitados', ['funcionario_ac']),
                ['admin', 'funcionario_slep', 'coordinador_gdp']
            )));

            if ($user->hasAnyRole($cargasRoles)) {
                return $next($request);
            }
        }


        // Buscador de postulantes y sus documentos de consulta son parte del módulo Reemplazos,
        // pero UATP requiere acceso de lectura aunque el módulo padre no esté asignado en module_role.
        if (
            (str_starts_with($routeName, 'reemplazos.buscador-postulantes.') || str_starts_with($routeName, 'reemplazos.documents.'))
            && method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['coordinador_uatp'])
        ) {
            return $next($request);
        }

        // Cometido funcionario es un submódulo de trámites con flujo propio por rol.
        // Se permite pasar a los roles autorizados aunque no tengan habilitado todo el módulo padre "tramites".
        if (
            str_starts_with($routeName, 'tramites.cometidos-funcionarios.')
            && method_exists($user, 'hasAnyRole')
        ) {
            if ($user->hasAnyRole(['funcionario_estab', 'coordinador_uatp', 'admin', 'funcionario_slep', 'coordinador_gdp', 'supervisor_plani', 'coordinador_plani', 'funcionario_daf', 'funcionario_juridica'])) {
                return $next($request);
            }
        }

        // Agendamiento Proyector y Salas tiene control fino por rol, propiedad del agendamiento
        // y administrador de sala en su controlador. Se permite pasar a los roles habilitados
        // aunque el módulo gestion.agendamientos-recursos no esté asignado en module_role.
        if (
            str_starts_with($routeName, 'gestion.agendamientos-recursos.')
            && method_exists($user, 'hasAnyRole')
        ) {
            if ($user->hasAnyRole(['admin', 'coordinador_gdp', 'funcionario_slep', 'funcionario_ac', 'secretaria_direccion_ejecutiva'])) {
                return $next($request);
            }
        }

        $roleIds = $user->roles()->pluck('id');
        $allowed = DB::table('module_role')
            ->where('module_id', $module->id)
            ->whereIn('role_id', $roleIds)
            ->exists();

        abort_unless($allowed, 403);

        return $next($request);
    }
}
