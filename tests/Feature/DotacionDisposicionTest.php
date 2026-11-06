<?php

namespace Tests\Feature;

use App\Models\DotacionDocenteDistribucion;
use App\Models\DotacionNoRenovacion;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Models\User;
use App\Services\Dotacion\DocentesDisposicionService;
use App\Services\Dotacion\DotacionRevisionService;
use App\Support\DotacionDisposicionDatos;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionDisposicionTest extends IsolatedSecurityTestCase
{
    private Establecimiento $origen;
    private Establecimiento $destino;
    private array $fuentes = [];
    private array $necesidades = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['2026_01_23_000001_create_establecimientos_table.php',
            '2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php',
            '2026_09_29_190000_create_dotacion_sobredotacion_justificaciones_table.php',
            '2026_10_13_200000_add_dotacion_no_renovaciones.php',
            '2026_11_06_170000_create_dotacion_docente_distribuciones_table.php',
            '2026_11_06_170100_create_dotacion_establecimiento_revisiones_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('modules', fn (Blueprint $t) => [$t->id(), $t->string('key')]);
        $this->origen = Establecimiento::create(['rbd' => 99990, 'cod_estab' => 'SYNTHETIC-1', 'nombre_establecimiento' => 'Origen sintético']);
        $this->destino = Establecimiento::create(['rbd' => 99991, 'cod_estab' => 'SYNTHETIC-2', 'nombre_establecimiento' => 'Destino sintético']);
        $this->actingAs($this->testUser(1, 3))->withSession(['active_role' => 'admin']);
        foreach ([$this->origen, $this->destino] as $ee) {
            app(DotacionRevisionService::class)->finalizar($ee->id, 2028, auth()->id());
        }
        $this->fuentes = [$this->fila()];
        $mock = \Mockery::mock(DotacionDisposicionDatos::class);
        $mock->shouldReceive('datos')->andReturnUsing(function ($ee, $anio): array {
            return ['filas' => collect($this->fuentes)->where('origen_id', $ee->id)->where('anio', $anio)->values(),
                'necesidades' => $this->necesidades[$ee->id] ?? array_fill_keys(array_keys(DotacionDisposicionDatos::BLOQUES), ['cupos' => 0, 'contrata' => 0, 'horas' => 0, 'personas' => 0])];
        });
        $mock->shouldReceive('referenciasDestino')->andReturnUsing(fn ($ee, $anio) => $this->necesidades[$ee->id]
            ?? array_fill_keys(array_keys(DotacionDisposicionDatos::BLOQUES), ['cupos' => 0, 'contrata' => 0, 'horas' => 0, 'personas' => 0]));
        $this->app->instance(DotacionDisposicionDatos::class, $mock);
        // Aislar la vista nueva de las tablas de menú ajenas a esta funcionalidad.
        $path = Storage::disk('local')->path('disposicion-vistas');
        File::ensureDirectoryExists($path.'/layouts');
        File::put($path.'/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta name="viewport" content="width=device-width, initial-scale=1">@stack("styles")</head><body>@yield("content")</body></html>');
        View::getFinder()->prependLocation($path);
        $this->withoutVite();
    }

    private function fila(array $cambios = []): array
    {
        return array_replace(['anio' => 2028, 'origen_id' => $this->origen->id, 'rut' => '99000001K', 'bloque' => 'plan_estudio',
            'tipo_horas' => 'titular', 'nombre' => 'Docente sintético', 'titulo' => 'Pedagogía sintética', 'funcion' => 'DOCENTE',
            'prelacion' => '4. Titular Acceso, Inicial, Temprano', 'prioridad' => 4, 'tramo' => 'Inicial', 'fecha_antiguedad' => '2010-01-01',
            'contrato' => 44, 'horas' => 20.0, 'tipo' => 'Supresión parcial', 'justificacion' => 'Fundamento sintético completo.',
            'estado_justificacion' => 'Vigente', 'situacion' => 'Propuesta en revisión', 'fuente' => 'sobredotacion', 'fuente_id' => null,
            'rbd' => $this->origen->rbd, 'establecimiento' => $this->origen->nombre_establecimiento], $cambios);
    }

    private function payload(array $cambios = []): array
    {
        return array_replace(array_intersect_key($this->fila(), array_flip(['anio', 'origen_id', 'rut', 'bloque', 'tipo_horas'])),
            ['destino_id' => $this->destino->id, 'horas' => '12.25', 'solicitud_uuid' => (string) Str::uuid(), 'observacion' => 'Distribución sintética.'], $cambios);
    }

    private function url(string $accion = 'store', array $params = []): string
    {
        return route('admin.dotacion-establecimiento.disposicion.'.$accion, $params);
    }

    private function guardar(array $cambios = [])
    {
        return $this->post($this->url(), $this->payload($cambios))->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_distribuye_en_varios_destinos_y_limita_saldo_acumulado_sin_usar_horas_destino_como_tope(): void
    {
        $antes = DB::table('establecimientos')->get()->toJson();
        $this->guardar();
        $this->guardar(['horas' => '7.75']);
        $this->post($this->url(), $this->payload(['horas' => '0.01']))->assertSessionHasErrors('horas');
        $this->assertSame(2, DotacionDocenteDistribucion::count());
        $this->assertEquals(20, DotacionDocenteDistribucion::sum('horas'));
        $fila = app(DocentesDisposicionService::class)->listado(2028)->sole();
        $this->assertSame('asignado', $fila['estado']);
        $this->assertEquals(0, $fila['saldo']);
        $this->assertSame($antes, DB::table('establecimientos')->get()->toJson());
        $this->assertSame(0, DB::table('dotacion_contrata_habilitaciones')->count());
    }

    public function test_peticion_repetida_es_idempotente_y_cambio_de_payload_con_mismo_uuid_se_rechaza(): void
    {
        $p = $this->payload();
        $this->post($this->url(), $p)->assertRedirect();
        $this->post($this->url(), $p)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($this->url(), array_replace($p, ['horas' => 1]))->assertStatus(409);
        $this->assertSame(1, DotacionDocenteDistribucion::count());
    }

    public function test_corrige_con_nueva_version_y_anula_con_historial_restituyendo_saldo(): void
    {
        $this->guardar(); $anterior = DotacionDocenteDistribucion::sole();
        $p = $this->payload(['horas' => 10, 'motivo_correccion' => 'Ajuste sintético de la distribución.']);
        $this->put($this->url('update', [$anterior->id]), $p)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull($anterior->fresh()->anulada_at);
        $nuevo = DotacionDocenteDistribucion::whereNull('anulada_at')->sole();
        $this->assertSame($anterior->id, $nuevo->reemplaza_id);
        $this->post($this->url('anular', [$nuevo->id]), ['anio' => 2028, 'motivo' => 'Anulación sintética debidamente fundada.'])->assertRedirect();
        $this->assertSame(2, DotacionDocenteDistribucion::count());
        $this->assertSame(0, DotacionDocenteDistribucion::whereNull('anulada_at')->count());
        $this->assertEquals(20, app(DocentesDisposicionService::class)->listado(2028)->sole()['saldo']);
        $this->assertSame(auth()->id(), $nuevo->fresh()->anulada_by);
    }

    public function test_cambio_de_origen_se_detecta_conserva_snapshot_y_permite_regularizar(): void
    {
        $this->guardar(); $r = DotacionDocenteDistribucion::sole();
        $this->fuentes[0]['horas'] = 5;
        $fila = app(DocentesDisposicionService::class)->listado(2028)->sole();
        $this->assertSame('regularizar', $fila['estado']); $this->assertEquals(7.25, $fila['exceso']);
        $this->post($this->url(), $this->payload(['horas' => 1]))->assertSessionHasErrors('horas');
        $this->put($this->url('update', [$r->id]), $this->payload(['horas' => 5, 'motivo_correccion' => 'Regularización del saldo de origen.']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(20, $r->fresh()->antecedente['horas']);
        $this->assertEquals(5, DotacionDocenteDistribucion::whereNull('anulada_at')->sole()->antecedente['horas']);
    }

    public function test_origen_desaparecido_conserva_historial_y_anulacion_sin_recrear_horas(): void
    {
        $this->withoutExceptionHandling();
        $this->guardar(); $this->fuentes = [];
        $fila = app(DocentesDisposicionService::class)->listado(2028)->sole();
        $this->assertTrue($fila['sin_origen']); $this->assertEquals(0, $fila['horas']);
        $this->get($this->url('gestionar', $this->payload()))->assertOk()->assertSee('Origen sin saldo actual')->assertSee('Destinos e historial');
        $this->post($this->url('anular', [1]), ['anio' => 2028, 'motivo' => 'Origen retirado según revisión sintética.'])->assertRedirect();
        $this->assertEquals(0, app(DocentesDisposicionService::class)->listado(2028)->sole()['saldo']);
    }

    public function test_no_cruza_anos_bloques_calidades_o_docentes_por_formularios_alterados(): void
    {
        foreach ([['anio' => 2027], ['bloque' => 'pie'], ['tipo_horas' => 'contrata'], ['rut' => '99000002K']] as $c) {
            $this->post($this->url(), $this->payload($c))->assertSessionHasErrors(isset($c['anio']) ? 'origen_id' : 'horas');
        }
        $this->guardar();
        $this->post($this->url('anular', [1]), ['anio' => 2027, 'motivo' => 'Solicitud sintética de otro año.'])->assertNotFound();
        $this->put($this->url('update', [1]), $this->payload(['rut' => '99000002K', 'motivo_correccion' => 'Corrección sintética de otro docente.']))->assertStatus(409);
        $this->assertNull(DotacionDocenteDistribucion::sole()->anulada_at);
    }

    public function test_rechaza_origen_como_destino_sala_cuna_y_horas_invalidas(): void
    {
        $sala = Establecimiento::create(['rbd' => 99992, 'cod_estab' => 'SYNTHETIC-3', 'nombre_establecimiento' => 'Sala sintética', 'sala_cuna' => true]);
        foreach ([$this->origen->id, $sala->id, 999999] as $destino) {
            $this->post($this->url(), $this->payload(['destino_id' => $destino]))->assertSessionHasErrors('destino_id');
        }
        foreach (['-1', '0', '1.234', 'texto', '999999999'] as $horas) {
            $this->post($this->url(), $this->payload(['horas' => $horas]))->assertSessionHasErrors('horas');
        }
        $this->assertSame(0, DotacionDocenteDistribucion::count());
    }

    public static function rolesPermitidos(): array
    {
        return array_map(fn ($r) => [$r], DocentesDisposicionService::ROLES);
    }

    #[DataProvider('rolesPermitidos')]
    public function test_cuatro_roles_pueden_consultar_crear_corregir_y_anular(string $rol): void
    {
        if ($rol !== 'admin') {
            DB::table('roles')->insert(['id' => 20, 'name' => $rol, 'guard_name' => 'web']);
            DB::table('model_has_roles')->where('model_id', auth()->id())->update(['role_id' => 20]);
            auth()->user()->unsetRelation('roles');
        }
        $this->withSession(['active_role' => $rol]);
        $this->get($this->url('index', ['anio' => 2028]))->assertOk()->assertSee('Docentes a disposición');
        $this->guardar();
        $this->put($this->url('update', [1]), $this->payload(['horas' => 5, 'motivo_correccion' => 'Corrección sintética por rol autorizado.']))->assertRedirect()->assertSessionHasNoErrors();
        $this->post($this->url('anular', [2]), ['anio' => 2028, 'motivo' => 'Anulación sintética por rol autorizado.'])->assertRedirect();
        $this->post(route('admin.dotacion-establecimiento.revision.finalizar', $this->origen->id), ['anio' => 2026, 'confirmacion' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $revision = \App\Models\DotacionEstablecimientoRevision::where('anio', 2026)->sole();
        $this->post(route('admin.dotacion-establecimiento.revision.reabrir', $this->origen->id),
            ['anio' => 2026, 'revision_id' => $revision->id, 'motivo' => 'Reapertura sintética por rol autorizado.'])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_denegacion_a_directivo_y_admin_con_rol_activo_no_autorizado(): void
    {
        DB::table('roles')->insert(['id' => 21, 'name' => 'funcionario_directivo_estab', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => 21, 'model_type' => User::class, 'model_id' => auth()->id()]);
        auth()->user()->unsetRelation('roles'); $this->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->get($this->url('index'))->assertForbidden();
        $this->get($this->url('gestionar', $this->payload()))->assertForbidden();
        $this->post($this->url(), $this->payload())->assertForbidden();
        $this->put($this->url('update', [1]), $this->payload())->assertForbidden();
        $this->post($this->url('anular', [1]), ['anio' => 2028, 'motivo' => 'Motivo de prueba suficientemente largo.'])->assertForbidden();
        $this->post(route('admin.dotacion-establecimiento.revision.finalizar', $this->origen->id), ['anio' => 2026, 'confirmacion' => 1])->assertForbidden();
        $this->post(route('admin.dotacion-establecimiento.revision.reabrir', $this->origen->id), ['anio' => 2028, 'revision_id' => 1, 'motivo' => 'Reapertura sintética no autorizada.'])->assertForbidden();
    }

    public function test_nomina_aplica_prelacion_global_antiguedad_y_nombre_con_filtros(): void
    {
        $this->fuentes = [
            $this->fila(['rut' => '99000001K', 'nombre' => 'Contrata sintética', 'prioridad' => 5]),
            $this->fila(['rut' => '99000002K', 'nombre' => 'Titular reciente', 'prioridad' => 3, 'fecha_antiguedad' => '2018-01-01']),
            $this->fila(['rut' => '99000003K', 'nombre' => 'Titular antigua', 'prioridad' => 3, 'fecha_antiguedad' => '2000-01-01']),
        ];
        $this->get($this->url('index', ['anio' => 2028]))->assertOk()->assertSeeInOrder(['Titular antigua', 'Titular reciente', 'Contrata sintética']);
        $this->get($this->url('index', ['anio' => 2028, 'q' => 'SIN COINCIDENCIAS']))->assertOk()->assertDontSee('Titular antigua');
        $this->get($this->url('index', ['anio' => 2028, 'q' => 'KINDER SIN COINCIDENCIAS']))->assertOk()->assertDontSee('Titular antigua');
        $this->get($this->url('index', ['anio' => 2028, 'q' => '99.000.003-K']))->assertOk()->assertSee('Titular antigua')->assertDontSee('Titular reciente');
        $this->get($this->url('index', ['anio' => 2027]))->assertOk()->assertDontSee('Titular antigua');
    }

    public function test_selector_muestra_referencia_separada_y_permanece_disponible_con_cero(): void
    {
        $this->withoutExceptionHandling();
        $this->necesidades[$this->destino->id] = array_fill_keys(array_keys(DotacionDisposicionDatos::BLOQUES), ['cupos' => 3, 'contrata' => 7, 'horas' => 10, 'personas' => 2]);
        $this->get($this->url('gestionar', $this->payload()))->assertOk()->assertSee('10 h en cupos con asignación')
            ->assertSee('Por contratar: 3 h')->assertSee('Contrata completa: 7 h')->assertSee('fuero maternal o gremial');
        $this->necesidades = [];
        $this->get($this->url('gestionar', $this->payload()))->assertOk()->assertSee('0 h en cupos con asignación');
        $this->guardar(['horas' => 20]);
    }

    public function test_rollback_de_correccion_conserva_version_activa_ante_fallo_de_insert(): void
    {
        $this->guardar();
        DotacionDocenteDistribucion::creating(fn () => throw new \RuntimeException('Fallo sintético.'));
        try {
            app(DocentesDisposicionService::class)->guardar($this->payload(['horas' => 5, 'motivo_correccion' => 'Ajuste sintético de prueba.']), auth()->id(), 1);
            $this->fail('Debe revertir la corrección.');
        } catch (\RuntimeException $e) { $this->assertSame('Fallo sintético.', $e->getMessage()); }
        finally { DotacionDocenteDistribucion::flushEventListeners(); }
        $this->assertNull(DotacionDocenteDistribucion::sole()->anulada_at);
    }

    public function test_migracion_repetible_y_reversion_preservan_historial(): void
    {
        $this->guardar(); $m = require database_path('migrations/2026_11_06_170000_create_dotacion_docente_distribuciones_table.php');
        $m->up(); $m->down();
        $this->assertSame(1, DotacionDocenteDistribucion::count());
    }

    public function test_fuentes_por_bloque_distinguen_total_parcial_y_retiro_sin_duplicar_horas(): void
    {
        $base = ['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Título sintético', 'contrato_considerado' => 44,
            'prelacion' => '3. Titular · Avanzado', 'estado_titular' => 'Vigente', 'estado_contrata' => 'Vigente'];
        $informe = ['bloques' => ['plan_estudio' => ['filas' => [array_replace($base, ['horas_titular' => 20, 'horas_contrata' => 4])]],
            'pie' => ['filas' => [array_replace($base, ['horas_titular' => 20, 'horas_contrata' => 0])]]]];
        $datos = new DotacionDisposicionDatos;
        $filas = $datos->filas($this->origen, 2028, $informe);
        $this->assertCount(3, $filas); $this->assertSame(2, $filas->where('tipo', 'Supresión total')->count());
        $informe['bloques']['pie']['filas'] = [];
        $this->assertSame('Supresión parcial', $datos->filas($this->origen, 2028, $informe)->first()['tipo']);
        $nr = DotacionNoRenovacion::create(['establecimiento_id' => $this->origen->id, 'anio' => 2028, 'docente_rut_normalizado' => '99000001K',
            'docente_nombre' => 'Docente sintético', 'titulo' => 'Título sintético', 'bloque' => 'plan_estudio', 'horas_contrata' => 44,
            'justificacion' => 'No renovación sintética.', 'asignaciones_anteriores' => []]);
        DB::table('dotacion_contrata_habilitaciones')->insert([
            ['establecimiento_id' => $this->origen->id, 'anio' => 2028, 'bloque' => 'plan_estudio', 'horas' => 10, 'no_renovacion_id' => $nr->id],
            ['establecimiento_id' => $this->origen->id, 'anio' => 2028, 'bloque' => 'pie', 'horas' => 34, 'no_renovacion_id' => $nr->id],
        ]);
        $filas = $datos->filas($this->origen, 2028, $informe);
        $this->assertCount(2, $filas); $this->assertEquals(44, $filas->sum('horas'));
        $this->assertSame(['no_renovacion'], $filas->pluck('fuente')->unique()->values()->all());
        $this->assertEquals(0, $datos->filas($this->origen, 2027, ['bloques' => []])->count());
    }

    protected function prepararTablasCalculo(): void
    {
        Schema::table('modules', function (Blueprint $t): void {
            foreach (['name', 'section', 'icon'] as $c) { $t->string($c)->nullable(); }
            $t->integer('sort')->default(0); $t->timestamps();
        });
        foreach (['2026_01_29_000001_create_reemplazos_personal_table.php', '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php', '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php', '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); foreach (['rbd', 'rut', 'nombres', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $c) { $t->string($c); }
        });
    }

    public function test_integracion_con_calculo_real_sin_alterar_personal_ni_asignaciones(): void
    {
        $this->prepararTablasCalculo();
        foreach ([$this->origen, $this->destino] as $ee) {
            DB::table('reemplazos_personal')->insert(['establecimiento_id' => $ee->id, 'rbd' => $ee->rbd, 'rut' => '99000001-K', 'nombre' => 'Docente sintético',
                'anio' => 2026, 'mes' => 10, 'jornada' => 44, 'tipocontrato' => $ee->id === $this->origen->id ? 'PLANTA' : 'CONTRATA',
                'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-'.$ee->id]);
            DB::table('declaracion_sostenedores')->insert(['rbd' => $ee->rbd, 'rut' => '99000001-K', 'nombres' => 'Docente sintético',
                'nombre_titulo' => 'Pedagogía en Educación Básica', 'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE']);
        }
        $cupo = DB::table('dotacion_contrata_habilitaciones')->insertGetId(['establecimiento_id' => $this->destino->id, 'anio' => 2026, 'bloque' => 'plan_estudio', 'horas' => 20]);
        foreach ([['99000001-K', '99000001K', 8], ['VACANTE-'.$cupo, 'VACANTE_'.$cupo, 6]] as [$rut, $normalizado, $horas]) {
            \App\Models\DotacionDocenteAsignacion::create(['establecimiento_id' => $this->destino->id, 'anio' => 2026, 'docente_rut' => $rut,
                'docente_rut_normalizado' => $normalizado, 'docente_nombre' => 'Docente sintético', 'tipo_asignacion' => 'otra_funcion', 'horas_contrato' => $horas, 'estado' => 'activa']);
        }
        foreach ([\App\Support\DotacionEstablecimientoCalculator::class, \App\Support\DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $p) { (new \ReflectionProperty($class, $p))->setValue(null, []); }
        }
        try {
            $datos = new DotacionDisposicionDatos;
            $fuentes = $datos->datos($this->origen, 2026)['filas'];
            $this->assertSame('Supresión total', $fuentes->sole()['tipo']); $this->assertEquals(44, $fuentes->sole()['horas']);
            $referencias = $datos->referenciasDestino($this->destino, 2026);
            $this->assertSame(6.0, $referencias['plan_estudio']['cupos']);
            $this->assertSame(8.0, $referencias['plan_estudio']['contrata']);
            $antes = DB::table('reemplazos_personal')->get()->toJson(); $asignadas = DB::table('dotacion_docente_asignaciones')->get()->toJson();
            foreach ([$this->origen, $this->destino] as $ee) { app(DotacionRevisionService::class)->finalizar($ee->id, 2026, auth()->id()); }
            (new DocentesDisposicionService($datos))->guardar($this->payload(['anio' => 2026, 'horas' => 30]), auth()->id());
            $this->assertSame($antes, DB::table('reemplazos_personal')->get()->toJson());
            $this->assertSame($asignadas, DB::table('dotacion_docente_asignaciones')->get()->toJson());
        } finally {
            foreach ([\App\Support\DotacionEstablecimientoCalculator::class, \App\Support\DotacionAsignacionCalculator::class] as $class) {
                foreach (['schemaTableCache', 'schemaColumnCache'] as $p) { (new \ReflectionProperty($class, $p))->setValue(null, []); }
            }
        }
    }

    public function test_revisiones_por_ano_limitan_nomina_destinos_y_guardado_aun_con_formulario_antiguo(): void
    {
        $this->fuentes[] = $this->fila(['anio' => 2027]);
        $this->assertCount(0, app(DocentesDisposicionService::class)->listado(2027));
        $this->guardar();
        $revision = \App\Models\DotacionEstablecimientoRevision::where('establecimiento_id', $this->destino->id)->sole();
        app(DotacionRevisionService::class)->reabrir($this->destino->id, 2028, $revision->id, 'Revisión sintética pendiente de ajuste.', auth()->id());
        $this->assertCount(0, app(DocentesDisposicionService::class)->destinos(2028, 'plan_estudio', $this->origen->id));
        $this->post($this->url(), $this->payload(['horas' => 1]))->assertSessionHasErrors('destino_id');
        $revision = \App\Models\DotacionEstablecimientoRevision::where('establecimiento_id', $this->origen->id)->sole();
        app(DotacionRevisionService::class)->reabrir($this->origen->id, 2028, $revision->id, 'Revisión sintética pendiente del origen.', auth()->id());
        $this->get($this->url('index', ['anio' => 2028]))->assertOk()->assertDontSee('Docente sintético');
        $this->post($this->url(), $this->payload(['horas' => 1]))->assertSessionHasErrors('origen_id');
        $this->get($this->url('gestionar', $this->payload()))->assertOk()->assertSee('no está revisado y finalizado')->assertDontSee('Guardar distribución');
        $this->post($this->url('anular', [1]), ['anio' => 2028, 'motivo' => 'Anulación permitida tras reapertura.'])->assertRedirect();
        $this->assertNotNull(DotacionDocenteDistribucion::sole()->anulada_at);
        $this->assertFalse(DotacionRevisionService::finalizado($this->origen->id, 2027));
    }

    public function test_revision_http_exige_confirmacion_motivo_ano_y_conserva_ciclos(): void
    {
        $finalizar = route('admin.dotacion-establecimiento.revision.finalizar', $this->origen->id);
        $reabrir = route('admin.dotacion-establecimiento.revision.reabrir', $this->origen->id);
        $this->post($finalizar, ['anio' => 2027])->assertSessionHasErrors('confirmacion');
        $this->post($finalizar, ['anio' => 2027, 'confirmacion' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->post($finalizar, ['anio' => 2027, 'confirmacion' => 1])->assertRedirect();
        $revision = \App\Models\DotacionEstablecimientoRevision::where('anio', 2027)->sole();
        $this->post($reabrir, ['anio' => 2028, 'revision_id' => $revision->id, 'motivo' => 'Otro año sintético.'])->assertNotFound();
        $this->post($reabrir, ['anio' => 2027, 'revision_id' => $revision->id])->assertSessionHasErrors('motivo');
        $this->post($reabrir, ['anio' => 2027, 'revision_id' => $revision->id, 'motivo' => 'Reapertura sintética para nueva revisión.'])->assertRedirect();
        $this->assertFalse(DotacionRevisionService::finalizado($this->origen->id, 2027));
        $this->post($finalizar, ['anio' => 2027, 'confirmacion' => 1])->assertRedirect();
        $this->assertSame(2, \App\Models\DotacionEstablecimientoRevision::where('anio', 2027)->count());
        $this->assertTrue(DotacionRevisionService::finalizado($this->origen->id, 2028));
        $this->assertNotNull($revision->fresh()->reabierta_at);
        $this->assertSame(auth()->id(), $revision->finalizada_by);
        $m = require database_path('migrations/2026_11_06_170100_create_dotacion_establecimiento_revisiones_table.php');
        $m->up(); $m->down();
        $this->assertSame(4, \App\Models\DotacionEstablecimientoRevision::count());
    }

    public function test_vista_general_presenta_marcas_anuales_y_establecimientos_pendientes(): void
    {
        $this->prepararTablasCalculo();
        $this->withoutExceptionHandling();
        $this->get(route('admin.dotacion-establecimiento.index', ['anio' => 2028]))->assertOk()
            ->assertSee('Revisión 2028')->assertSee('Revisado y finalizado')->assertSee('Reabrir revisión')->assertSee('Cuenta de prueba');
        $this->get(route('admin.dotacion-establecimiento.index', ['anio' => 2026]))->assertOk()
            ->assertSee('Pendiente de revisión')->assertSee('Guardar revisión finalizada')->assertDontSee('Confirmar reapertura');
    }
}
