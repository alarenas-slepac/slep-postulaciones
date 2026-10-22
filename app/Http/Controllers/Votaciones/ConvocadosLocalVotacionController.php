<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Votaciones\GuardarConvocadosLocalRequest;
use App\Models\RutaVotacion;
use App\Services\Votaciones\ConvocadosLocalVotacionService;
use Illuminate\Http\RedirectResponse;

class ConvocadosLocalVotacionController extends Controller
{
    public function update(GuardarConvocadosLocalRequest $request, RutaVotacion $ruta, ConvocadosLocalVotacionService $service): RedirectResponse
    {
        $service->actualizar($ruta, $request->validated('modo'), $request->validated('establecimientos', []) ?? [], $request->user());

        return redirect()->route('votaciones.admin.jornadas.show', $ruta->grupo->jornada)->withFragment('local-'.$ruta->id)
            ->with('success', $request->validated('modo') === 'manual' ? 'Establecimientos convocados actualizados en la planificación del grupo.' : 'El local vuelve a utilizar los establecimientos del padrón de esta jornada.');
    }
}
