<?php

namespace App\Support;

use Illuminate\Support\Str;

final class TramoDocente
{
    public const NO_REGIDO_LEY_20903 = 'No Regido Ley 20903';

    public static function esNoRegidoLey20903(?string $valor): bool
    {
        $clave = preg_replace('/[\s._-]+/u', '', Str::upper(Str::ascii((string) $valor)));

        return $clave === 'NOREGIDOLEY20903';
    }
}
