<?php

namespace Tests\Unit;

use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DotacionProyeccionCalculator;
use App\Support\DocenteHorasNoLectivasCalculator;
use Illuminate\Support\Facades\DB;
use ReflectionProperty;
use Tests\TestCase;

class DotacionVacantesDetalleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        (require database_path('migrations/2026_05_25_183000_create_docente_horas_proporciones_table.php'))->up();
        (require database_path('migrations/2026_07_23_170000_sync_docente_horas_proporciones_cpeip.php'))->up();
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        parent::tearDown();
    }

    public function test_desglose_identifica_cursos_funciones_y_horas_sin_modificar_vacantes(): void
    {
        $base = self::base();
        $antes = serialize($base);
        $proyeccion = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $detalle = $proyeccion['reservas'][0]['asignaciones_referencia'];
        $this->assertCount(3, $detalle['items']);
        $this->assertSame(37.0, $detalle['total_contrato']); // 20 aula 65/35 -> 23 contrato + 14 funciones.
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
        $this->assertSame(3, $xpath->query($path.'//tbody/tr[@data-asignacion-referencia]')->length);
        foreach (['Lenguaje y Comunicación', 'Curso combinado', 'Jefe UTP', 'Taller histórico', 'Hrs pedagógicas', 'Hrs contrato', 'Total asignado 2026', '30 h conservadas'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }
        $this->assertSame('20', trim($xpath->evaluate('string('.$path.'//tfoot/tr/td[1])')));
        $this->assertSame('37', trim($xpath->evaluate('string('.$path.'//tfoot/tr/td[2])')));
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

    public function test_28_horas_aula_60_40_mas_3_pie_suman_38_contrato(): void
    {
        $base = self::base();
        $filas = [];
        foreach ([[2, 3], [3, 4], [3, 4], [8, 10], [6, 8], [1, 1], [2, 3], [2, 3], [1, 1]] as $i => [$aula, $contrato]) {
            $filas[] = (new DotacionDocenteAsignacion)->forceFill([
                'id' => $i + 10, 'docente_rut' => '11111111-1', 'tipo_asignacion' => 'plan_estudio',
                'asignatura_nombre' => 'Asignatura de ejemplo '.($i + 1), 'proporcion_aplicada' => '60/40',
                'horas_plan_pedagogicas' => $aula, 'horas_contrato' => $contrato,
            ]);
        }
        $filas[] = (new DotacionDocenteAsignacion)->forceFill([
            'id' => 20, 'docente_rut' => '11111111-1', 'tipo_asignacion' => 'pie_colaborativo',
            'asignatura_nombre' => 'Trabajo colaborativo PIE', 'horas_contrato' => 3,
        ]);
        $base['docentes'][0]['horas_contrato'] = 35.0;
        $base['docentes'][0]['asignaciones'] = $filas;
        $base['asignacion']['asignaciones'] = collect($filas);
        $antes = serialize($base);
        $p = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $detalle = $p['reservas'][0]['asignaciones_referencia'];
        $this->assertSame(40.0, (float) collect($filas)->sum('horas_contrato'));
        $this->assertSame(28.0, $detalle['total_pedagogicas']);
        $this->assertSame(35.0, $detalle['bloques_aula'][0]['horas_contrato']);
        $this->assertSame(38.0, $detalle['total_contrato']);
        $this->assertSame(35.0, $p['reservas'][0]['ya_contempladas']);
        $this->assertSame(0.0, $p['reservas'][0]['adicionales']);
        $this->assertSame(35.0, $p['horas_vacantes_por_cubrir']);
        $this->assertSame(35.0, $p['contratos']['total']);
        $this->assertSame($antes, serialize($base));
        $html = $this->render($base);
        $this->assertStringContainsString('10 registro(s) · 38 h contrato', $html);
        $this->assertStringContainsString('Total aula y conversión · 60/40', $html);
        $this->assertLessThan(strpos($html, 'Horas de contrato · PIE y funciones'), strpos($html, 'Total aula y conversión · 60/40'));
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $fila = '//section[@aria-labelledby="proyeccion-reservas"]/div/table/tbody/tr[1]';
        $this->assertSame('35', trim($xpath->evaluate('string('.$fila.'/td[3])')));
        $this->assertSame('38', trim($xpath->evaluate('string(//section[@aria-labelledby="proyeccion-reservas"]//details//tfoot/tr/td[2])')));
        $base['docentes'][0]['horas_contrato'] = 0.0;
        $sinVacantes = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $this->assertSame(0.0, $sinVacantes['reservas'][0]['ya_contempladas']);
        $this->assertSame(38.0, $sinVacantes['reservas'][0]['asignaciones_referencia']['total_contrato']);
    }

    public function test_incluidas_coincide_con_total_65_35_y_no_genera_adicional_por_conversion(): void
    {
        $base = self::base();
        $filas = [];
        $necesidades = [];
        foreach (range(1, 4) as $i) {
            $fila = (new DotacionDocenteAsignacion)->forceFill([
                'id' => 100 + $i, 'docente_rut' => '11111111-1', 'tipo_asignacion' => 'plan_estudio',
                'asignatura_nombre' => 'Asignatura de ejemplo', 'proporcion_aplicada' => '65/35',
                'horas_plan_pedagogicas' => 2, 'horas_contrato' => 2,
            ]);
            $filas[] = $fila;
            $necesidades[] = ['titulo' => 'Asignatura de ejemplo', 'horas_contrato_requeridas' => 2, 'asignaciones' => [$fila]];
        }
        $base['docentes'][0]['horas_contrato'] = 9.0;
        $base['docentes'][0]['asignaciones'] = $filas;
        $base['asignacion'] = ['asignaciones' => collect($filas), 'necesidades' => ['plan_estudio' => $necesidades]];
        $base['resumen'] = ['contrato_plan_general_mas_trabajo_colaborativo_pie' => 9];
        $p = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $reserva = $p['reservas'][0];
        $this->assertSame(9.0, $reserva['ya_contempladas']);
        $this->assertSame($reserva['asignaciones_referencia']['total_contrato'], $reserva['ya_contempladas']);
        $this->assertSame(0.0, $reserva['adicionales']);
        $this->assertSame(0.0, $p['necesarias_adicionales']['aula']);
        $this->assertSame(0.0, $p['brechas']['aula']);
        $this->assertSame(9.0, $p['horas_vacantes_por_cubrir']);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$this->render($base));
        $xpath = new \DOMXPath($dom);
        $fila = '//section[@aria-labelledby="proyeccion-reservas"]/div/table/tbody/tr[1]';
        $this->assertSame('9', trim($xpath->evaluate('string('.$fila.'/td[3])')));
        $this->assertSame('0', trim($xpath->evaluate('string('.$fila.'/td[4])')));

        // Solo el exceso sobre lo asignado y las necesidades vigentes es adicional.
        $base['docentes'][0]['horas_contrato'] = 12.0;
        $p = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false]);
        $this->assertSame(9.0, $p['reservas'][0]['ya_contempladas']);
        $this->assertSame(3.0, $p['reservas'][0]['adicionales']);
    }

    public function test_separa_proporciones_y_conserva_contratos_especiales_o_sin_datos(): void
    {
        $base = self::base();
        $filas = [];
        foreach ([['60/40', 2, 3], ['60_40', 2, 3], ['65/35', 3, 4], ['65-35', 3, 4], ['NT1/NT2', 2, 6], ['', null, 5]] as $i => [$proporcion, $aula, $contrato]) {
            $filas[] = (new DotacionDocenteAsignacion)->forceFill([
                'id' => $i + 10, 'docente_rut' => '11111111-1', 'tipo_asignacion' => 'plan_estudio',
                'proporcion_aplicada' => $proporcion, 'horas_plan_pedagogicas' => $aula, 'horas_contrato' => $contrato,
            ]);
        }
        $filas[] = (new DotacionDocenteAsignacion)->forceFill([
            'id' => 20, 'docente_rut' => '11111111-1', 'tipo_asignacion' => 'pie_colaborativo', 'horas_contrato' => 3,
        ]);
        $filas[] = (new DotacionDocenteAsignacion)->forceFill([
            'id' => 21, 'docente_rut' => '11111111-1', 'tipo_asignacion' => 'funcion_directiva', 'horas_contrato' => 4,
        ]);
        $base['asignacion']['asignaciones'] = collect($filas);
        $base['docentes'][0]['asignaciones'] = $filas;
        $detalle = DotacionProyeccionCalculator::build($base, 2026, ['111111111' => false])['reservas'][0]['asignaciones_referencia'];
        $this->assertSame([5.0, 7.0, 6.0, 5.0], array_column($detalle['bloques_aula'], 'horas_contrato'));
        $this->assertSame(12.0, $detalle['total_pedagogicas']);
        $this->assertSame(30.0, $detalle['total_contrato']);
        $this->assertCount(2, $detalle['contratos_directos']);
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
