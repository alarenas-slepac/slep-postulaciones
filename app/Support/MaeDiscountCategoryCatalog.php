<?php

namespace App\Support;

use InvalidArgumentException;

class MaeDiscountCategoryCatalog
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'legal_imposiciones' => 'Descuento legal · Imposiciones',
            'legal_salud' => 'Descuento legal · Salud',
            'legal_impuesto' => 'Descuento legal · Impuesto',
            'legal_cesantia' => 'Descuento legal · Cesantía',
            'previsional' => 'Previsional',
            'salud_complementaria' => 'Salud complementaria',
            'judicial' => 'Judicial',
            'administrativo' => 'Administrativo / CGR',
            'reintegro' => 'Reintegro',
            'apv' => 'APV',
            'bienestar' => 'Bienestar',
            'gremial' => 'Gremial',
            'ahorro' => 'Ahorro',
            'credito' => 'Crédito o préstamo',
            'seguro' => 'Seguro',
            'anticipo' => 'Anticipo',
            'otros' => 'Otros descuentos',
            'aporte_patronal' => 'Aporte patronal / empleador',
        ];
    }

    /**
     * @return array{grupo: string, subgrupo: string, tipo_movimiento: string, es_aporte_patronal: bool}
     */
    public static function metadata(string $category): array
    {
        return match ($category) {
            'legal_imposiciones' => self::discountMetadata('descuentos_legales', 'imposiciones'),
            'legal_salud' => self::discountMetadata('descuentos_legales', 'salud'),
            'legal_impuesto' => self::discountMetadata('descuentos_legales', 'impuesto'),
            'legal_cesantia' => self::discountMetadata('descuentos_legales', 'cesantia'),
            'previsional' => self::discountMetadata('descuento', 'previsional'),
            'salud_complementaria' => self::discountMetadata('descuento', 'salud_complementaria'),
            'judicial' => self::discountMetadata('descuento', 'judicial'),
            'administrativo' => self::discountMetadata('descuento', 'administrativo'),
            'reintegro' => self::discountMetadata('descuento', 'reintegro'),
            'apv' => self::discountMetadata('descuento', 'apv'),
            'bienestar' => self::discountMetadata('descuento', 'bienestar'),
            'gremial' => self::discountMetadata('descuento', 'gremial'),
            'ahorro' => self::discountMetadata('descuento', 'ahorro'),
            'credito' => self::discountMetadata('descuento', 'credito'),
            'seguro' => self::discountMetadata('descuento', 'seguro'),
            'anticipo' => self::discountMetadata('descuento', 'anticipo'),
            'otros' => self::discountMetadata('descuento', 'otros'),
            'aporte_patronal' => [
                'grupo' => 'aporte_patronal',
                'subgrupo' => 'empleador',
                'tipo_movimiento' => 'aporte_patronal',
                'es_aporte_patronal' => true,
            ],
            default => throw new InvalidArgumentException('Categoría de descuento MAE no válida.'),
        };
    }

    public static function categoryFromMetadata(
        ?string $grupo,
        ?string $subgrupo,
        ?string $tipoMovimiento,
        bool $esAportePatronal,
        ?string $headerOriginal = null
    ): string {
        if ($esAportePatronal || $tipoMovimiento === 'aporte_patronal' || $grupo === 'aporte_patronal' || $subgrupo === 'patronal') {
            return 'aporte_patronal';
        }

        $subgrupo = trim((string) $subgrupo);
        $direct = [
            'imposiciones' => 'legal_imposiciones',
            'salud' => 'legal_salud',
            'impuesto' => 'legal_impuesto',
            'cesantia' => 'legal_cesantia',
            'previsional' => 'previsional',
            'salud_complementaria' => 'salud_complementaria',
            'judicial' => 'judicial',
            'administrativo' => 'administrativo',
            'reintegro' => 'reintegro',
            'apv' => 'apv',
            'bienestar' => 'bienestar',
            'gremial' => 'gremial',
            'ahorro' => 'ahorro',
            'credito' => 'credito',
            'seguro' => 'seguro',
            'anticipo' => 'anticipo',
            'otros' => 'otros',
        ];

        if (isset($direct[$subgrupo])) {
            return $direct[$subgrupo];
        }

        if ($subgrupo === 'descuentos_legales' || $grupo === 'descuentos_legales') {
            $inferred = MaeColumnNormalizer::inferDiscountMetadata($headerOriginal);

            return self::categoryFromMetadata(
                $inferred['grupo'],
                $inferred['subgrupo'],
                $inferred['tipo_movimiento'],
                $inferred['es_aporte_patronal'],
                $headerOriginal
            );
        }

        return 'otros';
    }

    /**
     * @return array{grupo: string, subgrupo: string, tipo_movimiento: string, es_aporte_patronal: bool}
     */
    private static function discountMetadata(string $group, string $subgroup): array
    {
        return [
            'grupo' => $group,
            'subgrupo' => $subgroup,
            'tipo_movimiento' => 'descuento',
            'es_aporte_patronal' => false,
        ];
    }
}
