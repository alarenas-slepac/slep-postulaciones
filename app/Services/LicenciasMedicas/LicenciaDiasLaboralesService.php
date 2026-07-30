<?php

namespace App\Services\LicenciasMedicas;

use App\Models\LicenciaFeriado;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class LicenciaDiasLaboralesService
{
    public function calcular(?string $fechaInicio, ?string $fechaTermino): array
    {
        if (!$fechaInicio || !$fechaTermino) {
            return [
                'dias_corridos' => null,
                'dias_laborales' => null,
                'feriados_descontados' => [],
            ];
        }

        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $termino = Carbon::parse($fechaTermino)->startOfDay();

        if ($termino->lt($inicio)) {
            return [
                'dias_corridos' => null,
                'dias_laborales' => null,
                'feriados_descontados' => [],
            ];
        }

        $feriados = $this->feriadosEnRango($inicio, $termino);
        $feriadosPorFecha = $feriados->keyBy(fn (LicenciaFeriado $feriado) => $feriado->fecha->format('Y-m-d'));

        $diasCorridos = 0;
        $diasLaborales = 0;
        $feriadosDescontados = [];

        foreach (CarbonPeriod::create($inicio, $termino) as $dia) {
            /** @var Carbon $dia */
            $diasCorridos++;
            $fecha = $dia->format('Y-m-d');

            if ($dia->isWeekend()) {
                continue;
            }

            if ($feriadosPorFecha->has($fecha)) {
                $feriado = $feriadosPorFecha->get($fecha);
                $feriadosDescontados[] = [
                    'fecha' => $fecha,
                    'nombre' => $feriado->nombre,
                    'tipo' => $feriado->tipo,
                ];
                continue;
            }

            $diasLaborales++;
        }

        return [
            'dias_corridos' => $diasCorridos,
            'dias_laborales' => $diasLaborales,
            'feriados_descontados' => $feriadosDescontados,
        ];
    }

    public function feriadosEnRango(Carbon $inicio, Carbon $termino): Collection
    {
        return LicenciaFeriado::query()
            ->where('activo', true)
            ->whereBetween('fecha', [$inicio->format('Y-m-d'), $termino->format('Y-m-d')])
            ->orderBy('fecha')
            ->get();
    }
}
