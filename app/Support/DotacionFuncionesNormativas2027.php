<?php

namespace App\Support;

use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DotacionFuncionesNormativas2027
{
    // Conserva el mapa histórico necesidad => booleano. Las horas se guardan
    // aparte, dentro del mismo JSON, por código estable del catálogo.
    public const HORAS_KEY = '_horas';

    public static function horasConfiguradas(Establecimiento $establecimiento, int $anio): array
    {
        if (! DotacionProceso2027Calculator::aplica($anio)
            || ! Schema::hasColumn('dotacion_proceso_2027_configuraciones', 'funciones_normativas')) {
            return [];
        }

        $config = DotacionProceso2027Configuracion::query()
            ->where('establecimiento_id', $establecimiento->id)->where('anio', $anio)->first();
        $horas = ($config?->funciones_normativas ?? [])[self::HORAS_KEY] ?? [];

        return is_array($horas) ? $horas : [];
    }

    public static function horasDefinidas(string $codigo, float $calculadas, array $configuradas): float
    {
        $horas = $configuradas[$codigo] ?? $calculadas;

        return round(min($calculadas, max(0.0, is_numeric($horas) ? (float) $horas : $calculadas)), 2);
    }

    public static function definicion(Collection $potenciales, array $solicitadas): array
    {
        $potenciales = $potenciales->keyBy('key');
        $seleccionadas = [];
        $horas = [];
        foreach ($solicitadas as $index => $solicitada) {
            $key = (string) ($solicitada['key'] ?? '');
            $funcion = $potenciales->get($key);
            if (! $funcion) {
                throw ValidationException::withMessages([
                    "funciones_normativas.$index.key" => 'La función ya no está disponible. Actualice la página.',
                ]);
            }
            $maximo = (float) ($funcion['horas_potenciales'] ?? $funcion['horas']);
            $valor = $solicitada['horas'] ?? $funcion['horas'];
            if (! is_numeric($valor) || (float) $valor < 0 || (float) $valor > $maximo) {
                throw ValidationException::withMessages([
                    "funciones_normativas.$index.horas" => 'Las horas de '.$funcion['titulo'].' deben estar entre 0 y '
                        .DotacionEstablecimientoCalculator::formatHoras($maximo).' h calculadas.',
                ]);
            }
            $seleccionadas[$key] = (bool) ($solicitada['usar'] ?? false);
            $horas[(string) ($funcion['codigo'] ?? $key)] = round((float) $valor, 2);
        }

        $definicion = $potenciales->mapWithKeys(fn ($funcion, $key) => [$key => $seleccionadas[$key] ?? false])->all();
        $definicion[self::HORAS_KEY] = $horas;

        return $definicion;
    }

    public static function validarAsignacion(array $necesidad, float $horas, ?int $asignacionActual = null): void
    {
        $asignadas = (float) collect($necesidad['asignaciones'] ?? [])
            ->reject(fn ($row) => (bool) data_get($row, 'asignacion_automatica', false)
                || ($asignacionActual !== null && (int) data_get($row, 'id') === $asignacionActual))
            ->sum(fn ($row) => (float) data_get($row, 'horas_contrato', 0));
        $limite = (float) ($necesidad['horas_contrato_requeridas'] ?? 0);
        if (round($asignadas + $horas, 2) > $limite + 0.001) {
            throw ValidationException::withMessages([
                'horas_contrato' => 'La asignación supera las '.DotacionEstablecimientoCalculator::formatHoras($limite)
                    .' h definidas para '.$necesidad['titulo'].'. Ya hay '
                    .DotacionEstablecimientoCalculator::formatHoras($asignadas).' h asignadas.',
            ]);
        }
    }
}
