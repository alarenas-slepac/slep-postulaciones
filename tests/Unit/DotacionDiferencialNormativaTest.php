<?php

namespace Tests\Unit;

use App\Exports\DotacionResumenSobredotacionExport;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionProyeccionCalculator;
use App\Support\DotacionSobredotacionCalculator;
use Tests\TestCase;

class DotacionDiferencialNormativaTest extends TestCase
{
    public function test_reparte_solo_normativas_y_concilia_resumen_detalle_y_excel(): void
    {
        $asignaciones = [$this->asignacion(20), $this->asignacion(10, [
            'subtipo_asignacion' => 'pie', 'asignatura_nombre' => 'Coordinadora PIE',
        ])];
        $docente = $this->docente($asignaciones);
        $resumenPie = DotacionAsignacionCalculator::resumenContratoDocentePie(collect($asignaciones), collect([$docente]));
        $this->assertSame(['coordinacion_pie' => 0.0, 'educadoras_diferenciales' => 24.0, 'total' => 24.0], $resumenPie);
        $this->assertSame(24.0, DotacionAsignacionCalculator::contratoPiePorDocente($docente));
        $resumen = ['horas_contrato_docentes' => 44, 'horas_contrato_docentes_aula' => 20,
            'horas_contrato_docentes_aula_general' => 20, 'horas_contrato_docente_pie' => 24,
            'horas_dotacion_funciones_normativas' => 20, 'horas_contrato_pie_necesarias' => 24];
        $detalle = DotacionSobredotacionCalculator::build([$docente], $resumen);
        foreach (['aula' => 20.0, 'pie' => 24.0] as $categoria => $horas) {
            $this->assertSame($horas, $detalle[$categoria]['resumen']['horas_dotacion_total']);
            $this->assertFalse($detalle[$categoria]['resumen']['tiene_ajuste_no_asociado']);
        }
        $row = (new DotacionResumenSobredotacionExport)->row(new Establecimiento(['rbd' => 99999]), $resumen);
        $this->assertSame([44.0, 20.0, 0.0, 24.0], array_slice($row, 10, 4));
    }

    public function test_excluye_coordinacion_declaradas_inactivas_asistentes_y_otros_ruts(): void
    {
        $normativa = $this->asignacion(20);
        $asignaciones = [$normativa, $normativa,
            $this->asignacion(10, ['subtipo_asignacion' => 'pie']),
            $this->asignacion(10, ['asignatura_nombre' => 'Coordinación PIE histórica']),
            $this->asignacion(10, ['dotacion_funcion_id' => 5, 'dotacion_funcion_regla_id' => 3]),
            $this->asignacion(10, ['estado' => 'inactiva']),
            $this->asignacion(10, ['estamento_cobertura' => 'asistente']),
            $this->asignacion(10, ['docente_rut_normalizado' => '222222222']),
            $this->asignacion(10, ['tipo_asignacion' => 'plan_estudio']),
            $this->asignacion(10, ['tipo_asignacion' => 'pie_educadora_diferencial']),
        ];
        $docente = $this->docente($asignaciones);
        $this->assertSame(24.0, DotacionAsignacionCalculator::contratoPiePorDocente($docente));
        // El resumen también funciona cuando solo recibe asignaciones a nivel establecimiento.
        unset($docente['asignaciones']);
        $this->assertSame(24.0, DotacionAsignacionCalculator::resumenContratoDocentePie(collect($asignaciones), collect([$docente]))['total']);
    }

    public function test_reparto_respeta_contrato_efectivo_decimales_y_funciones_normativas(): void
    {
        foreach (['funcion_directiva', 'funcion_tecnico_pedagogica', 'plan_normativo', 'otra_funcion'] as $tipo) {
            foreach ([[44, 44, 0], [34, 20, 14], [34, 44, 0], [34, 60, 0], [0, 20, 0], [34, 19.37, 14.63]] as [$contrato, $normativas, $pie]) {
                $docente = $this->docente([$this->asignacion($normativas, ['tipo_asignacion' => $tipo])], $contrato);
                $docente['exclusion_docente'] = ['motivo' => 'fuero_maternal', 'horas' => 44 - $contrato];
                $this->assertSame((float) $pie, DotacionAsignacionCalculator::contratoPiePorDocente($docente));
                $this->assertSame((float) $pie, DotacionAsignacionCalculator::resumenContratoDocentePie(collect($docente['asignaciones']), collect([$docente]))['total']);
                $this->assertSame(44.0, $docente['horas_contrato_base']);
            }
        }
    }

