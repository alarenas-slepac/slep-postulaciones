<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use App\Services\Padron\PadronIndividualService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PadronIndividualController extends Controller
{
    public function index(Request $request, PadronIndividualService $service)
    {
        $validated = $request->validate([
            'rut' => ['nullable', 'string', 'max:20'],
            'personal_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $rut = ! empty($validated['rut']) ? $service->rut($validated['rut']) : null;
        $registros = $rut ? $service->registros($rut) : collect();
        $periodo = $service->periodo();
        $editables = $registros->filter(fn ($p) => $service->editable($p, $periodo));
        $item = ! empty($validated['personal_id']) ? $editables->firstWhere('id', (int) $validated['personal_id']) : null;
        abort_if(! empty($validated['personal_id']) && ! $item, 422, 'El ID no corresponde a un contrato regular vigente del RUT en el período actual.');
        $registros->load('establecimiento:id,rbd,nombre_establecimiento');

        return view('reemplazos.personal.individual', [
            'rut' => $rut, 'registros' => $registros, 'editables' => $editables, 'item' => $item,
            'tieneRegular' => $registros->contains(fn ($p) => $service->regular($p->tipocontrato)),
            'periodo' => $periodo, 'huella' => $service->huella($registros, $periodo),
            'establecimientos' => Establecimiento::orderBy('nombre_establecimiento')->get(['id', 'rbd', 'nombre_establecimiento']),
            'asignaciones' => $item ? $service->asignaciones($item) : collect(),
            'instalado' => Schema::hasTable('padron_individual_cambios'),
            'historial' => $rut && Schema::hasTable('padron_individual_cambios')
                ? DB::table('padron_individual_cambios')->whereIn('personal_id', $registros->pluck('id'))->orderByDesc('id')->limit(10)->get()
                : collect(),
        ]);
    }

    public function store(Request $request, PadronIndividualService $service)
    {
        $personal = $service->guardar($request->all(), $request->user());
        $liberadas = count((array) $request->input('liberar_asignaciones', []));
        return redirect()->route('reemplazos.individual.index', ['rut' => $service->rut($personal->rut)])
            ->with('status', 'Registro ID '.$personal->id.' guardado en el padrón. '.$liberadas.' asignaciones liberadas (inactivadas, sin borrar historial). Las no seleccionadas y las referencias contractuales se conservan.');
    }
}
