<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Controllers\Admin\DotacionProceso2027Controller;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionDocentesSubsector;
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

class DotacionOrientacionCupoDiferencialTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;
    private array $cupo;
    private array $necesidades;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
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
            '2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php',
            '2026_09_29_130000_create_dotacion_docente_subsectores_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('dotacion_docente_asignaciones', fn (Blueprint $t) => $t->string('estamento_cobertura')->default('docente'));
        Schema::table('dotacion_proceso_2027_configuraciones', fn (Blueprint $t) => $t->json('funciones_normativas')->nullable());
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SYNTHETIC-ORIENTACION',
            'nombre_establecimiento' => 'Escuela sintética de Orientación']);
        $curso = Curso::where('codigo', '5B')->firstOrFail();
        $plan = PlanEstudio::create(['curso_id' => $curso->id, 'anio' => 2027, 'nombre_plan' => 'Plan sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 4, 'activo' => true]);
        foreach (['Orientación', 'Matemática'] as $nombre) {
            $plan->asignaturas()->create(['asignatura' => $nombre, 'tipo_bloque' => 'tiempo_minimo', 'horas_semanales' => 2]);
        }
        EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'rbd' => 99999, 'curso_id' => $curso->id,
            'plan_estudio_id' => $plan->id, 'anio' => 2027, 'letra' => 'A', 'nombre_seccion' => '5° Básico sintético',
            'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true]);
        foreach (['pie' => 12, 'plan_estudio' => 44] as $bloque => $horas) {
            DB::table('dotacion_contrata_habilitaciones')->insert(['establecimiento_id' => $this->ee->id,
                'anio' => 2027, 'bloque' => $bloque, 'horas' => $horas]);
        }
        $cupos = app(ContratacionHabilitacionService::class)->docentesVirtuales($this->ee, 2027);
        $this->cupo = $cupos->firstWhere('cupo_bloque', 'pie');
        DotacionProceso2027Configuracion::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'decision_combinacion' => 'sin_combinacion', 'max_horas_bloque_1' => 500,
            'max_horas_bloque_2' => 500, 'max_horas_bloque_3' => 500]);
        $proceso = $this->proceso();
        DotacionProceso2027Configuracion::first()->update(['funciones_normativas' => collect($proceso['funciones_normativas'])
            ->mapWithKeys(fn ($f) => [$f['key'] => false])->all()]);
        $this->necesidades = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027),
            'asignacion.necesidades.plan_estudio'))->keyBy('titulo')->all();
        foreach ($this->necesidades as $need) {
            DB::table('dotacion_docente_subsectores')->insert(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
                'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need), 'nivel' => 'basica',
                'asignatura_nombre' => $need['titulo'], 'docente_rut_normalizado' => $cupos->firstWhere('cupo_bloque', 'plan_estudio')['rut_normalizado']]);
        }
        $this->actingAs($this->testUser(1, 3));
        $this->withSession(['active_role' => 'admin']);
        $this->withoutVite();
        $this->app['request']->setLaravelSession($this->app['session.store']);
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    public function test_asocia_muestra_y_asigna_orientacion_al_cupo_diferencial(): void
    {
        $this->asociar();
        $this->assertContains($this->cupo['rut_normalizado'], collect($this->proceso()['docentes_subsector']['asignaturas'])
            ->firstWhere('nombre', 'Orientación')['docentes']);
        $this->assertContains($this->cupo['rut'], $this->opcionesAsignacion('Orientación'));
        $this->assertNotContains($this->cupo['rut'], $this->opcionesAsignacion('Matemática'));
        $this->guardar();
        $fila = DotacionDocenteAsignacion::sole();
        $this->assertSame('Orientación', $fila->asignatura_nombre);
        $this->assertSame(2.0, (float) $fila->horas_plan_pedagogicas);
        $this->assertSame(2.0, (float) $fila->horas_contrato);
        $this->assertSame(10.0, app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, $this->cupo['rut'])['diferencia']);
        $this->assertSame(2.0, $this->proceso()['bloques']['bloque_1']['asignadas']);
        $this->assertSame('pie', DB::table('dotacion_contrata_habilitaciones')->where('id', $this->cupo['cupo_contrata_id'])->value('bloque'));
    }

    public function test_selector_de_asociacion_ofrece_cupo_solo_en_orientacion(): void
    {
        $html = view('admin.dotacion-establecimiento.partials._docentes_subsector', ['establecimiento' => $this->ee,
            'anio' => 2027, 'proceso' => $this->proceso(), 'errors' => new ViewErrorBag])->render();
        $xpath = $this->xpath($html);
        foreach (['Orientación' => 1, 'Matemática' => 0] as $nombre => $cantidad) {
            $id = 'subsector-docentes-'.DotacionDocentesSubsector::keyParaNecesidad($this->necesidades[$nombre]);
            $this->assertSame($cantidad, $xpath->query('//select[@id="'.$id.'"]/option[@value="'.$this->cupo['rut_normalizado'].'"]')->length);
        }
    }

    public function test_cupo_diferencial_puede_cubrir_y_editar_trabajo_colaborativo_pie_sin_superar_su_jornada(): void
    {
        $curso = EstablecimientoCurso::sole();
        DB::table('establecimiento_curso_pie')->insert([
            'establecimiento_id' => $this->ee->id, 'establecimiento_curso_id' => $curso->id,
            'curso_id' => $curso->curso_id, 'anio' => 2027, 'total_pie' => 6,
        ]);
        $need = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027),
            'asignacion.necesidades.pie_colaborativo'))->sole();
        $this->assertContains($this->cupo['rut'], $this->opcionesParaNecesidad($need['key']));
        DotacionDocenteAsignacion::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut' => $this->cupo['rut'], 'docente_rut_normalizado' => $this->cupo['rut_normalizado'],
            'tipo_asignacion' => 'pie_educadora_diferencial', 'horas_contrato' => 10, 'estado' => 'activa']);

        $controller = app(DotacionAsignacionController::class);
        $controller->store($this->request([
            'anio' => 2027, 'docente_rut' => $this->cupo['rut'], 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => $need['tipo_asignacion'], 'subtipo_asignacion' => $need['subtipo_asignacion'],
            'necesidad_key' => $need['key'], 'establecimiento_curso_id' => $curso->id,
            'asignatura_nombre' => $need['titulo'], 'horas_contrato' => 1,
        ]), $this->ee);
        $fila = DotacionDocenteAsignacion::where('tipo_asignacion', 'pie_colaborativo')->sole();
        $this->assertSame(1.0, (float) $fila->horas_contrato);

        $controller->update($this->request(['docente_rut' => $this->cupo['rut'], 'horas_contrato' => 2]), $this->ee, $fila);
        $this->assertSame(2.0, (float) $fila->fresh()->horas_contrato);
        $this->assertSame(0.0, app(ContratacionHabilitacionService::class)
            ->docenteVirtual($this->ee, 2027, $this->cupo['rut'])['diferencia']);
        $this->assertSame(2.0, $this->proceso()['bloques']['bloque_1']['asignadas']);

        try {
            $controller->update($this->request(['docente_rut' => $this->cupo['rut'], 'horas_contrato' => 3]), $this->ee, $fila->fresh());
            $this->fail('Las horas de trabajo colaborativo PIE superan el saldo del cupo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('horas_contrato', $e->errors());
        }
        $this->assertSame(2.0, (float) $fila->fresh()->horas_contrato);
    }

    public function test_no_permite_asignar_sin_asociacion_previa(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no está asociado');
        $this->guardar();
    }

    public function test_no_permite_suplantar_matematica_con_nombre_orientacion(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('solo puede cubrir necesidades de su bloque');
        $this->guardar('Matemática', 1, ['asignatura_nombre' => 'Orientación']);
    }

    public function test_pie_ya_asignado_descuenta_del_mismo_cupo_y_rechaza_exceso(): void
    {
        $this->asociar();
        DotacionDocenteAsignacion::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut' => $this->cupo['rut'], 'docente_rut_normalizado' => $this->cupo['rut_normalizado'],
            'tipo_asignacion' => 'pie_educadora_diferencial', 'horas_contrato' => 11, 'estado' => 'activa']);
        try {
            $this->guardar();
            $this->fail('Las 2 h de contrato de Orientación no caben en la hora restante.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('horas_contrato', $e->errors());
        }
        $this->assertSame(1, DotacionDocenteAsignacion::count());
    }

    public function test_no_permite_usar_la_necesidad_de_orientacion_para_otro_curso(): void
    {
        $this->asociar();
        $otroCurso = EstablecimientoCurso::first()->replicate();
        $otroCurso->nombre_seccion = '5° Básico sintético B';
        $otroCurso->letra = 'B';
        $otroCurso->save();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('solo puede cubrir necesidades de su bloque');
        $this->guardar('Orientación', 1, ['establecimiento_curso_id' => $otroCurso->id]);
    }

    public function test_editar_y_eliminar_orientacion_restituyen_el_saldo_del_cupo(): void
    {
        $this->asociar();
        $this->guardar();
        $controller = app(DotacionAsignacionController::class);
        $fila = DotacionDocenteAsignacion::sole();
        $controller->update($this->request(['docente_rut' => $this->cupo['rut'], 'horas_plan_pedagogicas' => 1]), $this->ee, $fila);
        $this->assertSame(1.0, (float) $fila->fresh()->horas_contrato);
        $controller->destroy($this->request([]), $this->ee, $fila->fresh());
        $this->assertSame(12.0, app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, $this->cupo['rut'])['diferencia']);
    }

    public function test_no_permite_superar_horas_pendientes_del_plan(): void
    {
        $this->asociar();
        $this->expectException(ValidationException::class);
        $this->guardar('Orientación', 3);
    }

    public function test_orientacion_y_pie_pueden_completar_la_jornada_sin_duplicar_el_cupo(): void
    {
        $this->asociar();
        DotacionDocenteAsignacion::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut' => $this->cupo['rut'], 'docente_rut_normalizado' => $this->cupo['rut_normalizado'],
            'tipo_asignacion' => 'pie_educadora_diferencial', 'horas_contrato' => 10, 'estado' => 'activa']);
        $this->guardar();
        $fila = DotacionDocenteAsignacion::where('tipo_asignacion', 'plan_estudio')->sole();
        app(DotacionAsignacionController::class)->update($this->request(['docente_rut' => $this->cupo['rut'],
            'horas_plan_pedagogicas' => 2, 'observacion' => 'Edición sintética con jornada completa']), $this->ee, $fila);
        $this->assertSame(12.0, (float) DotacionDocenteAsignacion::sum('horas_contrato'));
        $this->assertSame(0.0, app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, $this->cupo['rut'])['diferencia']);
        $this->assertSame(2, DB::table('dotacion_contrata_habilitaciones')->count());
    }

    public function test_no_permite_asignar_si_el_maximo_del_bloque_es_insuficiente(): void
    {
        $this->asociar();
        DotacionProceso2027Configuracion::first()->update(['max_horas_bloque_1' => 0]);
        $this->expectException(ValidationException::class);
        $this->guardar();
    }

    private function asociar(): void
    {
        app(DotacionProceso2027Controller::class)->syncDocentesSubsector($this->request(['anio' => 2027,
            'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($this->necesidades['Orientación']),
            'docentes' => [$this->cupo['rut_normalizado']]]), $this->ee);
    }

    private function guardar(string $nombre = 'Orientación', float $aula = 2, array $extra = []): void
    {
        $need = $this->necesidades[$nombre];
        app(DotacionAsignacionController::class)->store($this->request(array_replace(['anio' => 2027,
            'docente_rut' => $this->cupo['rut'], 'estamento_cobertura' => 'docente', 'tipo_asignacion' => 'plan_estudio',
            'subtipo_asignacion' => $need['subtipo_asignacion'], 'necesidad_key' => $need['key'],
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'plan_estudio_id' => $need['plan_estudio_id'],
            'asignatura_nombre' => $nombre, 'horas_plan_pedagogicas' => $aula], $extra)), $this->ee);
    }

    private function proceso(): array
    {
        return DotacionProceso2027Calculator::resumen($this->ee, 2027);
    }

    private function request(array $data): Request
    {
        $request = Request::create('/orientacion-sintetica', 'POST', $data);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        return $request;
    }

    private function opcionesAsignacion(string $nombre): array
    {
        return $this->opcionesParaNecesidad($this->necesidades[$nombre]['key']);
    }

    private function opcionesParaNecesidad(string $key): array
    {
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', ['establecimiento' => $this->ee,
            'anio' => 2027, 'docentes' => $data['docentes'], 'asignacion' => $data['asignacion'],
            'proceso2027' => $this->proceso(), 'activeRole' => 'admin', 'errors' => new ViewErrorBag])->render();
        $xpath = $this->xpath($html);
        $selector = $xpath->query('//form[input[@name="necesidad_key" and @value="'.$key.'"]]//select[@name="docente_rut"]/optgroup[@data-personal-catalogo]')->item(0);
        $this->assertNotNull($selector);
        $id = $selector->getAttribute('data-personal-catalogo');
        return array_map(fn ($node) => $node->getAttribute('value'), iterator_to_array($xpath->query('//template[@id="'.$id.'"]//option')));
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }

    private function resetCaches(): void
    {
        foreach ([DotacionAsignacionCalculator::class, DotacionEstablecimientoCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) {
                (new ReflectionProperty($class, $property))->setValue(null, []);
            }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }
}
