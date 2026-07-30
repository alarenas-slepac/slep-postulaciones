<?php

namespace App\Services\LicenciasMedicas;

class LicenciaFolio
{
    public static function build($tipo, $cuerpo, $dv): ?string
    {
        $tipo = self::normalizeTipo($tipo);
        $cuerpo = self::normalizeCuerpo($cuerpo);
        $dv = self::normalizeDv($dv);

        if ($tipo === null || $cuerpo === null || $dv === null) {
            return null;
        }

        return $tipo . '-' . $cuerpo . '-' . $dv;
    }

    public static function fromParts($tipo, $cuerpo, $dv): array
    {
        $tipo = self::normalizeTipo($tipo);
        $cuerpo = self::normalizeCuerpo($cuerpo);
        $dv = self::normalizeDv($dv);
        $folio = self::build($tipo, $cuerpo, $dv);

        return [
            'tipo_ingreso_licencia' => $tipo,
            'cuerpo_licencia' => $cuerpo,
            'dv_licencia' => $dv,
            'folio_licencia' => $folio,
            'valido' => $folio !== null,
        ];
    }

    public static function split(?string $folio): array
    {
        $folio = strtoupper(trim((string) $folio));

        if (preg_match('/\b([1-4])\s*[- ]\s*(\d{5,12})\s*[- ]\s*([0-9K])\b/u', $folio, $m)) {
            return [
                'tipo_ingreso_licencia' => $m[1],
                'cuerpo_licencia' => ltrim($m[2], '0') ?: '0',
                'dv_licencia' => $m[3],
                'folio_licencia' => self::build($m[1], $m[2], $m[3]),
            ];
        }

        if (preg_match('/\b(\d{5,12})\s*[- ]\s*([0-9K])\b/u', $folio, $m)) {
            return [
                'tipo_ingreso_licencia' => null,
                'cuerpo_licencia' => ltrim($m[1], '0') ?: '0',
                'dv_licencia' => $m[2],
                'folio_licencia' => null,
            ];
        }

        return [
            'tipo_ingreso_licencia' => null,
            'cuerpo_licencia' => null,
            'dv_licencia' => null,
            'folio_licencia' => null,
        ];
    }

    public static function normalizeTipo($value): ?string
    {
        $value = preg_replace('/\D/', '', trim((string) $value));
        return in_array($value, ['1', '2', '3', '4'], true) ? $value : null;
    }

    public static function normalizeCuerpo($value): ?string
    {
        $value = preg_replace('/\D/', '', trim((string) $value));
        if ($value === '') {
            return null;
        }

        $value = ltrim($value, '0');
        return $value === '' ? '0' : $value;
    }

    public static function normalizeDv($value): ?string
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/[^0-9K]/', '', $value);

        return preg_match('/^[0-9K]$/', $value) === 1 ? $value : null;
    }
}
