<?php

namespace App\Support;

use Closure;

/** Referencias de lectura reutilizables únicamente durante un cálculo de dotación. */
final class DotacionLecturaCache
{
    private static ?array $valores = null;

    public static function ejecutar(Closure $calculo): mixed
    {
        if (self::$valores !== null) {
            return $calculo();
        }

        self::$valores = [];
        try {
            return $calculo();
        } finally {
            // No conservar referencias entre peticiones ni después de un error.
            self::$valores = null;
        }
    }

    public static function recordar(string $clave, Closure $consulta): mixed
    {
        if (self::$valores === null) {
            return $consulta();
        }

        if (! array_key_exists($clave, self::$valores)) {
            self::$valores[$clave] = $consulta();
        }

        return self::$valores[$clave];
    }
}
