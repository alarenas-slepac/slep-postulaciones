<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Dotacion\NoRenovacionService;
use App\Services\Dotacion\CuposBirService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DotacionContrataHabilitacionController extends Controller
{
    public function bir(Request $request, Establecimiento $establecimiento, CuposBirService $service): View
    {
        $this->authorizeRequest($request, $establecimiento);
        $data = $request->validate([
            'anio' => ['required', 'integer', 'between:2027,2100'],
            'bloque' => ['nullable', Rule::in(ContratacionHabilitacionService::BLOQUES)],
            'q' => ['nullable', 'string', 'max:150'],
        ]);
        $items = $service->resumen($establecimiento, (int) $data['anio']);
        $visibles = $items->filter(fn ($item) => (empty($data['bloque']) || $item['bloque'] === $data['bloque'])
            && (empty($data['q']) || Str::contains(Str::lower(Str::ascii($item['titulo'])), Str::lower(Str::ascii($data['q'])))));

        return view('admin.dotacion-establecimiento.cupos-bir', [
            'establecimiento' => $establecimiento, 'anio' => (int) $data['anio'], 'filtros' => $data,
            'items' => $visibles, 'disponible' => CuposBirService::disponible(),
        ]);
    }

    public function generarBir(Request $request, Establecimiento $establecimiento, CuposBirService $service): RedirectResponse
    {
        $this->authorizeRequest($request, $establecimiento);
        $data = $request->validate([
            'anio' => ['required', 'integer', 'between:2027,2100'],
            'cupos' => ['required', 'array', 'min:1', 'max:1000'],
            'cupos.*' => ['required', 'string', 'distinct', 'regex:/^\d+:(plan_estudio|parvularia|pie)$/D'],
            'huellas' => ['required', 'array', 'max:1000'],
            'huellas.*' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ]);
        $creados = $service->generar($establecimiento, (int) $data['anio'], $data['cupos'], $data['huellas'], $request->user()?->id);

        return redirect()->route('admin.dotacion-establecimiento.bir.index', [$establecimiento, 'anio' => $data['anio']])
            ->with('success', $creados.' cupo(s) BIR creado(s). Los cupos ya existentes no se duplicaron. Sus horas cuentan en la dotación del año destino y quedan sin asignaciones.');
    }

    public function store(Request $request, Establecimiento $establecimiento, ContratacionHabilitacionService $service): RedirectResponse
    {
        $this->authorizeRequest($request, $establecimiento);
        abort_unless(Schema::hasTable('dotacion_contrata_habilitaciones'), 503, 'Debe ejecutar las migraciones para habilitar contrataciones.');
        if ($request->input('accion') === 'anular_retiro') {
            $data = $request->validate([
                'anio' => ['required', 'integer', 'between:2020,2100'],
                'no_renovacion_id' => ['required', 'integer', 'min:1'],
            ]);
            abort_unless(app(NoRenovacionService::class)->anular($establecimiento, (int) $data['anio'],
                (int) $data['no_renovacion_id'], $request->user()?->id), 404);
            $mensaje = 'Retiro anulado. El docente vuelve a la dotación de '.$data['anio'].'. Se retiraron sus cupos de reemplazo sin reactivar las asignaciones anteriores.';

            return redirect()->route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $data['anio']])
                ->withFragment('no-renovaciones-historial')
                ->with('success', $mensaje)->with('contrata_success', $mensaje);
        }
        if ($request->input('accion') === 'reemplazar') {
            $data = $request->validate([
                'anio' => ['required', 'integer', 'between:2027,2100'],
                'bloque' => ['required', Rule::in(array_keys(NoRenovacionService::BLOQUES))],
                'docente_rut' => ['required', 'string', 'max:32'],
                'justificacion' => ['required', 'string', 'min:10', 'max:3000'],
            ]);
            app(NoRenovacionService::class)->reemplazar($establecimiento, (int) $data['anio'],
                $data['bloque'], $data['docente_rut'], $data['justificacion'], $request->user()?->id);
            $mensaje = 'No renovación registrada. Se crearon los cupos Docente por contratar sin asignaciones ni asociaciones a asignaturas; conservan el título y el total de horas del contrato reemplazado.';

            return redirect()->route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $data['anio']])
                ->withFragment('habilitaciones-contrata')
                ->with('success', $mensaje)->with('contrata_success', $mensaje);
        }
        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'bloque' => ['required', Rule::in(ContratacionHabilitacionService::BLOQUES)],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100'],
            'horas' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:44'],
        ]);

        $service->habilitar($establecimiento, (int) $data['anio'], $data['bloque'], (int) $data['cantidad'], (float) $data['horas'], $request->user()?->id);
        $mensaje = 'Docentes por contratar creados para '.$data['anio'].'. Sus horas ya se suman al contrato del bloque seleccionado y al total del establecimiento. Ya puede asignarles horas.';

        return redirect()->route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $data['anio']])
            ->withFragment('habilitaciones-contrata')
            ->with('success', $mensaje)->with('contrata_success', $mensaje);
    }

    public function destroy(Request $request, Establecimiento $establecimiento, int $habilitacion, ContratacionHabilitacionService $service): RedirectResponse
    {
        $this->authorizeRequest($request, $establecimiento);
        $data = $request->validate(['anio' => ['required', 'integer', 'min:2020', 'max:2100']]);
        abort_unless($service->revocar($establecimiento, (int) $data['anio'], $habilitacion), 404);
        $mensaje = 'Cupo retirado. Si correspondía a un reemplazo, la no renovación quedó anulada y el docente vuelve a la dotación sin reactivar las asignaciones anteriores.';

        return redirect()->route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $data['anio']])
            ->withFragment('habilitaciones-contrata')
            ->with('success', $mensaje)->with('contrata_success', $mensaje);
    }

    private function authorizeRequest(Request $request, Establecimiento $establecimiento): void
    {
        $user = $request->user();
        $role = $user && method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
        abort_unless(in_array($role, ['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'], true), 403);
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404);
    }
}
