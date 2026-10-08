<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionDocenteExclusion;
use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DotacionDocenteExclusionController extends Controller
{
    public const ROLES_GESTION = ['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

    private array $allowedRoles = self::ROLES_GESTION;

    public function store(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $this->authorizeScope($request, $establecimiento);
        abort_unless(Schema::hasTable('dotacion_docente_exclusiones'), 500, 'Debe ejecutar las migraciones antes de registrar situaciones docentes.');

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'docente_rut' => ['required', 'string', 'max:20'],
            'motivo' => ['required', Rule::in(array_keys(DotacionDocenteExclusion::MOTIVOS))],
            'horas_necesarias' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'horas' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'considerar_dotacion_siguiente' => ['sometimes', 'required', 'boolean'],
            'conservar_horas_necesarias' => ['sometimes', 'required', 'boolean'],
            'posee_fuero_maternal' => ['sometimes', 'required', 'boolean'],
        ]);

        if (array_key_exists('considerar_dotacion_siguiente', $data) && ! DotacionDocenteExclusion::continuidadDisponible()) {
            throw ValidationException::withMessages([
                'considerar_dotacion_siguiente' => 'Debe ejecutar la migración de continuidad docente antes de guardar esta decisión.',
            ]);
        }

        $anio = (int) $data['anio'];
        if (array_key_exists('conservar_horas_necesarias', $data) && ! DotacionDocenteExclusion::conservacionHorasDisponible()) {
            throw ValidationException::withMessages([
                'conservar_horas_necesarias' => 'Debe ejecutar la migración de conservación de horas necesarias antes de guardar esta decisión.',
            ]);
        }
        $rutNormalizado = DotacionEstablecimientoCalculator::normalizeRut((string) $data['docente_rut']);
        $fueroMaternalDisponible = DotacionDocenteExclusion::fueroMaternalDisponible();
        if (array_key_exists('posee_fuero_maternal', $data)) {
            if (! $fueroMaternalDisponible) {
                throw ValidationException::withMessages([
                    'posee_fuero_maternal' => 'Debe ejecutar la migración de fuero maternal antes de guardar esta decisión.',
                ]);
            }
            if ($request->boolean('posee_fuero_maternal') && $data['motivo'] !== 'horas_lactancia') {
                throw ValidationException::withMessages([
                    'posee_fuero_maternal' => 'Esta opción corresponde a la situación Horas de lactancia. Para registrar sólo fuero, seleccione Fuero maternal.',
                ]);
            }
        }
        $docente = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio)
            ->first(fn (array $item) => ($item['rut_normalizado'] ?? '') === $rutNormalizado);

        if (! $docente) {
            throw ValidationException::withMessages([
                'docente_rut' => 'El docente no pertenece a la nómina vigente del establecimiento y año seleccionados.',
            ]);
        }

        $horasBase = (float) ($docente['horas_contrato_base'] ?? $docente['horas_contrato'] ?? 0);
        $horasNecesarias = round((float) $data['horas_necesarias'], 2);
        $horasExcluidas = round((float) $data['horas'], 2);
        if ($data['motivo'] === 'proceso_bir') {
            // Durante el año seleccionado, Proceso BIR conserva la jornada
            // contractual completa. La continuidad define por separado si
            // ésta se representa como vacante en el año siguiente.
            $horasNecesarias = $horasBase;
            $horasExcluidas = 0.0;
        }
        // Compara centésimas para exigir igualdad, sin tolerar una diferencia
        // de 0,01 h. Las asignaciones no limitan la distribución contractual.
        if ($horasBase <= 0.0
            || (int) round($horasNecesarias * 100) + (int) round($horasExcluidas * 100) !== (int) round($horasBase * 100)) {
            throw ValidationException::withMessages([
                'horas' => sprintf(
                    'Las horas necesarias y no necesarias deben sumar el contrato original vigente de %s hora(s), que debe ser mayor que cero.',
                    DotacionEstablecimientoCalculator::formatHoras($horasBase)
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

        // Formularios antiguos que omitan el campo conservan la decisión previa.
        if (array_key_exists('considerar_dotacion_siguiente', $data)) {
            $exclusion->considerar_dotacion_siguiente = $request->boolean('considerar_dotacion_siguiente');
        }
        if (array_key_exists('conservar_horas_necesarias', $data)) {
            $exclusion->conservar_horas_necesarias = $request->boolean('conservar_horas_necesarias');
        }
        if ($fueroMaternalDisponible) {
            if ($data['motivo'] !== 'horas_lactancia') {
                $exclusion->posee_fuero_maternal = false;
            } elseif (array_key_exists('posee_fuero_maternal', $data)) {
                $exclusion->posee_fuero_maternal = $request->boolean('posee_fuero_maternal');
            }
        }

        // Conserva el campo histórico: "horas" son las no necesarias.
        // Las necesarias se obtienen como contrato vigente menos estas horas,
        // manteniendo la suma incluso cuando se actualiza el padrón.
        DB::transaction(fn () => $exclusion->fill([
            'docente_rut' => (string) ($docente['rut'] ?? $data['docente_rut']),
            'docente_nombre' => (string) ($docente['nombre'] ?? 'Docente'),
            'motivo' => (string) $data['motivo'],
            'horas' => $horasExcluidas,
            'updated_by' => $request->user()?->id,
        ])->save());

        return redirect()->route('admin.dotacion-establecimiento.show', [
            $establecimiento,
            'anio' => $anio,
            'tab' => 'docentes',
        ])->with('success', 'Situación docente guardada. Se actualizaron la distribución contractual y las decisiones de continuidad y conservación de horas para la proyección.');
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
    }
}
