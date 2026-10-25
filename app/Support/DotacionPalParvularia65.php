<?php

namespace App\Support;

/** Invierte el BUSCARV efectivo del PAL, conservando exactamente el contrato. */
final class DotacionPalParvularia65
{
    /** @return list<array{aula: float, contrato: float}>|null */
    public static function convertir(float $contrato, array $tabla): ?array
    {
        if (! is_finite($contrato) || $contrato <= 0 || $contrato > 10000) { return null; }
        $objetivo = (int) round($contrato * 100);
        if (abs($objetivo / 100 - $contrato) > 0.00001) { return null; }
        $opciones = [];
        foreach ($tabla as $aula => $regla) {
            $aula = (float) $aula; $valor = (float) ($regla['contrato'] ?? 0);
            if ($aula <= 0 || $valor <= 0 || ! is_finite($aula) || ! is_finite($valor)) { continue; }
            $centesimas = (int) round($valor * 100);
            if (abs($centesimas / 100 - $valor) > 0.00001) { continue; }
            $opciones[$centesimas] ??= ['aula' => $aula, 'contrato' => $valor];
        }
        if (isset($opciones[$objetivo])) { return [$opciones[$objetivo]]; }
        if ($opciones === []) { return null; }
        // El contrato de una fila puede exceder 44 h o no tener una coincidencia
        // directa por aulas repetidas. Se usa el menor número de filas exactas.
        // Normaliza al paso de la tabla; no redondea horas que ésta no admite.
        $divisor = 0;
        foreach (array_keys($opciones) as $valor) {
            while ($valor !== 0) { [$divisor, $valor] = [$valor, $divisor % $valor]; }
        }
        if ($divisor === 0 || $objetivo % $divisor !== 0) { return null; }
        $objetivo = intdiv($objetivo, $divisor);
        krsort($opciones);
        $mejor = array_fill(0, $objetivo + 1, PHP_INT_MAX); $previa = []; $mejor[0] = 0;
        for ($total = 1; $total <= $objetivo; $total++) {
            foreach ($opciones as $valor => $_opcion) {
                $paso = intdiv($valor, $divisor);
                if ($paso <= $total && $mejor[$total - $paso] !== PHP_INT_MAX && $mejor[$total - $paso] + 1 < $mejor[$total]) {
                    $mejor[$total] = $mejor[$total - $paso] + 1; $previa[$total] = $valor;
                }
            }
        }
        if ($mejor[$objetivo] === PHP_INT_MAX) { return null; }
        $bloques = [];
        while ($objetivo > 0) {
            $valor = $previa[$objetivo]; $bloques[] = $opciones[$valor]; $objetivo -= intdiv($valor, $divisor);
        }
        return $bloques;
    }
}
