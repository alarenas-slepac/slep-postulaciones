<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Support\DotacionDocentesSubsector;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DotacionProceso2027Controller extends Controller
{
    private array $allowedRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

    private array $maximosRoles = ['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

    private array $funcionesNormativasRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp'];

    public function syncDocentesSubsector(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $this->authorizeScope($request, $establecimiento);
        abort_unless(DotacionDocentesSubsector::disponible(), 503, 'Debe ejecutar la migración de docentes por asignatura.');
        $data = $request->validate([
            'anio' => ['required', 'integer', Rule::in([2027])],
            'asignatura_key' => ['required', 'string', 'size:40'],
            'docentes' => ['required', 'array', 'min:1', 'max:500'],
            'docentes.*' => ['required', 'string', 'max:32', 'distinct'],
        ]);
        $proceso = DotacionProceso2027Calculator::resumen($establecimiento, 2027);
        if (! ($proceso['pasos']['planes']['completo'] ?? false)) {
            throw ValidationException::withMessages(['asignatura_key' => 'Configure primero los planes de estudio del establecimiento.']);
        }
        $asignatura = collect($proceso['docentes_subsector']['asignaturas'])
            ->firstWhere('key', $data['asignatura_key']);
        if (! $asignatura) {
            throw ValidationException::withMessages(['asignatura_key' => 'La asignatura ya no forma parte del plan vigente. Actualice la página.']);
        }
        $vigentes = collect($proceso['docentes_subsector']['docentes'])
            ->filter(fn (array $docente) => DotacionDocentesSubsector::docenteAdmisible($docente, $asignatura['nivel']))
            ->mapWithKeys(fn (array $docente) => [
                DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? '')) => true,
            ]);
        $ruts = collect($data['docentes'])
            ->map(fn ($rut) => DotacionEstablecimientoCalculator::normalizeRut((string) $rut))
            ->filter()->unique()->values();
        if ($ruts->count() !== count($data['docentes']) || $ruts->contains(fn ($rut) => ! $vigentes->has($rut))) {
            throw ValidationException::withMessages(['docentes' => 'Seleccione únicamente docentes vigentes o cupos habilitados del establecimiento.']);
        }

        DB::transaction(function () use ($establecimiento, $asignatura, $ruts, $request): void {
            Establecimiento::query()->whereKey($establecimiento->id)->lockForUpdate()->firstOrFail();
            DB::table('dotacion_docente_subsectores')
                ->where('establecimiento_id', $establecimiento->id)->where('anio', 2027)
                ->where('asignatura_key', $asignatura['key'])->delete();
            DB::table('dotacion_docente_subsectores')->insert($ruts->map(fn ($rut) => [
                'establecimiento_id' => $establecimiento->id,
                'anio' => 2027,
                'asignatura_key' => $asignatura['key'],
                'nivel' => $asignatura['nivel'],
                'asignatura_nombre' => $asignatura['nombre'],
                'docente_rut_normalizado' => $rut,
                'created_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });

        return back()->with('subsector_success', 'Docentes de la asignatura guardados correctamente.');
    }

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
            'funciones_normativas_configuradas' => ['nullable', 'boolean'],
            'funciones_normativas' => ['nullable', 'array'],
            'funciones_normativas.*.key' => ['nullable', 'string', 'max:500'],
            'funciones_normativas.*.usar' => ['nullable', 'boolean'],
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

        if ($request->has('funciones_normativas_configuradas')) {
            abort_unless(in_array($role, $this->funcionesNormativasRoles, true), 403);
            $potenciales = collect(DotacionProceso2027Calculator::resumen($establecimiento, 2027)['funciones_normativas'] ?? [])
                ->keyBy('key');
            $seleccionadas = collect($data['funciones_normativas'] ?? [])
                ->filter(fn ($funcion) => ! empty($funcion['key']) && $potenciales->has($funcion['key']))
                ->mapWithKeys(fn ($funcion) => [(string) $funcion['key'] => (bool) ($funcion['usar'] ?? false)])
                ->all();
            $config->funciones_normativas = $potenciales
                ->mapWithKeys(fn ($funcion, $key) => [$key => (bool) ($seleccionadas[$key] ?? false)])
                ->all();
            $config->funciones_normativas_configuradas_by = $request->user()?->id;
            $config->funciones_normativas_configuradas_at = now();
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
