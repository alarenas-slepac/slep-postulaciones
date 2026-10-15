<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DotacionInformeFuncionesExport;
use App\Exports\DotacionInformePlanesExport;
use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Support\DotacionInformeFunciones;
use App\Support\DotacionLecturaCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DotacionInformeFuncionesController extends Controller
{
    public function index(Request $request)
    {
        // Control adicional al botón: una URL copiada no otorga acceso.
        abort_unless(DotacionInformeFunciones::puedeVer($request->user()?->activeRoleName()), 403);
        $data = $request->validate([
            'anio' => ['sometimes', 'required', 'integer', Rule::in([2026, 2027])],
            'q' => ['nullable', 'string', 'max:150'],
            'comuna' => ['nullable', 'string', 'max:100'],
            'export_excel' => ['sometimes', 'boolean'],
            'export_planes' => ['sometimes', 'boolean'],
        ]);
        $anio = (int) ($data['anio'] ?? (now()->year === 2026 ? 2026 : 2027));
        $q = trim((string) ($data['q'] ?? ''));
        $comuna = trim((string) ($data['comuna'] ?? ''));
        $base = Establecimiento::query()->where(fn ($query) => $query->whereNull('sala_cuna')->orWhere('sala_cuna', false));
        $filtrados = (clone $base)
            ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('nombre_establecimiento', 'like', "%{$q}%")
                ->orWhere('rbd', 'like', "%{$q}%")
                ->orWhere('comuna', 'like', "%{$q}%")))
            ->when($comuna !== '', fn ($query) => $query->where('comuna', $comuna))
            ->orderBy('comuna')->orderBy('nombre_establecimiento')->orderBy('id');
        if ($request->boolean('export_planes')) {
            return app(DotacionInformePlanesExport::class)->download($filtrados, $anio, ['q' => $q, 'comuna' => $comuna]);
        }
        if ($request->boolean('export_excel')) {
            return app(DotacionInformeFuncionesExport::class)->download($filtrados, $anio, ['q' => $q, 'comuna' => $comuna]);
        }
        $establecimientos = $filtrados->paginate(10)->withQueryString();
        $calculador = app(DotacionInformeFunciones::class);
        $establecimientos->setCollection($establecimientos->getCollection()->map(function (Establecimiento $ee) use ($calculador, $anio): array {
            try {
                return DotacionLecturaCache::ejecutar(fn () => $calculador->fila($ee, $anio));
            } catch (\Throwable $exception) {
                report($exception);
                // No presentar ceros como un resultado válido si falla el cálculo.
                return ['establecimiento_id' => $ee->id, 'rbd' => (string) $ee->rbd,
                    'nombre' => (string) $ee->nombre_establecimiento, 'comuna' => (string) $ee->comuna, 'error' => true];
            } finally {
                $ee->unsetRelations();
            }
        }));
        $validos = $establecimientos->getCollection()->where('error', false);
        $totales = collect(['docentes_total', 'contrato_total', 'contrato_considerado', 'horas_excluidas', 'asignadas', 'otras', 'reservadas', 'sin_asignar', 'sobreasignadas', 'sep', 'sep_sin_desglose'])
            ->mapWithKeys(fn ($key) => [$key => round((float) $validos->sum($key), 2)])->all();
        $totales['grupos'] = collect(DotacionInformeFunciones::GRUPOS)->mapWithKeys(fn ($label, $key) => [$key => [
            'docentes' => $validos->sum('grupos.'.$key.'.docentes'),
            'horas' => round((float) $validos->sum('grupos.'.$key.'.horas'), 2),
        ]])->all();

        return view('admin.dotacion-establecimiento.informe-funciones', [
            'establecimientos' => $establecimientos, 'anio' => $anio, 'q' => $q, 'comuna' => $comuna,
            'comunas' => (clone $base)->whereNotNull('comuna')->distinct()->orderBy('comuna')->pluck('comuna')->filter()->values(),
            'grupos' => DotacionInformeFunciones::GRUPOS, 'totales' => $totales,
            'errores' => $establecimientos->count() - $validos->count(), 'generadoEn' => now(),
        ]);
    }
}
