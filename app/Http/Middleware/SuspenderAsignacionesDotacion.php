<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\DotacionDocenteAsignacion;
use App\Support\DotacionAsignacionSuspension;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SuspenderAsignacionesDotacion
{
    public function handle(Request $request, Closure $next): Response
    {
        [$controller, $action] = array_pad(explode('@', $request->route()?->getActionName() ?? ''), 2, '');
        if ($request->isMethodSafe() || $controller !== DotacionAsignacionController::class) {
            return $next($request);
        }

        $rol = $request->user()?->activeRoleName();
        $asignacion = $request->route('asignacion');
        // El binding ya resolvió el registro: no confiar en un año enviado
        // por el cliente para modificar, vincular o eliminar horas existentes.
        $anio = $asignacion instanceof DotacionDocenteAsignacion
            ? (int) $asignacion->anio
            : (is_scalar($request->input('anio')) ? (int) $request->input('anio') : 0);
        if ($action === 'storeReserva') {
            $anio = 2027; // Las reservas actuales pertenecen exclusivamente a este año.
        }

        if (DotacionAsignacionSuspension::bloqueada($anio, $rol)) {
            throw ValidationException::withMessages(['asignaciones' => DotacionAsignacionSuspension::mensaje()]);
        }

        return $next($request);
    }
}
