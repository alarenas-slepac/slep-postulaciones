<?php

namespace App\Support;

use Illuminate\Support\Str;

class MaeColumnNormalizer
{
    public static function normalizeHeader(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '[[SIN ENCABEZADO]]';
        }

        return (string) Str::of($value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/u', ' ')
            ->trim();
    }

    public static function normalizeDomain(?string $value): string
    {
        $normalized = self::normalizeHeader($value);

        if (in_array($normalized, ['P01', 'ADMINISTRACION CENTRAL'], true)) {
            return 'ADMINISTRACION CENTRAL';
        }

        return $normalized;
    }

    public static function isAportePatronal(?string $header): bool
    {
        $normalized = self::normalizeHeader($header);

        if (str_contains($normalized, 'EMPLEADOR')) {
            return true;
        }

        if (str_contains($normalized, 'AP FONDO RETIRO')
            || str_contains($normalized, 'AP. FONDO RETIRO')
            || str_contains($normalized, 'APORTE FONDO RETIRO')
            || str_contains($normalized, 'APORTE PATRONAL FONDO RETIRO')) {
            return true;
        }

        return (bool) preg_match('/(^| )EMP($| )/', $normalized);
    }

    public static function legalDiscountAliases(string $field): array
    {
        return match ($field) {
            'imposiciones' => [
                'IMPOSICIONES',
                'IMPOSICION',
                'PREVISION',
                'COTIZACION PREVISIONAL',
                'COTIZACION AFP',
                'AFP',
            ],
            'salud' => [
                'SALUD',
                'DESCUENTO SALUD',
                'COTIZACION SALUD',
                'PLAN SALUD',
                'SALUD LEGAL',
                'SALUD 7',
                '7 SALUD',
            ],
            'impuesto' => [
                'IMPUESTO',
                'IMPUESTO UNICO',
                'IMPUESTO A LA RENTA',
                'IMPUESTO RENTA',
                'IMPTO UNICO',
            ],
            default => [],
        };
    }

    public static function findLegalDiscountIndex(array $normalizedHeaders, string $field, int $startIndex = 0, ?int $endExclusive = null): ?int
    {
        $aliases = self::legalDiscountAliases($field);
        $startIndex = max(0, $startIndex);
        $endExclusive = $endExclusive ?? count($normalizedHeaders);
        $endExclusive = min($endExclusive, count($normalizedHeaders));

        for ($index = $startIndex; $index < $endExclusive; $index++) {
            $header = $normalizedHeaders[$index] ?? '';
            if (in_array($header, $aliases, true)) {
                return $index;
            }
        }

        for ($index = $startIndex; $index < $endExclusive; $index++) {
            $header = $normalizedHeaders[$index] ?? '';
            if (self::matchesLegalDiscountHeader($header, $field)) {
                return $index;
            }
        }

        return null;
    }

    public static function findLegalDiscountValueInRawRow(array $rawRow, string $field): mixed
    {
        $fallback = null;

        foreach ($rawRow as $key => $value) {
            $normalized = self::normalizeHeader((string) $key);
            if (!in_array($normalized, self::legalDiscountAliases($field), true) && !self::matchesLegalDiscountHeader($normalized, $field)) {
                continue;
            }

            $decimal = self::decimalValue($value);
            if ($decimal !== null && abs($decimal) > 0.000001) {
                return $value;
            }

            if ($fallback === null && $decimal !== null) {
                $fallback = $value;
            }
        }

        return $fallback;
    }

    public static function matchesLegalDiscountHeader(string $normalizedHeader, string $field): bool
    {
        if ($normalizedHeader === '' || $normalizedHeader === '[[SIN ENCABEZADO]]') {
            return false;
        }

        return match ($field) {
            'imposiciones' =>
                str_contains($normalizedHeader, 'IMPOSICION')
                || str_contains($normalizedHeader, 'PREVISION')
                || preg_match('/(^| )AFP($| )/', $normalizedHeader) === 1,
            'salud' => (
                str_contains($normalizedHeader, 'SALUD')
                || str_contains($normalizedHeader, 'ISAPRE')
                || str_contains($normalizedHeader, 'FONASA')
            ) && !str_contains($normalizedHeader, 'SALUD COMPLEMENTARIA'),
            'impuesto' => str_contains($normalizedHeader, 'IMPUESTO'),
            default => false,
        };
    }



