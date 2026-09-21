<?php

namespace App\Support;

use App\Models\DotacionDocenteAsignacion;
use Illuminate\Support\Collection;

/** Proyección de solo lectura sobre la configuración del año base. */
class DotacionProyeccionCalculator
{
    public static function canView(?string $activeRole): bool
    {
        return in_array($activeRole, ['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'], true);
    }

    public static function build(array $base, int $anio, array $continuidadPorRut, array $conservacionHorasPorRut = []): array
    {
        $noContinuan = collect($continuidadPorRut)->filter(fn ($continua) => ! $continua)->keys()->flip()->all();
        $continuaAsignacion = fn ($row) => DotacionAsignacionCalculator::coverageEstamento($row) !== 'docente'
            || ! isset($noContinuan[self::rut($row)]);
        $docentes = collect($base['docentes'] ?? []);
        $docentesProyectados = $docentes->reject(fn ($docente) => isset($noContinuan[self::rut($docente)]))->values();
        $asignaciones = collect(data_get($base, 'asignacion.asignaciones', []));
        $asignacionesProyectadas = $asignaciones->filter($continuaAsignacion)->values();
        $resumen = $base['resumen'] ?? [];
        $especial = (bool) ($resumen['establecimiento_especial'] ?? false);
        $contratosCubiertos = self::contratos($docentesProyectados, $asignacionesProyectadas, $especial);
        $contratosBase = self::contratos($docentes, $asignaciones, $especial);

        // La salida de personas no altera la configuración curricular ni normativa.
        $necesarias = [
            'plan_general' => (float) ($resumen['contrato_plan_general_mas_trabajo_colaborativo_pie'] ?? 0),
            'parvularia' => (float) ($resumen['contrato_educacion_parvularia_mas_trabajo_colaborativo_pie'] ?? 0),
            'funciones_normativas' => (float) ($resumen['horas_dotacion_funciones_normativas'] ?? 0),
            'otras_funciones' => (float) ($resumen['horas_dotacion_funciones_declaradas'] ?? 0),
            'pie' => (float) ($resumen['horas_contrato_pie_necesarias'] ?? 0),
        ];
        $reservas = self::reservasNecesarias($base, $noContinuan, $conservacionHorasPorRut, $especial);
        // El contrato proyectado conserva las plazas necesarias aunque estén vacantes.
        // Las horas no necesarias ya están descontadas en horas_contrato.
        $contratosVacantes = ['total' => $reservas['vacantes'], 'aula' => 0.0, 'parvularia' => 0.0, 'pie' => 0.0];
        foreach ($reservas['docentes'] as $reserva) {
            foreach ($reserva['contratos_por_categoria'] as $categoria => $horas) {
                $contratosVacantes[$categoria] = round($contratosVacantes[$categoria] + $horas, 2);
            }
        }
        $contratos = [];
        foreach ($contratosCubiertos as $categoria => $horas) {
            $contratos[$categoria] = round($horas + $contratosVacantes[$categoria], 2);
        }

        $coberturas = collect(data_get($base, 'asignacion.necesidades', []))
            ->map(fn ($items, $grupo) => collect($items)->map(function (array $item) use ($grupo, $continuaAsignacion): array {
                $asignadas = collect($item['asignaciones'] ?? []);
                $proyectadas = $asignadas->filter($continuaAsignacion);
                $esPlan = $grupo === 'plan_estudio' && ($item['horas_plan_requeridas'] ?? null) !== null;
                $campo = $esPlan ? 'horas_plan_pedagogicas' : 'horas_contrato';
                $requeridas = (float) ($esPlan ? $item['horas_plan_requeridas'] : ($item['horas_contrato_requeridas'] ?? 0));
                $horasBase = round((float) $asignadas->sum(fn ($row) => (float) data_get($row, $campo, 0)), 2);
                $horasProyectadas = round((float) $proyectadas->sum(fn ($row) => (float) data_get($row, $campo, 0)), 2);

                return [
                    'titulo' => $item['titulo'] ?? $item['asignatura_nombre'] ?? 'Necesidad',
                    'curso' => $item['curso_label'] ?? 'Establecimiento',
                    'origen' => $item['fuente'] ?? '',
                    'unidad' => $esPlan ? 'h pedagógicas' : 'h contrato',
                    'requeridas' => $requeridas,
                    'asignadas_base' => $horasBase,
                    'asignadas_proyectadas' => $horasProyectadas,
                    'cobertura_retirada' => round($horasBase - $horasProyectadas, 2),
                    'pendientes' => max(0.0, round($requeridas - $horasProyectadas, 2)),
                    'excedidas' => max(0.0, round($horasProyectadas - $requeridas, 2)),
                    'personas' => $proyectadas->map(fn ($row) => (string) data_get($row, 'docente_nombre', 'Sin nombre'))->unique()->values()->all(),
                ];
            })->values()->all())->all();

        return [
            'anio_base' => $anio,
            'anio_proyeccion' => $anio + 1,
            'contratos_base' => $contratosBase,
            'contratos' => $contratos,
            'contratos_cubiertos' => $contratosCubiertos,
            'contratos_vacantes' => $contratosVacantes,
            'necesarias' => $necesarias,
            'necesarias_adicionales' => $reservas['adicionales'],
            'horas_vacantes_por_cubrir' => $reservas['vacantes'],
            'reservas' => $reservas['docentes'],
            'brechas' => [
                'aula' => round($necesarias['plan_general'] + $necesarias['funciones_normativas'] + $reservas['adicionales']['aula'] - $contratos['aula'], 2),
                'parvularia' => round($necesarias['parvularia'] + $reservas['adicionales']['parvularia'] - $contratos['parvularia'], 2),
                'pie' => round($necesarias['pie'] + $reservas['adicionales']['pie'] - $contratos['pie'], 2),
            ],
            'docentes_base' => $docentes->count(),
            'docentes_continuan' => $docentesProyectados->count(),
            'docentes_no_continuan' => $docentes->count() - $docentesProyectados->count(),
            'docentes' => $docentes->map(fn (array $docente) => [
                'rut' => $docente['rut'] ?? '',
                'nombre' => $docente['nombre'] ?? 'Docente',
                'funcion' => $docente['funcion'] ?? '',
                'motivo' => data_get($docente, 'exclusion_docente.motivo_label', ''),
                'continua' => ! isset($noContinuan[self::rut($docente)]),
                'conservar_horas_necesarias' => $conservacionHorasPorRut[self::rut($docente)] ?? true,
                'contrato_base' => (float) ($docente['horas_contrato_base'] ?? $docente['horas_contrato'] ?? 0),
                'contrato_considerado' => (float) ($docente['horas_contrato'] ?? 0),
                'contrato_proyectado' => isset($noContinuan[self::rut($docente)]) ? 0.0 : (float) ($docente['horas_contrato'] ?? 0),
            ])->values()->all(),
            'coberturas' => $coberturas,
        ];
    }

