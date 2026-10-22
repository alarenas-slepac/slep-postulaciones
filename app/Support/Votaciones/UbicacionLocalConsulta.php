<?php

namespace App\Support\Votaciones;

use App\Models\LocalConsultaVotacion;

final class UbicacionLocalConsulta
{
    /** Datos públicos del destino, sin información del votante ni de su lugar de trabajo. */
    public static function para(LocalConsultaVotacion $local): array
    {
        $establecimiento = $local->establecimientoDestino;
        $coordenadas = CoordenadasEstablecimiento::normalizar($establecimiento?->latitud, $establecimiento?->longitud);
        $validas = $coordenadas['validas'] && ! ($coordenadas['latitud'] === 0.0 && $coordenadas['longitud'] === 0.0);
        $direccion = trim((string) $establecimiento?->admisionPerfil?->direccion_publica);

        return [
            'direccion' => $direccion !== '' ? $direccion : null,
            'comuna' => trim((string) $establecimiento?->comuna),
            'latitud' => $validas ? $coordenadas['latitud'] : null,
            'longitud' => $validas ? $coordenadas['longitud'] : null,
            'validas' => $validas,
            'enlace' => $validas ? 'https://www.google.com/maps/dir/?'.http_build_query([
                'api' => 1, 'destination' => $coordenadas['latitud'].','.$coordenadas['longitud'],
            ], '', '&', PHP_QUERY_RFC3986) : null,
        ];
    }
}
