<?php

namespace Tests\Unit;

use App\Support\DotacionEstablecimientoCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DotacionContratoParvulariaTest extends TestCase
{
    public function test_separa_contratos_vigentes_por_titulo_sin_usar_horas_asignadas(): void
    {
        $docentes = [
            ['titulo' => 'Pedagogía en Educación de Párvulos', 'horas_contrato' => 44, 'horas_aula' => 32],
            ['declaracion' => (object) ['nombre_titulo' => ' pedagogia en educacion de parvulos '], 'horas_contrato' => 44, 'horas_aula' => 0],
            ['titulo' => 'Educadora Diferencial', 'horas_contrato' => 44],
            ['titulo' => 'Profesor de Educación Básica', 'horas_contrato' => 44, 'horas_aula_especial' => 32],
            ['titulo' => 'Sin título declarado', 'horas_contrato' => 44],
            ['titulo' => 'Pedagogía en Educación de Párvulos', 'horas_contrato' => 44, 'estamento_cobertura' => 'asistente'],
        ];

        $resultado = DotacionEstablecimientoCalculator::contratoParvularia($docentes, 771, 120);

        $this->assertSame(88.0, $resultado['horas_contrato_docentes_parvularia']);
        $this->assertSame(683.0, $resultado['horas_contrato_docentes_aula_general']);
        $this->assertSame(32.0, $resultado['brecha_dotacion_parvularia']);
        $this->assertSame(771.0, $resultado['horas_contrato_docentes_aula_general'] + $resultado['horas_contrato_docentes_parvularia']);
    }

    public function test_respeta_exclusiones_y_contratos_nulos(): void
    {
        $resultado = DotacionEstablecimientoCalculator::contratoParvularia([
            ['titulo' => 'Pedagogía en Educación de Párvulos', 'horas_contrato_base' => 44, 'horas_excluidas' => 10, 'horas_contrato' => 34],
            ['titulo' => 'Pedagogía en Educación de Párvulos', 'horas_contrato_base' => 44, 'horas_contrato' => 0],
            ['titulo' => 'Pedagogía en Educación de Párvulos', 'horas_contrato' => null],
        ], 100, 60);

        $this->assertSame(34.0, $resultado['horas_contrato_docentes_parvularia']);
        $this->assertSame(66.0, $resultado['horas_contrato_docentes_aula_general']);
        $this->assertSame(26.0, $resultado['brecha_dotacion_parvularia']);
    }

    public static function brechas(): array
    {
        return [
            'faltan horas para otra educadora' => [88.0, 120.0, 32.0, 'success', 'Horas por contratar'],
            'sobran horas' => [132.0, 120.0, -12.0, 'danger', 'Horas de sobredotación'],
            'dotacion equilibrada' => [120.0, 120.0, 0.0, 'primary', 'Dotación cuadrada'],
            'sin educadoras contratadas' => [0.0, 120.0, 120.0, 'success', 'Horas por contratar'],
        ];
    }

    #[DataProvider('brechas')]
    public function test_calcula_y_renderiza_la_brecha_en_pantalla_y_pdf(float $contrato, float $necesidad, float $brecha, string $tono, string $label): void
    {
        $resultado = DotacionEstablecimientoCalculator::contratoParvularia([
            ['titulo' => 'Pedagogía en Educación de Párvulos', 'horas_contrato' => $contrato],
        ], 771, $necesidad);
        $this->assertSame($brecha, $resultado['brecha_dotacion_parvularia']);

        $html = view('admin.dotacion-establecimiento.partials._brecha_parvularia', [
            'contratoEducacionParvulariaMasPie' => $necesidad,
            'horasContratoParvularia' => $contrato,
            'fmt' => fn ($horas) => DotacionEstablecimientoCalculator::formatHoras($horas),
        ])->render();
        $this->assertStringContainsString('Sobredotación Parvularia', $html);
        $this->assertStringContainsString($label, $html);
        $this->assertStringContainsString('fs-2 fw-bold text-'.$tono.'">'.abs($brecha).'</div>', $html);
        if ($brecha > 0) {
            $this->assertStringContainsString('contratación adicional de Educadora de Párvulos por '.abs($brecha).' horas', $html);
        } else {
            $this->assertStringNotContainsString('contratación adicional', $html);
        }

        $pdfHtml = view('admin.dotacion-establecimiento.pdf', [
            'establecimiento' => (object) ['rbd' => 'TEST', 'nombre_establecimiento' => 'Escuela de prueba', 'comuna' => 'Prueba'],
            'anio' => 2026,
            'cursos' => ['grupos' => ['parvularia' => ['totales' => ['cursos' => 2]]]],
            'resumen' => array_merge($resultado, [
                'horas_contrato_docentes' => 1017,
                'horas_contrato_docentes_aula' => 771,
                'horas_contrato_docente_pie' => 246,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => $necesidad,
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 390,
                'horas_dotacion_funciones_normativas' => 217,
            ]),
            'bloques' => [],
            'docentes' => collect(),
            'generatedBy' => null,
            'generatedAt' => now(),
        ])->render();
        $this->assertStringContainsString('Horas contrato parvularia', $pdfHtml);
        $this->assertStringContainsString('Sobredotación Parvularia', $pdfHtml);
        $this->assertStringContainsString(abs($brecha).' - '.$label, $pdfHtml);
        // La brecha general no compensa el déficit/excedente de Parvularia.
        $excesoGeneral = (771 - $contrato) - (390 + 217);
        $this->assertStringContainsString($excesoGeneral.' - Horas de sobredotación', $pdfHtml);
    }

    public function test_sin_parvularia_conserva_el_contrato_aula(): void
    {
        $resultado = DotacionEstablecimientoCalculator::contratoParvularia([], 771, 0);

        $this->assertSame(0.0, $resultado['horas_contrato_docentes_parvularia']);
        $this->assertSame(771.0, $resultado['horas_contrato_docentes_aula_general']);
        $this->assertSame(0.0, $resultado['brecha_dotacion_parvularia']);
    }
}
