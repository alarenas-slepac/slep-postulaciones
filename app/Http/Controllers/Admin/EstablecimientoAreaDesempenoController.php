<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AreaDesempeno;
use App\Models\Establecimiento;
use App\Models\EstablecimientoAreaDesempeno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstablecimientoAreaDesempenoController extends Controller
{
    public function edit(Establecimiento $establecimiento)
    {
        $this->ensureDefaults($establecimiento->id);

        $areas = AreaDesempeno::query()
            ->activos()
            ->orderBy('estamento')
            ->orderBy('nombre')
            ->get();

        $bloqueadas = EstablecimientoAreaDesempeno::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->pluck('bloqueada', 'area_desempeno_id')
            ->map(fn($v) => (bool) $v)
            ->all();

        return view('admin.establecimientos.areas-desempeno-bloqueadas', [
            'establecimiento' => $establecimiento,
            'areas' => $areas,
            'bloqueadas' => $bloqueadas,
        ]);
    }

    public function update(Request $request, Establecimiento $establecimiento)
    {
        $request->validate([
            'bloqueadas' => ['nullable', 'array'],
            'bloqueadas.*' => ['integer', 'exists:areas_desempeno,id'],
        ]);

        $this->ensureDefaults($establecimiento->id);

        $activos = AreaDesempeno::query()->activos()->pluck('id')->map(fn($v) => (int) $v);

        $bloqueadas = collect($request->input('bloqueadas', []))
            ->map(fn($v) => (int) $v)
            ->intersect($activos)
            ->values();

        DB::transaction(function () use ($establecimiento, $activos, $bloqueadas) {
            $now = now();

            // primero “limpiamos” todas las activas
            EstablecimientoAreaDesempeno::query()
                ->where('establecimiento_id', $establecimiento->id)
                ->whereIn('area_desempeno_id', $activos)
                ->update(['bloqueada' => false, 'updated_at' => $now]);

            // luego marcamos las seleccionadas
            if ($bloqueadas->isNotEmpty()) {
                EstablecimientoAreaDesempeno::query()
                    ->where('establecimiento_id', $establecimiento->id)
                    ->whereIn('area_desempeno_id', $bloqueadas)
                    ->update(['bloqueada' => true, 'updated_at' => $now]);
            }
        });

        return redirect()
            ->route('admin.establecimientos.areas-desempeno-bloqueadas.edit', $establecimiento)
            ->with('status', 'Configuración guardada.');
    }

    /**
     * Asegura 1 fila por cada área activa para el establecimiento.
     * Esto deja el “mantenedor” listo, sin depender de seeders.
     */
    private function ensureDefaults(int $establecimientoId): void
    {
        $areaIds = AreaDesempeno::query()->activos()->pluck('id');
        if ($areaIds->isEmpty()) return;

        $existing = EstablecimientoAreaDesempeno::query()
            ->where('establecimiento_id', $establecimientoId)
            ->pluck('area_desempeno_id');

        $missing = $areaIds->diff($existing);
        if ($missing->isEmpty()) return;

        $now = now();
        $rows = $missing->map(fn($id) => [
            'establecimiento_id' => $establecimientoId,
            'area_desempeno_id' => (int) $id,
            'bloqueada' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        EstablecimientoAreaDesempeno::query()->insert($rows);
    }
}
