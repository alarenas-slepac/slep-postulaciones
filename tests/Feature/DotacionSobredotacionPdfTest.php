<?php

namespace Tests\Feature;

use App\Exports\DotacionSobredotacionPdfExport;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Models\User;
use App\Services\Dotacion\EdicionBloqueoService;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProfesionDocenteResolver;
use App\Support\DotacionSobredotacionInforme;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionSobredotacionPdfTest extends IsolatedSecurityTestCase
{
    protected Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
        $this->withoutMiddleware([EnsureModuleAccess::class, CoordinarEscrituraPadron::class]);
        $this->withoutVite();
        (require database_path('migrations/2026_05_25_183000_create_docente_horas_proporciones_table.php'))->up();
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna'); $t->boolean('sala_cuna')->default(false);
        });
        foreach ([1 => false, 2 => false, 3 => true] as $id => $cuna) {
            DB::table('establecimientos')->insert(['id' => $id, 'rbd' => 100000 - $id,
                'nombre_establecimiento' => 'Establecimiento sintético '.$id, 'comuna' => 'Comuna de prueba', 'sala_cuna' => $cuna]);
        }
        foreach ([4 => 'funcionario_directivo_estab', 5 => 'coordinador_uatp', 6 => 'coordinador_gdp', 7 => 'supervisor_plani', 8 => 'funcionario_slep'] as $id => $rol) {
            DB::table('roles')->insert(['id' => $id, 'name' => $rol, 'guard_name' => 'web']);
        }
        $this->ee = Establecimiento::findOrFail(1);
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) { (new \ReflectionProperty($class, $property))->setValue(null, []); }
        }
        (new \ReflectionProperty(\App\Support\DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        \App\Support\DocenteHorasNoLectivasCalculator::clearExceptionCache();
        \App\Support\DotacionCursoCombinadoCalculator::clearCache();
    }

    protected function docente(array $changes = []): array
    {
        return array_replace(['rut' => '99000001-K', 'nombre' => 'Docente inicial sintético', 'titulo' => 'Pedagogía sintética',
            'horas_planta' => 40, 'horas_contrata' => 4, 'tipo_contrato' => 'Mixto', 'funcion' => 'Docencia',
            'tramo' => 'Inicial', 'fecha_antiguedad' => '2010-01-01', 'anio' => 2026, 'mes' => 10,
            'horas_contrato_base' => 44, 'horas_contrato' => 44, 'horas_aula' => 20,
            'horas_asignadas_protegidas' => 30, 'horas_asignadas_total' => 30, 'horas_contrato_pie' => 0,
            'horas_reservadas_no_normativas' => 0, 'diferencia' => 14, 'asignaciones' => []], $changes);
    }

    protected function registro(string $rut, string $bloque, string $tipo, float $horas, string $texto): DotacionSobredotacionJustificacion
    {
        $registro = new DotacionSobredotacionJustificacion(['establecimiento_id' => 1, 'anio' => 2027,
            'docente_rut_normalizado' => $rut, 'bloque' => $bloque, 'tipo_horas' => $tipo,
            'horas_detectadas' => $horas, 'justificacion' => $texto]);
        $registro->forceFill(['id' => 1, 'updated_at' => '2026-11-03 10:00:00']);
        $registro->exists = true;
        return $registro;
    }

    protected function informe(?array $docentes = null, ?\Illuminate\Support\Collection $registros = null, bool $ready = true, array $proceso = []): array
    {
        $docentes ??= [
            $this->docente(),
            $this->docente(['rut' => '99000002-K', 'nombre' => 'Docente experto sintético', 'tramo' => 'Experto 2']),
            $this->docente(['rut' => '99000003-K', 'nombre' => 'Educadora sintética', 'titulo' => DotacionProfesionDocenteResolver::TITULO_EDUCACION_PARVULOS]),
            $this->docente(['rut' => '99000004-K', 'nombre' => 'Diferencial sintética', 'titulo' => 'Educación Diferencial',
                'horas_contrato_pie' => 44, 'horas_asignadas_protegidas' => 0, 'horas_asignadas_total' => 0]),
            $this->docente(['rut' => '99000005-K', 'nombre' => 'Protegida sintética', 'exclusion_docente' => ['motivo' => 'fuero_maternal']]),
            $this->docente(['rut' => '99000006-K', 'nombre' => 'Sobreasignado sintético', 'horas_asignadas_protegidas' => 46, 'horas_asignadas_total' => 46]),
            $this->docente(['rut' => '99000007-K', 'nombre' => 'Función sintética', 'horas_declaradas_ajustables' => 4,
                'horas_asignadas_total' => 34, 'horas_declaradas_detalle' => [['tipo' => 'otra_funcion', 'tipo_label' => 'Otra función',
                    'nombre' => 'Actividad declarada sintética', 'subtipo_label' => 'Apoyo', 'subvencion' => 'General', 'horas' => 4]]]),
        ];
        $resumen = ['horas_contrato_docentes_aula' => 264, 'horas_contrato_docentes_aula_general' => 220,
            'horas_contrato_docentes_parvularia' => 44, 'horas_contrato_docente_pie' => 44,
            'horas_contrato_pie_necesarias' => 30, 'contrato_plan_mas_trabajo_colaborativo_pie' => 200,
            'contrato_plan_general_mas_trabajo_colaborativo_pie' => 160, 'horas_dotacion_funciones_normativas' => 20,
            'horas_dotacion_funciones_declaradas' => 4];
        $data = ['docentes' => collect($docentes), 'resumen' => $resumen];
        return (new DotacionSobredotacionInforme)->preparar($data, array_replace(['aplica' => false, 'docentes' => collect($docentes)], $proceso), $registros ?? collect(), $ready);
    }

    public function test_todos_los_bloques_conservan_prelacion_y_detalle_sin_mezclar_protegidos_sobrecargas_o_funciones(): void
    {
        $informe = $this->informe();
        $this->assertSame(['plan_estudio', 'parvularia', 'pie'], array_keys($informe['bloques']));
        $plan = $informe['bloques']['plan_estudio'];
        $this->assertSame(['Docente experto sintético', 'Docente inicial sintético', 'Función sintética'], $plan['filas']->pluck('nombre')->all());
        $this->assertSame(['Educadora sintética'], $informe['bloques']['parvularia']['filas']->pluck('nombre')->all());
        $this->assertSame(['Diferencial sintética'], $informe['bloques']['pie']['filas']->pluck('nombre')->all());
        $this->assertSame(['Protegida sintética'], $plan['protegidos']->pluck('nombre')->all());
        $this->assertSame(['Sobreasignado sintético'], $plan['sobrecargas']->pluck('nombre')->all());
        $this->assertSame(['Función sintética'], $plan['ajustes']->pluck('nombre')->all());
        $this->assertSame('Actividad declarada sintética', $plan['ajustes']->first()['horas_declaradas_detalle'][0]['nombre']);
        $this->assertEquals(38, $plan['totales']['saldo_bloque']);
        $this->assertSame('10/2026', $informe['periodos_padron']);
    }

    public function test_fundamentos_vigentes_desactualizados_y_sin_saldo_se_conservan_por_calidad_y_bloque(): void
    {
        $informe = $this->informe(registros: collect([
            $this->registro('99.000.001-K', 'plan_estudio', 'titular', 10, 'Fundamento vigente completo.'),
            $this->registro('99000001-K', 'plan_estudio', 'contrata', 8, 'Fundamento anterior completo.'),
            $this->registro('99000005-K', 'parvularia', 'contrata', 3, 'Registro anterior sin saldo.'),
            $this->registro('99000009-K', 'historico', 'titular', 2, 'Registro histórico completo.'),
        ]));
        $fila = $informe['bloques']['plan_estudio']['filas']->firstWhere('rut', '99000001-K');
        $this->assertSame('Vigente', $fila['estado_titular']);
        $this->assertStringContainsString('Desactualizada', $fila['estado_contrata']);
        $this->assertSame('Fundamento anterior completo.', $fila['justificacion_contrata']);
        $this->assertEquals(10, $fila['con_justificacion']); $this->assertEquals(4, $fila['sin_justificacion']);
        $this->assertSame('03-11-2026 10:00', $fila['actualizada_titular']);
        $this->assertSame('Registro anterior sin saldo.', $informe['bloques']['parvularia']['justificaciones_fuera']->first()['texto']);
        $this->assertSame('Registro histórico completo.', $informe['otros_registros']->first()['texto']);
        $this->assertCount(0, $informe['bloques']['plan_estudio']['justificaciones_fuera']);
    }

    public function test_excluye_cupos_y_asistentes_y_explica_tabla_de_justificaciones_no_disponible(): void
    {
        $informe = $this->informe([
            $this->docente(['cupo_contrata_id' => 1]), $this->docente(['estamento_cobertura' => 'asistente']),
        ], ready: false);
        foreach ($informe['bloques'] as $b) { $this->assertCount(0, $b['filas']); }
        $html = view('admin.dotacion-establecimiento.sobredotacion-pdf', ['establecimiento' => $this->ee, 'anio' => 2027, 'informe' => $informe])->render();
        $this->assertStringContainsString('La tabla de justificaciones no está disponible', $html);
    }

    public function test_cuadratura_del_proceso_separa_reservas_redondeo_y_cobertura_aaee_sin_alterar_contratos(): void
    {
        $informe = $this->informe(proceso: ['aplica' => true, 'bloques' => ['bloque_1' => [
            'asignadas' => 34, 'asignadas_con_redondeo' => 34.4, 'reservadas_no_normativas' => 4,
            'redondeo_parvularia' => .4, 'maximo' => 200, 'sin_padron_asignadas' => 2, 'asignadas_asistentes_obligatorias' => 5,
        ]]]);
        $cuadratura = $informe['bloques']['plan_estudio']['cuadratura'];
        $this->assertEquals(220, $cuadratura['Contrato vigente']);
        $this->assertEquals(30.4, $cuadratura['Asignadas']);
        $this->assertEquals(4, $cuadratura['Reservadas sin función']);
        $this->assertEquals(185.6, $cuadratura['Saldo contractual neto']);
        $this->assertEquals(5, $cuadratura['Cobertura AAEE (sin consumir contrato docente)']);
        $this->assertEquals(2, $cuadratura['Cupos o asignaciones sin padrón vigente']);
        $this->assertNotEmpty($informe['conciliacion']);
        $html = view('admin.dotacion-establecimiento.sobredotacion-pdf', ['establecimiento' => $this->ee, 'anio' => 2027, 'informe' => $informe])->render();
        $this->assertStringContainsString('Conciliación de horas de contrato', $html);
        $this->assertStringContainsString('Contrato y ocupación del bloque', $html);
    }

    public function test_boton_pdf_conserva_establecimiento_y_anio_y_el_excel_existente(): void
    {
        $html = view('admin.dotacion-establecimiento.partials._sobredotacion', [
            'establecimiento' => $this->ee, 'anio' => 2028, 'activeRole' => 'admin', 'docentes' => collect(),
            'sobredotacion' => \App\Support\DotacionSobredotacionCalculator::build([], []),
        ])->render();
        $this->assertStringContainsString(route('admin.dotacion-establecimiento.sobredotacion.pdf', [1, 'anio' => 2028]), $html);
        $this->assertStringContainsString('Descargar detalle PDF', $html);
        $this->assertStringContainsString('Exportar sobredotación (Excel)', $html);
    }

    public function test_pdf_horizontal_oficio_con_tabla_larga_y_fundamentos_completos_escapados(): void
    {
        $personas = collect(range(1, 24))->map(fn ($n) => $this->docente(['rut' => (99000000 + $n).'-K',
            'nombre' => 'Docente sintético '.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'fecha_antiguedad' => $n === 1 ? 'fecha histórica' : '2010-01-01']))->all();
        $texto = str_repeat('Fundamento extenso de revisión sintética. ', 100).'FIN DEL FUNDAMENTO <script>sin ejecutar</script>';
        $informe = $this->informe($personas, collect([$this->registro('99000001-K', 'plan_estudio', 'titular', 10, $texto)]));
        $html = view('admin.dotacion-establecimiento.sobredotacion-pdf', ['establecimiento' => $this->ee, 'anio' => 2027, 'informe' => $informe])->render();
        $this->assertStringContainsString('FIN DEL FUNDAMENTO &lt;script&gt;sin ejecutar&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('fecha histórica', $html);
        $this->assertStringContainsString('Sin justificación', $html);
        $pdf = (new DotacionSobredotacionPdfExport)->documento($this->ee, 2027, $informe);
        $canvas = $pdf->getDomPDF()->getCanvas();
        $this->assertEqualsWithDelta(963.7795, $canvas->get_width(), .01);
        $this->assertEqualsWithDelta(612.2835, $canvas->get_height(), .01);
        $this->assertGreaterThan(6, $canvas->get_page_count());
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }

    public static function roles(): array
    {
        return [[3, 'admin', true], [4, 'funcionario_directivo_estab', true], [5, 'coordinador_uatp', true],
            [6, 'coordinador_gdp', true], [7, 'supervisor_plani', true], [8, 'funcionario_slep', false], [1, 'postulante', false]];
    }

    #[DataProvider('roles')]
    public function test_descarga_por_rol_activo_y_anio(int $id, string $rol, bool $allowed): void
    {
        $user = $this->testUser($id, $id); $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => $rol]);
        $mock = $this->mock(DotacionSobredotacionInforme::class);
        if ($allowed) { $mock->shouldReceive('datos')->once()->withArgs(fn ($ee, $year) => $ee->id === 1 && $year === 2028)->andReturn($this->informe()); }
        else { $mock->shouldNotReceive('datos'); }
        $response = $this->get(route('admin.dotacion-establecimiento.sobredotacion.pdf', [1, 'anio' => 2028]));
        if ($allowed) {
            $response->assertOk()->assertDownload('detalle_sobredotacion_rbd_99999_2028.pdf')->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        } else { $response->assertForbidden(); }
    }

    public function test_alcance_directivo_incluso_con_rol_admin_inactivo_y_descarga_disponible_con_bloqueo(): void
    {
        (require database_path('migrations/2026_11_02_140000_create_dotacion_edicion_bloqueos.php'))->up();
        $admin = $this->testUser(10, 3); app(EdicionBloqueoService::class)->guardar(null, true, 0, null, $admin);
        $user = $this->testUser(4, 4); $user->forceFill(['establecimiento_id' => 1])->save();
        DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => User::class, 'model_id' => $user->id]); $user->unsetRelation('roles');
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->mock(DotacionSobredotacionInforme::class)->shouldReceive('datos')->once()->andReturn($this->informe());
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.pdf', [1, 'anio' => 2027]))->assertOk()->assertDownload();
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.pdf', 2))->assertForbidden();
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.pdf', 3))->assertNotFound();
        $this->getJson(route('admin.dotacion-establecimiento.sobredotacion.pdf', [1, 'anio' => 'invalido']))->assertUnprocessable()->assertJsonValidationErrors('anio');
        $user->forceFill(['establecimiento_id' => null])->save();
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.pdf', 1))->assertForbidden();
    }

    public static function years(): array { return [[2026], [2027]]; }

    #[DataProvider('years')]
    public function test_fuente_real_vacia_compatible_con_anios_anteriores_sin_escribir(int $year): void
    {
        foreach ([
            '2026_01_29_000001_create_reemplazos_personal_table.php', '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php', '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php', '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
        ] as $file) { (require database_path('migrations/'.$file))->up(); }
        $writes = [];
        DB::listen(function (\Illuminate\Database\Events\QueryExecuted $event) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $event->sql)) { $writes[] = $event->sql; }
        });
        $informe = (new DotacionSobredotacionInforme)->datos($this->ee, $year);
        foreach ($informe['bloques'] as $bloque) { $this->assertCount(0, $bloque['filas']); }
        $this->assertSame([], $writes);
        $this->assertFalse($informe['justificaciones_disponibles']);
        $pdf = (new DotacionSobredotacionPdfExport)->documento($this->ee, $year, $informe);
        $this->assertGreaterThanOrEqual(4, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }
}
