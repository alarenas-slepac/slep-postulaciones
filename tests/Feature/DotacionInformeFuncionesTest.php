<?php

namespace Tests\Feature;

use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionInformeFunciones;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionInformeFuncionesTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp(); // SQLite :memory:, almacenamiento simulado y sin configuración cacheada.
        $this->resetCalculators();
        $this->withoutVite();
        $disk = Storage::disk('local');
        $disk->put('informe-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head>@stack(\'styles\')</head><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation($disk->path('informe-views'));
        foreach ([
            '2026_01_23_000001_create_establecimientos_table.php',
            '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_22_150000_add_estamento_cobertura_to_dotacion_docente_asignaciones_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::create('modules', fn (Blueprint $t) => [$t->id(), $t->string('key')]);
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        $this->ee = $this->establecimiento(99999);
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public static function roles(): array
    {
        return [['admin', true], ['coordinador_uatp', true], ['coordinador_gdp', true], ['supervisor_plani', true],
            ['funcionario_directivo_estab', false], ['funcionario_ac', false], ['postulante', false], ['coordinador_plani', false]];
    }

    #[DataProvider('roles')]
    public function test_acceso_http_y_boton_se_restringen_al_rol_activo(string $rol, bool $permitido): void
    {
        $this->usarRol($rol); // La cuenta también tiene admin: no debe eludir el rol activo.
        $respuesta = $this->get($this->url(2027));
        if ($permitido) {
            $respuesta->assertOk()->assertViewIs('admin.dotacion-establecimiento.informe-funciones');
        } else {
            $respuesta->assertForbidden();
        }
        $html = view('admin.dotacion-establecimiento.index', [
            'establecimientos' => new LengthAwarePaginator([], 0, 20), 'anio' => 2027,
            'activeRole' => $rol, 'q' => '', 'comuna' => '', 'comunas' => collect(),
        ])->render();
        $this->assertSame($permitido, str_contains($html, 'Informe de dotación por funciones'));
    }

    public static function years(): array
    {
        return [[2026], [2027]];
    }

    #[DataProvider('years')]
    public function test_separa_funciones_conserva_contrato_completo_y_no_duplica_personas_ni_reservas(int $year): void
    {
        $this->personal('99000001-K', 44);
        $this->personal('99000001-K', 6, ['financiamiento' => 'SEP', 'tipocontrato' => 'CONTRATA']);
        foreach ([
            ['funcion_directiva', 'Director(a)', 10], ['funcion_directiva', 'Inspector(a) General', 4],
            ['funcion_tecnico_pedagogica', 'Jefe(a) UTP', 3], ['funcion_tecnico_pedagogica', 'Coordinador(a) de Convivencia Educativa', 2],
            ['plan_estudio', 'Lenguaje', 12], ['plan_normativo', 'CRA', 2], ['pie_colaborativo', 'Trabajo colaborativo PIE', 3],
            ['funcion_tecnico_pedagogica', 'Coordinador PIE', 2], ['pie_educadora_diferencial', 'Bolsa diferencial', 3],
            ['otra_funcion', 'Apoyo adicional', 2], ['reserva_no_normativa', 'Reservadas', 3],
        ] as [$type, $name, $hours]) {
            $this->asignacion($year, '99000001-K', $type, $name, $hours);
        }
        $this->asignacion($year === 2026 ? 2027 : 2026, '99000001-K', 'otra_funcion', 'Otro año', 100);
        $before = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        $fila = app(DotacionInformeFunciones::class)->fila($this->ee, $year);
        $this->assertSame(1, $fila['docentes_total']);
        $this->assertSame(50.0, $fila['contrato_total']);
        $this->assertSame(43.0, $fila['asignadas']);
        $this->assertSame(3.0, $fila['reservadas']);
        $this->assertSame(4.0, $fila['sin_asignar']);
        $this->assertSame(6.0, $fila['sep']);
        $this->assertSame(['docentes' => 1, 'horas' => 10.0], $fila['grupos']['directiva']);
        $this->assertSame(['docentes' => 1, 'horas' => 9.0], $fila['grupos']['tecnico_pedagogica']);
        $this->assertSame(['docentes' => 1, 'horas' => 17.0], $fila['grupos']['aula']);
        $this->assertSame(['docentes' => 1, 'horas' => 5.0], $fila['grupos']['pie']);
        $this->assertSame(['08/2026'], $fila['periodos']);
        $this->assertSame($before, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
        $this->get($this->url($year))->assertOk()->assertSee('Horas SEP '.$year)->assertSee('Ver conciliación')->assertViewHas('errores', 0);
    }

    public function test_un_exceso_no_compensa_saldo_de_otro_docente_y_excluye_asistentes_y_cupos(): void
    {
        $this->personal('99000001-K', 10);
        $this->personal('99000002-K', 20);
        $this->personal('99000003-K', 44, ['estatuto' => 'ASISTENTE DE LA EDUCACION', 'escalafon' => 'ASISTENTE']);
        $this->asignacion(2027, '99000001-K', 'plan_estudio', 'Aula', 15);
        $this->asignacion(2027, '99000003-K', 'plan_estudio', 'Aula AAEE', 8, ['estamento_cobertura' => 'asistente']);
        $this->asignacion(2027, 'VACANTE-1', 'plan_estudio', 'Cupo virtual', 44);
        $this->asignacion(2027, '99000009-K', 'plan_estudio', 'Persona no vigente', 44);
        $fila = app(DotacionInformeFunciones::class)->fila($this->ee, 2027);
        $this->assertSame(2, $fila['docentes_total']);
        $this->assertSame(30.0, $fila['contrato_total']);
        $this->assertSame(15.0, $fila['asignadas']);
        $this->assertSame(20.0, $fila['sin_asignar']);
        $this->assertSame(5.0, $fila['sobreasignadas']);
        $this->assertEquals($fila['contrato_total'], $fila['asignadas'] + $fila['reservadas'] + $fila['sin_asignar'] - $fila['sobreasignadas']);
    }

    public function test_respeta_padron_del_anio_y_ultimo_mes_incluso_con_sep_mixto(): void
    {
        $this->personal('99000001-K', 40, ['mes' => 7, 'financiamiento' => 'SEP']);
        $this->personal('99000001-K', 30, ['mes' => 8]);
        $this->personal('99000001-K', 5, ['mes' => 8, 'financiamiento' => 'SEP']);
        $this->personal('99000001-K', 4, ['mes' => 8, 'financiamiento' => 'SEP / PIE']);
        $this->personal('99000001-K', 22, ['anio' => 2027, 'mes' => 3, 'financiamiento' => 'SEP']);
        $service = app(DotacionInformeFunciones::class);
        $vigente = $service->fila($this->ee, 2026);
        $proximo = $service->fila($this->ee, 2027);
        $this->assertSame(39.0, $vigente['contrato_total']);
        $this->assertSame(5.0, $vigente['sep']);
        $this->assertSame(4.0, $vigente['sep_sin_desglose']);
        $this->assertSame(22.0, $proximo['contrato_total']);
        $this->assertSame(22.0, $proximo['sep']);
        $this->assertSame(['03/2027'], $proximo['periodos']);
    }

    public function test_conversion_de_aula_es_consolidada_y_no_reescribe_las_asignaciones(): void
    {
        $this->personal('99000001-K', 44);
        foreach (['A', 'B'] as $name) {
            $this->asignacion(2027, '99000001-K', 'plan_estudio', $name, 17, ['horas_plan_pedagogicas' => 15, 'proporcion_aplicada' => '65/35']);
        }
        $fila = app(DotacionInformeFunciones::class)->fila($this->ee, 2027);
        $this->assertSame(35.0, $fila['grupos']['aula']['horas']);
        $this->assertSame(9.0, $fila['sin_asignar']);
        $this->assertEquals(34, DotacionDocenteAsignacion::sum('horas_contrato'));
    }

    public function test_respeta_situaciones_continuidad_y_retiros_del_anio_sin_alterar_el_anterior(): void
    {
        (require database_path('migrations/2026_08_24_090000_create_dotacion_docente_exclusiones_table.php'))->up();
        (require database_path('migrations/2026_09_14_160000_add_continuidad_to_dotacion_docente_exclusiones.php'))->up();
        Schema::create('dotacion_no_renovaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); $t->string('docente_rut_normalizado'); $t->timestamp('anulada_at')->nullable();
        });
        $this->personal('99000001-K', 44, ['financiamiento' => 'SEP']);
        $this->personal('99000002-K', 20);
        $this->personal('99000003-K', 30);
        DB::table('dotacion_docente_exclusiones')->insert([
            ['establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K',
                'docente_nombre' => 'Persona sintética', 'motivo' => 'horas_gremiales', 'horas' => 10, 'considerar_dotacion_siguiente' => true],
            ['establecimiento_id' => $this->ee->id, 'anio' => 2026, 'docente_rut' => '99000002-K', 'docente_rut_normalizado' => '99000002K',
                'docente_nombre' => 'Persona sintética', 'motivo' => 'proceso_bir', 'horas' => 0, 'considerar_dotacion_siguiente' => false],
        ]);
        DB::table('dotacion_no_renovaciones')->insert(['establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut_normalizado' => '99000003K']);
        $this->asignacion(2027, '99000001-K', 'plan_estudio', 'Aula', 20);
        $this->asignacion(2027, '99000001-K', 'reserva_no_normativa', 'Reservadas', 5);
        $this->asignacion(2027, '99000001-K', 'plan_estudio', 'Inactiva', 99, ['estado' => 'inactiva']);
        $this->asignacion(2027, '99000002-K', 'plan_estudio', 'No continúa', 99);
        $service = app(DotacionInformeFunciones::class);
        $actual = $service->fila($this->ee, 2026);
        $proyeccion = $service->fila($this->ee, 2027);
        $this->assertSame(3, $actual['docentes_total']);
        $this->assertSame(94.0, $actual['contrato_total']);
        $this->assertSame(1, $proyeccion['docentes_total']);
        $this->assertSame(44.0, $proyeccion['contrato_original']);
        $this->assertSame(10.0, $proyeccion['horas_excluidas']);
        $this->assertSame(44.0, $proyeccion['contrato_total']);
        $this->assertSame(34.0, $proyeccion['contrato_considerado']);
        $this->assertSame(20.0, $proyeccion['asignadas']);
        $this->assertSame(5.0, $proyeccion['reservadas']);
        $this->assertSame(9.0, $proyeccion['sin_asignar']);
        $this->assertEquals($proyeccion['contrato_total'], $proyeccion['asignadas'] + $proyeccion['reservadas'] + $proyeccion['sin_asignar'] + $proyeccion['horas_excluidas'] - $proyeccion['sobreasignadas']);
        $this->assertSame(44.0, $proyeccion['sep']); // Fuente contractual complementaria, no otra asignación.
    }

    public function test_nombres_de_establecimiento_se_escapan_y_cada_fila_tiene_trece_columnas(): void
    {
        $this->ee->update(['nombre_establecimiento' => '<script>prueba</script>']);
        $response = $this->get($this->url(2027))->assertOk()->assertSee('&lt;script&gt;prueba&lt;/script&gt;', false)->assertDontSee('<script>prueba</script>', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(13, $xpath->query('//tr[@data-informe-rbd]/*')->length);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(1, $xpath->query('//*[@class="table-responsive" and @tabindex="0"]')->length);
        foreach ($xpath->query('//form//input[not(@type="hidden")] | //form//select') as $input) {
            $this->assertSame(1, $xpath->query('//label[@for="'.$input->getAttribute('id').'"]')->length);
        }
    }

    public function test_redondeo_parvularia_completa_jornada_sin_generar_saldo_ficticio(): void
    {
        $docente = ['rut' => '99000001-K', 'titulo' => 'Pedagogía en Educación de Párvulos',
            'horas_contrato' => 43, 'horas_planta' => 43, 'horas_asignadas_total' => 42.63,
            'asignaciones' => [['tipo_asignacion' => 'plan_estudio', 'proporcion_aplicada' => 'Parvularia', 'horas_contrato' => 42.63]]];
        $fila = app(DotacionInformeFunciones::class)->resumir(collect([$docente]));
        $this->assertSame(43.0, $fila['grupos']['aula']['horas']);
        $this->assertSame(0.37, $fila['redondeo']);
        $this->assertSame(0.0, $fila['sin_asignar']);
    }

    public function test_filtra_y_pagina_sin_calcular_establecimientos_fuera_de_pagina(): void
    {
        for ($i = 1; $i <= 12; $i++) { $this->establecimiento(99999 - $i); }
        $this->establecimiento(99980, ['sala_cuna' => true]);
        $called = [];
        $service = $this->mock(DotacionInformeFunciones::class);
        $service->shouldReceive('fila')->times(10)->andReturnUsing(function ($ee, $year) use (&$called) {
            $called[] = $ee->rbd;
            return (new DotacionInformeFunciones)->resumir(collect()) + ['error' => false, 'establecimiento_id' => $ee->id,
                'rbd' => (string) $ee->rbd, 'nombre' => $ee->nombre_establecimiento, 'comuna' => $ee->comuna, 'sep' => 0, 'sep_sin_desglose' => 0];
        });
        $this->get($this->url(2027))->assertOk()->assertViewHas('establecimientos', fn ($p) => $p->total() === 13 && $p->count() === 10);
        $this->assertCount(10, $called);
        $this->assertNotContains(99980, $called);
    }

    public function test_filtros_se_conservan_y_el_estado_vacio_no_inventa_filas(): void
    {
        $this->get($this->url(2026, ['q' => '99999', 'comuna' => 'Comuna sintética']))
            ->assertOk()->assertViewHas('establecimientos', fn ($p) => $p->total() === 1)->assertSee('value="99999"', false);
        $this->get($this->url(2027, ['q' => 'No existe']))->assertOk()->assertSee('No se encontraron establecimientos.')
            ->assertViewHas('establecimientos', fn ($p) => $p->isEmpty());
    }

    public function test_fallo_del_calculo_se_identifica_y_los_totales_se_marcan_parciales(): void
    {
        $this->mock(DotacionInformeFunciones::class)->shouldReceive('fila')->once()->andThrow(new \RuntimeException('Fallo sintético'));
        $this->get($this->url(2027))->assertOk()->assertSee('Cálculo no disponible')->assertSee('Total de esta página (parcial)')
            ->assertViewHas('errores', 1)->assertViewHas('totales', fn ($t) => $t['contrato_total'] === 0.0);
    }

    public function test_rechaza_anios_y_filtros_invalidos(): void
    {
        foreach ([['anio' => 2028], ['anio' => 'incorrecto'], ['anio' => [2027]], ['anio' => 2027, 'q' => ['no']], ['anio' => 2027, 'comuna' => str_repeat('a', 101)]] as $filters) {
            $this->from('/informe')->get(route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1] + $filters))
                ->assertRedirect('/informe')->assertSessionHasErrors();
        }
    }

    private function url(int $anio, array $filters = []): string
    {
        return route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1, 'anio' => $anio] + $filters);
    }

    private function usarRol(string $rol): void
    {
        $id = DB::table('roles')->where('name', $rol)->value('id') ?? DB::table('roles')->insertGetId(['name' => $rol, 'guard_name' => 'web']);
        $user = auth()->user();
        if (! DB::table('model_has_roles')->where('model_id', $user->id)->where('role_id', $id)->exists()) {
            DB::table('model_has_roles')->insert(['role_id' => $id, 'model_type' => $user::class, 'model_id' => $user->id]);
        }
        $user->forceFill(['establecimiento_id' => $this->ee->id])->save();
        $user->unsetRelation('roles');
        $this->withSession(['active_role' => $rol]);
    }

    private function establecimiento(int $rbd, array $extra = []): Establecimiento
    {
        return Establecimiento::create($extra + ['cod_estab' => $rbd, 'rbd' => $rbd, 'nombre_establecimiento' => 'Escuela sintética '.$rbd, 'comuna' => 'Comuna sintética']);
    }

    private function personal(string $rut, int $hours, array $extra = []): void
    {
        DB::table('reemplazos_personal')->insert($extra + [
            'establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd, 'rut' => $rut, 'nombre' => 'Docente sintético',
            'anio' => 2026, 'mes' => 8, 'jornada' => $hours, 'jornada_basica' => $hours, 'tipocontrato' => 'PLANTA',
            'financiamiento' => 'SUB.GENERAL', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => bin2hex(random_bytes(20)),
        ]);
    }

    private function asignacion(int $year, string $rut, string $tipo, string $name, float $hours, array $extra = []): void
    {
        DotacionDocenteAsignacion::create($extra + ['establecimiento_id' => $this->ee->id, 'anio' => $year, 'docente_rut' => $rut,
            'docente_rut_normalizado' => DotacionEstablecimientoCalculator::normalizeRut($rut), 'tipo_asignacion' => $tipo,
            'asignatura_nombre' => $name, 'horas_contrato' => $hours, 'estamento_cobertura' => 'docente']);
    }

    private function resetCalculators(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) { (new ReflectionProperty($class, $name))->setValue(null, []); }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
    }
}
