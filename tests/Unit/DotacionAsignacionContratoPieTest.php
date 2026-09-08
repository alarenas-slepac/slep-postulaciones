<?php

namespace Tests\Unit;

use App\Exports\DotacionResumenSobredotacionExport;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionProfesionDocenteResolver;
use App\Support\DotacionSobredotacionCalculator;
use ReflectionMethod;
use Tests\TestCase;

class DotacionAsignacionContratoPieTest extends TestCase
{
    public function test_suma_contratos_completos_y_no_la_bolsa_asignada(): void
    {
        $asignaciones = collect([
            $this->asignacion('funcion_tecnico_pedagogica', 'pie', 'Coordinador(a) PIE', 10),
            $this->asignacion('funcion_tecnico_pedagogica', 'tecnico_pedagogica', 'Coordinación PIE', 4),
            $this->asignacion('pie_educadora_diferencial', 'bolsa_total', 'Educadoras diferenciales PIE', 22),
            $this->asignacion('pie_colaborativo', 'trabajo_colaborativo', 'Trabajo colaborativo PIE', 3),
            $this->asignacion('funcion_tecnico_pedagogica', 'tecnico_pedagogica', 'Orientación', 5),
            $this->asignacion('pie_educadora_diferencial', 'bolsa_total', 'Educadoras diferenciales PIE', 8, 'asistente'),
        ]);

        $method = new ReflectionMethod(DotacionAsignacionCalculator::class, 'resumenContratoDocentePie');
        $resultado = $method->invoke(null, $asignaciones, collect([
            ['titulo' => 'Pedagogía en Educación Diferencial', 'horas_contrato' => 44],
            ['titulo' => 'Educadora Diferencial', 'horas_contrato' => 40],
            ['titulo' => 'Pedagogía en Educación de Párvulos', 'horas_contrato' => 44],
            ['titulo' => 'Educación Diferencial', 'horas_contrato' => 44, 'estamento_cobertura' => 'asistente'],
        ]));

        $this->assertSame([
            'coordinacion_pie' => 14.0,
            'educadoras_diferenciales' => 84.0,
            'total' => 98.0,
        ], $resultado);
    }

    public function test_cuenta_sin_asignaciones_y_respeta_contrato_efectivo_y_titulo_declarado(): void
    {
        $method = new ReflectionMethod(DotacionAsignacionCalculator::class, 'resumenContratoDocentePie');
        $resultado = $method->invoke(null, collect(), collect([
            ['declaracion' => (object) ['nombre_titulo' => 'PEDAGOGIA EN EDUCACION DIFERENCIAL'], 'horas_contrato_base' => 44, 'horas_excluidas' => 10, 'horas_contrato' => 34],
            ['titulo' => 'Educador Diferencial', 'horas_contrato' => 0],
            ['titulo' => 'Educadora Diferencial', 'horas_contrato' => null],
            ['titulo' => 'Educadora Diferencial', 'horas_contrato' => -4],
            ['titulo' => 'Sin título declarado', 'horas_contrato' => 44],
            ['titulo' => 'Educadora Diferencial', 'declaracion' => (object) ['nombre_titulo' => 'Profesor de Matemática'], 'horas_contrato' => 44],
        ]));

        $this->assertSame(['coordinacion_pie' => 0.0, 'educadoras_diferenciales' => 34.0, 'total' => 34.0], $resultado);
    }

