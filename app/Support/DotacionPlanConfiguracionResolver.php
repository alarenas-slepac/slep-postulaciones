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
        $vigentes = $oficiales->reject(fn ($row) => $bloquesConfigurados->contains(self::tipoOficial($row)))->values();
        $configuradas = $configuradas->map(function (array $row) use ($oficiales, $configuradas, $cursoId): array {
            $tipo = self::tipo($row['tipo_bloque'] ?? null);
            $nombreOficial = self::nombre($row['asignatura_oficial'] ?? $row['nombre']);
            $keyAnterior = self::key($cursoId, $row['tipo_bloque'] ?: 'libre_disposicion', $row['nombre']);
            $row['key'] = $keyAnterior;
            $row['necesidad_keys_historicas'] = [];
            if ($tipo === 'libre disposicion' || $nombreOficial === '') {
                return $row;
            }
            $coincidencias = $oficiales->filter(fn ($oficial) => self::tipoOficial($oficial) === $tipo
                && self::nombre($oficial->asignatura) === $nombreOficial);
            $mismoOrigen = $configuradas->filter(fn ($custom) => self::tipo($custom['tipo_bloque'] ?? null) === $tipo
                && self::nombre($custom['asignatura_oficial'] ?? $custom['nombre']) === $nombreOficial);
            // Sólo un vínculo inequívoco conserva la clave oficial. Una materia
            // dividida entre varias configuraciones no admite repartir sus horas previas.
            if ($coincidencias->count() === 1 && $mismoOrigen->count() === 1) {
                $oficial = $coincidencias->first();
                $row['key'] = self::key($cursoId, $oficial->tipo_bloque ?: 'tiempo_minimo', $oficial->asignatura);
                if ($row['key'] !== $keyAnterior) {
                    $row['necesidad_keys_historicas'][] = $keyAnterior;
                }
            }

            return $row;
        });

        return ['oficiales' => $vigentes, 'configuradas' => $configuradas];
    }

    private static function tipo(?string $tipo): string
    {
        $tipo = Str::of((string) $tipo)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();

        return match ($tipo) {
            '', 'asignatura', 'tiempo minimo', 'obligatoria', 'plan comun formacion general' => 'plan comun formacion general',
            'plan comun electivo' => 'plan comun formacion general electivo',
            'plan diferenciado humanistico cientifico', 'plan diferenciado humanistico cientifica' => 'plan diferenciado hc',
            default => $tipo,
        };
    }

    private static function tipoOficial(object $oficial): string
    {
        // Los planes importados pueden clasificar estos totales como asignatura,
        // tiempo mínimo o plan diferenciado sin modalidad. Sólo sus títulos
        // genéricos conocidos identifican el bloque que sustituye el desglose.
        return match (self::nombre($oficial->asignatura)) {
            'PLAN DIFERENCIADO HUMANISTICO CIENTIFICO', 'PLAN DIFERENCIADO HUMANISTICO CIENTIFICA',
            'PLAN DIFERENCIADO HC' => 'plan diferenciado hc',
            'PLAN COMUN FORMACION GENERAL ELECTIVO', 'PLAN COMUN ELECTIVO' => 'plan comun formacion general electivo',
            default => self::tipo($oficial->tipo_bloque),
        };
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
