<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubsectorRequest;
use App\Models\Subsector;
use Illuminate\Http\Request;

class SubsectorAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));

        $query = Subsector::query()->withCount('menciones'); // 👈 agrega el contador
        if ($q !== '') {
            $query->where('subsector', 'like', "%{$q}%");
        }

        $items = $query->orderBy('subsector')->paginate(15)->withQueryString();

        return view('admin.subsectores.index', compact('items', 'q'));
    }

    public function create()
    {
        $subsector = new Subsector();
        return view('admin.subsectores.create', compact('subsector'));
    }

    public function store(SubsectorRequest $request)
    {
        Subsector::create($request->validated());
        return redirect()->route('admin.subsectores.index')->with('status', 'Subsector creado correctamente.');
    }

    public function edit(Subsector $subsector)
    {
        return view('admin.subsectores.edit', compact('subsector'));
    }

    public function update(SubsectorRequest $request, Subsector $subsector)
    {
        $subsector->update($request->validated());
        return redirect()->route('admin.subsectores.index')->with('status', 'Subsector actualizado correctamente.');
    }

    public function destroy(Subsector $subsector)
    {
        // Evita borrar si tiene menciones asociadas
        if (method_exists($subsector, 'menciones') && $subsector->menciones()->exists()) {
            return back()->withErrors(['general' => 'No se puede eliminar: existen menciones asociadas a este subsector.']);
        }

        $subsector->delete();
        return redirect()->route('admin.subsectores.index')->with('status', 'Subsector eliminado.');
    }
}
