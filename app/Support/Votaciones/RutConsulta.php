<?php

namespace App\Support\Votaciones;

use App\Support\Rut;
use App\Support\RutChile;

final class RutConsulta
{
    public static function normalizar(mixed $valor): ?string
    {
        if (! is_scalar($valor) || ! preg_match('/^[0-9kK.\s-]+$/u', (string) $valor)) {
            return null;
        }
        $rut = Rut::normalize((string) $valor);
        if (! preg_match('/^[1-9][0-9]{5,7}[0-9K]$/', $rut ?? '')) {
            return null;
        }

        return RutChile::dv((int) substr($rut, 0, -1)) === substr($rut, -1) ? $rut : null;
    }
}
