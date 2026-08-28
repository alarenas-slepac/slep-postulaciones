<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Models\ProcesoVotacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProcesoVotacionController extends Controller
{
    public function index(): View
    {
        return view('votaciones.admin.procesos', [
            'procesos' => ProcesoVotacion::query()->withCount('jornadas')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:procesos_votacion,codigo'],
            'nombre' => ['required', 'string', 'max:255'],
        ]);

        ProcesoVotacion::create($data + ['activo' => true]);

        return back()->with('success', 'Proceso de votación creado.');
    }

    public function update(Request $request, ProcesoVotacion $proceso): RedirectResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('procesos_votacion', 'codigo')->ignore($proceso->id)],
            'nombre' => ['required', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $proceso->update($data + ['activo' => $request->boolean('activo')]);

        return back()->with('success', 'Proceso de votación actualizado.');
    }
}
