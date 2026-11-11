<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionSobredotacionJustificacionController;
use App\Models\DotacionSobredotacionJustificacion as Justificacion;
use App\Models\Establecimiento;
use App\Support\DotacionDisposicionDatos;
use App\Support\DotacionNominaDocentes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionJustificacionEnsenanzaTest extends IsolatedSecurityTestCase
{
    protected Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        foreach (['2026_01_23_000001_create_establecimientos_table.php',
            '2026_09_29_190000_create_dotacion_sobredotacion_justificaciones_table.php',
            '2026_11_11_140000_add_ensenanza_to_dotacion_justificaciones.php',
            '2026_01_30_173934_create_areas_desempeno_tables.php',
            '2026_01_29_000001_create_reemplazos_personal_table.php', '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php', '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php', '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); foreach (['rbd', 'rut', 'nombres', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $c) { $t->string($c); }
        });
        $this->ee = Establecimiento::create(['rbd' => 99990, 'cod_estab' => 'SYNTHETIC-1', 'nombre_establecimiento' => 'Establecimiento sintético']);
        $this->actingAs($this->testUser(1, 3))->withSession(['active_role' => 'admin']);
        DB::table('areas_desempeno')->insert([
            ['id' => 11, 'nombre' => 'Matemática de prueba', 'estamento' => 'docente', 'activo' => 1],
            ['id' => 12, 'nombre' => 'Lenguaje de prueba', 'estamento' => 'docente', 'activo' => 1],
            ['id' => 21, 'nombre' => 'Área AAEE de prueba', 'estamento' => 'asistente', 'activo' => 1],
            ['id' => 22, 'nombre' => 'Área docente inactiva', 'estamento' => 'docente', 'activo' => 0],
        ]);
        foreach (['PLANTA' => 40, 'CONTRATA' => 4] as $tipo => $horas) {
            DB::table('reemplazos_personal')->insert(['establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd,
                'rut' => '99000001-K', 'nombre' => 'Docente sintético', 'anio' => 2026, 'mes' => 10,
                'jornada' => $horas, 'tipocontrato' => $tipo, 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-'.$tipo]);
        }
        DB::table('declaracion_sostenedores')->insert(['rbd' => $this->ee->rbd, 'rut' => '99000001-K', 'nombres' => 'Docente sintético',
            'nombre_titulo' => 'Pedagogía sintética', 'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE']);
        $this->resetSchemaCaches();
    }

    protected function tearDown(): void
    {
        $this->resetSchemaCaches();
        parent::tearDown();
    }

    private function resetSchemaCaches(): void
    {
        foreach ([\App\Support\DotacionEstablecimientoCalculator::class, \App\Support\DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $p) { (new \ReflectionProperty($class, $p))->setValue(null, []); }
        }
    }

    protected function payload(array $changes = []): array
    {
        return array_replace(['anio' => 2026, 'bloque' => 'plan_estudio', 'tipo_horas' => 'titular', 'docente_rut' => '99000001-K',
            'justificacion' => 'Fundamento sintético de ajuste docente.', 'nivel_ensenanza' => 'basica', 'areas_desempeno_ids' => [11, 12]], $changes);
    }

    protected function guardar(array $changes = [], string $rol = 'admin', ?int $idEstablecimiento = null): void
    {
        $usuario = new class($rol, $idEstablecimiento) {
            public int $id = 1;
            public function __construct(private string $rol, public ?int $establecimiento_id) {}
            public function activeRoleName(): string { return $this->rol; }
        };
        $request = Request::create('/', 'POST', $this->payload($changes));
        $request->setUserResolver(fn () => $usuario);
        $response = app(DotacionSobredotacionJustificacionController::class)->store($request, $this->ee);
        $this->assertStringContainsString('#vacantes-plan_estudio', $response->getTargetUrl());
    }

    public function test_guarda_los_seis_niveles_y_varias_areas_del_catalogo_con_nombres_canonicos(): void
    {
        foreach (array_keys(Justificacion::NIVELES_ENSENANZA) as $nivel) {
            $this->guardar(['nivel_ensenanza' => $nivel, 'areas_ensenanza' => [['id' => 11, 'nombre' => 'Nombre adulterado']]]);
            $r = Justificacion::sole();
            $this->assertSame($nivel, $r->nivel_ensenanza);
            $this->assertSame([12, 11], $r->areasEnsenanzaIds());
            $this->assertSame(['Lenguaje de prueba', 'Matemática de prueba'], $r->areasEnsenanzaNombres());
            $this->assertEquals(40, $r->horas_detectadas);
        }
        $this->guardar(['tipo_horas' => 'contrata', 'areas_desempeno_ids' => [11]]);
        $this->assertSame(2, Justificacion::count());
        $this->assertEquals(4, Justificacion::where('tipo_horas', 'contrata')->sole()->horas_detectadas);
        $this->guardar(['nivel_ensenanza' => 'media', 'areas_desempeno_ids' => [12]]);
        $this->assertSame([12], Justificacion::where('tipo_horas', 'titular')->sole()->areasEnsenanzaIds());
        $this->assertSame([11], Justificacion::where('tipo_horas', 'contrata')->sole()->areasEnsenanzaIds());
    }

    public static function entradasInvalidas(): array
    {
        return ['nivel ausente' => [['nivel_ensenanza' => null], 'nivel_ensenanza'],
            'nivel inexistente' => [['nivel_ensenanza' => 'otro'], 'nivel_ensenanza'],
            'nivel como arreglo' => [['nivel_ensenanza' => ['basica']], 'nivel_ensenanza'],
            'sin areas' => [['areas_desempeno_ids' => []], 'areas_desempeno_ids'],
            'area escalar' => [['areas_desempeno_ids' => '11'], 'areas_desempeno_ids'],
            'AAEE' => [['areas_desempeno_ids' => [11, 21]], 'areas_desempeno_ids'],
            'inactiva' => [['areas_desempeno_ids' => [22]], 'areas_desempeno_ids'],
            'inexistente' => [['areas_desempeno_ids' => [999]], 'areas_desempeno_ids'],
            'repetidas' => [['areas_desempeno_ids' => [11, '11']], 'areas_desempeno_ids.0'],
            'area anidada' => [['areas_desempeno_ids' => [[11]]], 'areas_desempeno_ids.0'],
            'otro docente' => [['docente_rut' => '99000002-K'], 'docente_rut'],
            'bloque sin horas' => [['bloque' => 'pie'], 'docente_rut']];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_entradas_invalidas_sin_alterar_el_fundamento_previo(array $changes, string $campo): void
    {
        $this->guardar();
        $anterior = Justificacion::sole()->getAttributes();
        try { $this->guardar($changes); $this->fail('La selección alterada debía rechazarse.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey($campo, $e->errors()); }
        $this->assertSame($anterior, Justificacion::sole()->getAttributes());
    }

    public function test_historial_mantiene_nombres_y_vigencia_tras_renombrar_o_desactivar_areas(): void
    {
        $this->guardar();
        DB::table('areas_desempeno')->where('id', 11)->update(['nombre' => 'Nombre posterior', 'activo' => 0]);
        $r = Justificacion::sole();
        $this->assertStringContainsString('Matemática de prueba', $r->fundamentoCompleto());
        $this->assertStringNotContainsString('Nombre posterior', $r->fundamentoCompleto());
        $this->assertTrue($r->vigentePara(40));
        $this->assertSame([12], Justificacion::areasDocentesActivas()->pluck('id')->all());
        $this->assertStringContainsString('Áreas registradas fuera del catálogo activo: Matemática de prueba', $this->renderSobredotacion());
        $r->forceFill(['nivel_ensenanza' => null, 'areas_ensenanza' => null])->save();
        $this->assertSame($r->justificacion, $r->fundamentoCompleto());
        $this->assertTrue($r->vigentePara(40));
        $this->assertSame(['99000001K'], Justificacion::rutsJustificados($this->ee->id, 2026));
        $this->assertStringContainsString('No informado', $this->renderSobredotacion(false));
    }

    public function test_informes_y_fuentes_de_distribucion_incluyen_el_nivel_y_las_areas_sin_cambiar_horas(): void
    {
        $this->guardar();
        $datos = (new DotacionNominaDocentes)->datosSobredotacion($this->ee, 2026);
        $fila = $datos['bloques']['plan_estudio']->sole();
        $this->assertStringContainsString('Nivel de enseñanza: Enseñanza Básica', $fila['justificacion_titular']);
        $this->assertStringContainsString('Área de enseñanza: Lenguaje de prueba, Matemática de prueba', $fila['justificacion_titular']);
        $this->assertSame('Vigente', $fila['estado_titular']);
        $fuentes = (new DotacionDisposicionDatos)->datos($this->ee, 2026)['filas'];
        $this->assertSame(Justificacion::sole()->fundamentoCompleto(), $fuentes->where('tipo_horas', 'titular')->sole()['justificacion']);
        $this->assertEquals(40, $fuentes->where('tipo_horas', 'titular')->sole()['horas']);
    }

    public function test_permisos_y_establecimiento_se_conservan(): void
    {
        foreach (['coordinador_gdp', 'coordinador_uatp', 'postulante'] as $rol) {
            try { $this->guardar([], $rol, $this->ee->id); $this->fail('No debía autorizarse.'); }
            catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        }
        try { $this->guardar([], 'funcionario_directivo_estab', $this->ee->id + 1); $this->fail('No debía autorizarse.'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->guardar([], 'supervisor_plani');
        $this->guardar([], 'funcionario_directivo_estab', $this->ee->id);
        $this->assertSame(1, Justificacion::count());
    }

    public function test_migracion_pendiente_no_genera_error_de_columna_ni_modifica_el_historial(): void
    {
        Schema::table('dotacion_sobredotacion_justificaciones', fn (Blueprint $t) => $t->dropColumn('areas_ensenanza'));
        try { $this->guardar(); $this->fail('Debía informar la migración pendiente.'); }
        catch (HttpException $e) { $this->assertSame(503, $e->getStatusCode()); }
        $this->assertSame(0, Justificacion::count());
    }

    protected function renderSobredotacion(bool $editar = true): string
    {
        $fila = ['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'funcion' => 'DOCENTE', 'tipo_contrato' => 'PLANTA / CONTRATA',
            'horas_contrato_categoria' => 44, 'horas_sobredotacion_total' => 44, 'horas_sobredotacion_planta' => 40, 'horas_sobredotacion_contrata' => 4];
        $bloque = ['items' => collect([$fila]), 'horas_total' => 44, 'horas_planta' => 40, 'horas_contrata' => 4];
        return view('admin.dotacion-establecimiento.partials._sobredotacion', [
            'sobredotacion' => ['aula' => ['items' => collect(), 'ajustes' => collect(), 'resumen' => []], 'protegidos' => collect(),
                'vacantes_por_bloque' => ['plan_estudio' => $bloque, 'parvularia' => $bloque, 'pie' => $bloque]],
            'sobredotacionTipo' => 'aula', 'establecimiento' => $this->ee, 'anio' => 2026,
            'justificacionesSobredotacionTableReady' => true, 'ensenanzaJustificacionDisponible' => true,
            'areasDocentesJustificacion' => Justificacion::areasDocentesActivas(),
            'justificacionesSobredotacion' => Justificacion::registrosPara($this->ee->id, 2026)
                ->keyBy(fn ($r) => Justificacion::clave($r->bloque, $r->docente_rut_normalizado, $r->tipo_horas)),
            'canManageJustificacionesSobredotacion' => $editar,
        ])->render();
    }

    public function test_formularios_por_bloque_con_opciones_docentes_multiples_y_campos_unicos(): void
    {
        $html = $this->renderSobredotacion();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(6, $xpath->query('//select[@name="nivel_ensenanza"]')->length);
        $this->assertSame(6, $xpath->query('//select[@name="areas_desempeno_ids[]"][@multiple]')->length);
        foreach ($xpath->query('//select[@name="nivel_ensenanza"]') as $select) {
            $this->assertSame(['', ...array_keys(Justificacion::NIVELES_ENSENANZA)],
                array_map(fn ($o) => $o->getAttribute('value'), iterator_to_array($select->getElementsByTagName('option'))));
        }
        $this->assertStringNotContainsString('Área AAEE de prueba', $html);
        $this->assertStringNotContainsString('Área docente inactiva', $html);
        $ids = array_map(fn ($e) => $e->getAttribute('id'), iterator_to_array($xpath->query('//*[@id]')));
        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->guardar();
        $consulta = $this->renderSobredotacion(false);
        $this->assertStringContainsString('Enseñanza Básica', $consulta);
        $this->assertStringContainsString('Matemática de prueba', $consulta);
        $this->assertStringNotContainsString('name="areas_desempeno_ids[]"', $consulta);
    }

    public function test_error_restaura_selecciones_solo_en_el_formulario_enviado(): void
    {
        $this->guardar();
        $this->app['request']->setLaravelSession(session()->driver());
        session()->flash('_old_input', $this->payload(['bloque' => 'pie', 'tipo_horas' => 'contrata', 'nivel_ensenanza' => 'epja', 'areas_desempeno_ids' => [12]]));
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['areas_desempeno_ids' => ['Error sintético de selección.']])));
        $html = $this->renderSobredotacion();
        $this->assertSame(1, substr_count($html, 'value="epja" selected'));
        $this->assertSame(1, substr_count($html, 'value="basica" selected'));
        $this->assertStringContainsString('Error sintético de selección.', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
    }

    protected function prepararVistaCompleta(): void
    {
        Schema::create('modules', function (Blueprint $t): void {
            $t->id(); $t->string('key'); $t->string('name')->nullable(); $t->string('section')->nullable();
            $t->string('icon')->nullable(); $t->integer('sort')->default(0); $t->timestamps();
        });
        $path = \Illuminate\Support\Facades\Storage::disk('local')->path('ensenanza-vistas');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($path.'/layouts');
        \Illuminate\Support\Facades\File::put($path.'/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta name="viewport" content="width=device-width, initial-scale=1">@stack("styles")</head><body><main>@yield("content")</main>@stack("scripts")</body></html>');
        view()->getFinder()->prependLocation($path);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureModuleAccess::class, \App\Http\Middleware\CoordinarEscrituraPadron::class]);
    }

    public function test_detalle_carga_el_catalogo_docente_en_el_controlador(): void
    {
        $this->prepararVistaCompleta();
        $this->withoutExceptionHandling();
        $this->get(route('admin.dotacion-establecimiento.show', [$this->ee, 'anio' => 2026, 'tab' => 'sobredotacion']))
            ->assertOk()->assertViewHas('ensenanzaJustificacionDisponible', true)
            ->assertViewHas('areasDocentesJustificacion', fn ($areas) => $areas->pluck('id')->all() === [12, 11])
            ->assertSee('Matemática de prueba')->assertDontSee('Área AAEE de prueba');
    }
}
