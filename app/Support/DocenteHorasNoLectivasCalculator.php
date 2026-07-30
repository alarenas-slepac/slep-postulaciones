<?php

namespace App\Support;

use App\Models\DocenteHorasProporcion;
use App\Models\DotacionProporcionExcepcion;
use Illuminate\Support\Facades\Schema;

class DocenteHorasNoLectivasCalculator
{
    /** @var array<string, \Illuminate\Support\Collection> */
    private static array $proportionRowsCache = [];

    /** @var array<string, ?DotacionProporcionExcepcion> */
    private static array $exceptionCache = [];

    private static ?bool $exceptionTableExists = null;

    public const PROPORCION_GENERAL = '65_35';
    public const PROPORCION_PRIORITARIOS = '60_40';
    public const HORAS_CONTRATO_REFERENCIA = 44;

    public static function referenceFor(
        object $curso,
        ?float $porcentajePrioritarios,
        int $horasContrato = self::HORAS_CONTRATO_REFERENCIA,
        bool $incluirExcepcionInstitucional = false
    ): array {
        $aplicaPrimerCiclo = self::isPrimerCicloBasico($curso);
        $porcentaje = $porcentajePrioritarios !== null ? round($porcentajePrioritarios, 2) : null;
        $establecimientoId = (int) ($curso->establecimiento_id ?? 0);
        $anio = (int) ($curso->anio ?? 0);
        $excepcion = $incluirExcepcionInstitucional
            ? self::activeExceptionFor($establecimientoId, $anio)
            : null;

        if ($excepcion) {
            $proporcion = self::PROPORCION_PRIORITARIOS;
            $origen = 'excepcion_institucional';
            $origenLabel = 'Excepción institucional';
            $motivo = 'Excepción institucional 60/40 activa para todos los niveles del establecimiento. Justificación: '.trim((string) $excepcion->justificacion);
        } else {
            $proporcion = ($aplicaPrimerCiclo && $porcentaje !== null && $porcentaje >= 80.0)
                ? self::PROPORCION_PRIORITARIOS
                : self::PROPORCION_GENERAL;
            $origen = $proporcion === self::PROPORCION_PRIORITARIOS
                ? 'alumnos_prioritarios'
                : 'regla_general';
            $origenLabel = $proporcion === self::PROPORCION_PRIORITARIOS
                ? 'Alumnos prioritarios'
                : 'Regla general';
            $motivo = self::motivo($aplicaPrimerCiclo, $porcentaje, $proporcion);
        }

        $regla = DocenteHorasProporcion::query()
            ->where('proporcion', $proporcion)
            ->where('horas_contrato', $horasContrato)
            ->where('vigente', true)
            ->first();

        return [
            'porcentaje_prioritarios' => $porcentaje,
            'primer_ciclo_basico' => $aplicaPrimerCiclo,
            'proporcion' => $proporcion,
            'proporcion_label' => self::proporcionLabel($proporcion),
            'origen_proporcion' => $origen,
            'origen_proporcion_label' => $origenLabel,
            'excepcion_id' => $excepcion?->id,
            'excepcion_justificacion' => $excepcion?->justificacion,
            'horas_contrato' => $horasContrato,
            'horas_aula_pedagogicas' => $regla?->horas_aula_pedagogicas,
            'horas_aula_cronologicas_minutos' => $regla?->horas_aula_cronologicas_minutos,
            'recreo_minutos' => $regla?->recreo_minutos,
            'horas_no_lectivas_minutos' => $regla?->horas_no_lectivas_minutos,
            'motivo' => $motivo,
        ];
    }

    public static function activeExceptionFor(int $establecimientoId, int $anio): ?DotacionProporcionExcepcion
    {
        if ($establecimientoId <= 0 || $anio <= 0 || ! self::exceptionTableExists()) {
            return null;
        }

        $key = $establecimientoId.'_'.$anio;
        if (! array_key_exists($key, self::$exceptionCache)) {
            self::$exceptionCache[$key] = DotacionProporcionExcepcion::query()
                ->where('establecimiento_id', $establecimientoId)
                ->where('anio', $anio)
                ->where('activa', true)
                ->where('proporcion', self::PROPORCION_PRIORITARIOS)
                ->first();
        }

        return self::$exceptionCache[$key];
    }

    public static function clearExceptionCache(?int $establecimientoId = null, ?int $anio = null): void
    {
        if ($establecimientoId !== null && $anio !== null) {
            unset(self::$exceptionCache[$establecimientoId.'_'.$anio]);
            return;
        }

        self::$exceptionCache = [];
        self::$exceptionTableExists = null;
    }

    private static function exceptionTableExists(): bool
    {
        if (self::$exceptionTableExists === null) {
            self::$exceptionTableExists = Schema::hasTable('dotacion_proporcion_excepciones');
        }

        return self::$exceptionTableExists;
    }


