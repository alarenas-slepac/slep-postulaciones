<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DotacionNominaCompletaExport;
use App\Exports\DotacionNominaDocentesExport;
use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Support\DotacionNominaCompleta;
use App\Support\DotacionNominaDocentes;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class DotacionNominaCompletaController extends Controller
{
    public function index(Request $request, DotacionNominaCompleta $nomina)
    {
        [$filtros, $establecimientos, $seleccion] = $this->contexto($request);
        $filas = $nomina->datos($seleccion, $filtros['anio'], $filtros);
        $items = new LengthAwarePaginator($filas->forPage((int) $request->query('page', 1), 50)->values(), $filas->count(), 50,
            (int) $request->query('page', 1), ['path' => $request->url(), 'query' => $filtros]);

        return view('admin.dotacion-establecimiento.nomina-completa', [
            'anio' => $filtros['anio'], 'filtros' => $filtros, 'establecimientos' => $establecimientos,
            'comunas' => $establecimientos->pluck('comuna')->filter()->unique()->sort()->values(),
            'bloques' => DotacionNominaDocentes::BLOQUES, 'items' => $items, 'resumen' => $nomina->resumen($filas),
        ]);
    }

    public function export(Request $request, DotacionNominaCompleta $nomina, DotacionNominaCompletaExport $export)
    {
        [$filtros, , $seleccion] = $this->contexto($request);

        return $export->download($filtros['anio'], $nomina->datos($seleccion, $filtros['anio'], $filtros), $filtros);
    }

    private function contexto(Request $request): array
    {
        $rol = $request->user()?->activeRoleName();
        abort_unless(DotacionNominaDocentesExport::canExport($rol), 403);
        $datos = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2020,2100'],
            'bloque' => ['nullable', Rule::in(array_keys(DotacionNominaDocentes::BLOQUES))],
            'comuna' => ['nullable', 'string', 'max:120'], 'q' => ['nullable', 'string', 'max:150'],
            'establecimiento_id' => ['nullable', 'integer', 'min:0'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filtros = [
            'anio' => (int) ($datos['anio'] ?? now()->year), 'bloque' => (string) ($datos['bloque'] ?? ''),
            'comuna' => trim((string) ($datos['comuna'] ?? '')), 'q' => trim((string) ($datos['q'] ?? '')),
            'establecimiento_id' => (int) ($datos['establecimiento_id'] ?? 0),
        ];
        $propio = (int) ($request->user()->establecimiento_id ?? 0);
        if ($rol === 'funcionario_directivo_estab') {
            abort_unless($propio > 0, 403);
            abort_if($filtros['establecimiento_id'] > 0 && $filtros['establecimiento_id'] !== $propio, 403);
        }
        $establecimientos = Establecimiento::query()
            ->where(fn ($q) => $q->whereNull('sala_cuna')->orWhere('sala_cuna', false))
            ->when($rol === 'funcionario_directivo_estab', fn ($q) => $q->whereKey($propio))
            ->orderBy('comuna')->orderBy('nombre_establecimiento')->get();
        if ($filtros['establecimiento_id'] > 0) {
            abort_unless($establecimientos->contains('id', $filtros['establecimiento_id']), 404);
        }
        $seleccion = $establecimientos->filter(fn ($ee) =>
            ($filtros['establecimiento_id'] === 0 || (int) $ee->id === $filtros['establecimiento_id'])
            && ($filtros['comuna'] === '' || $ee->comuna === $filtros['comuna']))->values();

        return [$filtros, $establecimientos, $seleccion];
    }
}
