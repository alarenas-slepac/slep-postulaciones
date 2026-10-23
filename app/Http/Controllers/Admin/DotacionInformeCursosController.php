<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DotacionInformeCursosExport;
use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Support\DotacionInformeCursos;
use App\Support\DotacionInformeFunciones;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DotacionInformeCursosController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(DotacionInformeFunciones::puedeVer($request->user()?->activeRoleName()), 403);
        $data = $request->validate([
            'anio' => ['sometimes', 'required', 'integer', 'between:2020,2100'],
            'q' => ['nullable', 'string', 'max:150'], 'comuna' => ['nullable', 'string', 'max:120'],
            'seleccion' => ['sometimes', 'boolean'], 'export_cursos' => ['sometimes', 'boolean'],
        ]);
        $anio = (int) ($data['anio'] ?? now()->year);
        $q = trim((string) ($data['q'] ?? ''));
        $comuna = trim((string) ($data['comuna'] ?? ''));
        $calculador = app(DotacionInformeCursos::class);
        $catalogo = $calculador->niveles($anio);
        $seleccionados = $catalogo->pluck('id')->map(fn ($id) => (string) $id)->all();
        if ($request->has('seleccion') || $request->has('niveles')) {
            $seleccionados = $request->validate([
                'niveles' => ['required', 'array', 'min:1', 'max:500'],
                'niveles.*' => ['required', 'integer', 'distinct', Rule::in($seleccionados)],
            ], ['niveles.required' => 'Seleccione al menos un nivel para generar el informe.'])['niveles'];
            $seleccionados = array_map('strval', $seleccionados);
        }
        $base = Establecimiento::query()->where(fn ($query) => $query->whereNull('sala_cuna')->orWhere('sala_cuna', false));
        $establecimientos = (clone $base)
            ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('rbd', 'like', "%{$q}%")->orWhere('nombre_establecimiento', 'like', "%{$q}%")->orWhere('comuna', 'like', "%{$q}%")))
            ->when($comuna !== '', fn ($query) => $query->where('comuna', $comuna))
            ->orderBy('comuna')->orderBy('nombre_establecimiento')->orderBy('id')
            ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna']);
        try {
            $informe = $calculador->construir($establecimientos, $anio, $catalogo, $seleccionados);
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['informe_cursos' => 'No fue posible generar el informe. Revise la configuración de los grupos combinados o contacte a Administración.']);
        }
        $filtros = compact('anio', 'q', 'comuna', 'seleccionados');
        if ($request->boolean('export_cursos')) {
            return app(DotacionInformeCursosExport::class)->download($informe, $catalogo, $filtros);
        }

        return view('admin.dotacion-establecimiento.informe-cursos', $filtros + [
            'informe' => $informe, 'catalogo' => $catalogo,
            'comunas' => (clone $base)->whereNotNull('comuna')->distinct()->orderBy('comuna')->pluck('comuna')->filter(),
        ]);
    }
}
