<?php

namespace App\Services\Funcionarios;

use App\Services\LicenciasMedicas\RutNormalizer;

final class ConsultaRut
{
    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^[0-9]{1,8}[0-9Kk]$/D', $value)) {
            return null;
        }
        $rut = RutNormalizer::normalize($value);
        return $rut['valido'] && $rut['rut'] !== '0' ? $rut['normalizado'] : null;
    }
}