    public static function contratoRequeridoDesdeHorasAula(string $proporcion, float $horasAulaPedagogicas): array
    {
        $horasAulaPedagogicas = round(max(0.0, $horasAulaPedagogicas), 2);
        if ($horasAulaPedagogicas <= 0) {
            return [
                'horas_contrato' => 0.0,
                'horas_aula_pedagogicas' => 0.0,
                'proporcion' => $proporcion,
                'proporcion_label' => self::proporcionLabel($proporcion),
                'motivo' => 'Sin horas aula pedagógicas para convertir.',
            ];
        }

        $rows = self::rowsForProportion($proporcion);

        if ($rows->isEmpty()) {
            return [
                'horas_contrato' => $horasAulaPedagogicas,
                'horas_aula_pedagogicas' => $horasAulaPedagogicas,
                'proporcion' => $proporcion,
                'proporcion_label' => self::proporcionLabel($proporcion),
                'motivo' => 'No existe tabla de proporción vigente; se usa equivalencia 1:1 como respaldo.',
            ];
        }

        /*
         * La tabla CPEIP está definida desde horas de contrato hacia horas aula.
         * Al invertirla, una misma cantidad de horas aula puede corresponder a
         * más de una jornada contractual. En esos casos se debe utilizar la
         * jornada MAYOR, evitando subestimar las horas de contrato requeridas.
         *
         * Para cargas fraccionadas o sin coincidencia exacta se toma primero el
         * menor tramo de horas aula que cubra la carga y, si ese tramo está
         * repetido, nuevamente se selecciona la mayor jornada contractual.
         */
        $eligible = $rows->filter(
            fn ($row) => (float) $row->horas_aula_pedagogicas >= $horasAulaPedagogicas
        );

        if ($eligible->isNotEmpty()) {
            $targetClassroomHours = (float) $eligible->min('horas_aula_pedagogicas');
            $selected = $eligible
                ->filter(fn ($row) => (float) $row->horas_aula_pedagogicas === $targetClassroomHours)
                ->sortByDesc('horas_contrato')
                ->first();
            $isExact = abs($targetClassroomHours - $horasAulaPedagogicas) < 0.0001;

            return [
                'horas_contrato' => (float) $selected->horas_contrato,
                'horas_aula_pedagogicas' => $horasAulaPedagogicas,
                'proporcion' => $proporcion,
                'proporcion_label' => self::proporcionLabel($proporcion),
                'motivo' => $isExact
                    ? 'Conversión exacta desde tabla docente_horas_proporciones, usando la mayor jornada contractual cuando el tramo de horas aula está repetido.'
                    : 'Conversión por tramo superior desde tabla docente_horas_proporciones, usando la mayor jornada contractual del tramo seleccionado.',
                'horas_aula_tramo' => $targetClassroomHours,
            ];
        }

        $maxAula = (float) $rows->max('horas_aula_pedagogicas');
        $max = $rows
            ->filter(fn ($row) => (float) $row->horas_aula_pedagogicas === $maxAula)
            ->sortByDesc('horas_contrato')
            ->first();
        $maxContrato = (float) $max->horas_contrato;
        $excedenteAula = round($horasAulaPedagogicas - $maxAula, 2);
        $excedente = self::contratoRequeridoDesdeHorasAula($proporcion, $excedenteAula);

        return [
            'horas_contrato' => $maxContrato + (float) ($excedente['horas_contrato'] ?? 0),
            'horas_aula_pedagogicas' => $horasAulaPedagogicas,
            'proporcion' => $proporcion,
            'proporcion_label' => self::proporcionLabel($proporcion),
            'motivo' => 'Las horas aula superan el máximo de la tabla vigente; se calcula como máximo tabulado más excedente convertido por la misma proporción.',
            'horas_aula_base_tabla' => $maxAula,
            'horas_contrato_base_tabla' => $maxContrato,
            'horas_aula_excedente' => $excedenteAula,
            'horas_contrato_excedente' => (float) ($excedente['horas_contrato'] ?? 0),
        ];
    }


    private static function rowsForProportion(string $proporcion)
    {
        if (! array_key_exists($proporcion, self::$proportionRowsCache)) {
            self::$proportionRowsCache[$proporcion] = DocenteHorasProporcion::query()
                ->where('proporcion', $proporcion)
                ->where('vigente', true)
                ->orderBy('horas_aula_pedagogicas')
                ->orderByDesc('horas_contrato')
                ->get(['horas_contrato', 'horas_aula_pedagogicas']);
        }

        return self::$proportionRowsCache[$proporcion];
    }

