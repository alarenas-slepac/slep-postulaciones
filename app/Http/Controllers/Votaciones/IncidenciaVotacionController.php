<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Votaciones\GuardarIncidenciaRequest;
use App\Models\GrupoVotacion;
use App\Models\IncidenciaVotacion;
use App\Services\Votaciones\BitacoraVotacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IncidenciaVotacionController extends Controller
{
    public function store(GuardarIncidenciaRequest $request, GrupoVotacion $grupo, BitacoraVotacionService $bitacora): RedirectResponse
    {
        $this->authorize('reportIncident', $grupo);
        $data = $request->validated();
        $incidencia = $grupo->jornada->incidencias()->create($data + ['grupo_votacion_id' => $grupo->id, 'estado' => 'abierta', 'reportada_por' => $request->user()->id]);
        $ruta = $incidencia->ruta_votacion_id ? $grupo->rutas()->find($incidencia->ruta_votacion_id) : null;
        $bitacora->registrar($grupo->jornada, 'incidencia_reportada', 'Se reportó una incidencia.', $request->user(), $grupo, $ruta, ['incidencia_id' => $incidencia->id, 'publica' => $incidencia->publica]);

        return back()->with('success', 'Incidencia registrada.');
    }

    public function resolver(Request $request, IncidenciaVotacion $incidencia, BitacoraVotacionService $bitacora): RedirectResponse
    {
        abort_unless($request->user()->can('votaciones.admin'), 403);
        $data = $request->validate(['resolucion' => ['required', 'string', 'max:2000']]);
        $incidencia->update(['estado' => 'resuelta', 'resolucion' => $data['resolucion'], 'resuelta_por' => $request->user()->id, 'resuelta_at' => now(config('votaciones.timezone'))]);
        $bitacora->registrar($incidencia->jornada, 'incidencia_resuelta', 'Se resolvió una incidencia.', $request->user(), $incidencia->grupo, metadata: ['incidencia_id' => $incidencia->id, 'resolucion' => $data['resolucion']]);

        return back()->with('success','Incidencia resuelta.');
    }
}
