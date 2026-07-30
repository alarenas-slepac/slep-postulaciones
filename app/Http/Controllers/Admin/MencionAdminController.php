<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MencionRequest;
use App\Models\Mencion;
use App\Models\Subsector;
use Illuminate\Http\Request;

class MencionAdminController extends Controller
{
    public function index(Request $request)
    {
        $q          = trim((string) $request->query('q', ''));
        $subsector  = $request->query('subsector_id');

        $query = Mencion::query()->with('subsector');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                    ->orWhere('universidad', 'like', "%{$q}%")
                    ->orWhere('anio', 'like', "%{$q}%");
            });
        }

        if ($subsector) {
            $query->where('subsector_id', $subsector);
        }

        $items = $query->orderBy('subsector_id')->orderBy('nombre')->paginate(15)->withQueryString();
        $subsectores = Subsector::orderBy('subsector')->get();

        return view('admin.menciones.index', compact('items', 'subsectores', 'q', 'subsector'));
    }

    public function create()
    {
        $subsectores = Subsector::orderBy('subsector')->get();
        $mencion = new Mencion();
        return view('admin.menciones.create', compact('mencion', 'subsectores'));
    }

    public function store(MencionRequest $request)
    {
        Mencion::create($request->validated());
        return redirect()->route('admin.menciones.index')->with('status', 'Mención creada correctamente.');
    }

    public function edit(Mencion $mencione)
    {
        $subsectores = Subsector::orderBy('subsector')->get();
        return view('admin.menciones.edit', [
            'mencion'     => $mencione,
            'subsectores' => $subsectores,
        ]);
    }

    public function update(MencionRequest $request, Mencion $mencione)
    {
        $mencione->update($request->validated());
        return redirect()->route('admin.menciones.index')->with('status', 'Mención actualizada correctamente.');
    }

    public function destroy(Mencion $mencione)
    {
        $mencione->delete();
        return redirect()->route('admin.menciones.index')->with('status', 'Mención eliminada.');
    }

    /**
     * API de búsqueda (usada por el perfil) – ya la tenías
     */
    public function search(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        $query = Mencion::query()->with('subsector');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('nombre', 'like', "%{$q}%")
                    ->orWhere('universidad', 'like', "%{$q}%")
                    ->orWhere('anio', 'like', "%{$q}%");
            });
        }

        $items = $query->orderBy('subsector_id')->orderBy('nombre')->limit(300)->get();

        $grouped = [];
        foreach ($items as $m) {
            $grupo = $m->subsector?->subsector ?? 'Sin subsector';
            $grouped[$grupo][] = [
                'id'    => $m->id,
                'value' => trim($m->nombre
                    . ($m->universidad ? ' - ' . $m->universidad : '')
                    . ($m->anio ? ' - ' . $m->anio : '')),
                'label' => trim($m->nombre
                    . ($m->universidad ? ' - ' . $m->universidad : '')
                    . ($m->anio ? ' - ' . $m->anio : '')),
            ];
        }

        $out = [];
        foreach ($grouped as $grupo => $arr) {
            $out[] = ['subsector' => $grupo, 'items' => $arr];
        }

        return response()->json($out);
    }
}
