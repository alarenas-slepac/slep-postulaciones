<?php

namespace App\Support;

use App\Models\Establecimiento;

class DotacionDocenteGlosa
{
    public const HORAS = 1199;

    public const JORNADA = 44;

    public function criterio(Establecimiento $establecimiento, array $datos, float $horasDotacion): array
    {
        $proceso = DotacionProceso2027Calculator::resumen($establecimiento, DotacionProceso2027Calculator::ANIO, $datos);
        $maximos = array_column($proceso['bloques'], 'maximo');
        $maximo = in_array(null, $maximos, true) ? null : round(array_sum($maximos), 2);

        return $this->recalcularBrecha([
            'rbd' => (string) $establecimiento->rbd,
            'maximo' => $maximo,
            'justificadas' => (float) $proceso['justificaciones']['horas_justificadas'],
        ], $horasDotacion);
    }

    public function recalcularBrecha(array $criterio, float $horasDotacion): array
    {
        $brecha = $criterio['maximo'] === null ? null : round($criterio['maximo'] - $horasDotacion, 2);
        $criterio['brecha'] = $brecha;
        $criterio['saldo'] = $brecha === null ? null : round($brecha - $criterio['justificadas'], 2);

        return $criterio;
    }

    public function distribuir(array $establecimientos): array
    {
        $jornadas = array_fill(0, intdiv(self::HORAS, self::JORNADA), self::JORNADA);
        $jornadas[] = self::HORAS % self::JORNADA;
        $distribuidas = 0;

        foreach ($establecimientos as &$establecimiento) {
            $establecimiento['horas_glosa'] = 0;
        }
        unset($establecimiento);

        foreach ($jornadas as $horas) {
            // Prioriza el saldo que queda después de justificar y de asignar glosa.
            uasort($establecimientos, function (array $a, array $b): int {
                $saldoA = (float) $a['saldo'] - $a['horas_glosa'];
                $saldoB = (float) $b['saldo'] - $b['horas_glosa'];

                return $saldoB <=> $saldoA ?: ($a['rbd'] <=> $b['rbd']);
            });
            $id = array_key_first($establecimientos);
            if ($id === null || (float) $establecimientos[$id]['saldo'] - $establecimientos[$id]['horas_glosa'] < $horas) {
                continue;
            }
            $establecimientos[$id]['horas_glosa'] += $horas;
            $distribuidas += $horas;
        }

        return ['establecimientos' => $establecimientos, 'horas_distribuidas' => $distribuidas];
    }
}
