<?php

namespace App\Services\Maternidad;

use Carbon\CarbonImmutable;

final class MaternidadFechas
{
    /** Calcula sólo con respaldos vigentes; las fechas informadas sin documento siguen pendientes. */
    public function calcular(array $datos, array $documentos): array
    {
        $sumar = fn (?string $fecha) => $fecha ? CarbonImmutable::parse($fecha)->addYearNoOverflow()->addDays(84)->toDateString() : null;
        $estimado = $sumar($datos['fpp'] ?? null);
        $nacimiento = in_array('nacimiento', $documentos, true) ? $sumar($datos['fnh'] ?? null) : null;
        $dominga = null;
        if (($datos['ley_dominga'] ?? false) && in_array('ley_dominga', $documentos, true) && ! empty($datos['fecha_ley_dominga'])) {
            $dominga = ($datos['regla_fuero_dominga'] ?? config('maternidad.regla_fuero_dominga')) === 'anio_84_dias'
                ? $sumar($datos['fecha_ley_dominga'])
                : CarbonImmutable::parse($datos['fecha_ley_dominga'])->addDays(30)->toDateString();
        }
        $finLactancia = ($datos['lactancia'] ?? false) && in_array('lactancia', $documentos, true)
            && in_array('nacimiento', $documentos, true) && ! empty($datos['fnh'])
            ? CarbonImmutable::parse($datos['fnh'])->addYearsNoOverflow(2)->toDateString() : null;

        return ['fuero_estimado' => $estimado, 'fuero_nacimiento' => $nacimiento, 'fuero_dominga' => $dominga,
            'fecha_termino_fuero' => $dominga ?? $nacimiento ?? $estimado,
            'base_fuero' => $dominga ? 'ley_dominga' : ($nacimiento ? 'nacimiento_certificado' : ($estimado ? 'parto_probable' : null)),
            'fecha_termino_lactancia' => $finLactancia];
    }
}
