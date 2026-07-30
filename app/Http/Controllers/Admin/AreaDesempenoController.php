<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AreaDesempeno;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AreaDesempenoController extends Controller
{
    public function index(Request $request)
    {
        $estamento = (string)$request->query('estamento', '');
        $q = (string)$request->query('q', '');

        $areas = AreaDesempeno::query()
            ->when($estamento !== '', fn($qq) => $qq->where('estamento', $estamento))
            ->when($q !== '', fn($qq) => $qq->where('nombre', 'like', "%{$q}%"))
            ->orderBy('estamento')
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('admin.areas-desempeno.index', compact('areas', 'estamento', 'q'));
    }

    public function create()
    {
        return view('admin.areas-desempeno.create', [
            'area' => new AreaDesempeno(['activo' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'estamento' => ['required', 'in:docente,asistente'],
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('areas_desempeno')->where(fn($q) => $q->where('estamento', $request->estamento)),
            ],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['activo'] = (bool)($data['activo'] ?? false);

        AreaDesempeno::create($data);

        return redirect()
            ->route('admin.areas-desempeno.index')
            ->with('status', 'Área creada.');
    }

    public function edit(AreaDesempeno $areas_desempeno)
    {
        return view('admin.areas-desempeno.edit', [
            'area' => $areas_desempeno,
        ]);
    }

    public function update(Request $request, AreaDesempeno $areas_desempeno)
    {
        $data = $request->validate([
            'estamento' => ['required', 'in:docente,asistente'],
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('areas_desempeno')
                    ->where(fn($q) => $q->where('estamento', $request->estamento))
                    ->ignore($areas_desempeno->id),
            ],
            'activo' => ['nullable', 'boolean'],
        ]);

        $data['activo'] = (bool)($data['activo'] ?? false);

        $areas_desempeno->update($data);

        return redirect()
            ->route('admin.areas-desempeno.index')
            ->with('status', 'Área actualizada.');
    }

    public function destroy(AreaDesempeno $areas_desempeno)
    {
        $areas_desempeno->delete();

        return redirect()
            ->route('admin.areas-desempeno.index')
            ->with('status', 'Área eliminada.');
    }
}
