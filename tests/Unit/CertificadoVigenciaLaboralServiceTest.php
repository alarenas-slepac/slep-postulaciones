<?php

namespace Tests\Unit;

use App\Services\Certificados\CertificadoVigenciaLaboralService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CertificadoVigenciaLaboralServiceTest extends TestCase
{
    private CertificadoVigenciaLaboralService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CertificadoVigenciaLaboralService;
    }

    public function test_reemplazo_docente_anterior_no_se_suma_a_contrata(): void
    {
        $resultado = $this->resolver([
            $this->contrato(1, '2023-03-29', '2023-04-10', 'REEMPLAZO DOCENTE'),
            $this->contrato(2, '2023-04-11', '2024-02-29', 'CONTRATA'),
            $this->contrato(3, '2024-03-01', '2025-02-28', 'CONTRATA'),
            $this->contrato(4, '2025-03-01', '2026-02-28', 'CONTRATA'),
            $this->contrato(5, '2026-03-01', '2027-02-28', 'CONTRATA'),
        ]);

        self::assertSame('2023-04-11', $resultado['fecha_antiguedad']->format('Y-m-d'));
    }

    public function test_periodos_consecutivos_continuan_y_un_dia_sin_contrato_corta(): void
    {
        $consecutivos = $this->resolver([
            $this->contrato(1, '2024-01-01', '2024-01-31'),
            $this->contrato(2, '2024-02-01', '2026-12-31'),
        ]);
        self::assertSame('2024-01-01', $consecutivos['fecha_antiguedad']->format('Y-m-d'));

        $conCorte = $this->resolver([
            $this->contrato(1, '2024-01-01', '2024-01-31'),
            $this->contrato(2, '2024-02-02', '2026-12-31'),
        ]);
        self::assertSame('2024-02-02', $conCorte['fecha_antiguedad']->format('Y-m-d'));
    }

    public function test_cambio_de_regimen_juridico_inicia_una_nueva_continuidad(): void
    {
        $resultado = $this->resolver([
            $this->contrato(
                1,
                '2022-01-01',
                '2024-12-31',
                'CONTRATA',
                'ESTATUTO DOCENTE'
            ),
            $this->contrato(
                2,
                '2025-01-01',
                '2027-12-31',
                'CONTRATA',
                'CÓDIGO DEL TRABAJO'
            ),
        ]);

        self::assertSame('2025-01-01', $resultado['fecha_antiguedad']->format('Y-m-d'));
        self::assertSame('CÓDIGO DEL TRABAJO', $resultado['regimen_juridico']);
    }

    public function test_cambios_de_establecimiento_y_calidad_mantienen_continuidad(): void
    {
        $primero = $this->contrato(1, '2022-01-01', '2024-12-31', 'PLAZO FIJO');
        $segundo = $this->contrato(2, '2025-01-01', '2027-12-31', 'CONTRATA');
        $segundo['establecimiento'] = 'ESCUELA B';
        $segundo['comuna'] = 'LOTA';

        $resultado = $this->resolver([$primero, $segundo]);

        self::assertSame('2022-01-01', $resultado['fecha_antiguedad']->format('Y-m-d'));
        self::assertSame('CONTRATA', $resultado['calidad_juridica']);
        self::assertSame('ESCUELA B', $resultado['establecimientos'][0]['establecimiento']);
    }

    public function test_reemplazo_anterior_no_se_suma_a_plazo_fijo(): void
    {
        $resultado = $this->resolver([
            $this->contrato(1, '2024-01-01', '2024-05-31', 'REEMPLAZO'),
            $this->contrato(2, '2024-06-01', '2027-12-31', 'PLAZO FIJO'),
        ]);

        self::assertSame('2024-06-01', $resultado['fecha_antiguedad']->format('Y-m-d'));
    }

    public function test_informa_todos_los_establecimientos_actualmente_vigentes(): void
    {
        $uno = $this->contrato(1, '2025-01-01', '2027-12-31');
        $dos = $this->contrato(2, '2026-01-01', '2027-02-28');
        $dos['establecimiento'] = 'LICEO C';
        $dos['comuna'] = 'SAN PEDRO DE LA PAZ';

        $resultado = $this->resolver([$uno, $dos]);

        self::assertCount(2, $resultado['establecimientos']);
        self::assertSame(
            ['LICEO C', 'ESCUELA A'],
            array_column($resultado['establecimientos'], 'establecimiento')
        );
    }

    public function test_superposiciones_usan_el_fin_acumulado_para_la_continuidad(): void
    {
        $resultado = $this->resolver([
            $this->contrato(1, '2024-01-01', '2024-01-31'),
            $this->contrato(2, '2024-01-15', '2026-06-30'),
            $this->contrato(3, '2026-07-01', '2027-02-28'),
        ]);

        self::assertSame('2024-01-01', $resultado['fecha_antiguedad']->format('Y-m-d'));
    }

    public function test_historial_incluye_todos_los_contratos_en_orden_cronologico(): void
    {
        $resultado = $this->resolver([
            $this->contrato(
                3,
                '2025-01-01',
                '2027-12-31',
                'CONTRATA',
                'CÓDIGO DEL TRABAJO'
            ),
            $this->contrato(
                1,
                '2022-01-01',
                '2022-12-31',
                'REEMPLAZO DOCENTE',
                'ESTATUTO DOCENTE'
            ),
            $this->contrato(
                2,
                '2023-01-01',
                '2024-12-31',
                'CONTRATA',
                'ESTATUTO DOCENTE'
            ),
        ]);

        self::assertCount(3, $resultado['historial_contratos']);
        self::assertSame(
            ['2022-01-01', '2023-01-01', '2025-01-01'],
            array_column($resultado['historial_contratos'], 'fecha_ingreso')
        );
        self::assertSame(
            [
                'establecimiento',
                'fecha_ingreso',
                'fecha_finiquito',
                'termino_indefinido',
                'calidad_juridica',
                'regimen_juridico',
            ],
            array_keys($resultado['historial_contratos'][0])
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $contratos
     * @return array<string, mixed>
     */
    private function resolver(array $contratos): array
    {
        return $this->service->resolverDesdeContratos(
            $contratos,
            CarbonImmutable::create(2026, 7, 30)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function contrato(
        int $id,
        string $desde,
        ?string $hasta,
        string $calidad = 'CONTRATA',
        string $regimen = 'ESTATUTO DOCENTE'
    ): array {
        return [
            'id' => $id,
            'fila_origen' => $id + 1,
            'rut_normalizado' => '123456785',
            'nombre' => 'FUNCIONARIA DE PRUEBA',
            'establecimiento' => 'ESCUELA A',
            'comuna' => 'CORONEL',
            'fecha_ingreso' => $desde,
            'fecha_finiquito' => $hasta,
            'termino_indefinido' => $hasta === null,
            'calidad_juridica' => $calidad,
            'regimen_juridico' => $regimen,
        ];
    }
}
