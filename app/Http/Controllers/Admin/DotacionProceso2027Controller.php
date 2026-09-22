<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DotacionProceso2027Controller extends Controller
{
    private array $allowedRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

    private array $maximosRoles = ['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

    public function update(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $role = $this->authorizeScope($request, $establecimiento);
        $data = $request->validate([
            'anio' => ['required', 'integer', Rule::in([2027])],
            'decision_combinacion' => ['nullable', Rule::in(array_keys(DotacionProceso2027Configuracion::COMBINACIONES))],
            'observacion_combinacion' => ['nullable', 'string', 'max:2000'],
            'max_horas_bloque_1' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'max_horas_bloque_2' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'max_horas_bloque_3' => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ]);

        $config = DotacionProceso2027Configuracion::firstOrNew([
            'establecimiento_id' => $establecimiento->id,
            'anio' => 2027,
        ]);

        if ($request->has('decision_combinacion')) {
            $config->decision_combinacion = $data['decision_combinacion'];
            $config->observacion_combinacion = trim((string) ($data['observacion_combinacion'] ?? '')) ?: null;
            $config->combinacion_confirmada_by = $request->user()?->id;
            $config->combinacion_confirmada_at = now();
        }

        $incluyeMaximos = collect(['max_horas_bloque_1', 'max_horas_bloque_2', 'max_horas_bloque_3'])
            ->contains(fn ($field) => $request->has($field));
        if ($incluyeMaximos) {
            abort_unless(in_array($role, $this->maximosRoles, true), 403);
            foreach (['max_horas_bloque_1', 'max_horas_bloque_2', 'max_horas_bloque_3'] as $field) {
                $config->{$field} = $data[$field] ?? null;
            }
            $config->maximos_configurados_by = $request->user()?->id;
            $config->maximos_configurados_at = now();
        }

        $config->save();

        return back()->with('success', 'Configuración del proceso de dotación 2027 actualizada.');
    }

    private function authorizeScope(Request $request, Establecimiento $establecimiento): string
    {
        $role = $request->user() && method_exists($request->user(), 'activeRoleName')
            ? $request->user()->activeRoleName()
            : null;
        abort_unless(in_array($role, $this->allowedRoles, true), 403);
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404);
        if ($role === 'funcionario_directivo_estab') {
            abort_unless((int) $establecimiento->id === (int) ($request->user()->establecimiento_id ?? 0), 403);
        }

        return $role;
    }
}
