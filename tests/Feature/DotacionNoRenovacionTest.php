<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionNoRenovacion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Dotacion\NoRenovacionService;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionDocentesSubsector;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionNoRenovacionTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

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
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php',
            '2026_09_29_130000_create_dotacion_docente_subsectores_table.php',
            '2026_10_13_200000_add_dotacion_no_renovaciones.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->string('estamento_cobertura')->default('docente');
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); $t->string('rbd'); $t->string('rut'); $t->integer('horas_contratadas');
            foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $field) {
                $t->string($field)->nullable();
            }
        });
        Schema::create('modules', function (Blueprint $t): void {
            $t->id(); $t->string('key');
        });
        $this->ee = Establecimiento::create([
            'rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'Escuela sintética de reemplazo',
        ]);
        $this->actingAs($this->testUser(1, 3))->withSession(['active_role' => 'admin']);
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        DotacionDocenteAsignacion::flushEventListeners();
        parent::tearDown();
    }

    public static function bloques(): array
    {
        return [
            ['plan_estudio', 'Pedagogía en Educación Básica'],
            ['parvularia', 'Pedagogía en Educación de Párvulos'],
            ['pie', 'Pedagogía en Educación Diferencial'],
        ];
    }

    #[DataProvider('bloques')]
    public function test_reemplaza_contrato_sin_brecha_en_cada_bloque_y_deja_cupo_vacio(string $bloque, string $titulo): void
    {
        $this->personal($titulo);
        $this->asignacion();
        DB::table('dotacion_docente_subsectores')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'asignatura_key' => str_repeat('a', 40),
            'nivel' => 'basica', 'asignatura_nombre' => 'Asignatura sintética', 'docente_rut_normalizado' => '99000001K',
        ]);
        $antes = DB::table('reemplazos_personal')->get()->map(fn ($row) => (array) $row)->all();
        $registro = $this->reemplazar($bloque);
        $cupo = DB::table('dotacion_contrata_habilitaciones')->sole();
        $this->assertEquals(44, $cupo->horas);
        $this->assertSame($titulo, $cupo->titulo);
        $this->assertSame($registro->id, $cupo->no_renovacion_id);
        $this->assertSame('DOCENTE', $cupo->funcion);
        $this->assertSame('No renovación sintética debidamente fundada.', $registro->justificacion);
        $this->assertSame('99000001K', $registro->docente_rut_normalizado);
        $this->assertSame(auth()->id(), $registro->created_by);
        $this->assertCount(1, $registro->asignaciones_anteriores);
        $this->assertSame('activa', $registro->asignaciones_anteriores[0]['estado']);
        $this->assertSame('no_renovada', DotacionDocenteAsignacion::sole()->estado);
        $virtual = app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, 'VACANTE-'.$cupo->id);
        $this->assertEquals(0, $virtual['horas_asignadas_total']);
        $this->assertEquals(44, $virtual['diferencia']);
        $this->assertSame($titulo, $virtual['titulo']);
        $this->assertEmpty($virtual['asignaciones']);
        $this->assertSame(1, DB::table('dotacion_docente_subsectores')->count());
        $this->assertFalse(DB::table('dotacion_docente_subsectores')->where('docente_rut_normalizado', 'VACANTE_'.$cupo->id)->exists());
        $this->assertCount(0, DotacionEstablecimientoCalculator::docentes($this->ee, 2027));
        $this->assertCount(1, DotacionEstablecimientoCalculator::docentes($this->ee, 2026));
        $this->assertCount(1, DotacionEstablecimientoCalculator::docentes($this->ee, 2028));
        $this->assertSame($antes, DB::table('reemplazos_personal')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertEquals(0, DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027)->sum('horas_contrato'));
    }

    public function test_contrato_distribuido_en_varios_bloques_crea_cupos_vacios_sin_aumentar_total(): void
    {
        $this->personal('Pedagogía en Educación Diferencial');
        $this->asignacion(['tipo_asignacion' => 'funcion_tecnico_pedagogica', 'subtipo_asignacion' => 'tecnico_pedagogica', 'asignatura_nombre' => 'Función normativa sintética', 'horas_contrato' => 6]);
        $this->asignacion(['tipo_asignacion' => 'pie_educadora_diferencial', 'horas_contrato' => 20]);
        $registro = $this->reemplazar('pie');
        $cupos = DB::table('dotacion_contrata_habilitaciones')->get()->keyBy('bloque');
        $this->assertCount(2, $cupos);
        $this->assertEquals(6, $cupos['plan_estudio']->horas);
        $this->assertEquals(38, $cupos['pie']->horas);
        $this->assertEquals(44, $cupos->sum('horas'));
        foreach ($cupos as $cupo) {
            $this->assertSame($registro->id, $cupo->no_renovacion_id);
            $virtual = app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, 'VACANTE-'.$cupo->id);
            $this->assertEmpty($virtual['asignaciones']);
            $this->assertSame('Pedagogía en Educación Diferencial', $virtual['titulo']);
        }
        $this->asignacion(['docente_rut' => 'VACANTE-'.$cupos['pie']->id, 'docente_rut_normalizado' => 'VACANTE_'.$cupos['pie']->id]);
        $this->assertInvalid(fn () => app(ContratacionHabilitacionService::class)->revocar($this->ee, 2027, $cupos['plan_estudio']->id), 'habilitacion');
        $this->assertNull($registro->fresh()->anulada_at);
        $this->assertEquals(44, DB::table('dotacion_contrata_habilitaciones')->sum('horas'));
    }

    public function test_solo_reemplaza_contrata_completa_y_no_planta_mixtos_ni_protegidos(): void
    {
        foreach ([
            ['horas_planta' => 1, 'horas_contrata' => 43, 'horas_contrato' => 44],
            ['horas_planta' => 44, 'horas_contrata' => 0, 'horas_contrato' => 44],
            ['horas_planta' => 0, 'horas_contrata' => 44, 'horas_contrato' => 40],
            ['horas_planta' => 0, 'horas_contrata' => 44, 'horas_contrato' => 44, 'exclusion_docente' => ['motivo' => 'fuero_maternal']],
            ['horas_planta' => 0, 'horas_contrata' => 44, 'horas_contrato' => 44, 'exclusion_docente' => ['motivo' => 'horas_gremiales']],
        ] as $docente) {
            $this->assertFalse(NoRenovacionService::elegible($docente));
        }
        $this->personal('Pedagogía en Educación Básica', 'PLANTA');
        $this->assertInvalid(fn () => $this->reemplazar(), 'docente_rut');
        $this->assertSame(0, DotacionNoRenovacion::count());
        $this->assertSame(0, DB::table('dotacion_contrata_habilitaciones')->count());
    }

    public function test_valida_justificacion_bloque_ano_y_pertenencia_sin_retirar_personal(): void
    {
        $this->personal();
        $this->assertInvalid(fn () => $this->reemplazar('plan_estudio', 'breve'), 'justificacion');
        $this->assertInvalid(fn () => $this->reemplazar('pie'), 'docente_rut');
        $this->assertInvalid(fn () => app(NoRenovacionService::class)->reemplazar($this->ee, 2026, 'plan_estudio', '99000001-K', str_repeat('x', 15), auth()->id()), 'bloque');
        $otro = Establecimiento::create(['rbd' => 99998, 'cod_estab' => 'OTHER', 'nombre_establecimiento' => 'Otra escuela sintética']);
        $this->assertInvalid(fn () => app(NoRenovacionService::class)->reemplazar($otro, 2027, 'plan_estudio', '99000001-K', str_repeat('x', 15), auth()->id()), 'docente_rut');
        $this->assertCount(1, DotacionEstablecimientoCalculator::docentes($this->ee, 2027));
        $this->assertSame(0, DotacionNoRenovacion::count());
    }

    public function test_no_renovacion_no_afecta_otro_establecimiento_ni_asignaciones_de_otro_ano(): void
    {
        $this->personal();
        $historica = $this->asignacion(['anio' => 2026]);
        $otra = Establecimiento::create(['rbd' => 99998, 'cod_estab' => 'OTHER', 'nombre_establecimiento' => 'Otra escuela sintética']);
        $this->personal(ee: $otra);
        $this->reemplazar();
        $this->assertSame('activa', $historica->fresh()->estado);
        $this->assertCount(1, DotacionEstablecimientoCalculator::docentes($otra, 2027));
    }

    public function test_rollback_conserva_docente_asignaciones_y_no_deja_cupos_ante_error(): void
    {
        $this->personal();
        $anterior = $this->asignacion();
        DotacionDocenteAsignacion::updating(fn () => throw new \RuntimeException('Fallo de prueba al archivar'));
        try {
            $this->reemplazar();
            $this->fail('Debe revertir la transacción completa.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fallo de prueba al archivar', $error->getMessage());
        }
        $this->assertSame('activa', $anterior->fresh()->estado);
        $this->assertSame(0, DotacionNoRenovacion::count());
        $this->assertSame(0, DB::table('dotacion_contrata_habilitaciones')->count());
        $this->assertCount(1, DotacionEstablecimientoCalculator::docentes($this->ee, 2027));
    }

    public function test_solicitudes_repetidas_se_rechazan_y_clave_activa_es_unica(): void
    {
        $this->personal();
        $registro = $this->reemplazar();
        $this->assertInvalid(fn () => $this->reemplazar(), 'docente_rut');
        $this->assertSame(1, DotacionNoRenovacion::count());
        $this->assertSame(1, DB::table('dotacion_contrata_habilitaciones')->count());
        $duplicate = $registro->getAttributes();
        unset($duplicate['id']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('dotacion_no_renovaciones')->insert($duplicate);
    }

    public function test_validacion_tras_bloqueo_rechaza_formulario_previo_al_retiro(): void
    {
        $this->personal();
        $persona = DotacionEstablecimientoCalculator::docentes($this->ee, 2027)->sole();
        $this->reemplazar();
        $validar = new ReflectionMethod(DotacionAsignacionController::class, 'validateProceso2027Assignment');
        $this->assertInvalid(fn () => $validar->invoke(app(DotacionAsignacionController::class), $this->ee, $persona, [
            'anio' => 2027, 'docente_rut' => '99000001-K', 'tipo_asignacion' => 'otra_funcion',
        ]), 'docente_rut');
    }

    public function test_asignacion_archivada_no_se_puede_eliminar_manipulando_formulario(): void
    {
        $this->personal();
        $asignacion = $this->asignacion();
        $this->reemplazar();
        $request = Request::create('/asignaciones-sinteticas', 'DELETE');
        $request->setUserResolver(fn () => auth()->user());
        $this->assertInvalid(fn () => app(DotacionAsignacionController::class)->destroy($request, $this->ee, $asignacion), 'asignacion');
        $this->assertSame('no_renovada', $asignacion->fresh()->estado);
    }

    public function test_anula_cupo_vacio_conserva_historial_y_no_reactiva_asignaciones(): void
    {
        $this->personal();
        $anterior = $this->asignacion();
        $registro = $this->reemplazar();
        $cupo = DB::table('dotacion_contrata_habilitaciones')->sole();
        $service = app(ContratacionHabilitacionService::class);
        $this->assertFalse($service->revocar($this->ee, 2026, $cupo->id));
        $this->assertTrue($service->revocar($this->ee, 2027, $cupo->id));
        $this->assertNotNull($registro->fresh()->anulada_at);
        $this->assertSame(auth()->id(), $registro->fresh()->anulada_by);
        $this->assertNull($registro->fresh()->clave_activa);
        $this->assertSame(0, DB::table('dotacion_contrata_habilitaciones')->count());
        $this->assertCount(1, DotacionEstablecimientoCalculator::docentes($this->ee, 2027));
        $this->assertSame('no_renovada', $anterior->fresh()->estado);
        $this->reemplazar();
        $this->assertSame(2, DotacionNoRenovacion::count());
    }

    public function test_no_anula_reemplazo_si_cupo_ya_tiene_asignaciones(): void
    {
        $this->personal();
        $registro = $this->reemplazar();
        $cupo = DB::table('dotacion_contrata_habilitaciones')->sole();
        $this->asignacion(['docente_rut' => 'VACANTE-'.$cupo->id, 'docente_rut_normalizado' => 'VACANTE_'.$cupo->id]);
        $this->assertInvalid(fn () => app(ContratacionHabilitacionService::class)->revocar($this->ee, 2027, $cupo->id), 'habilitacion');
        $this->assertNull($registro->fresh()->anulada_at);
        $this->assertCount(0, DotacionEstablecimientoCalculator::docentes($this->ee, 2027));
    }

    public function test_cupo_general_se_puede_asociar_a_asignatura_y_valida_bloque(): void
    {
        $this->personal();
        $this->reemplazar();
        $cupo = DB::table('dotacion_contrata_habilitaciones')->sole();
        $persona = app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2027, 'VACANTE-'.$cupo->id);
        $this->assertTrue(DotacionDocentesSubsector::docenteAdmisible($persona, 'basica'));
        $validar = new ReflectionMethod(DotacionAsignacionController::class, 'validateVirtualAssignment');
        $validar->invoke(app(DotacionAsignacionController::class), $this->ee, $persona, [
            'anio' => 2027, 'tipo_asignacion' => 'otra_funcion', 'horas_contrato' => 4,
        ]);
        $this->addToAssertionCount(1);
        $this->assertInvalid(fn () => $validar->invoke(app(DotacionAsignacionController::class), $this->ee, $persona, [
            'anio' => 2027, 'tipo_asignacion' => 'pie_educadora_diferencial', 'horas_contrato' => 4,
        ]), 'docente_rut');
        $this->assertInvalid(fn () => $validar->invoke(app(DotacionAsignacionController::class), $this->ee, $persona, [
            'anio' => 2027, 'tipo_asignacion' => 'otra_funcion', 'horas_contrato' => 45,
        ]), 'horas_contrato');
        $nt = EstablecimientoCurso::create([
            'establecimiento_id' => $this->ee->id, 'rbd' => 99999, 'anio' => 2027,
            'curso_id' => Curso::where('codigo', 'NT1')->sole()->id, 'letra' => 'A', 'nombre_seccion' => 'NT1 sintético', 'regimen_jec' => 'Con JEC',
        ]);
        $validar->invoke(app(DotacionAsignacionController::class), $this->ee, $persona, [
            'anio' => 2027, 'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'libre_disposicion',
            'establecimiento_curso_id' => $nt->id, 'horas_contrato' => 4,
        ]);
        $this->addToAssertionCount(1);
    }

    public function test_ui_ofrece_tres_bloques_sin_brecha_y_solicita_justificacion(): void
    {
        foreach (self::bloques() as $index => [$bloque, $titulo]) {
            $this->personal($titulo, rut: '9900000'.($index + 1).'-K');
        }
        $vars = [
            'establecimiento' => $this->ee, 'anio' => 2027, 'resumen' => [],
            'contrataHabilitacionesTableReady' => true, 'contrataHabilitaciones' => collect(),
            'canManageContrataHabilitaciones' => true, 'noRenovacionesDisponible' => true,
            'candidatosNoRenovacion' => app(NoRenovacionService::class)->candidatos($this->ee, 2027),
            'fmt' => fn ($value) => (string) $value, 'errors' => new ViewErrorBag,
        ];
        $html = Blade::render("@include('admin.dotacion-establecimiento.partials._contrata_habilitaciones') @stack('styles') @stack('scripts')", $vars);
        if (getenv('SGA_VACANTES_PREVIEW')) {
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
            $css = $manifest['resources/scss/app.scss']['file'];
            $preview = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Vacantes · datos sintéticos</title><link rel="stylesheet" href="/build/'.$css.'"></head><body><main class="container-fluid p-3"><div class="row">'.$html.'</div></main></body></html>';
            file_put_contents(storage_path('framework/testing/vacantes-preview.html'), $preview);
        }
        foreach (array_keys(NoRenovacionService::BLOQUES) as $bloque) {
            $this->assertStringContainsString('value="'.$bloque.'"', $html);
        }
        $this->assertSame(3, substr_count($html, 'name="justificacion"'));
        $this->assertSame(3, substr_count($html, 'name="cantidad"'));
        $this->assertStringContainsString('sin asignaciones ni asociaciones', $html);
        $soloLectura = Blade::render("@include('admin.dotacion-establecimiento.partials._contrata_habilitaciones')", [...$vars, 'canManageContrataHabilitaciones' => false]);
        $this->assertStringNotContainsString('name="justificacion"', $soloLectura);
    }

    public function test_http_exige_rol_autorizado_y_no_acepta_horas_o_titulo_forjados(): void
    {
        $this->personal();
        $url = route('admin.dotacion-establecimiento.contrata-habilitaciones.store', $this->ee);
        $params = [
            'accion' => 'reemplazar', 'anio' => 2027, 'bloque' => 'plan_estudio', 'docente_rut' => '99000001-K',
            'justificacion' => 'No renovación sintética debidamente fundada.', 'horas' => 99, 'titulo' => 'Título falso',
        ];
        $this->post($url, [...$params, 'justificacion' => 'breve'])->assertRedirect()->assertSessionHasErrors('justificacion');
        $this->post($url, $params)->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Pedagogía en Educación Básica', DB::table('dotacion_contrata_habilitaciones')->sole()->titulo);
        $this->assertEquals(44, DB::table('dotacion_contrata_habilitaciones')->sole()->horas);
        $this->actingAs($this->testUser(2, 1))->withSession(['active_role' => 'postulante'])->post($url, $params)->assertForbidden();
    }

    public function test_migracion_es_aditiva_repetible_y_conserva_cupos_historicos(): void
    {
        $id = DB::table('dotacion_contrata_habilitaciones')->insertGetId([
            'establecimiento_id' => $this->ee->id, 'anio' => 2026, 'bloque' => 'pie', 'horas' => 30,
        ]);
        $migration = require database_path('migrations/2026_10_13_200000_add_dotacion_no_renovaciones.php');
        $migration->up();
        $migration->down();
        $this->assertTrue(DotacionNoRenovacion::disponible());
        $legacy = app(ContratacionHabilitacionService::class)->docenteVirtual($this->ee, 2026, 'VACANTE-'.$id);
        $this->assertSame('Pedagogía en Educación Diferencial', $legacy['titulo']);
        $this->assertEquals(30, $legacy['horas_contrato']);
    }

    private function personal(string $titulo = 'Pedagogía en Educación Básica', string $tipo = 'CONTRATA', string $rut = '99000001-K', ?Establecimiento $ee = null): void
    {
        $ee ??= $this->ee;
        DB::table('reemplazos_personal')->insert([
            'establecimiento_id' => $ee->id, 'rbd' => $ee->rbd, 'rut' => $rut,
            'nombre' => 'Docente sintético '.$rut, 'anio' => 2026, 'mes' => 8, 'jornada' => 44, 'jornada_basica' => 44,
            'tipocontrato' => $tipo, 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE',
            'row_hash' => hash('sha256', 'synthetic-'.$rut.'-'.$ee->id),
        ]);
        DB::table('declaracion_sostenedores')->insert([
            'rbd' => $ee->rbd, 'rut' => $rut, 'nombres' => 'Docente sintético '.$rut,
            'nombre_titulo' => $titulo, 'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE', 'horas_contratadas' => 44,
        ]);
    }

    private function asignacion(array $override = []): DotacionDocenteAsignacion
    {
        return DotacionDocenteAsignacion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut' => '99000001-K',
            'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente sintético',
            'tipo_asignacion' => 'otra_funcion', 'horas_contrato' => 4, 'estado' => 'activa', ...$override,
        ]);
    }

    private function reemplazar(string $bloque = 'plan_estudio', string $justificacion = 'No renovación sintética debidamente fundada.'): DotacionNoRenovacion
    {
        return app(NoRenovacionService::class)->reemplazar($this->ee, 2027, $bloque, '99000001-K', $justificacion, auth()->id());
    }

    private function assertInvalid(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('La acción debe rechazarse.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey($field, $error->errors());
        }
    }

    private function resetCalculators(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) {
                (new ReflectionProperty($class, $property))->setValue(null, []);
            }
        }
    }
}
