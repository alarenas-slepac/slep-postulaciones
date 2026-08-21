<?php

namespace Tests\Unit;

use App\Support\MaeDiscountCategoryCatalog;
use App\Support\MaeColumnNormalizer;
use PHPUnit\Framework\TestCase;

class MaeDiscountCategoryCatalogTest extends TestCase
{
    public function test_resuelve_categorias_desde_metadatos_existentes(): void
    {
        $this->assertSame(
            'aporte_patronal',
            MaeDiscountCategoryCatalog::categoryFromMetadata('descuento', 'patronal', 'descuento', true, 'SEG. CESANTIA EMP.')
        );
        $this->assertSame(
            'legal_impuesto',
            MaeDiscountCategoryCatalog::categoryFromMetadata('resumen', 'descuentos_legales', 'descuento', false, 'IMPUESTO')
        );
        $this->assertSame(
            'salud_complementaria',
            MaeDiscountCategoryCatalog::categoryFromMetadata('descuento', 'salud_complementaria', 'descuento', false)
        );
    }

    public function test_entrega_metadatos_consistentes_para_una_correccion_manual(): void
    {
        $administrativo = MaeDiscountCategoryCatalog::metadata('administrativo');
        $patronal = MaeDiscountCategoryCatalog::metadata('aporte_patronal');

        $this->assertSame('descuento', $administrativo['grupo']);
        $this->assertSame('administrativo', $administrativo['subgrupo']);
        $this->assertFalse($administrativo['es_aporte_patronal']);
        $this->assertSame('aporte_patronal', $patronal['tipo_movimiento']);
        $this->assertTrue($patronal['es_aporte_patronal']);
    }

    public function test_detecta_salud_complementaria_sin_confundirla_con_salud_legal(): void
    {
        $metadata = MaeColumnNormalizer::inferDiscountMetadata('Seguro Salud Complementaria');

        $this->assertSame('descuento', $metadata['grupo']);
        $this->assertSame('salud_complementaria', $metadata['subgrupo']);
        $this->assertSame(
            'salud_complementaria',
            MaeDiscountCategoryCatalog::categoryFromMetadata(
                $metadata['grupo'],
                $metadata['subgrupo'],
                $metadata['tipo_movimiento'],
                $metadata['es_aporte_patronal']
            )
        );
    }
}
