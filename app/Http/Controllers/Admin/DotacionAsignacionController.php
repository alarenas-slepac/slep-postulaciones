<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DotacionCursoCombinado;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionEstablecimientoConfiguracion;
use App\Models\DotacionFuncionEstablecimiento;
use App\Models\DotacionFuncionRegla;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProfesionDocenteResolver;
use App\Support\DotacionProceso2027Calculator;
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
            'excepcion_prelacion' => ['nullable', 'string', 'max:2000'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->validateDirectorAdpAssignment($establecimiento, (int) $data['anio'], $data);

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
        $this->validateProceso2027Assignment($establecimiento, $persona, $payload);
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
            'excepcion_prelacion' => ['nullable', 'string', 'max:2000'],
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
        $this->validateDirectorAdpAssignment($establecimiento, (int) $asignacion->anio, $context);
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
        $this->validateProceso2027Assignment($establecimiento, $persona, $payload, $asignacion);
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

        if ($tipo === 'pie_colaborativo' && (int) $establecimientoCursoId > 0) {
            $cursoPie = EstablecimientoCurso::with(['curso', 'planEstudio'])
                ->where('establecimiento_id', $establecimiento->id)->find($establecimientoCursoId);
            if ($cursoPie && DotacionProfesionDocenteResolver::esCursoNt($cursoPie)
                && ! \App\Support\DotacionParvulariaCalculator::conJec($cursoPie)
                && ($estamentoCobertura !== 'docente' || ! DotacionProfesionDocenteResolver::perfilTitulo($docente)['es_educacion_parvulos'])) {
                throw ValidationException::withMessages(['docente_rut' => 'NT1/NT2 sin JEC solo admite cobertura por Educadoras de Párvulos, incluido el trabajo colaborativo PIE.']);
            }
        }

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
                $subtipo = ! empty($necesidadCombinada['curso_combinado_libre_disposicion'])
                    ? 'libre_disposicion' : 'curso_combinado';
                $planEstudioId = $necesidadCombinada['plan_estudio_id'] ?? null;
                $planBloqueId = null;
                $asignaturaId = $necesidadCombinada['asignatura_id'] ?? null;
                $asignaturaNombre = $necesidadCombinada['asignatura_nombre'] ?? $necesidadCombinada['titulo'] ?? $asignaturaNombre;
            }

            if ($subtipo === 'libre_disposicion' && DotacionProfesionDocenteResolver::esCursoNt($curso)
                && ! \App\Support\DotacionParvulariaCalculator::conJec($curso, $necesidadCombinada['proporcion_key'] ?? null)) {
                throw ValidationException::withMessages(['subtipo_asignacion' => 'NT1/NT2 sin JEC no contempla libre disposición. Revise el plan asociado.']);
            }

            $horasPlan = max(0.0, (float) ($horasPlan ?? 0));
            if ($horasPlan <= 0) {
                throw ValidationException::withMessages([
                    'horas_plan_pedagogicas' => 'Debe ingresar horas plan mayores a 0 para asignar esta asignatura.',
                ]);
            }

            if ($estamentoCobertura === 'asistente') {
                if (DotacionProfesionDocenteResolver::esCursoNt($curso)
                    && ! \App\Support\DotacionParvulariaCalculator::conJec($curso, $necesidadCombinada['proporcion_key'] ?? null)) {
                    throw ValidationException::withMessages(['docente_rut' => 'NT1/NT2 sin JEC solo admite cobertura por Educadoras de Párvulos.']);
                }
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
                if (DotacionProfesionDocenteResolver::esCursoNt($curso)
                    && ! \App\Support\DotacionParvulariaCalculator::conJec($curso, $proporcionConfigurada)
                    && ! DotacionProfesionDocenteResolver::perfilTitulo($docente)['es_educacion_parvulos']) {
                    throw ValidationException::withMessages(['docente_rut' => 'NT1/NT2 sin JEC solo admite cobertura por Educadoras de Párvulos. Revise el título declarado.']);
                }
                $calculoNt = DotacionProfesionDocenteResolver::conversionNt(
                    $curso,
                    $horasPlan,
                    $docente,
                    $proporcionConfigurada,
                    $necesidadCombinada
                );

                if ($calculoNt !== null) {
                    if (DotacionProfesionDocenteResolver::perfilTitulo($docente)['es_educacion_parvulos']
                        && (float) ($calculoNt['parvularia_horas_plan_total'] ?? 0) <= 0) {
                        throw ValidationException::withMessages(['horas_plan_pedagogicas' => 'Configure el total del plan de Parvularia antes de asignar horas.']);
                    }
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

        if ($tipo === 'pie_colaborativo' && (int) ($data['dotacion_curso_combinado_id'] ?? 0) > 0) {
            $cursoCombinadoId = (int) $data['dotacion_curso_combinado_id'];
            $cursoId = (int) ($data['establecimiento_curso_id'] ?? 0);
            $cursoCombinado = DotacionCursoCombinado::query()
                ->with('miembros')
                ->whereKey($cursoCombinadoId)
                ->where('establecimiento_id', $establecimiento->id)
                ->where('anio', (int) ($data['anio'] ?? 0))
                ->where('activo', true)
                ->first();

            if (! $cursoCombinado
                || $cursoId <= 0
                || ! $cursoCombinado->miembros->contains('establecimiento_curso_id', $cursoId)
                || (string) ($data['necesidad_key'] ?? '') !== DotacionCursoCombinadoCalculator::collaborativePieNeedKey($cursoCombinadoId)) {
                throw ValidationException::withMessages([
                    'necesidad_key' => 'La necesidad de trabajo colaborativo PIE del grupo combinado ya no se encuentra vigente. Actualice la página e intente nuevamente.',
                ]);
            }

            $cursoCombinadoIdValidado = $cursoCombinadoId;
            $fuente = 'Asignación manual de trabajo colaborativo PIE consolidado por grupo combinado';
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
            'excepcion_prelacion' => trim((string) ($data['excepcion_prelacion'] ?? '')) ?: null,
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

    private function validateProceso2027Assignment(
        Establecimiento $establecimiento,
        array $persona,
        array $payload,
        ?DotacionDocenteAsignacion $current = null
    ): void {
        $anio = (int) ($payload['anio'] ?? 0);
        if (! DotacionProceso2027Calculator::aplica($anio)
            || ($payload['estamento_cobertura'] ?? 'docente') !== 'docente') {
            return;
        }

        $proceso = DotacionProceso2027Calculator::resumen($establecimiento, $anio);
        if (! ($proceso['asignacion_habilitada'] ?? false)) {
            throw ValidationException::withMessages([
                'anio' => 'Para asignar horas en 2027 debe completar planes de estudio, declarar la combinación de cursos y configurar máximos suficientes para los tres bloques.',
            ]);
        }

        $bloque = data_get($proceso, 'need_blocks.'.($payload['necesidad_key'] ?? ''))
            ?: DotacionProceso2027Calculator::bloqueParaAsignacion($payload);
        if (! $bloque) {
            return;
        }

        $horas = max(0.0, (float) ($payload['horas_contrato'] ?? 0));
        $bloqueProceso = data_get($proceso, 'bloques.'.$bloque, []);
        $asignadas = (float) ($bloqueProceso['asignadas'] ?? 0);
        if ($current) {
            $bloqueActual = data_get($proceso, 'need_blocks.'.($current->necesidad_key ?? ''))
                ?: DotacionProceso2027Calculator::bloqueParaAsignacion($current);
            if ($bloqueActual === $bloque) {
                $asignadas = max(0.0, $asignadas - (float) $current->horas_contrato);
            }
        }
        $maximo = $bloqueProceso['maximo'] ?? null;
        if ($maximo !== null && $asignadas + $horas > (float) $maximo + 0.01) {
            throw ValidationException::withMessages([
                'horas_contrato' => 'La asignación supera el máximo autorizado del bloque '.$bloqueProceso['label'].'.',
            ]);
        }

        $asignadasPersona = max(0.0, (float) ($persona['horas_asignadas_total'] ?? 0));
        if ($current && DotacionEstablecimientoCalculator::normalizeRut((string) $current->docente_rut_normalizado)
            === DotacionEstablecimientoCalculator::normalizeRut((string) ($persona['rut_normalizado'] ?? ''))) {
            $asignadasPersona = max(0.0, $asignadasPersona - (float) $current->horas_contrato);
        }
        $disponibles = max(0.0, (float) ($persona['horas_contrato'] ?? 0) - $asignadasPersona);
        if ($horas > $disponibles + 0.01) {
            throw ValidationException::withMessages([
                'horas_contrato' => 'La persona seleccionada dispone de '.$disponibles.' hora(s) de contrato para asignar.',
            ]);
        }

        $rut = DotacionEstablecimientoCalculator::normalizeRut((string) ($persona['rut_normalizado'] ?? $persona['rut'] ?? ''));
        $seleccionado = collect($proceso['docentes'] ?? [])->first(
            fn (array $docente) => ($docente['rut_normalizado'] ?? '') === $rut
        );
        $prioridad = (int) ($seleccionado['prioridad_2027'] ?? 6);
        $hayPrioridadAnterior = collect($proceso['docentes'] ?? [])->contains(
            fn (array $docente) => (int) ($docente['prioridad_2027'] ?? 6) < $prioridad
                && (float) ($docente['horas_disponibles'] ?? 0) > 0.01
        );
        if ($hayPrioridadAnterior && blank($payload['excepcion_prelacion'] ?? null)) {
            throw ValidationException::withMessages([
                'excepcion_prelacion' => 'Existen docentes de prioridad superior con horas disponibles. Para continuar debe indicar una justificación de excepción.',
            ]);
        }

        if ((int) ($payload['dotacion_funcion_id'] ?? 0) > 0
            && ! ($proceso['funciones_no_normativas_habilitadas'] ?? false)) {
            throw ValidationException::withMessages([
                'dotacion_funcion_id' => 'Las funciones no normativas se habilitan sólo cuando todas las necesidades obligatorias estén cubiertas.',
            ]);
        }
    }

    private function validateDirectorAdpAssignment(Establecimiento $establecimiento, int $anio, array $data): void
    {
        $reglaId = (int) ($data['dotacion_funcion_regla_id'] ?? 0);
        $esDirectorAdp = $reglaId > 0 && DotacionFuncionRegla::query()
            ->whereKey($reglaId)
            ->where('codigo', 'director_adp')
            ->exists();

        if (! $esDirectorAdp) {
            return;
        }

        if (($data['tipo_asignacion'] ?? null) !== 'funcion_directiva'
            || ($data['estamento_cobertura'] ?? null) !== 'docente') {
            throw ValidationException::withMessages([
                'estamento_cobertura' => 'Director(a) ADP debe ser cubierto por un docente directivo.',
            ]);
        }

        if (abs((float) ($data['horas_contrato'] ?? 0) - 44.0) > 0.01) {
            throw ValidationException::withMessages([
                'horas_contrato' => 'La asignación de Director(a) ADP debe registrar exactamente 44 horas de contrato.',
            ]);
        }

        $habilitado = DotacionEstablecimientoConfiguracion::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('director_adp', true)
            ->exists();

        if (! $habilitado) {
            throw ValidationException::withMessages([
                'dotacion_funcion_regla_id' => 'Director(a) ADP no está habilitado para este establecimiento y año.',
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
