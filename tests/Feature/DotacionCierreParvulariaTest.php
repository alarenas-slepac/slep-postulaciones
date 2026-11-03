<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\Curso;
use App\Models\DotacionCursoCombinado;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCierreParvularia;
use App\Support\DotacionContratoParvulariaCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionCierreParvulariaTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;
    private EstablecimientoCurso $curso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
        foreach (['2026_01_23_000001_create_establecimientos_table.php', '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_18_130000_create_cursos_table.php', '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php', '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php', '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php', '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php',
            '2026_09_22_180000_create_dotacion_proceso_2027_configuraciones_table.php', '2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php',
            '2026_09_29_130000_create_dotacion_docente_subsectores_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('dotacion_docente_asignaciones', fn (Blueprint $t) => $t->string('estamento_cobertura')->default('docente'));
        Schema::table('dotacion_proceso_2027_configuraciones', fn (Blueprint $t) => $t->json('funciones_normativas')->nullable());
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rbd'); $t->string('rut'); $t->integer('horas_contratadas');
            foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $field) { $t->string($field)->nullable(); }
        });
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SINTETICO', 'nombre_establecimiento' => 'EE sintético']);
        $catalogo = Curso::where('codigo', 'NT1')->firstOrFail();
        $plan = PlanEstudio::create(['curso_id' => $catalogo->id, 'anio' => 2027, 'nombre_plan' => 'NT sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 38, 'horas_semanales_subtotal' => 36,
            'horas_semanales_libre_disposicion' => 2, 'activo' => true]);
        foreach ([['Otros núcleos', 36, 'plan_comun_formacion_general'], ['Taller de Lectoescritura', 2, 'libre_disposicion']] as [$nombre, $horas, $tipo]) {
            DB::table('planes_estudio_asignaturas')->insert(['plan_estudio_id' => $plan->id, 'asignatura' => $nombre, 'horas_semanales' => $horas, 'tipo_bloque' => $tipo]);
        }
        $this->curso = EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
            'curso_id' => $catalogo->id, 'plan_estudio_id' => $plan->id, 'anio' => 2027, 'letra' => 'A',
            'nombre_seccion' => 'NT1 A', 'regimen_jec' => 'Con JEC', 'matricula' => 20, 'activo' => true]);
        foreach ([1 => 27, 2 => 27, 3 => 2] as $id => $horas) {
            DB::table('reemplazos_personal')->insert(['establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
                'rut' => '9900000'.$id.'-K', 'nombre' => 'Educadora sintética '.$id, 'anio' => 2026, 'mes' => 8,
                'jornada' => $horas, 'jornada_basica' => $horas, 'tipocontrato' => 'PLANTA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'sintetico-'.$id]);
            DB::table('declaracion_sostenedores')->insert(['id' => $id, 'rbd' => $this->ee->rbd, 'rut' => '9900000'.$id.'-K',
                'nombres' => 'Educadora sintética '.$id, 'nombre_titulo' => 'Pedagogía en Educación de Párvulos',
                'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE', 'horas_contratadas' => $horas]);
        }
        DB::table('dotacion_contrata_habilitaciones')->insert(['id' => 1, 'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'bloque' => 'parvularia', 'horas' => 2]);
        DotacionProceso2027Configuracion::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'decision_combinacion' => 'sin_combinacion', 'max_horas_bloque_1' => 500, 'max_horas_bloque_2' => 55, 'max_horas_bloque_3' => 500]);
        $need = $this->necesidades()->firstWhere('titulo', 'Otros núcleos');
        foreach ([1, 2] as $id) {
            DotacionDocenteAsignacion::create(['anio' => 2027, 'establecimiento_id' => $this->ee->id,
                'docente_rut' => '9900000'.$id.'-K', 'docente_rut_normalizado' => '9900000'.$id.'K',
                'docente_nombre' => 'Educadora sintética '.$id, 'declaracion_sostenedor_id' => $id, 'estamento_cobertura' => 'docente',
                'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => $need['subtipo_asignacion'], 'necesidad_key' => $need['key'],
                'establecimiento_curso_id' => $this->curso->id, 'asignatura_nombre' => $need['titulo'],
                'horas_plan_pedagogicas' => 18, 'horas_contrato' => 27, 'estado' => 'activa',
                'proporcion_aplicada' => 'NT Con JEC · base contractual 55 h',
                'fuente_calculo' => 'Regla especial Parvularia · Reparto proporcional: 18 / 38 h de plan × 55 h de contrato.']);
        }
        $process = $this->proceso();
        DotacionProceso2027Configuracion::first()->update(['funciones_normativas' => collect($process['funciones_normativas'])->mapWithKeys(fn ($f) => [$f['key'] => false])->all()]);
        foreach ($this->proceso()['docentes_subsector']['asignaturas'] as $asignatura) {
            foreach (['99000001K', '99000002K', '99000003K', 'VACANTE_1'] as $rut) {
                DB::table('dotacion_docente_subsectores')->insert(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
                    'asignatura_key' => $asignatura['key'], 'docente_rut_normalizado' => $rut, 'nivel' => 'parvularia', 'asignatura_nombre' => $asignatura['nombre']]);
            }
        }
        $this->actingAs($this->testUser(1, 3)); $this->withSession(['active_role' => 'admin']);
        $this->assertTrue($this->proceso()['asignacion_habilitada']);
        $this->assertEquals(2, $this->necesidades()->sum('horas_plan_pendientes'));
    }

    protected function tearDown(): void { $this->resetCaches(); parent::tearDown(); }

    public static function personas(): array { return [['VACANTE-1'], ['99000003-K']]; }

    #[DataProvider('personas')]
    public function test_cierra_las_dos_aula_con_dos_contrato_en_cupo_o_educadora_real(string $rut): void
    {
        $this->guardar($rut);
        $row = DotacionDocenteAsignacion::where('docente_rut', $rut)->sole();
        $this->assertTrue(DotacionCierreParvularia::es($row));
        $this->assertSame(2.0, (float) $row->horas_contrato); $this->assertSame(2.0, (float) $row->horas_plan_pedagogicas);
        $this->assertEquals(0, $this->necesidades()->sum('horas_plan_pendientes'));
        $this->assertSame(0.0, $this->proceso()['bloques']['bloque_2']['pendientes']);
        $this->assertSame(55.0, $this->proceso()['bloques']['bloque_2']['asignadas_para_maximo']);
        $this->assertSame(0.0, $this->proceso()['bloques']['bloque_2']['saldo_maximo']);
        $antes = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        $this->proceso(); $this->necesidades();
        $this->assertSame($antes, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
        $persona = collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027), 'asignacion.docentes'))->firstWhere('rut', $rut);
        $this->assertSame(0.0, (float) $persona['diferencia']);
    }

    public function test_el_cierre_no_habilita_libre_disposicion_en_cursos_sin_jec(): void
    {
        PlanEstudio::query()->update(['regimen_jec' => 'Sin JEC']);
        $this->curso->update(['regimen_jec' => 'Sin JEC']);
        DotacionDocenteAsignacion::query()->update(['proporcion_aplicada' => 'NT Sin JEC · base contractual 35 h',
            'fuente_calculo' => 'Reparto proporcional: 18 / 38 h de plan × 35 h de contrato.']);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sin JEC no contempla libre disposición');
        $this->guardar('VACANTE-1');
    }

    #[DataProvider('personas')]
    public function test_cierre_del_plan_base_nt2_sin_jec_respetando_sus_31_horas(string $rut): void
    {
        $nt2 = Curso::where('codigo', 'NT2')->firstOrFail();
        PlanEstudio::query()->update(['curso_id' => $nt2->id, 'regimen_jec' => 'Sin JEC', 'horas_semanales_total' => 30,
            'horas_semanales_subtotal' => 30, 'horas_semanales_libre_disposicion' => 0]);
        DB::table('planes_estudio_asignaturas')->where('asignatura', 'Otros núcleos')->update(['horas_semanales' => 28]);
        DB::table('planes_estudio_asignaturas')->where('asignatura', 'Taller de Lectoescritura')->update(['tipo_bloque' => 'plan_comun_formacion_general']);
        $this->curso->update(['curso_id' => $nt2->id, 'nombre_seccion' => 'NT2 A', 'regimen_jec' => 'Sin JEC']);
        foreach ([1, 2] as $id) { $this->contrato($id, 15); }
        if ($rut === 'VACANTE-1') { $this->contrato(3, 0); }
        $need = $this->necesidades()->firstWhere('titulo', 'Otros núcleos');
        DotacionDocenteAsignacion::query()->update(['necesidad_key' => $need['key'], 'horas_plan_pedagogicas' => 14,
            'horas_contrato' => 15, 'proporcion_aplicada' => 'NT Sin JEC · base contractual 31 h',
            'fuente_calculo' => 'Reparto proporcional: 14 / 30 h de plan × 31 h de contrato.']);
        DotacionProceso2027Configuracion::first()->update(['max_horas_bloque_2' => 31]);
        $this->guardar($rut);
        $row = DotacionDocenteAsignacion::where('docente_rut', $rut)->sole();
        $this->assertTrue(DotacionCierreParvularia::es($row));
        $this->assertSame(2.0, (float) $row->horas_contrato);
        $this->assertSame(31.0, $this->proceso()['bloques']['bloque_2']['asignadas_para_maximo']);
        $this->assertSame(0.0, $this->proceso()['bloques']['bloque_2']['pendientes']);
    }

    public function test_grupo_combinado_conserva_una_sola_necesidad_y_su_base(): void
    {
        $nt2 = Curso::where('codigo', 'NT2')->firstOrFail();
        $otro = $this->curso->replicate(); $otro->curso_id = $nt2->id; $otro->nombre_seccion = 'NT2 A'; $otro->save();
        $grupo = DotacionCursoCombinado::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027, 'nombre' => 'NT1 y NT2 sintético', 'proporcion' => 'auto', 'activo' => true]);
        foreach ([$this->curso->id, $otro->id] as $id) { $grupo->miembros()->create(['establecimiento_curso_id' => $id]); }
        DotacionProceso2027Configuracion::first()->update(['decision_combinacion' => 'combinaciones_configuradas']);
        DotacionCursoCombinadoCalculator::clearCache();
        $necesidad = $this->necesidades()->firstWhere('titulo', 'Otros núcleos');
        DotacionDocenteAsignacion::query()->update(['necesidad_key' => $necesidad['key'], 'dotacion_curso_combinado_id' => $grupo->id]);
        $this->guardar('VACANTE-1');
        $this->assertEquals(38, $this->necesidades()->sum('horas_plan_requeridas'));
        $this->assertEquals(0, $this->necesidades()->sum('horas_plan_pendientes'));
        $this->assertSame(55.0, $this->proceso()['bloques']['bloque_2']['asignadas_para_maximo']);
    }

    public function test_el_saldo_del_curso_se_evalua_independiente_de_otros_cursos_pendientes(): void
    {
        $otro = $this->curso->replicate(); $otro->letra = 'B'; $otro->nombre_seccion = 'NT1 B'; $otro->save();
        DotacionProceso2027Configuracion::first()->update(['max_horas_bloque_2' => 110]);
        $this->guardar('VACANTE-1');
        $this->assertEquals(0, $this->necesidades()->where('establecimiento_curso_id', $this->curso->id)->sum('horas_plan_pendientes'));
        $this->assertEquals(38, $this->necesidades()->where('establecimiento_curso_id', $otro->id)->sum('horas_plan_pendientes'));
        $this->assertTrue(DotacionCierreParvularia::es(DotacionDocenteAsignacion::where('docente_rut', 'VACANTE-1')->sole()));
    }

    public function test_si_otra_educadora_puede_cubrir_el_saldo_no_omite_la_conversion_habitual(): void
    {
        $this->contrato(3, 3);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('superan el cupo');
        $this->guardar('VACANTE-1');
    }

    public function test_si_el_contrato_alcanza_sigue_la_conversion_ordinaria(): void
    {
        $this->contrato(3, 3); $this->guardar('99000003-K');
        $row = DotacionDocenteAsignacion::where('docente_rut', '99000003-K')->sole();
        $this->assertFalse(DotacionCierreParvularia::es($row)); $this->assertSame(3.0, (float) $row->horas_contrato);
    }

    public function test_no_aplica_si_quedan_mas_de_dos_aula_en_el_plan_de_parvularia(): void
    {
        DotacionDocenteAsignacion::first()->update(['horas_plan_pedagogicas' => 17]);
        $this->expectException(ValidationException::class); $this->expectExceptionMessage('superan el cupo');
        $this->guardar('VACANTE-1');
    }

    public function test_no_puede_asignar_mas_aula_que_la_pendiente(): void
    {
        $this->expectException(ValidationException::class); $this->expectExceptionMessage('dispone de 2 hora(s) aula');
        $this->guardar('VACANTE-1', 3);
    }

    public function test_repetir_el_envio_no_duplica_el_cierre(): void
    {
        $this->guardar('VACANTE-1');
        try { $this->guardar('99000003-K'); $this->fail('El aula ya está cubierta.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('horas_plan_pedagogicas', $e->errors()); }
        $this->assertSame(3, DotacionDocenteAsignacion::count());
        $this->assertEquals(0, $this->necesidades()->sum('horas_plan_pendientes'));
    }

    public function test_no_aplica_si_el_saldo_de_contrato_es_menor_que_el_aula(): void
    {
        $this->contrato(3, 1);
        $this->expectException(ValidationException::class);
        $this->guardar('99000003-K');
    }

    public function test_no_omite_la_asociacion_del_docente_ni_los_maximos_autorizados(): void
    {
        DB::table('dotacion_docente_subsectores')->where('docente_rut_normalizado', '99000003K')->delete();
        try { $this->guardar('99000003-K'); $this->fail('Debe conservar la asociación a la asignatura.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('docente_rut', $e->errors()); }
        DotacionProceso2027Configuracion::first()->update(['max_horas_bloque_2' => 54]);
        try { $this->guardar('VACANTE-1'); $this->fail('Debe conservar el máximo institucional.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('anio', $e->errors()); }
        $this->assertSame(2, DotacionDocenteAsignacion::count());
    }

    public function test_permite_cerrar_el_residuo_en_dos_asignaciones_sin_convertirlo_en_saldo_negativo(): void
    {
        $this->contrato(3, 1); DB::table('dotacion_contrata_habilitaciones')->update(['horas' => 1]);
        $this->guardar('VACANTE-1', 1); $this->guardar('99000003-K', 1);
        $this->assertEquals(0, $this->necesidades()->sum('horas_plan_pendientes'));
        $this->assertEquals(2, DotacionDocenteAsignacion::whereIn('docente_rut', ['VACANTE-1', '99000003-K'])->sum('horas_contrato'));
        $this->assertSame(0.0, $this->proceso()['bloques']['bloque_2']['pendientes']);
    }

    public function test_editar_reasignar_eliminar_y_volver_a_asignar_conserva_los_saldos(): void
    {
        $this->guardar('VACANTE-1'); $row = DotacionDocenteAsignacion::where('docente_rut', 'VACANTE-1')->sole();
        $this->contrato(3, 3);
        app(DotacionAsignacionController::class)->update($this->request(['docente_rut' => 'VACANTE-1', 'horas_plan_pedagogicas' => 2, 'observacion' => 'Seguimiento sintético']), $this->ee, $row);
        $this->assertSame(2.0, (float) $row->fresh()->horas_contrato);
        $this->contrato(3, 2);
        app(DotacionAsignacionController::class)->update($this->request(['docente_rut' => '99000003-K', 'horas_plan_pedagogicas' => 2]), $this->ee, $row->fresh());
        $this->assertSame(2.0, (float) $row->fresh()->horas_contrato);
        $this->assertSame(2.0, app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, 'VACANTE-1')['diferencia']);
        app(DotacionAsignacionController::class)->destroy($this->request([]), $this->ee, $row->fresh());
        $this->assertEquals(2, $this->necesidades()->sum('horas_plan_pendientes'));
        $this->guardar('VACANTE-1'); $this->assertEquals(0, $this->necesidades()->sum('horas_plan_pendientes'));
    }

    private function guardar(string $rut, float $aula = 2): void
    {
        $need = $this->necesidades()->firstWhere('titulo', 'Taller de Lectoescritura');
        app(DotacionAsignacionController::class)->store($this->request(['anio' => 2027, 'docente_rut' => $rut,
            'estamento_cobertura' => 'docente', 'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => $need['subtipo_asignacion'],
            'necesidad_key' => $need['key'], 'establecimiento_curso_id' => $need['establecimiento_curso_id'],
            'dotacion_curso_combinado_id' => $need['dotacion_curso_combinado_id'] ?? null, 'horas_plan_pedagogicas' => $aula]), $this->ee);
    }

    private function contrato(int $id, int $horas): void
    {
        DB::table('reemplazos_personal')->where('rut', '9900000'.$id.'-K')->update(['jornada' => $horas, 'jornada_basica' => $horas]);
        DB::table('declaracion_sostenedores')->where('id', $id)->update(['horas_contratadas' => $horas]);
    }

    private function necesidades(): \Illuminate\Support\Collection
    {
        return collect(data_get(DotacionEstablecimientoCalculator::build($this->ee, 2027, false, false), 'asignacion.necesidades.plan_estudio'));
    }

    private function proceso(): array { return DotacionProceso2027Calculator::resumen($this->ee, 2027); }

    private function request(array $data): Request
    {
        $request = Request::create('/asignaciones-sinteticas', 'POST', $data);
        $request->setLaravelSession($this->app['session.store']); $request->setUserResolver(fn () => auth()->user());
        $this->app->instance('request', $request); return $request;
    }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) { (new ReflectionProperty($class, $property))->setValue(null, []); }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache(); DotacionCursoCombinadoCalculator::clearCache();
    }
}
