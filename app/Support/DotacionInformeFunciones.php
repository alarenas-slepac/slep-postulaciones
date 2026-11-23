<?php

namespace App\Support;

use App\Models\DotacionDocenteDistribucion;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Padron\PadronPeriodoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Consulta contractual por funciones; no modifica contratos ni asignaciones. */
class DotacionInformeFunciones
{
    public const ROLES = ['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

    public const GRUPOS = [
        'directiva' => 'Función directiva',
        'tecnico_pedagogica' => 'Función técnico-pedagógica',
        'aula' => 'Aula y planes normativos',
        'pie' => 'Otras funciones PIE',
    ];

    public static function puedeVer(?string $rol): bool
    {
        return in_array($rol, self::ROLES, true);
    }

    public static function establecimientos(): Builder
    {
        return Establecimiento::query()->where(fn ($query) => $query->whereNull('sala_cuna')->orWhere('sala_cuna', false));
    }

    public static function establecimientosFiltrados(string $q, string $comuna): Builder
    {
        return self::establecimientos()
            ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('nombre_establecimiento', 'like', "%{$q}%")
                ->orWhere('rbd', 'like', "%{$q}%")
                ->orWhere('comuna', 'like', "%{$q}%")))
            ->when($comuna !== '', fn ($query) => $query->where('comuna', $comuna))
            ->orderBy('comuna')->orderBy('nombre_establecimiento')->orderBy('id');
    }

    public function fila(Establecimiento $establecimiento, int $anio): array
    {
        $docentes = $this->nomina($establecimiento, $anio);

        return $this->resumir($docentes) + $this->horasSep($establecimiento, $anio, $docentes) + [
            'establecimiento_id' => (int) $establecimiento->id,
            'rbd' => (string) $establecimiento->rbd,
            'nombre' => (string) $establecimiento->nombre_establecimiento,
            'comuna' => (string) $establecimiento->comuna,
            'error' => false,
        ];
    }

    /** @return array<string, float> Saldos de contrato elegible por RUT. */
    public function saldosSinAsignar(Establecimiento $establecimiento, int $anio): array
    {
        $saldos = [];
        foreach ($this->docentesComputables($this->nomina($establecimiento, $anio)) as $docente) {
            if (($docente['exclusion_docente']['motivo'] ?? null) === 'proceso_bir') {
                continue;
            }
            [$horas] = $this->horasDocente($docente);
            $saldo = round((float) $docente['horas_contrato'] - array_sum($horas), 2);
            if ($saldo > 0) {
                $saldos[$this->rut($docente)] = $saldo;
            }
        }
        ksort($saldos);

        return $saldos;
    }

    private function nomina(Establecimiento $establecimiento, int $anio): Collection
    {
        $id = (int) $establecimiento->id;
        $justificados = DotacionSobredotacionJustificacion::rutsJustificados($id, $anio);
        $nomina = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio)
            ->concat(app(ContratacionHabilitacionService::class)->docentesVirtuales($establecimiento, $anio))
            ->keyBy(fn (array $docente) => $this->rut($docente));
        $movimientos = Schema::hasTable('dotacion_docente_distribuciones')
            ? DotacionDocenteDistribucion::query()->where('anio', $anio)->whereNull('anulada_at')
                ->where('horas', '>', 0)->where(fn ($q) => $q->where('origen_id', $id)->orWhere('destino_id', $id))
                ->orderBy('id')->get()
            : collect();
        $salidas = $movimientos->where('origen_id', $id)
            ->groupBy(fn ($movimiento) => DotacionEstablecimientoCalculator::normalizeRut($movimiento->docente_rut_normalizado));
        foreach ($salidas as $rut => $registros) {
            if ($docente = $nomina->get($rut)) {
                $restante = $this->descontarSalida($docente, (float) $registros->sum('horas'));
                if ($restante['horas_contrato_base'] > 0) {
                    $nomina->put($rut, $restante);
                } else {
                    $nomina->forget($rut);
                }
            }
        }

        $saldosCupos = [];
        foreach ($movimientos->where('destino_id', $id) as $movimiento) {
            $vinculo = $movimiento->antecedente['destino_cupo'] ?? [];
            $bloque = $vinculo['bloque'] ?? $movimiento->bloque;
            $horas = (float) $movimiento->horas;
            $porFuncion = [];
            $rutCupo = DotacionEstablecimientoCalculator::normalizeRut($vinculo['persona'] ?? null);
            $cupo = ($vinculo['tipo'] ?? '') === 'cupos' ? $nomina->get($rutCupo) : null;
            if ($cupo && ! empty($cupo['cupo_contrata_id'])) {
                $saldosCupos[$rutCupo][$bloque] ??= $this->saldoCupo($cupo, $vinculo, $bloque, $id, $anio);
                [$cupo, $porFuncion] = $this->cubrirCupo($cupo, $saldosCupos[$rutCupo][$bloque], $horas);
                if ($cupo['horas_contrato'] > 0) {
                    $nomina->put($rutCupo, $cupo);
                } else {
                    $nomina->forget($rutCupo);
                }
            }
            $rut = DotacionEstablecimientoCalculator::normalizeRut($movimiento->docente_rut_normalizado);
            $docente = $nomina->get($rut, ['rut_normalizado' => $rut, 'horas_contrato' => 0.0, 'horas_contrato_base' => 0.0]);
            $grupo = match ($bloque) {
                'plan_estudio', 'parvularia' => 'aula',
                'pie' => 'pie',
                default => 'otras',
            };
            $porFuncion[$grupo] = ($porFuncion[$grupo] ?? 0.0) + max(0.0, round($horas - array_sum($porFuncion), 2));
            $docente['horas_contrato'] += $horas;
            $docente['horas_contrato_base'] += $horas;
            foreach ($porFuncion as $funcion => $cantidad) {
                $docente['horas_movimientos'][$funcion] = ($docente['horas_movimientos'][$funcion] ?? 0.0) + $cantidad;
            }
            $nomina->put($rut, $docente);
        }

        return $nomina->except($justificados)->values();
    }

    private function descontarSalida(array $docente, float $horas): array
    {
        // Si el traslado ya figura como situación del padrón, descontar sólo el
        // saldo que aún estaba considerado en el establecimiento de origen.
        $traslado = ($docente['exclusion_docente']['motivo'] ?? '') === 'traslado'
            ? (float) ($docente['horas_excluidas'] ?? 0) : 0.0;
        $docente['horas_contrato_base'] = max(0.0, round($docente['horas_contrato_base'] - $horas, 2));
        $docente['horas_contrato'] = max(0.0, round($docente['horas_contrato'] - max(0.0, $horas - $traslado), 2));

        return $docente;
    }

    private function saldoCupo(array $cupo, array $vinculo, string $bloque, int $id, int $anio): array
    {
        $asignaciones = DotacionDisposicionDatos::asignacionesDestinoPorBloque($cupo, $id, $anio)->get($bloque, collect());
        $grupos = $asignaciones->groupBy(fn ($a) => $this->grupo($a))->map(fn (Collection $filas) => (float) $filas->sum('horas_contrato'))->all();

        return ['horas' => $grupos ? array_sum($grupos) : (float) ($vinculo['horas'] ?? $cupo['horas_contrato']), 'grupos' => $grupos];
    }

    private function cubrirCupo(array $cupo, array &$saldo, float $horas): array
    {
        $cubiertas = max(0.0, min($horas, $cupo['horas_contrato'], $saldo['horas']));
        $cupo['horas_contrato'] = round($cupo['horas_contrato'] - $cubiertas, 2);
        $cupo['horas_contrato_base'] = $cupo['horas_contrato'];
        $saldo['horas'] = round($saldo['horas'] - $cubiertas, 2);
        $porFuncion = [];
        foreach ($saldo['grupos'] as $funcion => $disponibles) {
            $cantidad = min($cubiertas, $disponibles);
            $cubiertas = round($cubiertas - $cantidad, 2);
            $saldo['grupos'][$funcion] = round($disponibles - $cantidad, 2);
            $cupo['horas_asignadas_total'] -= $cantidad;
            $cupo['horas_movimientos'][$funcion] = ($cupo['horas_movimientos'][$funcion] ?? 0.0) - $cantidad;
            $porFuncion[$funcion] = $cantidad;
        }

        return [$cupo, $porFuncion];
    }

    /** Recibe la nómina consolidada por RUT, incluidos los cupos aún disponibles. */
    public function resumir(Collection $docentes): array
    {
        $grupos = collect(self::GRUPOS)->map(fn () => ['docentes' => 0, 'horas' => 0.0])->all();
        $resumen = array_fill_keys([
            'docentes_total', 'contrato_original', 'horas_excluidas', 'contrato_total', 'contrato_considerado',
            'asignadas', 'otras', 'reservadas', 'sin_asignar', 'sobreasignadas', 'redondeo',
        ], 0);
        $periodos = collect();
        foreach ($this->docentesComputables($docentes) as $docente) {
            [$horas, $redondeo] = $this->horasDocente($docente);
            $contrato = max(0.0, (float) ($docente['horas_contrato'] ?? 0));
            $original = max(0.0, (float) ($docente['horas_contrato_base'] ?? $contrato));
            $asignadas = array_sum($horas) - $horas['reservadas'];
            $ocupadas = $asignadas + $horas['reservadas'];
            $resumen['docentes_total']++;
            $resumen['contrato_original'] += $original;
            $resumen['horas_excluidas'] += max(0.0, $original - $contrato);
            $resumen['contrato_total'] += $original;
            $resumen['contrato_considerado'] += $contrato;
            $resumen['asignadas'] += $asignadas;
            $resumen['otras'] += $horas['otras'];
            $resumen['reservadas'] += $horas['reservadas'];
            // El exceso de una persona nunca compensa el saldo de otra.
            $resumen['sin_asignar'] += max(0.0, $contrato - $ocupadas);
            $resumen['sobreasignadas'] += max(0.0, $ocupadas - $contrato);
            $resumen['redondeo'] += $redondeo;
            foreach (self::GRUPOS as $grupo => $label) {
                $grupos[$grupo]['horas'] += $horas[$grupo];
                $grupos[$grupo]['docentes'] += $horas[$grupo] > 0 ? 1 : 0;
            }
            if (($docente['anio'] ?? 0) > 0 && ($docente['mes'] ?? 0) > 0) {
                $periodos->push(sprintf('%02d/%d', $docente['mes'], $docente['anio']));
            }
        }
        foreach ($resumen as $key => $value) {
            $resumen[$key] = $key === 'docentes_total' ? (int) $value : round($value, 2);
        }
        foreach ($grupos as &$grupo) {
            $grupo['horas'] = round($grupo['horas'], 2);
        }
        unset($grupo);

        return $resumen + ['grupos' => $grupos, 'periodos' => $periodos->unique()->values()->all()];
    }

    private function docentesComputables(Collection $docentes): Collection
    {
        return $docentes->filter(fn (array $d) => ($d['estamento_cobertura'] ?? 'docente') === 'docente'
            && $this->rut($d) !== ''
            && (! empty($d['cupo_contrata_id']) || ! ContratacionHabilitacionService::esRutVirtual($this->rut($d))))
            ->unique(fn (array $d) => $this->rut($d));
    }

    private function horasDocente(array $docente): array
    {
        $rut = $this->rut($docente);
        $horas = array_fill_keys(array_keys(self::GRUPOS), 0.0) + ['otras' => 0.0, 'reservadas' => 0.0];
        $asignaciones = collect($docente['asignaciones'] ?? [])
            ->filter(fn ($a) => (data_get($a, 'estado') ?? 'activa') === 'activa'
                && DotacionAsignacionCalculator::coverageEstamento($a) === 'docente'
                && DotacionEstablecimientoCalculator::normalizeRut((string) (data_get($a, 'docente_rut_normalizado') ?: data_get($a, 'docente_rut', $rut))) === $rut)
            ->unique(fn ($a, $key) => data_get($a, 'id') ? 'id:'.data_get($a, 'id') : 'fila:'.$key);
        foreach ($asignaciones as $asignacion) {
            $horas[$this->grupo($asignacion)] += max(0.0, (float) data_get($asignacion, 'horas_contrato', 0));
        }
        foreach ($docente['horas_movimientos'] ?? [] as $grupo => $cantidad) {
            $horas[$grupo] = max(0.0, round($horas[$grupo] + $cantidad, 2));
        }
        $redondeo = DotacionSobredotacionCalculator::redondeoParvulariaDocente($docente);
        $horas['aula'] += $redondeo;

        return [$horas, $redondeo];
    }

    private function grupo(object|array $asignacion): string
    {
        $tipo = data_get($asignacion, 'tipo_asignacion');
        if ($tipo === 'reserva_no_normativa') {
            return 'reservadas';
        }
        if (in_array($tipo, ['plan_estudio', 'acompanamiento_parvularia', 'pie_colaborativo', 'plan_normativo'], true)) {
            return 'aula';
        }
        if ($tipo === 'pie_educadora_diferencial' || DotacionAsignacionCalculator::esAsignacionCoordinacionPie($asignacion)) {
            return 'pie';
        }
        // El formato del informe considera Inspectoría, UTP y Convivencia como
        // apoyo técnico-pedagógico aunque una carga histórica diga "directiva".
        $nombre = Str::upper(Str::ascii((string) data_get($asignacion, 'asignatura_nombre', '')));
        if (in_array($tipo, ['funcion_directiva', 'funcion_tecnico_pedagogica', 'otra_funcion'], true)
            && preg_match('/INSPECTOR.*GENERAL|\bUTP\b|CONVIVENCIA|UNIDAD TECNIC[AO].*PEDAGOGIC/', $nombre)) {
            return 'tecnico_pedagogica';
        }

        return match ($tipo) {
            'funcion_directiva' => 'directiva',
            'funcion_tecnico_pedagogica' => 'tecnico_pedagogica',
            default => 'otras',
        };
    }

    private function horasSep(Establecimiento $establecimiento, int $anio, Collection $docentes): array
    {
        if ($docentes->isEmpty() || ! Schema::hasTable('reemplazos_personal')) {
            return ['sep' => 0.0, 'sep_sin_desglose' => 0.0];
        }
        $nomina = $docentes->keyBy(fn (array $d) => $this->rut($d));
        $lineas = app(PadronPeriodoService::class)->consultaAnualParaDotacion((int) $establecimiento->id, $anio)
            ->sinReemplazoSuplencia()->get()
            ->filter(function ($linea) use ($nomina): bool {
                $docente = $nomina->get(DotacionEstablecimientoCalculator::normalizeRut((string) $linea->rut));
                return $docente && (int) $linea->anio === (int) ($docente['anio'] ?? 0) && (int) $linea->mes === (int) ($docente['mes'] ?? 0);
            })
            ->unique(fn ($l) => $l->row_hash ?: implode('|', [
                $l->rut, $l->anio, $l->mes, $l->fecha_ingreso, $l->fecha_termino,
                $l->tipocontrato, $l->financiamiento, $l->estatuto, $l->escalafon,
                $l->jornada, $l->jornada_basica, $l->jornada_media,
            ]));
        $sep = 0.0;
        $sinDesglose = 0.0;
        foreach ($lineas as $linea) {
            $financiamiento = Str::of((string) $linea->financiamiento)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->squish()->toString();
            if (in_array($financiamiento, ['SEP', 'SUBVENCION ESCOLAR PREFERENCIAL'], true)) {
                $sep += max(0.0, (float) $linea->jornada);
            } elseif (preg_match('/\bSEP\b|SUBVENCION ESCOLAR PREFERENCIAL/', $financiamiento)) {
                $sinDesglose += max(0.0, (float) $linea->jornada);
            }
        }

        return ['sep' => round($sep, 2), 'sep_sin_desglose' => round($sinDesglose, 2)];
    }

    private function rut(array $docente): string
    {
        return DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? ''));
    }
}
