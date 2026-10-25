<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DotacionPal2027Export;
use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Support\DotacionPal2027;
use Illuminate\Http\Request;

class DotacionPal2027Controller extends Controller
{
    public function __invoke(Request $request, Establecimiento $establecimiento, DotacionPal2027 $datos, DotacionPal2027Export $export)
    {
        abort_unless(DotacionPal2027::canView($request->user()?->activeRoleName()), 403);
        abort_if((bool) $establecimiento->sala_cuna, 404);
        $validated = $request->validate(['descargar' => ['sometimes', 'boolean'], 'anio' => ['sometimes', 'integer', 'in:2027']]);
        $reporte = $datos->construir($establecimiento, $datos->datos($establecimiento), $export->tablas());
        if ($validated['descargar'] ?? false) { return $export->download($reporte, (string) $establecimiento->rbd); }
        return response()->view('admin.dotacion-establecimiento.pal-2027', compact('establecimiento', 'reporte'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
