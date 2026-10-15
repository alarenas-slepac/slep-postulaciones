<?php

namespace App\Support;

use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Padron\PadronPeriodoService;
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

    public function fila(Establecimiento $establecimiento, int $anio): array
    {
        // Reutiliza la nómina consolidada de dotación, sus situaciones y su año
        // de respaldo, sin construir planes, necesidades ni el proceso guiado.
        $docentes = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio);

        return $this->resumir($docentes) + $this->horasSep($establecimiento, $anio, $docentes) + [
            'establecimiento_id' => (int) $establecimiento->id,
            'rbd' => (string) $establecimiento->rbd,
            'nombre' => (string) $establecimiento->nombre_establecimiento,
            'comuna' => (string) $establecimiento->comuna,
            'error' => false,
        ];
    }

    /** Recibe la nómina ya consolidada por RUT que utiliza dotación. */
    public function resumir(Collection $docentes): array
    {
        $grupos = collect(self::GRUPOS)->map(fn () => ['docentes' => 0, 'horas' => 0.0])->all();
        $resumen = array_fill_keys([
            'docentes_total', 'contrato_original', 'horas_excluidas', 'contrato_total', 'contrato_considerado',
            'asignadas', 'otras', 'reservadas', 'sin_asignar', 'sobreasignadas', 'redondeo',
        ], 0);
        $periodos = collect();
        $docentes = $docentes->filter(fn (array $d) => ($d['estamento_cobertura'] ?? 'docente') === 'docente'
            && empty($d['cupo_contrata_id']) && $this->rut($d) !== ''
            && ! ContratacionHabilitacionService::esRutVirtual($this->rut($d)))
            ->unique(fn (array $d) => $this->rut($d));

        foreach ($docentes as $docente) {
            $rut = $this->rut($docente);
            $horas = array_fill_keys(array_keys(self::GRUPOS), 0.0);
            $horas += ['otras' => 0.0, 'reservadas' => 0.0];
            $asignaciones = collect($docente['asignaciones'] ?? [])
                ->filter(fn ($a) => (data_get($a, 'estado') ?? 'activa') === 'activa'
                    && DotacionAsignacionCalculator::coverageEstamento($a) === 'docente'
                    && DotacionEstablecimientoCalculator::normalizeRut((string) (data_get($a, 'docente_rut_normalizado') ?: data_get($a, 'docente_rut', $rut))) === $rut)
                ->unique(fn ($a, $key) => data_get($a, 'id') ? 'id:'.data_get($a, 'id') : 'fila:'.$key);
            foreach ($asignaciones as $asignacion) {
                $horas[$this->grupo($asignacion)] += max(0.0, (float) data_get($asignacion, 'horas_contrato', 0));
            }
            $contrato = max(0.0, (float) ($docente['horas_contrato'] ?? 0));
            $original = max(0.0, (float) ($docente['horas_contrato_base'] ?? $contrato));
            $redondeo = DotacionSobredotacionCalculator::redondeoParvulariaDocente($docente);
            $horas['aula'] += $redondeo;
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
                return $docente && (int) $linea->anio === (int) $docente['anio'] && (int) $linea->mes === (int) $docente['mes'];
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
