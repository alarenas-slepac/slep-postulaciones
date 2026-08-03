<?php

namespace Tests\Unit;

use App\Services\EstablecimientoImportService;
use Tests\TestCase;

class EstablecimientoImportHeadersTest extends TestCase
{
    public function test_campos_opcionales_se_agregan_sin_alterar_los_encabezados_historicos(): void
    {
        $servicio = new EstablecimientoImportService();

        $this->assertSame($servicio->requiredHeaders(), array_slice(
            $servicio->expectedHeaders(),
            0,
            count($servicio->requiredHeaders())
        ));
        $this->assertSame('MATRICULA_TOTAL', $servicio->expectedHeaders()[count($servicio->requiredHeaders())]);
        $this->assertSame('DIRECTOR_NOMBRE', $servicio->expectedHeaders()[count($servicio->requiredHeaders()) + 1]);
        $this->assertSame('DIRECTOR_CONTACTO', $servicio->expectedHeaders()[count($servicio->requiredHeaders()) + 2]);
    }
}
