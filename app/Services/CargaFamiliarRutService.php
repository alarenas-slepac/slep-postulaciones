<?php

namespace App\Services;

use App\Support\RutChile;

class CargaFamiliarRutService
{
    public function fromParts(mixed $run, mixed $dv): array
    {
        $run = preg_replace('/[^0-9]/', '', (string) $run) ?? '';
        $dv = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $dv) ?? '');

        if ($run === '' && $dv === '') {
            return [null, null, null, null, null];
        }

        if ($run !== '' && $dv !== '') {
            $rutCompleto = ltrim($run, '0') . '-' . $dv;
            $normalized = ltrim($run, '0') . $dv;
            $status = RutChile::normalize($rutCompleto)['status'] ?? null;

            return [ltrim($run, '0'), $dv, $rutCompleto, strtoupper($normalized), $status];
        }

        return $this->fromString($run . $dv);
    }

    public function fromString(mixed $value): array
    {
        $normalized = RutChile::normalize((string) $value);
        if (!$normalized) {
            return [null, null, null, null, null];
        }

        $run = (string) ($normalized['rut_body'] ?? '');
        $dv = strtoupper((string) ($normalized['rut_dv'] ?? ''));
        $rutCompleto = $run !== '' && $dv !== '' ? $run . '-' . $dv : null;
        $runNormalizado = $rutCompleto ? strtoupper($run . $dv) : null;

        return [$run ?: null, $dv ?: null, $rutCompleto, $runNormalizado, (string) ($normalized['status'] ?? '')];
    }
}
