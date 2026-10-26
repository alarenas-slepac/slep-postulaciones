<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionSaldoMixtoTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;
    private array $necesidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCalculators();
        foreach ([
            '2026_01_23_000001_create_establecimientos_table.php',
            '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php',
            '2026_07_23_170000_sync_docente_horas_proporciones_cpeip.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php',
            '2026_09_22_180000_create_dotacion_proceso_2027_configuraciones_table.php',
            '2026_09_29_130000_create_dotacion_docente_subsectores_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('dotacion_docente_asignaciones', fn (Blueprint $t) => $t->string('estamento_cobertura')->default('docente'));
        Schema::table('dotacion_proceso_2027_configuraciones', fn (Blueprint $t) => $t->json('funciones_normativas')->nullable());
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); $t->string('rbd'); $t->string('rut'); $t->integer('horas_contratadas');
            foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $field) {
                $t->string($field)->nullable();
            }
        });
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'Establecimiento sintético']);
        $catalogo = Curso::where('codigo', '1B')->firstOrFail();
        $plan = PlanEstudio::create([
            'curso_id' => $catalogo->id, 'anio' => 2027, 'nombre_plan' => 'Plan sintético', 'regimen_jec' => 'Con JEC',
            'horas_semanales_total' => 38, 'horas_semanales_subtotal' => 32, 'horas_semanales_libre_disposicion' => 6, 'activo' => true,
        ]);
        foreach ([['Tecnología', 'plan_comun_formacion_general', 3], ['Matemática', 'plan_comun_formacion_general', 29], ['Taller sintético', 'libre_disposicion', 6]] as [$nombre, $tipo, $horas]) {
            DB::table('planes_estudio_asignaturas')->insert(['plan_estudio_id' => $plan->id, 'asignatura' => $nombre, 'tipo_bloque' => $tipo, 'horas_semanales' => $horas]);
        }
        EstablecimientoCurso::create([
            'establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd, 'curso_id' => $catalogo->id,
            'plan_estudio_id' => $plan->id, 'anio' => 2027, 'letra' => 'A', 'nombre_seccion' => 'Curso sintético A',
            'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true,
        ]);
        $this->personal('99000001-K', 33, 2);
        DotacionProceso2027Configuracion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'decision_combinacion' => 'sin_combinacion',
            'max_horas_bloque_1' => 500, 'max_horas_bloque_2' => 500, 'max_horas_bloque_3' => 500,
        ]);
        $proceso = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        DotacionProceso2027Configuracion::first()->update([
            'funciones_normativas' => collect($proceso['funciones_normativas'])->mapWithKeys(fn ($f) => [$f['key'] => false])->all(),
        ]);
        foreach ($proceso['docentes_subsector']['asignaturas'] as $asignatura) {
            DB::table('dotacion_docente_subsectores')->insert([
                'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'asignatura_key' => $asignatura['key'],
                'docente_rut_normalizado' => '99000001K', 'nivel' => 'basica', 'asignatura_nombre' => $asignatura['nombre'],
            ]);
        }
        $necesidades = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.necesidades.plan_estudio'));
        $this->necesidad = $necesidades->firstWhere('titulo', 'Tecnología');
        $previa = $necesidades->firstWhere('titulo', 'Matemática');
        DotacionDocenteAsignacion::create([
            ...$this->payload($previa), 'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Persona sintética',
            'establecimiento_id' => $this->ee->id, 'horas_plan_pedagogicas' => 28, 'horas_contrato' => 32,
            'proporcion_aplicada' => '65/35', 'estado' => 'activa',
        ]);
        $this->actingAs($this->testUser(1, 3));
        $this->withSession(['active_role' => 'admin']);
        $this->assertTrue(DotacionProceso2027Calculator::resumen($this->ee, 2027)['asignacion_habilitada']);
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public function test_asigna_media_hora_aula_usando_una_hora_titular_y_una_a_contrata(): void
    {
        $contratos = DB::table('reemplazos_personal')->orderBy('id')->get()->toJson();
        $this->assertEquals(1, $this->docente()['horas_titulares_disponibles']);
        $this->assertEquals(2, $this->docente()['horas_contrata_disponibles']);
        $this->store();
        $guardada = DotacionDocenteAsignacion::where('necesidad_key', $this->necesidad['key'])->sole();
        $this->assertEquals(.5, $guardada->horas_plan_pedagogicas);
        $this->assertEquals(2, $guardada->horas_contrato);
        $this->assertSame('99000001K', $guardada->docente_rut_normalizado);
        $proceso = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        $this->assertEquals(33, $proceso['bloques']['bloque_1']['titulares_asignadas']);
        $this->assertEquals(1, $proceso['bloques']['bloque_1']['contrata_asignadas']);
        $this->assertEquals(34, $this->docente()['horas_asignadas_total']);
        $this->assertEquals(0, $this->docente()['horas_titulares_disponibles']);
        $this->assertEquals(1, $this->docente()['horas_contrata_disponibles']);
        $this->assertSame($contratos, DB::table('reemplazos_personal')->orderBy('id')->get()->toJson());
    }

    public function test_editar_restituye_la_asignacion_y_permite_consumir_el_total_mixto(): void
    {
        $this->store();
        $asignacion = DotacionDocenteAsignacion::where('necesidad_key', $this->necesidad['key'])->sole();
        app(DotacionAsignacionController::class)->update($this->request([
            'docente_rut' => '99000001-K', 'horas_plan_pedagogicas' => 1.5,
        ]), $this->ee, $asignacion);
        $this->assertEquals(3, $asignacion->fresh()->horas_contrato);
        $this->assertEquals(35, $this->docente()['horas_asignadas_total']);
        $this->assertEquals(0, $this->docente()['horas_disponibles']);
        $this->assertSame(2, DotacionDocenteAsignacion::count());
        $antes = $asignacion->fresh()->getAttributes();
        try {
            app(DotacionAsignacionController::class)->update($this->request([
                'docente_rut' => '99000001-K', 'horas_plan_pedagogicas' => 2.5,
            ]), $this->ee, $asignacion);
            $this->fail('No se debe exceder el saldo titular más contrata.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('horas_plan_pedagogicas', $exception->errors());
        }
        $this->assertSame($antes, $asignacion->fresh()->getAttributes());
    }

    public function test_conserva_reservas_y_rechaza_si_la_suma_disponible_es_insuficiente(): void
    {
        DotacionDocenteAsignacion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut' => '99000001-K',
            'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Persona sintética',
            'tipo_asignacion' => 'reserva_no_normativa', 'subtipo_asignacion' => 'titular',
            'horas_contrato' => 2, 'estado' => 'activa',
        ]);
        $antes = DotacionDocenteAsignacion::orderBy('id')->get()->toJson();
        $this->assertEquals(1, $this->docente()['horas_disponibles']);
        try {
            $this->store();
            $this->fail('La reserva no debe volver a quedar disponible.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('horas_plan_pedagogicas', $exception->errors());
        }
        $this->assertSame($antes, DotacionDocenteAsignacion::orderBy('id')->get()->toJson());
    }

    public function test_no_habilita_contrata_exclusiva_ni_personas_sin_asociacion(): void
    {
        $this->personal('99000002-K', 0, 10);
        $this->personal('99000003-K', 10, 10);
        $asociacion = (array) DB::table('dotacion_docente_subsectores')->first();
        unset($asociacion['id']);
        foreach (DB::table('dotacion_docente_subsectores')->get() as $item) {
            DB::table('dotacion_docente_subsectores')->insert([
                ...$asociacion, 'asignatura_key' => $item->asignatura_key, 'docente_rut_normalizado' => '99000002K',
            ]);
        }
        foreach (['99000002-K' => 'Esta asignatura aún tiene docentes', '99000003-K' => 'no está asociado'] as $rut => $mensaje) {
            try {
                $this->store(['docente_rut' => $rut]);
                $this->fail('La contrata no debe omitir la fase titular ni la asociación.');
            } catch (ValidationException $exception) { $this->assertStringContainsString($mensaje, $exception->getMessage()); }
        }
        $this->assertSame(1, DotacionDocenteAsignacion::count());
    }

    public function test_selector_informa_capacidad_total_y_desglose_sin_cambiar_la_fase(): void
    {
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $this->app['view']->share('errors', new ViewErrorBag);
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', [
            'establecimiento' => $this->ee, 'anio' => 2027, 'docentes' => $data['docentes'],
            'asignacion' => $data['asignacion'], 'proceso2027' => DotacionProceso2027Calculator::resumen($this->ee, 2027, $data),
        ])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $opcion = $xpath->query('//template//option[@value="99000001-K" and @data-fase-plan="titular"]')->item(0);
        $this->assertNotNull($opcion);
        $this->assertSame('3', $opcion->getAttribute('data-fase-disponible'));
        $this->assertSame('1', $opcion->getAttribute('data-titular-disponible'));
        $this->assertSame('2', $opcion->getAttribute('data-contrata-disponible'));
        $this->assertStringContainsString('Disponible para asignar: 3 h', $opcion->textContent);
        $this->assertStringContainsString('1 titular + 2 contrata', $opcion->textContent);
        $this->assertStringContainsString('puede completar lo que falta con su saldo a contrata', $html);
    }

    private function personal(string $rut, int $planta, int $contrata): void
    {
        foreach (['PLANTA' => $planta, 'CONTRATA' => $contrata] as $tipo => $horas) {
            if ($horas === 0) { continue; }
            DB::table('reemplazos_personal')->insert([
                'establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd, 'rut' => $rut, 'nombre' => 'Persona sintética '.$rut,
                'anio' => 2026, 'mes' => 8, 'jornada' => $horas, 'jornada_basica' => $horas, 'tipocontrato' => $tipo,
                'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-'.$rut.'-'.$tipo,
            ]);
        }
        DB::table('declaracion_sostenedores')->insert([
            'rbd' => $this->ee->rbd, 'rut' => $rut, 'nombres' => 'Persona sintética '.$rut, 'nombre_titulo' => 'Pedagogía en Educación Básica',
            'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE', 'horas_contratadas' => $planta + $contrata,
        ]);
    }

    private function payload(array $necesidad): array
    {
        return [
            'anio' => 2027, 'docente_rut' => '99000001-K', 'estamento_cobertura' => 'docente', 'tipo_asignacion' => 'plan_estudio',
            'subtipo_asignacion' => $necesidad['subtipo_asignacion'], 'necesidad_key' => $necesidad['key'],
            'establecimiento_curso_id' => $necesidad['establecimiento_curso_id'], 'plan_estudio_id' => $necesidad['plan_estudio_id'],
            'asignatura_nombre' => $necesidad['titulo'], 'horas_plan_pedagogicas' => .5, 'subvencion' => 'General',
        ];
    }

    private function store(array $datos = []): void
    {
        app(DotacionAsignacionController::class)->store($this->request([...$this->payload($this->necesidad), ...$datos]), $this->ee);
    }

    private function docente(): array
    {
        return collect(DotacionProceso2027Calculator::resumen($this->ee, 2027)['docentes'])->firstWhere('rut_normalizado', '99000001K');
    }

    private function request(array $datos): Request
    {
        $request = Request::create('/asignacion-sintetica', 'POST', $datos);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        return $request;
    }

    private function resetCalculators(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $clase) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $campo) {
                (new ReflectionProperty($clase, $campo))->setValue(null, []);
            }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }
}
