<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Votaciones\GuardarGrupoRequest;
use App\Models\GrupoVotacion;
use App\Models\JornadaVotacion;
use App\Services\Votaciones\BitacoraVotacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GrupoVotacionController extends Controller
{
    public function store(GuardarGrupoRequest $request, JornadaVotacion $jornada, BitacoraVotacionService $bitacora): RedirectResponse
    {
        if ($jornada->estado !== 'borrador') {
            throw ValidationException::withMessages(['grupo' => 'Los grupos solo se configuran en borrador.']);
        }
        DB::transaction(function () use ($request, $jornada, $bitacora) {
            $data = $request->safe()->except('miembros');
            $g = $jornada->grupos()->create($data + ['estado' => 'pendiente']);
            $g->miembros()->sync($request->validated('miembros', []));
            $bitacora->registrar($jornada, 'grupo_creado', "Se creó {$g->nombre}.", $request->user(), $g);
        });

        return back()->with('success', 'Grupo creado.');
    }

    public function update(GuardarGrupoRequest $request, GrupoVotacion $grupo, BitacoraVotacionService $bitacora): RedirectResponse
    {
        if ($grupo->jornada->estado !== 'borrador') {
            throw ValidationException::withMessages(['grupo' => 'Los grupos solo se configuran en borrador.']);
        } DB::transaction(function () use ($request, $grupo, $bitacora) {
            $grupo->update($request->safe()->except('miembros'));
            $grupo->miembros()->sync($request->validated('miembros', []));
            $bitacora->registrar($grupo->jornada, 'grupo_actualizado', "Se actualizó {$grupo->nombre}.", $request->user(), $grupo);
        });

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
