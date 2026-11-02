<?php

namespace App\Support;

use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DotacionNominaDocentes
{
    public function datos(Establecimiento $establecimiento, int $anio): array
    {
        return DotacionLecturaCache::ejecutar(function () use ($establecimiento, $anio) {
            $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio, true, false);
            $proceso = DotacionProceso2027Calculator::resumen($establecimiento, $anio, $data);
            $docentes = collect(($proceso['aplica'] ?? false) ? $proceso['docentes'] : $data['docentes'])
                ->reject(fn (array $docente) => isset($docente['cupo_contrata_id']) || ($docente['estamento_cobertura'] ?? 'docente') === 'asistente');
            $sobredotacion = DotacionSobredotacionCalculator::build(
                $docentes, $data['resumen'], data_get($data, 'asignacion.necesidades.funciones', [])
            );
            $periodos = $docentes->map(fn (array $docente) => !empty($docente['anio']) && !empty($docente['mes'])
                ? sprintf('%02d/%04d', $docente['mes'], $docente['anio']) : 'Período no informado')->unique()->values();

            return [
                'filas' => $this->filas($docentes, $sobredotacion, DotacionSobredotacionJustificacion::registrosPara((int) $establecimiento->id, $anio)),
                'periodos_padron' => $periodos->implode(', ') ?: 'Sin docentes en la nómina',
                'justificaciones_disponibles' => Schema::hasTable('dotacion_sobredotacion_justificaciones'),
            ];
        });
    }

    /** Usa la prelación y los saldos de Etapa 7 sin modificar los cálculos operativos. */
    public function filas(Collection $docentes, array $sobredotacion, Collection $registros): Collection
    {
        $registros = $registros->keyBy(fn ($registro) => DotacionSobredotacionJustificacion::clave(
            $registro->bloque, $registro->docente_rut_normalizado, $registro->tipo_horas
        ));
        $revision = [];
        foreach (['plan_estudio' => 'Plan general', 'parvularia' => 'Educación Parvularia', 'pie' => 'PIE especializado'] as $bloque => $nombreBloque) {
            foreach (data_get($sobredotacion, 'vacantes_por_bloque.'.$bloque.'.items', []) as $docente) {
                $rut = DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut'] ?? ''));
                if ($rut === '') { continue; }
                foreach (['titular' => 'horas_sobredotacion_planta', 'contrata' => 'horas_sobredotacion_contrata'] as $tipo => $campo) {
                    $horas = round(max(0.0, (float) ($docente[$campo] ?? 0)), 2);
                    if ($horas <= 0.01) { continue; }
                    $registro = $registros->get(DotacionSobredotacionJustificacion::clave($bloque, $rut, $tipo));
                    $vigente = $registro && trim((string) $registro->justificacion) !== '' && $registro->vigentePara($horas);
                    $revision[$rut] ??= ['con' => 0.0, 'sin' => 0.0, 'detalle' => []];
                    $revision[$rut][$vigente ? 'con' : 'sin'] += $horas;
                    $descripcion = $nombreBloque.' · '.($tipo === 'titular' ? 'Supresión titular' : 'No renovación contrata').': '
                        .DotacionEstablecimientoCalculator::formatHoras($horas).' h · '.($vigente ? 'Con justificación vigente' : 'Sin justificación vigente');
                    if ($registro && trim((string) $registro->justificacion) !== '') {
                        $descripcion .= $vigente ? ': '.$registro->justificacion
                            : ' (justificación registrada para '.DotacionEstablecimientoCalculator::formatHoras($registro->horas_detectadas).' h): '.$registro->justificacion;
                    }
                    $revision[$rut]['detalle'][] = $descripcion;
                }
            }
        }
        $personas = $docentes->reject(fn (array $docente) => isset($docente['cupo_contrata_id']) || ($docente['estamento_cobertura'] ?? 'docente') === 'asistente');

        return DotacionProceso2027Calculator::docentesPriorizados($personas)->map(function (array $docente) use ($revision): array {
            $rut = DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut'] ?? ''));
            $redondeo = (float) ($docente['redondeo_parvularia'] ?? 0);
            $ocupadas = round((float) ($docente['horas_asignadas_total'] ?? 0) + $redondeo, 2);
            $reservadas = round((float) ($docente['horas_reservadas_no_normativas'] ?? 0), 2);
            $original = $docente['horas_contrato_base'] ?? $docente['horas_contrato'] ?? null;
            $contrato = $docente['horas_contrato'] ?? null;
            $diferencia = array_key_exists('diferencia', $docente) ? $docente['diferencia']
                : ($contrato === null ? null : (float) $contrato - (float) ($docente['horas_asignadas_total'] ?? 0));

            return [
                'rut' => (string) ($docente['rut'] ?? ''), 'nombre' => (string) ($docente['nombre'] ?? ''),
                'titulo' => (string) ($docente['titulo'] ?? 'Sin título declarado'),
                'prelacion' => $docente['prioridad_2027_label'], 'tramo' => (string) ($docente['tramo'] ?? ''),
                'fecha_antiguedad' => $docente['fecha_antiguedad'] ?? null,
                'contrato_original' => $original === null ? null : round((float) $original, 2),
                'contrato_considerado' => $contrato === null ? null : round((float) $contrato, 2),
                'aula_asignada' => round((float) ($docente['horas_aula'] ?? 0), 2),
                'contrato_asignado' => round($ocupadas - $reservadas, 2), 'reservadas' => $reservadas,
                'pendientes' => $diferencia === null ? null : round((float) $diferencia - $redondeo, 2),
                'con_justificacion' => round($revision[$rut]['con'] ?? 0.0, 2),
                'sin_justificacion' => round($revision[$rut]['sin'] ?? 0.0, 2),
                'detalle_justificaciones' => implode("\n", $revision[$rut]['detalle'] ?? []),
            ];
        })->values();
    }
}
