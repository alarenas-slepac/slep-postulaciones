<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveRole
{
    /**
     * Restringe por el contexto de rol activo, sin alterar el middleware
     * histórico ensure.role que valida roles asignados.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user, 403, 'No autenticado.');

        $allowed = collect($roles)
            ->flatMap(fn ($role) => preg_split('/[|,]/', (string) $role) ?: [])
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter()
            ->unique()
            ->values();

        $activeRole = method_exists($user, 'activeRoleName')
            ? strtolower(trim((string) $user->activeRoleName()))
            : '';

        abort_unless(
            $activeRole !== '' && $allowed->contains($activeRole),
            403,
            'Debes cambiar al rol autorizado para acceder a este módulo.'
        );

        return $next($request);
    }
}
