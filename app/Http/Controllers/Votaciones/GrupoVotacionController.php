<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Votaciones\GuardarGrupoRequest;
use App\Models\GrupoVotacion;
use App\Models\JornadaVotacion;
use App\Services\Votaciones\BitacoraVotacionService;
use App\Services\Votaciones\ConfiguracionGrupoVotacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GrupoVotacionController extends Controller
{
    public function store(GuardarGrupoRequest $request, JornadaVotacion $jornada, ConfiguracionGrupoVotacionService $service): RedirectResponse
    {
        $grupo = $service->crear($jornada, $request->validated(), $request->user());

        return redirect()->route('votaciones.admin.jornadas.show', $jornada)->withFragment('grupo-'.$grupo->id)
            ->with('success', $grupo->grupo_origen_id ? 'Grupo asociado a la jornada. Su equipo y ruta se pueden configurar de forma independiente.' : 'Grupo creado y asociado a la jornada.');
    }

    public function update(GuardarGrupoRequest $request, GrupoVotacion $grupo, ConfiguracionGrupoVotacionService $service): RedirectResponse
    {
        $service->actualizar($grupo, $request->validated(), $request->user());

        return back()->with('success', 'Grupo actualizado.');
    }

    public function destroy(Request $request, GrupoVotacion $grupo, BitacoraVotacionService $bitacora): RedirectResponse
    {
        abort_unless($request->user()->can('votaciones.manage-grupos'), 403);
        if ($grupo->jornada->estado !== 'borrador') {
            throw ValidationException::withMessages(['grupo' => 'No se puede eliminar un grupo iniciado.']);
        }$j = $grupo->jornada;
        $nombre = $grupo->nombre;
        DB::transaction(function () use ($grupo, $j, $nombre, $request, $bitacora) {
            $grupo->delete();
            $bitacora->registrar($j, 'grupo_eliminado', "Se eliminó {$nombre}.", $request->user());
        });

        return back()->with('success','Grupo eliminado.');
    }
}
