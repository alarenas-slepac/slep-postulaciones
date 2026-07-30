<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ViaticoReembolsoValor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ViaticoReembolsoValorController extends Controller
{
    public function index(Request $request): View
    {
        $query = ViaticoReembolsoValor::query();

        if ($request->filled('estamento')) {
            $query->where('estamento', $request->string('estamento'));
        }

        if ($request->filled('cargo_funcion')) {
            $query->where('cargo_funcion', 'like', '%' . $request->string('cargo_funcion') . '%');
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('fecha_referencia')) {
            $fecha = $request->date('fecha_referencia')->toDateString();
            $query->whereDate('vigente_desde', '<=', $fecha)
                ->where(function ($q) use ($fecha) {
                    $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha);
                });
        }

        $valores = $query
            ->orderBy('estamento')
            ->orderBy('cargo_funcion')
            ->orderByDesc('vigente_desde')
            ->paginate(25)
            ->withQueryString();

        return view('admin.viaticos-reembolsos.index', [
            'valores' => $valores,
            'estamentos' => ViaticoReembolsoValor::estamentos(),
            'cargosPorEstamento' => ViaticoReembolsoValor::cargosPorEstamento(),
            'filters' => $request->only(['estamento', 'cargo_funcion', 'activo', 'fecha_referencia']),
        ]);
    }

    public function create(): View
    {
        return view('admin.viaticos-reembolsos.create', [
            'valor' => new ViaticoReembolsoValor(['activo' => true]),
            'estamentos' => ViaticoReembolsoValor::estamentos(),
            'cargosPorEstamento' => ViaticoReembolsoValor::cargosPorEstamento(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['activo'] = $request->boolean('activo');
        $data['valor_60'] = $data['valor_60'] ?? (int) round(((int) $data['valor_100']) * 0.60);

        ViaticoReembolsoValor::create($data);

        return redirect()
            ->route('admin.viaticos-reembolsos.index')
            ->with('success', 'Valor de viático/reembolso registrado correctamente.');
    }

    public function edit(ViaticoReembolsoValor $viaticos_reembolso): View
    {
        return view('admin.viaticos-reembolsos.edit', [
            'valor' => $viaticos_reembolso,
            'estamentos' => ViaticoReembolsoValor::estamentos(),
            'cargosPorEstamento' => ViaticoReembolsoValor::cargosPorEstamento(),
        ]);
    }

    public function update(Request $request, ViaticoReembolsoValor $viaticos_reembolso): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['activo'] = $request->boolean('activo');
        $data['valor_60'] = $data['valor_60'] ?? (int) round(((int) $data['valor_100']) * 0.60);

        $viaticos_reembolso->update($data);

        return redirect()
            ->route('admin.viaticos-reembolsos.index')
            ->with('success', 'Valor de viático/reembolso actualizado correctamente.');
    }

    public function destroy(ViaticoReembolsoValor $viaticos_reembolso): RedirectResponse
    {
        $viaticos_reembolso->delete();

        return redirect()
            ->route('admin.viaticos-reembolsos.index')
            ->with('success', 'Valor eliminado correctamente.');
    }

    public function activarVigentes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fecha_referencia' => ['required', 'date'],
        ]);

        $fecha = $data['fecha_referencia'];

        ViaticoReembolsoValor::query()->update(['activo' => false]);

        $activados = ViaticoReembolsoValor::query()
            ->whereDate('vigente_desde', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $fecha);
            })
            ->update(['activo' => true]);

        return redirect()
            ->route('admin.viaticos-reembolsos.index', ['fecha_referencia' => $fecha])
            ->with('success', "Se activaron {$activados} valores vigentes al {$fecha}.");
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'estamento' => ['required', 'string', 'max:100'],
            'cargo_funcion' => ['required', 'string', 'max:150'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'valor_100' => ['required', 'integer', 'min:0'],
            'valor_60' => ['nullable', 'integer', 'min:0'],
            'valor_40' => ['required', 'integer', 'min:0'],
        ]);
    }
}
