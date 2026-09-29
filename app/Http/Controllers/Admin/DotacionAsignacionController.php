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
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionAsignacionPorCursoBloque;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProfesionDocenteResolver;
use App\Support\DotacionDocentesSubsector;
use App\Support\DotacionProceso2027Calculator;
use App\Support\DotacionPlanTitularPrimero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DotacionAsignacionController extends Controller
{
    private const PARVULARIA_CPEIP_LABEL = 'NT JEC · CPEIP 65/35';

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
        DB::transaction(function () use ($establecimiento, $persona, $payload): void {
            $this->validateAcompanamientoParvularia($establecimiento, $payload);
            $this->validateLimiteAulaParvularia($establecimiento, $payload);
            $this->validateVirtualAssignment($establecimiento, $persona, $payload);
            $this->validateProceso2027Assignment($establecimiento, $persona, $payload);
            DotacionDocenteAsignacion::create($payload);
            $this->recalcularContratoAulaParvularia($establecimiento, (int) $payload['anio'], (string) $payload['docente_rut_normalizado']);
        });

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
        $payload = $this->buildPayload($request, $establecimiento, $persona, array_merge($asignacion->toArray(), $data), $asignacion);
        $payload['updated_by'] = $request->user()?->id;
        DB::transaction(function () use ($establecimiento, $persona, $payload, $asignacion): void {
            $rutAnterior = (string) $asignacion->docente_rut_normalizado;
            $this->validateAcompanamientoParvularia($establecimiento, $payload, $asignacion);
            $this->validateLimiteAulaParvularia($establecimiento, $payload, $asignacion);
            $this->validateVirtualAssignment($establecimiento, $persona, $payload, $asignacion);
            $this->validateProceso2027Assignment($establecimiento, $persona, $payload, $asignacion);
            $asignacion->update($payload);
            $this->recalcularContratoAulaParvularia($establecimiento, (int) $asignacion->anio, $rutAnterior);
            $this->recalcularContratoAulaParvularia($establecimiento, (int) $payload['anio'], (string) $payload['docente_rut_normalizado']);
        });

        return back()->with('success', 'Asignación de horas actualizada correctamente.');
    }

    public function destroy(Request $request, Establecimiento $establecimiento, DotacionDocenteAsignacion $asignacion): RedirectResponse
    {
        $this->authorizeScope($request, $establecimiento);
        abort_unless((int) $asignacion->establecimiento_id === (int) $establecimiento->id, 404);
        DB::transaction(function () use ($establecimiento, $asignacion): void {
            $this->validateAcompanamientoParvularia($establecimiento, null, $asignacion);
            $asignacion->delete();
            $this->recalcularContratoAulaParvularia($establecimiento, (int) $asignacion->anio, (string) $asignacion->docente_rut_normalizado);
        });

        return back()->with('success', 'Asignación de horas eliminada correctamente.');
    }

    public function destroyCourseBlock(Request $request, Establecimiento $establecimiento): RedirectResponse
    {
        $this->authorizeScope($request, $establecimiento);
        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'grupo' => ['required', 'in:plan_estudio,pie_colaborativo'],
            'curso_label' => ['required', 'string', 'max:255'],
            'bloque' => ['nullable', 'string', 'max:255'],
        ]);
        $anio = (int) $data['anio'];
        $grupo = (string) $data['grupo'];
        $bloque = $data['bloque'] ?? null;

        $resumen = DotacionEstablecimientoCalculator::build($establecimiento, $anio, false);
        $necesidades = DotacionAsignacionPorCursoBloque::necesidades(
            data_get($resumen, 'asignacion.necesidades', []),
            $grupo,
            (string) $data['curso_label'],
            $bloque
        );
        if ($necesidades->isEmpty()) {
            throw ValidationException::withMessages(['curso_label' => 'El curso o bloque ya no está vigente. Actualice la página.']);
        }
        $asignaciones = DotacionAsignacionPorCursoBloque::asignaciones($necesidades);
        if ($asignaciones->isEmpty()) {
            return back()->with('info', 'Este curso y bloque ya no tienen asignaciones para eliminar.');
        }

        $cantidad = $this->deleteCourseBlockAssignments($establecimiento, $anio, $asignaciones);

        $alcance = $bloque === null ? 'curso seleccionado' : 'curso y bloque seleccionados';

        return back()->with('success', "Se eliminaron {$cantidad} asignaciones del {$alcance}.");
    }

    /** @param Collection<int, DotacionDocenteAsignacion> $asignaciones */
    private function deleteCourseBlockAssignments(Establecimiento $establecimiento, int $anio, Collection $asignaciones): int
    {
        return DB::transaction(function () use ($establecimiento, $anio, $asignaciones): int {
            $rows = DotacionDocenteAsignacion::query()
                ->where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio)
                ->where('estado', 'activa')
                ->whereIn('id', $asignaciones->pluck('id'))
                ->lockForUpdate()
                ->get();
            if ($rows->count() !== $asignaciones->count()) {
                throw ValidationException::withMessages(['curso_label' => 'Las asignaciones cambiaron. Actualice la página e intente nuevamente.']);
            }
            $ruts = $rows->pluck('docente_rut_normalizado')->filter()->unique();
            $rows->each->delete();
            foreach ($ruts as $rut) {
                $this->recalcularContratoAulaParvularia($establecimiento, $anio, (string) $rut);
            }

            return $rows->count();
        });
    }

    private function buildPayload(Request $request, Establecimiento $establecimiento, array $docente, array $data, ?DotacionDocenteAsignacion $current = null): array
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

        if (in_array($tipo, ['plan_estudio', 'acompanamiento_parvularia'], true)) {
            if ($tipo === 'acompanamiento_parvularia') {
                $necesidad = DotacionAsignacionCalculator::planNeedForKey(
                    $establecimiento, (int) ($data['anio'] ?? 0), (string) ($data['necesidad_key'] ?? '')
                );
                if (! $necesidad) {
                    throw ValidationException::withMessages(['necesidad_key' => 'La libre disposición seleccionada ya no se encuentra vigente.']);
                }
                $data = array_merge($data, [
                    'establecimiento_curso_id' => $necesidad['establecimiento_curso_id'],
                    'dotacion_curso_combinado_id' => $necesidad['dotacion_curso_combinado_id'] ?? null,
                    'plan_estudio_id' => $necesidad['plan_estudio_id'] ?? null,
                    'plan_bloque_id' => $necesidad['plan_bloque_id'] ?? null,
                    'asignatura_id' => $necesidad['asignatura_id'] ?? null,
                    'asignatura_nombre' => $necesidad['asignatura_nombre'] ?? $necesidad['titulo'],
                    'subtipo_asignacion' => 'libre_disposicion',
                ]);
                $subtipo = 'libre_disposicion';
                $establecimientoCursoId = $data['establecimiento_curso_id'];
                $planEstudioId = $data['plan_estudio_id'];
                $planBloqueId = $data['plan_bloque_id'];
                $asignaturaId = $data['asignatura_id'];
                $asignaturaNombre = $data['asignatura_nombre'];
                $dotacionFuncionId = null;
                $dotacionFuncionReglaId = null;
            }
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
            if ($tipo === 'acompanamiento_parvularia'
                && ($estamentoCobertura !== 'docente'
                    || ! DotacionProfesionDocenteResolver::perfilTitulo($docente)['es_educacion_parvulos'])) {
                throw ValidationException::withMessages(['docente_rut' => 'El acompañamiento en aula solo puede asignarse a una Educadora de Párvulos.']);
            }
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
                    if ($tipo === 'acompanamiento_parvularia') {
                        $fuente = 'Acompañamiento NT1/NT2 en libre disposición';
                    }
                    if ($estamentoCobertura === 'docente'
                        && DotacionProfesionDocenteResolver::perfilTitulo($docente)['es_educacion_parvulos']
                        && \App\Support\DotacionParvulariaCalculator::conJec($curso, $proporcionConfigurada)
                        && Schema::hasTable('docente_horas_proporciones')) {
                        $horasContrato = $this->contratoMarginalAulaParvularia(
                            $establecimiento, (int) $data['anio'], (string) $docente['rut_normalizado'], $horasPlan, $current
                        );
                        $proporcion = self::PARVULARIA_CPEIP_LABEL;
                        $fuente = 'Tabla CPEIP 65/35 · aula NT1/NT2 JEC';
                    }
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
            'subvencion' => in_array($tipo, ['plan_estudio', 'acompanamiento_parvularia'], true)
                ? 'General'
                : (($data['subvencion'] ?? null) ?: $this->defaultSubvencion($tipo, $subtipo)),
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

    /** La educadora acompaña las horas de otro docente sin cubrir de nuevo el plan. */
    private function validateAcompanamientoParvularia(
        Establecimiento $establecimiento,
        ?array $payload,
        ?DotacionDocenteAsignacion $current = null
    ): void {
        $tipo = (string) ($payload['tipo_asignacion'] ?? $current?->tipo_asignacion ?? '');
        if (! in_array($tipo, ['plan_estudio', 'acompanamiento_parvularia'], true)) {
            return;
        }
        $key = (string) ($payload['necesidad_key'] ?? $current?->necesidad_key ?? '');
        $need = DotacionAsignacionCalculator::planNeedForKey(
            $establecimiento, (int) ($payload['anio'] ?? $current?->anio ?? 0), $key
        );
        if (! $need) {
            if ($tipo === 'acompanamiento_parvularia') {
                throw ValidationException::withMessages(['necesidad_key' => 'La libre disposición asociada ya no está vigente.']);
            }
            return;
        }

        $curso = $need['curso'] ?? null;
        $esLibreDisposicion = ($need['subtipo_asignacion'] ?? '') === 'libre_disposicion'
            || (bool) ($need['curso_combinado_libre_disposicion'] ?? false);
        $esNtJec = $curso instanceof EstablecimientoCurso
            && DotacionProfesionDocenteResolver::esCursoNt($curso)
            && \App\Support\DotacionParvulariaCalculator::conJec($curso, $need['proporcion_key'] ?? null);
        if ($tipo === 'acompanamiento_parvularia' && (! $esLibreDisposicion || ! $esNtJec)) {
            throw ValidationException::withMessages(['necesidad_key' => 'El acompañamiento solo corresponde a libre disposición de NT1/NT2 con JEC.']);
        }
        if (! $esLibreDisposicion || ! $esNtJec) {
            return;
        }

        $externas = (float) ($need['horas_externas_libre_disposicion'] ?? 0);
        $acompanadas = (float) ($need['horas_acompanamiento_asignadas'] ?? 0);
        if ($current && $current->tipo_asignacion === 'acompanamiento_parvularia') {
            $acompanadas -= (float) ($current->horas_plan_pedagogicas ?? 0);
        }
        if ($tipo === 'acompanamiento_parvularia' && $payload !== null) {
            $horas = (float) ($payload['horas_plan_pedagogicas'] ?? 0);
            if ($horas <= 0 || $acompanadas + $horas > $externas + 0.01) {
                throw ValidationException::withMessages(['horas_plan_pedagogicas' => 'Las horas de acompañamiento no pueden superar las horas de libre disposición impartidas por otro docente en esta asignatura.']);
            }
            return;
        }
        if ($current && $current->tipo_asignacion === 'plan_estudio') {
            $current->loadMissing('declaracionSostenedor');
            $eraExterna = ($current->estamento_cobertura ?? 'docente') === 'docente'
                && ! DotacionProfesionDocenteResolver::esAsignacionParvularia($current);
            if ($eraExterna) {
                $externas -= (float) ($current->horas_plan_pedagogicas ?? 0);
            }
        }
        if ($payload !== null && $tipo === 'plan_estudio' && ($payload['estamento_cobertura'] ?? '') === 'docente') {
            $declaracion = $payload['declaracion_sostenedor_id']
                ? \App\Models\DeclaracionSostenedor::find($payload['declaracion_sostenedor_id'])
                : null;
            $nuevaAsignacion = (new DotacionDocenteAsignacion)->forceFill($payload);
            $nuevaAsignacion->setRelation('declaracionSostenedor', $declaracion);
            if (! DotacionProfesionDocenteResolver::esAsignacionParvularia($nuevaAsignacion)) {
                $externas += (float) ($payload['horas_plan_pedagogicas'] ?? 0);
            }
        }
        if ($acompanadas > $externas + 0.01) {
            throw ValidationException::withMessages(['horas_plan_pedagogicas' => 'Reduzca primero el acompañamiento de la Educadora de Párvulos antes de disminuir o eliminar las horas del otro docente.']);
        }
    }

    private function contratoCpeip65(float $horasAula): float
    {
        if ($horasAula <= 0) {
            return 0.0;
        }

        return (float) (DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula(
            DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL, $horasAula
        )['horas_contrato'] ?? 0);
    }

    private function contratoMarginalAulaParvularia(
        Establecimiento $establecimiento,
        int $anio,
        string $rut,
        float $horasAula,
        ?DotacionDocenteAsignacion $current = null
    ): float {
        $aulaExistente = Schema::hasTable('dotacion_docente_asignaciones')
            ? (float) DotacionDocenteAsignacion::query()
                ->where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio)
                ->where('estado', 'activa')
                ->where('docente_rut_normalizado', $rut)
                ->where('proporcion_aplicada', self::PARVULARIA_CPEIP_LABEL)
                ->when($current, fn ($query) => $query->whereKeyNot($current->id))
                ->sum('horas_plan_pedagogicas')
            : 0.0;

        return round($this->contratoCpeip65($aulaExistente + $horasAula) - $this->contratoCpeip65($aulaExistente), 2);
    }

    private function recalcularContratoAulaParvularia(Establecimiento $establecimiento, int $anio, string $rut): void
    {
        if ($rut === '' || ! Schema::hasTable('docente_horas_proporciones')) {
            return;
        }
        $asignaciones = DotacionDocenteAsignacion::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('estado', 'activa')
            ->where('docente_rut_normalizado', $rut)
            ->where('proporcion_aplicada', self::PARVULARIA_CPEIP_LABEL)
            ->orderBy('id')
            ->get();
        $aula = 0.0;
        $contrato = 0.0;
        foreach ($asignaciones as $asignacion) {
            $aula += (float) ($asignacion->horas_plan_pedagogicas ?? 0);
            $nuevoContrato = $this->contratoCpeip65($aula);
            $marginal = round($nuevoContrato - $contrato, 2);
            if (abs((float) $asignacion->horas_contrato - $marginal) > 0.001) {
                DB::table('dotacion_docente_asignaciones')->where('id', $asignacion->id)
                    ->update(['horas_contrato' => $marginal]);
            }
            $contrato = $nuevoContrato;
        }
    }

    private function validateLimiteAulaParvularia(
        Establecimiento $establecimiento,
        array $payload,
        ?DotacionDocenteAsignacion $current = null
    ): void {
        if (($payload['proporcion_aplicada'] ?? '') !== self::PARVULARIA_CPEIP_LABEL
            || ! Schema::hasTable('dotacion_docente_asignaciones')) {
            return;
        }
        $asignaciones = DotacionDocenteAsignacion::query()
            ->with('establecimientoCurso.curso')
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', (int) $payload['anio'])
            ->where('estado', 'activa')
            ->where('docente_rut_normalizado', $payload['docente_rut_normalizado'])
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->whereIn('tipo_asignacion', ['plan_estudio', 'acompanamiento_parvularia'])
            ->get()
            ->filter(fn ($row) => $row->establecimientoCurso
                && DotacionProfesionDocenteResolver::esCursoNt($row->establecimientoCurso)
                && \App\Support\DotacionParvulariaCalculator::conJec($row->establecimientoCurso));
        $aula = (float) $asignaciones->sum(fn ($row) => (float) ($row->horas_plan_pedagogicas ?? 0))
            + (float) ($payload['horas_plan_pedagogicas'] ?? 0);
        $contrato = (float) $asignaciones->sum(fn ($row) => (float) ($row->horas_contrato ?? 0))
            + (float) ($payload['horas_contrato'] ?? 0);
        if ($aula > 35.01 || $contrato > 41.01) {
            throw ValidationException::withMessages([
                'horas_plan_pedagogicas' => 'La Educadora de Párvulos no puede superar 35 horas pedagógicas de aula (26 h 15 min cronológicas), equivalentes a 41 horas de contrato de aula según CPEIP 65/35. Reserve las otras 3 horas de una jornada de 44 para PIE.',
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
        if (! DotacionProceso2027Calculator::aplica($anio)) {
            return;
        }

        $proceso = DotacionProceso2027Calculator::resumen($establecimiento, $anio);
        if (! ($proceso['asignacion_habilitada'] ?? false)) {
            throw ValidationException::withMessages([
                'anio' => 'Para asignar horas en 2027 debe completar planes de estudio, asociar docentes a las asignaturas, declarar la combinación de cursos y configurar máximos suficientes para los tres bloques.',
            ]);
        }

        $docentesPermitidosSubsector = null;
        $necesidadPlan = null;
        if (in_array((string) ($payload['tipo_asignacion'] ?? ''), ['plan_estudio', 'acompanamiento_parvularia'], true)) {
            $necesidadPlan = DotacionAsignacionCalculator::planNeedForKey(
                $establecimiento, $anio, (string) ($payload['necesidad_key'] ?? '')
            );
            if (! $necesidadPlan) {
                throw ValidationException::withMessages(['necesidad_key' => 'La asignatura del plan ya no está vigente. Actualice la página.']);
            }
            $subsectorKey = DotacionDocentesSubsector::keyParaNecesidad($necesidadPlan);
            $docentesPermitidosSubsector = collect($proceso['docentes_subsector']['asignaturas'] ?? [])
                ->firstWhere('key', $subsectorKey)['docentes'] ?? [];
            $rutSeleccionado = DotacionEstablecimientoCalculator::normalizeRut(
                (string) ($persona['rut_normalizado'] ?? $persona['rut'] ?? '')
            );
            if (($payload['estamento_cobertura'] ?? 'docente') === 'docente'
                && ! in_array($rutSeleccionado, $docentesPermitidosSubsector, true)) {
                throw ValidationException::withMessages([
                    'docente_rut' => 'El docente no está asociado a esta asignatura. Asócielo primero en la etapa Docentes por asignatura.',
                ]);
            }
        }

        if (($payload['tipo_asignacion'] ?? '') === 'plan_estudio' && $necesidadPlan !== null) {
            $docentesPlan = DotacionPlanTitularPrimero::elegibles(
                collect($proceso['docentes'] ?? []), $docentesPermitidosSubsector ?? [], $necesidadPlan, $current
            );
            $rutSeleccionado = DotacionEstablecimientoCalculator::normalizeRut(
                (string) ($persona['rut_normalizado'] ?? $persona['rut'] ?? '')
            );
            $seleccionadoPlan = $docentesPlan->first(fn (array $docente) =>
                DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? '')) === $rutSeleccionado
            );
            DotacionPlanTitularPrimero::validar(
                $docentesPlan,
                $seleccionadoPlan,
                (float) ($payload['horas_contrato'] ?? 0),
                ($payload['estamento_cobertura'] ?? 'docente') === 'asistente'
            );
        }
        if (($payload['estamento_cobertura'] ?? 'docente') !== 'docente') {
            return;
        }

        $bloqueNecesidad = data_get($proceso, 'need_blocks.'.($payload['necesidad_key'] ?? ''));
        $bloque = DotacionProceso2027Calculator::bloqueFuncionPorDocente($payload, $persona)
            ?: DotacionProceso2027Calculator::bloqueLibreDisposicionNtOtroDocente($payload, $bloqueNecesidad, $persona)
            ?: $bloqueNecesidad
            ?: DotacionProceso2027Calculator::bloqueParaAsignacion($payload);
        if (! $bloque) {
            return;
        }

        $horas = max(0.0, (float) ($payload['horas_contrato'] ?? 0));
        $bloqueProceso = data_get($proceso, 'bloques.'.$bloque, []);
        $asignadas = (float) ($bloqueProceso['asignadas'] ?? 0);
        $asignadasMaximo = max(0.0, $asignadas - (float) ($bloqueProceso['asignadas_acompanamiento'] ?? 0));
        $noNormativas = (float) ($bloqueProceso['asignadas_no_normativas'] ?? 0);
        $esNoNormativa = (int) ($payload['dotacion_funcion_id'] ?? 0) > 0
            || (string) ($payload['tipo_asignacion'] ?? '') === 'otra_funcion';
        if ($current) {
            $rutActual = DotacionEstablecimientoCalculator::normalizeRut((string) ($current->docente_rut_normalizado ?: $current->docente_rut));
            $personaActual = collect($proceso['docentes'] ?? [])->first(fn (array $docente) =>
                DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? '')) === $rutActual
            );
            $bloqueNecesidadActual = data_get($proceso, 'need_blocks.'.($current->necesidad_key ?? ''));
            $bloqueActual = ($personaActual ? DotacionProceso2027Calculator::bloqueFuncionPorDocente($current, $personaActual) : null)
                ?: DotacionProceso2027Calculator::bloqueLibreDisposicionNtOtroDocente($current, $bloqueNecesidadActual)
                ?: $bloqueNecesidadActual
                ?: DotacionProceso2027Calculator::bloqueParaAsignacion($current);
            if ($bloqueActual === $bloque) {
                $asignadasMaximo = max(0.0, $asignadasMaximo - DotacionProceso2027Calculator::horasImputablesAlMaximo(
                    (string) $current->tipo_asignacion, (float) $current->horas_contrato
                ));
                if ((int) ($current->dotacion_funcion_id ?? 0) > 0
                    || (string) $current->tipo_asignacion === 'otra_funcion') {
                    $noNormativas = max(0.0, $noNormativas - (float) $current->horas_contrato);
                }
            }
        }
        $maximo = $bloqueProceso['maximo'] ?? null;
        // Las horas aula completas acreditan el plan consolidado, aunque las
        // asignaciones históricas conserven un contrato menor por asignatura.
        // Ese contrato necesario ocupa cupo antes de agregar funciones optativas.
        $contratoComprometido = $esNoNormativa
            ? DotacionProceso2027Calculator::contratoComprometidoParaMaximo(
                (float) ($bloqueProceso['requeridas'] ?? 0), $asignadasMaximo, $noNormativas
            )
            : $asignadasMaximo;
        $horasMaximo = DotacionProceso2027Calculator::horasImputablesAlMaximo(
            (string) ($payload['tipo_asignacion'] ?? ''), $horas
        );
        if ($maximo !== null && $horasMaximo > 0.01
            && $contratoComprometido + $horasMaximo > (float) $maximo + 0.01) {
            throw ValidationException::withMessages([
                'horas_contrato' => 'La asignación supera el máximo autorizado del bloque '.$bloqueProceso['label'].' (saldo del bloque: '.max(0, round((float) $maximo - $contratoComprometido, 2)).' h). El saldo del contrato individual se valida por separado.',
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
        $docentesElegibles = collect($proceso['docentes'] ?? []);
        if ($docentesPermitidosSubsector !== null) {
            $docentesElegibles = $docentesElegibles->filter(fn (array $docente) => in_array(
                DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? '')),
                $docentesPermitidosSubsector, true
            ));
        }
        $docentesPrelacion = DotacionProceso2027Calculator::docentesPrelacionParaAsignacion(
            $docentesElegibles, $persona, $payload, $bloque
        );
        $seleccionado = $docentesPrelacion->first(
            fn (array $docente) => ($docente['rut_normalizado'] ?? '') === $rut
        );
        $hayPrelacionAnterior = DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible(
            $docentesPrelacion,
            $seleccionado ?? [],
            $horas
        );
        if ($hayPrelacionAnterior && blank($payload['excepcion_prelacion'] ?? null)) {
            throw ValidationException::withMessages([
                'excepcion_prelacion' => 'Existen docentes de prioridad superior o de mayor antigüedad en el mismo grupo con horas suficientes para cubrir esta asignación. Para continuar debe indicar una justificación de excepción.',
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
        if ($tipo !== 'plan_estudio' && $subtipo === 'libre_disposicion') {
            return 'Libre disposición';
        }
        return 'General';
    }

    private function findPersonal(Establecimiento $establecimiento, int $anio, string $rut, string $estamentoCobertura): ?array
    {
        if ($estamentoCobertura === 'docente' && ContratacionHabilitacionService::esRutVirtual($rut)) {
            return app(ContratacionHabilitacionService::class)->docenteVirtual($establecimiento, $anio, $rut);
        }

        $rutNorm = DotacionEstablecimientoCalculator::normalizeRut($rut);
        $personal = $estamentoCobertura === 'asistente'
            ? DotacionEstablecimientoCalculator::asistentes($establecimiento, $anio)
            : DotacionEstablecimientoCalculator::docentes($establecimiento, $anio);

        return $personal->first(
            fn ($persona) => DotacionEstablecimientoCalculator::normalizeRut($persona['rut_normalizado'] ?? $persona['rut'] ?? '') === $rutNorm
        );
    }

    private function validateVirtualAssignment(
        Establecimiento $establecimiento,
        array $persona,
        array $payload,
        ?DotacionDocenteAsignacion $current = null
    ): void {
        $cupoId = (int) ($persona['cupo_contrata_id'] ?? 0);
        if ($cupoId <= 0) {
            return;
        }

        $cupo = DB::table('dotacion_contrata_habilitaciones')
            ->where('id', $cupoId)
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', (int) $payload['anio'])
            ->lockForUpdate()
            ->first();
        if (! $cupo) {
            throw ValidationException::withMessages(['docente_rut' => 'El docente por contratar ya no está habilitado.']);
        }
        if ((float) ($payload['horas_contrato'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['horas_contrato' => 'Asigne horas mayores que cero al docente por contratar.']);
        }

        $tipo = (string) ($payload['tipo_asignacion'] ?? '');
        $bloqueFuncion = DotacionProceso2027Calculator::bloqueFuncionPorDocente($payload, $persona);
        $curso = (int) ($payload['establecimiento_curso_id'] ?? 0) > 0
            ? EstablecimientoCurso::query()->with('curso')->find((int) $payload['establecimiento_curso_id'])
            : null;
        $bloqueAsignacion = match (true) {
            $bloqueFuncion !== null => $bloqueFuncion,
            in_array($tipo, ['plan_estudio', 'pie_colaborativo', 'acompanamiento_parvularia'], true) => $curso && DotacionProfesionDocenteResolver::esCursoNt($curso) ? 'bloque_2' : 'bloque_1',
            $tipo === 'pie_educadora_diferencial' => 'bloque_3',
            default => DotacionProceso2027Calculator::bloqueParaAsignacion($payload),
        };
        $bloquePermitido = $cupo->bloque === 'parvularia' ? 'bloque_2' : 'bloque_3';
        if ($bloqueAsignacion !== $bloquePermitido) {
            throw ValidationException::withMessages([
                'docente_rut' => 'El docente por contratar solo puede cubrir necesidades de su bloque '.($cupo->bloque === 'parvularia' ? 'Parvularia' : 'PIE').'.',
            ]);
        }

        $rutNormalizado = DotacionEstablecimientoCalculator::normalizeRut((string) $persona['rut']);
        $asignadas = (float) DotacionDocenteAsignacion::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', (int) $payload['anio'])
            ->where('docente_rut_normalizado', $rutNormalizado)
            ->where('estado', 'activa')
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->sum('horas_contrato');
        if ($asignadas + (float) ($payload['horas_contrato'] ?? 0) > (float) $cupo->horas + 0.01) {
            throw ValidationException::withMessages([
                'horas_contrato' => 'Las horas asignadas al docente por contratar superan el cupo de '.$cupo->horas.' horas.',
            ]);
        }
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