    public function test_proyeccion_conserva_reparto_vacantes_y_no_duplica_necesidades(): void
    {
        $utp = $this->asignacion(20);
        $pie = $this->asignacion(24, ['tipo_asignacion' => 'pie_educadora_diferencial']);
        $base = ['docentes' => [$this->docente([$utp, $pie])],
            'resumen' => ['horas_dotacion_funciones_normativas' => 44, 'horas_contrato_pie_necesarias' => 24],
            'asignacion' => ['asignaciones' => collect([$utp, $pie]), 'necesidades' => [
                'funciones' => [['titulo' => 'Jefe UTP', 'horas_contrato_requeridas' => 44, 'asignaciones' => [$utp]]],
                'pie_educadora_diferencial' => [['titulo' => 'Educación Diferencial', 'horas_contrato_requeridas' => 24, 'asignaciones' => [$pie]]],
            ]]];
        $antes = serialize($base);
        foreach ([true, false] as $continua) {
            $p = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => $continua]);
            $this->assertSame(['total' => 44.0, 'aula' => 20.0, 'parvularia' => 0.0, 'pie' => 24.0], $p['contratos']);
            $this->assertSame($p['contratos'], $p['contratos_base']);
            $this->assertSame($continua ? 0.0 : 44.0, $p['horas_vacantes_por_cubrir']);
            $this->assertSame(['aula' => 0.0, 'parvularia' => 0.0, 'pie' => 0.0], $p['necesarias_adicionales']);
            if (! $continua) {
                $this->assertSame($p['contratos'], $p['contratos_vacantes']);
                $this->assertSame(44.0, $p['reservas'][0]['ya_contempladas']);
                $this->assertSame(0.0, $p['contratos_cubiertos']['total']);
                $html = view('admin.dotacion-establecimiento.partials._proyeccion', [
                    'establecimiento' => (new Establecimiento)->forceFill(['id' => 1, 'rbd' => 99999]), 'proyeccion' => $p,
                ])->render();
                $this->assertStringContainsString('Aula · plan general y funciones normativas: 20 h', $html);
                $this->assertStringContainsString('Docente PIE: 24 h', $html);
            }
        }
        $this->assertSame($antes, serialize($base));
        unset($base['asignacion']['necesidades']['pie_educadora_diferencial']);
        $p = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $this->assertSame(['aula' => 0.0, 'parvularia' => 0.0, 'pie' => 24.0], $p['necesarias_adicionales']);
        $this->assertSame(20.0, $p['reservas'][0]['ya_contempladas']);
        $p = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false], ['111111111' => false]);
        $this->assertSame(0.0, $p['contratos']['total']);
        $this->assertSame(0.0, $p['horas_vacantes_por_cubrir']);
    }

    public function test_coordinacion_parcial_de_otro_titulo_conserva_ambas_categorias(): void
    {
        $coordinacion = $this->asignacion(10, ['subtipo_asignacion' => 'pie']);
        $plan = $this->asignacion(34, ['tipo_asignacion' => 'plan_estudio']);
        $docente = $this->docente([$coordinacion, $plan]);
        $docente['titulo'] = 'Profesor de Educación Básica';
        $base = ['docentes' => [$docente], 'asignacion' => ['asignaciones' => collect([$coordinacion, $plan]), 'necesidades' => [
            'funciones' => [['horas_contrato_requeridas' => 10, 'asignaciones' => [$coordinacion]]],
            'plan_estudio' => [['horas_contrato_requeridas' => 34, 'asignaciones' => [$plan]]],
        ]]];
        $p = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $this->assertSame(['total' => 44.0, 'aula' => 34.0, 'parvularia' => 0.0, 'pie' => 10.0], $p['contratos_vacantes']);
        $this->assertSame(['aula' => 0.0, 'parvularia' => 0.0, 'pie' => 0.0], $p['necesarias_adicionales']);
    }

    private function docente(array $asignaciones, float $contrato = 44): array
    {
        return ['rut' => '11.111.111-1', 'nombre' => 'Docente sintética',
            'titulo' => 'Pedagogía en Educación Diferencial', 'horas_contrato' => $contrato,
            'horas_contrato_base' => 44.0, 'asignaciones' => $asignaciones];
    }

    private function asignacion(float $horas, array $atributos = []): DotacionDocenteAsignacion
    {
        return new DotacionDocenteAsignacion(array_replace([
            'docente_rut_normalizado' => '111111111', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'funcion_tecnico_pedagogica', 'subtipo_asignacion' => 'tecnico_pedagogica',
            'asignatura_nombre' => 'Jefe UTP', 'horas_contrato' => $horas, 'estado' => 'activa',
        ], $atributos));
    }
}
