<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Votaciones\GuardarRutaRequest;
use App\Models\GrupoVotacion;
use App\Models\RutaVotacion;
use App\Models\VisitaVotacion;
use App\Services\Votaciones\BitacoraVotacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RutaVotacionController extends Controller
{
    public function store(GuardarRutaRequest $request, GrupoVotacion $grupo, BitacoraVotacionService $bitacora): RedirectResponse
    {
        $this->editable($grupo);
        DB::transaction(function () use ($request, $grupo, $bitacora) {
            $grupo = GrupoVotacion::query()->lockForUpdate()->findOrFail($grupo->id);
            $orden = (int) $grupo->rutas()->max('orden') + 1;
            $ruta = $grupo->rutas()->create(['establecimiento_id' => $request->integer('establecimiento_id'), 'orden' => $orden, 'activa' => true]);
            VisitaVotacion::create(['ruta_votacion_id' => $ruta->id, 'estado' => 'pendiente']);
            $bitacora->registrar($grupo->jornada, 'ruta_agregada', 'Se agregó un establecimiento a la ruta.', $request->user(), $grupo, $ruta);
        });

        return back()->with('success', 'Establecimiento agregado.');
    }

    public function mover(Request $request, RutaVotacion $ruta, BitacoraVotacionService $bitacora): RedirectResponse
    {
        abort_unless($request->user()->can('votaciones.manage-rutas'), 403);
        $data = $request->validate(['direccion' => ['required', 'in:subir,bajar']]);
        $this->editable($ruta->grupo);
        $comparador = $data['direccion'] === 'subir' ? '<' : '>';
        $ordenDir = $data['direccion'] === 'subir' ? 'desc' : 'asc';
        $otra = $ruta->grupo->rutas()->where('orden', $comparador, $ruta->orden)->orderBy('orden', $ordenDir)->first();
        if (! $otra) {
            return back();
        }DB::transaction(function () use ($ruta, $otra, $request, $bitacora) {
            $orden = $ruta->orden;
            $otraOrden = $otra->orden;
            $ruta->update(['orden' => 0]);
            $otra->update(['orden' => $orden]);
            $ruta->update(['orden' => $otraOrden]);
            $bitacora->registrar($ruta->grupo->jornada, 'ruta_reordenada', 'Se cambió el orden de la ruta.', $request->user(), $ruta->grupo, $ruta);
        });

        return back()->with('success', 'Ruta reordenada.');
    }

    public function destroy(Request $request, RutaVotacion $ruta, BitacoraVotacionService $bitacora): RedirectResponse
    {
        abort_unless($request->user()->can('votaciones.manage-rutas'), 403);
        $grupo = $ruta->grupo;
        $this->editable($grupo);
        DB::transaction(function () use ($ruta, $grupo, $request, $bitacora) {
            $orden = $ruta->orden;
            $ruta->delete();
            $grupo->rutas()->where('orden', '>', $orden)->increment('orden', 1000);
            $grupo->rutas()->where('orden', '>', 1000)->decrement('orden', 1001);
            $bitacora->registrar($grupo->jornada, 'ruta_eliminada', 'Se eliminó un establecimiento de la ruta.', $request->user(), $grupo);
        });

        return back()->with('success', 'Establecimiento retirado.');
    }

    private function editable(GrupoVotacion $grupo): void
    {
        if ($grupo->jornada->estado !== 'borrador') {
            throw ValidationException::withMessages(['ruta' => 'Las rutas solo se configuran en borrador.']);
        }
    }
}
