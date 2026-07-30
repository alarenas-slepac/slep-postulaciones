<?php

namespace App\Services\LicenciasMedicas;

class RutNormalizer
{
    public static function normalize(?string $rut): array
    {
        $raw = strtoupper(trim((string) $rut));
        $clean = preg_replace('/[^0-9K]/', '', $raw) ?: '';

        if (strlen($clean) < 2) {
            return [
                'rut' => null,
                'dv' => null,
                'normalizado' => null,
                'formateado' => null,
                'valido' => false,
            ];
        }

        $dv = substr($clean, -1);
        $body = substr($clean, 0, -1);
        $body = ltrim($body, '0') ?: '0';
        $normalizado = $body . $dv;

        return [
            'rut' => $body,
            'dv' => $dv,
            'normalizado' => $normalizado,
            'formateado' => number_format((int) $body, 0, '', '.') . '-' . $dv,
            'valido' => self::validate($body, $dv),
        ];
    }

    public static function validate(?string $body, ?string $dv): bool
    {
        $body = preg_replace('/\D/', '', (string) $body) ?: '';
        $dv = strtoupper((string) $dv);

        if ($body === '' || $dv === '') return false;

        $sum = 0;
        $factor = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += ((int) $body[$i]) * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }

        $expected = 11 - ($sum % 11);
        $expectedDv = $expected === 11 ? '0' : ($expected === 10 ? 'K' : (string) $expected);

        return $expectedDv === $dv;
    }
}
