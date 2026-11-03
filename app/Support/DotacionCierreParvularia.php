<?php

namespace App\Support;

/** Excepción operativa de cierre; la base institucional del curso se conserva. */
class DotacionCierreParvularia
{
    public const MAXIMO_AULA = 2.0;
    public const MARCA = 'Cierre residual de plan Parvularia';

    public static function es(object|array $fila): bool
    {
        $aula = (float) data_get($fila, 'horas_plan_pedagogicas', 0);

        return (int) data_get($fila, 'anio', 0) >= 2027
            && data_get($fila, 'tipo_asignacion') === 'plan_estudio'
            && DotacionAsignacionCalculator::esAsignacionDocenteReal($fila)
            && $aula > 0 && $aula <= self::MAXIMO_AULA
            && (str_starts_with((string) data_get($fila, 'proporcion_aplicada'), 'NT Con JEC · base contractual')
                || str_starts_with((string) data_get($fila, 'proporcion_aplicada'), 'NT Sin JEC · base contractual'))
            && str_contains((string) data_get($fila, 'fuente_calculo'), self::MARCA);
    }

    public static function marcar(array $payload): array
    {
        $habitual = (float) $payload['horas_contrato'];
        $aula = round((float) $payload['horas_plan_pedagogicas'], 2);
        $payload['horas_contrato'] = $aula;
        $payload['fuente_calculo'] .= ' '.self::MARCA.': '
            .DotacionEstablecimientoCalculator::formatHoras($aula).' h aula con '
            .DotacionEstablecimientoCalculator::formatHoras($aula).' h de contrato; conversión habitual '
            .DotacionEstablecimientoCalculator::formatHoras($habitual).' h. Sin otra educadora disponible para cubrir el saldo. Se conserva la base institucional del curso.';

        return $payload;
    }
}
