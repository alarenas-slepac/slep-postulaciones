<?php

namespace App\Services\Remuneraciones;

use App\Models\DescuentoCgr;
use App\Models\UtmValor;
use Carbon\CarbonImmutable;

class CronogramaDescuentoCgrService
{
    public function calcular(DescuentoCgr $descuento): array
    {
        $inicio = CarbonImmutable::parse($descuento->fecha_primer_descuento)->startOfMonth();
        $numeroCuotas = (int) $descuento->numero_cuotas;
        $periodos = collect(range(0, max(0, $numeroCuotas - 1)))
            ->map(fn (int $offset) => $inicio->addMonthsNoOverflow($offset));

        $utmPorPeriodo = UtmValor::query()
            ->where(function ($query) use ($periodos) {
                foreach ($periodos as $periodo) {
                    $query->orWhere(fn ($subquery) => $subquery
                        ->where('anio', $periodo->year)
                        ->where('mes', $periodo->month));
                }
            })
            ->get()
            ->keyBy(fn (UtmValor $utm) => sprintf('%04d-%02d', $utm->anio, $utm->mes));

        $saldoUtm = round((float) $descuento->deuda_equivalente_utm, 4);
        $cuotaUtm = round((float) $descuento->cuota_utm, 4);
        $tasaMensual = (float) $descuento->tasa_interes_mensual / 100;
        $filas = [];
        $totales = ['capital_pesos' => 0.0, 'interes_pesos' => 0.0, 'descuento_pesos' => 0.0];

        foreach ($periodos as $indice => $periodo) {
            $saldoInicialUtm = $saldoUtm;
            $capitalUtm = round(min($cuotaUtm, $saldoInicialUtm), 4);
            $saldoUtm = round(max(0, $saldoInicialUtm - $capitalUtm), 4);
            $utm = $utmPorPeriodo->get($periodo->format('Y-m'));
            $valorUtm = $utm ? (float) $utm->valor : null;

            $saldoInicialPesos = $valorUtm === null ? null : round($saldoInicialUtm * $valorUtm, 6);
            $capitalPesos = $valorUtm === null ? null : round($capitalUtm * $valorUtm, 6);
            $interesPesos = $saldoInicialPesos === null ? null : round($saldoInicialPesos * $tasaMensual, 6);
            $descuentoPesos = $capitalPesos === null ? null : round($capitalPesos + $interesPesos, 6);

            if ($capitalPesos !== null) {
                $totales['capital_pesos'] += $capitalPesos;
                $totales['interes_pesos'] += $interesPesos;
                $totales['descuento_pesos'] += $descuentoPesos;
            }

            $filas[] = [
                'numero' => $indice + 1,
                'periodo' => $periodo,
                'valor_utm' => $valorUtm,
                'saldo_inicial_utm' => $saldoInicialUtm,
                'capital_utm' => $capitalUtm,
                'saldo_final_utm' => $saldoUtm,
                'saldo_inicial_pesos' => $saldoInicialPesos,
                'capital_pesos' => $capitalPesos,
                'interes_pesos' => $interesPesos,
                'descuento_pesos' => $descuentoPesos,
                'pendiente_utm' => $valorUtm === null,
            ];
        }

        return [
            'filas' => $filas,
            'totales' => array_map(fn (float $valor) => round($valor, 6), $totales),
            'saldo_final_utm' => $saldoUtm,
            'utm_faltantes' => collect($filas)
                ->where('pendiente_utm', true)
                ->pluck('periodo')
                ->map(fn (CarbonImmutable $periodo) => $periodo->format('m-Y'))
                ->values()
                ->all(),
        ];
    }
}
