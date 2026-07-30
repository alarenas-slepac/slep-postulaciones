<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictPlanesEeDirectivoEstablecimiento
{
    /**
     * Restringe el acceso del rol funcionario_directivo_estab a los planes EE
     * asociados exclusivamente a su establecimiento. Para admin no aplica filtro.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $activeRole = $user && method_exists($user, 'activeRoleName')
            ? strtolower(trim((string) $user->activeRoleName()))
            : strtolower(trim((string) (session('active_role') ?? session('rol_activo') ?? ($user->rol ?? ''))));

        if ($activeRole !== 'funcionario_directivo_estab') {
            return $next($request);
        }

        $establecimientoId = $this->establecimientoIdUsuario($user);

        if (! $establecimientoId) {
            abort(403, 'Usuario directivo sin establecimiento asociado.');
        }

        // Forzar filtro en listados. Si el controlador ya soporta establecimiento_id,
        // sólo mostrará los planes del establecimiento del directivo.
        $request->merge([
            'establecimiento_id' => $establecimientoId,
            'directivo_establecimiento_id' => $establecimientoId,
            'solo_mi_establecimiento' => 1,
        ]);

        foreach (['establecimiento_plan', 'establecimiento_curso'] as $parameterName) {
            $parameter = $request->route($parameterName);

            if (! $parameter) {
                continue;
            }

            $parameterEstablecimientoId = $this->establecimientoIdDesdeParametro($parameter);

            abort_unless(
                $parameterEstablecimientoId && (int) $parameterEstablecimientoId === (int) $establecimientoId,
                403,
                'No puede acceder a planes de otro establecimiento.'
            );
        }

        return $next($request);
    }

    private function establecimientoIdUsuario($user): ?int
    {
        if (! $user) {
            return null;
        }

        foreach (['establecimiento_id', 'establecimiento_asociado_id', 'establecimiento_principal_id'] as $field) {
            if (isset($user->{$field}) && $user->{$field}) {
                return (int) $user->{$field};
            }
        }

        if (method_exists($user, 'loadMissing')) {
            $user->loadMissing('establecimiento');
        }

        if (isset($user->establecimiento) && $user->establecimiento && isset($user->establecimiento->id)) {
            return (int) $user->establecimiento->id;
        }

        return null;
    }

    private function establecimientoIdDesdeParametro($parameter): ?int
    {
        if (is_numeric($parameter)) {
            return null;
        }

        foreach (['establecimiento_id', 'id_establecimiento'] as $field) {
            if (isset($parameter->{$field}) && $parameter->{$field}) {
                return (int) $parameter->{$field};
            }
        }

        foreach (['establecimiento', 'curso', 'establecimientoCurso'] as $relation) {
            if (method_exists($parameter, 'loadMissing')) {
                try {
                    $parameter->loadMissing($relation);
                } catch (\Throwable $e) {
                    // Relacion inexistente: se intenta la siguiente alternativa.
                }
            }

            if (isset($parameter->{$relation}) && $parameter->{$relation}) {
                $related = $parameter->{$relation};

                if (isset($related->establecimiento_id) && $related->establecimiento_id) {
                    return (int) $related->establecimiento_id;
                }

                if (isset($related->id) && $relation === 'establecimiento') {
                    return (int) $related->id;
                }

                if (isset($related->establecimiento) && $related->establecimiento && isset($related->establecimiento->id)) {
                    return (int) $related->establecimiento->id;
                }
            }
        }

        return null;
    }
}
