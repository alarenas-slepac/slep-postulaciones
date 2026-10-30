<?php

namespace App\Services\Maternidad;

use App\Support\RutChile;
use Illuminate\Validation\ValidationException;

final class MaternidadRut
{
    public static function normalizar(?string $valor, string $campo = 'rut'): string
    {
        $rut = strtoupper(preg_replace('/[.\s-]+/u', '', (string) $valor));
        if (! preg_match('/^([0-9]{1,8})([0-9K])$/', $rut, $partes)
            || (int) $partes[1] === 0 || RutChile::dv((int) $partes[1]) !== $partes[2]) {
            throw ValidationException::withMessages([$campo => 'Ingrese el RUT completo con dígito verificador válido.']);
        }
        return (string) (int) $partes[1].'-'.$partes[2];
    }

    public static function clave(string $rut): string { return str_replace('-', '', self::normalizar($rut)); }
}
