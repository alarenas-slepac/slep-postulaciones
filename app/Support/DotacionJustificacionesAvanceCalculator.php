<?php

namespace App\Support;

use App\Models\DotacionSobredotacionJustificacion;
use Illuminate\Support\Facades\Schema;

/** Avance sobre los mismos saldos individuales que admite el registro de justificaciones. */
class DotacionJustificacionesAvanceCalculator
{
    public static function resumen(int $establecimientoId, int $anio, array $sobredotacion): array
    {
        $disponible = Schema::hasTable('dotacion_sobredotacion_justificaciones');
        $registros = $disponible
            ? DotacionSobredotacionJustificacion::query()
                ->where('establecimiento_id', $establecimientoId)->where('anio', $anio)->get()
                ->keyBy(fn ($registro) => DotacionSobredotacionJustificacion::clave(
                    $registro->bloque, $registro->docente_rut_normalizado, $registro->tipo_horas
                ))
            : collect();
        $total = 0.0;
        $justificadas = 0.0;

        foreach (['plan_estudio', 'parvularia', 'pie'] as $bloque) {
            foreach (data_get($sobredotacion, 'vacantes_por_bloque.'.$bloque.'.items', []) as $docente) {
                foreach (['titular' => 'horas_sobredotacion_planta', 'contrata' => 'horas_sobredotacion_contrata'] as $tipo => $campo) {
                    $horas = round(max(0.0, (float) ($docente[$campo] ?? 0)), 2);
                    if ($horas <= 0.01) {
                        continue;
                    }
                    $total += $horas;
                    $registro = $registros->get(DotacionSobredotacionJustificacion::clave($bloque, $docente['rut'], $tipo));
                    if ($registro && trim((string) $registro->justificacion) !== '' && $registro->vigentePara($horas)) {
                        $justificadas += $horas;
                    }
                }
            }
        }

        $total = round($total, 2);
        $justificadas = round($justificadas, 2);
        $pendientes = round(max(0.0, $total - $justificadas), 2);

        return [
            'disponible' => $disponible,
            'horas_total' => $total,
            'horas_justificadas' => $justificadas,
            'horas_pendientes' => $pendientes,
            'completo' => $pendientes <= 0.01,
            'detalle' => DotacionEstablecimientoCalculator::formatHoras($pendientes).' de '
                .DotacionEstablecimientoCalculator::formatHoras($total).' horas por justificar.',
        ];
    }
}
