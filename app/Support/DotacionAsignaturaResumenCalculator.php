<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DotacionAsignaturaResumenCalculator
{
    /**
     * Consolida el plan y sus asignaciones por asignatura para un establecimiento.
     *
     * @param  iterable<int, mixed>  $necesidadesPlan
     * @param  iterable<int, mixed>  $docentes
     */
    public static function build(iterable $necesidadesPlan, iterable $docentes): array
    {
        $docentesPorRut = collect($docentes)
            ->mapWithKeys(function ($docente) {
                $rut = DotacionEstablecimientoCalculator::normalizeRut((string) data_get($docente, 'rut_normalizado', data_get($docente, 'rut', '')));

                return $rut !== '' ? [$rut => $docente] : [];
            });

        $grupos = [];

        foreach (collect($necesidadesPlan) as $necesidad) {
            $nombre = trim((string) (
                data_get($necesidad, 'plan_comun_asociado')
                ?: data_get($necesidad, 'asignatura_oficial')
                ?: data_get($necesidad, 'asignatura_nombre')
                ?: data_get($necesidad, 'titulo', 'Asignatura')
            ));
            $asignaturaId = (int) data_get($necesidad, 'asignatura_id', 0);
            // El nombre canonico permite consolidar horas oficiales y horas de libre
            // disposicion asociadas a la misma asignatura, aunque solo una de las
            // fuentes disponga de asignatura_id.
            $clave = 'nombre:'.self::normalizarAsignatura($nombre);

            if (! isset($grupos[$clave])) {
                $grupos[$clave] = self::filaBase($clave, $asignaturaId > 0 ? $asignaturaId : null, $nombre);
            }

            $grupo =& $grupos[$clave];
            if (($grupo['asignatura_id'] ?? null) === null && $asignaturaId > 0) {
                $grupo['asignatura_id'] = $asignaturaId;
            }
            $proporcion = self::grupoProporcion((string) data_get($necesidad, 'proporcion', ''));
            $origenProporcion = trim((string) data_get($necesidad, 'origen_proporcion_label', 'Regla general'));
            $horasAulaPlan = max(0.0, (float) data_get($necesidad, 'horas_plan_requeridas', 0));
            $horasContratoRequeridas = max(0.0, (float) data_get($necesidad, 'horas_contrato_requeridas', 0));
            $asignaciones = collect(data_get($necesidad, 'asignaciones', []));

            $grupo['horas_aula_plan'] += $horasAulaPlan;
            $grupo['horas_contrato_requeridas_'.$proporcion] += $horasContratoRequeridas;
            $grupo['proporciones'][$proporcion] = true;
            $grupo['origenes_proporcion'][$origenProporcion !== '' ? $origenProporcion : 'Regla general'] = true;

            $detalleAsignaciones = [];
            foreach ($asignaciones as $asignacion) {
                $rut = DotacionEstablecimientoCalculator::normalizeRut((string) data_get($asignacion, 'docente_rut_normalizado', data_get($asignacion, 'docente_rut', '')));
                $docente = $docentesPorRut->get($rut);
                $tipoContrato = trim((string) data_get($docente, 'tipo_contrato', ''));
                $estamentoCobertura = DotacionAsignacionCalculator::coverageEstamento($asignacion);
                $esAsistente = $estamentoCobertura === 'asistente';
                $titularidad = $esAsistente
                    ? ['key' => 'no_aplica', 'label' => 'Asistente de la educación', 'es_titular' => false]
                    : self::titularidad($tipoContrato);
                $proporcionAsignacion = self::grupoProporcion((string) (data_get($asignacion, 'proporcion_aplicada') ?: data_get($necesidad, 'proporcion', '')));
                $horasAula = max(0.0, (float) data_get($asignacion, 'horas_plan_pedagogicas', 0));
                $horasContratoRegistradas = max(0.0, (float) data_get($asignacion, 'horas_contrato', 0));
                $bucketKey = $estamentoCobertura.'|'.($rut !== '' ? $rut : 'sin-rut:'.md5((string) data_get($asignacion, 'docente_nombre', '')))
                    .'|'.$proporcionAsignacion;

                if (! isset($grupo['buckets_asignacion'][$bucketKey])) {
                    $grupo['buckets_asignacion'][$bucketKey] = [
                        'rut' => $rut,
                        'docente' => trim((string) data_get($asignacion, 'docente_nombre', data_get($docente, 'nombre', 'Docente'))),
                        'tipo_contrato' => $tipoContrato,
                        'titularidad' => $titularidad,
                        'estamento_cobertura' => $estamentoCobertura,
                        'es_asistente' => $esAsistente,
                        'proporcion' => $proporcionAsignacion,
                        'horas_aula' => 0.0,
                        'horas_contrato_especial' => 0.0,
                        'horas_contrato_asistente' => 0.0,
                    ];
                }

                $grupo['buckets_asignacion'][$bucketKey]['horas_aula'] += $horasAula;
                if ($esAsistente) {
                    $grupo['buckets_asignacion'][$bucketKey]['horas_contrato_asistente'] += $horasContratoRegistradas;
                } elseif ($proporcionAsignacion === 'especial') {
                    $grupo['buckets_asignacion'][$bucketKey]['horas_contrato_especial'] += $horasContratoRegistradas;
                }

                $detalleAsignaciones[] = [
                    'rut' => (string) data_get($asignacion, 'docente_rut', $rut),
                    'docente' => trim((string) data_get($asignacion, 'docente_nombre', data_get($docente, 'nombre', 'Docente'))),
                    'tipo_contrato' => $tipoContrato !== '' ? $tipoContrato : 'Sin tipo de contrato',
                    'estamento_cobertura' => $estamentoCobertura,
                    'estamento_cobertura_label' => $esAsistente ? 'Asistente de la educación' : 'Docente',
                    'titularidad' => $titularidad,
                    'horas_aula' => round($horasAula, 2),
                    'horas_contrato_registradas' => round($horasContratoRegistradas, 2),
                    'proporcion' => self::etiquetaProporcion($proporcionAsignacion),
                    'subvencion' => (string) data_get($asignacion, 'subvencion', data_get($necesidad, 'subvencion', 'General')),
                    'observacion' => (string) data_get($asignacion, 'observacion', ''),
                ];
            }

            $grupo['detalle'][] = [
                'curso' => (string) data_get($necesidad, 'curso_label', 'Sin curso'),
                'bloque' => (string) (data_get($necesidad, 'bloque') ?: data_get($necesidad, 'fuente', 'Plan de estudios')),
                'proporcion' => self::etiquetaProporcion($proporcion),
                'origen_proporcion' => $origenProporcion !== '' ? $origenProporcion : 'Regla general',
                'horas_aula_plan' => round($horasAulaPlan, 2),
                'horas_aula_asignadas' => round((float) $asignaciones->sum(fn ($item) => (float) data_get($item, 'horas_plan_pedagogicas', 0)), 2),
                'horas_contrato_requeridas' => round($horasContratoRequeridas, 2),
                'subvencion' => (string) data_get($necesidad, 'subvencion', 'General'),
                'asignaciones' => $detalleAsignaciones,
            ];

            unset($grupo);
        }

        $items = collect($grupos)
            ->map(function (array $grupo) {
                foreach ($grupo['buckets_asignacion'] as $bucket) {
                    $proporcion = (string) $bucket['proporcion'];
                    $horasAula = round((float) $bucket['horas_aula'], 2);
                    $esAsistente = (bool) ($bucket['es_asistente'] ?? false);
                    $horasContrato = $esAsistente
                        ? round((float) ($bucket['horas_contrato_asistente'] ?? 0), 2)
                        : ($proporcion === 'especial'
                            ? round((float) $bucket['horas_contrato_especial'], 2)
                            : self::contratoDesdeAula($proporcion, $horasAula));

                    $grupo['horas_aula_asignadas'] += $horasAula;
                    if ($esAsistente) {
                        $grupo['horas_aula_asistentes'] += $horasAula;
                        $grupo['horas_contrato_asistentes'] += $horasContrato;
                        $grupo['horas_contrato_asignadas_asistentes'] += $horasContrato;
                    } else {
                        $grupo['horas_contrato_asignadas_'.$proporcion] += $horasContrato;
                    }

                    if (! $esAsistente && (bool) data_get($bucket, 'titularidad.es_titular', false)) {
                        $grupo['horas_aula_titulares'] += $horasAula;
                        $grupo['horas_contrato_titulares'] += $horasContrato;
                    }
                }

                $grupo['horas_aula_plan'] = round($grupo['horas_aula_plan'], 2);
                $grupo['horas_aula_asignadas'] = round($grupo['horas_aula_asignadas'], 2);
                $grupo['horas_aula_titulares'] = round($grupo['horas_aula_titulares'], 2);
                $grupo['horas_aula_asistentes'] = round($grupo['horas_aula_asistentes'], 2);
                $grupo['horas_aula_no_titulares'] = round(max(0.0, $grupo['horas_aula_asignadas'] - $grupo['horas_aula_titulares'] - $grupo['horas_aula_asistentes']), 2);

                $grupo['horas_contrato_requeridas_total'] = round(
                    $grupo['horas_contrato_requeridas_65_35']
                    + $grupo['horas_contrato_requeridas_60_40']
                    + $grupo['horas_contrato_requeridas_especial'],
                    2
                );
                $grupo['horas_contrato_asignadas_total'] = round(
                    $grupo['horas_contrato_asignadas_65_35']
                    + $grupo['horas_contrato_asignadas_60_40']
                    + $grupo['horas_contrato_asignadas_especial']
                    + $grupo['horas_contrato_asignadas_asistentes'],
                    2
                );
                $grupo['horas_contrato_titulares'] = round($grupo['horas_contrato_titulares'], 2);
                $grupo['horas_contrato_asistentes'] = round($grupo['horas_contrato_asistentes'], 2);
                $grupo['horas_contrato_no_titulares'] = round(max(0.0, $grupo['horas_contrato_asignadas_total'] - $grupo['horas_contrato_titulares'] - $grupo['horas_contrato_asistentes']), 2);
                $grupo['saldo_aula'] = round($grupo['horas_aula_plan'] - $grupo['horas_aula_asignadas'], 2);
                $grupo['saldo_contrato'] = round($grupo['horas_contrato_requeridas_total'] - $grupo['horas_contrato_asignadas_total'], 2);
                $grupo['porcentaje_cobertura'] = self::porcentaje($grupo['horas_aula_asignadas'], $grupo['horas_aula_plan']);
                $grupo['porcentaje_cobertura_titular'] = self::porcentaje($grupo['horas_aula_titulares'], $grupo['horas_aula_asignadas']);
                $grupo['estado'] = self::estado($grupo['horas_aula_plan'], $grupo['horas_aula_asignadas']);
                $grupo['proporciones'] = collect(array_keys(array_filter($grupo['proporciones'])))
                    ->map(fn ($proporcion) => self::etiquetaProporcion((string) $proporcion))
                    ->values()
                    ->all();
                $grupo['origenes_proporcion'] = collect(array_keys(array_filter($grupo['origenes_proporcion'] ?? [])))
                    ->filter()
                    ->values()
                    ->all();

                unset($grupo['buckets_asignacion']);

                return $grupo;
            })
            ->sortBy(fn ($item) => Str::of((string) $item['asignatura'])->ascii()->lower()->toString())
            ->values();

        return [
            'items' => $items,
            'resumen' => self::resumen($items),
            'opciones' => [
                'asignaturas' => $items->pluck('asignatura')->filter()->unique()->sort()->values(),
                'proporciones' => $items->flatMap(fn ($item) => $item['proporciones'] ?? [])->filter()->unique()->sort()->values(),
                'estados' => $items->pluck('estado')->filter()->unique('key')->values(),
            ],
        ];
    }

    public static function filtrar(iterable $items, array $filtros): Collection
    {
        $query = self::normalizarAsignatura((string) ($filtros['q'] ?? ''));
        $proporcion = trim((string) ($filtros['proporcion'] ?? ''));
        $estado = trim((string) ($filtros['estado'] ?? ''));
        $titulares = trim((string) ($filtros['titulares'] ?? ''));

        return collect($items)
            ->filter(function ($item) use ($query, $proporcion, $estado, $titulares) {
                if ($query !== '' && ! str_contains(self::normalizarAsignatura((string) data_get($item, 'asignatura', '')), $query)) {
                    return false;
                }

                if ($proporcion !== '' && ! in_array($proporcion, (array) data_get($item, 'proporciones', []), true)) {
                    return false;
                }

                if ($estado !== '' && (string) data_get($item, 'estado.key', '') !== $estado) {
                    return false;
                }

                $aulaTitular = (float) data_get($item, 'horas_aula_titulares', 0);
                $aulaNoTitular = (float) data_get($item, 'horas_aula_no_titulares', 0);
                if ($titulares === 'con' && $aulaTitular <= 0.01) {
                    return false;
                }
                if ($titulares === 'sin' && $aulaTitular > 0.01) {
                    return false;
                }
                if ($titulares === 'mixta' && ! ($aulaTitular > 0.01 && $aulaNoTitular > 0.01)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    public static function resumen(iterable $items): array
    {
        $items = collect($items);
        $aulaPlan = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_aula_plan', 0));
        $aulaAsignadas = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_aula_asignadas', 0));
        $aulaTitulares = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_aula_titulares', 0));
        $aulaAsistentes = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_aula_asistentes', 0));
        $contratoRequerido = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_contrato_requeridas_total', 0));
        $contratoAsignado = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_contrato_asignadas_total', 0));
        $contratoTitulares = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_contrato_titulares', 0));
        $contratoAsistentes = (float) $items->sum(fn ($item) => (float) data_get($item, 'horas_contrato_asistentes', 0));

        return [
            'asignaturas_total' => $items->count(),
            'horas_aula_plan' => round($aulaPlan, 2),
            'horas_aula_asignadas' => round($aulaAsignadas, 2),
            'horas_aula_titulares' => round($aulaTitulares, 2),
            'horas_aula_asistentes' => round($aulaAsistentes, 2),
            'horas_aula_no_titulares' => round(max(0.0, $aulaAsignadas - $aulaTitulares - $aulaAsistentes), 2),
            'horas_contrato_requeridas' => round($contratoRequerido, 2),
            'horas_contrato_asignadas' => round($contratoAsignado, 2),
            'horas_contrato_titulares' => round($contratoTitulares, 2),
            'horas_contrato_asistentes' => round($contratoAsistentes, 2),
            'horas_contrato_no_titulares' => round(max(0.0, $contratoAsignado - $contratoTitulares - $contratoAsistentes), 2),
            'saldo_aula' => round($aulaPlan - $aulaAsignadas, 2),
            'saldo_contrato' => round($contratoRequerido - $contratoAsignado, 2),
            'porcentaje_cobertura' => self::porcentaje($aulaAsignadas, $aulaPlan),
            'porcentaje_cobertura_titular' => self::porcentaje($aulaTitulares, $aulaAsignadas),
            'porcentaje_cobertura_asistentes' => self::porcentaje($aulaAsistentes, $aulaAsignadas),
            'asignaturas_pendientes' => $items->filter(fn ($item) => in_array((string) data_get($item, 'estado.key', ''), ['sin_asignacion', 'pendiente'], true))->count(),
            'asignaturas_excedidas' => $items->where('estado.key', 'excedida')->count(),
        ];
    }

    public static function titularidad(?string $tipoContrato): array
    {
        $normalizado = Str::of((string) $tipoContrato)
            ->ascii()
            ->upper()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        if ($normalizado === '') {
            return ['key' => 'no_determinada', 'label' => 'No determinada', 'es_titular' => false];
        }

        foreach (['CONTRATA', 'REEMPLAZ', 'SUPLENC', 'HONORAR', 'PLAZO FIJO', 'TEMPORAL', 'INTERIN', 'PROVISOR'] as $token) {
            if (str_contains($normalizado, $token)) {
                return ['key' => 'no_titular', 'label' => 'No titular', 'es_titular' => false];
            }
        }

        foreach (['TITULAR', 'PLANTA'] as $token) {
            if (str_contains($normalizado, $token)) {
                return ['key' => 'titular', 'label' => 'Titular', 'es_titular' => true];
            }
        }

        return ['key' => 'no_determinada', 'label' => 'No determinada', 'es_titular' => false];
    }

    private static function filaBase(string $clave, ?int $asignaturaId, string $nombre): array
    {
        return [
            'key' => $clave,
            'asignatura_id' => $asignaturaId,
            'asignatura' => $nombre !== '' ? $nombre : 'Asignatura sin nombre',
            'horas_aula_plan' => 0.0,
            'horas_aula_asignadas' => 0.0,
            'horas_aula_titulares' => 0.0,
            'horas_aula_asistentes' => 0.0,
            'horas_aula_no_titulares' => 0.0,
            'horas_contrato_requeridas_65_35' => 0.0,
            'horas_contrato_requeridas_60_40' => 0.0,
            'horas_contrato_requeridas_especial' => 0.0,
            'horas_contrato_requeridas_total' => 0.0,
            'horas_contrato_asignadas_65_35' => 0.0,
            'horas_contrato_asignadas_60_40' => 0.0,
            'horas_contrato_asignadas_especial' => 0.0,
            'horas_contrato_asignadas_asistentes' => 0.0,
            'horas_contrato_asignadas_total' => 0.0,
            'horas_contrato_titulares' => 0.0,
            'horas_contrato_asistentes' => 0.0,
            'horas_contrato_no_titulares' => 0.0,
            'saldo_aula' => 0.0,
            'saldo_contrato' => 0.0,
            'porcentaje_cobertura' => 0.0,
            'porcentaje_cobertura_titular' => 0.0,
            'estado' => self::estado(0, 0),
            'proporciones' => [],
            'origenes_proporcion' => [],
            'detalle' => [],
            'buckets_asignacion' => [],
        ];
    }

    private static function contratoDesdeAula(string $proporcion, float $horasAula): float
    {
        $codigo = $proporcion === '60_40'
            ? DocenteHorasNoLectivasCalculator::PROPORCION_PRIORITARIOS
            : DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL;

        return round((float) data_get(
            DocenteHorasNoLectivasCalculator::contratoRequeridoDesdeHorasAula($codigo, $horasAula),
            'horas_contrato',
            0
        ), 2);
    }

    private static function grupoProporcion(?string $proporcion): string
    {
        $valor = Str::of((string) $proporcion)
            ->ascii()
            ->upper()
            ->replace([' ', '-', '_'], '/')
            ->replaceMatches('/\/+/', '/')
            ->trim('/')
            ->toString();

        if ($valor === '60/40') {
            return '60_40';
        }

        if ($valor === '' || $valor === '65/35') {
            return '65_35';
        }

        return 'especial';
    }

    private static function etiquetaProporcion(string $proporcion): string
    {
        return match ($proporcion) {
            '60_40' => '60/40',
            '65_35' => '65/35',
            default => 'Especial',
        };
    }

    private static function estado(float $requeridas, float $asignadas): array
    {
        if ($asignadas <= 0.01) {
            return ['key' => 'sin_asignacion', 'label' => 'Sin asignación', 'class' => 'text-bg-secondary'];
        }
        if ($asignadas + 0.01 < $requeridas) {
            return ['key' => 'pendiente', 'label' => 'Pendiente', 'class' => 'text-bg-warning'];
        }
        if ($asignadas - 0.01 > $requeridas) {
            return ['key' => 'excedida', 'label' => 'Excedida', 'class' => 'text-bg-danger'];
        }

        return ['key' => 'cubierta', 'label' => 'Cubierta', 'class' => 'text-bg-success'];
    }

    private static function porcentaje(float $parte, float $total): float
    {
        return $total > 0 ? round(min(100.0, max(0.0, ($parte / $total) * 100)), 2) : 0.0;
    }

    private static function normalizarAsignatura(?string $nombre): string
    {
        return Str::of((string) $nombre)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
