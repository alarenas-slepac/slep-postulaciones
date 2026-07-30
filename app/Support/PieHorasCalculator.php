<?php

namespace App\Support;

use App\Models\EstablecimientoCurso;
use App\Models\PieHorasApoyoMinimo;

class PieHorasCalculator
{
    public static function calculate(EstablecimientoCurso $curso, int $neet, int $neep): array
    {
        $curso->loadMissing(['curso', 'planEstudio']);

        $regimen = self::determineRegimen($curso);
        $isEpja = self::isEpja($curso);
        $observaciones = [];

        if ($isEpja) {
            $observaciones[] = 'EPJA aplica regla SIN JEC.';
        }

        if ($neet > 0 && $neet < 5) {
            $observaciones[] = 'NEET menor a 5 calculado como mínimo 5.';
        }

        if ($neet === 0 && $neep === 0) {
            return self::payload($regimen, 0, $neep, 0, 0, 0, $observaciones ?: ['Sin estudiantes PIE registrados.']);
        }

        if ($neep > 31) {
            $observaciones[] = 'Cantidad NEEP excede tabla de referencia vigente (máximo 31); cálculo automático no aplicado.';
            return self::payload($regimen, $neet > 0 ? 5 : 0, $neep, null, null, null, $observaciones);
        }

        if ($neet === 0 && $neep > 0) {
            $total = $neep * 180;
            $observaciones[] = 'Sin NEET registrados: se calculan sólo horas NEEP, asignadas a Profesional Educador Diferencial.';
            return self::payload($regimen, 0, $neep, $total, $total, 0, $observaciones);
        }

        if ($neep === 0) {
            if ($neet > 0) {
                if ($regimen === 'sin_jec') {
                    $observaciones[] = 'Sin estudiantes NEEP: regla SIN JEC asigna 07:00 horas totales, distribuidas en 04:30 para Profesional Educador/a Diferencial y 02:30 para PAEC.';
                    return self::payload($regimen, 5, 0, 420, 270, 150, $observaciones);
                }

                $observaciones[] = 'Sin estudiantes NEEP: regla CON JEC asigna 10:00 horas totales, distribuidas en 06:00 para Profesional Educador/a Diferencial y 04:00 para PAEC.';
                return self::payload($regimen, 5, 0, 600, 360, 240, $observaciones);
            }

            return self::payload($regimen, 0, 0, 0, 0, 0, $observaciones ?: ['Sin estudiantes PIE registrados.']);
        }

        $rule = PieHorasApoyoMinimo::query()
            ->where('regimen_jec', $regimen)
            ->where('neep_cantidad', $neep)
            ->where('vigente', true)
            ->first();

        if (! $rule) {
            $observaciones[] = 'No existe regla de catálogo para el régimen y cantidad NEEP indicada.';
            return self::payload($regimen, 5, $neep, null, null, null, $observaciones);
        }

        return self::payload(
            $regimen,
            5,
            $neep,
            (int) $rule->total_crono_minutos,
            (int) $rule->prof_educ_dif_minutos,
            (int) $rule->pae_minutos,
            $observaciones
        );
    }

    public static function formatMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        $hours = intdiv(max(0, $minutes), 60);
        $mins = max(0, $minutes) % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    public static function educadoresDiferencialesNecesarios(?int $minutes, int $baseMinutes = 1710): float
    {
        $baseMinutes = max(1, $baseMinutes);
        return max(0, (int) ($minutes ?? 0)) / $baseMinutes;
    }

    public static function educadoresDiferencialesRedondeados(?int $minutes, int $baseMinutes = 1710): int
    {
        $minutes = max(0, (int) ($minutes ?? 0));
        $baseMinutes = max(1, $baseMinutes);

        if ($minutes === 0) {
            return 0;
        }

        return (int) ceil($minutes / $baseMinutes);
    }

    public static function formatEducadoresDiferenciales(?int $minutes, int $baseMinutes = 1710): string
    {
        return number_format(self::educadoresDiferencialesNecesarios($minutes, $baseMinutes), 2, ',', '.');
    }

    public static function determineRegimen(EstablecimientoCurso $curso): string
    {
        if (self::isEpja($curso)) {
            return 'sin_jec';
        }

        $regimen = self::normalize((string) ($curso->regimen_jec ?? ''));
        if (str_contains($regimen, 'CON JEC') || ($regimen === 'CON' || $regimen === 'JEC')) {
            return 'con_jec';
        }

        return 'sin_jec';
    }

    public static function isEpja(EstablecimientoCurso $curso): bool
    {
        $curso->loadMissing(['curso', 'planEstudio']);
        $haystack = implode(' ', [
            $curso->nombre_seccion,
            $curso->curso?->nombre,
            $curso->curso?->codigo,
            $curso->curso?->nivel_educativo,
            $curso->curso?->modalidad,
            $curso->planEstudio?->nombre_plan,
            $curso->planEstudio?->nivel_educativo,
            $curso->planEstudio?->modalidad,
        ]);

        $text = self::normalize($haystack);
        return str_contains($text, 'EPJA')
            || str_contains($text, 'ADULTO')
            || str_contains($text, 'ADULTA')
            || str_contains($text, 'NIVEL BASICO')
            || str_contains($text, 'NIVEL MEDIO');
    }

    private static function payload(string $regimen, int $neetCalculo, int $neepCalculo, ?int $total, ?int $educDif, ?int $pae, array $observaciones): array
    {
        return [
            'regimen_calculo' => $regimen,
            'neet_calculo' => $neetCalculo,
            'neep_calculo' => $neepCalculo,
            'total_crono_minutos' => $total,
            'prof_educ_dif_minutos' => $educDif,
            'pae_minutos' => $pae,
            'calculo_observacion' => implode(' ', array_values(array_filter($observaciones))),
            'calculado_at' => now(),
        ];
    }

    private static function normalize(string $text): string
    {
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            '°' => '', 'º' => '', '/' => ' ', '-' => ' ', '_' => ' ', '(' => ' ', ')' => ' ', '.' => ' ',
        ]);
        $text = preg_replace('/\s+/', ' ', $text);
        return mb_strtoupper(trim($text), 'UTF-8');
    }
}