    public function test_coordinacion_del_diferencial_no_duplica_su_contrato_y_detalle_concilia(): void
    {
        $coordinacion = $this->asignacion('funcion_tecnico_pedagogica', 'pie', 'Coordinación PIE', 10);
        $coordinacion->docente_rut = '11.111.111-1';
        $bolsa = $this->asignacion('pie_educadora_diferencial', 'bolsa_total', 'PIE', 12);
        $bolsa->docente_rut_normalizado = '111111111';
        $docente = [
            'rut' => '11111111-1', 'nombre' => 'Docente de prueba',
            'titulo' => 'Educadora Diferencial', 'horas_contrato' => 44,
            'asignaciones' => [$coordinacion, $bolsa],
        ];
        $method = new ReflectionMethod(DotacionAsignacionCalculator::class, 'resumenContratoDocentePie');
        $resumen = $method->invoke(null, collect([$coordinacion, $bolsa, $bolsa]), collect([$docente]));
        $this->assertSame(['coordinacion_pie' => 0.0, 'educadoras_diferenciales' => 44.0, 'total' => 44.0], $resumen);

        $detalle = DotacionSobredotacionCalculator::build([$docente], [
            'horas_contrato_docente_pie' => $resumen['total'],
            'horas_contrato_pie_necesarias' => 30,
            'horas_contrato_docentes_aula' => 0,
        ]);
        $this->assertSame(44.0, $detalle['pie']['resumen']['horas_dotacion_total']);
        $this->assertSame(22.0, $detalle['pie']['resumen']['horas_asignadas_registradas']);
        $this->assertSame(14.0, $detalle['pie']['resumen']['horas_sobredotacion_total']);
        $this->assertFalse($detalle['pie']['resumen']['tiene_ajuste_no_asociado']);
        $this->assertSame(0.0, $detalle['aula']['resumen']['horas_dotacion_total']);
        $this->assertSame('11111111-1', $detalle['pie']['items']->sole()['rut']);
    }

    public function test_detalle_identifica_contrato_sin_asignaciones_y_no_lo_inventa_como_asignado(): void
    {
        $detalle = DotacionSobredotacionCalculator::build([
            ['rut' => '22222222-2', 'titulo' => 'Educador Diferencial', 'horas_contrato' => 44],
        ], ['horas_contrato_docente_pie' => 44, 'horas_contrato_pie_necesarias' => 30]);

        $this->assertSame(0.0, $detalle['pie']['resumen']['horas_asignadas_registradas']);
        $this->assertFalse($detalle['pie']['resumen']['tiene_ajuste_no_asociado']);
        $this->assertSame(14.0, $detalle['pie']['resumen']['horas_sobredotacion_total']);
        $this->assertSame(0.0, $detalle['aula']['resumen']['horas_dotacion_total']);
    }

    public function test_asignaciones_de_bolsa_sin_titulo_no_reemplazan_un_contrato_diferencial(): void
    {
        $bolsa = $this->asignacion('pie_educadora_diferencial', 'bolsa_total', 'PIE', 44);
        $coordinacion = $this->asignacion('funcion_tecnico_pedagogica', 'pie', 'Coordinación PIE', 10);
        $this->assertSame(10.0, DotacionAsignacionCalculator::contratoPiePorDocente([
            'titulo' => 'Profesor de Matemática', 'horas_contrato' => 44,
            'asignaciones' => [$bolsa, $coordinacion],
        ]));
    }

    public function test_reconoce_titulos_docentes_no_titulos_tecnicos_ni_menciones_ajenas(): void
    {
        foreach (['Pedagogía en Educación Diferencial', ' educación diferencial ', 'Educador/a Diferencial', 'Profesora de Educación Diferencial mención Lenguaje'] as $titulo) {
            $this->assertTrue(DotacionProfesionDocenteResolver::perfilTitulo(['titulo' => $titulo])['es_educacion_diferencial'], $titulo);
        }
        foreach (['Técnico en Educación Diferencial', 'Psicopedagogía', 'Profesor de Matemática', 'Pedagogía en Educación de Párvulos', 'Profesor de Básica con mención en Educación Diferencial', ''] as $titulo) {
            $this->assertFalse(DotacionProfesionDocenteResolver::perfilTitulo(['titulo' => $titulo])['es_educacion_diferencial'], $titulo);
        }
    }

