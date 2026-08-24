<?php

namespace App\Support;

use App\Models\AlumnoPrioritarioPorcentaje;
use App\Models\DeclaracionSostenedor;
use App\Models\DotacionDocenteExclusion;
use App\Models\DotacionFuncionEstablecimiento;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\EstablecimientoCursoPie;
use App\Models\ReemplazoPersonal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DotacionEstablecimientoCalculator
{
    /** @var array<string, bool> */
    private static array $schemaTableCache = [];

    /** @var array<string, bool> */
    private static array $schemaColumnCache = [];

    private static function schemaHasTable(string $table): bool
    {
        if (! array_key_exists($table, self::$schemaTableCache)) {
            self::$schemaTableCache[$table] = Schema::hasTable($table);
        }

        return self::$schemaTableCache[$table];
    }

    private static function schemaHasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        if (! array_key_exists($key, self::$schemaColumnCache)) {
            self::$schemaColumnCache[$key] = Schema::hasColumn($table, $column);
        }

        return self::$schemaColumnCache[$key];
    }

    public static function build(Establecimiento $establecimiento, int $anio, bool $incluirResumenAsignaturas = true): array
    {
        $proporcionExcepcion = DocenteHorasNoLectivasCalculator::activeExceptionFor((int) $establecimiento->id, $anio);
        $cursos = self::cursosPorNivel($establecimiento, $anio);
        $bloques = self::bloquesDotacion($establecimiento, $anio);
        $docentes = self::docentes($establecimiento, $anio);
        // El informe global usa build(..., false): evita cargar el padrón AAEE completo
        // cuando no se requiere la edición ni el consolidado detallado por asignatura.
        $asistentes = $incluirResumenAsignaturas
            ? self::asistentes($establecimiento, $anio)
            : collect();
        $asignacion = DotacionAsignacionCalculator::build($establecimiento, $anio, $docentes, $cursos, $bloques, $asistentes);
        $planNeeds = collect(data_get($asignacion, 'necesidades.plan_estudio', []));
        $cursosCombinados = DotacionCursoCombinadoCalculator::summary(
            $establecimiento,
            $anio,
            $planNeeds
        );
        $asignaturas = $incluirResumenAsignaturas
            ? DotacionAsignaturaResumenCalculator::build(
                $planNeeds,
                $docentes->concat($asistentes)->values()
            )
            : ['items' => collect(), 'resumen' => [], 'opciones' => []];

        $horasPlanBrutas = (float) ($cursos['totales']['horas'] ?? 0);
        $horasContratoPlanBrutas = (float) ($cursos['totales']['horas_contrato_equivalente'] ?? 0);
        $gruposCombinadosActivos = collect(data_get($cursosCombinados, 'grupos', []))
            ->where('activo', true)
            ->values();
        $horasPlanAjustadas = $gruposCombinadosActivos->isEmpty()
            ? $horasPlanBrutas
            : round((float) $planNeeds->sum(
                fn ($item) => (float) ($item['horas_plan_requeridas'] ?? 0)
            ), 2);

        $cursoIdsCombinados = $gruposCombinadosActivos
            ->flatMap(fn ($grupo) => collect($grupo['miembros'] ?? [])->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique();
        $contratoCursosReemplazados = collect($cursos['rows'] ?? [])
            ->flatMap(fn ($row) => collect($row['detalles'] ?? []))
            ->filter(fn ($detalle) => $cursoIdsCombinados->contains((int) ($detalle['establecimiento_curso_id'] ?? 0)))
            ->sum(fn ($detalle) => (float) ($detalle['horas_contrato_equivalente_redondeado'] ?? 0));
        $contratoGruposCombinados = $gruposCombinadosActivos
            ->sum(fn ($grupo) => (float) data_get($grupo, 'totales.horas_contrato', 0));
        $horasContratoPlanAjustadas = $gruposCombinadosActivos->isEmpty()
            ? $horasContratoPlanBrutas
            : round(
                max(0.0, $horasContratoPlanBrutas - $contratoCursosReemplazados) + $contratoGruposCombinados,
                2
            );

        $reduccionCursosCombinados = max(0.0, round($horasPlanBrutas - $horasPlanAjustadas, 2));
        $reduccionContratoCursosCombinados = max(0.0, round($horasContratoPlanBrutas - $horasContratoPlanAjustadas, 2));
        $cursos['totales']['horas_brutas_sin_combinar'] = $horasPlanBrutas;
        $cursos['totales']['horas_contrato_brutas_sin_combinar'] = $horasContratoPlanBrutas;
        $cursos['totales']['horas'] = $horasPlanAjustadas;
        $cursos['totales']['horas_contrato_equivalente'] = $horasContratoPlanAjustadas;
        $cursos['totales']['reduccion_cursos_combinados'] = $reduccionCursosCombinados;
        $cursos['totales']['reduccion_contrato_cursos_combinados'] = $reduccionContratoCursosCombinados;

        $desgloseContratoPieNecesario = self::desgloseContratoPieNecesario($bloques);
        $bloquesContratoDotacion = self::bloquesSinContratoPieNecesario($bloques);
        $horasContratoPieNecesarias = (float) ($desgloseContratoPieNecesario['total'] ?? 0);
        $horasDotacionFunciones = collect($bloquesContratoDotacion)->sum(fn ($bloque) => (float) ($bloque['total'] ?? 0));
        $horasDotacionFuncionesNormativas = (float) collect($bloquesContratoDotacion)->sum(fn ($bloque) => (float) ($bloque['automaticas'] ?? 0));
        $horasDotacionFuncionesDeclaradas = (float) collect($bloquesContratoDotacion)->sum(fn ($bloque) => (float) ($bloque['declaradas'] ?? 0));
        $desgloseHorasDotacion = self::desgloseContratoBloqueDotacion(
            $bloquesContratoDotacion,
            data_get($asignacion, 'necesidades.funciones', [])
        );
        $contratoPlanMasTrabajoColaborativoPie = (float) ($horasContratoPlanAjustadas + ($cursos['totales']['trabajo_colaborativo_pie'] ?? 0));
        $horasContratoDocentesBase = $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato_base'] ?? $docente['horas_contrato'] ?? 0));
        $horasContratoDocentesExcluidas = $docentes->sum(fn ($docente) => (float) ($docente['horas_excluidas'] ?? 0));
        $horasContratoDocentes = $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato'] ?? 0));
        $horasContratoDocentePieCoordinacion = (float) data_get($asignacion, 'resumen.horas_contrato_docente_pie_coordinacion', 0);
        $horasContratoDocentePieEducadoras = (float) data_get($asignacion, 'resumen.horas_contrato_docente_pie_educadoras_diferenciales', 0);
        $horasContratoDocentePie = (float) data_get($asignacion, 'resumen.horas_contrato_docente_pie', 0);
        $horasContratoDocentesAula = max(0.0, round($horasContratoDocentes - $horasContratoDocentePie, 2));
        $horasContratoDocentePieExceso = max(0.0, round($horasContratoDocentePie - $horasContratoDocentes, 2));
        $horasContratoRequeridas = $contratoPlanMasTrabajoColaborativoPie + $horasDotacionFunciones + $horasContratoPieNecesarias;
        $brechaContrato = round($horasContratoRequeridas - $horasContratoDocentes, 2);
        $horasAulaAsignadas = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_aula'] ?? 0));
        $horasContrato6535 = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato_65_35'] ?? 0));
        $horasContrato6040 = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato_60_40'] ?? 0));
        $horasContratoEspecial = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato_especial'] ?? 0));
        $horasFuncionesAsignadas = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_funciones_total'] ?? 0));
        $horasContratoCalculado = (float) $docentes->sum(fn ($docente) => (float) ($docente['horas_asignadas_total'] ?? 0));
        $diferenciaContratoCalculado = round($horasContratoDocentes - $horasContratoCalculado, 2);
        $horasAulaAsistentes = (float) data_get($asignacion, 'resumen.horas_aula_asistentes', 0);
        $horasContratoAsistentes = (float) data_get($asignacion, 'resumen.horas_contrato_asistentes', 0);
        $horasAulaCoberturaTotal = round($horasAulaAsignadas + $horasAulaAsistentes, 2);
        $horasContratoCoberturaTotal = round($horasContratoCalculado + $horasContratoAsistentes, 2);

        $resumen = [
            'matricula_total' => (int) ($cursos['totales']['matricula'] ?? 0),
            'cursos_total' => (int) ($cursos['totales']['cursos'] ?? 0),
            'docentes_total' => $docentes->count(),
            'asistentes_total' => $asistentes->count(),
            'horas_plan_total' => $horasPlanAjustadas,
            'horas_plan_total_brutas' => $horasPlanBrutas,
            'horas_plan_reduccion_cursos_combinados' => $reduccionCursosCombinados,
            'horas_plan_contrato_equivalente' => $horasContratoPlanAjustadas,
            'horas_plan_contrato_brutas' => $horasContratoPlanBrutas,
            'horas_plan_contrato_reduccion_cursos_combinados' => $reduccionContratoCursosCombinados,
            'trabajo_colaborativo_pie' => (float) ($cursos['totales']['trabajo_colaborativo_pie'] ?? 0),
            'contrato_plan_mas_trabajo_colaborativo_pie' => $contratoPlanMasTrabajoColaborativoPie,
            'horas_dotacion_funciones' => $horasDotacionFunciones,
            'horas_dotacion_funciones_normativas' => $horasDotacionFuncionesNormativas,
            'horas_dotacion_funciones_declaradas' => $horasDotacionFuncionesDeclaradas,
            'horas_dotacion_desglose' => $desgloseHorasDotacion,
            'horas_contrato_pie_necesarias' => $horasContratoPieNecesarias,
            'horas_contrato_pie_necesarias_desglose' => $desgloseContratoPieNecesario,
            'horas_contrato_docentes_base' => $horasContratoDocentesBase,
            'horas_contrato_docentes_excluidas' => $horasContratoDocentesExcluidas,
            'horas_contrato_docentes' => $horasContratoDocentes,
            'horas_contrato_docentes_aula' => $horasContratoDocentesAula,
            'horas_contrato_docente_pie_coordinacion' => $horasContratoDocentePieCoordinacion,
            'horas_contrato_docente_pie_educadoras_diferenciales' => $horasContratoDocentePieEducadoras,
            'horas_contrato_docente_pie' => $horasContratoDocentePie,
            'horas_contrato_docente_pie_exceso' => $horasContratoDocentePieExceso,
            'horas_contrato_requeridas' => $horasContratoRequeridas,
            'horas_por_contratar' => $brechaContrato > 0 ? $brechaContrato : 0.0,
            'sobredotacion_horas' => $brechaContrato < 0 ? abs($brechaContrato) : 0.0,
            'horas_aula_docentes' => $horasAulaAsignadas,
            'horas_aula_asignadas' => $horasAulaCoberturaTotal,
            'horas_contrato_65_35' => $horasContrato6535,
            'horas_contrato_60_40' => $horasContrato6040,
            'horas_contrato_especial' => $horasContratoEspecial,
            'horas_funciones_asignadas' => $horasFuncionesAsignadas,
            'horas_contrato_calculado' => $horasContratoCalculado,
            'diferencia_contrato_calculado' => $diferenciaContratoCalculado,
            'horas_aula_asistentes' => $horasAulaAsistentes,
            'horas_contrato_asistentes' => $horasContratoAsistentes,
            'horas_contrato_cobertura_total' => $horasContratoCoberturaTotal,
        ];

        $alertas = [];
        if (($resumen['cursos_total'] ?? 0) === 0) {
            $alertas[] = 'No se encontraron cursos activos para el año seleccionado.';
        }
        if (($resumen['docentes_total'] ?? 0) === 0) {
            $alertas[] = 'No se encontraron docentes vigentes en reemplazos_personal para el año seleccionado.';
        }
        if (($cursos['totales']['sin_horas_plan'] ?? 0) > 0) {
            $alertas[] = 'Existen cursos sin horas de plan de estudio configuradas.';
        }
        if ($docentes->where('tiene_declaracion', false)->count() > 0) {
            $alertas[] = 'Existen docentes sin registro asociado en declaración sostenedor.';
        }
        if ($horasContratoDocentePieExceso > 0.01) {
            $alertas[] = 'Las horas de contrato asignadas a docentes PIE superan en '.self::formatHoras($horasContratoDocentePieExceso).' hora(s) la base contractual docente vigente.';
        }
        if ((int) data_get($cursosCombinados, 'resumen.grupos_activos', 0) > 0) {
            $alertas[] = 'La necesidad de horas aula considera '.data_get($cursosCombinados, 'resumen.grupos_activos', 0).' grupo(s) de cursos combinados y una reducción de '.self::formatHoras($reduccionCursosCombinados).' hora(s) aula respecto de la suma individual.';
        }
        if ($planNeeds->contains(fn ($item) => ($item['origen_proporcion'] ?? null) === 'curso_combinado_mixto_respaldo')) {
            $alertas[] = 'Existe un curso combinado con proporciones contractuales mixtas configurado en modo automático. Defina 60/40 o 65/35 en la pestaña Cursos combinados.';
        }

        return [
            'resumen' => $resumen,
            'cursos' => $cursos,
            'bloques' => $bloques,
            'bloques_contrato_dotacion' => $bloquesContratoDotacion,
            'docentes' => $docentes,
            'asistentes' => $asistentes,
            'asignacion' => $asignacion,
            'asignaturas' => $asignaturas,
            'cursos_combinados' => $cursosCombinados,
            'proporcion_excepcion' => $proporcionExcepcion,
            'alertas' => $alertas,
        ];
    }

    public static function cursosPorNivel(Establecimiento $establecimiento, int $anio): array
    {
        $base = self::nivelesBase();
        $porcentajePrioritarios = self::porcentajePrioritarios($establecimiento, $anio);
        $cursosConNee = self::cursosConNeeMap($establecimiento, $anio);
        $rows = EstablecimientoCurso::query()
            ->with(['curso', 'planEstudio'])
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('activo', true)
            ->where('matricula', '>', 0)
            ->orderBy('curso_id')
            ->orderBy('letra')
            ->get();

        foreach ($rows as $cursoEstablecimiento) {
            $nivel = self::nivelMeta($cursoEstablecimiento);
            self::ensureNivelRow($base, $nivel);
            $nivelKey = $nivel['key'];

            $horas = self::horasCurso($cursoEstablecimiento);
            $contratoEquivalente = self::horasContratoEquivalenteCurso($cursoEstablecimiento, (float) ($horas['horas'] ?? 0), $porcentajePrioritarios);
            $contratoEquivalenteRedondeado = (float) ($contratoEquivalente['horas_contrato_equivalente_redondeado'] ?? 0);
            if ($contratoEquivalenteRedondeado <= 0 && (float) ($contratoEquivalente['horas_contrato_equivalente'] ?? 0) > 0) {
                $contratoEquivalenteRedondeado = (float) ceil((float) $contratoEquivalente['horas_contrato_equivalente']);
            }
            $cursoNeeKey = 'ec_'.$cursoEstablecimiento->id;
            $cursoBaseNeeKey = 'curso_'.$cursoEstablecimiento->curso_id;
            $tieneNee = isset($cursosConNee[$cursoNeeKey]) || isset($cursosConNee[$cursoBaseNeeKey]);
            $trabajoColaborativoPie = $tieneNee ? 3.0 : 0.0;
            $base['rows'][$nivelKey]['matricula'] += (int) ($cursoEstablecimiento->matricula ?? 0);
            $base['rows'][$nivelKey]['cursos'] += 1;
            $base['rows'][$nivelKey]['total_horas'] += $horas['horas'];
            // Para dotación contractual no se mantienen decimales: cada curso se redondea hacia arriba.
            $base['rows'][$nivelKey]['total_horas_contrato_equivalente'] += $contratoEquivalenteRedondeado;
            $base['rows'][$nivelKey]['total_trabajo_colaborativo_pie'] += $trabajoColaborativoPie;
            $base['rows'][$nivelKey]['total_contrato_mas_trabajo_colaborativo_pie'] += $contratoEquivalenteRedondeado + $trabajoColaborativoPie;
            $base['rows'][$nivelKey]['horas_valores'][] = $horas['horas'];
            $base['rows'][$nivelKey]['proporcion_valores'][] = $contratoEquivalente['proporcion_label'];
            $base['rows'][$nivelKey]['origen_proporcion_valores'][] = $contratoEquivalente['origen_proporcion_label'] ?? 'Regla general';
            $base['rows'][$nivelKey]['sin_horas_plan'] += $horas['horas'] > 0 ? 0 : 1;
            $base['rows'][$nivelKey]['detalles'][] = [
                'establecimiento_curso_id' => $cursoEstablecimiento->id,
                'nombre_seccion' => $cursoEstablecimiento->nombre_seccion,
                'letra' => $cursoEstablecimiento->letra,
                'matricula' => (int) ($cursoEstablecimiento->matricula ?? 0),
                'regimen_jec' => $cursoEstablecimiento->regimen_jec,
                'horas' => $horas['horas'],
                'horas_aula_cronologicas' => $contratoEquivalente['horas_aula_cronologicas'],
                'horas_contrato_equivalente' => $contratoEquivalente['horas_contrato_equivalente'],
                'horas_contrato_equivalente_redondeado' => $contratoEquivalenteRedondeado,
                'tiene_nee' => $tieneNee,
                'trabajo_colaborativo_pie' => $trabajoColaborativoPie,
                'proporcion_docente' => $contratoEquivalente['proporcion'],
                'proporcion_docente_label' => $contratoEquivalente['proporcion_label'],
                'origen_proporcion' => $contratoEquivalente['origen_proporcion'] ?? 'regla_general',
                'origen_proporcion_label' => $contratoEquivalente['origen_proporcion_label'] ?? 'Regla general',
                'motivo_proporcion' => $contratoEquivalente['motivo'],
                'fuente_horas' => $horas['fuente'],
            ];
        }

        foreach ($base['rows'] as $key => $row) {
            $valores = collect($row['horas_valores'] ?? [])
                ->map(fn ($value) => round((float) $value, 2))
                ->unique()
                ->values();
            $base['rows'][$key]['horas_por_nivel'] = $valores->count() === 1 ? (float) $valores->first() : null;
            $base['rows'][$key]['horas_variable'] = $valores->count() > 1;
            $proporciones = collect($row['proporcion_valores'] ?? [])->filter()->unique()->values();
            $base['rows'][$key]['proporcion_docente_label'] = $proporciones->count() === 1 ? $proporciones->first() : ($proporciones->count() > 1 ? 'Mixta' : '—');
            $origenes = collect($row['origen_proporcion_valores'] ?? [])->filter()->unique()->values();
            $base['rows'][$key]['origen_proporcion_label'] = $origenes->count() === 1 ? $origenes->first() : ($origenes->count() > 1 ? 'Mixto' : 'Regla general');
        }

        foreach ($base['grupos'] as $grupoKey => $grupo) {
            $niveles = collect($grupo['niveles'] ?? [])
                ->filter(fn ($nivelKey) => isset($base['rows'][$nivelKey]) && (int) ($base['rows'][$nivelKey]['matricula'] ?? 0) > 0)
                ->sortBy(fn ($nivelKey) => (int) ($base['rows'][$nivelKey]['order'] ?? 9999))
                ->values()
                ->all();

            $total = ['matricula' => 0, 'cursos' => 0, 'horas' => 0.0, 'horas_contrato_equivalente' => 0.0, 'trabajo_colaborativo_pie' => 0.0, 'contrato_mas_trabajo_colaborativo_pie' => 0.0, 'sin_horas_plan' => 0];
            foreach ($niveles as $nivelKey) {
                $row = $base['rows'][$nivelKey] ?? null;
                if (! $row) {
                    continue;
                }
                $total['matricula'] += (int) $row['matricula'];
                $total['cursos'] += (int) $row['cursos'];
                $total['horas'] += (float) $row['total_horas'];
                $total['horas_contrato_equivalente'] += (float) ($row['total_horas_contrato_equivalente'] ?? 0);
                $total['trabajo_colaborativo_pie'] += (float) ($row['total_trabajo_colaborativo_pie'] ?? 0);
                $total['contrato_mas_trabajo_colaborativo_pie'] += (float) ($row['total_contrato_mas_trabajo_colaborativo_pie'] ?? (($row['total_horas_contrato_equivalente'] ?? 0) + ($row['total_trabajo_colaborativo_pie'] ?? 0)));
                $total['sin_horas_plan'] += (int) $row['sin_horas_plan'];
            }
            $base['grupos'][$grupoKey]['niveles'] = $niveles;
            $base['grupos'][$grupoKey]['totales'] = $total;
        }

        $base['grupos'] = collect($base['grupos'])
            ->filter(fn ($grupo) => (int) ($grupo['totales']['matricula'] ?? 0) > 0)
            ->sortBy(fn ($grupo) => (int) ($grupo['order'] ?? 9999))
            ->all();

        $base['totales'] = [
            'matricula' => collect($base['grupos'])->sum(fn ($g) => (int) ($g['totales']['matricula'] ?? 0)),
            'cursos' => collect($base['grupos'])->sum(fn ($g) => (int) ($g['totales']['cursos'] ?? 0)),
            'horas' => collect($base['grupos'])->sum(fn ($g) => (float) ($g['totales']['horas'] ?? 0)),
            'horas_contrato_equivalente' => collect($base['grupos'])->sum(fn ($g) => (float) ($g['totales']['horas_contrato_equivalente'] ?? 0)),
            'trabajo_colaborativo_pie' => collect($base['grupos'])->sum(fn ($g) => (float) ($g['totales']['trabajo_colaborativo_pie'] ?? 0)),
            'contrato_mas_trabajo_colaborativo_pie' => collect($base['grupos'])->sum(fn ($g) => (float) ($g['totales']['contrato_mas_trabajo_colaborativo_pie'] ?? (($g['totales']['horas_contrato_equivalente'] ?? 0) + ($g['totales']['trabajo_colaborativo_pie'] ?? 0)))),
            'sin_horas_plan' => collect($base['grupos'])->sum(fn ($g) => (int) ($g['totales']['sin_horas_plan'] ?? 0)),
        ];

        return $base;
    }

    public static function bloquesDotacion(Establecimiento $establecimiento, int $anio): array
    {
        $bloques = self::bloquesBase();
        $sugerencias = DotacionFuncionesCalculator::sugerencias($establecimiento, $anio);
        $manuales = DotacionFuncionEstablecimiento::query()
            ->with('regla')
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->get();

        foreach ($sugerencias as $item) {
            $key = self::grupoConsolidadoFor($item['categoria'] ?? null, $item['codigo'] ?? null);
            $horas = (float) ($item['horas_sugeridas'] ?? 0);
            $tipoContratoPieNecesario = ($item['codigo'] ?? null) === 'coordinador_pie'
                ? 'coordinacion_pie'
                : null;
            $bloques[$key]['automaticas'] += $horas;
            $bloques[$key]['total'] += $horas;
            $bloques[$key]['items'][] = [
                'nombre' => $item['nombre_funcion'] ?? 'Función automática',
                'origen' => 'Automática',
                'horas' => $horas,
                'detalle' => $item['detalle'] ?? null,
                'dotacion_funcion_id' => null,
                'dotacion_funcion_regla_id' => $item['regla']?->id,
                'tipo_contrato_pie_necesario' => $tipoContratoPieNecesario,
            ];
        }

        foreach ($manuales as $manual) {
            $key = self::grupoConsolidadoFor($manual->categoria, $manual->regla?->codigo);
            $horas = (float) $manual->horasFinales();
            $bloques[$key]['declaradas'] += $horas;
            $bloques[$key]['total'] += $horas;
            $bloques[$key]['items'][] = [
                'nombre' => $manual->nombre_funcion,
                'origen' => 'Declarada',
                'horas' => $horas,
                'estado' => $manual->estadoLabel(),
                'detalle' => $manual->descripcion_funcion ?: $manual->fundamento ?: $manual->observacion,
                'dotacion_funcion_id' => $manual->id,
                'dotacion_funcion_regla_id' => $manual->regla_id,
            ];
        }

        $pieContrato = self::horasContratoPieEstablecimiento($establecimiento, $anio);
        if (($pieContrato['educadoras_diferenciales_horas'] ?? 0) > 0) {
            $horas = (float) $pieContrato['educadoras_diferenciales_horas'];
            $bloques['pie']['automaticas'] += $horas;
            $bloques['pie']['total'] += $horas;
            $bloques['pie']['educadoras_diferenciales'] = $horas;
            $detalleProporciones = collect($pieContrato['detalle_proporciones'] ?? [])
                ->map(fn ($detalle, $label) => $label.': '.($detalle['prof_educ_dif_label'] ?? '00:00').' PROF EDUC. DIF / '.($detalle['contrato_label'] ?? '00:00').' contrato')
                ->values()
                ->implode(' · ');
            $bloques['pie']['items'][] = [
                'nombre' => 'Educadoras diferenciales PIE',
                'origen' => 'Automática',
                'horas' => $horas,
                'prof_educ_dif_label' => $pieContrato['prof_educ_dif_label'] ?? '00:00',
                'horas_contrato_exactas_label' => $pieContrato['educadoras_diferenciales_label_exactas'] ?? '00:00',
                'detalle_proporciones' => $pieContrato['detalle_proporciones'] ?? [],
                'tipo_contrato_pie_necesario' => 'educadoras_diferenciales',
                'detalle' => 'PROF EDUC. DIF: '.($pieContrato['prof_educ_dif_label'] ?? '00:00')
                    .'. Horas contrato asociadas: '.($pieContrato['educadoras_diferenciales_label_exactas'] ?? '00:00')
                    .'. Bolsa contractual redondeada: '.$pieContrato['educadoras_diferenciales_label'].' h.'
                    .($detalleProporciones !== '' ? ' '.$detalleProporciones.'.' : ''),
            ];
        }
        return $bloques;
    }


    private static function cursosConNeeMap(Establecimiento $establecimiento, int $anio): array
    {
        if (! self::schemaHasTable('establecimiento_curso_pie')) {
            return [];
        }

        $query = DB::table('establecimiento_curso_pie')
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio);

        self::applyCursosNeeCondition($query);

        $columns = ['curso_id'];
        if (self::schemaHasColumn('establecimiento_curso_pie', 'establecimiento_curso_id')) {
            $columns[] = 'establecimiento_curso_id';
        }

        return $query->get($columns)
            ->flatMap(function ($row) {
                $keys = [];
                if (isset($row->establecimiento_curso_id) && $row->establecimiento_curso_id) {
                    $keys[] = 'ec_'.$row->establecimiento_curso_id;
                }
                if (isset($row->curso_id) && $row->curso_id) {
                    $keys[] = 'curso_'.$row->curso_id;
                }
                return $keys;
            })
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($key) => [$key => true])
            ->all();
    }


    private static function applyCursosNeeCondition($query): void
    {
        $hasTotalPie = self::schemaHasColumn('establecimiento_curso_pie', 'total_pie');
        $hasNeet = self::schemaHasColumn('establecimiento_curso_pie', 'necesidades_transitorias');
        $hasNeep = self::schemaHasColumn('establecimiento_curso_pie', 'necesidades_permanentes');

        $query->where(function ($subQuery) use ($hasTotalPie, $hasNeet, $hasNeep) {
            if ($hasTotalPie) {
                $subQuery->where('total_pie', '>', 0);
            }
            if ($hasNeet) {
                $method = $hasTotalPie ? 'orWhere' : 'where';
                $subQuery->{$method}('necesidades_transitorias', '>', 0);
            }
            if ($hasNeep) {
                $method = ($hasTotalPie || $hasNeet) ? 'orWhere' : 'where';
                $subQuery->{$method}('necesidades_permanentes', '>', 0);
            }
        });
    }

    private static function horasContratoPieEstablecimiento(Establecimiento $establecimiento, int $anio): array
    {
        if (! self::schemaHasTable('establecimiento_curso_pie')) {
            return [
                'cursos_nee' => 0,
                'prof_educ_dif_minutos' => 0,
                'prof_educ_dif_label' => '00:00',
                'educadoras_diferenciales_horas' => 0.0,
                'educadoras_diferenciales_horas_exactas' => 0.0,
                'educadoras_diferenciales_label' => '0',
                'educadoras_diferenciales_label_exactas' => '00:00',
                'trabajo_colaborativo_horas' => 0.0,
                'detalle_proporciones' => [],
            ];
        }

        $recordsQuery = EstablecimientoCursoPie::query()
            ->with(['establecimientoCurso.curso', 'establecimientoCurso.planEstudio'])
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio);

        self::applyCursosNeeCondition($recordsQuery);
        $records = $recordsQuery->get();

        $cursosNee = $records
            ->map(fn ($row) => $row->establecimiento_curso_id ?: ('curso_'.$row->curso_id))
            ->filter()
            ->unique()
            ->count();

        $porcentajePrioritarios = self::porcentajePrioritarios($establecimiento, $anio);
        $profEducDifMinutos = 0;
        $contratoMinutosExactos = 0.0;
        $detalleProporciones = [];

        foreach ($records as $record) {
            $profMinutos = max(0, (int) ($record->prof_educ_dif_minutos ?? 0));
            $profEducDifMinutos += $profMinutos;

            $curso = $record->establecimientoCurso;
            $baseMinutos = 1710;
            $proporcionLabel = '65/35';

            if ($curso) {
                $referencia = DocenteHorasNoLectivasCalculator::referenceFor($curso, $porcentajePrioritarios);
                $baseMinutos = (int) (($referencia['horas_aula_cronologicas_minutos'] ?? 1710) ?: 1710);
                $proporcionLabel = (string) ($referencia['proporcion_label'] ?? '65/35');
            }

            $conversion = DocenteHorasNoLectivasCalculator::contratoAsociadoDesdeMinutosAula($profMinutos, $baseMinutos);
            $contratoMinutosExactos += (float) ($conversion['minutos_contrato_exactos'] ?? 0.0);

            if (! isset($detalleProporciones[$proporcionLabel])) {
                $detalleProporciones[$proporcionLabel] = [
                    'base_minutos' => $baseMinutos,
                    'base_label' => DocenteHorasNoLectivasCalculator::formatMinutes($baseMinutos),
                    'prof_educ_dif_minutos' => 0,
                    'contrato_minutos_exactos' => 0.0,
                ];
            }

            $detalleProporciones[$proporcionLabel]['prof_educ_dif_minutos'] += $profMinutos;
            $detalleProporciones[$proporcionLabel]['contrato_minutos_exactos'] += (float) ($conversion['minutos_contrato_exactos'] ?? 0.0);
        }

        foreach ($detalleProporciones as &$detalle) {
            $detalle['contrato_minutos_redondeados'] = (int) round((float) $detalle['contrato_minutos_exactos']);
            $detalle['prof_educ_dif_label'] = DocenteHorasNoLectivasCalculator::formatMinutes((int) $detalle['prof_educ_dif_minutos']);
            $detalle['contrato_label'] = DocenteHorasNoLectivasCalculator::formatMinutes((int) $detalle['contrato_minutos_redondeados']);
        }
        unset($detalle);
        ksort($detalleProporciones);

        // La bolsa contractual se calcula curso a curso según 65/35 o 60/40,
        // suma los minutos exactos y redondea una sola vez hacia arriba.
        $contratoMinutosRedondeados = (int) round($contratoMinutosExactos);
        $educDifHorasExactas = $contratoMinutosExactos > 0 ? $contratoMinutosExactos / 60 : 0.0;
        $educDifHorasBolsa = $educDifHorasExactas > 0 ? (float) ceil($educDifHorasExactas) : 0.0;

        return [
            'cursos_nee' => $cursosNee,
            'prof_educ_dif_minutos' => $profEducDifMinutos,
            'prof_educ_dif_label' => DocenteHorasNoLectivasCalculator::formatMinutes($profEducDifMinutos),
            'educadoras_diferenciales_horas' => $educDifHorasBolsa,
            'educadoras_diferenciales_horas_exactas' => round($educDifHorasExactas, 4),
            'educadoras_diferenciales_label' => self::formatHoras($educDifHorasBolsa),
            'educadoras_diferenciales_label_exactas' => DocenteHorasNoLectivasCalculator::formatMinutes($contratoMinutosRedondeados),
            'trabajo_colaborativo_horas' => (float) ($cursosNee * 3),
            'detalle_proporciones' => $detalleProporciones,
        ];
    }

    public static function docentes(Establecimiento $establecimiento, int $anio): Collection
    {
        if (! self::schemaHasTable('reemplazos_personal')) {
            return collect();
        }

        $query = ReemplazoPersonal::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio);

        if (self::schemaHasColumn('reemplazos_personal', 'vigente')) {
            $query->where('vigente', true);
        }

        $personal = $query->orderByDesc('mes')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $personalConsolidado = self::consolidarPersonalUltimoPeriodo($personal);
        $exclusionesPorRut = self::exclusionesDocentesPorRut($establecimiento, $anio);

        $declaraciones = self::declaracionesPorRut(
            $establecimiento,
            $personalConsolidado->pluck('representante.rut')->filter()->all()
        );
        $personalConsolidado = $personalConsolidado->filter(function (array $grupo) use ($declaraciones) {
            /** @var ReemplazoPersonal $row */
            $row = $grupo['representante'];
            $rut = self::normalizeRut($row->rut);
            $declaracion = $declaraciones[$rut] ?? null;

            if (self::declaracionEsAsistente($declaracion)) {
                return false;
            }

            if (self::declaracionEsDocente($declaracion)) {
                return true;
            }

            return self::isDocentePersonal($row);
        })->values();
        $asignacionesPorRut = DotacionAsignacionCalculator::assignmentsByRut($establecimiento, $anio);

        return $personalConsolidado->map(function (array $grupo) use ($declaraciones, $asignacionesPorRut, $exclusionesPorRut) {
            /** @var ReemplazoPersonal $row */
            $row = $grupo['representante'];
            $rut = self::normalizeRut($row->rut);
            $declaracion = $declaraciones[$rut] ?? null;
            $horasContratoBase = self::firstPositive([
                $declaracion?->horas_contratadas ?? null,
                $grupo['jornada_total'] ?? null,
            ]);
            $exclusionDocente = $exclusionesPorRut[$rut] ?? null;
            $ajusteContrato = self::ajustarHorasContratoPorExclusion(
                $horasContratoBase,
                $exclusionDocente?->horas
            );
            $horasContrato = $ajusteContrato['horas_consideradas'];
            $horasBasicaDeclarada = (float) ($grupo['jornada_basica_total'] ?? 0);
            $horasMediaDeclarada = (float) ($grupo['jornada_media_total'] ?? 0);
            $asignacionesDocente = $asignacionesPorRut[$rut] ?? [
                'items' => collect(),
                'total' => 0.0,
                'aula' => 0.0,
                'aula_65_35' => 0.0,
                'aula_60_40' => 0.0,
                'aula_especial' => 0.0,
                'contrato_65_35' => 0.0,
                'contrato_60_40' => 0.0,
                'contrato_especial' => 0.0,
                'funciones_total' => 0.0,
                'pie' => 0.0,
                'directivas' => 0.0,
                'tecnico_pedagogicas' => 0.0,
                'planes' => 0.0,
                'otras' => 0.0,
                'subvenciones' => collect(),
            ];
            $funcionesTecnicoPedagogicasDetalle = self::detalleFuncionesTecnicoPedagogicas($asignacionesDocente['items'] ?? collect());
            $otrasFuncionesDetalle = self::detalleOtrasFunciones($asignacionesDocente['items'] ?? collect());
            $horasBasica = 0.0;
            $horasMedia = 0.0;
            $horasAula = (float) ($asignacionesDocente['aula'] ?? 0);
            $funcion = $declaracion?->nombre_funcion ?: ($row->escalafon ?: 'Sin función declarada');
            $categoriaFuncion = self::categoriaFuncionDocente($funcion, $declaracion?->estamento ?: ($row->estatuto ?: null));
            $asignacionFunciones = self::asignacionFuncionDocente($categoriaFuncion, 0.0, $funcion);
            $horasAsignadasTotal = (float) ($asignacionesDocente['total'] ?? 0);
            $diferenciaAsignacion = $horasContrato !== null ? round((float) $horasContrato - $horasAsignadasTotal, 2) : null;
            $estadoCuadratura = self::estadoCuadraturaDocente($horasContrato, $horasAsignadasTotal, $diferenciaAsignacion, (bool) $declaracion);
            $titularidad = DotacionAsignaturaResumenCalculator::titularidad($row->tipocontrato);

            return [
                'rut' => $row->rut,
                'rut_normalizado' => $rut,
                'nombre' => self::nombreDocente($row, $declaracion),
                'titulo' => $declaracion?->nombre_titulo ?: 'Sin título declarado',
                'funcion' => $funcion,
                'categoria_funcion' => $categoriaFuncion,
                'estamento' => $declaracion?->estamento ?: ($row->estatuto ?: 'Docente'),
                'estamento_cobertura' => 'docente',
                'horas_contrato_base' => $ajusteContrato['horas_base'],
                'horas_excluidas' => $ajusteContrato['horas_excluidas'],
                'horas_contrato' => $horasContrato,
                'exclusion_docente' => $exclusionDocente ? [
                    'id' => (int) $exclusionDocente->id,
                    'motivo' => (string) $exclusionDocente->motivo,
                    'motivo_label' => $exclusionDocente->motivo_label,
                    'horas' => $ajusteContrato['horas_excluidas'],
                ] : null,
                'horas_planta' => (float) ($grupo['jornada_planta_total'] ?? 0),
                'horas_contrata' => (float) ($grupo['jornada_contrata_total'] ?? 0),
                'horas_aula' => $horasAula,
                'horas_aula_65_35' => (float) ($asignacionesDocente['aula_65_35'] ?? 0),
                'horas_aula_60_40' => (float) ($asignacionesDocente['aula_60_40'] ?? 0),
                'horas_aula_especial' => (float) ($asignacionesDocente['aula_especial'] ?? 0),
                'horas_contrato_65_35' => (float) ($asignacionesDocente['contrato_65_35'] ?? 0),
                'horas_contrato_60_40' => (float) ($asignacionesDocente['contrato_60_40'] ?? 0),
                'horas_contrato_especial' => (float) ($asignacionesDocente['contrato_especial'] ?? 0),
                'horas_funciones_total' => (float) ($asignacionesDocente['funciones_total'] ?? 0),
                'horas_basica' => $horasBasica,
                'horas_media' => $horasMedia,
                'horas_directivas' => (float) ($asignacionesDocente['directivas'] ?? 0),
                'horas_tecnico_pedagogicas' => (float) ($asignacionesDocente['tecnico_pedagogicas'] ?? 0),
                'horas_pie' => (float) ($asignacionesDocente['pie'] ?? 0),
                'horas_planes' => (float) ($asignacionesDocente['planes'] ?? 0),
                'horas_otras_funciones' => (float) ($asignacionesDocente['otras'] ?? 0),
                'funciones_tecnico_pedagogicas_detalle' => $funcionesTecnicoPedagogicasDetalle,
                'otras_funciones_detalle' => $otrasFuncionesDetalle,
                'asignacion_funciones' => $asignacionFunciones,
                'asignaciones' => $asignacionesDocente['items'] ?? collect(),
                'subvenciones' => $asignacionesDocente['subvenciones'] ?? collect(),
                'horas_asignadas_total' => $horasAsignadasTotal,
                'horas_no_lectivas_ref' => null,
                'horas_basica_declarada' => $horasBasicaDeclarada,
                'horas_media_declarada' => $horasMediaDeclarada,
                'diferencia' => $diferenciaAsignacion,
                'estado_cuadratura' => $estadoCuadratura,
                'financiamiento' => ($grupo['financiamientos'] ?? '') ?: ($row->financiamiento ?: 'Sin financiamiento'),
                'tipo_contrato' => ($grupo['tipos_contrato'] ?? '') ?: ($row->tipocontrato ?: 'Sin tipo contrato'),
                'titularidad' => $titularidad,
                'es_titular' => (bool) ($titularidad['es_titular'] ?? false),
                'mes' => (int) ($grupo['mes'] ?? $row->mes ?? 0),
                'anio' => (int) ($grupo['anio'] ?? $row->anio ?? 0),
                'fuente_contrato' => $declaracion ? 'declaracion_sostenedor' : 'reemplazos_personal',
                'registros_contrato' => (int) ($grupo['registros'] ?? 1),
                'horas_contrato_componentes' => $declaracion
                    ? [(float) $horasContratoBase]
                    : collect($grupo['componentes_jornada'] ?? [])->values()->all(),
                'horas_contrato_detalle' => $declaracion
                    ? null
                    : self::detalleComposicionContrato($grupo['componentes_jornada'] ?? [], $horasContratoBase),
                'niveles_declarados' => self::nivelesDeclarados($declaracion),
                'tiene_declaracion' => (bool) $declaracion,
                'declaracion' => $declaracion,
            ];
        })->sortBy('nombre')->values();
    }

    /**
     * @return array<string, DotacionDocenteExclusion>
     */
    private static function exclusionesDocentesPorRut(Establecimiento $establecimiento, int $anio): array
    {
        if (! self::schemaHasTable('dotacion_docente_exclusiones')) {
            return [];
        }

        return DotacionDocenteExclusion::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->get()
            ->keyBy(fn (DotacionDocenteExclusion $exclusion) => self::normalizeRut(
                $exclusion->docente_rut_normalizado ?: $exclusion->docente_rut
            ))
            ->all();
    }

    /**
     * @return array{horas_base: ?float, horas_excluidas: float, horas_consideradas: ?float}
     */
    private static function ajustarHorasContratoPorExclusion(
        float|int|null $horasContratoBase,
        float|int|string|null $horasExcluidas
    ): array
    {
        if ($horasContratoBase === null) {
            return [
                'horas_base' => null,
                'horas_excluidas' => 0.0,
                'horas_consideradas' => null,
            ];
        }

        $base = max(0.0, round((float) $horasContratoBase, 2));
        $excluidas = min($base, max(0.0, round((float) ($horasExcluidas ?? 0), 2)));

        return [
            'horas_base' => $base,
            'horas_excluidas' => $excluidas,
            'horas_consideradas' => max(0.0, round($base - $excluidas, 2)),
        ];
    }

    public static function asistentes(Establecimiento $establecimiento, int $anio): Collection
    {
        if (! self::schemaHasTable('reemplazos_personal')) {
            return collect();
        }

        $query = ReemplazoPersonal::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio);

        if (self::schemaHasColumn('reemplazos_personal', 'vigente')) {
            $query->where('vigente', true);
        }

        $personal = $query->orderByDesc('mes')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $latest = $personal
            ->groupBy(fn ($row) => self::normalizeRut($row->rut))
            ->map(fn ($items) => $items->sortByDesc(fn ($row) => sprintf('%04d%02d%010d', (int) $row->anio, (int) $row->mes, (int) $row->id))->first())
            ->values();

        $declaraciones = self::declaracionesPorRut($establecimiento, $latest->pluck('rut')->all());
        $latest = $latest->filter(function ($row) use ($declaraciones) {
            $rut = self::normalizeRut($row->rut);
            $declaracion = $declaraciones[$rut] ?? null;

            if (self::declaracionEsAsistente($declaracion)) {
                return true;
            }

            if (self::declaracionEsDocente($declaracion)) {
                return false;
            }

            return self::isAsistentePersonal($row);
        })->values();

        $asignacionesPorRut = DotacionAsignacionCalculator::assistantAssignmentsByRut($establecimiento, $anio);

        return $latest->map(function ($row) use ($declaraciones, $asignacionesPorRut) {
            $rut = self::normalizeRut($row->rut);
            $declaracion = $declaraciones[$rut] ?? null;
            $horasContrato = self::firstPositive([
                $declaracion?->horas_contratadas ?? null,
                $row->jornada ?? null,
            ]);
            $asignaciones = $asignacionesPorRut[$rut] ?? [
                'items' => collect(),
                'total' => 0.0,
                'aula' => 0.0,
                'funciones_total' => 0.0,
                'subvenciones' => collect(),
            ];
            $horasAsignadas = (float) ($asignaciones['total'] ?? 0);
            $diferencia = $horasContrato !== null ? round((float) $horasContrato - $horasAsignadas, 2) : null;
            $funcion = $declaracion?->nombre_funcion ?: ($row->escalafon ?: 'Asistente de la educación');

            return [
                'rut' => $row->rut,
                'rut_normalizado' => $rut,
                'nombre' => self::nombreDocente($row, $declaracion),
                'titulo' => $declaracion?->nombre_titulo ?: 'Sin título declarado',
                'funcion' => $funcion,
                'estamento' => 'Asistente de la educación',
                'estamento_cobertura' => 'asistente',
                'horas_contrato' => $horasContrato,
                'horas_aula' => (float) ($asignaciones['aula'] ?? 0),
                'horas_funciones_total' => (float) ($asignaciones['funciones_total'] ?? 0),
                'horas_asignadas_total' => $horasAsignadas,
                'diferencia' => $diferencia,
                'estado_cuadratura' => self::estadoCuadraturaDocente($horasContrato, $horasAsignadas, $diferencia, (bool) $declaracion),
                'financiamiento' => $row->financiamiento ?: 'Sin financiamiento',
                'tipo_contrato' => $row->tipocontrato ?: 'Sin tipo contrato',
                'mes' => (int) ($row->mes ?? 0),
                'anio' => (int) ($row->anio ?? 0),
                'tiene_declaracion' => (bool) $declaracion,
                'declaracion' => $declaracion,
                'asignaciones' => $asignaciones['items'] ?? collect(),
                'subvenciones' => $asignaciones['subvenciones'] ?? collect(),
            ];
        })->sortBy('nombre')->values();
    }


    /**
     * Consolida todas las líneas contractuales vigentes del último período
     * disponible para el establecimiento y año. No conserva RUT de meses
     * anteriores y evita perder jornadas complementarias registradas en filas
     * separadas del mismo padrón mensual.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function consolidarPersonalUltimoPeriodo(Collection $personal): Collection
    {
        $periodo = (int) $personal->max(
            fn ($row) => ((int) ($row->anio ?? 0) * 100) + (int) ($row->mes ?? 0)
        );

        if ($periodo <= 0) {
            return collect();
        }

        $anio = intdiv($periodo, 100);
        $mes = $periodo % 100;

        return $personal
            ->filter(fn ($row) => (int) ($row->anio ?? 0) === $anio && (int) ($row->mes ?? 0) === $mes)
            ->groupBy(fn ($row) => self::normalizeRut($row->rut))
            ->map(function (Collection $items) use ($anio, $mes) {
                $rowsPeriodo = $items
                    ->unique(fn ($row) => self::claveLineaContractual($row))
                    ->sortByDesc(fn ($row) => (int) ($row->id ?? 0))
                    ->values();

                /** @var ReemplazoPersonal|null $representante */
                $representante = $rowsPeriodo->first();
                $componentes = $rowsPeriodo
                    ->map(fn ($row) => is_numeric($row->jornada) ? (float) $row->jornada : 0.0)
                    ->filter(fn ($horas) => $horas > 0)
                    ->sortDesc()
                    ->values();
                $jornadaPlanta = (float) $rowsPeriodo
                    ->filter(fn ($row) => self::calidadJuridicaContrato($row->tipocontrato) === 'planta')
                    ->sum(fn ($row) => is_numeric($row->jornada) ? (float) $row->jornada : 0.0);
                $jornadaContrata = (float) $rowsPeriodo
                    ->filter(fn ($row) => self::calidadJuridicaContrato($row->tipocontrato) === 'contrata')
                    ->sum(fn ($row) => is_numeric($row->jornada) ? (float) $row->jornada : 0.0);

                return [
                    'representante' => $representante,
                    'rows' => $rowsPeriodo,
                    'anio' => $anio,
                    'mes' => $mes,
                    'registros' => $rowsPeriodo->count(),
                    'jornada_total' => round((float) $componentes->sum(), 2),
                    'jornada_planta_total' => round($jornadaPlanta, 2),
                    'jornada_contrata_total' => round($jornadaContrata, 2),
                    'jornada_basica_total' => round((float) $rowsPeriodo->sum(fn ($row) => is_numeric($row->jornada_basica) ? (float) $row->jornada_basica : 0.0), 2),
                    'jornada_media_total' => round((float) $rowsPeriodo->sum(fn ($row) => is_numeric($row->jornada_media) ? (float) $row->jornada_media : 0.0), 2),
                    'componentes_jornada' => $componentes,
                    'tipos_contrato' => self::resumenValoresPersonal($rowsPeriodo, 'tipocontrato'),
                    'financiamientos' => self::resumenValoresPersonal($rowsPeriodo, 'financiamiento'),
                ];
            })
            ->filter(fn ($grupo) => $grupo['representante'] instanceof ReemplazoPersonal)
            ->values();
    }

    private static function calidadJuridicaContrato(?string $tipoContrato): ?string
    {
        $tipo = self::normalizeText((string) $tipoContrato);

        if (str_contains($tipo, 'CONTRATA')) {
            return 'contrata';
        }

        if (str_contains($tipo, 'PLANTA') || str_contains($tipo, 'TITULAR')) {
            return 'planta';
        }

        return null;
    }

    private static function claveLineaContractual(ReemplazoPersonal $row): string
    {
        if (filled($row->row_hash)) {
            return (string) $row->row_hash;
        }

        return implode('|', [
            self::normalizeRut($row->rut),
            (string) ($row->establecimiento_id ?? ''),
            (string) ($row->anio ?? ''),
            (string) ($row->mes ?? ''),
            optional($row->fecha_ingreso)->format('Y-m-d') ?? '',
            optional($row->fecha_termino)->format('Y-m-d') ?? '',
            self::normalizeText($row->tipocontrato),
            self::normalizeText($row->financiamiento),
            self::normalizeText($row->estatuto),
            self::normalizeText($row->escalafon),
            (string) ($row->jornada ?? ''),
            (string) ($row->jornada_basica ?? ''),
            (string) ($row->jornada_media ?? ''),
        ]);
    }

    private static function resumenValoresPersonal(Collection $rows, string $campo): string
    {
        return $rows
            ->pluck($campo)
            ->map(fn ($valor) => trim((string) $valor))
            ->filter()
            ->unique(fn ($valor) => self::normalizeText($valor))
            ->implode(' / ');
    }

    private static function detalleComposicionContrato(iterable $componentes, ?float $total): ?string
    {
        $horas = collect($componentes)
            ->filter(fn ($valor) => is_numeric($valor) && (float) $valor > 0)
            ->map(fn ($valor) => (float) $valor)
            ->values();

        if ($horas->count() <= 1 || $total === null) {
            return null;
        }

        return $horas
            ->map(fn ($valor) => self::formatHoras($valor))
            ->implode(' + ').' = '.self::formatHoras($total).' h';
    }

    /**
     * @return array<int, array{nombre: string, horas: float}>
     */
    private static function detalleOtrasFunciones(iterable $asignaciones): array
    {
        return self::detalleFuncionesPorTipo($asignaciones, 'otra_funcion', 'Otra función');
    }

    /**
     * @return array<int, array{nombre: string, horas: float}>
     */
    private static function detalleFuncionesTecnicoPedagogicas(iterable $asignaciones): array
    {
        return self::detalleFuncionesPorTipo(
            $asignaciones,
            'funcion_tecnico_pedagogica',
            'Función técnico-pedagógica'
        );
    }

    /**
     * @return array<int, array{nombre: string, horas: float}>
     */
    private static function detalleFuncionesPorTipo(
        iterable $asignaciones,
        string $tipoAsignacion,
        string $nombreFallback
    ): array
    {
        return collect($asignaciones)
            ->filter(fn ($row) => ($row->tipo_asignacion ?? null) === $tipoAsignacion
                && is_numeric($row->horas_contrato ?? null)
                && (float) $row->horas_contrato > 0.01)
            ->groupBy(function ($row) use ($nombreFallback) {
                $nombre = trim((string) ($row->asignatura_nombre ?? ''));

                return self::normalizeText($nombre !== '' ? $nombre : $nombreFallback);
            })
            ->map(function (Collection $rows) use ($nombreFallback) {
                $representante = $rows->first();
                $nombre = trim((string) ($representante->asignatura_nombre ?? ''));

                return [
                    'nombre' => $nombre !== '' ? $nombre : $nombreFallback,
                    'horas' => round((float) $rows->sum(fn ($row) => (float) ($row->horas_contrato ?? 0)), 2),
                ];
            })
            ->filter(fn (array $item) => $item['horas'] > 0.01)
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private static function categoriaFuncionDocente(?string $funcion, ?string $estamento = null): string
    {
        $texto = self::normalizeText(collect([$funcion, $estamento])->filter()->implode(' '));

        if ($texto === '') {
            return 'aula';
        }

        if (str_contains($texto, 'DIRECTOR') || str_contains($texto, 'INSPECTOR GENERAL') || str_contains($texto, 'JEFE UTP') || str_contains($texto, 'JEFA UTP') || str_contains($texto, 'UNIDAD TECNICO PEDAGOGICA') || str_contains($texto, 'UNIDAD TECNICA PEDAGOGICA')) {
            return 'directivas';
        }

        if (str_contains($texto, 'PIE') || str_contains($texto, 'INTEGRACION') || str_contains($texto, 'INTEGRACIÓN') || str_contains($texto, 'EDUCADOR DIFERENCIAL') || str_contains($texto, 'EDUCADORA DIFERENCIAL')) {
            return 'pie';
        }

        if (str_contains($texto, 'PISE') || str_contains($texto, 'CRA') || str_contains($texto, 'FORMACION CIUDADANA') || str_contains($texto, 'FORMACIÓN CIUDADANA') || str_contains($texto, 'AFECTIVIDAD') || str_contains($texto, 'SEXUALIDAD') || str_contains($texto, 'GENERO') || str_contains($texto, 'GÉNERO') || str_contains($texto, 'CENTRO DE ESTUDIANTES') || str_contains($texto, 'CENTRO DE PADRES') || str_contains($texto, 'ALIMENTACION') || str_contains($texto, 'ALIMENTACIÓN') || str_contains($texto, 'PAE')) {
            return 'planes';
        }

        if (str_contains($texto, 'COORDINADOR') || str_contains($texto, 'COORDINADORA') || str_contains($texto, 'CONVIVENCIA') || str_contains($texto, 'ORIENTADOR') || str_contains($texto, 'ORIENTADORA') || str_contains($texto, 'EVALUADOR') || str_contains($texto, 'EVALUADORA') || str_contains($texto, 'CURRICULISTA') || str_contains($texto, 'CURRICULAR') || str_contains($texto, 'EXTRAESCOLAR') || str_contains($texto, 'APOYO UTP')) {
            return 'tecnico_pedagogicas';
        }

        if (str_contains($texto, 'SUBDIRECTOR') || str_contains($texto, 'SUBDIRECTORA') || str_contains($texto, 'APOYO') || str_contains($texto, 'ENCARGADO') || str_contains($texto, 'ENCARGADA')) {
            return 'otras';
        }

        return 'aula';
    }

    private static function asignacionFuncionDocente(string $categoria, float $horas, ?string $funcion): array
    {
        $base = [
            'directivas' => ['label' => 'Directivas', 'horas' => 0.0, 'detalle' => null],
            'tecnico_pedagogicas' => ['label' => 'Técnico-pedagógicas', 'horas' => 0.0, 'detalle' => null],
            'pie' => ['label' => 'PIE', 'horas' => 0.0, 'detalle' => null],
            'planes' => ['label' => 'Planes', 'horas' => 0.0, 'detalle' => null],
            'otras' => ['label' => 'Otras funciones', 'horas' => 0.0, 'detalle' => null],
        ];

        if (isset($base[$categoria]) && $horas > 0) {
            $base[$categoria]['horas'] = round($horas, 2);
            $base[$categoria]['detalle'] = $funcion ?: 'Función declarada';
        }

        return $base;
    }

    private static function estadoCuadraturaDocente(float|int|null $horasContrato, float $horasAsignadas, ?float $diferencia, bool $tieneDeclaracion): array
    {
        if (! $tieneDeclaracion) {
            return ['key' => 'sin_declaracion', 'label' => 'Sin declaración', 'class' => 'text-bg-warning', 'detalle' => 'No tiene registro asociado en declaración sostenedor.'];
        }

        if ($horasContrato === null) {
            return ['key' => 'sin_horas_contrato', 'label' => 'Sin horas contrato', 'class' => 'text-bg-secondary', 'detalle' => 'No hay horas contratadas disponibles para cuadrar.'];
        }

        if ($diferencia !== null && $diferencia < -0.01) {
            return ['key' => 'sobrecarga', 'label' => 'Sobrecarga', 'class' => 'text-bg-danger', 'detalle' => 'Las horas asignadas superan las horas contratadas.'];
        }

        if ($diferencia !== null && $diferencia > 0.01) {
            if ($horasAsignadas <= 0.01) {
                return ['key' => 'pendiente_asignacion', 'label' => 'Pendiente asignación', 'class' => 'text-bg-secondary', 'detalle' => 'El docente tiene horas contratadas, pero aún no registra horas asignadas en este módulo.'];
            }

            return ['key' => 'faltan_horas', 'label' => 'Faltan horas', 'class' => 'text-bg-info', 'detalle' => 'Existen horas contratadas sin asignación clasificada.'];
        }

        return ['key' => 'cuadra', 'label' => 'Cuadra', 'class' => 'text-bg-success', 'detalle' => 'Horas contratadas y asignadas cuadran.'];
    }

    public static function normalizeRut(?string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
    }

    public static function formatHoras(float|int|null $horas): string
    {
        if ($horas === null) {
            return '—';
        }
        $value = (float) $horas;
        if (abs($value - round($value)) < 0.01) {
            return number_format((int) round($value), 0, ',', '.');
        }
        return number_format($value, 2, ',', '.');
    }

    private static function nivelesBase(): array
    {
        $rows = [
            'NT1' => self::rowNivel('NT1', 'Educación Parvularia', 10),
            'NT2' => self::rowNivel('NT2', 'Educación Parvularia', 20),
            '1B' => self::rowNivel('1° Básico', 'Educación Básica', 30),
            '2B' => self::rowNivel('2° Básico', 'Educación Básica', 40),
            '3B' => self::rowNivel('3° Básico', 'Educación Básica', 50),
            '4B' => self::rowNivel('4° Básico', 'Educación Básica', 60),
            '5B' => self::rowNivel('5° Básico', 'Educación Básica', 70),
            '6B' => self::rowNivel('6° Básico', 'Educación Básica', 80),
            '7B' => self::rowNivel('7° Básico', 'Educación Básica', 90),
            '8B' => self::rowNivel('8° Básico', 'Educación Básica', 100),
            '1M' => self::rowNivel('1° Medio', 'Educación Media', 110),
            '2M' => self::rowNivel('2° Medio', 'Educación Media', 120),
            '3M' => self::rowNivel('3° Medio', 'Educación Media', 130),
            '4M' => self::rowNivel('4° Medio', 'Educación Media', 140),
        ];

        return [
            'grupos' => [
                'parvularia' => ['label' => 'Educación Parvularia', 'niveles' => ['NT1', 'NT2'], 'order' => 10],
                'basica' => ['label' => 'Educación Básica', 'niveles' => ['1B', '2B', '3B', '4B', '5B', '6B', '7B', '8B'], 'order' => 20],
                'media' => ['label' => 'Educación Media', 'niveles' => ['1M', '2M', '3M', '4M'], 'order' => 30],
                'epja' => ['label' => 'EPJA', 'niveles' => [], 'order' => 40],
                'especial' => ['label' => 'Educación Especial', 'niveles' => [], 'order' => 50],
                'otros' => ['label' => 'Otros niveles', 'niveles' => [], 'order' => 60],
            ],
            'rows' => $rows,
            'totales' => ['matricula' => 0, 'cursos' => 0, 'horas' => 0.0, 'horas_contrato_equivalente' => 0.0, 'trabajo_colaborativo_pie' => 0.0, 'contrato_mas_trabajo_colaborativo_pie' => 0.0, 'sin_horas_plan' => 0],
        ];
    }

    private static function rowNivel(string $label, string $grupo, int $order = 9999): array
    {
        return [
            'label' => $label,
            'grupo' => $grupo,
            'order' => $order,
            'matricula' => 0,
            'cursos' => 0,
            'horas_por_nivel' => null,
            'horas_variable' => false,
            'total_horas' => 0.0,
            'total_horas_contrato_equivalente' => 0.0,
            'total_trabajo_colaborativo_pie' => 0.0,
            'total_contrato_mas_trabajo_colaborativo_pie' => 0.0,
            'horas_valores' => [],
            'proporcion_valores' => [],
            'origen_proporcion_valores' => [],
            'sin_horas_plan' => 0,
            'detalles' => [],
        ];
    }

    private static function ensureNivelRow(array &$base, array $nivel): void
    {
        $grupoKey = $nivel['grupo_key'];
        if (! isset($base['grupos'][$grupoKey])) {
            $base['grupos'][$grupoKey] = [
                'label' => $nivel['grupo_label'],
                'niveles' => [],
                'order' => $nivel['grupo_order'] ?? 9999,
            ];
        }

        if (! isset($base['rows'][$nivel['key']])) {
            $base['rows'][$nivel['key']] = self::rowNivel($nivel['label'], $nivel['grupo_label'], $nivel['order'] ?? 9999);
        }

        if (! in_array($nivel['key'], $base['grupos'][$grupoKey]['niveles'] ?? [], true)) {
            $base['grupos'][$grupoKey]['niveles'][] = $nivel['key'];
        }
    }

    private static function nivelMeta(EstablecimientoCurso $cursoEstablecimiento): array
    {
        $curso = $cursoEstablecimiento->curso;
        $texto = self::normalizeText(collect([
            $curso?->codigo,
            $curso?->nombre,
            $curso?->nivel_educativo,
            $curso?->modalidad,
            $cursoEstablecimiento->nombre_seccion,
        ])->filter()->implode(' '));

        if (str_contains($texto, 'EPJA') || str_contains($texto, 'ADULTO')) {
            $label = self::realCourseLabel($cursoEstablecimiento);
            return [
                'key' => 'EPJA_'.self::safeKey($label),
                'label' => $label,
                'grupo_key' => 'epja',
                'grupo_label' => 'EPJA',
                'grupo_order' => 40,
                'order' => 4000 + (int) ($curso?->orden ?? 0),
            ];
        }

        if (str_contains($texto, 'LABORAL') || str_contains($texto, 'ESPECIAL') || str_contains($texto, 'DIFERENCIAL')) {
            $label = self::realCourseLabel($cursoEstablecimiento);
            return [
                'key' => 'ESP_'.self::safeKey($label),
                'label' => $label,
                'grupo_key' => 'especial',
                'grupo_label' => 'Educación Especial',
                'grupo_order' => 50,
                'order' => 5000 + (int) ($curso?->orden ?? 0),
            ];
        }

        $key = self::nivelKey($curso?->codigo, $curso?->nombre, $curso?->nivel_educativo);
        $baseGroup = match (true) {
            in_array($key, ['NT1', 'NT2'], true) => ['parvularia', 'Educación Parvularia', 10],
            in_array($key, ['1B', '2B', '3B', '4B', '5B', '6B', '7B', '8B'], true) => ['basica', 'Educación Básica', 20],
            in_array($key, ['1M', '2M', '3M', '4M'], true) => ['media', 'Educación Media', 30],
            default => ['otros', 'Otros niveles', 60],
        };

        if ($key === 'OTRO') {
            $label = self::realCourseLabel($cursoEstablecimiento);
            return [
                'key' => 'OTRO_'.self::safeKey($label),
                'label' => $label,
                'grupo_key' => $baseGroup[0],
                'grupo_label' => $baseGroup[1],
                'grupo_order' => $baseGroup[2],
                'order' => 6000 + (int) ($curso?->orden ?? 0),
            ];
        }

        return [
            'key' => $key,
            'label' => null,
            'grupo_key' => $baseGroup[0],
            'grupo_label' => $baseGroup[1],
            'grupo_order' => $baseGroup[2],
            'order' => (int) ($curso?->orden ?? 9999),
        ];
    }

    private static function realCourseLabel(EstablecimientoCurso $cursoEstablecimiento): string
    {
        $label = trim((string) ($cursoEstablecimiento->nombre_seccion ?: $cursoEstablecimiento->curso?->nombre ?: $cursoEstablecimiento->curso?->codigo ?: 'Curso sin nombre'));
        $letra = trim((string) ($cursoEstablecimiento->letra ?? ''));

        if ($letra !== '') {
            $label = preg_replace('/\s+'.preg_quote($letra, '/').'$/iu', '', $label) ?: $label;
            $label = preg_replace('/\s*-\s*'.preg_quote($letra, '/').'$/iu', '', $label) ?: $label;
        }

        return trim($label) !== '' ? trim($label) : 'Curso sin nombre';
    }

    private static function normalizeText(string $value): string
    {
        return Str::of($value)->ascii()->upper()->squish()->toString();
    }

    private static function safeKey(string $label): string
    {
        $key = Str::of($label)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->toString();
        return $key !== '' ? $key : 'SIN_NOMBRE';
    }

    private static function nivelKey(?string $codigo, ?string $nombre, ?string $nivel): string
    {
        $codigo = strtoupper(trim((string) $codigo));
        if (in_array($codigo, ['NT1', 'NT2', '1B', '2B', '3B', '4B', '5B', '6B', '7B', '8B', '1M', '2M'], true)) {
            return $codigo;
        }
        if (str_starts_with($codigo, '3M')) {
            return '3M';
        }
        if (str_starts_with($codigo, '4M')) {
            return '4M';
        }

        $texto = self::normalizeText(($nombre ?? '').' '.($nivel ?? ''));
        foreach (['NT1', 'NT2'] as $nt) {
            if (str_contains($texto, $nt)) {
                return $nt;
            }
        }
        for ($i = 1; $i <= 8; $i++) {
            if (str_contains($texto, $i.' BASIC') || str_contains($texto, $i.' BASICO')) {
                return $i.'B';
            }
        }
        for ($i = 1; $i <= 4; $i++) {
            if (str_contains($texto, $i.' MEDIO')) {
                return $i.'M';
            }
        }

        return 'OTRO';
    }

    private static function cursoTieneJec(EstablecimientoCurso $curso): bool
    {
        $plan = $curso->planEstudio;
        $texto = self::normalizeText(collect([
            $curso->regimen_jec ?? null,
            $curso->jornada ?? null,
            $curso->tipo_jornada ?? null,
            $plan?->nombre ?? null,
            $plan?->regimen_jec ?? null,
            $plan?->jornada ?? null,
            $plan?->tipo_jornada ?? null,
        ])->filter()->implode(' '));

        if (str_contains($texto, 'SIN JEC')) {
            return false;
        }

        return str_contains($texto, 'CON JEC')
            || str_contains($texto, 'JECD')
            || str_contains($texto, 'JEC');
    }

    private static function horasCurso(EstablecimientoCurso $curso): array
    {
        // Para dotación, las horas del curso deben corresponder al total del plan
        // de estudio asociado, no sólo a las asignaturas personalizadas del EE.
        // Ej.: si el EE sólo configura 6,50 h de libre disposición en 1° básico,
        // el plan sigue totalizando 38 h semanales porque el tiempo mínimo
        // obligatorio viene definido por el plan oficial.
        if ($curso->planEstudio) {
            $total = $curso->planEstudio->horas_semanales_total;
            if ($total !== null && (float) $total > 0) {
                return ['horas' => (float) $total, 'fuente' => 'Plan asociado'];
            }
        }

        if ($curso->plan_estudio_id && self::schemaHasTable('planes_estudio_bloques')) {
            $totalBlock = (float) DB::table('planes_estudio_bloques')
                ->where('plan_estudio_id', $curso->plan_estudio_id)
                ->where('activo', true)
                ->where('tipo_bloque', 'total')
                ->max('horas_semanales');
            if ($totalBlock > 0) {
                return ['horas' => $totalBlock, 'fuente' => 'Bloque total plan'];
            }
        }

        if ($curso->plan_estudio_id && self::schemaHasTable('planes_estudio_asignaturas')) {
            $sum = (float) DB::table('planes_estudio_asignaturas')
                ->where('plan_estudio_id', $curso->plan_estudio_id)
                ->sum('horas_semanales');
            if ($sum > 0) {
                return ['horas' => $sum, 'fuente' => 'Asignaturas plan oficial'];
            }
        }

        if ($curso->plan_estudio_id && self::schemaHasTable('planes_estudio_bloques')) {
            $sum = (float) DB::table('planes_estudio_bloques')
                ->where('plan_estudio_id', $curso->plan_estudio_id)
                ->where('activo', true)
                ->where('tipo_bloque', '<>', 'total')
                ->sum('horas_semanales');
            if ($sum > 0) {
                return ['horas' => $sum, 'fuente' => 'Bloques plan oficial'];
            }
        }

        return ['horas' => 0.0, 'fuente' => 'Sin horas de plan'];
    }


    public static function porcentajePrioritariosPara(Establecimiento $establecimiento, int $anio): ?float
    {
        return self::porcentajePrioritarios($establecimiento, $anio);
    }

    public static function contratoEquivalenteAsignacion(EstablecimientoCurso $curso, float $horasPlan, ?float $porcentajePrioritarios = null, ?string $subtipo = null): array
    {
        if ($horasPlan <= 0) {
            return [
                'proporcion' => null,
                'proporcion_label' => '—',
                'origen_proporcion' => null,
                'origen_proporcion_label' => '—',
                'horas_aula_cronologicas' => 0.0,
                'horas_contrato_equivalente' => 0.0,
                'horas_contrato_equivalente_redondeado' => 0.0,
                'motivo' => null,
            ];
        }

        // Las horas de libre disposición usan la misma proporción del curso.
        // En 1° a 4° básico con vulnerabilidad >= 80%, también deben convertirse con 60/40.
        return self::horasContratoEquivalenteCurso($curso, $horasPlan, $porcentajePrioritarios);
    }

    private static function porcentajePrioritarios(Establecimiento $establecimiento, int $anio): ?float
    {
        if (! self::schemaHasTable('alumnos_prioritarios_porcentajes')) {
            return null;
        }

        $row = AlumnoPrioritarioPorcentaje::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->orderByDesc('id')
            ->first();

        return $row && $row->porcentaje !== null ? (float) $row->porcentaje : null;
    }

    private static function horasContratoEquivalenteCurso(EstablecimientoCurso $curso, float $horasPlan, ?float $porcentajePrioritarios): array
    {
        $nivel = self::nivelMeta($curso);
        $nivelKey = $nivel['key'] ?? null;
        $referencia = DocenteHorasNoLectivasCalculator::referenceFor(
            $curso,
            $porcentajePrioritarios,
            DocenteHorasNoLectivasCalculator::HORAS_CONTRATO_REFERENCIA,
            true
        );
        $excepcionInstitucional = ($referencia['origen_proporcion'] ?? null) === 'excepcion_institucional';

        // Regla especial para Educación Parvularia NT1/NT2:
        // - Las primeras 32 horas del plan se tratan como horas de plan de estudio
        //   de Educación Parvularia, excluyendo libre disposición de la regla especial.
        // - Si el curso es Con JEC/JECD: 32 h plan requieren 32 h 15 m cronológicas,
        //   equivalentes a 50 h de contrato (41 h + 9 h).
        // - Si el curso es Sin JEC: 32 h plan requieren 30 h cronológicas,
        //   equivalentes a 47 h de contrato (41 h + 6 h).
        // - Las horas por sobre 32, normalmente libre disposición, se convierten
        //   aparte con la proporción general 65/35.
        if (! $excepcionInstitucional && in_array($nivelKey, ['NT1', 'NT2'], true) && $horasPlan > 0) {
            $horasBaseParvularia = min($horasPlan, 32.0);
            $horasLibreDisposicion = max(0.0, $horasPlan - 32.0);
            $cursoConJec = self::cursoTieneJec($curso);
            $contratoBaseReferencia = $cursoConJec ? 50.0 : 47.0;
            $cronologicasBaseReferencia = $cursoConJec ? 32.25 : 30.0;
            $contratoBaseParvularia = round(($horasBaseParvularia / 32.0) * $contratoBaseReferencia, 4);
            $cronologicasBaseParvularia = round(($horasBaseParvularia / 32.0) * $cronologicasBaseReferencia, 4);
            $cronologicasLibreDisposicion = $horasLibreDisposicion > 0 ? round($horasLibreDisposicion * 45 / 60, 4) : 0.0;
            $contratoLibreDisposicion = $horasLibreDisposicion > 0
                ? round($cronologicasLibreDisposicion / 0.65, 4)
                : 0.0;
            $horasContratoDecimal = round($contratoBaseParvularia + $contratoLibreDisposicion, 4);
            $horasAulaCronologicas = round($cronologicasBaseParvularia + $cronologicasLibreDisposicion, 4);
            $regimenLabel = $cursoConJec ? 'Con JEC' : 'Sin JEC';

            return [
                'proporcion' => $cursoConJec ? 'parvularia_jec_especial_65_35_ld' : 'parvularia_sin_jec_especial_65_35_ld',
                'proporcion_label' => $horasLibreDisposicion > 0 ? 'NT '.$regimenLabel.' especial + 65/35 LD' : 'NT '.$regimenLabel.' especial',
                'origen_proporcion' => 'regla_especial_parvularia',
                'origen_proporcion_label' => 'Regla especial Educación Parvularia',
                'horas_aula_cronologicas' => $horasAulaCronologicas,
                'horas_contrato_equivalente' => $horasContratoDecimal,
                'horas_contrato_equivalente_redondeado' => $horasContratoDecimal > 0 ? (float) ceil($horasContratoDecimal) : 0.0,
                'motivo' => $horasLibreDisposicion > 0
                    ? 'NT1/NT2 '.$regimenLabel.': 32 h plan se calculan con regla especial de aula cronológica; las horas de libre disposición se convierten con 65/35.'
                    : 'NT1/NT2 '.$regimenLabel.': 32 h plan se calculan con regla especial de aula cronológica.',
                'horas_base_parvularia' => $horasBaseParvularia,
                'horas_libre_disposicion_parvularia' => $horasLibreDisposicion,
                'cronologicas_base_parvularia' => $cronologicasBaseParvularia,
                'cronologicas_libre_disposicion_parvularia' => $cronologicasLibreDisposicion,
                'contrato_base_parvularia' => $contratoBaseParvularia,
                'contrato_libre_disposicion_parvularia' => $contratoLibreDisposicion,
                'regimen_parvularia' => $cursoConJec ? 'con_jec' : 'sin_jec',
            ];
        }

        $proporcion = $referencia['proporcion'] ?? DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL;
        $horasAulaCronologicas = $horasPlan > 0 ? round($horasPlan * 45 / 60, 2) : 0.0;
        $conversion = DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula($proporcion, $horasPlan);
        $horasContrato = (float) ($conversion['horas_contrato'] ?? 0);

        return [
            'proporcion' => $proporcion,
            'proporcion_label' => DocenteHorasNoLectivasCalculator::proporcionLabel($proporcion),
            'origen_proporcion' => $referencia['origen_proporcion'] ?? 'regla_general',
            'origen_proporcion_label' => $referencia['origen_proporcion_label'] ?? 'Regla general',
            'horas_aula_cronologicas' => $horasAulaCronologicas,
            'horas_contrato_equivalente' => $horasContrato,
            'horas_contrato_equivalente_redondeado' => $horasContrato,
            'motivo' => trim(($referencia['motivo'] ?? '').' '.($conversion['motivo'] ?? 'Las horas plan se convierten usando la tabla docente_horas_proporciones.')),
            'conversion_tabla' => $conversion,
        ];
    }

    private static function bloquesBase(): array
    {
        return [
            'directiva' => ['label' => 'Directivos', 'icon' => 'bi-person-badge', 'tone' => 'primary', 'automaticas' => 0, 'declaradas' => 0, 'total' => 0, 'items' => []],
            'tecnico_pedagogica' => ['label' => 'Técnico-pedagógicas', 'icon' => 'bi-diagram-3', 'tone' => 'success', 'automaticas' => 0, 'declaradas' => 0, 'total' => 0, 'items' => []],
            'pie' => ['label' => 'PIE', 'icon' => 'bi-universal-access', 'tone' => 'info', 'automaticas' => 0, 'declaradas' => 0, 'educadoras_diferenciales' => 0, 'total' => 0, 'items' => []],
            'planes_programas' => ['label' => 'Planes', 'icon' => 'bi-journal-check', 'tone' => 'warning', 'automaticas' => 0, 'declaradas' => 0, 'total' => 0, 'items' => []],
            'otras_funciones_docentes' => ['label' => 'Otras funciones declaradas', 'icon' => 'bi-plus-square-dotted', 'tone' => 'secondary', 'automaticas' => 0, 'declaradas' => 0, 'total' => 0, 'items' => []],
        ];
    }

    /** @return array<string, float> */
    private static function desgloseContratoBloqueDotacion(array $bloques, iterable $necesidadesFunciones = []): array
    {
        $necesidades = collect($necesidadesFunciones);
        $asignadasNormativas = function (string $subtipo) use ($necesidades): float {
            return round((float) $necesidades
                ->filter(fn ($item) => data_get($item, 'subtipo_asignacion') === $subtipo
                    && (int) data_get($item, 'dotacion_funcion_id', 0) <= 0)
                ->sum(fn ($item) => (float) data_get($item, 'horas_contrato_asignadas', 0)), 2);
        };

        return [
            'funciones_directivas' => (float) data_get($bloques, 'directiva.total', 0),
            'funciones_directivas_normativas' => (float) data_get($bloques, 'directiva.automaticas', 0),
            'funciones_directivas_declaradas' => (float) data_get($bloques, 'directiva.declaradas', 0),
            'funciones_directivas_normativas_asignadas' => $asignadasNormativas('directiva'),
            'funciones_tecnico_pedagogicas' => (float) data_get($bloques, 'tecnico_pedagogica.total', 0),
            'funciones_tecnico_pedagogicas_normativas' => (float) data_get($bloques, 'tecnico_pedagogica.automaticas', 0),
            'funciones_tecnico_pedagogicas_declaradas' => (float) data_get($bloques, 'tecnico_pedagogica.declaradas', 0),
            'funciones_tecnico_pedagogicas_normativas_asignadas' => $asignadasNormativas('tecnico_pedagogica'),
            'otras_funciones_pie' => (float) data_get($bloques, 'pie.total', 0),
            'planes_normativos' => (float) data_get($bloques, 'planes_programas.automaticas', 0),
            'planes_normativos_asignadas' => $asignadasNormativas('planes_programas'),
            'planes_declarados' => (float) data_get($bloques, 'planes_programas.declaradas', 0),
            'otras_funciones_declaradas' => (float) data_get($bloques, 'otras_funciones_docentes.total', 0),
            'total_normativas' => (float) collect($bloques)->sum(fn ($bloque) => (float) ($bloque['automaticas'] ?? 0)),
            'total_declaradas' => (float) collect($bloques)->sum(fn ($bloque) => (float) ($bloque['declaradas'] ?? 0)),
        ];
    }

    /**
     * @return array{coordinacion_pie: float, educadoras_diferenciales: float, total: float}
     */
    private static function desgloseContratoPieNecesario(array $bloques): array
    {
        $items = collect(data_get($bloques, 'pie.items', []));
        $coordinacionPie = (float) $items
            ->where('tipo_contrato_pie_necesario', 'coordinacion_pie')
            ->sum(fn ($item) => (float) ($item['horas'] ?? 0));
        $educadorasDiferenciales = (float) $items
            ->where('tipo_contrato_pie_necesario', 'educadoras_diferenciales')
            ->sum(fn ($item) => (float) ($item['horas'] ?? 0));

        return [
            'coordinacion_pie' => round($coordinacionPie, 2),
            'educadoras_diferenciales' => round($educadorasDiferenciales, 2),
            'total' => round($coordinacionPie + $educadorasDiferenciales, 2),
        ];
    }

    private static function bloquesSinContratoPieNecesario(array $bloques): array
    {
        $itemsPie = collect(data_get($bloques, 'pie.items', []));
        $horasRemovidas = (float) $itemsPie
            ->whereNotNull('tipo_contrato_pie_necesario')
            ->sum(fn ($item) => (float) ($item['horas'] ?? 0));

        if ($horasRemovidas <= 0 || ! isset($bloques['pie'])) {
            return $bloques;
        }

        $bloques['pie']['automaticas'] = max(0.0, round((float) ($bloques['pie']['automaticas'] ?? 0) - $horasRemovidas, 2));
        $bloques['pie']['total'] = max(0.0, round((float) ($bloques['pie']['total'] ?? 0) - $horasRemovidas, 2));
        $bloques['pie']['educadoras_diferenciales'] = 0.0;
        $bloques['pie']['items'] = $itemsPie
            ->whereNull('tipo_contrato_pie_necesario')
            ->values()
            ->all();

        return $bloques;
    }

    private static function grupoConsolidadoFor(?string $categoria, ?string $codigo = null): string
    {
        if ($codigo === 'coordinador_pie') {
            return 'pie';
        }
        if (in_array($categoria, ['directiva', 'planes_programas', 'otras_funciones_docentes'], true)) {
            return $categoria;
        }
        return 'tecnico_pedagogica';
    }

    private static function declaracionesPorRut(Establecimiento $establecimiento, array $ruts): array
    {
        if (! self::schemaHasTable('declaracion_sostenedores')) {
            return [];
        }

        $normalized = collect($ruts)->map(fn ($rut) => self::normalizeRut($rut))->filter()->unique()->values();
        if ($normalized->isEmpty()) {
            return [];
        }

        $rows = DeclaracionSostenedor::query()
            ->where(function ($query) use ($establecimiento, $normalized) {
                $query->where('rbd', (string) $establecimiento->rbd)
                    ->orWhereIn('rut', $normalized->all());
            })
            ->orderByDesc('id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $rut = self::normalizeRut($row->rut);
            if ($rut === '' || ! $normalized->contains($rut) || isset($out[$rut])) {
                continue;
            }
            $out[$rut] = $row;
        }

        return $out;
    }

    private static function isDocentePersonal(ReemplazoPersonal $row): bool
    {
        $texto = Str::of(($row->estatuto ?? '').' '.($row->escalafon ?? ''))->ascii()->upper()->toString();
        if ($texto === '') {
            return true;
        }
        return str_contains($texto, 'DOCENTE') || str_contains($texto, 'PROFESOR') || str_contains($texto, 'EDUCADOR');
    }

    private static function isAsistentePersonal(ReemplazoPersonal $row): bool
    {
        $texto = Str::of(collect([
            $row->estatuto ?? null,
            $row->escalafon ?? null,
        ])->filter()->implode(' '))->ascii()->upper()->squish()->toString();

        if ($texto === '') {
            return false;
        }

        if (str_contains($texto, 'DOCENTE')
            || str_contains($texto, 'PROFESOR')
            || str_contains($texto, 'EDUCADOR')) {
            return false;
        }

        return str_contains($texto, 'ASISTENTE')
            || str_contains($texto, 'AAEE')
            || str_contains($texto, 'PARADOCENTE')
            || str_contains($texto, 'ASIST EDUC')
            || str_contains($texto, 'CODIGO DEL TRABAJO')
            || str_contains($texto, 'LEY 19464')
            || str_contains($texto, 'LEY 19.464')
            || str_contains($texto, 'AUXILIAR')
            || str_contains($texto, 'ADMINISTRATIVO')
            || str_contains($texto, 'TECNICO')
            || str_contains($texto, 'PROFESIONAL');
    }

    private static function declaracionEsAsistente(?DeclaracionSostenedor $declaracion): bool
    {
        if (! $declaracion) {
            return false;
        }

        $estamento = Str::of((string) $declaracion->estamento)->ascii()->upper()->squish()->toString();

        return $estamento === 'ASISTENTE'
            || str_contains($estamento, 'ASISTENTE DE LA EDUCACION')
            || str_contains($estamento, 'AAEE');
    }

    private static function declaracionEsDocente(?DeclaracionSostenedor $declaracion): bool
    {
        if (! $declaracion) {
            return false;
        }

        $estamento = Str::of((string) $declaracion->estamento)->ascii()->upper()->squish()->toString();

        return $estamento === 'DOCENTE'
            || str_contains($estamento, 'PROFESOR')
            || str_contains($estamento, 'EDUCADOR');
    }

    private static function firstPositive(array $values): ?float
    {
        foreach ($values as $value) {
            if (is_numeric($value) && (float) $value > 0) {
                return (float) $value;
            }
        }
        return null;
    }

    private static function nombreDocente(ReemplazoPersonal $row, ?DeclaracionSostenedor $declaracion): string
    {
        if ($declaracion) {
            $nombre = trim(collect([
                $declaracion->nombres,
                $declaracion->apellido_paterno,
                $declaracion->apellido_materno,
            ])->filter()->implode(' '));
            if ($nombre !== '') {
                return $nombre;
            }
        }
        return (string) ($row->nombre ?: 'Sin nombre');
    }

    private static function nivelesDeclarados(?DeclaracionSostenedor $declaracion): string
    {
        if (! $declaracion) {
            return 'Sin declaración';
        }
        $niveles = [];
        if ((bool) $declaracion->educacion_parvularia) {
            $niveles[] = 'Ed. Parvularia';
        }
        if ((bool) $declaracion->ensenanza_basica) {
            $niveles[] = 'Básica';
        }
        if ((bool) $declaracion->ensenanza_media) {
            $niveles[] = 'Media';
        }
        return $niveles ? implode(' / ', $niveles) : 'Sin nivel declarado';
    }
}
