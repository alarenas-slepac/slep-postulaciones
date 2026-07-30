<?php

namespace App\Support;

final class Rut
{
    public static function normalize(?string $rut): ?string
    {
        if (!$rut) return null;
        $rut = strtoupper(trim($rut));
        $rut = preg_replace('/[^0-9K]/', '', $rut);
        return $rut ?: null;
    }

    public static function format(?string $rut): ?string
    {
        $n = self::normalize($rut);
        if (!$n || strlen($n) < 2) return $rut;

        $dv  = substr($n, -1);
        $num = substr($n, 0, -1);

        $num = strrev(implode('.', str_split(strrev($num), 3)));

        return $num . '-' . $dv;
    }
}
