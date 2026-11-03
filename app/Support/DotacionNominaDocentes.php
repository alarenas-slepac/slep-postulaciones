<?php

namespace App\Support;

use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DotacionNominaDocentes
{
    public const BLOQUES = ['plan_estudio' => 'Plan de estudio', 'parvularia' => 'Educación Parvularia', 'pie' => 'PIE'];

    public function datos(Establecimiento $establecimiento, int $anio): array
    {
        return $this->informe($establecimiento, $anio, false);
    }

    public function datosSobredotacion(Establecimiento $establecimiento, int $anio): array
    {
        return $this->informe($establecimiento, $anio, true);
    }

    private function informe(Establecimiento $establecimiento, int $anio, bool $porBloque): array
    {
        return DotacionLecturaCache::ejecutar(function () use ($establecimiento, $anio, $porBloque) {
            $data = DotacionEstablecimientoCalculator::build($establecimiento, $anio, true, false);
            $proceso = DotacionProceso2027Calculator::resumen($establecimiento, $anio, $data);
            $docentes = collect(($proceso['aplica'] ?? false) ? $proceso['docentes'] : $data['docentes'])
                ->reject(fn (array $docente) => isset($docente['cupo_contrata_id']) || ($docente['estamento_cobertura'] ?? 'docente') === 'asistente');
            $sobredotacion = DotacionSobredotacionCalculator::build(
                $docentes, $data['resumen'], data_get($data, 'asignacion.necesidades.funciones', [])
            );
            $periodos = $docentes->map(fn (array $docente) => !empty($docente['anio']) && !empty($docente['mes'])
                ? sprintf('%02d/%04d', $docente['mes'], $docente['anio']) : 'Período no informado')->unique()->values();

            $registros = DotacionSobredotacionJustificacion::registrosPara((int) $establecimiento->id, $anio);
            if ($porBloque) {
                $filas = ['bloques' => $this->filasPorBloque($docentes, $sobredotacion, $registros)];
            } else {
                $nomina = $this->filas($docentes, $sobredotacion, $registros);
                $filas = ['filas' => $nomina, 'bloques' => $this->bloquesNomina(
                    $docentes, $nomina, (bool) ($data['resumen']['establecimiento_especial'] ?? false)
                )];
            }

            return $filas + [
                'periodos_padron' => $periodos->implode(', ') ?: 'Sin docentes en la nómina',
                'justificaciones_disponibles' => Schema::hasTable('dotacion_sobredotacion_justificaciones'),
            ];
        });
    }

    /** Nómina completa por aporte contractual, incluidos docentes sin saldo y protegidos. */
    public function bloquesNomina(Collection $docentes, Collection $filas, bool $especial = false): array
    {
        $pertenencia = array_fill_keys(array_keys(self::BLOQUES), []);
        foreach ($docentes as $docente) {
            if (isset($docente['cupo_contrata_id']) || ($docente['estamento_cobertura'] ?? 'docente') === 'asistente') { continue; }
            $rut = DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut'] ?? ''));
            $contrato = max(0.0, (float) ($docente['horas_contrato'] ?? 0));
            $pie = $especial ? 0.0 : min($contrato, max(0.0, (float) ($docente['horas_contrato_pie']
                ?? DotacionAsignacionCalculator::contratoPiePorDocente($docente))));
            $aula = max(0.0, round($contrato - $pie, 2));
            $parvularia = min($aula, (float) DotacionEstablecimientoCalculator::contratoParvularia([$docente], $aula, 0)['horas_contrato_docentes_parvularia']);
            $aportes = ['plan_estudio' => round($aula - $parvularia, 2), 'parvularia' => $parvularia, 'pie' => $pie];
            foreach ($aportes as $bloque => $horas) {
                if ($horas > 0.01) { $pertenencia[$bloque][$rut] = true; }
            }
            // Conserva docentes con contrato cero o desconocido: no se pierden por agrupar.
            if (max($aportes) <= 0.01) {
                $perfil = DotacionProfesionDocenteResolver::perfilTitulo($docente);
                $bloque = $perfil['es_educacion_parvulos'] ? 'parvularia'
                    : (!$especial && $perfil['es_educacion_diferencial'] ? 'pie' : 'plan_estudio');
                $pertenencia[$bloque][$rut] = true;
            }
        }
        $bloques = [];
        foreach (self::BLOQUES as $bloque => $titulo) {
            $bloques[$bloque] = $filas->filter(fn (array $fila) => isset($pertenencia[$bloque][
                DotacionEstablecimientoCalculator::normalizeRut((string) $fila['rut'])
            ]))->values();
        }

        return $bloques;
    }

    /** Una fila por docente y bloque con saldo, conservando ambos tipos de fundamento. */
    public function filasPorBloque(Collection $docentes, array $sobredotacion, Collection $registros): array
    {
        $porClave = $registros->keyBy(fn ($registro) => DotacionSobredotacionJustificacion::clave(
            $registro->bloque, $registro->docente_rut_normalizado, $registro->tipo_horas
        ));
        $bloques = [];
        foreach (['plan_estudio', 'parvularia', 'pie'] as $bloque) {
            $vacantes = collect(data_get($sobredotacion, 'vacantes_por_bloque.'.$bloque.'.items', []))
                ->filter(fn (array $item) => (float) ($item['horas_sobredotacion_total'] ?? 0) > 0.01)
                ->keyBy(fn (array $item) => DotacionEstablecimientoCalculator::normalizeRut((string) $item['rut']));
            $personas = $docentes->filter(fn (array $docente) => $vacantes->has(
                DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut'] ?? ''))
            ));
            $bloques[$bloque] = $this->filas($personas, ['vacantes_por_bloque' => [$bloque => ['items' => $vacantes]]], $registros)
                ->map(function (array $fila) use ($bloque, $vacantes, $porClave): array {
                    $rut = DotacionEstablecimientoCalculator::normalizeRut($fila['rut']);
                    $vacante = $vacantes->get($rut);
                    $fila['contrato_bloque'] = round((float) $vacante['horas_contrato_categoria'], 2);
                    $fila['saldo_bloque'] = round((float) $vacante['horas_sobredotacion_total'], 2);
                    foreach (['titular' => 'horas_sobredotacion_planta', 'contrata' => 'horas_sobredotacion_contrata'] as $tipo => $campo) {
                        $horas = round((float) $vacante[$campo], 2);
                        $registro = $porClave->get(DotacionSobredotacionJustificacion::clave($bloque, $rut, $tipo));
                        $texto = trim((string) ($registro?->justificacion ?? ''));
                        $fila['horas_'.$tipo] = $horas;
                        $fila['justificacion_'.$tipo] = $texto !== '' ? $texto : ($horas > 0.01 ? 'Sin justificación' : 'No aplica');
                        $fila['estado_'.$tipo] = match (true) {
                            $horas <= 0.01 => 'Sin saldo actual',
                            $texto === '' => 'Sin justificación',
                            $registro->vigentePara($horas) => 'Vigente',
                            default => 'Desactualizada · registrada para '.DotacionEstablecimientoCalculator::formatHoras($registro->horas_detectadas).' h',
                        };
                    }

                    return $fila;
                })->values();
        }

        return $bloques;
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
