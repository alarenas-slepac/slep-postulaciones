<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Verifica que el usuario tenga al menos uno de los roles indicados.
     * Uso en rutas: ->middleware(['auth','verified','ensure.role:admin'])
     * Múltiples roles: 'ensure.role:admin|funcionario_slep'
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'No autenticado.');
        }

        // Normaliza: "admin|editor", "admin", ["admin","editor"], etc.
        $needed = [];
        foreach ($roles as $r) {
            foreach (explode('|', (string)$r) as $piece) {
                $piece = trim($piece);
                if ($piece !== '') {
                    $needed[] = $piece;
                }
            }
        }
        $needed = array_values(array_unique($needed));

        // Si el modelo User tiene HasRoles (Spatie), úsalo:
        if (method_exists($user, 'hasAnyRole')) {
            if (!$user->hasAnyRole($needed)) {
                abort(403, 'No tienes el rol requerido.');
            }
        } else {
            // Fallback mínimo por si no está el trait (puedes adaptarlo a tu esquema)
            // Aquí simplemente bloqueamos para evitar accesos indebidos.
            abort(500, 'Sistema de roles no disponible.');
        }

        return $next($request);
    }
}