    /** Conserva las horas de quienes salen sin duplicar necesidades ya configuradas. */
    private static function reservasNecesarias(array $base, array $noContinuan, array $conservarPorRut, bool $especial): array
    {
        $necesidades = collect(data_get($base, 'asignacion.necesidades', []))->flatMap(fn ($grupo) => collect($grupo)->values())->values();
        $saldos = $necesidades->map(fn ($item) => max(0.0, (float) ($item['horas_contrato_requeridas'] ?? 0)))->all();
        $adicionales = ['aula' => 0.0, 'parvularia' => 0.0, 'pie' => 0.0];
        $reservas = [];
        $vacantes = 0.0;
        $asignacionesPorRut = collect(data_get($base, 'asignacion.asignaciones', []))
            ->filter(fn ($row) => DotacionAsignacionCalculator::coverageEstamento($row) === 'docente')
            ->groupBy(fn ($row) => self::rut($row));
        $contextos = [];
        foreach ($necesidades as $necesidad) {
            foreach ($necesidad['asignaciones'] ?? [] as $asignacion) {
                // Usa los vínculos ya resueltos, incluidos cursos combinados y funciones históricas.
                $contextos[self::asignacionKey($asignacion)] ??= [
                    'titulo' => $necesidad['titulo'] ?? $necesidad['asignatura_nombre'] ?? '',
                    'curso' => $necesidad['curso_label'] ?? '',
                    'fuente' => $necesidad['fuente'] ?? '',
                ];
            }
        }

        foreach ($base['docentes'] ?? [] as $docente) {
            $rut = self::rut($docente);
            if (! isset($noContinuan[$rut]) || ! ($conservarPorRut[$rut] ?? true)) {
                continue;
            }

            $horas = round(max(0.0, (float) ($docente['horas_contrato'] ?? 0)), 2);
            $asignacionesDocente = collect($asignacionesPorRut->get($rut, $docente['asignaciones'] ?? []));
            $referencia = self::detalleAsignaciones($asignacionesDocente, $contextos, $rut);
            $contrato = self::contratos(collect([$docente]), $asignacionesDocente, $especial);
            $porCategoria = array_intersect_key($contrato, $adicionales);
            $porIncorporarCategoria = $porCategoria;
            $categorias = array_keys(array_filter($porCategoria, fn ($cantidad) => $cantidad > 0));
            $categoriaPrincipal = $categorias[0] ?? 'aula';
            $esDiferencial = DotacionProfesionDocenteResolver::perfilTitulo($docente)['es_educacion_diferencial'];
            $categoriaAsignacion = function ($row) use ($categorias, $categoriaPrincipal, $esDiferencial, $contrato): string {
                if (count($categorias) <= 1) {
                    return $categoriaPrincipal;
                }
                if ($esDiferencial) {
                    return DotacionAsignacionCalculator::esAsignacionPie($row) ? 'pie' : 'aula';
                }

                return DotacionAsignacionCalculator::esAsignacionCoordinacionPie($row)
                    ? 'pie' : ($contrato['parvularia'] > 0 ? 'parvularia' : 'aula');
            };
            $asignadasPorCategoria = $asignacionesDocente->groupBy($categoriaAsignacion)
                ->map(fn (Collection $filas) => self::detalleAsignaciones($filas, $contextos, $rut)['total_contrato']);
            // La asignación identifica la necesidad, pero su cantidad no limita
            // las horas necesarias definidas en Situación docente.
            foreach ($necesidades as $index => $necesidad) {
                $vinculadas = collect($necesidad['asignaciones'] ?? [])->filter(fn ($row) =>
                    DotacionAsignacionCalculator::coverageEstamento($row) === 'docente' && self::rut($row) === $rut
                );
                if ($vinculadas->isEmpty()) {
                    continue;
                }
                // Un contrato diferencial mixto conserva cada parte en su bloque:
                // la necesidad normativa no absorbe también las horas PIE.
                $categoria = $categoriaPrincipal;
                if (count($categorias) > 1) {
                    if ($esDiferencial) {
                        $categoria = $vinculadas->contains(fn ($row) => DotacionAsignacionCalculator::esAsignacionPie($row)) ? 'pie' : 'aula';
                    } else {
                        $categoria = $vinculadas->contains(fn ($row) => DotacionAsignacionCalculator::esAsignacionCoordinacionPie($row))
                            ? 'pie' : ($contrato['parvularia'] > 0 ? 'parvularia' : 'aula');
                    }
                }
                $existentes = min($porIncorporarCategoria[$categoria], $saldos[$index]);
                $porIncorporarCategoria[$categoria] = round($porIncorporarCategoria[$categoria] - $existentes, 2);
                $saldos[$index] = round($saldos[$index] - $existentes, 2);
            }

            foreach ($porIncorporarCategoria as $categoria => $cantidad) {
                // El total asignado usa la misma conversión por bloques que el detalle.
                // Tampoco se duplican necesidades configuradas mayores que lo asignado.
                $sinAsignacion = max(0.0, round($porCategoria[$categoria] - (float) $asignadasPorCategoria->get($categoria, 0), 2));
                $cantidad = min($cantidad, $sinAsignacion);
                $porIncorporarCategoria[$categoria] = $cantidad;
                $adicionales[$categoria] = round($adicionales[$categoria] + $cantidad, 2);
            }
            $porIncorporar = round(array_sum($porIncorporarCategoria), 2);
            $vacantes = round($vacantes + $horas, 2);
            $reservas[] = [
                'rut' => $docente['rut'] ?? '', 'nombre' => $docente['nombre'] ?? 'Docente',
                'categoria' => $categoriaPrincipal, 'contratos_por_categoria' => $porCategoria, 'horas_necesarias' => $horas,
                'ya_contempladas' => min($horas, $referencia['total_contrato']), 'adicionales' => $porIncorporar,
                'asignaciones_referencia' => $referencia,
            ];
        }

        return ['adicionales' => $adicionales, 'vacantes' => $vacantes, 'docentes' => $reservas];
    }

