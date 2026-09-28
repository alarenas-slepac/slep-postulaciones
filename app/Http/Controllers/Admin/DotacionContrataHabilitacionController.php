<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class DotacionContrataHabilitacionController extends Controller
{
    public function store(Request $request, Establecimiento $establecimiento, ContratacionHabilitacionService $service): RedirectResponse
    {
        $this->authorizeRequest($request, $establecimiento);
        abort_unless(Schema::hasTable('dotacion_contrata_habilitaciones'), 503, 'Debe ejecutar las migraciones para habilitar contrataciones.');
        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'bloque' => ['required', Rule::in(ContratacionHabilitacionService::BLOQUES)],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'horas' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:44'],
        ]);

        $service->habilitar($establecimiento, (int) $data['anio'], $data['bloque'], (int) $data['cantidad'], (float) $data['horas'], $request->user()?->id);

        return redirect()->route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $data['anio']])
            ->withFragment('habilitaciones-contrata')
            ->with('success', 'Funcionarios a contrata habilitados para el bloque seleccionado.');
    }

    public function destroy(Request $request, Establecimiento $establecimiento, int $habilitacion, ContratacionHabilitacionService $service): RedirectResponse
    {
        $this->authorizeRequest($request, $establecimiento);
        $data = $request->validate(['anio' => ['required', 'integer', 'min:2020', 'max:2100']]);
        abort_unless($service->revocar($establecimiento, (int) $data['anio'], $habilitacion), 404);

        return redirect()->route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $data['anio']])
            ->withFragment('habilitaciones-contrata')
            ->with('success', 'Habilitación a contrata retirada.');
    }

    private function authorizeRequest(Request $request, Establecimiento $establecimiento): void
    {
        $user = $request->user();
        $role = $user && method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        abort_unless(in_array($role, ['admin', 'coordinador_uatp', 'coordinador_gdp'], true), 403);
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404);
    }
}
