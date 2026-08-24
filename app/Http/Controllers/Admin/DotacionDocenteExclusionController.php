<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionDocenteExclusion;
use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DotacionDocenteExclusionController extends Controller
{
    private array $allowedRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'];

    public function store(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $this->authorizeScope($request, $establecimiento);
        abort_unless(Schema::hasTable('dotacion_docente_exclusiones'), 500, 'Debe ejecutar las migraciones antes de registrar situaciones docentes.');

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'docente_rut' => ['required', 'string', 'max:20'],
            'motivo' => ['required', Rule::in(array_keys(DotacionDocenteExclusion::MOTIVOS))],
            'horas' => ['required', 'numeric', 'min:0.25', 'max:999'],
        ]);

        $anio = (int) $data['anio'];
        $rutNormalizado = DotacionEstablecimientoCalculator::normalizeRut((string) $data['docente_rut']);
        $docente = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio)
            ->first(fn (array $item) => ($item['rut_normalizado'] ?? '') === $rutNormalizado);

        if (! $docente) {
            throw ValidationException::withMessages([
                'docente_rut' => 'El docente no pertenece a la nómina vigente del establecimiento y año seleccionados.',
            ]);
        }

        $horasBase = (float) ($docente['horas_contrato_base'] ?? $docente['horas_contrato'] ?? 0);
        $horasAsignadas = max(0.0, (float) ($docente['horas_asignadas_total'] ?? 0));
        $horasDisponibles = max(0.0, round($horasBase - $horasAsignadas, 2));
        $horasExcluidas = round((float) $data['horas'], 2);
        if ($horasBase <= 0.0 || $horasDisponibles < 0.25 || $horasExcluidas > $horasDisponibles + 0.01) {
            throw ValidationException::withMessages([
                'horas' => sprintf(
                    'Sólo puede excluir horas contractuales sin asignación. El docente dispone de %s hora(s) por asignar.',
                    DotacionEstablecimientoCalculator::formatHoras($horasDisponibles)
                ),
            ]);
        }

        $exclusion = DotacionDocenteExclusion::query()->firstOrNew([
            'establecimiento_id' => $establecimiento->id,
            'anio' => $anio,
            'docente_rut_normalizado' => $rutNormalizado,
        ]);

        if (! $exclusion->exists) {
            $exclusion->created_by = $request->user()?->id;
        }

        $exclusion->fill([
            'docente_rut' => (string) ($docente['rut'] ?? $data['docente_rut']),
            'docente_nombre' => (string) ($docente['nombre'] ?? 'Docente'),
            'motivo' => (string) $data['motivo'],
            'horas' => $horasExcluidas,
            'updated_by' => $request->user()?->id,
        ])->save();

        return redirect()->route('admin.dotacion-establecimiento.show', [
            $establecimiento,
            'anio' => $anio,
            'tab' => 'docentes',
        ])->with('success', 'Situación docente guardada. Las horas indicadas ya no se consideran en el contrato del establecimiento.');
    }

    public function destroy(
        Request $request,
        Establecimiento $establecimiento,
        DotacionDocenteExclusion $exclusion
    ): RedirectResponse {
        $this->authorizeScope($request, $establecimiento);
        abort_unless((int) $exclusion->establecimiento_id === (int) $establecimiento->id, 404);

        $anio = (int) $exclusion->anio;
        $exclusion->delete();

        return redirect()->route('admin.dotacion-establecimiento.show', [
            $establecimiento,
            'anio' => $anio,
            'tab' => 'docentes',
        ])->with('success', 'Situación docente eliminada. Se restablecieron sus horas contractuales en el cálculo.');
    }

    private function authorizeScope(Request $request, Establecimiento $establecimiento): void
    {
        $user = $request->user();
        $activeRole = $user && method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;

        abort_unless(in_array($activeRole, $this->allowedRoles, true), 403);
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404, 'El establecimiento no participa en el proceso de dotación establecimiento.');

        if ($activeRole === 'funcionario_directivo_estab') {
            abort_unless((int) $establecimiento->id === (int) ($user->establecimiento_id ?? 0), 403);
        }
    }
}
