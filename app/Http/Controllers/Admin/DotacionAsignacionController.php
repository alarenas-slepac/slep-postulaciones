<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionCursoCombinado;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionFuncionEstablecimiento;
use App\Models\DotacionFuncionRegla;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProfesionDocenteResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DotacionAsignacionController extends Controller
{
    private array $allowedRoles = ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

    public function store(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $this->authorizeScope($request, $establecimiento);

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'docente_rut' => ['required', 'string', 'max:32'],
            'estamento_cobertura' => ['required', 'in:docente,asistente'],
            'tipo_asignacion' => ['required', 'string', 'max:64'],
            'subtipo_asignacion' => ['nullable', 'string', 'max:64'],
            'subvencion' => ['nullable', 'string', 'max:80'],
            'necesidad_key' => ['nullable', 'string', 'max:180'],
            'establecimiento_curso_id' => ['nullable', 'integer'],
            'dotacion_curso_combinado_id' => ['nullable', 'integer'],
            'dotacion_curso_combinado_asignatura_id' => ['nullable', 'integer'],
            'plan_estudio_id' => ['nullable', 'integer'],
            'plan_bloque_id' => ['nullable', 'integer'],
            'asignatura_id' => ['nullable', 'integer'],
            'asignatura_nombre' => ['nullable', 'string', 'max:255'],
            'dotacion_funcion_id' => ['nullable', 'integer', 'min:1'],
            'dotacion_funcion_regla_id' => ['nullable', 'integer', 'min:1'],
            'horas_plan_pedagogicas' => ['nullable', 'numeric', 'min:0'],
            'horas_contrato' => ['nullable', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $persona = $this->findPersonal(
            $establecimiento,
            (int) $data['anio'],
            $data['docente_rut'],
            (string) $data['estamento_cobertura']
        );
        if (! $persona) {
            $label = $data['estamento_cobertura'] === 'asistente' ? 'asistente de la educación' : 'docente';
            return back()->withInput()->withErrors(['docente_rut' => 'La persona seleccionada no corresponde a un '.$label.' vigente del establecimiento.']);
        }

        if (($data['tipo_asignacion'] ?? null) === 'pie_colaborativo' && $this->isEducadoraDiferencialOrCoordinadorPie($persona)) {
            return back()->withInput()->withErrors(['docente_rut' => 'Las horas de trabajo colaborativo PIE no pueden asignarse a Educadora Diferencial ni a Coordinador/a PIE.']);
        }

        $this->validatePlanHoursAvailable($establecimiento, $data);
        $payload = $this->buildPayload($request, $establecimiento, $persona, $data);
        DotacionDocenteAsignacion::create($payload);

        return back()->with('success', 'Asignación de horas guardada correctamente.');
    }

    public function update(Request $request, Establecimiento $establecimiento, DotacionDocenteAsignacion $asignacion): RedirectResponse
    {
        $this->authorizeScope($request, $establecimiento);
        abort_unless((int) $asignacion->establecimiento_id === (int) $establecimiento->id, 404);

        $data = $request->validate([
            'docente_rut' => ['required', 'string', 'max:32'],
            'estamento_cobertura' => ['nullable', 'in:docente,asistente'],
            'horas_plan_pedagogicas' => ['nullable', 'numeric', 'min:0'],
            'horas_contrato' => ['nullable', 'numeric', 'min:0'],
            'subvencion' => ['nullable', 'string', 'max:80'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $estamentoCobertura = (string) ($data['estamento_cobertura'] ?? $asignacion->estamento_cobertura ?? 'docente');
        $data['estamento_cobertura'] = $estamentoCobertura;
        $persona = $this->findPersonal($establecimiento, (int) $asignacion->anio, $data['docente_rut'], $estamentoCobertura);
        if (! $persona) {
            $label = $estamentoCobertura === 'asistente' ? 'asistente de la educación' : 'docente';
            return back()->withInput()->withErrors(['docente_rut' => 'La persona seleccionada no corresponde a un '.$label.' vigente del establecimiento.']);
        }
        if ($asignacion->tipo_asignacion === 'pie_colaborativo' && $this->isEducadoraDiferencialOrCoordinadorPie($persona)) {
            return back()->withInput()->withErrors(['docente_rut' => 'Las horas de trabajo colaborativo PIE no pueden asignarse a Educadora Diferencial ni a Coordinador/a PIE.']);
        }

        $context = array_merge($asignacion->toArray(), $data, [
            'anio' => $asignacion->anio,
            'tipo_asignacion' => $asignacion->tipo_asignacion,
            'necesidad_key' => $asignacion->necesidad_key,
        ]);
        $this->validatePlanHoursAvailable($establecimiento, $context, $asignacion);

        $request->merge([
            'anio' => $asignacion->anio,
            'tipo_asignacion' => $asignacion->tipo_asignacion,
            'subtipo_asignacion' => $asignacion->subtipo_asignacion,
            'necesidad_key' => $asignacion->necesidad_key,
            'establecimiento_curso_id' => $asignacion->establecimiento_curso_id,
            'dotacion_curso_combinado_id' => $asignacion->dotacion_curso_combinado_id,
            'dotacion_curso_combinado_asignatura_id' => $asignacion->dotacion_curso_combinado_asignatura_id,
            'plan_estudio_id' => $asignacion->plan_estudio_id,
            'plan_bloque_id' => $asignacion->plan_bloque_id,
            'asignatura_id' => $asignacion->asignatura_id,
            'asignatura_nombre' => $asignacion->asignatura_nombre,
            'dotacion_funcion_id' => $asignacion->dotacion_funcion_id,
            'dotacion_funcion_regla_id' => $asignacion->dotacion_funcion_regla_id,
            'estamento_cobertura' => $estamentoCobertura,
        ]);
        $payload = $this->buildPayload($request, $establecimiento, $persona, array_merge($asignacion->toArray(), $data));
        $payload['updated_by'] = $request->user()?->id;
        $asignacion->update($payload);

        return back()->with('success', 'Asignación de horas actualizada correctamente.');
    }

    public function destroy(Request $request, Establecimiento $establecimiento, DotacionDocenteAsignacion $asignacion): RedirectResponse
    {
        $this->authorizeScope($request, $establecimiento);
        abort_unless((int) $asignacion->establecimiento_id === (int) $establecimiento->id, 404);
        $asignacion->delete();

        return back()->with('success', 'Asignación de horas eliminada correctamente.');
    }

    private function buildPayload(Request $request, Establecimiento $establecimiento, array $docente, array $data): array
    {
        $tipo = (string) ($data['tipo_asignacion'] ?? '');
        $estamentoCobertura = (string) ($data['estamento_cobertura'] ?? 'docente');
        $subtipo = $data['subtipo_asignacion'] ?? null;
        $horasPlan = isset($data['horas_plan_pedagogicas']) && $data['horas_plan_pedagogicas'] !== null
            ? (float) $data['horas_plan_pedagogicas']
            : null;
        $horasContrato = isset($data['horas_contrato']) && $data['horas_contrato'] !== null
            ? (float) $data['horas_contrato']
            : 0.0;
        $horasCronologicas = null;
        $proporcion = null;
        $fuente = 'Asignación manual de horas contrato';
        $establecimientoCursoId = $data['establecimiento_curso_id'] ?? null;
        $cursoCombinadoIdValidado = null;
        $cursoCombinadoAsignaturaIdValidado = null;
        $planEstudioId = $data['plan_estudio_id'] ?? null;
        $planBloqueId = $data['plan_bloque_id'] ?? null;
        $asignaturaId = $data['asignatura_id'] ?? null;
        $asignaturaNombre = $data['asignatura_nombre'] ?? null;
        [$dotacionFuncionId, $dotacionFuncionReglaId] = $this->resolveFunctionLinks(
            $establecimiento,
            (int) ($data['anio'] ?? now()->year),
            $data
        );

        if ($tipo === 'plan_estudio') {
            $cursoId = (int) ($data['establecimiento_curso_id'] ?? 0);
            if ($cursoId <= 0) {
                throw ValidationException::withMessages([
                    'establecimiento_curso_id' => 'No fue posible identificar el curso asociado a esta asignatura. Actualice la página e intente nuevamente.',
                ]);
            }

            $curso = EstablecimientoCurso::query()
                ->with(['curso', 'planEstudio'])
                ->where('establecimiento_id', $establecimiento->id)
                ->where('id', $cursoId)
                ->first();

            if (! $curso) {
                throw ValidationException::withMessages([
                    'establecimiento_curso_id' => 'El curso asociado a esta asignatura no existe o no pertenece al establecimiento seleccionado.',
                ]);
            }

            $cursoCombinadoId = (int) ($data['dotacion_curso_combinado_id'] ?? 0);
            $cursoCombinado = null;
            $necesidadCombinada = null;
            if ($cursoCombinadoId > 0) {
                $cursoCombinado = DotacionCursoCombinado::query()
                    ->with('miembros')
                    ->whereKey($cursoCombinadoId)
                    ->where('establecimiento_id', $establecimiento->id)
                    ->where('anio', (int) $data['anio'])
                    ->where('activo', true)
                    ->first();

                if (! $cursoCombinado) {
                    throw ValidationException::withMessages([
                        'dotacion_curso_combinado_id' => 'El grupo de cursos combinados ya no se encuentra activo. Actualice la página e intente nuevamente.',
                    ]);
                }

                $necesidadCombinada = DotacionAsignacionCalculator::planNeedForKey(
                    $establecimiento,
                    (int) $data['anio'],
                    (string) ($data['necesidad_key'] ?? '')
                );
                if (! $necesidadCombinada
                    || (int) ($necesidadCombinada['dotacion_curso_combinado_id'] ?? 0) !== $cursoCombinadoId) {
                    throw ValidationException::withMessages([
                        'necesidad_key' => 'La asignatura no pertenece al curso combinado seleccionado o dejó de estar vigente. Actualice la página e intente nuevamente.',
                    ]);
                }

                $cursoIdNecesidad = (int) ($necesidadCombinada['establecimiento_curso_id'] ?? 0);
                if ($cursoIdNecesidad <= 0
                    || ! $cursoCombinado->miembros->contains('establecimiento_curso_id', $cursoIdNecesidad)) {
                    throw ValidationException::withMessages([
                        'establecimiento_curso_id' => 'No fue posible identificar un curso integrante válido para registrar la asignación combinada.',
                    ]);
                }

                if ($cursoIdNecesidad !== $cursoId) {
                    $curso = EstablecimientoCurso::query()
                        ->with(['curso', 'planEstudio'])
                        ->where('establecimiento_id', $establecimiento->id)
                        ->where('id', $cursoIdNecesidad)
                        ->first();
                    if (! $curso) {
                        throw ValidationException::withMessages([
                            'establecimiento_curso_id' => 'El curso representativo del grupo combinado ya no existe.',
                        ]);
                    }
                    $cursoId = $cursoIdNecesidad;
                }

                $establecimientoCursoId = $cursoIdNecesidad;
                $cursoCombinadoIdValidado = $cursoCombinadoId;
                $cursoCombinadoAsignaturaIdValidado = ! empty($necesidadCombinada['dotacion_curso_combinado_asignatura_id'])
                    ? (int) $necesidadCombinada['dotacion_curso_combinado_asignatura_id']
                    : null;
                $subtipo = 'curso_combinado';
                $planEstudioId = $necesidadCombinada['plan_estudio_id'] ?? null;
                $planBloqueId = null;
                $asignaturaId = $necesidadCombinada['asignatura_id'] ?? null;
                $asignaturaNombre = $necesidadCombinada['asignatura_nombre'] ?? $necesidadCombinada['titulo'] ?? $asignaturaNombre;
            }

            $horasPlan = max(0.0, (float) ($horasPlan ?? 0));
            if ($horasPlan <= 0) {
                throw ValidationException::withMessages([
                    'horas_plan_pedagogicas' => 'Debe ingresar horas plan mayores a 0 para asignar esta asignatura.',
                ]);
            }

            if ($estamentoCobertura === 'asistente') {
                $horasContrato = max(0.0, (float) ($data['horas_contrato'] ?? 0));
                if ($horasContrato <= 0) {
                    throw ValidationException::withMessages([
                        'horas_contrato' => 'Debe indicar las horas de contrato del Asistente de la Educación que cubrirá la asignatura.',
                    ]);
                }
                $horasCronologicas = null;
                $proporcion = 'AAEE';
                $fuente = 'Cobertura por Asistente de la Educación · horas aula y horas contrato informadas manualmente';
            } else {
                $proporcionConfigurada = $cursoCombinado
                    ? (string) ($necesidadCombinada['proporcion_key'] ?? '')
                    : null;
                $calculoNt = DotacionProfesionDocenteResolver::conversionNt(
                    $curso,
                    $horasPlan,
                    $docente,
                    $proporcionConfigurada
                );

                if ($calculoNt !== null) {
                    $horasContrato = (float) ($calculoNt['horas_contrato_equivalente_redondeado'] ?? 0);
                    $horasCronologicas = (float) ($calculoNt['horas_aula_cronologicas'] ?? 0);
                    $proporcion = (string) ($calculoNt['proporcion_label'] ?? '65/35');
                    $fuente = 'Conversión NT1/NT2 según profesión declarada · '
                        .($calculoNt['origen_proporcion_label'] ?? 'Regla profesional')
                        .' · '.($calculoNt['motivo'] ?? '');
                } elseif ($cursoCombinado) {
                    $proporcionKey = (string) ($necesidadCombinada['proporcion_key'] ?? '65_35');
                    $calc = DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula($proporcionKey, $horasPlan);
                    $horasContrato = (float) ($calc['horas_contrato'] ?? 0);
                    $horasCronologicas = null;
                    $proporcion = (string) ($calc['proporcion_label'] ?? DocenteHorasNoLectivasCalculator::proporcionLabel($proporcionKey));
                    $fuente = 'Conversión automática desde horas aula de curso combinado · '.($necesidadCombinada['origen_proporcion_label'] ?? 'Configuración del grupo').' · consolidado contractual en pestaña Docentes';
                } else {
                    $porcentaje = DotacionEstablecimientoCalculator::porcentajePrioritariosPara($establecimiento, (int) $data['anio']);
                    $calc = DotacionEstablecimientoCalculator::contratoEquivalenteAsignacion($curso, $horasPlan, $porcentaje, $subtipo);
                    $horasContrato = (float) ($calc['horas_contrato_equivalente_redondeado'] ?? 0);
                    $horasCronologicas = (float) ($calc['horas_aula_cronologicas'] ?? 0);
                    $proporcion = $calc['proporcion_label'] ?? null;
                    $fuente = 'Conversión automática desde horas aula · '.($calc['origen_proporcion_label'] ?? 'Regla general').' · consolidado contractual en pestaña Docentes';
                }
            }
        }

        return [
            'anio' => (int) ($data['anio'] ?? now()->year),
            'establecimiento_id' => $establecimiento->id,
            'docente_rut' => $docente['rut'],
            'docente_rut_normalizado' => $docente['rut_normalizado'],
            'docente_nombre' => $docente['nombre'],
            'reemplazos_personal_id' => null,
            'declaracion_sostenedor_id' => $docente['declaracion']->id ?? null,
            'estamento_cobertura' => $estamentoCobertura,
            'tipo_asignacion' => $tipo,
            'subtipo_asignacion' => $subtipo,
            'subvencion' => ($data['subvencion'] ?? null) ?: $this->defaultSubvencion($tipo, $subtipo),
            'necesidad_key' => $data['necesidad_key'] ?? null,
            'establecimiento_curso_id' => $establecimientoCursoId,
            'dotacion_curso_combinado_id' => $cursoCombinadoIdValidado,
            'dotacion_curso_combinado_asignatura_id' => $cursoCombinadoAsignaturaIdValidado,
            'plan_estudio_id' => $planEstudioId,
            'plan_bloque_id' => $planBloqueId,
            'asignatura_id' => $asignaturaId,
            'asignatura_nombre' => $asignaturaNombre,
            'dotacion_funcion_id' => $dotacionFuncionId,
            'dotacion_funcion_regla_id' => $dotacionFuncionReglaId,
            'horas_plan_pedagogicas' => $horasPlan,
            'horas_contrato' => $horasContrato,
            'horas_cronologicas_aula' => $horasCronologicas,
            'proporcion_aplicada' => $proporcion,
            'fuente_calculo' => $fuente,
            'observacion' => $data['observacion'] ?? null,
            'estado' => 'activa',
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ];
    }

    private function resolveFunctionLinks(Establecimiento $establecimiento, int $anio, array $data): array
    {
        $funcionId = (int) ($data['dotacion_funcion_id'] ?? 0);
        $reglaId = (int) ($data['dotacion_funcion_regla_id'] ?? 0);

        if ($funcionId > 0) {
            $funcion = DotacionFuncionEstablecimiento::query()
                ->whereKey($funcionId)
                ->where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio)
                ->first();

            if (! $funcion) {
                $funcionId = 0;
            } elseif ($reglaId <= 0 && $funcion->regla_id) {
                $reglaId = (int) $funcion->regla_id;
            }
        }

        if ($reglaId > 0 && ! DotacionFuncionRegla::query()->whereKey($reglaId)->exists()) {
            $reglaId = 0;
        }

        return [
            $funcionId > 0 ? $funcionId : null,
            $reglaId > 0 ? $reglaId : null,
        ];
    }

    private function validatePlanHoursAvailable(
        Establecimiento $establecimiento,
        array $data,
        ?DotacionDocenteAsignacion $current = null
    ): void {
        if (($data['tipo_asignacion'] ?? null) !== 'plan_estudio') {
            return;
        }

        $anio = (int) ($data['anio'] ?? 0);
        $key = trim((string) ($data['necesidad_key'] ?? ''));
        $requested = max(0.0, (float) ($data['horas_plan_pedagogicas'] ?? 0));
        $need = DotacionAsignacionCalculator::planNeedForKey($establecimiento, $anio, $key);

        if (! $need) {
            throw ValidationException::withMessages([
                'necesidad_key' => 'La asignatura seleccionada ya no se encuentra disponible. Actualice la página e intente nuevamente.',
            ]);
        }

        $required = (float) ($need['horas_plan_requeridas'] ?? 0);
        $assigned = (float) ($need['horas_plan_asignadas'] ?? 0);
        $currentHours = $current && $current->necesidad_key === $key
            ? (float) ($current->horas_plan_pedagogicas ?? 0)
            : 0.0;
        $available = max(0.0, round($required - $assigned + $currentHours, 2));

        if ($requested > $available + 0.01) {
            throw ValidationException::withMessages([
                'horas_plan_pedagogicas' => sprintf(
                    'La asignatura dispone de %s hora(s) aula por asignar. No puede registrar %s hora(s).',
                    DotacionEstablecimientoCalculator::formatHoras($available),
                    DotacionEstablecimientoCalculator::formatHoras($requested)
                ),
            ]);
        }
    }

    private function defaultSubvencion(string $tipo, ?string $subtipo): string
    {
        if (in_array($tipo, ['pie_colaborativo', 'pie_educadora_diferencial'], true)) {
            return 'PIE';
        }
        if ($subtipo === 'libre_disposicion') {
            return 'Libre disposición';
        }
        return 'General';
    }

    private function findPersonal(Establecimiento $establecimiento, int $anio, string $rut, string $estamentoCobertura): ?array
    {
        $rutNorm = DotacionEstablecimientoCalculator::normalizeRut($rut);
        $personal = $estamentoCobertura === 'asistente'
            ? DotacionEstablecimientoCalculator::asistentes($establecimiento, $anio)
            : DotacionEstablecimientoCalculator::docentes($establecimiento, $anio);

        return $personal->first(
            fn ($persona) => DotacionEstablecimientoCalculator::normalizeRut($persona['rut_normalizado'] ?? $persona['rut'] ?? '') === $rutNorm
        );
    }

    private function isEducadoraDiferencialOrCoordinadorPie(array $docente): bool
    {
        $texto = Str::of(($docente['funcion'] ?? '').' '.($docente['titulo'] ?? '').' '.($docente['estamento'] ?? ''))
            ->ascii()
            ->upper()
            ->toString();

        return str_contains($texto, 'EDUCADOR DIFERENCIAL')
            || str_contains($texto, 'EDUCADORA DIFERENCIAL')
            || (str_contains($texto, 'COORDINADOR') && str_contains($texto, 'PIE'))
            || (str_contains($texto, 'COORDINADORA') && str_contains($texto, 'PIE'));
    }

    private function authorizeScope(Request $request, Establecimiento $establecimiento): void
    {
        $role = $this->activeRole($request);
        abort_unless(in_array($role, $this->allowedRoles, true), 403);
        abort_if((bool) ($establecimiento->sala_cuna ?? false), 404, 'El establecimiento no participa en el proceso de dotación establecimiento.');
        if ($role === 'funcionario_directivo_estab') {
            abort_unless((int) $establecimiento->id === (int) ($request->user()->establecimiento_id ?? 0), 403);
        }
        abort_unless(Schema::hasTable('dotacion_docente_asignaciones'), 500, 'Debe ejecutar las migraciones antes de asignar horas.');
    }

    private function activeRole(Request $request): ?string
    {
        $user = $request->user();
        return $user && method_exists($user, 'activeRoleName') ? $user->activeRoleName() : null;
    }
}
