<?php

namespace Tests\Feature;

use App\Models\CertificadoEmitido;
use Tests\TestCase;

class CertificadoVigenciaPdfTemplateTest extends TestCase
{
    public function test_funcionario_ac_usa_texto_de_empleador(): void
    {
        $html = $this->renderizar(true);

        self::assertStringContainsString('actual empleador', $html);
        self::assertStringNotContainsString('actual sostenedor', $html);
    }

    public function test_funcionario_de_establecimiento_conserva_texto_de_sostenedor(): void
    {
        $html = $this->renderizar(false);

        self::assertStringContainsString('actual sostenedor', $html);
        self::assertStringNotContainsString('actual empleador', $html);
    }

    private function renderizar(bool $esFuncionarioAc): string
    {
        $certificado = new CertificadoEmitido;
        $certificado->forceFill([
            'numero' => 'CV-2026-000001',
            'codigo_validacion' => str_repeat('A', 32),
            'rut_normalizado' => '123456785',
            'nombre_snapshot' => 'FUNCIONARIA DE PRUEBA',
            'fecha_antiguedad' => '2025-01-01',
            'calidad_juridica_snapshot' => 'CONTRATA',
            'regimen_juridico_snapshot' => 'CÓDIGO DEL TRABAJO',
            'establecimientos_snapshot' => [
                [
                    'establecimiento' => 'ADMINISTRACIÓN CENTRAL',
                    'comuna' => 'CORONEL',
                ],
            ],
            'es_funcionario_ac_snapshot' => $esFuncionarioAc,
            'emitido_at' => '2026-07-31 09:00:00',
        ]);

        return view('pdf.certificados.vigencia', [
            'certificado' => $certificado,
            'institucion' => config('certificados.institucion'),
            'firmante' => config('certificados.firmante'),
            'logoDataUri' => null,
            'timbreDataUri' => null,
            'firmaDataUri' => null,
            'fuenteRegularDataUri' => null,
            'fuenteBoldDataUri' => null,
            'urlVerificacion' => 'https://example.test/verificar',
            'qrDataUri' => null,
        ])->render();
    }
}