    public static function inferDiscountMetadata(?string $headerOriginal, ?string $normalizedHeader = null): array
    {
        $headerOriginal = trim((string) $headerOriginal);
        $normalized = $normalizedHeader ?: self::normalizeHeader($headerOriginal);
        $isPatronal = self::isAportePatronal($headerOriginal);

        $contains = static fn (string ...$needles): bool => collect($needles)
            ->contains(fn ($needle) => str_contains($normalized, self::normalizeHeader($needle)));

        $subgrupo = 'otros';
        $grupo = 'descuento';
        $tipoMovimiento = 'descuento';

        if ($isPatronal) {
            $grupo = 'aporte_patronal';
            $subgrupo = 'empleador';
            $tipoMovimiento = 'aporte_patronal';
        } elseif ($contains('ret judicial', 'judicial', 'pension alimenticia')) {
            $subgrupo = 'judicial';
        } elseif ($contains('cgr', 'contraloria', 'rex', 'res exenta', 'resolucion exenta', 'dscto rex', 'dsto rex', 'dsto cgr rex', 'dscto cgr rex')) {
            $subgrupo = 'administrativo';
        } elseif ($contains('reintegro', 'reint')) {
            $subgrupo = 'reintegro';
        } elseif ($contains('imposicion', 'prevision', 'cotizacion afp') || preg_match('/(^| )AFP($| )/', $normalized) === 1) {
            $grupo = 'descuentos_legales';
            $subgrupo = 'imposiciones';
        } elseif ($contains('impuesto')) {
            $grupo = 'descuentos_legales';
            $subgrupo = 'impuesto';
        } elseif (($contains('salud', 'isapre', 'fonasa')) && !$contains('salud complementaria')) {
            $grupo = 'descuentos_legales';
            $subgrupo = 'salud';
        } elseif ($contains('cesantia')) {
            $grupo = 'descuentos_legales';
            $subgrupo = 'cesantia';
        } elseif ($contains('salud complementaria', 'emerg medicas', 'odontologia')) {
            $subgrupo = 'salud_complementaria';
        } elseif ($contains('apv')) {
            $subgrupo = 'apv';
        } elseif ($contains('bienestar')) {
            $subgrupo = 'bienestar';
        } elseif ($contains('sindicato', 'colegio de profesores', 'asoc', 'asociacion', 'afe', 'afpae', 'sute')) {
            $subgrupo = 'gremial';
        } elseif ($contains('ahorro', 'acciones')) {
            $subgrupo = 'ahorro';
        } elseif ($contains('credito', 'creditos', 'prestamo', 'prest', 'cuota', 'coopeuch', 'oriencoop', 'caja 18', 'los andes', 'banco', 'falabella', 'scotia', 'ripley')) {
            $subgrupo = 'credito';
        } elseif ($contains('seguro')) {
            $subgrupo = 'seguro';
        } elseif ($contains('anticipo')) {
            $subgrupo = 'anticipo';
        }

        $campoCanonico = self::canonicalFieldName($normalized, 'descuento_no_homologado');

        return [
            'campo_canonico' => $campoCanonico,
            'grupo' => $grupo,
            'subgrupo' => $subgrupo,
            'tipo_movimiento' => $tipoMovimiento,
            'es_aporte_patronal' => $isPatronal,
        ];
    }

    public static function canonicalFieldName(?string $normalizedHeader, string $fallback = 'descuento_no_homologado'): string
    {
        $normalized = trim((string) $normalizedHeader);
        if ($normalized === '' || $normalized === '[[SIN ENCABEZADO]]') {
            return $fallback;
        }

        $value = (string) Str::of($normalized)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/u', '_')
            ->trim('_');

        return $value !== '' ? $value : $fallback;
    }

    private static function decimalValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $text = preg_replace('/[^0-9,.-]/', '', $text) ?? '';
        if ($text === '' || $text === '-' || $text === ',') {
            return null;
        }

        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif (substr_count($text, '.') > 1) {
            $text = str_replace('.', '', $text);
        } elseif (str_contains($text, ',') && !str_contains($text, '.')) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }
}
