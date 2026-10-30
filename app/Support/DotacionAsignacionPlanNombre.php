<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Una clave curricular estable no transfiere horas entre nombres personalizados distintos. */
final class DotacionAsignacionPlanNombre
{
    public static function coincide(object|array $asignacion, array $necesidad): bool
    {
        $nombre = self::normalizar((string) data_get($asignacion, 'asignatura_nombre', ''));
        if ($nombre === '') {
            // Los registros antiguos sin etiqueta siguen dependiendo de sus vínculos.
            return true;
        }

        $nombres = collect([
            $necesidad['asignatura_nombre'] ?? $necesidad['titulo'] ?? '',
            $necesidad['nombre_personalizado'] ?? '',
            ...collect($necesidad['curso_combinado_desglose'] ?? [])->pluck('nombre')->all(),
            ...($necesidad['curso_combinado_libre_disposicion_nombres'] ?? []),
        ])->map(fn ($valor) => self::normalizar((string) $valor))->filter(fn ($valor) => $valor !== '')->unique();

        return $nombres->isEmpty() || $nombres->contains($nombre);
    }

    private static function normalizar(string $nombre): string
    {
        $nombre = Str::of($nombre)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->squish()->toString();

        // Alias de importación ya admitido por DotacionPlanConfiguracionResolver.
        return preg_replace('/^IDIOMA EXTRANJERO /', '', $nombre) ?? $nombre;
    }
}
