<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Votaciones\RegistrarHorarioRequest;
use App\Models\GrupoVotacion;
use App\Models\RutaVotacion;
use App\Services\Votaciones\OperacionVotacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperacionVotacionController extends Controller
{
    public function index(Request $request): View
    {
        $query = GrupoVotacion::with(['jornada', 'rutas.establecimiento', 'rutas.visita'])->whereHas('jornada', fn ($q) => $q->whereIn('estado', ['publicada', 'en_curso']));
        if (! $request->user()->can('votaciones.admin')) {
            $query->where(fn ($q) => $q->where('encargado_id', $request->user()->id)->orWhereHas('miembros', fn ($m) => $m->whereKey($request->user()->id)));
        }

        return view('votaciones.operacion.index', ['grupos' => $query->orderBy('jornada_votacion_id')->orderBy('numero')->get()]);
    }

    public function show(Request $request, GrupoVotacion $grupo): View
    {
        $this->authorize('operate', $grupo);
        $grupo->load(['jornada.procesos', 'rutas.establecimiento', 'rutas.visita']);

        return view('votaciones.operacion.show', compact('grupo'));
    }

    public function iniciarGrupo(Request $request, GrupoVotacion $grupo, OperacionVotacionService $service): RedirectResponse
    {
        $this->authorize('operate', $grupo);
        $service->iniciarGrupo($grupo, $request->user());

        return back()->with('success', 'Grupo iniciado.');
    }

    public function iniciarVisita(RegistrarHorarioRequest $request, RutaVotacion $ruta, OperacionVotacionService $service): RedirectResponse
    {
        $this->authorize('operate', $ruta->grupo);
        $service->iniciarVisita($ruta, $request->user(), $request->validated('fecha_hora'));

        return back()->with('success', 'Votación iniciada.');
    }

    public function finalizarVisita(RegistrarHorarioRequest $request, RutaVotacion $ruta, OperacionVotacionService $service): RedirectResponse
    {
        $this->authorize('operate', $ruta->grupo);
        $service->finalizarVisita($ruta, $request->user(), $request->validated('fecha_hora'));

        return back()->with('success','Votación finalizada.');
    }
}
