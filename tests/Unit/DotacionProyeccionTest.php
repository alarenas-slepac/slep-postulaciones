<?php

namespace Tests\Unit;

use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DotacionProyeccionCalculator;
use Tests\TestCase;

class DotacionProyeccionTest extends TestCase
{
    public function test_bir_conserva_contrato_vacante_y_retira_cobertura_sin_quitar_necesidad_normativa(): void
    {
        $base = $this->base();
        $antes = serialize($base);
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);

        $this->assertSame(2027, $proyeccion['anio_proyeccion']);
        $this->assertSame(44.0, $proyeccion['necesarias']['funciones_normativas']);
        $this->assertSame(44.0, $proyeccion['necesarias']['plan_general']);
        $this->assertSame(88.0, $proyeccion['contratos']['total']);
        $this->assertSame(44.0, $proyeccion['contratos_cubiertos']['total']);
        $this->assertSame(44.0, $proyeccion['contratos_vacantes']['total']);
        $this->assertSame(0.0, $proyeccion['brechas']['aula']);
        $utp = $proyeccion['coberturas']['funciones'][0];
        $this->assertSame(44.0, $utp['requeridas']);
        $this->assertSame(44.0, $utp['asignadas_base']);
        $this->assertSame(0.0, $utp['asignadas_proyectadas']);
        $this->assertSame(44.0, $utp['pendientes']);
        $this->assertSame(0.0, $proyeccion['docentes'][0]['contrato_proyectado']);
        $this->assertSame(44.0, $proyeccion['docentes'][0]['contrato_considerado']);
        $this->assertSame($antes, serialize($base));
    }

    public function test_plan_de_estudio_conserva_horas_pedagogicas_y_necesidad_contractual(): void
    {
        $base = $this->base();
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['222222222' => false]);
        $plan = $proyeccion['coberturas']['plan_estudio'][0];
        $this->assertSame('h pedagógicas', $plan['unidad']);
        $this->assertSame(30.0, $plan['requeridas']);
        $this->assertSame(30.0, $plan['asignadas_base']);
        $this->assertSame(0.0, $plan['asignadas_proyectadas']);
        $this->assertSame(30.0, $plan['pendientes']);
        $this->assertSame(44.0, $proyeccion['necesarias']['plan_general']);
        $this->assertSame(0.0, $proyeccion['coberturas']['funciones'][0]['pendientes']);
    }

    public function test_conserva_otras_coberturas_y_no_compensa_necesidades_distintas(): void
    {
        $base = $this->base();
        $otra = $this->asignacion('222222222', 'Docente que continúa', 'funcion_tecnico_pedagogica', 12);
        $base['asignacion']['asignaciones']->push($otra);
        $base['asignacion']['necesidades']['funciones'][0]['asignaciones']->push($otra);
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $utp = $proyeccion['coberturas']['funciones'][0];
        $this->assertSame(12.0, $utp['asignadas_proyectadas']);
        $this->assertSame(32.0, $utp['pendientes']);
        $this->assertSame(['Docente que continúa'], $utp['personas']);

        $otra->horas_contrato = 44;
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $this->assertSame(0.0, $proyeccion['coberturas']['funciones'][0]['pendientes']);
    }

    public function test_sin_decisiones_o_con_check_marcado_conserva_todo_y_permite_revertir(): void
    {
        $base = $this->base();
        $original = DotacionProyeccionCalculator::build($base, 2026, []);
        $marcados = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => true, '222222222' => true]);
        $this->assertSame($original, $marcados);
        $this->assertSame(88.0, $original['contratos']['total']);
        $this->assertSame(2, $original['docentes_continuan']);
        DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false, '222222222' => false]);
        $this->assertSame($original, DotacionProyeccionCalculator::build($base, 2026, []));
    }

    public function test_excluye_por_persona_no_por_cantidad_y_preserva_cobertura_de_asistentes(): void
    {
        $base = $this->base();
        $base['docentes'][0]['horas_contrato'] = 20.0;
        $asistente = $this->asignacion('111111111', 'Asistente sintético', 'funcion_tecnico_pedagogica', 6);
        $asistente->estamento_cobertura = 'asistente';
        $base['asignacion']['asignaciones']->push($asistente);
        $base['asignacion']['necesidades']['funciones'][0]['asignaciones']->push($asistente);
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $this->assertSame(64.0, $proyeccion['contratos_base']['total']);
        $this->assertSame(64.0, $proyeccion['contratos']['total']);
        $this->assertSame(44.0, $proyeccion['contratos_cubiertos']['total']);
        $utp = $proyeccion['coberturas']['funciones'][0];
        $this->assertSame(44.0, $utp['cobertura_retirada']);
        $this->assertSame(6.0, $utp['asignadas_proyectadas']);
        $this->assertSame(38.0, $utp['pendientes']);
    }

    public function test_separa_parvularia_y_pie_y_respeta_establecimientos_especiales(): void
    {
        $base = $this->base();
        $base['docentes'][0]['titulo'] = 'Pedagogía en Educación de Párvulos';
        $base['docentes'][1]['titulo'] = 'Educadora Diferencial';
        $base['resumen']['contrato_educacion_parvularia_mas_trabajo_colaborativo_pie'] = 55;
        $base['resumen']['horas_contrato_pie_necesarias'] = 44;
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $this->assertSame(44.0, $proyeccion['contratos']['parvularia']);
        $this->assertSame(44.0, $proyeccion['contratos']['pie']);
        $this->assertSame(11.0, $proyeccion['brechas']['parvularia']);
        $base['resumen']['establecimiento_especial'] = true;
        $especial = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $this->assertSame(0.0, $especial['contratos']['pie']);
        $this->assertSame(44.0, $especial['contratos']['aula']);
    }

    public function test_vista_completa_muestra_anios_unidades_y_continuidad_sin_formularios_de_asignacion(): void
    {
        $proyeccion = DotacionProyeccionCalculator::build($this->base(), 2026, ['111111111' => false]);
        $html = view('admin.dotacion-establecimiento.partials._proyeccion', [
            'establecimiento' => (new Establecimiento)->forceFill(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']),
            'proyeccion' => $proyeccion, 'continuidadDisponible' => true,
        ])->render();
        foreach (['Proyección dotación 2027', 'Jefe UTP', 'Proceso BIR', 'Plan de estudio', 'h pedagógicas', 'h contrato', 'No continúa', 'Cobertura 2027', 'Pendientes'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringContainsString('anio=2026', $html);
    }

    public function test_vista_incluye_34_vacantes_en_contrato_2027_sin_cobertura_de_la_persona(): void
    {
        $base = $this->base();
        $base['docentes'] = [$base['docentes'][0]];
        $base['docentes'][0]['horas_contrato'] = 34.0;
        $base['docentes'][0]['exclusion_docente']['horas'] = 10.0;
        $base['resumen']['contrato_plan_general_mas_trabajo_colaborativo_pie'] = 0;
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false], ['111111111' => true]);
        $this->assertSame(34.0, $proyeccion['contratos']['total']);
        $this->assertSame(34.0, $proyeccion['horas_vacantes_por_cubrir']);
        $this->assertSame(44.0, $proyeccion['necesarias']['funciones_normativas']);
        $this->assertSame(0.0, $proyeccion['necesarias_adicionales']['aula']);
        $this->assertSame(10.0, $proyeccion['brechas']['aula']);
        $this->assertSame(0.0, $proyeccion['coberturas']['funciones'][0]['asignadas_proyectadas']);
        $html = view('admin.dotacion-establecimiento.partials._proyeccion', [
            'establecimiento' => (new Establecimiento)->forceFill(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']),
            'proyeccion' => $proyeccion, 'continuidadDisponible' => true, 'conservacionHorasDisponible' => true,
        ])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame('34', trim($xpath->evaluate('string(//div[contains(., "Horas de contrato proyectadas") and contains(@class, "h-100")]/div[last()])')));
        $this->assertSame('34', trim($xpath->evaluate('string(//div[contains(., "Hrs vacantes por cubrir 2027") and contains(@class, "h-100")]/div[last()])')));
        $this->assertSame('34', trim($xpath->evaluate('string((//dt[normalize-space()="Horas contrato 2027"]/following-sibling::dd[1])[1])')));
        $this->assertSame('0', trim($xpath->evaluate('string((//dt[normalize-space()="De docentes que continúan"]/following-sibling::dd[1])[1])')));
        $this->assertSame('34', trim($xpath->evaluate('string((//dt[normalize-space()="Incluye vacantes por cubrir"]/following-sibling::dd[1])[1])')));
        $this->assertStringContainsString('No continúa', $html);
    }

    private function base(): array
    {
        $utp = $this->asignacion('111111111', 'Jefatura sintética', 'funcion_tecnico_pedagogica', 44);
        $plan = $this->asignacion('222222222', 'Docente que continúa', 'plan_estudio', 44, 30);

        return [
            'resumen' => [
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 44,
                'horas_dotacion_funciones_normativas' => 44,
                'horas_dotacion_funciones_declaradas' => 0,
                'horas_contrato_docentes' => 88,
            ],
            'docentes' => [
                ['rut' => '11.111.111-1', 'nombre' => 'Jefatura sintética', 'funcion' => 'Jefe UTP', 'titulo' => 'Profesor de Educación Básica', 'horas_contrato' => 44.0, 'horas_contrato_base' => 44.0, 'asignaciones' => [$utp], 'exclusion_docente' => ['horas' => 0, 'motivo_label' => 'Proceso BIR']],
                ['rut' => '22222222-2', 'nombre' => 'Docente que continúa', 'funcion' => 'Docente aula', 'titulo' => 'Profesor de Educación Básica', 'horas_contrato' => 44.0, 'asignaciones' => [$plan]],
            ],
            'asignacion' => [
                'asignaciones' => collect([$utp, $plan]),
                'necesidades' => [
                    'funciones' => [['titulo' => 'Jefe UTP', 'horas_contrato_requeridas' => 44, 'fuente' => 'Normativa', 'asignaciones' => collect([$utp])]],
                    'plan_estudio' => [['titulo' => 'Plan de estudio', 'curso_label' => '1° Básico A', 'horas_plan_requeridas' => 30, 'horas_contrato_requeridas' => 44, 'asignaciones' => collect([$plan])]],
                ],
            ],
        ];
    }

    public function test_conservar_necesidad_utp_no_reincorpora_persona_ni_duplica_las_44_horas(): void
    {
        $base = $this->base();
        $base['asignacion']['necesidades']['funciones'][0]['asignaciones'][0]->horas_contrato = 5;
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false], ['111111111' => true]);
        $this->assertFalse($proyeccion['docentes'][0]['continua']);
        $this->assertSame(0.0, $proyeccion['docentes'][0]['contrato_proyectado']);
        $this->assertSame(44.0, $proyeccion['necesarias']['funciones_normativas']);
        $this->assertSame(44.0, $proyeccion['horas_vacantes_por_cubrir']);
        $this->assertSame(0.0, $proyeccion['necesarias_adicionales']['aula']);
        $this->assertSame(5.0, $proyeccion['reservas'][0]['ya_contempladas']);
        $this->assertSame(88.0, $proyeccion['contratos']['total']);
        $sinReserva = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false], ['111111111' => false]);
        $this->assertFalse($sinReserva['docentes'][0]['continua']);
        $this->assertSame(0.0, $sinReserva['horas_vacantes_por_cubrir']);
        $this->assertSame(44.0, $sinReserva['necesarias']['funciones_normativas']);
        $this->assertSame(44.0, $sinReserva['contratos']['total']);
        $this->assertSame(44.0, $sinReserva['brechas']['aula']);
        $continua = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => true], ['111111111' => true]);
        $this->assertSame(44.0, $continua['docentes'][0]['contrato_proyectado']);
        $this->assertSame(0.0, $continua['horas_vacantes_por_cubrir']);
    }

    public function test_horas_conservadas_sin_necesidad_previa_incrementan_su_categoria(): void
    {
        foreach ([['Profesor de Educación Básica', false, 'aula'], ['Pedagogía en Educación de Párvulos', false, 'parvularia'], ['Educadora Diferencial', false, 'pie'], ['Educadora Diferencial', true, 'aula']] as [$titulo, $especial, $categoria]) {
            $base = $this->base();
            $base['docentes'] = [$base['docentes'][0]];
            $base['docentes'][0]['titulo'] = $titulo;
            $base['docentes'][0]['horas_contrato'] = 20.0;
            $base['docentes'][0]['exclusion_docente']['horas'] = 24.0;
            $base['resumen'] = ['establecimiento_especial' => $especial];
            $base['asignacion']['necesidades'] = [];
            // Esta prueba cubre contratos sin funciones normativas asignadas.
            $base['docentes'][0]['asignaciones'] = [];
            $base['asignacion']['asignaciones'] = collect();
            $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false], ['111111111' => true]);
            $this->assertSame(20.0, $proyeccion['horas_vacantes_por_cubrir']);
            $this->assertSame(20.0, $proyeccion['necesarias_adicionales'][$categoria]);
            $this->assertSame(0.0, $proyeccion['brechas'][$categoria]);
            $this->assertSame(20.0, $proyeccion['contratos']['total']);
            $this->assertSame(20.0, $proyeccion['contratos'][$categoria]);
            $this->assertSame(0.0, $proyeccion['contratos_cubiertos']['total']);
            $this->assertSame(44.0, $proyeccion['docentes'][0]['contrato_base']);
        }
    }

    public function test_necesidad_compartida_se_descuenta_una_sola_vez_y_plan_no_se_duplica(): void
    {
        $base = $this->base();
        $base['asignacion']['necesidades']['plan_estudio'] = [];
        $base['asignacion']['necesidades']['funciones'][0]['asignaciones']->push($base['asignacion']['asignaciones'][1]);
        $base['resumen']['contrato_plan_general_mas_trabajo_colaborativo_pie'] = 0;
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false, '222222222' => false]);
        $this->assertSame(88.0, $proyeccion['horas_vacantes_por_cubrir']);
        $this->assertSame(0.0, $proyeccion['necesarias_adicionales']['aula']);
        $this->assertSame(-44.0, $proyeccion['brechas']['aula']);
        $this->assertSame(88.0, $proyeccion['contratos']['total']);
        $plan = DotacionProyeccionCalculator::build($this->base(), 2026, ['222222222' => false]);
        $this->assertSame(44.0, $plan['horas_vacantes_por_cubrir']);
        $this->assertSame(0.0, $plan['necesarias_adicionales']['aula']);
        $this->assertSame(44.0, $plan['necesarias']['plan_general']);
        $this->assertSame(30.0, $plan['coberturas']['plan_estudio'][0]['pendientes']);
    }

    private function asignacion(string $rut, string $nombre, string $tipo, float $contrato, float $aula = 0): DotacionDocenteAsignacion
    {
        return new DotacionDocenteAsignacion([
            'docente_rut' => $rut, 'docente_nombre' => $nombre, 'tipo_asignacion' => $tipo,
            'horas_contrato' => $contrato, 'horas_plan_pedagogicas' => $aula,
            'estamento_cobertura' => 'docente',
        ]);
    }
}