    /**
     * Convierte minutos de atención PROF. EDUC. DIF. en horas de contrato asociadas.
     *
     * La base de aula cronológica corresponde a la jornada referencial de 44 horas
     * para la proporción aplicada al curso (28:30 para 65/35 o 26:15 para 60/40).
     * El total exacto se conserva en minutos decimales y la bolsa contractual se
     * redondea una sola vez hacia arriba a horas enteras.
     */
    public static function contratoAsociadoDesdeMinutosAula(
        ?int $minutosAula,
        ?int $baseAulaMinutos,
        int $horasContratoReferencia = self::HORAS_CONTRATO_REFERENCIA
    ): array {
        $minutosAula = max(0, (int) ($minutosAula ?? 0));
        $baseAulaMinutos = max(0, (int) ($baseAulaMinutos ?? 0));
        $horasContratoReferencia = max(0, $horasContratoReferencia);

        if ($minutosAula <= 0 || $baseAulaMinutos <= 0 || $horasContratoReferencia <= 0) {
            return [
                'minutos_aula' => $minutosAula,
                'base_aula_minutos' => $baseAulaMinutos,
                'horas_contrato_referencia' => $horasContratoReferencia,
                'equivalentes' => 0.0,
                'minutos_contrato_exactos' => 0.0,
                'minutos_contrato_redondeados' => 0,
                'horas_contrato_decimal' => 0.0,
                'horas_contrato_bolsa' => 0,
                'horas_contrato_label' => '00:00',
                'formula' => null,
            ];
        }

        $equivalentes = $minutosAula / $baseAulaMinutos;
        $minutosContratoExactos = $equivalentes * ($horasContratoReferencia * 60);
        $minutosContratoRedondeados = (int) round($minutosContratoExactos);
        $horasContratoDecimal = $minutosContratoExactos / 60;
        $horasContratoBolsa = (int) ceil($horasContratoDecimal);

        return [
            'minutos_aula' => $minutosAula,
            'base_aula_minutos' => $baseAulaMinutos,
            'horas_contrato_referencia' => $horasContratoReferencia,
            'equivalentes' => $equivalentes,
            'minutos_contrato_exactos' => $minutosContratoExactos,
            'minutos_contrato_redondeados' => $minutosContratoRedondeados,
            'horas_contrato_decimal' => round($horasContratoDecimal, 4),
            'horas_contrato_bolsa' => $horasContratoBolsa,
            'horas_contrato_label' => self::formatMinutes($minutosContratoRedondeados),
            'formula' => sprintf(
                '%s / %s x %d h',
                self::formatMinutes($minutosAula),
                self::formatMinutes($baseAulaMinutos),
                $horasContratoReferencia
            ),
        ];
    }

    public static function proportionTable(string $proporcion, array $contratos = [44, 37, 30, 23, 16, 9, 2])
    {
        return DocenteHorasProporcion::query()
            ->where('proporcion', $proporcion)
            ->whereIn('horas_contrato', $contratos)
            ->where('vigente', true)
            ->orderByDesc('horas_contrato')
            ->get();
    }

    public static function proporcionLabel(?string $proporcion): string
    {
        return match ($proporcion) {
            self::PROPORCION_PRIORITARIOS => '60/40',
            self::PROPORCION_GENERAL => '65/35',
            default => '—',
        };
    }

    public static function formatMinutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        $minutes = max(0, $minutes);
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function isPrimerCicloBasico(object $curso): bool
    {
        $text = self::normalize(implode(' ', [
            $curso->nombre_seccion ?? '',
            $curso->curso_nombre ?? '',
            $curso->nombre ?? '',
            $curso->letra ?? '',
            optional($curso->curso ?? null)->nombre ?? '',
            optional($curso->curso ?? null)->codigo ?? '',
            optional($curso->curso ?? null)->nivel_educativo ?? '',
            optional($curso->curso ?? null)->modalidad ?? '',
            optional($curso->planEstudio ?? null)->nombre_plan ?? '',
            $curso->plan_nombre ?? '',
        ]));

        if (str_contains($text, 'EPJA') || str_contains($text, 'ADULTO') || str_contains($text, 'ADULTA')) {
            return false;
        }

        foreach ([1, 2, 3, 4] as $nivel) {
            if (str_contains($text, "{$nivel} BASICO") || str_contains($text, "{$nivel} ANO BASICO")) {
                return true;
            }
        }

        return str_contains($text, 'PRIMERO BASICO')
            || str_contains($text, 'SEGUNDO BASICO')
            || str_contains($text, 'TERCERO BASICO')
            || str_contains($text, 'CUARTO BASICO');
    }

    private static function motivo(bool $primerCiclo, ?float $porcentaje, string $proporcion): string
    {
        if ($proporcion === self::PROPORCION_PRIORITARIOS) {
            return '1° a 4° Básico con porcentaje de alumnos prioritarios igual o superior a 80%.';
        }

        if (! $primerCiclo) {
            return 'Curso fuera de 1° a 4° Básico: aplica proporción general 65/35.';
        }

        if ($porcentaje === null) {
            return 'Sin porcentaje de alumnos prioritarios registrado para el año: aplica proporción general 65/35.';
        }

        return '1° a 4° Básico con porcentaje de alumnos prioritarios inferior a 80%: aplica proporción general 65/35.';
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
