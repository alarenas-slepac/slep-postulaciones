<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dotacion\DocentesDisposicionService;
use App\Services\Dotacion\DotacionRevisionService;
use Illuminate\Http\Request;

class DotacionRevisionController extends Controller
{
    public function __construct(private DotacionRevisionService $servicio) {}

    private function autorizar(Request $request): void
    {
        abort_unless(in_array($request->user()?->activeRoleName(), DocentesDisposicionService::ROLES, true), 403);
        abort_unless(DotacionRevisionService::disponible(), 503, 'Ejecute la migración de revisiones anuales de dotación.');
    }

    public function finalizar(Request $request, int $establecimiento)
    {
        $this->autorizar($request);
        $datos = $request->validate(['anio' => ['required', 'integer', 'between:2020,2100'], 'confirmacion' => ['required', 'accepted']]);
        $this->servicio->finalizar($establecimiento, (int) $datos['anio'], (int) $request->user()->id);
        return redirect()->route('admin.dotacion-establecimiento.index', ['anio' => $datos['anio']])
            ->with('success', 'Establecimiento revisado y finalizado para '.$datos['anio'].'. Ya participa como origen y destino en Docentes a disposición.');
    }

    public function reabrir(Request $request, int $establecimiento)
    {
        $this->autorizar($request);
        $datos = $request->validate(['anio' => ['required', 'integer', 'between:2020,2100'],
            'revision_id' => ['required', 'integer'], 'motivo' => ['required', 'string', 'min:10', 'max:1000']]);
        $this->servicio->reabrir($establecimiento, (int) $datos['anio'], (int) $datos['revision_id'], $datos['motivo'], (int) $request->user()->id);
        return redirect()->route('admin.dotacion-establecimiento.index', ['anio' => $datos['anio']])
            ->with('success', 'Establecimiento reabierto para '.$datos['anio'].'. Se retiró de los nuevos orígenes y destinos y se conservó el historial.');
    }
}
