<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Support\DotacionEtapasPendientes;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionEtapasPendientesTest extends IsolatedSecurityTestCase
{
    public function test_cada_etapa_incompleta_identifica_sus_datos_pendientes_sin_consultas_adicionales(): void
    {
        $proceso = $this->proceso();
        $original = $proceso;
        DB::enableQueryLog();
        $items = DotacionEtapasPendientes::porEtapa($proceso, $this->asignacion());
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame($original, $proceso);
        $this->assertCount(6, $items);
        $this->assertStringContainsString('1° Básico A', $items['planes'][0]['texto']);
        $this->assertStringContainsString('libre disposición', $items['planes'][1]['texto']);
        $this->assertStringContainsString('Matemática · Educación Básica · 1° Básico A', $items['subsectores'][0]['texto']);
        $this->assertStringContainsString('guardar la decisión', $items['combinaciones'][0]['texto']);
        $this->assertSame('Definir si se utilizará: Coordinación de prueba.', $items['normativas'][0]['texto']);
        $this->assertStringContainsString('falta definir el máximo', $items['maximos'][0]['texto']);
        $this->assertStringContainsString('máximo autorizado 20 h; se requieren 24 h', $items['maximos'][1]['texto']);
        $this->assertStringContainsString('faltan 4 h de contrato', $items['asignacion'][0]['texto']);
        $this->assertStringContainsString('Matemática: faltan 1,5 h aula', $items['asignacion'][1]['texto']);
    }

    public function test_completadas_no_muestran_pendientes_aunque_conserven_datos_historicos(): void
    {
        $proceso = $this->proceso();
        foreach ($proceso['pasos'] as &$paso) {
            $paso['completo'] = true;
        }
        unset($paso);
        $this->assertSame([], DotacionEtapasPendientes::porEtapa($proceso, $this->asignacion()));
        $this->assertStringNotContainsString('Qué falta para completar', $this->render($proceso));
        $this->assertStringContainsString('6 de 6 etapas completas', $this->render($proceso));
    }

    public function test_asignacion_distingue_aula_y_contrato_y_excluye_necesidades_no_obligatorias(): void
    {
        $proceso = $this->proceso();
        $asignacion = $this->asignacion();
        $proceso['need_blocks'] += ['pie:uno' => 'bloque_1', 'funcion:optativa' => 'bloque_1', 'funcion:no-usada' => 'bloque_1'];
        $proceso['funciones_normativas'][] = ['key' => 'funcion:no-usada', 'definida' => true, 'se_utilizara' => false];
        $asignacion['necesidades']['pie_colaborativo'] = [['key' => 'pie:uno', 'titulo' => 'Trabajo colaborativo PIE', 'horas_contrato_pendientes' => 2.25]];
        $asignacion['necesidades']['funciones'] = [
            ['key' => 'funcion:optativa', 'titulo' => 'Función no normativa', 'dotacion_funcion_id' => 8, 'horas_contrato_pendientes' => 10],
            ['key' => 'funcion:no-usada', 'titulo' => 'Función descartada', 'horas_contrato_pendientes' => 10],
        ];
        $asignacion['necesidades']['plan_estudio'][] = ['key' => 'plan:cubierto', 'titulo' => 'Asignatura cubierta', 'horas_plan_pendientes' => 0];
        $text = implode(' ', array_column(DotacionEtapasPendientes::porEtapa($proceso, $asignacion)['asignacion'], 'texto'));
        $this->assertStringContainsString('1,5 h aula', $text);
        $this->assertStringContainsString('Trabajo colaborativo PIE: faltan 2,25 h de contrato', $text);
        $this->assertStringNotContainsString('Función no normativa', $text);
        $this->assertStringNotContainsString('Función descartada', $text);
        $this->assertStringNotContainsString('Asignatura cubierta', $text);
    }

    public function test_maximos_distinguen_cero_configurado_y_valor_sin_definir(): void
    {
        $proceso = $this->proceso();
        $proceso['bloques']['bloque_1'] = ['label' => 'Sin necesidad', 'maximo' => 0, 'requeridas' => 0, 'maximo_insuficiente' => false];
        $items = DotacionEtapasPendientes::porEtapa($proceso)['maximos'];
        $this->assertCount(1, $items);
        $this->assertStringNotContainsString('Sin necesidad', $items[0]['texto']);
        $proceso['configuracion'] = (object) ['decision_combinacion' => 'combinaciones_configuradas'];
        $this->assertStringContainsString('al menos un grupo activo', DotacionEtapasPendientes::porEtapa($proceso)['combinaciones'][0]['texto']);
    }

    public function test_detalle_extenso_es_desplegable_y_escapa_texto_y_parametros(): void
    {
        $proceso = $this->proceso();
        $proceso['estado_planes']['pendientes'] = ['Curso <script>alert(1)</script>', 'Curso 2', 'Curso 3', 'Curso 4'];
        $html = $this->render($proceso);
        $this->assertStringContainsString('Ver 2 pendiente(s) más', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        preg_match('/href="([^"]*asig_buscar[^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches);
        $url = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('2027', $query['anio']);
        $this->assertSame('asignacion', $query['tab']);
        $this->assertSame('1', $query['asig_pendientes']);
        $this->assertSame('plan_estudio', $query['asig_seccion']);
        $this->assertSame('1° Básico A', $query['asig_curso']);
        $this->assertSame('Matemática', $query['asig_buscar']);
        $this->assertSame('dotacion-asignacion', parse_url($url, PHP_URL_FRAGMENT));
    }

    public function test_cinco_etapas_completas_dejan_visible_el_motivo_de_la_sexta(): void
    {
        $proceso = $this->proceso();
        foreach (['planes', 'subsectores', 'combinaciones', 'normativas', 'maximos'] as $key) {
            $proceso['pasos'][$key]['completo'] = true;
        }
        $html = $this->render($proceso);
        $this->assertStringContainsString('5 de 6 etapas completas', $html);
        $this->assertSame(1, substr_count($html, 'data-dotacion-etapa-pendientes='));
        $this->assertStringContainsString('data-dotacion-etapa-pendientes="asignacion"', $html);
        $this->assertStringContainsString('Matemática: faltan 1,5 h aula.', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="step"'));
    }

    public function test_respuestas_incompletas_siempre_dan_una_indicacion_y_respetan_roles_y_anio(): void
    {
        $proceso = ['aplica' => true, 'pasos' => ['planes' => ['label' => 'Planes de estudio', 'completo' => false]]];
        $html = $this->render($proceso, 'coordinador_gdp');
        $this->assertStringContainsString('Qué falta para completar', $html);
        $this->assertStringContainsString('Solicite la configuración', $html);
        $this->assertStringNotContainsString('href="'.route('admin.establecimiento-planes.index'), $html);
        $proceso['aplica'] = false;
        $this->assertSame('', trim($this->render($proceso)));
    }

    public function test_asociacion_epja_admite_asistentes_y_registro_no_disponible_se_explica(): void
    {
        $proceso = $this->proceso();
        $proceso['docentes_subsector']['asignaturas'][0]['nivel'] = 'epja';
        $items = DotacionEtapasPendientes::porEtapa($proceso);
        $this->assertStringContainsString('docente o asistente habilitado', $items['subsectores'][0]['texto']);
        $proceso['docentes_subsector']['disponible'] = false;
        $this->assertStringContainsString('Solicite su habilitación', DotacionEtapasPendientes::porEtapa($proceso)['subsectores'][0]['texto']);
        $proceso['estado_planes'] = ['total' => 0];
        $this->assertStringContainsString('cursos activos con matrícula', DotacionEtapasPendientes::porEtapa($proceso)['planes'][0]['texto']);
    }

    private function render(array $proceso, string $role = 'admin'): string
    {
        $establecimiento = new Establecimiento(['rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento de prueba']);
        $establecimiento->id = 99999;

        return view('admin.dotacion-establecimiento.partials._proceso_etapas', [
            'proceso2027' => $proceso, 'asignacion' => $this->asignacion(),
            'establecimiento' => $establecimiento, 'anio' => 2027, 'activeRole' => $role,
        ])->render();
    }

    private function proceso(): array
    {
        $labels = ['planes' => 'Planes de estudio', 'subsectores' => 'Docentes por asignatura',
            'combinaciones' => 'Combinación de cursos', 'normativas' => 'Definición de funciones normativas',
            'maximos' => 'Máximos por bloque', 'asignacion' => 'Asignación obligatoria'];

        return [
            'aplica' => true, 'pasos' => array_map(fn ($label) => ['label' => $label, 'completo' => false], $labels),
            'estado_planes' => ['total' => 2, 'pendientes' => ['1° Básico A'], 'libre_disposicion_pendiente' => 1],
            'docentes_subsector' => ['disponible' => true, 'asignaturas' => [
                ['nombre' => 'Matemática', 'nivel' => 'basica', 'cursos' => ['1° Básico A'], 'completo' => false],
                ['nombre' => 'Artes', 'nivel' => 'basica', 'completo' => true],
            ]],
            'funciones_normativas' => [['key' => 'funcion:uno', 'titulo' => 'Coordinación de prueba', 'definida' => false]],
            'bloques' => [
                'bloque_1' => ['label' => 'Plan general', 'maximo' => null, 'pendientes' => 4],
                'bloque_2' => ['label' => 'Educación Parvularia', 'maximo' => 20, 'requeridas' => 24, 'maximo_insuficiente' => true, 'pendientes' => 0],
            ],
            'need_blocks' => ['plan:uno' => 'bloque_1'],
        ];
    }

    private function asignacion(): array
    {
        return ['necesidades' => ['plan_estudio' => [[
            'key' => 'plan:uno', 'titulo' => 'Matemática', 'curso_label' => '1° Básico A', 'horas_plan_pendientes' => 1.5,
        ]]]];
    }
}
