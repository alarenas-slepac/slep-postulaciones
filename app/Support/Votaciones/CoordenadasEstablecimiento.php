<?php

namespace App\Support\Votaciones;

final class CoordenadasEstablecimiento
{
    public static function sonValidas(mixed $latitud, mixed $longitud): bool
    {
        if (! is_numeric($latitud) || ! is_numeric($longitud)) {
            return false;
        }

        $latitud = (float) $latitud;
        $longitud = (float) $longitud;

        return is_finite($latitud)
            && is_finite($longitud)
            && $latitud >= -90
            && $latitud <= 90
            && $longitud >= -180
            && $longitud <= 180;
    }

    /** @return array{latitud: ?float, longitud: ?float, validas: bool} */
    public static function normalizar(mixed $latitud, mixed $longitud): array
    {
        $validas = self::sonValidas($latitud, $longitud);

        return [
            'latitud' => $validas ? (float) $latitud : null,
            'longitud' => $validas ? (float) $longitud : null,
            'validas' => $validas,
        ];
    }
}
