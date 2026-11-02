<?php

namespace App\Http\Middleware;

use App\Services\Dotacion\EdicionBloqueoService;
use App\Support\DotacionEdicionAcceso;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class BloquearEdicionDotacion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!DotacionEdicionAcceso::aplica($request)
            || $request->user()?->activeRoleName() !== EdicionBloqueoService::ROL_BLOQUEADO) {
            return $next($request);
        }
        $establecimientoId = DotacionEdicionAcceso::establecimiento($request);
        if (DotacionEdicionAcceso::esEdicion($request)) {
            abort_unless($establecimientoId, 403, 'El directivo no tiene un establecimiento asociado.');
        }
        $estado = app(EdicionBloqueoService::class)->estado($establecimientoId);
        $request->attributes->set('dotacion_edicion_estado', $estado);
        if ($estado['bloqueado'] && DotacionEdicionAcceso::esEdicion($request)) {
            if ($request->isMethodSafe()) { abort(403, $estado['mensaje']); }
            throw ValidationException::withMessages(['dotacion_bloqueo' => $estado['mensaje']]);
        }

        return $next($request);
    }
}
