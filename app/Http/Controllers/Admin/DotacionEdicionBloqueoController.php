<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Services\Dotacion\EdicionBloqueoService;
use Illuminate\Http\Request;

class DotacionEdicionBloqueoController extends Controller
{
    public function general(Request $request, EdicionBloqueoService $service)
    {
        return $this->guardar($request, $service);
    }

    public function establecimiento(Request $request, Establecimiento $establecimiento, EdicionBloqueoService $service)
    {
        return $this->guardar($request, $service, $establecimiento);
    }

    private function guardar(Request $request, EdicionBloqueoService $service, ?Establecimiento $establecimiento = null)
    {
        abort_unless($service->puedeAdministrar($request->user()), 403);
        $data = $request->validate([
            'bloqueado' => ['required', 'boolean'], 'version' => ['required', 'integer', 'min:0'],
            'motivo' => ['nullable', 'string', 'max:1000'], 'anio' => ['nullable', 'integer'],
        ]);
        $service->guardar($establecimiento?->id, (bool) $data['bloqueado'], (int) $data['version'], $data['motivo'] ?? null, $request->user());
        $params = ['anio' => $data['anio'] ?? now()->year];
        if ($establecimiento) { $params['establecimiento'] = $establecimiento; }

        return redirect()->route($establecimiento ? 'admin.dotacion-establecimiento.show' : 'admin.dotacion-establecimiento.index', $params)
            ->with('dotacion_bloqueo_status', 'Bloqueo '.($establecimiento ? 'individual' : 'general').' '.($data['bloqueado'] ? 'activado' : 'desactivado').'. Sólo afecta al rol Funcionario Directivo de Establecimiento, en todos los años.');
    }
}
