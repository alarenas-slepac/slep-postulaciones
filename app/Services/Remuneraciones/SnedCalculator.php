<?php

namespace App\Services\Remuneraciones;

use App\Models\SnedAporte;
use App\Models\SnedCuota;
use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class SnedCalculator
{
    // Múltiplo común de las duraciones de los meses. Permite comparar horas-día sin redondear.
    public const BASE_DIAS = 377580;

    public function calcular(SnedCuota $cuota, SnedAporte $aporte, Collection $cargas): array
    {
        $contratos = $this->tramos($aporte, $cuota, $cargas);
        $corte = max($cuota->meses());
        $grupos = ['docentes' => [], 'aaee' => []];
        $fueraDelCorte = [];
        $errores = [];

        foreach ($contratos as $estamento => $personas) {
            foreach ($personas as $rut => $tramos) {
                $tramosCorte = array_filter($tramos, fn ($tramo) => $tramo['mes'] === $corte);
                if (! $tramosCorte) {
                    $fueraDelCorte[$estamento][] = ['rut' => $rut, 'nombre' => end($tramos)['nombre']];
                    continue;
                }
                $ultimoDia = max(array_column($tramosCorte, 'hasta'));
                $minutosCorte = array_sum(array_column(
                    array_filter($tramosCorte, fn ($tramo) => $tramo['hasta'] === $ultimoDia),
                    'minutos'
                ));
                $pesoDias = array_sum(array_column($tramos, 'peso'));
                $grupos[$estamento][$rut] = [
                    'rut' => $rut, 'nombre' => end($tramosCorte)['nombre'], 'estamento' => $estamento,
                    'minutos_corte' => $minutosCorte, 'peso_dias' => $pesoDias, 'tramos' => $tramos,
                    'peso' => $cuota->metodo === 'jornada_dias' ? $pesoDias : $minutosCorte,
                    'incentivo' => 0,
                ];
            }
        }

        $fondos = ['docentes' => $aporte->docentes - $aporte->fondoIncentivo(), 'aaee' => $aporte->aaee];
        foreach ($fondos as $estamento => $fondo) {
            if ($fondo > 0 && ! $grupos[$estamento]) {
                $errores[] = 'El aporte de '.($estamento === 'aaee' ? 'AAEE' : 'docentes').' no tiene personas elegibles en el mes de corte.';
            }
            $pagos = $this->repartir($fondo, array_column($grupos[$estamento], 'peso', 'rut'));
            foreach ($grupos[$estamento] as $rut => &$persona) {
                $persona['general'] = $pagos[$rut] ?? 0;
            }
            unset($persona);
        }

        $incentivo = $this->incentivo($aporte, $grupos['docentes'], $errores);
        foreach ($incentivo as $rut => $monto) {
            $grupos['docentes'][$rut]['incentivo'] = $monto;
        }

        $personas = array_merge(array_values($grupos['docentes']), array_values($grupos['aaee']));
        usort($personas, fn ($a, $b) => [$a['estamento'] === 'aaee', $a['nombre'], $a['rut']] <=> [$b['estamento'] === 'aaee', $b['nombre'], $b['rut']]);
        $pagos = [
            'docentes' => array_sum(array_column($grupos['docentes'], 'general')),
            'incentivo' => array_sum($incentivo),
            'aaee' => array_sum(array_column($grupos['aaee'], 'general')),
        ];

        return [
            'rbd' => $aporte->rbd, 'establecimiento' => $aporte->establecimiento,
            'fondos' => $fondos + ['incentivo' => $aporte->fondoIncentivo()],
            'pagos' => $pagos, 'total' => array_sum($pagos), 'personas' => $personas,
            'fuera_del_corte' => $fueraDelCorte, 'errores' => $errores,
        ];
    }

    private function tramos(SnedAporte $aporte, SnedCuota $cuota, Collection $cargas): array
    {
        $tramos = [];
        foreach ($cargas->sortKeys() as $mes => $carga) {
            $inicioMes = CarbonImmutable::create($cuota->anio, $mes, 1);
            $finMes = $inicioMes->endOfMonth();
            foreach ($carga->lectura['contratos'][$aporte->rbd] ?? [] as $contrato) {
                $desde = max($contrato['inicio'], $inicioMes->toDateString());
                $hasta = min($contrato['termino'] ?? $finMes->toDateString(), $finMes->toDateString());
                if ($desde > $hasta || $contrato['minutos'] <= 0) {
                    continue;
                }
                $dias = (int) CarbonImmutable::parse($desde)->diffInDays(CarbonImmutable::parse($hasta)) + 1;
                $tramos[$contrato['estamento']][$contrato['rut']][] = [
                    'mes' => $mes, 'carga_id' => $carga->id, 'desde' => $desde, 'hasta' => $hasta,
                    'dias' => $dias, 'dias_mes' => $inicioMes->daysInMonth,
                    'peso' => $contrato['minutos'] * $dias * intdiv(self::BASE_DIAS, $inicioMes->daysInMonth),
                ] + $contrato;
            }
        }

        return $tramos;
    }

    private function incentivo(SnedAporte $aporte, array $docentes, array &$errores): array
    {
        $fondo = $aporte->fondoIncentivo();
        if ($fondo === 0) {
            return [];
        }
        $instruccion = $aporte->incentivo;
        if (empty($instruccion['ruts'])) {
            $errores[] = 'Falta registrar la instrucción de reparto del incentivo docente del 10%.';

            return [];
        }
        if (array_diff($instruccion['ruts'], array_keys($docentes))
            || ($instruccion['alcance'] === 'todos' && count($instruccion['ruts']) !== count($docentes))) {
            $errores[] = 'La carga vigente cambió los docentes elegibles. Revise la selección del incentivo.';

            return [];
        }
        if ($instruccion['modo'] === 'iguales') {
            return $this->repartir($fondo, array_fill_keys($instruccion['ruts'], 1));
        }

        $montos = array_intersect_key($instruccion['montos'], array_flip($instruccion['ruts']));
        if (count($montos) !== count($instruccion['ruts']) || array_sum($montos) !== $fondo) {
            $errores[] = 'Los montos individuales deben sumar el 10% del aporte bruto docente.';

            return [];
        }

        return $montos;
    }

    private function repartir(int $monto, array $pesos): array
    {
        if (! $pesos) {
            return [];
        }
        $denominador = array_sum($pesos);
        $base = [];
        $restos = [];
        foreach ($pesos as $rut => $peso) {
            [$entero, $residuo] = BigInteger::of($monto)->multipliedBy($peso)->quotientAndRemainder($denominador);
            $base[$rut] = $entero->toInt();
            $restos[$rut] = $residuo->toInt();
        }
        $orden = array_keys($pesos);
        usort($orden, fn ($a, $b) => ($restos[$b] <=> $restos[$a]) ?: strcmp($a, $b));
        foreach (array_slice($orden, 0, $monto - array_sum($base)) as $rut) {
            $base[$rut]++;
        }

        return $base;
    }
}
