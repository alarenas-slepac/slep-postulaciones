<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Controllers\Admin\DotacionProceso2027Controller;
use App\Models\Curso;
use App\Models\DotacionCursoCombinado;
use PHPUnit\Framework\Attributes\DataProvider;
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

class DotacionCupoGeneralParvulariaTest extends IsolatedSecurityTestCase
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
        Schema::table('dotacion_contrata_habilitaciones', fn (Blueprint $t) => $t->string('titulo')->nullable());
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SYNTHETIC-CUPO-NT',
            'nombre_establecimiento' => 'Escuela sintética de cupos NT']);
        $curso = Curso::where('codigo', 'NT1')->firstOrFail();
        $plan = PlanEstudio::create(['curso_id' => $curso->id, 'anio' => 2027, 'nombre_plan' => 'Plan sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 38, 'activo' => true]);
        foreach (['Lenguaje Verbal' => 2, 'Núcleo sintético' => 36] as $nombre => $horas) {
            $plan->asignaturas()->create(['asignatura' => $nombre, 'tipo_bloque' => 'tiempo_minimo', 'horas_semanales' => $horas]);
        }
        EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'rbd' => 99999, 'curso_id' => $curso->id,
            'plan_estudio_id' => $plan->id, 'anio' => 2027, 'letra' => 'A', 'nombre_seccion' => 'NT1 sintético',
            'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true]);
        foreach (['pie' => 44, 'plan_estudio' => 44, 'parvularia' => 44] as $bloque => $horas) {
            DB::table('dotacion_contrata_habilitaciones')->insert(['establecimiento_id' => $this->ee->id,
                'anio' => 2027, 'bloque' => $bloque, 'horas' => $horas]);
        }
        $cupos = app(ContratacionHabilitacionService::class)->docentesVirtuales($this->ee, 2027);
        $this->cupo = $cupos->firstWhere('cupo_bloque', 'plan_estudio');
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
                'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($need), 'nivel' => 'parvularia',
                'asignatura_nombre' => $need['titulo'], 'docente_rut_normalizado' => $cupos->firstWhere('cupo_bloque', 'parvularia')['rut_normalizado']]);
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

    public static function cursosConJec(): array
    {
        return [['NT1', false], ['NT2', false], ['NT1', true]];
    }

    #[DataProvider('cursosConJec')]
    public function test_cupo_general_asociado_aparece_y_puede_asignar_aula_nt(string $nivel, bool $combinado): void
    {
        $this->prepararCurso($nivel, 'Con JEC', $combinado);
        $this->asociar();
        $this->assertContains($this->cupo['rut'], $this->opcionesAsignacion('Lenguaje Verbal'));
        $this->guardar();
        $fila = DotacionDocenteAsignacion::sole();
        $this->assertSame($this->cupo['rut_normalizado'], $fila->docente_rut_normalizado);
        $this->assertSame('65/35', $fila->proporcion_aplicada);
        $this->assertSame(2.0, (float) $fila->horas_contrato);
        $this->assertSame(42.0, app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, $this->cupo['rut'])['diferencia']);
        $this->assertSame(2.0, $this->proceso()['bloques']['bloque_2']['asignadas']);
        $this->assertSame('plan_estudio', DB::table('dotacion_contrata_habilitaciones')->where('id', $this->cupo['cupo_contrata_id'])->value('bloque'));
    }

    public function test_sin_asociacion_no_aparece_y_no_permite_forzar_el_guardado(): void
    {
        $this->assertNotContains($this->cupo['rut'], $this->opcionesAsignacion('Lenguaje Verbal'));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('no está asociado');
        $this->guardar();
    }

    public function test_editar_eliminar_y_reasignar_restituye_el_saldo_del_mismo_cupo(): void
    {
        $this->asociar();
        $this->guardar();
        $controller = app(DotacionAsignacionController::class);
        $fila = DotacionDocenteAsignacion::sole();
        $controller->update($this->request(['docente_rut' => $this->cupo['rut'], 'horas_plan_pedagogicas' => 1]), $this->ee, $fila);
        $this->assertSame(1.0, (float) $fila->fresh()->horas_contrato);
        $controller->destroy($this->request([]), $this->ee, $fila->fresh());
        $this->assertSame(44.0, app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, $this->cupo['rut'])['diferencia']);
        $this->guardar();
        $this->assertSame(2.0, (float) DotacionDocenteAsignacion::sum('horas_contrato'));
        $this->assertSame(3, DB::table('dotacion_contrata_habilitaciones')->count());
    }

    public function test_editar_con_jornada_completa_libera_solo_sus_horas_actuales(): void
    {
        DB::table('dotacion_contrata_habilitaciones')->where('id', $this->cupo['cupo_contrata_id'])->update(['horas' => 2]);
        $this->asociar();
        $this->guardar();
        $fila = DotacionDocenteAsignacion::sole();
        app(DotacionAsignacionController::class)->update($this->request(['docente_rut' => $this->cupo['rut'],
            'horas_plan_pedagogicas' => 2, 'observacion' => 'Edición sintética a jornada completa']), $this->ee, $fila);
        $this->assertSame(2.0, (float) $fila->fresh()->horas_contrato);
        $this->assertSame(0.0, app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, $this->cupo['rut'])['diferencia']);
    }

    public function test_no_permite_superar_el_contrato_del_cupo(): void
    {
        $this->asociar();
        DB::table('dotacion_contrata_habilitaciones')->where('id', $this->cupo['cupo_contrata_id'])->update(['horas' => 1]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('superan el cupo');
        $this->guardar();
    }

    public function test_no_permite_superar_el_plan_pendiente(): void
    {
        $this->asociar();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('dispone de 2 hora(s) aula');
        $this->guardar('Lenguaje Verbal', 3);
    }

    public function test_no_permite_un_maximo_insuficiente_en_el_bloque_parvularia(): void
    {
        $this->asociar();
        DotacionProceso2027Configuracion::first()->update(['max_horas_bloque_2' => 0]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('configurar máximos suficientes');
        $this->guardar();
    }

    public function test_no_permite_suplantar_el_curso_de_la_asignatura(): void
    {
        $this->asociar();
        $otro = EstablecimientoCurso::first()->replicate();
        $otro->letra = 'B'; $otro->nombre_seccion = 'NT1 sintético B'; $otro->save();
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('solo puede cubrir necesidades de su bloque');
        $this->guardar('Lenguaje Verbal', 1, ['establecimiento_curso_id' => $otro->id]);
    }

    public function test_sin_jec_conserva_el_requisito_de_titulo_de_parvulos(): void
    {
        $this->prepararCurso('NT1', 'Sin JEC');
        $this->asociar();
        $this->assertNotContains($this->cupo['rut'], $this->opcionesAsignacion('Lenguaje Verbal'));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('solo admite cobertura por Educadoras de Párvulos');
        $this->guardar();
    }

    public function test_sin_jec_admite_cupo_general_con_titulo_de_parvulos_y_asociacion(): void
    {
        DB::table('dotacion_contrata_habilitaciones')->where('id', $this->cupo['cupo_contrata_id'])
            ->update(['titulo' => 'Pedagogía en Educación de Párvulos']);
        $this->prepararCurso('NT2', 'Sin JEC');
        $this->asociar();
        $this->assertContains($this->cupo['rut'], $this->opcionesAsignacion('Lenguaje Verbal'));
        $this->guardar();
        $this->assertStringStartsWith('NT Sin JEC', DotacionDocenteAsignacion::sole()->proporcion_aplicada);
    }

    public function test_la_excepcion_no_habilita_pie_ni_cobertura_de_otros_anos_o_asistentes(): void
    {
        $need = $this->necesidades['Lenguaje Verbal'];
        $regla = \App\Support\DotacionAsignacionElegibilidad2027::class;
        $this->assertFalse($regla::cupoGeneralPuedeImpartirParvularia($this->cupo, $need, 2026));
        $this->assertFalse($regla::cupoGeneralPuedeImpartirParvularia($this->cupo, $need, 2028));
        $this->assertFalse($regla::cupoGeneralPuedeImpartirParvularia(array_replace($this->cupo, ['estamento_cobertura' => 'asistente']), $need, 2027));
        $this->assertFalse($regla::cupoGeneralPuedeImpartirParvularia(array_replace($this->cupo, ['cupo_bloque' => 'pie']), $need, 2027));
        $this->assertFalse($regla::cupoGeneralPuedeImpartirParvularia($this->cupo, array_replace($need, ['tipo_asignacion' => 'acompanamiento_parvularia']), 2027));
        $this->assertFalse($regla::cupoGeneralPuedeImpartirParvularia($this->cupo, ['tipo_asignacion' => 'plan_estudio'], 2027));
    }

    public function test_el_cupo_propio_de_parvularia_conserva_su_asignacion_y_conversion(): void
    {
        $this->cupo = app(ContratacionHabilitacionService::class)->docentesVirtuales($this->ee, 2027)->firstWhere('cupo_bloque', 'parvularia');
        $this->asociar();
        $this->assertContains($this->cupo['rut'], $this->opcionesAsignacion('Lenguaje Verbal'));
        $this->guardar();
        $this->assertStringStartsWith('NT Con JEC', DotacionDocenteAsignacion::sole()->proporcion_aplicada);
    }

    public function test_la_asociacion_del_cupo_no_omite_la_prioridad_titular(): void
    {
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id();
            foreach (['rbd', 'rut', 'nombres', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $campo) { $t->string($campo); }
        });
        DB::table('reemplazos_personal')->insert(['establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
            'rut' => '99000007-K', 'nombre' => 'Educadora titular sintética', 'anio' => 2026, 'mes' => 10,
            'jornada' => 44, 'jornada_basica' => 44, 'tipocontrato' => 'TITULAR', 'estatuto' => 'DOCENTE',
            'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-titular-cupo-nt']);
        DB::table('declaracion_sostenedores')->insert(['rbd' => $this->ee->rbd, 'rut' => '99000007-K',
            'nombres' => 'Educadora titular sintética', 'nombre_titulo' => 'Pedagogía en Educación de Párvulos',
            'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE']);
        $this->resetCaches();
        $this->asociar();
        DB::table('dotacion_docente_subsectores')->insert(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($this->necesidades['Lenguaje Verbal']),
            'nivel' => 'parvularia', 'asignatura_nombre' => 'Lenguaje Verbal', 'docente_rut_normalizado' => '99000007K']);
        $this->assertNotContains($this->cupo['rut'], $this->opcionesAsignacion('Lenguaje Verbal'));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('1 h titular disponible');
        $this->guardar();
    }

    private function prepararCurso(string $nivel, string $jec, bool $combinado = false): void
    {
        $curso = EstablecimientoCurso::first();
        $catalogo = Curso::where('codigo', $nivel)->firstOrFail();
        $horas = $jec === 'Con JEC' ? 38 : ($nivel === 'NT1' ? 32 : 30);
        $plan = $curso->planEstudio;
        $plan->update(['curso_id' => $catalogo->id, 'regimen_jec' => $jec, 'horas_semanales_total' => $horas]);
        $plan->asignaturas()->where('asignatura', 'Núcleo sintético')->update(['horas_semanales' => $horas - 2]);
        $curso->update(['curso_id' => $catalogo->id, 'regimen_jec' => $jec, 'nombre_seccion' => $nivel.' sintético']);
        if ($combinado) {
            $nt2 = Curso::where('codigo', 'NT2')->firstOrFail();
            $otroPlan = $plan->replicate(); $otroPlan->curso_id = $nt2->id; $otroPlan->save();
            foreach ($plan->asignaturas as $asignatura) {
                $otra = $asignatura->replicate(); $otra->plan_estudio_id = $otroPlan->id; $otra->save();
            }
            $otroCurso = $curso->replicate(); $otroCurso->curso_id = $nt2->id; $otroCurso->plan_estudio_id = $otroPlan->id;
            $otroCurso->nombre_seccion = 'NT2 sintético'; $otroCurso->save();
            $grupo = DotacionCursoCombinado::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
                'nombre' => 'NT1 + NT2 sintético', 'activo' => true]);
            foreach ([$curso, $otroCurso] as $miembro) { $grupo->miembros()->create(['establecimiento_curso_id' => $miembro->id]); }
            DotacionProceso2027Configuracion::first()->update(['decision_combinacion' => 'combinaciones_configuradas']);
        }
        DotacionCursoCombinadoCalculator::clearCache();
        $this->necesidades = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027),
            'asignacion.necesidades.plan_estudio'))->keyBy('titulo')->all();
    }

    private function asociar(): void
    {
        app(DotacionProceso2027Controller::class)->syncDocentesSubsector($this->request(['anio' => 2027,
            'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($this->necesidades['Lenguaje Verbal']),
            'docentes' => [$this->cupo['rut_normalizado']]]), $this->ee);
    }

    private function guardar(string $nombre = 'Lenguaje Verbal', float $aula = 2, array $extra = []): void
    {
        $need = $this->necesidades[$nombre];
        app(DotacionAsignacionController::class)->store($this->request(array_replace(['anio' => 2027,
            'docente_rut' => $this->cupo['rut'], 'estamento_cobertura' => 'docente', 'tipo_asignacion' => 'plan_estudio',
            'subtipo_asignacion' => $need['subtipo_asignacion'], 'necesidad_key' => $need['key'],
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'plan_estudio_id' => $need['plan_estudio_id'],
            'dotacion_curso_combinado_id' => $need['dotacion_curso_combinado_id'] ?? null,
            'asignatura_nombre' => $nombre, 'horas_plan_pedagogicas' => $aula], $extra)), $this->ee);
    }

    private function proceso(): array
    {
        return DotacionProceso2027Calculator::resumen($this->ee, 2027);
    }

    private function request(array $data): Request
    {
        $request = Request::create('/parvularia-sintetica', 'POST', $data);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request);
        return $request;
    }

    private function opcionesAsignacion(string $nombre): array
    {
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', ['establecimiento' => $this->ee,
            'anio' => 2027, 'docentes' => $data['docentes'], 'asignacion' => $data['asignacion'],
            'proceso2027' => $this->proceso(), 'activeRole' => 'admin', 'errors' => new ViewErrorBag])->render();
        $xpath = $this->xpath($html);
        $key = $this->necesidades[$nombre]['key'];
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
