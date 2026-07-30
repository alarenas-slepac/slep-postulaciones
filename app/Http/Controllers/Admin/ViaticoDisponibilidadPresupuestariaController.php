<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ViaticoDisponibilidadPresupuestaria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ViaticoDisponibilidadPresupuestariaController extends Controller
{
    public function index(Request $request): View
    {
        $query = ViaticoDisponibilidadPresupuestaria::query();

        if ($request->filled('anio')) {
            $query->where('anio', (int) $request->input('anio'));
        }

        if ($request->filled('origen_tipo')) {
            $query->where('origen_tipo', $request->string('origen_tipo'));
        }

        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $disponibilidades = $query
            ->orderByDesc('anio')
            ->orderBy('origen_tipo')
            ->orderByDesc('vigente_desde')
            ->paginate(20)
            ->withQueryString();

        $resumen = ViaticoDisponibilidadPresupuestaria::query()
            ->when($request->filled('anio'), fn ($q) => $q->where('anio', (int) $request->input('anio')))
            ->when($request->filled('origen_tipo'), fn ($q) => $q->where('origen_tipo', $request->string('origen_tipo')))
            ->when($request->filled('activo'), fn ($q) => $q->where('activo', $request->boolean('activo')))
            ->selectRaw('COALESCE(SUM(monto_inicial), 0) as monto_inicial')
            ->selectRaw('COALESCE(SUM(monto_comprometido), 0) as monto_comprometido')
            ->selectRaw('COALESCE(SUM(monto_ejecutado), 0) as monto_ejecutado')
            ->selectRaw('COALESCE(SUM(saldo_disponible), 0) as saldo_disponible')
            ->first();

        return view('admin.viaticos-disponibilidad.index', [
            'disponibilidades' => $disponibilidades,
            'origenes' => ViaticoDisponibilidadPresupuestaria::origenes(),
            'filters' => $request->only(['anio', 'origen_tipo', 'activo']),
            'resumen' => $resumen,
        ]);
    }

    public function create(): View
    {
        return view('admin.viaticos-disponibilidad.create', [
            'disponibilidad' => new ViaticoDisponibilidadPresupuestaria([
                'anio' => now()->year,
                'monto_comprometido' => 0,
                'monto_ejecutado' => 0,
                'activo' => true,
            ]),
            'origenes' => ViaticoDisponibilidadPresupuestaria::origenes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['activo'] = $request->boolean('activo');
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $disponibilidad = new ViaticoDisponibilidadPresupuestaria($this->filterAuditableColumns($data));
        $disponibilidad->recalcularSaldo();
        $disponibilidad->save();

        return redirect()
            ->route('admin.viaticos-disponibilidad.index')
            ->with('success', 'Disponibilidad presupuestaria de viáticos registrada correctamente.');
    }

    public function edit(ViaticoDisponibilidadPresupuestaria $viaticos_disponibilidad): View
    {
        return view('admin.viaticos-disponibilidad.edit', [
            'disponibilidad' => $viaticos_disponibilidad,
            'origenes' => ViaticoDisponibilidadPresupuestaria::origenes(),
        ]);
    }

    public function update(Request $request, ViaticoDisponibilidadPresupuestaria $viaticos_disponibilidad): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['activo'] = $request->boolean('activo');
        $data['updated_by'] = auth()->id();

        $viaticos_disponibilidad->fill($this->filterAuditableColumns($data));
        $viaticos_disponibilidad->recalcularSaldo();
        $viaticos_disponibilidad->save();

        return redirect()
            ->route('admin.viaticos-disponibilidad.index')
            ->with('success', 'Disponibilidad presupuestaria de viáticos actualizada correctamente.');
    }

    public function destroy(ViaticoDisponibilidadPresupuestaria $viaticos_disponibilidad): RedirectResponse
    {
        $viaticos_disponibilidad->delete();

        return redirect()
            ->route('admin.viaticos-disponibilidad.index')
            ->with('success', 'Registro de disponibilidad eliminado correctamente.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'origen_tipo' => ['required', 'string', 'in:' . implode(',', array_keys(ViaticoDisponibilidadPresupuestaria::origenes()))],
            'monto_inicial' => ['required', 'integer', 'min:0'],
            'monto_comprometido' => ['nullable', 'integer', 'min:0'],
            'monto_ejecutado' => ['nullable', 'integer', 'min:0'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function filterAuditableColumns(array $data): array
    {
        $table = (new ViaticoDisponibilidadPresupuestaria())->getTable();

        foreach (['created_by', 'updated_by'] as $column) {
            if (!Schema::hasColumn($table, $column)) {
                unset($data[$column]);
            }
        }

        $data['monto_comprometido'] = (int) ($data['monto_comprometido'] ?? 0);
        $data['monto_ejecutado'] = (int) ($data['monto_ejecutado'] ?? 0);

        return $data;
    }
}
