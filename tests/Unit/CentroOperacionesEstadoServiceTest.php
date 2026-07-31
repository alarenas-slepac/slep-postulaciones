<?php

namespace Tests\Unit;

use App\Models\CentroOperacionesReporte;
use App\Models\CentroOperacionesReporteAfectacion;
use App\Models\CentroOperacionesReporteServicio;
use App\Services\CentroOperaciones\EstadoService;
use Tests\TestCase;

class CentroOperacionesEstadoServiceTest extends TestCase
{
    public function test_reporte_sin_novedades_es_operativo(): void
    {
        $reporte = $this->reporte('si', 'sin_novedad', ['operativo', 'operativo']);

        $this->assertSame('operativo', app(EstadoService::class)->paraReporte($reporte));
    }

    public function test_una_alerta_de_servicio_eleva_el_estado_general(): void
    {
        $reporte = $this->reporte('si', 'sin_novedad', ['operativo', 'alerta']);

        $this->assertSame('alerta', app(EstadoService::class)->paraReporte($reporte));
    }

    public function test_incidencias_activas_acumuladas_pueden_elevar_un_reporte_a_critico(): void
    {
        $reporte = $this->reporte('si', 'sin_novedad', ['operativo']);
        $incidenciasActivas = [
            ['severidad' => 'alerta'],
            ['severidad' => 'critico'],
        ];

        $this->assertSame('critico', app(EstadoService::class)->paraReporte($reporte, $incidenciasActivas));
    }

    public function test_suspension_total_es_critica_aunque_los_servicios_sean_operativos(): void
    {
        $reporte = $this->reporte('si', 'sin_novedad', ['operativo']);
        $reporte->setRelation('afectaciones', collect([
            new CentroOperacionesReporteAfectacion(['tipo' => 'suspension_total']),
        ]));

        $this->assertSame('critico', app(EstadoService::class)->paraReporte($reporte));
    }

    /** @param array<int, string> $estados */
    private function reporte(string $funcionamiento, string $prioridad, array $estados): CentroOperacionesReporte
    {
        $reporte = new CentroOperacionesReporte(compact('funcionamiento', 'prioridad'));
        $reporte->setRelation('servicios', collect(array_map(
            fn (string $estado) => new CentroOperacionesReporteServicio(['estado' => $estado]),
            $estados
        )));
        $reporte->setRelation('afectaciones', collect());

        return $reporte;
    }
}
