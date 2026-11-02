<?php

namespace App\Support;

use App\Services\Dotacion\EdicionBloqueoService;
use Illuminate\Http\Request;

class DotacionEdicionAcceso
{
    public const MODULOS = [
        'admin.dotacion-establecimiento.*', 'admin.dotacion-funciones.*',
        'admin.establecimiento-planes.*', 'admin.establecimiento-curso-pie.*', 'admin.establecimiento-cursos.*',
    ];

    public static function aplica(Request $request): bool
    {
        return $request->routeIs(...self::MODULOS) && !$request->routeIs('admin.dotacion-establecimiento.bloqueos.*');
    }

    public static function esEdicion(Request $request): bool
    {
        if (!$request->isMethodSafe()) { return true; }
        $action = $request->route()?->getActionMethod();

        // configure crea una configuración de plan con firstOrCreate mediante GET.
        return in_array($action, ['configure', 'edit', 'create', 'importForm'], true);
    }

    public static function establecimiento(Request $request): ?int
    {
        $user = $request->user();
        if ($user?->activeRoleName() === EdicionBloqueoService::ROL_BLOQUEADO) {
            // Nunca usar el ID del formulario o del filtro para eludir el bloqueo individual.
            foreach (['establecimiento_id', 'establecimiento_asociado_id', 'establecimiento_principal_id'] as $campo) {
                if (isset($user->$campo) && (int) $user->$campo > 0) { return (int) $user->$campo; }
            }

            return null;
        }
        $establecimiento = $request->route('establecimiento');

        return $establecimiento instanceof \App\Models\Establecimiento ? (int) $establecimiento->id : null;
    }
}
