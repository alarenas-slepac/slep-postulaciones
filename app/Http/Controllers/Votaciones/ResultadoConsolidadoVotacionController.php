<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Models\JornadaVotacion;
use App\Models\ResultadoConsolidadoVotacion;
use App\Services\Votaciones\ResultadoConsolidadoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResultadoConsolidadoVotacionController extends Controller
{
    public function consolidar(Request $request, JornadaVotacion $jornada, ResultadoConsolidadoService $service): RedirectResponse
    {
        $data = $request->validate(['huella' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/D'], 'fecha' => ['nullable', 'date_format:Y-m-d']]);
        $service->consolidar($jornada, $data['huella'], $request->user());

        return redirect()->route('votaciones.admin.resultados.index', ['jornada' => $jornada->slug, 'fecha' => $request->input('fecha')])
            ->with('success', 'Todos los días fueron consolidados. Revisa las sumas y pulsa Publicar consolidado.')->withFragment('consolidado');
    }

    public function publicacion(Request $request, JornadaVotacion $jornada, ResultadoConsolidadoVotacion $consolidado, ResultadoConsolidadoService $service): RedirectResponse
    {
        $request->validate(['publicar' => ['required', 'boolean']]);
        $service->publicar($jornada, $consolidado, $request->boolean('publicar'), $request->user());

        return redirect()->route('votaciones.admin.resultados.index', ['jornada' => $jornada->slug])
            ->with('success', $request->boolean('publicar') ? 'Consolidado publicado con todos los días incluidos.' : 'Consolidado retirado de la vista pública.')->withFragment('consolidado');
    }
}
