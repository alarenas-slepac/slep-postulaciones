<?php

namespace App\Http\Controllers\Tramites;

use App\Http\Controllers\Controller;
use App\Models\LicenciaFeriado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LicenciaFeriadoController extends Controller
{
    public function index(Request $request)
    {
        $anio = (int) ($request->input('anio') ?: now()->year);
        $tipo = $request->input('tipo');
        $estado = $request->input('estado', 'activos');

        $query = LicenciaFeriado::query()
            ->whereYear('fecha', $anio)
            ->orderBy('fecha');

        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        if ($estado === 'activos') {
            $query->where('activo', true);
        } elseif ($estado === 'inactivos') {
            $query->where('activo', false);
        }

        $feriados = $query->paginate(30)->withQueryString();

        $metricas = [
            'anio' => $anio,
            'activos' => LicenciaFeriado::whereYear('fecha', $anio)->where('activo', true)->count(),
            'inactivos' => LicenciaFeriado::whereYear('fecha', $anio)->where('activo', false)->count(),
            'total' => LicenciaFeriado::whereYear('fecha', $anio)->count(),
        ];

        return view('tramites.licencias-medicas.feriados.index', compact('feriados', 'metricas', 'anio', 'tipo', 'estado'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fecha' => ['required', 'date'],
            'nombre' => ['required', 'string', 'max:190'],
            'tipo' => ['required', Rule::in(['nacional', 'regional', 'institucional', 'otro'])],
            'activo' => ['nullable', 'boolean'],
        ]);

        $feriado = LicenciaFeriado::updateOrCreate(
            ['fecha' => $data['fecha']],
            [
                'nombre' => $data['nombre'],
                'tipo' => $data['tipo'],
                'activo' => $request->boolean('activo', true),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]
        );

        return redirect()
            ->route('tramites.licencias-medicas.feriados.index', ['anio' => $feriado->fecha->year])
            ->with('success', 'Feriado registrado correctamente.');
    }

    public function update(Request $request, LicenciaFeriado $feriado)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:190'],
            'tipo' => ['required', Rule::in(['nacional', 'regional', 'institucional', 'otro'])],
            'activo' => ['nullable', 'boolean'],
        ]);

        $feriado->update([
            'nombre' => $data['nombre'],
            'tipo' => $data['tipo'],
            'activo' => $request->boolean('activo'),
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Feriado actualizado correctamente.');
    }

    public function destroy(LicenciaFeriado $feriado)
    {
        $feriado->delete();

        return back()->with('success', 'Feriado eliminado correctamente.');
    }
}
