<?php

namespace App\Services\WhatsApp;

final class WhatsAppPhone
{
    public static function normalize(?string $value): ?string
    {
        $phone = preg_replace('/[\s().-]+/u', '', trim((string) $value));
        if (str_starts_with($phone ?? '', '00')) {
            $phone = '+'.substr($phone, 2);
        } elseif (preg_match('/^56[0-9]{9}$/D', $phone ?? '')) {
            $phone = '+'.$phone;
        } elseif (preg_match('/^9[0-9]{8}$/D', $phone ?? '')) {
            $phone = '+56'.$phone;
        }
        if (str_starts_with($phone ?? '', '+56') && !preg_match('/^\+56[0-9]{9}$/D', $phone)) {
            return null;
        }
        return preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone ?? '') ? $phone : null;
    }
}
