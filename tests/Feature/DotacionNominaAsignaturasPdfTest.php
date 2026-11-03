<?php

namespace Tests\Feature;

use App\Exports\DotacionNominaAsignaturasPdfExport;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Establecimiento;
use App\Models\User;
use App\Services\Dotacion\EdicionBloqueoService;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionNominaAsignaturas;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionNominaAsignaturasPdfTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

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
                'nombre_establecimiento' => 'Escuela sintética '.$id, 'comuna' => 'Comuna de prueba', 'sala_cuna' => $cuna]);
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

    private function docente(array $changes = []): array
    {
        return array_replace(['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Pedagogía sintética',
            'horas_planta' => 44, 'horas_contrata' => 0, 'tramo' => 'Inicial', 'fecha_antiguedad' => '2010-01-01'], $changes);
    }

    private function asignacion(array $changes = []): array
    {
        return array_replace(['id' => 1, 'docente_rut' => '99.000.001-K', 'docente_nombre' => 'Docente sintético',
            'tipo_asignacion' => 'plan_estudio', 'estado' => 'activa', 'horas_plan_pedagogicas' => 2.5,
            'horas_contrato' => 4, 'proporcion_aplicada' => '65/35'], $changes);
    }

    private function necesidad(array $changes = []): array
    {
        return array_replace(['titulo' => 'Matemática', 'asignatura_nombre' => 'Matemática', 'curso_label' => '1° Básico A',
            'horas_plan_requeridas' => 6, 'horas_contrato_requeridas' => 7, 'proporcion' => '65/35',
            'asignaciones' => [$this->asignacion()]], $changes);
    }

    private function informe(array $changes = []): array
    {
        return array_replace(['asignaturas' => (new DotacionNominaAsignaturas)->asignaturas([$this->necesidad()], collect([$this->docente()])),
            'periodos_padron' => '10/2026'], $changes);
    }

    public function test_suma_horas_por_docente_y_asignatura_con_libre_disposicion_y_respeta_prelacion(): void
    {
        $docentes = collect([
            $this->docente(),
            $this->docente(['rut' => '99000002-K', 'nombre' => 'Experto sintético', 'tramo' => 'Experto 2']),
            $this->docente(['rut' => '99000003-K', 'nombre' => 'Fuero sintético', 'exclusion_docente' => ['motivo' => 'fuero_maternal']]),
        ]);
        $necesidades = [
            $this->necesidad(['asignaciones' => [$this->asignacion(), $this->asignacion(['id' => 2, 'docente_rut' => '99000002-K', 'horas_plan_pedagogicas' => 3])]]),
            $this->necesidad(['titulo' => 'Refuerzo personalizado', 'asignatura_nombre' => 'Refuerzo personalizado', 'plan_comun_asociado' => 'Matemática',
                'curso_label' => '2° Básico A', 'asignaciones' => [$this->asignacion(['id' => 3, 'horas_plan_pedagogicas' => 1.25]),
                    $this->asignacion(['id' => 4, 'docente_rut' => '99000003-K', 'horas_plan_pedagogicas' => 2])]]),
            $this->necesidad(['titulo' => 'Historia', 'asignatura_nombre' => 'Historia', 'asignaciones' => []]),
        ];
        $asignaturas = (new DotacionNominaAsignaturas)->asignaturas($necesidades, $docentes);
        $this->assertSame(['Historia', 'Matemática'], $asignaturas->pluck('nombre')->all());
        $this->assertCount(0, $asignaturas[0]['docentes']);
        $filas = $asignaturas[1]['docentes'];
        $this->assertSame(['Fuero sintético', 'Experto sintético', 'Docente sintético'], $filas->pluck('nombre')->all());
        $this->assertEquals(3.75, $filas[2]['horas_asignadas']);
        $this->assertEquals(8.75, $asignaturas[1]['horas_asignadas']);
        $this->assertStringStartsWith('1.', $filas[0]['prelacion']);
    }

    public function test_excluye_cupos_aaee_fantasmas_y_otras_asignaciones_y_no_duplica_ids_de_cursos_combinados(): void
    {
        $personas = collect([$this->docente(), $this->docente(['rut' => '99000002-K', 'cupo_contrata_id' => 7]),
            $this->docente(['rut' => '99000003-K', 'estamento_cobertura' => 'asistente'])]);
        $incluir = $this->asignacion();
        $excluir = [
            ['estado' => 'inactiva'], ['es_plan_estudio_huerfano' => true], ['estamento_cobertura' => 'asistente'],
            ['tipo_asignacion' => 'reserva_no_normativa'], ['tipo_asignacion' => 'acompanamiento_parvularia'],
            ['horas_plan_pedagogicas' => 0], ['cupo_contrata_id' => 99], ['asignacion_automatica' => true],
            ['docente_rut' => '99000002-K'], ['docente_rut' => '99000003-K'], ['docente_rut' => ''],
        ];
        $rows = [$incluir];
        foreach ($excluir as $index => $cambio) { $rows[] = $this->asignacion(array_replace(['id' => $index + 10], $cambio)); }
        $asignaturas = (new DotacionNominaAsignaturas)->asignaturas([
            $this->necesidad(['asignaciones' => $rows]), $this->necesidad(['curso_label' => 'Curso combinado B', 'asignaciones' => [$incluir]]),
        ], $personas);
        $this->assertCount(1, $asignaturas->first()['docentes']);
        $this->assertEquals(2.5, $asignaturas->first()['horas_asignadas']);
    }

    public function test_conserva_horas_sin_nomina_anual_sin_inventar_prelacion(): void
    {
        $asignaturas = (new DotacionNominaAsignaturas)->asignaturas([$this->necesidad(['asignaciones' => [
            $this->asignacion(['id' => 8, 'docente_rut' => '99000009-K', 'docente_nombre' => 'Sin padrón sintético']), $this->asignacion(),
        ]])], collect([$this->docente()]));
        $filas = $asignaturas->first()['docentes'];
        $this->assertSame('Docente sintético', $filas->first()['nombre']);
        $this->assertSame('Sin antecedentes en nómina anual', $filas->last()['prelacion']);
        $this->assertNull($filas->last()['tramo']); $this->assertNull($filas->last()['fecha_antiguedad']);
        $this->assertEquals(5, $asignaturas->first()['horas_asignadas']);
    }

    public function test_pdf_horizontal_con_numeracion_textos_escapados_y_tabla_larga(): void
    {
        $personas = collect(range(1, 65))->map(fn ($n) => $this->docente(['rut' => (99000000 + $n).'-K', 'nombre' => 'Docente sintético '.str_pad((string) $n, 2, '0', STR_PAD_LEFT)]));
        $asignadas = $personas->map(fn ($p, $index) => $this->asignacion(['id' => $index + 1, 'docente_rut' => $p['rut'], 'horas_plan_pedagogicas' => .5]))->all();
        $informe = $this->informe(['asignaturas' => (new DotacionNominaAsignaturas)->asignaturas([
            $this->necesidad(['asignaciones' => $asignadas]),
            $this->necesidad(['asignatura_nombre' => '<script>Asignatura sintética</script>', 'asignaciones' => []]),
        ], $personas)]);
        $pdf = (new DotacionNominaAsignaturasPdfExport)->documento($this->ee, 2027, $informe);
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertEqualsWithDelta(340 / 25.4 * 72, $pdf->getDomPDF()->getCanvas()->get_width(), .01);
        $this->assertEqualsWithDelta(216 / 25.4 * 72, $pdf->getDomPDF()->getCanvas()->get_height(), .01);
        $this->assertGreaterThanOrEqual(3, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $html = view('admin.dotacion-establecimiento.nomina-asignaturas-pdf', ['establecimiento' => $this->ee, 'anio' => 2027, 'informe' => $informe])->render();
        $this->assertStringNotContainsString('<script>Asignatura', $html); $this->assertStringContainsString('Docente sintético 65', $html);
        $this->assertStringContainsString('32,5', $html);
    }

    public static function roles(): array
    {
        return [[3, 'admin', true], [4, 'funcionario_directivo_estab', true], [5, 'coordinador_uatp', true], [6, 'coordinador_gdp', true], [7, 'supervisor_plani', true], [8, 'funcionario_slep', false], [1, 'postulante', false]];
    }

    #[DataProvider('roles')]
    public function test_descarga_por_rol_activo_y_anio(int $id, string $rol, bool $allowed): void
    {
        $user = $this->testUser($id, $id); $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => $rol]);
        $mock = $this->mock(DotacionNominaAsignaturas::class);
        if ($allowed) { $mock->shouldReceive('datos')->once()->withArgs(fn ($ee, $year) => $ee->id === 1 && $year === 2028)->andReturn($this->informe()); }
        else { $mock->shouldNotReceive('datos'); }
        $response = $this->get(route('admin.dotacion-establecimiento.docentes.asignaturas.pdf', [1, 'anio' => 2028]));
        if ($allowed) {
            $response->assertOk()->assertDownload('docentes_por_asignatura_prelacion_rbd_99999_2028.pdf')->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent()); $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        } else { $response->assertForbidden(); }
    }

    public function test_alcance_del_directivo_lectura_con_bloqueo_y_validacion_de_anio(): void
    {
        $this->get(route('admin.dotacion-establecimiento.docentes.asignaturas.pdf', 1))->assertRedirect(route('login'));
        (require database_path('migrations/2026_11_02_140000_create_dotacion_edicion_bloqueos.php'))->up();
        $admin = $this->testUser(10, 3); app(EdicionBloqueoService::class)->guardar(null, true, 0, null, $admin);
        $user = $this->testUser(4, 4); $user->forceFill(['establecimiento_id' => 1])->save();
        DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => User::class, 'model_id' => $user->id]); $user->unsetRelation('roles');
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->mock(DotacionNominaAsignaturas::class)->shouldReceive('datos')->once()->andReturn($this->informe());
        $this->get(route('admin.dotacion-establecimiento.docentes.asignaturas.pdf', [1, 'anio' => 2027]))->assertOk()->assertDownload();
        $this->get(route('admin.dotacion-establecimiento.docentes.asignaturas.pdf', 2))->assertForbidden();
        $this->get(route('admin.dotacion-establecimiento.docentes.asignaturas.pdf', 3))->assertNotFound();
        $this->getJson(route('admin.dotacion-establecimiento.docentes.asignaturas.pdf', [1, 'anio' => 'invalido']))->assertUnprocessable()->assertJsonValidationErrors('anio');
        $user->forceFill(['establecimiento_id' => null])->save();
        $this->get(route('admin.dotacion-establecimiento.docentes.asignaturas.pdf', 1))->assertForbidden();
    }

    public static function years(): array { return [[2026], [2027]]; }

    #[DataProvider('years')]
    public function test_fuente_real_sin_cursos_no_escribe_y_entrega_reporte_vacio(int $year): void
    {
        foreach ([
            '2026_01_29_000001_create_reemplazos_personal_table.php', '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php', '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
        ] as $file) { (require database_path('migrations/'.$file))->up(); }
        $writes = [];
        DB::listen(function (\Illuminate\Database\Events\QueryExecuted $event) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $event->sql)) { $writes[] = $event->sql; }
        });
        $informe = (new DotacionNominaAsignaturas)->datos($this->ee, $year);
        $this->assertCount(0, $informe['asignaturas']); $this->assertSame([], $writes);
        $pdf = (new DotacionNominaAsignaturasPdfExport)->documento($this->ee, $year, $informe);
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
    }
}
