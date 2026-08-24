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
     * Distribuye las necesidades institucionales entre las horas disponibles,
     * conservando primero Planta y luego Contrata. Así, el detalle por docente
     * concilia con las mismas fórmulas utilizadas en el resumen superior.
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
        $aula = self::itemsAula($base, $declaradasObjetivo);
        $aulaObjetivo = round(
            self::numero($resumen, 'horas_contrato_docentes_aula') + $declaradasObjetivo,
            2
        );
        $aula = self::conciliarDotacion($aula, $aulaObjetivo, 'Horas de dotación general no asociadas a docente');
        $necesidadAula = round(
            self::numero($resumen, 'contrato_plan_mas_trabajo_colaborativo_pie')
            + self::numero($resumen, 'horas_dotacion_funciones_normativas'),
            2
        );

        $pie = self::itemsPie($base);
        $pieObjetivo = self::numero($resumen, 'horas_contrato_docente_pie');
        $pie = self::conciliarDotacion($pie, $pieObjetivo, 'Horas de contrato docente PIE no asociadas a docente');
        $necesidadPie = self::numero($resumen, 'horas_contrato_pie_necesarias');

        return [
            'aula' => self::distribuirNecesidad($aula, $necesidadAula, [
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

        $declaradasAsignadas = array_key_exists('horas_bloque_declarado', $docente)
            ? max(0.0, (float) $docente['horas_bloque_declarado'])
            : (float) $asignaciones
                ->filter(fn ($asignacion) => (int) data_get($asignacion, 'dotacion_funcion_id', 0) > 0)
                ->sum(fn ($asignacion) => self::horasAsignacion($asignacion));
        $asignadasGeneral = array_key_exists('horas_asignadas_general', $docente)
            ? max(0.0, (float) $docente['horas_asignadas_general'])
            : (float) $asignaciones
                ->filter(fn ($asignacion) => self::esAsignacionGeneral($asignacion))
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
            'declaradas_asignadas' => round($declaradasAsignadas, 2),
            'asignadas_general' => round($asignadasGeneral, 2),
            'asignadas_pie' => round($contratoPie, 2),
        ];
    }

    private static function itemsAula(Collection $base, float $declaradasObjetivo): Collection
    {
        $declaradasIndividualizadas = round(
            (float) $base->sum(fn (array $docente) => (float) $docente['declaradas_asignadas']),
            2
        );
        $distribucionDeclaradas = self::distribuirProporcionalmente(
            $base->map(fn (array $docente) => (float) $docente['declaradas_asignadas']),
            min($declaradasObjetivo, $declaradasIndividualizadas)
        );

        $items = $base->map(function (array $docente, int $index) use ($distribucionDeclaradas) {
            $declaradas = (float) ($distribucionDeclaradas[$index] ?? 0);
            $declaradasPlanta = $docente['es_titular'] ? $declaradas : 0.0;
            $declaradasContrata = $docente['es_titular'] ? 0.0 : $declaradas;

            return self::itemBase($docente, [
                'horas_contrato_categoria' => round($docente['aula_planta'] + $docente['aula_contrata'], 2),
                'horas_bloque_declarado' => round($declaradas, 2),
                'horas_dotacion_planta' => round($docente['aula_planta'] + $declaradasPlanta, 2),
                'horas_dotacion_contrata' => round($docente['aula_contrata'] + $declaradasContrata, 2),
                'horas_asignadas_relevantes' => $docente['asignadas_general'],
            ]);
        })->values();

        $declaradasNoAsociadas = round(
            max(0.0, $declaradasObjetivo - self::sumar($items, 'horas_bloque_declarado')),
            2
        );
        if ($declaradasNoAsociadas > 0.01) {
            $items->push([
                'rut' => '—',
                'nombre' => 'Horas declaradas no asociadas a docente',
                'funcion' => 'Revisar asignación individual',
                'tipo_contrato' => 'Sin clasificación individual',
                'es_ajuste' => true,
                'horas_contrato_categoria' => 0.0,
                'horas_bloque_declarado' => $declaradasNoAsociadas,
                'horas_dotacion_planta' => 0.0,
                'horas_dotacion_contrata' => $declaradasNoAsociadas,
                'horas_dotacion_total' => $declaradasNoAsociadas,
                'horas_asignadas_relevantes' => 0.0,
            ]);
        }

        return $items;
    }

    private static function itemsPie(Collection $base): Collection
    {
        return $base->map(fn (array $docente) => self::itemBase($docente, [
            'horas_contrato_categoria' => round($docente['pie_planta'] + $docente['pie_contrata'], 2),
            'horas_bloque_declarado' => 0.0,
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
                'horas_bloque_declarado' => 0.0,
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
                    $reduccionDeclarada = round($reduccion - $reduccionContrato, 2);
                    $item['horas_bloque_declarado'] = round(
                        max(0.0, (float) $item['horas_bloque_declarado'] - $reduccionDeclarada),
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

    private static function esAsignacionGeneral(object|array $asignacion): bool
    {
        if (self::esContratoPie($asignacion)) {
            return false;
        }

        $tipo = (string) data_get($asignacion, 'tipo_asignacion', '');
        if (in_array($tipo, ['plan_estudio', 'pie_colaborativo'], true)) {
            return true;
        }

        return in_array($tipo, ['funcion_directiva', 'funcion_tecnico_pedagogica', 'plan_normativo', 'otra_funcion'], true)
            && (int) data_get($asignacion, 'dotacion_funcion_id', 0) === 0;
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

    /** @return Collection<int, float> */
    private static function distribuirProporcionalmente(Collection $pesos, float $objetivo): Collection
    {
        $resultado = $pesos->map(fn () => 0.0);
        $totalPesos = round((float) $pesos->sum(), 2);
        if ($objetivo <= 0.0 || $totalPesos <= 0.0) {
            return $resultado;
        }

        $restante = round($objetivo, 2);
        $indices = $pesos->filter(fn ($peso) => (float) $peso > 0.0)->keys()->values();
        foreach ($indices as $posicion => $index) {
            $esUltimo = $posicion === $indices->count() - 1;
            $horas = $esUltimo
                ? $restante
                : round($objetivo * ((float) $pesos[$index] / $totalPesos), 2);
            $resultado[$index] = $horas;
            $restante = round($restante - $horas, 2);
        }

        return $resultado;
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
