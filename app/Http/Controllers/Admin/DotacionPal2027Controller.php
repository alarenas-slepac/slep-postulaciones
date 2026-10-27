<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DotacionPal2027Export;
use App\Exports\DotacionPal2027ConsolidadoExport;
use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Support\DotacionPal2027;
use App\Support\DotacionPal2027Consolidado;
use App\Support\DotacionPalPersonal2027;
use Illuminate\Http\Request;

class DotacionPal2027Controller extends Controller
{
    public function todos(Request $request, DotacionPal2027Consolidado $datos, DotacionPal2027ConsolidadoExport $export)
    {
        abort_unless(DotacionPal2027::canView($request->user()?->activeRoleName()), 403);
        $validated = $request->validate(['descargar' => ['sometimes', 'boolean'], 'anio' => ['sometimes', 'integer', 'in:2027']]);
        if ($validated['descargar'] ?? false) {
            // El proveedor puede imponer un límite menor; no requiere colas ni procesos nuevos.
            @set_time_limit(240);
            return $export->descargar($datos->reportes($export->tablas()));
        }
        $establecimientos = $datos->establecimientos()->get(['id','rbd','nombre_establecimiento','comuna']);
        return response()->view('admin.dotacion-establecimiento.pal-2027', compact('establecimientos'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function __invoke(Request $request, Establecimiento $establecimiento, DotacionPal2027 $datos, DotacionPal2027Export $export, DotacionPalPersonal2027 $personal)
    {
        abort_unless(DotacionPal2027::canView($request->user()?->activeRoleName()), 403);
        abort_if((bool) $establecimiento->sala_cuna, 404);
        $validated = $request->validate(['descargar' => ['sometimes', 'boolean'], 'anio' => ['sometimes', 'integer', 'in:2027']]);
        $reporte = $datos->construir($establecimiento, $datos->datos($establecimiento), $export->tablas());
        $reporte += $personal->construir($establecimiento);
        if ($validated['descargar'] ?? false) { return $export->download($reporte, (string) $establecimiento->rbd); }
        return response()->view('admin.dotacion-establecimiento.pal-2027', compact('establecimiento', 'reporte'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
