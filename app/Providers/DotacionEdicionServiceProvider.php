<?php

namespace App\Providers;

use App\Services\Dotacion\EdicionBloqueoService;
use App\Support\DotacionEdicionAcceso;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class DotacionEdicionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer([
            'admin.dotacion-establecimiento.index', 'admin.dotacion-establecimiento.show',
            'admin.dotacion-funciones.index', 'admin.dotacion-funciones.show',
            'admin.establecimiento-planes.index', 'admin.establecimiento-planes.show',
            'admin.establecimiento-curso-pie.index', 'admin.establecimiento-curso-pie.show',
        ], function ($view) {
            $datos = $view->getData();
            $rol = $datos['activeRole'] ?? auth()->user()?->activeRoleName();
            $establecimientoId = DotacionEdicionAcceso::establecimiento(request()) ?? auth()->user()?->establecimiento_id
                ?? ($datos['establecimiento']->id ?? $datos['configuracion']->establecimiento_id ?? null);
            if ($rol !== EdicionBloqueoService::ROL_BLOQUEADO) {
                $view->with('dotacionEdicionBloqueada', false);
                return;
            }
            $estado = app(EdicionBloqueoService::class)->estado($establecimientoId ? (int) $establecimientoId : null);
            request()->attributes->set('dotacion_edicion_estado', $estado);
            $view->with('dotacionEdicionBloqueada', $estado['bloqueado']);
            if ($estado['bloqueado']) {
                foreach ($datos as $clave => $valor) {
                    if (str_starts_with($clave, 'canManage') || str_starts_with($clave, 'canConfigure') || in_array($clave, ['canEdit', 'canEditPie', 'canValidate'], true)) {
                        $view->with($clave, false);
                    }
                }
            }
        });
        View::composer('layouts.app', function ($view) {
            $request = request();
            if (!DotacionEdicionAcceso::aplica($request)) { return; }
            $service = app(EdicionBloqueoService::class);
            $establecimientoId = DotacionEdicionAcceso::establecimiento($request);
            $estado = $service->estado($establecimientoId);
            $gestiona = $service->puedeAdministrar($request->user());
            $directivo = $request->user()?->activeRoleName() === EdicionBloqueoService::ROL_BLOQUEADO;
            $controles = $gestiona && $request->routeIs('admin.dotacion-establecimiento.index', 'admin.dotacion-establecimiento.show');
            if ($controles || ($directivo && $estado['bloqueado']) || session()->has('dotacion_bloqueo_status') || session('errors')?->has('dotacion_bloqueo')) {
                $view->with('dotacionEdicionPanel', [
                    'estado' => $estado, 'gestiona' => $controles, 'soloConsulta' => $directivo && $estado['bloqueado'],
                    'establecimiento' => $request->routeIs('admin.dotacion-establecimiento.show') ? $request->route('establecimiento') : null,
                    'anio' => (int) $request->query('anio', now()->year),
                ]);
            }
        });
    }
}
