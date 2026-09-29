<?php

namespace App\Support;

use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionDocenteExclusion;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DotacionSobredotacionCalculator
{
    public const ALLOWED_ROLES = [
        'admin',
        'funcionario_directivo_estab',
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
     * @param  iterable<int, array<string, mixed>>  $necesidadesFunciones
     * @return array{aula: array<string, mixed>, pie: array<string, mixed>, protegidos: Collection, vacantes_por_bloque: array<string, array<string, mixed>>}
     */
    public static function build(iterable $docentes, array $resumen, iterable $necesidadesFunciones = []): array
    {
        $clasificacionFunciones = self::clasificacionFunciones($necesidadesFunciones);
        $base = collect($docentes)
            ->map(fn (array $docente) => self::prepararDocente(
                $docente,
                $clasificacionFunciones,
                (bool) ($resumen['establecimiento_especial'] ?? false)
            ))
            ->values();

        $declaradasObjetivo = self::numero($resumen, 'horas_dotacion_funciones_declaradas');
        $aulaObjetivo = self::numero($resumen, 'horas_contrato_docentes_aula');
        $necesidadAula = round(
            self::numero($resumen, 'contrato_plan_mas_trabajo_colaborativo_pie')
            + self::numero($resumen, 'horas_dotacion_funciones_normativas'),
            2
        );

        $pie = self::itemsPie($base);
        $pieObjetivo = self::numero($resumen, 'horas_contrato_docente_pie');
        $pie = self::conciliarDotacion($pie, $pieObjetivo, 'Horas de contrato docente PIE no asociadas a docente');
        $necesidadPie = self::numero($resumen, 'horas_contrato_pie_necesarias');

        $aula = self::analizarAula($base, $necesidadAula, $aulaObjetivo, [
            'contrato_plan_pie' => (float) ($resumen['contrato_plan_general_mas_trabajo_colaborativo_pie']
                ?? max(0.0, self::numero($resumen, 'contrato_plan_mas_trabajo_colaborativo_pie')
                    - self::numero($resumen, 'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie'))),
            'bloque_normativo' => self::numero($resumen, 'horas_dotacion_funciones_normativas'),
            'contrato_aula' => (float) ($resumen['horas_contrato_docentes_aula_general']
                ?? max(0.0, $aulaObjetivo - self::numero($resumen, 'horas_contrato_docentes_parvularia'))),
            'bloque_declarado' => $declaradasObjetivo,
        ]);

        return [
            'protegidos' => $base->where('contrato_protegido', true)->values(),
            'aula' => $aula,
            'pie' => self::distribuirNecesidad($pie, $necesidadPie, [
                'contrato_pie_necesario' => $necesidadPie,
                'contrato_docente_pie' => $pieObjetivo,
            ]),
            'vacantes_por_bloque' => self::vacantesPorBloque($base, $aula['items']),
        ];
    }

    /** @return array<string, mixed> */
    private static function prepararDocente(array $docente, Collection $clasificacionFunciones, bool $especial = false): array
    {
        $horasContrato = round(max(0.0, (float) ($docente['horas_contrato'] ?? 0)), 2);
        $motivo = (string) data_get($docente, 'exclusion_docente.motivo', '');
        [$planta, $contrata] = self::contratoPorCalidad($docente, $horasContrato);
        $asignaciones = collect($docente['asignaciones'] ?? []);
        $reservas = $asignaciones->where('tipo_asignacion', 'reserva_no_normativa');
        $reservadasPlanta = (float) $reservas->where('subtipo_asignacion', 'titular')->sum(fn ($row) => self::horasAsignacion($row));
        $reservadasContrata = (float) $reservas->where('subtipo_asignacion', 'contrata')->sum(fn ($row) => self::horasAsignacion($row));
        $reservadasSinOrigen = max(0.0, round((float) ($docente['horas_reservadas_no_normativas'] ?? $reservas->sum(fn ($row) => self::horasAsignacion($row))) - $reservadasPlanta - $reservadasContrata, 2));
        $contratoPie = array_key_exists('horas_contrato_pie', $docente)
            ? max(0.0, (float) $docente['horas_contrato_pie'])
            : DotacionAsignacionCalculator::contratoPiePorDocente($docente);
        if ($especial) {
            $contratoPie = 0.0;
        }
        $perfil = DotacionProfesionDocenteResolver::perfilTitulo($docente);
        $esDiferencial = $perfil['es_educacion_diferencial'];
        $contratoParvularia = $perfil['es_educacion_parvulos']
            ? min(max(0.0, $horasContrato - $contratoPie), (float) DotacionEstablecimientoCalculator::contratoParvularia(
                [$docente], max(0.0, $horasContrato - $contratoPie), 0
            )['horas_contrato_docentes_parvularia'])
            : 0.0;
        $asignadasParvularia = $perfil['es_educacion_parvulos']
            ? (float) $asignaciones
                ->filter(fn ($asignacion) => self::esAsignacionParvularia($asignacion))
                ->sum(fn ($asignacion) => self::horasAsignacion($asignacion))
            : 0.0;

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
            : self::horasAsignadasProtegidas($docente, $asignaciones, $clasificacionFunciones);
        $declaradasAjustables = array_key_exists('horas_declaradas_ajustables', $docente)
            ? max(0.0, (float) $docente['horas_declaradas_ajustables'])
            : (float) $asignaciones
                ->filter(fn ($asignacion) => self::esAsignacionDeclarada($asignacion, $clasificacionFunciones))
                ->sum(fn ($asignacion) => self::horasAsignacion($asignacion));
        $declaradasDetalle = array_key_exists('horas_declaradas_detalle', $docente)
            ? array_values((array) $docente['horas_declaradas_detalle'])
            : self::detalleAsignacionesDeclaradas($asignaciones, $clasificacionFunciones);

        return [
            'rut' => (string) ($docente['rut'] ?? ''),
            'nombre' => (string) ($docente['nombre'] ?? 'Docente sin nombre'),
            'funcion' => (string) ($docente['funcion'] ?? 'Sin función declarada'),
            'tipo_contrato' => (string) ($docente['tipo_contrato'] ?? 'Sin tipo contrato'),
            'es_titular' => self::esTitular($docente),
            'contrato_protegido' => in_array($motivo, ['fuero_maternal', 'horas_gremiales'], true),
            'motivo_proteccion' => DotacionDocenteExclusion::MOTIVOS[$motivo] ?? '',
            'contrato_original' => round(max(0.0, (float) ($docente['horas_contrato_base'] ?? $horasContrato)), 2),
            'contrato_considerado' => $horasContrato,
            'aula_planta' => round(max(0.0, $planta - $piePlanta), 2),
            'aula_contrata' => round(max(0.0, $contrata - $pieContrata), 2),
            'pie_planta' => round($piePlanta, 2),
            'pie_contrata' => round($pieContrata, 2),
            'contrato_parvularia' => round($contratoParvularia, 2),
            'asignadas_parvularia' => round($asignadasParvularia, 2),
            'reservadas_planta' => round($reservadasPlanta + $reservadasSinOrigen, 2),
            'reservadas_contrata' => round($reservadasContrata, 2),
            'asignadas_protegidas' => round($asignadasProtegidas, 2),
            'declaradas_ajustables' => round($declaradasAjustables, 2),
            'declaradas_detalle' => $declaradasDetalle,
            'asignadas_pie' => $especial ? 0.0 : (array_key_exists('horas_contrato_pie', $docente)
                ? round($contratoPie, 2)
                : round((float) $asignaciones
                    ->filter(fn ($asignacion) => self::esContratoPie($asignacion)
                        && DotacionAsignacionCalculator::coverageEstamento($asignacion) === 'docente')
                    ->sum(fn ($asignacion) => self::horasAsignacion($asignacion)), 2)),
            'asignadas_pie_registradas' => $especial ? 0.0 : round((float) $asignaciones
                ->filter(fn ($asignacion) => DotacionAsignacionCalculator::coverageEstamento($asignacion) === 'docente'
                    && ($esDiferencial
                        ? ! in_array((string) data_get($asignacion, 'tipo_asignacion'), ['plan_estudio', 'reserva_no_normativa'], true)
                            && ! DotacionAsignacionCalculator::esAsignacionNormativaAula($asignacion, true)
                        : self::esContratoPie($asignacion)))
                ->sum(fn ($asignacion) => self::horasAsignacion($asignacion)), 2),
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
            $protegidasConsideradas = min($contratoAula, $protegidas);
            $protegidasPlanta = min($contratoPlanta, $protegidasConsideradas);
            $protegidasContrata = min($contratoContrata, max(0.0, $protegidasConsideradas - $protegidasPlanta));
            $plantaDisponible = max(0.0, round($contratoPlanta - $protegidasPlanta, 2));
            $contrataDisponible = max(0.0, round($contratoContrata - $protegidasContrata, 2));
            $declaradasConsideradas = min($plantaDisponible + $contrataDisponible, $declaradas);
            $declaradasPlanta = min($plantaDisponible, $declaradasConsideradas);
            $declaradasContrata = min(
                $contrataDisponible,
                max(0.0, round($declaradasConsideradas - $declaradasPlanta, 2))
            );
            $declaradasSinCobertura = max(0.0, round($declaradas - $declaradasConsideradas, 2));
            $asignadasConsideradas = round($protegidasConsideradas + $declaradasConsideradas, 2);
            $asignadasPlanta = round($protegidasPlanta + $declaradasPlanta, 2);
            $asignadasContrata = round($protegidasContrata + $declaradasContrata, 2);
            $saldoPlanta = max(0.0, round($contratoPlanta - $asignadasPlanta, 2));
            $saldoContrata = max(0.0, round($contratoContrata - $asignadasContrata, 2));
            $reservaPlanta = min($saldoPlanta, (float) $docente['reservadas_planta']);
            $reservaContrata = min($saldoContrata, (float) $docente['reservadas_contrata']);
            $reservaPendiente = max(0.0, round(
                (float) $docente['reservadas_planta'] + (float) $docente['reservadas_contrata']
                - $reservaPlanta - $reservaContrata, 2
            ));
            $sinAsignacionPlanta = round(max(0.0, $saldoPlanta - $reservaPlanta - $reservaPendiente), 2);
            $reservaPendiente = max(0.0, round($reservaPendiente - ($saldoPlanta - $reservaPlanta), 2));
            $sinAsignacionContrata = round(max(0.0, $saldoContrata - $reservaContrata - $reservaPendiente), 2);

            // La conversión proporcional NT puede dejar centésimas de contrato
            // aun cuando la Educadora completó su jornada al redondear hacia arriba.
            // El ajuste sólo afecta el saldo informado, nunca las asignaciones guardadas.
            $neteoParvularia = min(
                self::saldoFraccionalParvularia($docente),
                round($sinAsignacionPlanta + $sinAsignacionContrata, 2)
            );
            $neteoPlanta = min($sinAsignacionPlanta, $neteoParvularia);
            $sinAsignacionPlanta = round($sinAsignacionPlanta - $neteoPlanta, 2);
            $sinAsignacionContrata = round(max(0.0, $sinAsignacionContrata - ($neteoParvularia - $neteoPlanta)), 2);

            return [
                'rut' => $docente['rut'],
                'nombre' => $docente['nombre'],
                'funcion' => $docente['funcion'],
                'tipo_contrato' => $docente['tipo_contrato'],
                'es_ajuste' => false,
                'contrato_protegido' => $docente['contrato_protegido'],
                'horas_contrato_categoria' => $contratoAula,
                'horas_dotacion_total' => $contratoAula,
                'horas_asignadas_protegidas' => $protegidas,
                'horas_declaradas_ajustables' => $declaradas,
                'horas_declaradas_titulares' => round($declaradasPlanta, 2),
                'horas_declaradas_contrata' => round($declaradasContrata, 2),
                'horas_declaradas_sin_cobertura' => $declaradasSinCobertura,
                'horas_declaradas_detalle' => $docente['declaradas_detalle'],
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
            ->filter(fn (array $item) => ! $item['contrato_protegido'] && $item['horas_sobredotacion_total'] > 0.01)
            ->sortBy([
                ['horas_sobredotacion_total', 'desc'],
                ['nombre', 'asc'],
            ])
            ->values();
        $ajustes = $analizados
            ->filter(fn (array $item) => ! $item['contrato_protegido'] && $item['horas_declaradas_ajustables'] > 0.01)
            ->sortBy([
                ['horas_declaradas_ajustables', 'desc'],
                ['nombre', 'asc'],
            ])
            ->values();
        $contratoAulaIndividualizado = self::sumar($analizados, 'horas_contrato_categoria');
        // La brecha estructural excluye Parvularia; la nómina factual conserva
        // todas las horas individuales para no ocultar contratos sin asignación.
        $brechaEstructural = round($formula['contrato_plan_pie'] + $formula['bloque_normativo'] - $formula['contrato_aula'], 2);
        $sobredotacionReal = self::sumar($sobredotados, 'horas_sobredotacion_total');
        $declaradasAjustables = self::sumar($ajustes, 'horas_declaradas_ajustables');
        // La protección impide proponer ajustes, pero no borra la cobertura real.
        $declaradasAsignadas = self::sumar($analizados, 'horas_declaradas_ajustables');
        $asignadasTotal = self::sumar($analizados, 'horas_asignadas_total');
        $declaradasRequeridas = round(max(0.0, (float) ($formula['bloque_declarado'] ?? 0)), 2);
        $sobredotacionEstructural = max(0.0, round(-$brechaEstructural, 2));
        $universoRevision = round($sobredotacionReal + $declaradasAjustables, 2);

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
                'horas_sobredotacion_estructural' => $sobredotacionEstructural,
                'horas_necesarias_estructurales' => max(0.0, $brechaEstructural),
                'horas_asignadas_protegidas' => self::sumar($analizados, 'horas_asignadas_protegidas'),
                'horas_declaradas_ajustables' => $declaradasAjustables,
                'horas_declaradas_asignadas' => $declaradasAsignadas,
                'horas_declaradas_protegidas' => round($declaradasAsignadas - $declaradasAjustables, 2),
                'horas_declaradas_requeridas' => $declaradasRequeridas,
                'horas_declaradas_pendientes' => max(0.0, round($declaradasRequeridas - $declaradasAsignadas, 2)),
                'horas_declaradas_excedentes' => max(0.0, round($declaradasAsignadas - $declaradasRequeridas, 2)),
                'horas_declaradas_titulares' => self::sumar($ajustes, 'horas_declaradas_titulares'),
                'horas_declaradas_contrata' => self::sumar($ajustes, 'horas_declaradas_contrata'),
                'horas_declaradas_sin_cobertura' => self::sumar($ajustes, 'horas_declaradas_sin_cobertura'),
                'horas_asignadas_total' => $asignadasTotal,
                'horas_brecha_cobertura' => round($horasNecesarias - $asignadasTotal, 2),
                'horas_diferencia_indicadores' => round($sobredotacionReal - $sobredotacionEstructural, 2),
                'horas_sobreasignadas' => self::sumar($analizados, 'horas_sobreasignadas'),
                'horas_sobredotacion_total' => $sobredotacionReal,
                'horas_sobredotacion_protegida' => self::sumar($analizados->where('contrato_protegido', true), 'horas_sobredotacion_total'),
                'horas_sobredotacion_planta' => self::sumar($sobredotados, 'horas_sobredotacion_planta'),
                'horas_sobredotacion_contrata' => self::sumar($sobredotados, 'horas_sobredotacion_contrata'),
                'horas_universo_revision' => $universoRevision,
                'horas_potencial_ajuste' => $universoRevision,
                'tiene_ajuste_no_asociado' => abs($contratoAulaIndividualizado - $contratoAulaResumen) > 0.01,
            ],
            'formula' => $formula,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function vacantesPorBloque(Collection $base, Collection $vacantesAula): array
    {
        $grupos = [
            'plan_estudio' => collect(),
            'parvularia' => collect(),
            'pie' => collect(),
        ];
        $aulaPorRut = $vacantesAula->keyBy('rut');

        foreach ($base as $docente) {
            if ($docente['contrato_protegido']) {
                continue;
            }

            $aula = $aulaPorRut->get($docente['rut']);
            if ($aula) {
                $contratoAula = (float) $aula['horas_contrato_categoria'];
                $contratoParvularia = (float) $docente['contrato_parvularia'];
                $asignadasParvularia = (float) $docente['asignadas_parvularia'];
                $vacanteParvularia = round(min(
                    (float) $aula['horas_sobredotacion_total'],
                    max(0.0, $contratoParvularia - $asignadasParvularia)
                ), 2);
                $vacanteParvularia = round(min($contratoParvularia, max(
                    $vacanteParvularia,
                    (float) $aula['horas_sobredotacion_total'] - ($contratoAula - $contratoParvularia)
                )), 2);
                $vacanteParvularia = round(max(
                    0.0, $vacanteParvularia - self::saldoFraccionalParvularia($docente)
                ), 2);
                $vacantePlan = round((float) $aula['horas_sobredotacion_total'] - $vacanteParvularia, 2);
                $plantaParvularia = min($vacanteParvularia, (float) $aula['horas_sobredotacion_planta']);

                foreach ([
                    'plan_estudio' => [$contratoAula - $contratoParvularia, $vacantePlan,
                        (float) $aula['horas_sobredotacion_planta'] - $plantaParvularia],
                    'parvularia' => [$contratoParvularia, $vacanteParvularia, $plantaParvularia],
                ] as $bloque => [$contrato, $vacante, $planta]) {
                    if ($vacante <= 0.01) {
                        continue;
                    }
                    $grupos[$bloque]->push(self::filaVacante($docente, $contrato, $vacante, $planta));
                }
            }

            $contratoPie = round((float) $docente['pie_planta'] + (float) $docente['pie_contrata'], 2);
            $vacantePie = round(max(0.0, $contratoPie - (float) $docente['asignadas_pie_registradas']), 2);
            if ($vacantePie > 0.01) {
                $plantaPie = min($vacantePie, max(0.0, (float) $docente['pie_planta'] - (float) $docente['asignadas_pie_registradas']));
                $grupos['pie']->push(self::filaVacante($docente, $contratoPie, $vacantePie, $plantaPie));
            }
        }

        return collect($grupos)->map(function (Collection $items): array {
            $items = $items->sortBy([
                ['horas_sobredotacion_total', 'desc'],
                ['nombre', 'asc'],
            ])->values();

            return [
                'items' => $items,
                'horas_total' => self::sumar($items, 'horas_sobredotacion_total'),
                'horas_planta' => self::sumar($items, 'horas_sobredotacion_planta'),
                'horas_contrata' => self::sumar($items, 'horas_sobredotacion_contrata'),
            ];
        })->all();
    }

    /** Fracción que completa la jornada NT sin constituir una hora contractual vacante. */
    private static function saldoFraccionalParvularia(array $docente): float
    {
        $contrato = round((float) ($docente['contrato_parvularia'] ?? 0), 2);
        $asignadas = round((float) ($docente['asignadas_parvularia'] ?? 0), 2);
        $saldo = round($contrato - $asignadas, 2);

        if ($contrato <= 0 || $asignadas <= 0 || $saldo <= 0.01 || $saldo >= 1) {
            return 0.0;
        }

        return ceil($asignadas) >= $contrato ? $saldo : 0.0;
    }

    private static function esAsignacionParvularia(object|array $asignacion): bool
    {
        $tipo = (string) data_get($asignacion, 'tipo_asignacion', '');
        if ($tipo === 'acompanamiento_parvularia') {
            return true;
        }
        if (! in_array($tipo, ['plan_estudio', 'pie_colaborativo'], true)) {
            return false;
        }
        $curso = data_get($asignacion, 'establecimientoCurso');
        if ($curso instanceof \App\Models\EstablecimientoCurso) {
            return DotacionProfesionDocenteResolver::esCursoNt($curso);
        }

        $proporcion = Str::of((string) data_get($asignacion, 'proporcion_aplicada', ''))
            ->ascii()->upper()->trim()->toString();

        return str_starts_with($proporcion, 'NT ') || str_contains($proporcion, 'PARVULARIA');
    }

    /** @return array<string, mixed> */
    private static function filaVacante(array $docente, float $contrato, float $vacante, float $planta): array
    {
        return [
            'rut' => $docente['rut'],
            'nombre' => $docente['nombre'],
            'funcion' => $docente['funcion'],
            'tipo_contrato' => $docente['tipo_contrato'],
            'horas_contrato_categoria' => round($contrato, 2),
            'horas_sobredotacion_total' => round($vacante, 2),
            'horas_sobredotacion_planta' => round($planta, 2),
            'horas_sobredotacion_contrata' => round($vacante - $planta, 2),
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
            'contrato_protegido' => $docente['contrato_protegido'],
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
                'contrato_protegido' => false,
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

        // Reserva primero el aporte necesario de los contratos protegidos.
        // No genera cobertura ficticia: nunca distribuye más que la necesidad PIE.
        foreach ([
            ['protegido' => true, 'capacidad' => 'horas_dotacion_planta', 'cubierta' => 'horas_necesidad_cubierta_planta'],
            ['protegido' => true, 'capacidad' => 'horas_dotacion_contrata', 'cubierta' => 'horas_necesidad_cubierta_contrata'],
            ['protegido' => false, 'capacidad' => 'horas_dotacion_planta', 'cubierta' => 'horas_necesidad_cubierta_planta'],
            ['protegido' => false, 'capacidad' => 'horas_dotacion_contrata', 'cubierta' => 'horas_necesidad_cubierta_contrata'],
        ] as $calidad) {
            $orden = $items->where('contrato_protegido', $calidad['protegido'])->keys()->sort(function (int $a, int $b) use ($items) {
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
            ->filter(fn (array $item) => ! $item['contrato_protegido'] && $item['horas_sobredotacion_total'] > 0.01)
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
                'horas_sobredotacion_estructural' => max(0.0, round($disponibles - $horasNecesarias, 2)),
                'horas_sobredotacion_protegida' => self::sumar($analizados->where('contrato_protegido', true), 'horas_sobredotacion_total'),
                'horas_sobredotacion_planta' => self::sumar($sobredotados, 'horas_sobredotacion_planta'),
                'horas_sobredotacion_contrata' => self::sumar($sobredotados, 'horas_sobredotacion_contrata'),
                'tiene_ajuste_no_asociado' => $analizados->contains(fn (array $item) => (bool) $item['es_ajuste']),
            ],
            'formula' => $formula,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function detalleAsignacionesDeclaradas(
        Collection $asignaciones,
        Collection $clasificacionFunciones
    ): array
    {
        return $asignaciones
            ->filter(fn ($asignacion) => self::esAsignacionDeclarada($asignacion, $clasificacionFunciones)
                && self::horasAsignacion($asignacion) > 0.01)
            ->map(function ($asignacion) {
                $tipo = (string) data_get($asignacion, 'tipo_asignacion', 'otra_funcion');
                $tipoLabel = DotacionDocenteAsignacion::TIPOS[$tipo]
                    ?? Str::headline($tipo);
                $nombre = trim((string) data_get($asignacion, 'asignatura_nombre', ''));
                $subtipo = trim((string) data_get($asignacion, 'subtipo_asignacion', ''));
                $subvencion = trim((string) data_get($asignacion, 'subvencion', ''));

                return [
                    'tipo' => $tipo,
                    'tipo_label' => $tipoLabel,
                    'nombre' => $nombre !== '' ? $nombre : $tipoLabel,
                    'subtipo' => $subtipo,
                    'subtipo_label' => $subtipo !== '' ? Str::headline($subtipo) : '',
                    'subvencion' => $subvencion,
                    'horas' => self::horasAsignacion($asignacion),
                ];
            })
            ->groupBy(fn (array $item) => implode('|', [
                $item['tipo'],
                $item['subtipo'],
                Str::of($item['nombre'])->ascii()->lower()->trim()->toString(),
                Str::of($item['subvencion'])->ascii()->lower()->trim()->toString(),
            ]))
            ->map(function (Collection $items) {
                $detalle = $items->first();
                $detalle['horas'] = round((float) $items->sum('horas'), 2);

                return $detalle;
            })
            ->sortBy([
                ['tipo_label', 'asc'],
                ['nombre', 'asc'],
            ], SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private static function horasAsignadasProtegidas(
        array $docente,
        Collection $asignaciones,
        Collection $clasificacionFunciones
    ): float
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
                && ! self::esAsignacionDeclarada($asignacion, $clasificacionFunciones))
            ->sum(fn ($asignacion) => self::horasAsignacion($asignacion));

        return round($contratoPlan + $trabajoColaborativo + $funcionesNormativas, 2);
    }

    private static function esAsignacionDeclarada(
        object|array $asignacion,
        Collection $clasificacionFunciones
    ): bool
    {
        if (! self::esFuncionGeneral($asignacion) || self::esContratoPie($asignacion)) {
            return false;
        }

        $necesidadKey = trim((string) data_get($asignacion, 'necesidad_key', ''));
        if ($necesidadKey !== '' && $clasificacionFunciones->has($necesidadKey)) {
            return (bool) $clasificacionFunciones->get($necesidadKey);
        }

        return (int) data_get($asignacion, 'dotacion_funcion_id', 0) > 0;
    }

    /** @return Collection<string, bool> */
    private static function clasificacionFunciones(iterable $necesidadesFunciones): Collection
    {
        return collect($necesidadesFunciones)
            ->filter(fn ($necesidad) => trim((string) data_get($necesidad, 'key', '')) !== '')
            ->mapWithKeys(fn ($necesidad) => [
                (string) data_get($necesidad, 'key') => (int) data_get($necesidad, 'dotacion_funcion_id', 0) > 0,
            ]);
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
