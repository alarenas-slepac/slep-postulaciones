<?php

namespace App\Support;

class RutChile
{
    public static function normalize(?string $value): ?array
    {
        $raw = trim((string) $value);
        if ($raw === '') return null;

        // fixes típicos (OCR / data sucia)
        $s = strtoupper($raw);
        $s = str_replace(['.', ' ', '´', '’', '`'], '', $s);
        $s = str_replace(['_', '–', '—'], '-', $s);
        $s = str_replace(['O'], '0', $s); // O -> 0 (cuando viene mal digitado)

        // deja solo dígitos, K y guion
        $s = preg_replace('/[^0-9K\-]/', '', $s) ?? '';
        if ($s === '') return null;

        // Caso con guion
        if (str_contains($s, '-')) {
            $parts = array_values(array_filter(explode('-', $s), fn($p) => $p !== ''));
            if (count($parts) < 2) return null;
            $dv = array_pop($parts);
            $body = implode('', $parts);
        } else {
            // Caso sin guion: asumimos último char como DV
            if (strlen($s) < 2) return null;
            $body = substr($s, 0, -1);
            $dv   = substr($s, -1);
        }

        if (!ctype_digit($body)) {
            // si venía TODO numérico y sin guion, puede ser "solo cuerpo" (sin DV)
            if (ctype_digit($s) && strlen($s) >= 7) {
                $rutBody = ltrim($s, '0');
                $rutDv = self::dv((int) $rutBody);
                return [
                    'rut' => $rutBody . '-' . $rutDv,
                    'rut_body' => $rutBody,
                    'rut_dv' => $rutDv,
                    'status' => 'computed_dv',
                ];
            }
            return null;
        }

        $rutBody = (string) (int) $body; // normaliza ceros a la izquierda
        $dv = strtoupper($dv);

        // valida DV, si no calza y el input era “solo cuerpo”
        $calc = self::dv((int) $rutBody);
        if ($dv !== $calc) {
            if (!str_contains($s, '-') && ctype_digit($s) && strlen($s) >= 7) {
                $rutBody2 = (string) (int) $s;
                $rutDv2 = self::dv((int) $rutBody2);
                return [
                    'rut' => $rutBody2 . '-' . $rutDv2,
                    'rut_body' => $rutBody2,
                    'rut_dv' => $rutDv2,
                    'status' => 'computed_dv',
                ];
            }

            // DV incorrecto -> igual devolvemos, pero marcado
            return [
                'rut' => $rutBody . '-' . $dv,
                'rut_body' => $rutBody,
                'rut_dv' => $dv,
                'status' => 'invalid_dv',
            ];
        }

        return [
            'rut' => $rutBody . '-' . $dv,
            'rut_body' => $rutBody,
            'rut_dv' => $dv,
            'status' => 'ok',
        ];
    }

    public static function dv(int $body): string
    {
        $s = 0;
        $m = 2;
        while ($body > 0) {
            $s += ($body % 10) * $m;
            $body = intdiv($body, 10);
            $m = ($m === 7) ? 2 : $m + 1;
        }
        $r = 11 - ($s % 11);
        if ($r === 11) return '0';
        if ($r === 10) return 'K';
        return (string) $r;
    }

    /**
     * Heurística: materno = última palabra, paterno = resto.
     */
    public static function splitApellidos(?string $apellidos): array
    {
        $a = trim(preg_replace('/\s+/', ' ', (string) $apellidos));
        if ($a === '') return [null, null];

        $parts = explode(' ', $a);
        if (count($parts) === 1) return [$a, null];

        $materno = array_pop($parts);
        $paterno = implode(' ', $parts);

        return [$paterno ?: null, $materno ?: null];
    }
}
