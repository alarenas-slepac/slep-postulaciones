<?php

namespace Tests\Unit;

use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DotacionProyeccionCalculator;
use Tests\TestCase;

class DotacionVacantesDetalleTest extends TestCase
{
    public function test_desglose_identifica_cursos_funciones_y_horas_sin_modificar_vacantes(): void
    {
        $base = self::base();
        $antes = serialize($base);
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $detalle = $proyeccion['reservas'][0]['asignaciones_referencia'];
        $this->assertCount(3, $detalle['items']);
        $this->assertSame(44.0, $detalle['total_contrato']);
        $this->assertSame(20.0, $detalle['total_pedagogicas']);
        $this->assertSame('Lenguaje y Comunicación', $detalle['items'][0]['titulo']);
        $this->assertSame('1° y 2° Básico A · Curso combinado', $detalle['items'][0]['curso']);
        $this->assertSame('65/35', $detalle['items'][0]['proporcion']);
        $this->assertSame('SEP', $detalle['items'][0]['subvencion']);
        $this->assertFalse($detalle['items'][0]['sin_necesidad_vigente']);
        $this->assertSame('Jefe UTP', $detalle['items'][1]['titulo']);
        $this->assertSame('Normativa', $detalle['items'][1]['fuente']);
        $this->assertNull($detalle['items'][1]['horas_pedagogicas']);
        $this->assertTrue($detalle['items'][2]['sin_necesidad_vigente']);
        $this->assertSame('Taller histórico', $detalle['items'][2]['titulo']);
        $this->assertSame(30.0, $proyeccion['horas_vacantes_por_cubrir']);
        $this->assertSame(30.0, $proyeccion['contratos']['total']);
        $this->assertSame(0.0, $proyeccion['necesarias_adicionales']['aula']);
        $this->assertSame(0.0, $proyeccion['docentes'][0]['contrato_proyectado']);
        $this->assertSame($antes, serialize($base));
    }

    public function test_desplegable_cerrado_muestra_detalle_escapado_y_totales_con_unidades(): void
    {
        $base = self::base();
        $base['asignacion']['asignaciones'][0]->observacion = '<script>alert(1)</script>';
        $html = $this->render($base);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $path = '//section[@aria-labelledby="proyeccion-reservas"]//details';
        $this->assertSame(1, $xpath->query($path)->length);
        $this->assertFalse($xpath->evaluate('boolean('.$path.'/@open)'));
        $this->assertStringContainsString('Ver asignaciones 2026', $xpath->evaluate('string('.$path.'/summary)'));
        $this->assertSame(3, $xpath->query($path.'//tbody/tr')->length);
        foreach (['Lenguaje y Comunicación', 'Curso combinado', 'Jefe UTP', 'Taller histórico', 'Hrs pedagógicas', 'Hrs contrato', 'Total asignado 2026', '30 h conservadas'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertSame('20', trim($xpath->evaluate('string('.$path.'//tfoot/tr/td[1])')));
        $this->assertSame('44', trim($xpath->evaluate('string('.$path.'//tfoot/tr/td[2])')));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_sin_asignaciones_conserva_vacantes_e_informa_falta_de_referencia(): void
    {
        $base = self::base();
        $base['asignacion'] = ['asignaciones' => collect(), 'necesidades' => []];
        $base['docentes'][0]['asignaciones'] = [];
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $this->assertSame([], $proyeccion['reservas'][0]['asignaciones_referencia']['items']);
        $this->assertSame(0.0, $proyeccion['reservas'][0]['asignaciones_referencia']['total_contrato']);
        $this->assertSame(30.0, $proyeccion['horas_vacantes_por_cubrir']);
        $this->assertStringContainsString('No hay asignaciones registradas para este docente en 2026', $this->render($base));
    }

    public static function base(): array
    {
        $crear = fn ($id, $tipo, $nombre, $horas, $extras = []) => (new DotacionDocenteAsignacion)->forceFill(array_merge([
            'id' => $id, 'docente_rut' => '11.111.111-1', 'docente_nombre' => 'Docente de ejemplo',
            'tipo_asignacion' => $tipo, 'asignatura_nombre' => $nombre, 'horas_contrato' => $horas,
            'estamento_cobertura' => 'docente', 'estado' => 'activa', 'subvencion' => 'General',
        ], $extras));
        $plan = $crear(1, 'plan_estudio', 'Nombre histórico', 30, [
            'horas_plan_pedagogicas' => 20, 'proporcion_aplicada' => '65/35', 'subvencion' => 'SEP',
        ]);
        $utp = $crear(2, 'funcion_tecnico_pedagogica', 'Jefe UTP', 10);
        $huerfana = $crear(3, 'otra_funcion', 'Taller histórico', 4);
        $asistente = $crear(4, 'otra_funcion', 'Asignación asistente', 7, ['estamento_cobertura' => 'asistente']);
        $otro = $crear(5, 'otra_funcion', 'Otro docente', 9, ['docente_rut' => '22222222-2']);
        $inactiva = $crear(6, 'otra_funcion', 'Inactiva', 6, ['estado' => 'inactiva']);

        return [
            'resumen' => ['contrato_plan_general_mas_trabajo_colaborativo_pie' => 30, 'horas_dotacion_funciones_normativas' => 10],
            'docentes' => [[
                'rut' => '11111111-1', 'nombre' => 'Docente de ejemplo', 'titulo' => 'Profesor de Educación Básica',
                'horas_contrato' => 30.0, 'horas_contrato_base' => 44.0,
                'exclusion_docente' => ['horas' => 14.0, 'motivo_label' => 'Proceso BIR'],
                'asignaciones' => [$plan, $utp, $huerfana],
            ]],
            'asignacion' => [
                'asignaciones' => collect([$plan, $utp, $huerfana, $asistente, $otro, $inactiva, clone $plan]),
                'necesidades' => [
                    'plan_estudio' => [[
                        'titulo' => 'Lenguaje y Comunicación', 'curso_label' => '1° y 2° Básico A · Curso combinado',
                        'horas_contrato_requeridas' => 30, 'horas_plan_requeridas' => 20,
                        'asignaciones' => [clone $plan], 'fuente' => 'Plan de estudio',
                    ]],
                    'funciones' => [[
                        'titulo' => 'Jefe UTP', 'horas_contrato_requeridas' => 10, 'fuente' => 'Normativa',
                        'asignaciones' => [$utp],
                    ]],
                ],
            ],
        ];
    }

    private function render(array $base): string
    {
        return view('admin.dotacion-establecimiento.partials._proyeccion', [
            'establecimiento' => (new Establecimiento)->forceFill(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento de ejemplo']),
            'proyeccion' => DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]),
            'continuidadDisponible' => true, 'conservacionHorasDisponible' => true,
        ])->render();
    }
}
