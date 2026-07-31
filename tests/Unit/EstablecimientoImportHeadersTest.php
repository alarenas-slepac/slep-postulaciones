<?php

namespace Tests\Unit;

use App\Services\EstablecimientoImportService;
use Tests\TestCase;

class EstablecimientoImportHeadersTest extends TestCase
{
    public function test_matricula_total_se_agrega_sin_alterar_los_encabezados_historicos(): void
    {
        $servicio = new EstablecimientoImportService();

        $this->assertSame($servicio->requiredHeaders(), array_slice(
            $servicio->expectedHeaders(),
            0,
            count($servicio->requiredHeaders())
        ));
        $this->assertSame('MATRICULA_TOTAL', $servicio->expectedHeaders()[count($servicio->requiredHeaders())]);
    }
}
