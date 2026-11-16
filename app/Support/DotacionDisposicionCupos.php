<?php

namespace App\Support;

use Illuminate\Support\Collection;

/** Cupos por persona y bloque; la ocupación se lee siempre del registro vigente. */
class DotacionDisposicionCupos
{
    public static function clave(string $bloque, string $tipo, string $persona): string
    {
        return hash('sha256', implode('|', [$bloque, $tipo, $persona]));
    }

    public static function opciones(array $referencias, Collection $distribuciones): Collection
    {
        $cupos = collect();
        foreach ($referencias as $bloque => $referencia) {
            foreach ($referencia['detalles'] ?? [] as $detalle) {
                $persona = (string) ($detalle['persona'] ?? '');
                if ($persona === '' || (float) ($detalle['horas'] ?? 0) <= 0) { continue; }
                $tipo = (string) $detalle['tipo'];
                $clave = self::clave($bloque, $tipo, $persona);
                $cupo = $cupos->get($clave, [
                    'clave' => $clave, 'bloque' => $bloque, 'tipo' => $tipo, 'persona' => $persona,
                    'nombre' => $detalle['nombre'] ?? (($tipo === 'cupos' ? 'Cupo ' : 'Contrata ').$persona),
                    'titulo' => $detalle['titulo'] ?? 'Sin título declarado', 'areas' => [], 'niveles' => [], 'horas' => 0,
                ]);
                $cupo['horas'] += (int) round((float) $detalle['horas'] * 100);
                $cupo['areas'] = array_values(array_unique([...$cupo['areas'], ...array_filter([$detalle['area'] ?? ''])]));
                $cupo['niveles'] = array_values(array_unique([...$cupo['niveles'], ...($detalle['niveles'] ?? [])]));
                $cupos->put($clave, $cupo);
            }
        }
        $porCupo = $distribuciones->groupBy(fn ($r) => $r->antecedente['destino_cupo']['clave'] ?? '');

        return $cupos->map(function ($cupo) use ($porCupo): array {
            $registros = $porCupo->get($cupo['clave'], collect());
            $destinadas = (int) round((float) $registros->sum('horas') * 100);
            $cupo['horas'] /= 100;
            $cupo['destinadas'] = $destinadas / 100;
            $cupo['disponibles'] = max(0, (int) round($cupo['horas'] * 100) - $destinadas) / 100;
            $cupo['estado'] = $cupo['disponibles'] <= 0 ? 'Ya destinado' : ($destinadas > 0 ? 'Parcialmente destinado' : 'Disponible');
            $cupo['destinaciones'] = $registros->map(fn ($r) => [
                'id' => $r->id, 'nombre' => $r->antecedente['nombre'] ?? 'Docente',
                'origen' => $r->antecedente['establecimiento'] ?? '', 'horas' => (float) $r->horas,
            ])->values()->all();
            $cupo['label'] = (DotacionDisposicionDatos::BLOQUES[$cupo['bloque']] ?? $cupo['bloque']).' · '.$cupo['nombre']
                .' · '.$cupo['titulo'].' · '.DotacionEstablecimientoCalculator::formatHoras($cupo['disponibles']).' h disponibles'
                .' · '.$cupo['estado'];

            return $cupo;
        })->values();
    }
}