    public function test_flag_especial_conserva_contratos_en_aula_y_cero_pie_en_detalle_y_excel(): void
    {
        $coordinacion = $this->asignacion('funcion_tecnico_pedagogica', 'pie', 'Coordinación PIE histórica', 10);
        $bolsa = $this->asignacion('pie_educadora_diferencial', 'bolsa_total', 'PIE histórico', 20);
        $docentes = collect([
            ['rut' => '11111111-1', 'titulo' => 'Educadora Diferencial', 'horas_contrato' => 44, 'asignaciones' => [$bolsa]],
            ['rut' => '22222222-2', 'titulo' => 'Educador Diferencial', 'horas_contrato_base' => 44, 'horas_contrato' => 34, 'asignaciones' => []],
            ['rut' => '33333333-3', 'titulo' => 'Profesor de Matemática', 'horas_contrato' => 44, 'asignaciones' => [$coordinacion]],
        ]);
        $method = new ReflectionMethod(DotacionAsignacionCalculator::class, 'resumenContratoDocentePie');
        foreach ([true, false, null] as $flag) {
            $establecimiento = new Establecimiento(['rbd' => 99999, 'nombre_establecimiento' => 'Escuela de prueba', 'especial' => $flag]);
            $pie = $method->invoke(null, collect([$coordinacion, $bolsa]), $docentes, (bool) $establecimiento->especial);
            $this->assertSame($flag ? 0.0 : 88.0, $pie['total']);
            $this->assertSame($flag ? 0.0 : 78.0, $pie['educadoras_diferenciales']);
            $this->assertSame($flag ? 0.0 : 10.0, $pie['coordinacion_pie']);
            $resumen = [
                'establecimiento_especial' => (bool) $establecimiento->especial,
                'horas_contrato_docentes' => 122.0,
                'horas_contrato_docentes_aula' => 122.0 - $pie['total'],
                'horas_contrato_docentes_aula_general' => 122.0 - $pie['total'],
                'horas_contrato_docente_pie' => $pie['total'],
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 100.0,
                'horas_contrato_pie_necesarias' => 0.0,
            ];
            $detalle = DotacionSobredotacionCalculator::build($docentes, $resumen);
            $this->assertSame($resumen['horas_contrato_docentes_aula'], $detalle['aula']['resumen']['horas_dotacion_total']);
            $this->assertSame($pie['total'], $detalle['pie']['resumen']['horas_dotacion_total']);
            $this->assertFalse($detalle['aula']['resumen']['tiene_ajuste_no_asociado']);
            $this->assertFalse($detalle['pie']['resumen']['tiene_ajuste_no_asociado']);

            $export = new DotacionResumenSobredotacionExport;
            $row = $export->row($establecimiento, $resumen);
            $this->assertSame($flag ? 122.0 : 34.0, $row[11]);
            $this->assertSame($pie['total'], $row[13]);
            $this->assertSame($flag ? -22.0 : 66.0, $row[14]);
            $this->assertEquals($flag ? 0.0 : -88.0, $row[16]);
            $book = $export->workbook(collect([$row]), 2026);
            $this->assertSame($flag ? 22.0 : 0.0, $book->getActiveSheet()->getCell('A4')->getValue());
            $this->assertSame($flag ? 0.0 : 88.0, $book->getActiveSheet()->getCell('L4')->getValue());
            $book->disconnectWorksheets();
        }
        $this->assertSame(10.0, (float) $coordinacion->horas_contrato);
        $this->assertSame(20.0, (float) $bolsa->horas_contrato);
    }

    public function test_pdf_explica_que_contratos_diferenciales_de_especial_estan_en_aula(): void
    {
        $html = view('admin.dotacion-establecimiento.pdf', [
            'establecimiento' => new Establecimiento(['rbd' => 99999, 'nombre_establecimiento' => 'Escuela de prueba', 'especial' => true]),
            'anio' => 2026, 'resumen' => ['establecimiento_especial' => true, 'horas_contrato_docentes' => 122, 'horas_contrato_docentes_aula' => 122, 'horas_contrato_docente_pie' => 0],
            'cursos' => [], 'bloques' => [], 'docentes' => collect(), 'generatedBy' => null, 'generatedAt' => now(),
        ])->render();
        $this->assertStringContainsString('No aplica por flag Especial.', $html);
        $this->assertStringContainsString('Los contratos de Educación Diferencial se contabilizan en Horas contrato aula.', $html);
    }

    private function asignacion(
        string $tipo,
        string $subtipo,
        string $nombre,
        float $horas,
        string $estamento = 'docente'
    ): DotacionDocenteAsignacion {
        return new DotacionDocenteAsignacion([
            'tipo_asignacion' => $tipo,
            'subtipo_asignacion' => $subtipo,
            'asignatura_nombre' => $nombre,
            'horas_contrato' => $horas,
            'estamento_cobertura' => $estamento,
        ]);
    }
}