    /** Detalle informativo del año base; no limita ni modifica las horas conservadas. */
    private static function detalleAsignaciones(Collection $asignaciones, array $contextos, string $rut): array
    {
        $items = $asignaciones
            ->filter(fn ($row) => self::rut($row) === $rut
                && DotacionAsignacionCalculator::coverageEstamento($row) === 'docente'
                && (data_get($row, 'estado') ?? 'activa') === 'activa')
            ->unique(fn ($row) => self::asignacionKey($row))
            ->map(function ($row) use ($contextos): array {
                $contexto = $contextos[self::asignacionKey($row)] ?? null;
                $tipo = (string) data_get($row, 'tipo_asignacion', '');
                $esCurso = in_array($tipo, ['plan_estudio', 'pie_colaborativo'], true)
                    || data_get($row, 'establecimiento_curso_id') || data_get($row, 'dotacion_curso_combinado_id');

                return [
                    'es_plan' => $tipo === 'plan_estudio',
                    'tipo' => DotacionDocenteAsignacion::TIPOS[$tipo] ?? 'Asignación',
                    'titulo' => ($contexto['titulo'] ?? '') ?: (data_get($row, 'asignatura_nombre') ?: 'Sin nombre registrado'),
                    'curso' => ($contexto['curso'] ?? '') ?: ($esCurso ? 'Curso sin identificar' : 'Establecimiento'),
                    'fuente' => $contexto['fuente'] ?? '',
                    'subvencion' => data_get($row, 'subvencion') ?: 'Sin clasificar',
                    'horas_contrato' => round((float) data_get($row, 'horas_contrato', 0), 2),
                    'horas_pedagogicas' => $tipo === 'plan_estudio' && data_get($row, 'horas_plan_pedagogicas') !== null
                        ? round((float) data_get($row, 'horas_plan_pedagogicas'), 2) : null,
                    'proporcion' => $tipo === 'plan_estudio' ? (data_get($row, 'proporcion_aplicada') ?: '') : '',
                    'observacion' => data_get($row, 'observacion') ?: '',
                    'sin_necesidad_vigente' => $contexto === null,
                ];
            })->values();

        $bloques = $items->where('es_plan', true)->groupBy(function (array $item): string {
            if ($item['horas_pedagogicas'] === null || trim($item['proporcion']) === '') {
                return 'sin_conversion';
            }

            return DotacionAsignacionCalculator::proportionGroup($item['proporcion']);
        })->map(function (Collection $filas, string $grupo): array {
            $pedagogicas = round((float) $filas->sum('horas_pedagogicas'), 2);
            $convertido = in_array($grupo, ['65_35', '60_40'], true);
            $contrato = $convertido
                ? DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula($grupo, $pedagogicas)['horas_contrato']
                : $filas->sum('horas_contrato');

            return [
                'label' => match ($grupo) {
                    '65_35' => '65/35', '60_40' => '60/40',
                    'especial' => 'Reglas especiales', default => 'Sin datos de conversión',
                },
                'convertido' => $convertido, 'items' => $filas->values()->all(),
                'horas_pedagogicas' => $pedagogicas, 'horas_contrato' => round((float) $contrato, 2),
            ];
        })->sortKeys()->values();
        $directos = $items->where('es_plan', false)->values();

        return [
            'items' => $items->all(),
            'bloques_aula' => $bloques->all(),
            'contratos_directos' => $directos->all(),
            'total_contrato' => round((float) $bloques->sum('horas_contrato') + (float) $directos->sum('horas_contrato'), 2),
            'total_pedagogicas' => round((float) $items->sum('horas_pedagogicas'), 2),
        ];
    }

    private static function asignacionKey(object|array $row): string
    {
        $id = data_get($row, 'id');

        return $id ? 'id:'.$id : (is_object($row) ? 'obj:'.spl_object_id($row) : 'array:'.sha1(serialize($row)));
    }

    private static function contratos(Collection $docentes, Collection $asignaciones, bool $especial): array
    {
        $total = round((float) $docentes->sum(fn ($docente) => (float) ($docente['horas_contrato'] ?? 0)), 2);
        $pie = DotacionAsignacionCalculator::resumenContratoDocentePie($asignaciones, $docentes, $especial)['total'];
        $parvularia = DotacionEstablecimientoCalculator::contratoParvularia($docentes, max(0.0, $total - $pie), 0);

        return [
            'total' => $total,
            'aula' => $parvularia['horas_contrato_docentes_aula_general'],
            'parvularia' => $parvularia['horas_contrato_docentes_parvularia'],
            'pie' => $pie,
        ];
    }

    private static function rut(object|array $row): string
    {
        return DotacionEstablecimientoCalculator::normalizeRut(
            data_get($row, 'rut_normalizado') ?: data_get($row, 'rut')
                ?: data_get($row, 'docente_rut_normalizado') ?: data_get($row, 'docente_rut', '')
        );
    }
}
