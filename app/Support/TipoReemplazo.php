<?php

namespace App\Support;

final class TipoReemplazo
{
    public const ENFERMEDAD_ACCIDENTE_COMUN = '1. Enfermedad o accidente común';
    public const PRORROGA_MEDICINA_PREVENTIVA = '2. Prórroga de medicina preventiva.';
    public const LICENCIA_MATERNAL = '3. Licencia maternal pre y postnatal.';
    public const ENFERMEDAD_GRAVE_HIJO_MENOR_UN_ANO = '4. Enfermedad grave del hijo menor de un año.';
    public const ACCIDENTE_TRABAJO_TRAYECTO = '5. Accidente del trabajo o del trayecto.';
    public const ENFERMEDAD_PROFESIONAL = '6. Enfermedad profesional';
    public const PATOLOGIA_EMBARAZO = '7. Patología del embarazo.';
    public const LEY_SANNA = '8. Ley Sanna.';
    public const REPOSO_MUTUALIDAD = 'Reposo Mutualidad (ACHS, MUTUAL, IST o ISL)';

    public const LICENCIA_MEDICA_GENERAL_ANTERIOR = 'Licencia Médica (General)';
    public const LICENCIA_MEDICA_MATERNAL_ANTERIOR = 'Licencia Médica (Pre y/o Post Natal y/o Parental)';

    /**
     * Opciones disponibles, en el orden reglamentario solicitado.
     * Las causales no numeradas se mantienen en orden alfabético.
     *
     * @return array<int, string>
     */
    public static function opciones(): array
    {
        return [
            self::ENFERMEDAD_ACCIDENTE_COMUN,
            self::PRORROGA_MEDICINA_PREVENTIVA,
            self::LICENCIA_MATERNAL,
            self::ENFERMEDAD_GRAVE_HIJO_MENOR_UN_ANO,
            self::ACCIDENTE_TRABAJO_TRAYECTO,
            self::ENFERMEDAD_PROFESIONAL,
            self::PATOLOGIA_EMBARAZO,
            self::LEY_SANNA,
            'Otras',
            'Permiso especial para deportistas (Art 74, Ley 19.712)',
            'Permiso Horas de Lactancia',
            'Permiso Postnatal Parental',
            'Permiso sin goce de sueldo',
            self::REPOSO_MUTUALIDAD,
            'Sumario Administrativo',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function opcionesDeshabilitadasParaNuevasSolicitudes(): array
    {
        return [
            'Permiso Horas de Lactancia',
            'Permiso especial para deportistas (Art 74, Ley 19.712)',
            'Otras',
        ];
    }

    public static function normalizar(?string $tipo): string
    {
        return self::equivalenciasHistoricas()[$tipo] ?? (string) $tipo;
    }

    public static function etiqueta(?string $tipo): string
    {
        return self::normalizar($tipo);
    }

    public static function esReposoMutualidad(?string $tipo): bool
    {
        return self::normalizar($tipo) === self::REPOSO_MUTUALIDAD;
    }

    /**
     * @return array<string, string>
     */
    public static function equivalenciasHistoricas(): array
    {
        return [
            self::LICENCIA_MEDICA_GENERAL_ANTERIOR => self::ENFERMEDAD_ACCIDENTE_COMUN,
            self::LICENCIA_MEDICA_MATERNAL_ANTERIOR => self::LICENCIA_MATERNAL,
        ];
    }
}
