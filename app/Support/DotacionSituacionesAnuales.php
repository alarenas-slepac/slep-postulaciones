<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Copia anual de situaciones; nunca copia asignaciones ni altera el padrón. */
class DotacionSituacionesAnuales
{
    public function copiarAlAnioSiguiente(int $establecimientoId, int $anioOrigen): int
    {
        if ($anioOrigen < 2020 || $anioOrigen >= 2100
            || ! Schema::hasTable('dotacion_situacion_traspasos')
            || ! Schema::hasTable('dotacion_docente_exclusiones')) {
            return 0;
        }

        return DB::transaction(function () use ($establecimientoId, $anioOrigen): int {
            // Serializa las copias del establecimiento en MySQL. Las claves
            // únicas también protegen frente a inserciones concurrentes.
            if (! DB::table('establecimientos')->where('id', $establecimientoId)->lockForUpdate()->first()) {
                return 0;
            }

            $anioDestino = $anioOrigen + 1;
            $fuente = DB::table('dotacion_docente_exclusiones')
                ->where('establecimiento_id', $establecimientoId)->where('anio', $anioOrigen)->orderBy('id')->get();
            $existentes = DB::table('dotacion_docente_exclusiones')
                ->where('establecimiento_id', $establecimientoId)->where('anio', $anioDestino)->get()
                ->keyBy(fn ($row) => DotacionEstablecimientoCalculator::normalizeRut($row->docente_rut_normalizado ?: $row->docente_rut));
            $procesados = DB::table('dotacion_situacion_traspasos')
                ->where('establecimiento_id', $establecimientoId)->where('anio_destino', $anioDestino)
                ->pluck('docente_rut_normalizado')->flip();
            $copiadas = 0;

            foreach ($fuente as $situacion) {
                $rut = DotacionEstablecimientoCalculator::normalizeRut($situacion->docente_rut_normalizado ?: $situacion->docente_rut);
                if ($rut === '' || $procesados->has($rut) || ! ($situacion->considerar_dotacion_siguiente ?? true)) {
                    continue;
                }

                $copiada = false;
                if (! $existentes->has($rut)) {
                    $datos = [
                        'establecimiento_id' => $establecimientoId, 'anio' => $anioDestino,
                        'docente_rut' => $situacion->docente_rut, 'docente_rut_normalizado' => $rut,
                        'docente_nombre' => $situacion->docente_nombre, 'motivo' => $situacion->motivo,
                        'horas' => $situacion->horas, 'created_by' => $situacion->created_by,
                        'updated_by' => $situacion->updated_by, 'created_at' => now(), 'updated_at' => now(),
                    ];
                    foreach (['considerar_dotacion_siguiente', 'conservar_horas_necesarias'] as $campo) {
                        if (property_exists($situacion, $campo)) {
                            $datos[$campo] = $situacion->{$campo} ?? true;
                        }
                    }
                    // Query Builder evita disparar saved otra vez y propagar
                    // la copia indefinidamente a años futuros.
                    $copiada = DB::table('dotacion_docente_exclusiones')->insertOrIgnore($datos) === 1;
                    if (! $copiada && ! DB::table('dotacion_docente_exclusiones')
                        ->where('establecimiento_id', $establecimientoId)->where('anio', $anioDestino)
                        ->where('docente_rut_normalizado', $rut)->exists()) {
                        throw new \RuntimeException('No fue posible completar el traspaso anual de situaciones docentes.');
                    }
                }

                $registrada = DB::table('dotacion_situacion_traspasos')->insertOrIgnore([
                    'establecimiento_id' => $establecimientoId, 'anio_origen' => $anioOrigen,
                    'anio_destino' => $anioDestino, 'docente_rut_normalizado' => $rut,
                    'situacion_origen_id' => $situacion->id, 'copiada' => $copiada,
                    'created_at' => now(), 'updated_at' => now(),
                ]) === 1;
                if (! $registrada && ! DB::table('dotacion_situacion_traspasos')
                    ->where('establecimiento_id', $establecimientoId)->where('anio_destino', $anioDestino)
                    ->where('docente_rut_normalizado', $rut)->exists()) {
                    throw new \RuntimeException('No fue posible registrar el historial del traspaso anual.');
                }
                $procesados->put($rut, true);
                $copiadas += (int) $copiada;
            }

            return $copiadas;
        });
    }
}
