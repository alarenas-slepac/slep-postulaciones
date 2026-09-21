<?php

namespace Tests\Unit;

use App\Exports\DotacionSobredotacionEstablecimientosExport;
use App\Models\DotacionDocenteExclusion;
use App\Models\Establecimiento;
use App\Support\DotacionProyeccionCalculator;
use App\Support\DotacionSobredotacionCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DotacionContratoProtegidoTest extends TestCase
{
    public static function motivosProtegidos(): array
    {
        return [['fuero_maternal'], ['horas_gremiales']];
    }

    #[DataProvider('motivosProtegidos')]
    public function test_protege_contrato_completo_sin_inflar_dotacion_ni_perder_cobertura(string $motivo): void
    {
        $docentes = [$this->docente($motivo), $this->otroDocente()];
        $antes = serialize($docentes);
        $resultado = DotacionSobredotacionCalculator::build($docentes, $this->resumen());
        $aula = $resultado['aula'];

        $this->assertSame(['22222222-2'], $aula['items']->pluck('rut')->all());
        $this->assertSame(['22222222-2'], $aula['ajustes']->pluck('rut')->all());
        $this->assertSame(74.0, $aula['resumen']['horas_dotacion_total']);
        $this->assertSame(-32.0, $aula['resumen']['brecha_estructural']);
        $this->assertSame(20.0, $aula['resumen']['horas_sobredotacion_total']);
        $this->assertSame(24.0, $aula['resumen']['horas_universo_revision']);
        $this->assertSame(4.0, $aula['resumen']['horas_declaradas_ajustables']);
        $this->assertSame(12.0, $aula['resumen']['horas_declaradas_asignadas']);
        $this->assertSame(8.0, $aula['resumen']['horas_declaradas_protegidas']);
        $this->assertSame(0.0, $aula['resumen']['horas_declaradas_pendientes']);
        $this->assertSame(42.0, $aula['resumen']['horas_asignadas_total']);
        $this->assertSame(12.0, $aula['resumen']['horas_sobredotacion_protegida']);
        $protegido = $resultado['protegidos']->sole();
        $this->assertSame(44.0, $protegido['contrato_original']);
        $this->assertSame(30.0, $protegido['contrato_considerado']);
        $this->assertSame(DotacionDocenteExclusion::MOTIVOS[$motivo], $protegido['motivo_proteccion']);
        $this->assertSame($antes, serialize($docentes));

        $proyeccion = DotacionProyeccionCalculator::build(['docentes' => $docentes, 'resumen' => $this->resumen()], 2026, []);
        $this->assertSame(74.0, $proyeccion['contratos']['total']);
        $this->assertSame(30.0, $proyeccion['docentes'][0]['contrato_proyectado']);
    }

    #[DataProvider('motivosProtegidos')]
    public function test_pie_reserva_protegidos_antes_de_otras_personas_sin_inventar_necesidades(string $motivo): void
    {
        $protegido = $this->docente($motivo);
        $protegido['horas_planta'] = 0;
        $protegido['horas_contrata'] = 44;
        $otro = $this->otroDocente();
        $otro['horas_planta'] = 44;
        $otro['horas_contrata'] = 0;
        $protegido['titulo'] = $otro['titulo'] = 'Educadora Diferencial';
        $resultado = DotacionSobredotacionCalculator::build([$protegido, $otro], [
            'horas_contrato_docente_pie' => 32, 'horas_contrato_pie_necesarias' => 25,
        ]);
        $pie = $resultado['pie'];
        $this->assertSame(['22222222-2'], $pie['items']->pluck('rut')->all());
        $this->assertSame(32.0, $pie['resumen']['horas_dotacion_total']);
        $this->assertSame(25.0, $pie['resumen']['horas_necesidad_cubierta']);
        $this->assertSame(7.0, $pie['resumen']['horas_sobredotacion_total']);
        $this->assertSame(7.0, $pie['resumen']['horas_sobredotacion_planta']);
        $this->assertSame(0.0, $pie['resumen']['horas_sobredotacion_contrata']);
        $this->assertSame(7.0, $pie['resumen']['horas_sobredotacion_estructural']);
        $this->assertSame(0.0, $pie['resumen']['horas_sobredotacion_protegida']);
    }

    public function test_aplica_a_parvularia_y_escuela_especial_y_a_cero_o_todas_las_horas_necesarias(): void
    {
        foreach (['fuero_maternal', 'horas_gremiales'] as $motivo) {
            foreach ([0, 30, 44] as $necesarias) {
                foreach (['Pedagogía en Educación de Párvulos', 'Educadora Diferencial'] as $titulo) {
                    $docente = $this->docente($motivo);
                    $docente['titulo'] = $titulo;
                    $docente['horas_contrato'] = $necesarias;
                    $docente['exclusion_docente']['horas'] = 44 - $necesarias;
                    $resultado = DotacionSobredotacionCalculator::build([$docente], [
                        'establecimiento_especial' => true, 'horas_contrato_docentes_aula' => $necesarias,
                    ]);
                    $this->assertTrue($resultado['aula']['items']->isEmpty());
                    $this->assertTrue($resultado['aula']['ajustes']->isEmpty());
                    $this->assertTrue($resultado['pie']['items']->isEmpty());
                    $this->assertSame((float) $necesarias, $resultado['aula']['resumen']['horas_dotacion_total']);
                    $this->assertSame(44.0, $resultado['protegidos']->sole()['contrato_original']);
                }
            }
        }
    }

    public function test_cambiar_o_eliminar_situacion_restituye_elegibilidad_y_otros_motivos_no_protegen(): void
    {
        foreach (array_merge([''], array_diff(array_keys(DotacionDocenteExclusion::MOTIVOS), ['fuero_maternal', 'horas_gremiales'])) as $motivo) {
            $docente = $this->docente('fuero_maternal');
            $this->assertTrue(DotacionSobredotacionCalculator::build([$docente], [])['aula']['items']->isEmpty());
            if ($motivo === '') {
                unset($docente['exclusion_docente']);
                $docente['horas_contrato'] = 44;
            } else {
                $docente['exclusion_docente']['motivo'] = $motivo;
            }
            $resultado = DotacionSobredotacionCalculator::build([$docente], []);
            $this->assertTrue($resultado['protegidos']->isEmpty());
            $this->assertCount(1, $resultado['aula']['items']);
            $this->assertCount(1, $resultado['aula']['ajustes']);
        }
    }

    public function test_vista_y_excel_separan_protegidos_de_propuestas_y_conservan_horas_declaradas(): void
    {
        $resultado = DotacionSobredotacionCalculator::build([$this->docente('fuero_maternal')], $this->resumen());
        $establecimiento = (new Establecimiento)->forceFill(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']);
        foreach (['aula', 'pie'] as $tipo) {
            $html = view('admin.dotacion-establecimiento.partials._sobredotacion', [
                'establecimiento' => $establecimiento, 'anio' => 2026,
                'sobredotacion' => $resultado, 'sobredotacionTipo' => $tipo,
            ])->render();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//tr[contains(., "Docente protegido sintético")]')->length);
            $fila = $xpath->query('//section[@aria-labelledby="contratos-protegidos-titulo"]//tbody/tr')->item(0);
            $this->assertNotNull($fila);
            $this->assertStringContainsString('Fuero maternal', $fila->textContent);
            $this->assertStringContainsString('44', $fila->textContent);
            $this->assertStringContainsString('30', $fila->textContent);
        }

        $libro = (new DotacionSobredotacionEstablecimientosExport)->workbookFromRows(collect([
            ['establecimiento' => $establecimiento, 'sobredotacion' => $resultado],
        ]), 2026);
        try {
            $filas = collect($libro->getActiveSheet()->toArray(null, true, false, false));
            $protegido = $filas->filter(fn ($fila) => ($fila[1] ?? '') === 'Docente protegido sintético')->sole();
            $this->assertSame('Fuero maternal', $protegido[2]);
            $this->assertSame(44.0, $protegido[4]);
            $this->assertSame(30.0, $protegido[5]);
            $this->assertSame(8.0, $libro->getActiveSheet()->getCell('F8')->getValue());
            $this->assertSame(0.0, (float) $libro->getActiveSheet()->getCell('F11')->getValue());
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    private function docente(string $motivo): array
    {
        return [
            'rut' => '11111111-1', 'nombre' => 'Docente protegido sintético', 'tipo_contrato' => 'PLANTA / CONTRATA',
            'horas_contrato_base' => 44, 'horas_contrato' => 30, 'horas_planta' => 20, 'horas_contrata' => 24,
            'exclusion_docente' => ['motivo' => $motivo, 'horas' => 14],
            'asignaciones' => [
                ['tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 10],
                ['tipo_asignacion' => 'otra_funcion', 'horas_contrato' => 8, 'dotacion_funcion_id' => 1],
            ],
        ];
    }

    private function otroDocente(): array
    {
        return [
            'rut' => '22222222-2', 'nombre' => 'Docente revisable sintético',
            'horas_contrato' => 44, 'horas_planta' => 30, 'horas_contrata' => 14,
            'asignaciones' => [
                ['tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 20],
                ['tipo_asignacion' => 'otra_funcion', 'horas_contrato' => 4, 'dotacion_funcion_id' => 2],
            ],
        ];
    }

    private function resumen(): array
    {
        return [
            'horas_contrato_docentes_aula' => 74, 'contrato_plan_mas_trabajo_colaborativo_pie' => 42,
            'horas_dotacion_funciones_declaradas' => 12,
        ];
    }
}
