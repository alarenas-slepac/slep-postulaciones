<?php

namespace Tests\Unit;

use App\Models\Establecimiento;
use App\Services\CentroOperaciones\UnidadOperacionalService;
use Tests\TestCase;

class CentroOperacionesUnidadOperacionalServiceTest extends TestCase
{
    public function test_internado_solo_se_habilita_para_el_liceo_nueva_zelandia(): void
    {
        $service = new UnidadOperacionalService();
        $liceo = new Establecimiento([
            'nombre_establecimiento' => 'LICEO BICENTENARIO NUEVA ZELANDIA',
        ]);
        $otro = new Establecimiento([
            'nombre_establecimiento' => 'Liceo de Coronel',
        ]);

        $this->assertTrue($service->codigoPermitido($liceo, 'internado_nueva_zelandia'));
        $this->assertFalse($service->codigoPermitido($otro, 'internado_nueva_zelandia'));
        $this->assertCount(2, $service->opciones($liceo));
        $this->assertCount(1, $service->opciones($otro));
    }
}
