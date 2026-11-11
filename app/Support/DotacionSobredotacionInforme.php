<?php

namespace App\Support;

use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DotacionSobredotacionInforme
{
    public function datos(Establecimiento $establecimiento, int $anio): array
    {
        return DotacionLecturaCache::ejecutar(function () use ($establecimiento, $anio) {
            $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio, true, false);
            $proceso = DotacionProceso2027Calculator::resumen($establecimiento, $anio, $data);

            return $this->preparar($data, $proceso,
                DotacionSobredotacionJustificacion::registrosPara((int) $establecimiento->id, $anio),
                Schema::hasTable('dotacion_sobredotacion_justificaciones'));
        });
    }

    public function preparar(array $data, array $proceso, Collection $registros, bool $justificacionesDisponibles = true): array
    {
        $docentes = collect(($proceso['aplica'] ?? false) ? $proceso['docentes'] : ($data['docentes'] ?? []))
            ->reject(fn ($p) => isset($p['cupo_contrata_id']) || ($p['estamento_cobertura'] ?? 'docente') === 'asistente')->values();
        $resumen = $data['resumen'] ?? [];
        $sobredotacion = DotacionSobredotacionCalculator::build($docentes, $resumen, data_get($data, 'asignacion.necesidades.funciones', []));
        $nomina = new DotacionNominaDocentes;
        $metadatos = $nomina->filas($docentes, $sobredotacion, $registros);
        $norm = static fn ($rut) => DotacionEstablecimientoCalculator::normalizeRut((string) $rut);
        $personas = $docentes->keyBy(fn ($p) => $norm($p['rut'] ?? ''));
        $orden = $metadatos->mapWithKeys(fn ($p, $i) => [$norm($p['rut']) => $i]);
        $meta = $metadatos->keyBy(fn ($p) => $norm($p['rut']));
        $ordenar = static fn ($filas) => collect($filas)->map(fn ($p) => array_replace($meta->get($norm($p['rut'] ?? ''), []), $p))
            ->sortBy(fn ($p) => $orden->get($norm($p['rut'] ?? ''), PHP_INT_MAX))->values();
        $pertenencia = $nomina->bloquesNomina($docentes, $metadatos, (bool) ($resumen['establecimiento_especial'] ?? false));
        $saldos = $nomina->filasPorBloque($docentes, $sobredotacion, $registros);
        $justificaciones = $registros->keyBy(fn ($r) => DotacionSobredotacionJustificacion::clave($r->bloque, $r->docente_rut_normalizado, $r->tipo_horas));
        $fuera = $registros->filter(fn ($r) => $r->exists && $r->getKey()
            && DotacionSobredotacionJustificacion::horasVacantes($sobredotacion, $r->bloque, $r->docente_rut_normalizado, $r->tipo_horas) <= 0.01)
            ->map(fn ($r) => [
                'rut' => $r->docente_rut_normalizado,
                'nombre' => data_get($personas->get($norm($r->docente_rut_normalizado)), 'nombre', 'Sin antecedentes en la nómina'),
                'bloque' => $r->bloque, 'tipo' => $r->tipo_horas,
                'horas' => (float) $r->horas_detectadas, 'texto' => $r->fundamentoCompleto(),
                'actualizada' => $r->updated_at?->format('d-m-Y H:i'),
            ])->values();
        $ajustesPorBloque = collect(data_get($sobredotacion, 'aula.ajustes', []))->groupBy(function ($fila) use ($personas, $norm) {
            $persona = $personas->get($norm($fila['rut']), []);
            $bloque = DotacionProceso2027Calculator::bloqueFuncionPorDocente(['tipo_asignacion' => 'otra_funcion'], $persona) ?? 'bloque_1';
            return ['bloque_1' => 'plan_estudio', 'bloque_2' => 'parvularia', 'bloque_3' => 'pie'][$bloque];
        });
        $bloques = [];
        foreach (DotacionNominaDocentes::BLOQUES as $key => $titulo) {
            $ruts = $pertenencia[$key]->map(fn ($p) => $norm($p['rut']))->all();
            $conExceso = $docentes->filter(fn ($p) => in_array($norm($p['rut']), $ruts, true)
                && ($p['horas_contrato'] ?? null) !== null
                && (float) ($p['horas_asignadas_total'] ?? 0) + (float) ($p['redondeo_parvularia'] ?? 0) > (float) $p['horas_contrato'] + 0.01);
            $filas = $saldos[$key]->map(function ($fila) use ($key, $justificaciones, $personas, $norm) {
                $persona = $personas->get($norm($fila['rut']), []);
                $fila['funcion'] = $persona['funcion'] ?? '';
                $fila['tipo_contrato'] = $persona['tipo_contrato'] ?? '';
                foreach (['titular', 'contrata'] as $tipo) {
                    $r = $justificaciones->get(DotacionSobredotacionJustificacion::clave($key, $fila['rut'], $tipo));
                    $fila['horas_registradas_'.$tipo] = $r ? (float) $r->horas_detectadas : null;
                    $fila['actualizada_'.$tipo] = $r?->updated_at?->format('d-m-Y H:i');
                }
                return $fila;
            });
            $ref = ['plan_estudio' => ['bloque_1', 'horas_contrato_docentes_aula_general'],
                'parvularia' => ['bloque_2', 'horas_contrato_docentes_parvularia'], 'pie' => ['bloque_3', 'horas_contrato_docente_pie']][$key];
            $b = data_get($proceso, 'bloques.'.$ref[0], []);
            $reserva = (float) ($b['reservadas_no_normativas'] ?? 0);
            $redondeo = (float) ($b['redondeo_parvularia'] ?? 0);
            $ocupadas = (float) ($b['asignadas_con_redondeo'] ?? ((float) ($b['asignadas'] ?? 0) + $redondeo));
            $contrato = $resumen[$ref[1]] ?? null;
            $totales = [];
            foreach (['contrato_bloque', 'saldo_bloque', 'horas_titular', 'horas_contrata', 'con_justificacion', 'sin_justificacion'] as $campo) {
                $totales[$campo] = round((float) $filas->sum($campo), 2);
            }
            $bloques[$key] = [
                'titulo' => $titulo, 'filas' => $filas, 'totales' => $totales,
                'justificaciones_fuera' => $ordenar($fuera->where('bloque', $key)),
                'protegidos' => $ordenar(collect($sobredotacion['protegidos'])->filter(fn ($p) => in_array($norm($p['rut']), $ruts, true))),
                'sobrecargas' => $ordenar($conExceso), 'ajustes' => $ordenar($ajustesPorBloque->get($key, [])),
                'cuadratura' => ($proceso['aplica'] ?? false) ? [
                    'Contrato vigente' => $contrato, 'Asignadas' => round($ocupadas - $reserva, 2),
                    'Reservadas sin función' => $reserva, 'Saldo contractual neto' => $contrato === null ? null : round($contrato - $ocupadas, 2),
                    'Máximo autorizado' => $b['maximo'] ?? null, 'Vacantes revisables' => data_get($sobredotacion, 'vacantes_por_bloque.'.$key.'.horas_total', 0),
                    'Redondeo individual NT incluido' => $redondeo, 'Cupos o asignaciones sin padrón vigente' => $b['sin_padron_asignadas'] ?? 0,
                    'Cobertura AAEE (sin consumir contrato docente)' => $b['asignadas_asistentes_obligatorias'] ?? 0,
                ] : [],
            ];
        }
        $periodos = $docentes->map(fn ($p) => !empty($p['anio']) && !empty($p['mes']) ? sprintf('%02d/%04d', $p['mes'], $p['anio']) : 'Período no informado')->unique();

        return [
            'bloques' => $bloques, 'resumen' => data_get($sobredotacion, 'aula.resumen', []),
            'formula' => data_get($sobredotacion, 'aula.formula', []),
            'conciliacion' => DotacionConciliacionGeneral::build(
                collect(($proceso['aplica'] ?? false) ? $proceso['docentes'] : data_get($data, 'asignacion.docentes', $docentes))
                    ->concat(data_get($data, 'asignacion.plazas_por_asumir', [])), $resumen, $proceso, $sobredotacion),
            'periodos_padron' => $periodos->implode(', ') ?: 'Sin docentes en la nómina',
            'justificaciones_disponibles' => $justificacionesDisponibles,
            'otros_registros' => $fuera->reject(fn ($r) => array_key_exists($r['bloque'], DotacionNominaDocentes::BLOQUES))->values(),
        ];
    }
}
