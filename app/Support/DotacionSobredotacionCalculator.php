<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DotacionSobredotacionCalculator
{
    public const ALLOWED_ROLES = [
        'admin',
        'coordinador_gdp',
        'supervisor_plani',
        'coordinador_uatp',
    ];

    public const TIPOS = ['aula', 'pie'];

    public static function canView(?string $role): bool
    {
        return in_array($role, self::ALLOWED_ROLES, true);
    }

    /**
     * Separa el saldo factual de Aula por docente de la brecha estructural del
     * establecimiento. Para PIE distribuye la necesidad institucional entre
     * las horas disponibles, conservando primero Planta y luego Contrata.
     *
     * @param  iterable<int, array<string, mixed>>  $docentes
     * @param  array<string, mixed>  $resumen
     * @return array{aula: array<string, mixed>, pie: array<string, mixed>}
     */
    public static function build(iterable $docentes, array $resumen): array
    {
        $base = collect($docentes)
            ->map(fn (array $docente) => self::prepararDocente($docente))
            ->values();

        $declaradasObjetivo = self::numero($resumen, 'horas_dotacion_funciones_declaradas');
        $aulaObjetivo = self::numero($resumen, 'horas_contrato_docentes_aula');
        $necesidadAula = round(
            self::numero($resumen, 'contrato_plan_mas_trabajo_colaborativo_pie')
            + self::numero($resumen, 'horas_dotacion_funciones_normativas')
            + $declaradasObjetivo,
            2
        );

        $pie = self::itemsPie($base);
        $pieObjetivo = self::numero($resumen, 'horas_contrato_docente_pie');
        $pie = self::conciliarDotacion($pie, $pieObjetivo, 'Horas de contrato docente PIE no asociadas a docente');
        $necesidadPie = self::numero($resumen, 'horas_contrato_pie_necesarias');

        return [
            'aula' => self::analizarAula($base, $necesidadAula, $aulaObjetivo, [
                'contrato_plan_pie' => self::numero($resumen, 'contrato_plan_mas_trabajo_colaborativo_pie'),
                'bloque_normativo' => self::numero($resumen, 'horas_dotacion_funciones_normativas'),
                'contrato_aula' => self::numero($resumen, 'horas_contrato_docentes_aula'),
                'bloque_declarado' => $declaradasObjetivo,
            ]),
            'pie' => self::distribuirNecesidad($pie, $necesidadPie, [
                'contrato_pie_necesario' => $necesidadPie,
                'contrato_docente_pie' => $pieObjetivo,
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private static function prepararDocente(array $docente): array
    {
        $horasContrato = round(max(0.0, (float) ($docente['horas_contrato'] ?? 0)), 2);
        [$planta, $contrata] = self::contratoPorCalidad($docente, $horasContrato);
        $asignaciones = collect($docente['asignaciones'] ?? []);
        $contratoPie = array_key_exists('horas_contrato_pie', $docente)
            ? max(0.0, (float) $docente['horas_contrato_pie'])
            : (float) $asignaciones
                ->filter(fn ($asignacion) => self::esContratoPie($asignacion))
                ->sum(fn ($asignacion) => self::horasAsignacion($asignacion));

        // La porción PIE se reserva primero desde Contrata para mantener la
        // mayor cantidad posible de horas titulares en la dotación de Aula.
        $pieContrata = min($contrata, $contratoPie);
        $piePlanta = min($planta, max(0.0, $contratoPie - $pieContrata));
        $pieSinClasificar = max(0.0, round($contratoPie - $piePlanta - $pieContrata, 2));
        if ($pieSinClasificar > 0.0) {
            if (self::esTitular($docente)) {
                $piePlanta += $pieSinClasificar;
            } else {
                $pieContrata += $pieSinClasificar;
            }
        }

        $asignadasProtegidas = array_key_exists('horas_asignadas_protegidas', $docente)
            ? max(0.0, (float) $docente['horas_asignadas_protegidas'])
            : self::horasAsignadasProtegidas($docente, $asignaciones);
        $declaradasAjustables = array_key_exists('horas_declaradas_ajustables', $docente)
            ? max(0.0, (float) $docente['horas_declaradas_ajustables'])
            : (float) $asignaciones
                ->filter(fn ($asignacion) => self::esAsignacionDeclarada($asignacion))
                ->sum(fn ($asignacion) => self::horasAsignacion($asignacion));

        return [
            'rut' => (string) ($docente['rut'] ?? ''),
            'nombre' => (string) ($docente['nombre'] ?? 'Docente sin nombre'),
            'funcion' => (string) ($docente['funcion'] ?? 'Sin función declarada'),
            'tipo_contrato' => (string) ($docente['tipo_contrato'] ?? 'Sin tipo contrato'),
            'es_titular' => self::esTitular($docente),
            'aula_planta' => round(max(0.0, $planta - $piePlanta), 2),
            'aula_contrata' => round(max(0.0, $contrata - $pieContrata), 2),
            'pie_planta' => round($piePlanta, 2),
            'pie_contrata' => round($pieContrata, 2),
            'asignadas_protegidas' => round($asignadasProtegidas, 2),
            'declaradas_ajustables' => round($declaradasAjustables, 2),
            'asignadas_pie' => round($contratoPie, 2),
        ];
    }

    /** @return array<string, mixed> */
    private static function analizarAula(
        Collection $base,
        float $horasNecesarias,
        float $contratoAulaResumen,
        array $formula
    ): array
    {
        $analizados = $base->map(function (array $docente) {
            $contratoPlanta = (float) $docente['aula_planta'];
            $contratoContrata = (float) $docente['aula_contrata'];
            $contratoAula = round($contratoPlanta + $contratoContrata, 2);
            $protegidas = round(max(0.0, (float) $docente['asignadas_protegidas']), 2);
            $declaradas = round(max(0.0, (float) $docente['declaradas_ajustables']), 2);
            $asignadas = round($protegidas + $declaradas, 2);
            $asignadasConsideradas = min($contratoAula, $asignadas);
            $asignadasPlanta = min($contratoPlanta, $asignadasConsideradas);
            $asignadasContrata = min(
                $contratoContrata,
                max(0.0, round($asignadasConsideradas - $asignadasPlanta, 2))
            );
            $sinAsignacionPlanta = round(max(0.0, $contratoPlanta - $asignadasPlanta), 2);
            $sinAsignacionContrata = round(max(0.0, $contratoContrata - $asignadasContrata), 2);

            return [
                'rut' => $docente['rut'],
                'nombre' => $docente['nombre'],
                'funcion' => $docente['funcion'],
                'tipo_contrato' => $docente['tipo_contrato'],
                'es_ajuste' => false,
                'horas_contrato_categoria' => $contratoAula,
                'horas_dotacion_total' => $contratoAula,
                'horas_asignadas_protegidas' => $protegidas,
                'horas_declaradas_ajustables' => $declaradas,
                'horas_asignadas_total' => $asignadas,
                'horas_asignadas_consideradas' => round($asignadasConsideradas, 2),
                'horas_sobreasignadas' => round(max(0.0, $asignadas - $contratoAula), 2),
                'horas_sobredotacion_total' => round($sinAsignacionPlanta + $sinAsignacionContrata, 2),
                'horas_sobredotacion_planta' => $sinAsignacionPlanta,
                'horas_sobredotacion_contrata' => $sinAsignacionContrata,
            ];
        })->filter(fn (array $item) => $item['horas_contrato_categoria'] > 0.01
            || $item['horas_asignadas_total'] > 0.01)
            ->values();

        $sobredotados = $analizados
            ->filter(fn (array $item) => $item['horas_sobredotacion_total'] > 0.01)
            ->sortBy([
                ['horas_sobredotacion_total', 'desc'],
                ['nombre', 'asc'],
            ])
            ->values();
        $ajustes = $analizados
            ->filter(fn (array $item) => $item['horas_declaradas_ajustables'] > 0.01)
            ->sortBy([
                ['horas_declaradas_ajustables', 'desc'],
                ['nombre', 'asc'],
            ])
            ->values();
        $contratoAulaIndividualizado = self::sumar($analizados, 'horas_contrato_categoria');
        $brechaEstructural = round($horasNecesarias - $contratoAulaResumen, 2);
        $sobredotacionReal = self::sumar($sobredotados, 'horas_sobredotacion_total');
        $declaradasAjustables = self::sumar($ajustes, 'horas_declaradas_ajustables');

        return [
            'items' => $sobredotados,
            'ajustes' => $ajustes,
            'resumen' => [
                'docentes_analizados' => $analizados->count(),
                'docentes_sobredotacion' => $sobredotados->count(),
                'docentes_ajuste' => $ajustes->count(),
                'horas_dotacion_total' => $contratoAulaIndividualizado,
                'horas_dotacion_resumen' => round($contratoAulaResumen, 2),
                'horas_necesarias_total' => round($horasNecesarias, 2),
                'brecha_estructural' => $brechaEstructural,
                'horas_sobredotacion_estructural' => max(0.0, round(-$brechaEstructural, 2)),
                'horas_necesarias_estructurales' => max(0.0, $brechaEstructural),
                'horas_asignadas_protegidas' => self::sumar($analizados, 'horas_asignadas_protegidas'),
                'horas_declaradas_ajustables' => $declaradasAjustables,
                'horas_asignadas_total' => self::sumar($analizados, 'horas_asignadas_total'),
                'horas_sobreasignadas' => self::sumar($analizados, 'horas_sobreasignadas'),
                'horas_sobredotacion_total' => $sobredotacionReal,
                'horas_sobredotacion_planta' => self::sumar($sobredotados, 'horas_sobredotacion_planta'),
                'horas_sobredotacion_contrata' => self::sumar($sobredotados, 'horas_sobredotacion_contrata'),
                'horas_potencial_ajuste' => round($sobredotacionReal + $declaradasAjustables, 2),
                'tiene_ajuste_no_asociado' => abs($contratoAulaIndividualizado - $contratoAulaResumen) > 0.01,
            ],
            'formula' => $formula,
        ];
    }

    private static function itemsPie(Collection $base): Collection
    {
        return $base->map(fn (array $docente) => self::itemBase($docente, [
            'horas_contrato_categoria' => round($docente['pie_planta'] + $docente['pie_contrata'], 2),
            'horas_dotacion_planta' => $docente['pie_planta'],
            'horas_dotacion_contrata' => $docente['pie_contrata'],
            'horas_asignadas_relevantes' => $docente['asignadas_pie'],
        ]))->values();
    }

    /** @return array<string, mixed> */
    private static function itemBase(array $docente, array $horas): array
    {
        return array_merge([
            'rut' => $docente['rut'],
            'nombre' => $docente['nombre'],
            'funcion' => $docente['funcion'],
            'tipo_contrato' => $docente['tipo_contrato'],
            'es_ajuste' => false,
        ], $horas, [
            'horas_dotacion_total' => round(
                (float) $horas['horas_dotacion_planta'] + (float) $horas['horas_dotacion_contrata'],
                2
            ),
        ]);
    }

    private static function conciliarDotacion(Collection $items, float $objetivo, string $nombreAjuste): Collection
    {
        $actual = self::sumar($items, 'horas_dotacion_total');
        $diferencia = round($objetivo - $actual, 2);

        if ($diferencia > 0.01) {
            $items->push([
                'rut' => '—',
                'nombre' => $nombreAjuste,
                'funcion' => 'Revisar asignación individual',
                'tipo_contrato' => 'Sin clasificación individual',
                'es_ajuste' => true,
                'horas_contrato_categoria' => $diferencia,
                'horas_dotacion_planta' => 0.0,
                'horas_dotacion_contrata' => $diferencia,
                'horas_dotacion_total' => $diferencia,
                'horas_asignadas_relevantes' => 0.0,
            ]);
        } elseif ($diferencia < -0.01) {
            $porReducir = abs($diferencia);
            foreach (['horas_dotacion_contrata', 'horas_dotacion_planta'] as $calidad) {
                foreach ($items->keys()->reverse() as $index) {
                    if ($porReducir <= 0.01) {
                        break 2;
                    }
                    $item = $items[$index];
                    $reduccion = min((float) $item[$calidad], $porReducir);
                    $item[$calidad] = round((float) $item[$calidad] - $reduccion, 2);
                    $reduccionContrato = min((float) $item['horas_contrato_categoria'], $reduccion);
                    $item['horas_contrato_categoria'] = round(
                        (float) $item['horas_contrato_categoria'] - $reduccionContrato,
                        2
                    );
                    $item['horas_dotacion_total'] = round(
                        (float) $item['horas_dotacion_planta'] + (float) $item['horas_dotacion_contrata'],
                        2
                    );
                    $items[$index] = $item;
                    $porReducir = round($porReducir - $reduccion, 2);
                }
            }
        }

        return $items->filter(fn (array $item) => $item['horas_dotacion_total'] > 0.01)->values();
    }

    /** @return array<string, mixed> */
    private static function distribuirNecesidad(Collection $items, float $horasNecesarias, array $formula): array
    {
        $items = $items->map(fn (array $item) => array_merge($item, [
            'horas_necesidad_cubierta_planta' => 0.0,
            'horas_necesidad_cubierta_contrata' => 0.0,
        ]));
        $disponibles = self::sumar($items, 'horas_dotacion_total');
        $porCubrir = min($disponibles, max(0.0, round($horasNecesarias, 2)));

        foreach ([
            ['capacidad' => 'horas_dotacion_planta', 'cubierta' => 'horas_necesidad_cubierta_planta'],
            ['capacidad' => 'horas_dotacion_contrata', 'cubierta' => 'horas_necesidad_cubierta_contrata'],
        ] as $calidad) {
            $orden = $items->keys()->sort(function (int $a, int $b) use ($items) {
                $asignadas = (float) $items[$b]['horas_asignadas_relevantes'] <=> (float) $items[$a]['horas_asignadas_relevantes'];
                if ($asignadas !== 0) {
                    return $asignadas;
                }

                return strcmp((string) $items[$a]['nombre'], (string) $items[$b]['nombre']);
            });

            foreach ($orden as $index) {
                if ($porCubrir <= 0.01) {
                    break 2;
                }
                $item = $items[$index];
                $cubierta = min((float) $item[$calidad['capacidad']], $porCubrir);
                $item[$calidad['cubierta']] = round($cubierta, 2);
                $items[$index] = $item;
                $porCubrir = round($porCubrir - $cubierta, 2);
            }
        }

        $analizados = $items->map(function (array $item) {
            $item['horas_necesidad_cubierta'] = round(
                $item['horas_necesidad_cubierta_planta'] + $item['horas_necesidad_cubierta_contrata'],
                2
            );
            $item['horas_sobredotacion_planta'] = round(
                max(0.0, $item['horas_dotacion_planta'] - $item['horas_necesidad_cubierta_planta']),
                2
            );
            $item['horas_sobredotacion_contrata'] = round(
                max(0.0, $item['horas_dotacion_contrata'] - $item['horas_necesidad_cubierta_contrata']),
                2
            );
            $item['horas_sobredotacion_total'] = round(
                $item['horas_sobredotacion_planta'] + $item['horas_sobredotacion_contrata'],
                2
            );

            return $item;
        });
        $sobredotados = $analizados
            ->filter(fn (array $item) => $item['horas_sobredotacion_total'] > 0.01)
            ->sortBy([
                ['horas_sobredotacion_total', 'desc'],
                ['nombre', 'asc'],
            ])
            ->values();

        return [
            'items' => $sobredotados,
            'resumen' => [
                'docentes_analizados' => $analizados->where('es_ajuste', false)->count(),
                'docentes_sobredotacion' => $sobredotados->where('es_ajuste', false)->count(),
                'horas_dotacion_total' => $disponibles,
                'horas_necesarias_total' => round($horasNecesarias, 2),
                'horas_asignadas_registradas' => self::sumar($analizados, 'horas_asignadas_relevantes'),
                'horas_necesidad_cubierta' => self::sumar($analizados, 'horas_necesidad_cubierta'),
                'horas_necesarias_pendientes' => max(0.0, round($horasNecesarias - $disponibles, 2)),
                'horas_sobredotacion_total' => self::sumar($sobredotados, 'horas_sobredotacion_total'),
                'horas_sobredotacion_planta' => self::sumar($sobredotados, 'horas_sobredotacion_planta'),
                'horas_sobredotacion_contrata' => self::sumar($sobredotados, 'horas_sobredotacion_contrata'),
                'tiene_ajuste_no_asociado' => $analizados->contains(fn (array $item) => (bool) $item['es_ajuste']),
            ],
            'formula' => $formula,
        ];
    }

    private static function horasAsignadasProtegidas(array $docente, Collection $asignaciones): float
    {
        $camposContratoPlan = [
            'horas_contrato_65_35',
            'horas_contrato_60_40',
            'horas_contrato_especial',
        ];
        $tieneContratoPlanConsolidado = collect($camposContratoPlan)
            ->contains(fn (string $campo) => array_key_exists($campo, $docente));
        $contratoPlan = $tieneContratoPlanConsolidado
            ? (float) collect($camposContratoPlan)->sum(fn (string $campo) => (float) ($docente[$campo] ?? 0))
            : (float) $asignaciones
                ->where('tipo_asignacion', 'plan_estudio')
                ->sum(fn ($asignacion) => self::horasAsignacion($asignacion));
        $trabajoColaborativo = (float) $asignaciones
            ->where('tipo_asignacion', 'pie_colaborativo')
            ->sum(fn ($asignacion) => self::horasAsignacion($asignacion));
        $funcionesNormativas = (float) $asignaciones
            ->filter(fn ($asignacion) => self::esFuncionGeneral($asignacion)
                && ! self::esContratoPie($asignacion)
                && (int) data_get($asignacion, 'dotacion_funcion_id', 0) === 0)
            ->sum(fn ($asignacion) => self::horasAsignacion($asignacion));

        return round($contratoPlan + $trabajoColaborativo + $funcionesNormativas, 2);
    }

    private static function esAsignacionDeclarada(object|array $asignacion): bool
    {
        return self::esFuncionGeneral($asignacion)
            && ! self::esContratoPie($asignacion)
            && (int) data_get($asignacion, 'dotacion_funcion_id', 0) > 0;
    }

    private static function esFuncionGeneral(object|array $asignacion): bool
    {
        return in_array((string) data_get($asignacion, 'tipo_asignacion', ''), [
            'funcion_directiva',
            'funcion_tecnico_pedagogica',
            'plan_normativo',
            'otra_funcion',
        ], true);
    }

    private static function esContratoPie(object|array $asignacion): bool
    {
        $tipo = (string) data_get($asignacion, 'tipo_asignacion', '');
        if ($tipo === 'pie_educadora_diferencial') {
            return true;
        }
        if ($tipo !== 'funcion_tecnico_pedagogica') {
            return false;
        }

        $subtipo = Str::of((string) data_get($asignacion, 'subtipo_asignacion', ''))
            ->ascii()->lower()->trim()->toString();
        if ($subtipo === 'pie') {
            return true;
        }

        $nombre = Str::of((string) data_get($asignacion, 'asignatura_nombre', ''))
            ->ascii()->upper()->toString();

        return str_contains($nombre, 'PIE') && str_contains($nombre, 'COORDIN');
    }

    private static function horasAsignacion(object|array $asignacion): float
    {
        return max(0.0, (float) data_get($asignacion, 'horas_contrato', 0));
    }

    /** @return array{0: float, 1: float} */
    private static function contratoPorCalidad(array $docente, float $horasContrato): array
    {
        $horasPlanta = min($horasContrato, max(0.0, (float) ($docente['horas_planta'] ?? 0)));
        $horasContrata = min(
            max(0.0, $horasContrato - $horasPlanta),
            max(0.0, (float) ($docente['horas_contrata'] ?? 0))
        );
        $sinClasificar = max(0.0, round($horasContrato - $horasPlanta - $horasContrata, 2));

        if ($sinClasificar > 0.0) {
            if (self::esTitular($docente)) {
                $horasPlanta += $sinClasificar;
            } else {
                $horasContrata += $sinClasificar;
            }
        }

        return [round($horasPlanta, 2), round($horasContrata, 2)];
    }

    private static function esTitular(array $docente): bool
    {
        if ((bool) ($docente['es_titular'] ?? false)) {
            return true;
        }

        $tipoContrato = Str::of((string) ($docente['tipo_contrato'] ?? ''))
            ->ascii()->upper()->toString();

        return str_contains($tipoContrato, 'PLANTA') || str_contains($tipoContrato, 'TITULAR');
    }

    private static function numero(array $resumen, string $key): float
    {
        return round(max(0.0, (float) ($resumen[$key] ?? 0)), 2);
    }

    private static function sumar(Collection $items, string $key): float
    {
        return round((float) $items->sum(fn (array $item) => (float) ($item[$key] ?? 0)), 2);
    }
}
