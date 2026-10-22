<?php

namespace App\Support\Votaciones;

use App\Models\JornadaVotacion;
use App\Models\ResultadoDiarioVotacion;
use Illuminate\Support\Collection;

final class PapeletasVotacion
{
    public static function para(JornadaVotacion $jornada, ?ResultadoDiarioVotacion $dia = null): array
    {
        if ($dia) {
            return self::deDias(collect([$dia]));
        }
        $codigos = $jornada->procesos->pluck('codigo')->all();
        $papeletas = array_filter(config('votaciones.papeletas'), fn (array $p) => in_array($p['proceso'], $codigos, true));

        return self::conCampos($papeletas);
    }

    public static function deDias(Collection $dias): array
    {
        $claves = $dias->flatMap(fn ($dia) => array_keys($dia->conteos))->unique()->all();

        return self::conCampos(array_intersect_key(config('votaciones.papeletas'), array_flip($claves)));
    }

    private static function conCampos(array $papeletas): array
    {
        foreach ($papeletas as &$papeleta) {
            $papeleta['campos'] = $papeleta['opciones'] + ['nulos' => 'Nulos', 'blancos' => 'Blancos'];
        }

        return $papeletas;
    }

    /** Totales por papeleta: nunca sumar papeletas diferentes como personas. */
    public static function resumir(array $papeletas, Collection $dias): array
    {
        $resumen = [];
        foreach ($papeletas as $clave => $papeleta) {
            $conteos = array_fill_keys(array_keys($papeleta['campos']), 0);
            foreach ($dias as $dia) {
                foreach ($conteos as $opcion => &$cantidad) {
                    $cantidad += (int) ($dia->conteos[$clave][$opcion] ?? 0);
                }
                unset($cantidad);
            }
            $validos = array_sum(array_intersect_key($conteos, $papeleta['opciones']));
            $resumen[$clave] = array_replace($papeleta, ['conteos' => $conteos, 'validos' => $validos, 'emitidos' => array_sum($conteos)]);
        }

        return $resumen;
    }
}
