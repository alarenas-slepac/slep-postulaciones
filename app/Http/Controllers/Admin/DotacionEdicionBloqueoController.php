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
            'desbloqueo_hasta' => ['nullable', 'string', 'max:30'],
        ]);
        $service->guardar($establecimiento?->id, (bool) $data['bloqueado'], (int) $data['version'], $data['motivo'] ?? null, $request->user(), $data['desbloqueo_hasta'] ?? null);
        $params = ['anio' => $data['anio'] ?? now()->year];
        if ($establecimiento) { $params['establecimiento'] = $establecimiento; }

        $mensaje = $establecimiento && !$data['bloqueado']
            ? 'Desbloqueo temporal autorizado sólo para el RBD '.$establecimiento->rbd.' hasta el '.$service->estado($establecimiento->id)['desbloqueo_hasta_texto'].' ('.$service->zonaHoraria().'). Al vencer, la edición se bloquea automáticamente.'
            : 'Bloqueo '.($establecimiento ? 'individual' : 'general').' '.($data['bloqueado'] ? 'activado' : 'desactivado').'. Sólo afecta al rol Funcionario Directivo de Establecimiento, en todos los años.';

        return redirect()->route($establecimiento ? 'admin.dotacion-establecimiento.show' : 'admin.dotacion-establecimiento.index', $params)
            ->with('dotacion_bloqueo_status', $mensaje);
    }
}
