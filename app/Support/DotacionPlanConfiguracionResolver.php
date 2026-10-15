<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** La configuración del EE sustituye el bloque oficial; no es un plan adicional. */
class DotacionPlanConfiguracionResolver
{
    public static function resolver(Collection $oficiales, Collection $configuradas, int $cursoId): array
    {
        $bloquesConfigurados = $configuradas->map(fn ($row) => self::tipo($row['tipo_bloque'] ?? null))
            ->reject(fn ($tipo) => $tipo === 'libre disposicion')->unique();
        $vigentes = $oficiales->reject(fn ($row) => $bloquesConfigurados->contains(self::tipo($row->tipo_bloque)))->values();
        $configuradas = $configuradas->map(function (array $row) use ($oficiales, $configuradas, $cursoId): array {
            $tipo = self::tipo($row['tipo_bloque'] ?? null);
            $nombreOficial = self::nombre($row['asignatura_oficial'] ?? $row['nombre']);
            $keyAnterior = self::key($cursoId, $row['tipo_bloque'] ?: 'libre_disposicion', $row['nombre']);
            $row['key'] = $keyAnterior;
            $row['necesidad_keys_historicas'] = [];
            if ($tipo === 'libre disposicion' || $nombreOficial === '') { return $row; }
            $coincidencias = $oficiales->filter(fn ($oficial) => self::tipo($oficial->tipo_bloque) === $tipo
                && self::nombre($oficial->asignatura) === $nombreOficial);
            $mismoOrigen = $configuradas->filter(fn ($custom) => self::tipo($custom['tipo_bloque'] ?? null) === $tipo
                && self::nombre($custom['asignatura_oficial'] ?? $custom['nombre']) === $nombreOficial);
            // Sólo un vínculo inequívoco conserva la clave oficial. Una materia
            // dividida entre varias configuraciones no admite repartir sus horas previas.
            if ($coincidencias->count() === 1 && $mismoOrigen->count() === 1) {
                $oficial = $coincidencias->first();
                $row['key'] = self::key($cursoId, $oficial->tipo_bloque ?: 'tiempo_minimo', $oficial->asignatura);
                if ($row['key'] !== $keyAnterior) { $row['necesidad_keys_historicas'][] = $keyAnterior; }
            }

            return $row;
        });

        return ['oficiales' => $vigentes, 'configuradas' => $configuradas];
    }

    private static function tipo(?string $tipo): string
    {
        $tipo = Str::of((string) $tipo)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        return in_array($tipo, ['', 'asignatura', 'tiempo minimo', 'obligatoria', 'plan comun formacion general'], true)
            ? 'plan comun formacion general' : $tipo;
    }

    private static function nombre(?string $nombre): string
    {
        $nombre = Str::of((string) $nombre)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->squish()->toString();

        return preg_replace('/^IDIOMA EXTRANJERO /', '', $nombre) ?? $nombre;
    }

    private static function key(int $cursoId, string $subtipo, string $nombre): string
    {
        return 'plan:'.md5($cursoId.'|'.$subtipo.'|'.$nombre);
    }
}
