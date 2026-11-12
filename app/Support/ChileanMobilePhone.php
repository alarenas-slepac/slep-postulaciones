<?php

namespace App\Support;

final class ChileanMobilePhone
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 64) { return null; }
        $phone = preg_replace('/[\s().-]+/u', '', trim($value));
        if (preg_match('/^9[0-9]{8}$/D', $phone ?? '')) { return '+56'.$phone; }
        if (preg_match('/^569[0-9]{8}$/D', $phone ?? '')) { return '+'.$phone; }
        return preg_match('/^\+569[0-9]{8}$/D', $phone ?? '') ? $phone : null;
    }
}
