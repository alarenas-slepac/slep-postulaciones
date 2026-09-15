<?php

namespace App\Support;

use Illuminate\Support\Collection;

/** Proyección de solo lectura sobre la configuración del año base. */
class DotacionProyeccionCalculator
{
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
        $contratos = self::contratos($docentesProyectados, $asignacionesProyectadas, $especial);
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

        foreach ($base['docentes'] ?? [] as $docente) {
            $rut = self::rut($docente);
            if (! isset($noContinuan[$rut]) || ! ($conservarPorRut[$rut] ?? true)) {
                continue;
            }

            $horas = round(max(0.0, (float) ($docente['horas_contrato'] ?? 0)), 2);
            $porIncorporar = $horas;
            // La asignación identifica la necesidad, pero su cantidad no limita
            // las horas necesarias definidas en Situación docente.
            foreach ($necesidades as $index => $necesidad) {
                $vinculada = collect($necesidad['asignaciones'] ?? [])->contains(fn ($row) =>
                    DotacionAsignacionCalculator::coverageEstamento($row) === 'docente' && self::rut($row) === $rut
                );
                if (! $vinculada) {
                    continue;
                }
                $existentes = min($porIncorporar, $saldos[$index]);
                $porIncorporar = round($porIncorporar - $existentes, 2);
                $saldos[$index] = round($saldos[$index] - $existentes, 2);
            }

            $contrato = self::contratos(collect([$docente]), collect($docente['asignaciones'] ?? []), $especial);
            $categoria = $contrato['pie'] > 0 ? 'pie' : ($contrato['parvularia'] > 0 ? 'parvularia' : 'aula');
            $adicionales[$categoria] = round($adicionales[$categoria] + $porIncorporar, 2);
            $vacantes = round($vacantes + $horas, 2);
            $reservas[] = [
                'rut' => $docente['rut'] ?? '', 'nombre' => $docente['nombre'] ?? 'Docente',
                'categoria' => $categoria, 'horas_necesarias' => $horas,
                'ya_contempladas' => round($horas - $porIncorporar, 2), 'adicionales' => $porIncorporar,
            ];
        }

        return ['adicionales' => $adicionales, 'vacantes' => $vacantes, 'docentes' => $reservas];
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
