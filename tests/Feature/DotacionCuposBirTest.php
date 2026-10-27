<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Dotacion\CuposBirService;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionContratoVigentePorBloque;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionCuposBirTest extends IsolatedSecurityTestCase
{
    protected Establecimiento $ee;
    private CuposBirService $service;
    private int $situacion;

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
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_08_24_090000_create_dotacion_docente_exclusiones_table.php',
            '2026_09_14_160000_add_continuidad_to_dotacion_docente_exclusiones.php',
            '2026_09_14_170000_add_conservar_horas_to_dotacion_docente_exclusiones.php',
            '2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php',
            '2026_09_29_130000_create_dotacion_docente_subsectores_table.php',
            '2026_10_08_170000_add_horas_traspaso_bir_to_dotacion_docente_exclusiones.php',
            '2026_10_13_200000_add_dotacion_no_renovaciones.php',
            '2026_10_27_150000_add_origen_bir_to_dotacion_contrata_habilitaciones.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('declaracion_sostenedores', function (Blueprint $table): void {
            $table->id();
            foreach (['rbd', 'rut', 'nombres', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $campo) $table->string($campo);
        });
        Schema::create('modules', function (Blueprint $table): void { $table->id(); $table->string('key'); });
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'Escuela sintética BIR']);
        DB::table('reemplazos_personal')->insert([
            'establecimiento_id' => $this->ee->id, 'rbd' => 99999, 'rut' => '99000001-K',
            'nombre' => 'Docente sintético BIR', 'anio' => 2026, 'mes' => 10, 'jornada' => 44,
            'tipocontrato' => 'PLANTA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'synthetic-bir',
        ]);
        DB::table('declaracion_sostenedores')->insert([
            'rbd' => '99999', 'rut' => '99000001-K', 'nombres' => 'Docente sintético BIR',
            'nombre_titulo' => 'Pedagogía en Educación Básica', 'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE',
        ]);
        $this->situacion = DB::table('dotacion_docente_exclusiones')->insertGetId([
            'establecimiento_id' => $this->ee->id, 'anio' => 2026, 'docente_rut' => '99000001-K',
            'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente sintético BIR',
            'motivo' => 'proceso_bir', 'horas' => 0, 'considerar_dotacion_siguiente' => false,
            'conservar_horas_necesarias' => true, 'horas_traspaso_bir' => 19.37,
        ]);
        $this->service = app(CuposBirService::class);
        $this->withoutVite();
        Storage::disk('local')->put('bir-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">@stack(\'styles\')</head><body><main class="p-3">@yield(\'content\')</main>@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('bir-views'));
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public static function titulos(): array
    {
        return [
            ['Pedagogía en Educación Básica', false, 'plan_estudio'],
            ['Pedagogía en Educación de Párvulos', false, 'parvularia'],
            ['Educación Diferencial', false, 'pie'],
            ['Educación Diferencial', true, 'plan_estudio'],
        ];
    }

    #[DataProvider('titulos')]
    public function test_crea_por_titulo_y_bloque_suma_solo_en_destino_y_conserva_origen(string $titulo, bool $especial, string $bloque): void
    {
        DB::table('declaracion_sostenedores')->update(['nombre_titulo' => $titulo]);
        $this->ee->forceFill(['especial' => $especial])->save();
        $padron = DB::table('reemplazos_personal')->get()->toJson();
        $situaciones = DB::table('dotacion_docente_exclusiones')->get()->toJson();
        $item = $this->service->resumen($this->ee, 2027)->sole();
        $this->assertNull($item['motivo']);
        $this->assertSame($bloque, $item['bloque']);
        $this->assertSame(19.37, $item['horas']);
        $this->assertSame(0.0, (float) DotacionEstablecimientoCalculator::build($this->ee, 2027)['resumen']['horas_contrato_docentes']);
        $this->assertSame(1, $this->generar());
        $this->assertSame(0, $this->generar());
        $cupo = DB::table('dotacion_contrata_habilitaciones')->sole();
        $this->assertSame($titulo, $cupo->titulo);
        $this->assertSame('DOCENTE', $cupo->funcion);
        $this->assertSame($this->situacion, (int) $cupo->bir_situacion_id);
        $this->assertSame('99000001K', $cupo->bir_docente_rut_normalizado);
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $this->assertSame(19.37, (float) $data['resumen']['horas_contrato_docentes']);
        $this->assertSame(19.37, $data['resumen']['horas_contrato_docentes_por_contratar']);
        $this->assertSame(19.37, (float) DotacionEstablecimientoCalculator::resumenListado($this->ee, 2027)['resumen']['horas_contrato_docentes']);
        $referencia = (new DotacionContratoVigentePorBloque)->paraEstablecimiento($this->ee, 2027);
        foreach (['plan_estudio' => 1, 'parvularia' => 2, 'pie' => 3] as $key => $numero) {
            $this->assertSame($key === $bloque ? 19.37 : 0.0, $referencia['contrato_vigente_bloque_'.$numero]);
        }
        $this->assertSame(44.0, (float) DotacionEstablecimientoCalculator::build($this->ee, 2026)['resumen']['horas_contrato_docentes']);
        $this->assertSame($padron, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertSame($situaciones, DB::table('dotacion_docente_exclusiones')->get()->toJson());
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 0);
        $this->assertDatabaseCount('dotacion_docente_subsectores', 0);
        $virtual = app(ContratacionHabilitacionService::class)->docentesVirtuales($this->ee, 2027)->sole();
        $this->assertSame($titulo, $virtual['titulo']);
        $this->assertSame(19.37, $virtual['horas_contrato']);
    }

    public function test_diferencial_con_funcion_normativa_distribuye_sin_duplicar_horas(): void
    {
        DB::table('declaracion_sostenedores')->update(['nombre_titulo' => 'Educación Diferencial']);
        DB::table('dotacion_docente_asignaciones')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2026, 'docente_rut' => '99000001-K',
            'docente_rut_normalizado' => '99000001K', 'tipo_asignacion' => 'funcion_directiva',
            'horas_contrato' => 5, 'estado' => 'activa',
        ]);
        $items = $this->service->resumen($this->ee, 2027)->keyBy('bloque');
        $this->assertSame(5.0, $items['plan_estudio']['horas']);
        $this->assertSame(14.37, $items['pie']['horas']);
        $this->assertSame(2, $this->generar());
        $this->assertSame(19.37, round((float) DB::table('dotacion_contrata_habilitaciones')->sum('horas'), 2));
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 1);
        $this->assertDatabaseMissing('dotacion_docente_asignaciones', ['anio' => 2027]);
    }

    #[DataProvider('titulos')]
    public function test_permite_crear_14_de_34_horas_y_suma_solo_la_seleccion_al_bloque(string $titulo, bool $especial, string $bloque): void
    {
        DB::table('declaracion_sostenedores')->update(['nombre_titulo' => $titulo]);
        DB::table('dotacion_docente_exclusiones')->update(['horas_traspaso_bir' => 34]);
        $this->ee->forceFill(['especial' => $especial])->save();
        $origen = DB::table('dotacion_docente_exclusiones')->get()->toJson();
        $item = $this->service->resumen($this->ee, 2027)->sole();
        $this->assertSame(34.0, $item['horas']);
        $this->assertSame(1, $this->generar([$item['clave'] => 14]));
        $this->assertSame(0, $this->generar([$item['clave'] => 14]));
        $revision = $this->service->resumen($this->ee, 2027)->sole();
        $this->assertNull($revision['motivo']);
        $this->assertSame(14.0, (float) $revision['cupo']->horas);
        $this->assertDatabaseHas('dotacion_contrata_habilitaciones', ['horas' => 14, 'bloque' => $bloque, 'titulo' => $titulo]);
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $this->assertSame(14.0, $data['resumen']['horas_contrato_docentes']);
        $this->assertSame(14.0, $data['resumen']['horas_contrato_por_contratar_por_bloque'][$bloque]);
        $this->assertSame(44.0, DotacionEstablecimientoCalculator::build($this->ee, 2026)['resumen']['horas_contrato_docentes']);
        $this->assertSame($origen, DB::table('dotacion_docente_exclusiones')->get()->toJson());
        $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 1);
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 0);
        $this->assertDatabaseCount('dotacion_docente_subsectores', 0);
    }

    public static function cantidadesInvalidas(): array
    {
        return [[19.38], [44], [0], [-1], ['abc'], ['14,5'], ['1.234'], [null], [['horas' => 14]], ['1e1']];
    }

    #[DataProvider('cantidadesInvalidas')]
    public function test_servidor_rechaza_horas_invalidas_o_sobre_el_maximo_sin_crear_cupos(mixed $cantidad): void
    {
        $this->ingresar('admin');
        $item = $this->service->resumen($this->ee, 2027)->sole();
        $payload = ['anio' => 2027, 'cupos' => [$item['clave']], 'huellas' => [$item['clave'] => $item['huella']], 'horas' => [$item['clave'] => $cantidad]];
        $url = route('admin.dotacion-establecimiento.bir.index', [$this->ee, 'anio' => 2027]);
        $this->from($url)->post(route('admin.dotacion-establecimiento.bir.store', $this->ee), $payload)
            ->assertRedirect($url)->assertSessionHasErrors('horas.'.$item['clave']);
        $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 0);
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['id' => $this->situacion, 'horas_traspaso_bir' => 19.37]);
        $this->get($url)->assertOk()->assertSee('Revise la selección.');
    }

    public function test_valida_solo_cupos_seleccionados_y_exige_su_cantidad(): void
    {
        $item = $this->service->resumen($this->ee, 2027)->sole();
        try { $this->generar([]); $this->fail('Faltan las horas seleccionadas.'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('horas.'.$item['clave'], $error->errors()); }
        $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 0);
        $this->assertSame(1, $this->generar([$item['clave'] => 14, '999:pie' => 'ignorar no seleccionado']));
        $this->assertSame(14.0, (float) DB::table('dotacion_contrata_habilitaciones')->sole()->horas);
    }

    public function test_cupo_parcial_reenviado_no_se_amplia_y_cambio_de_maximo_se_revalida(): void
    {
        $item = $this->service->resumen($this->ee, 2027)->sole();
        $this->generar([$item['clave'] => 14]);
        try { $this->generar([$item['clave'] => 15]); $this->fail('Un reenvío no modifica el cupo existente.'); }
        catch (ValidationException) { $this->assertSame(14.0, (float) DB::table('dotacion_contrata_habilitaciones')->sole()->horas); }
        DB::table('dotacion_docente_exclusiones')->update(['horas_traspaso_bir' => 10]);
        $this->assertNotNull($this->service->resumen($this->ee, 2027)->sole()['motivo']);
        try {
            $this->service->generar($this->ee, 2027, [$item['clave']], [$item['clave'] => $item['huella']], null, [$item['clave'] => 10]);
            $this->fail('La revisión anterior ya no es válida.');
        } catch (ValidationException) { $this->assertDatabaseHas('dotacion_contrata_habilitaciones', ['horas' => 14]); }
    }

    public function test_horas_parciales_por_bloque_no_consumen_el_maximo_completo_y_no_se_traspasan_entre_bloques(): void
    {
        DB::table('declaracion_sostenedores')->update(['nombre_titulo' => 'Educación Diferencial']);
        DB::table('dotacion_docente_asignaciones')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2026, 'docente_rut' => '99000001-K',
            'docente_rut_normalizado' => '99000001K', 'tipo_asignacion' => 'funcion_directiva', 'horas_contrato' => 5, 'estado' => 'activa',
        ]);
        $items = $this->service->resumen($this->ee, 2027)->keyBy('bloque');
        $general = $items['plan_estudio']['clave']; $pie = $items['pie']['clave'];
        try { $this->generar([$general => 5.01, $pie => 10]); $this->fail('No se trasladan horas del máximo PIE al general.'); }
        catch (ValidationException) { $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 0); }
        $this->assertSame(2, $this->generar([$general => 4, $pie => 10.25]));
        $this->assertSame(14.25, (float) DB::table('dotacion_contrata_habilitaciones')->sum('horas'));
        $this->assertSame(0, $this->generar([$general => 4, $pie => 10.25]));
        $this->assertSame(14.25, DotacionEstablecimientoCalculator::build($this->ee, 2027)['resumen']['horas_contrato_docentes']);
    }

    public function test_formulario_precarga_maximo_conserva_errores_y_muestra_cupo_parcial_creado(): void
    {
        $this->ingresar('admin');
        DB::table('dotacion_docente_exclusiones')->update(['horas_traspaso_bir' => 34]);
        $item = $this->service->resumen($this->ee, 2027)->sole();
        $url = route('admin.dotacion-establecimiento.bir.index', [$this->ee, 'anio' => 2027]);
        $this->get($url)->assertOk()->assertSee('name="horas['.$item['clave'].']"', false)->assertSee('max="34"', false)->assertSee('value="34"', false);
        $payload = ['anio' => 2027, 'cupos' => [$item['clave']], 'huellas' => [$item['clave'] => $item['huella']], 'horas' => [$item['clave'] => 35]];
        $this->from($url)->post(route('admin.dotacion-establecimiento.bir.store', $this->ee), $payload)->assertSessionHasErrors('horas.'.$item['clave']);
        $this->get($url)->assertOk()->assertSee('value="35"', false)->assertSee('aria-invalid="true"', false)->assertSee('checked', false);
        $payload['horas'][$item['clave']] = 14;
        $this->post(route('admin.dotacion-establecimiento.bir.store', $this->ee), $payload)->assertRedirect($url)->assertSessionHasNoErrors();
        $this->get($url)->assertOk()->assertSee('generado · 14 h')->assertDontSee('name="horas[', false);
        $this->assertDatabaseHas('dotacion_contrata_habilitaciones', ['horas' => 14]);
    }

    public static function situacionesNoElegibles(): array
    {
        return [
            [['considerar_dotacion_siguiente' => true]],
            [['conservar_horas_necesarias' => false]],
            [['horas_traspaso_bir' => 0]],
        ];
    }

    #[DataProvider('situacionesNoElegibles')]
    public function test_rechaza_situacion_sin_vacante_conservada(array $cambios): void
    {
        DB::table('dotacion_docente_exclusiones')->where('id', $this->situacion)->update($cambios);
        $this->assertNotNull($this->service->resumen($this->ee, 2027)->sole()['motivo']);
        $this->expectException(ValidationException::class);
        $this->generar();
    }

    public function test_no_crea_si_el_docente_esta_en_el_padron_del_destino(): void
    {
        DB::table('reemplazos_personal')->update(['anio' => 2027]);
        $this->assertStringContainsString('No hay contrato', $this->service->resumen($this->ee, 2027)->sole()['motivo']);
        // Conserva el origen y agrega contrato efectivo en el destino.
        $fila = (array) DB::table('reemplazos_personal')->sole();
        unset($fila['id']);
        DB::table('reemplazos_personal')->insert([...$fila, 'anio' => 2026, 'row_hash' => 'synthetic-source']);
        $this->assertStringContainsString('nómina del año destino', $this->service->resumen($this->ee, 2027)->sole()['motivo']);
        $this->expectException(ValidationException::class);
        $this->generar();
    }

    public function test_historico_nulo_conserva_contrato_completo_y_horas_expresas_se_limitan_al_contrato(): void
    {
        DB::table('dotacion_docente_exclusiones')->update(['horas_traspaso_bir' => null]);
        $item = $this->service->resumen($this->ee, 2027)->sole();
        $this->assertTrue($item['historico']);
        $this->assertSame(44.0, $item['horas']);
        DB::table('dotacion_docente_exclusiones')->update(['horas_traspaso_bir' => 40]);
        DB::table('reemplazos_personal')->update(['jornada' => 20]);
        $this->assertSame(1, $this->generar());
        $this->assertSame(20.0, (float) DB::table('dotacion_contrata_habilitaciones')->sum('horas'));
    }

    public function test_reenvio_antiguo_y_cambios_del_origen_no_crean_cupos(): void
    {
        $item = $this->service->resumen($this->ee, 2027)->sole();
        DB::table('dotacion_docente_exclusiones')->update(['horas_traspaso_bir' => 20]);
        try {
            $this->service->generar($this->ee, 2027, [$item['clave']], [$item['clave'] => $item['huella']], null);
            $this->fail('Debe rechazar la revisión obsoleta.');
        } catch (ValidationException) { $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 0); }
        $this->generar();
        DB::table('declaracion_sostenedores')->update(['nombre_titulo' => 'Pedagogía en Educación de Párvulos']);
        $nuevo = $this->service->resumen($this->ee, 2027)->sole();
        $this->assertSame('parvularia', $nuevo['bloque']);
        $this->assertNotNull($nuevo['motivo']);
        try { $this->generar(); $this->fail('No se duplica tras cambiar el bloque.'); }
        catch (ValidationException) { $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 1); }
        $this->assertDatabaseHas('dotacion_contrata_habilitaciones', ['bloque' => 'plan_estudio', 'horas' => 20]);
    }

    public function test_eliminar_y_recrear_situacion_no_duplica_persona_y_el_indice_protege_escrituras_competidoras(): void
    {
        $this->generar();
        $fila = (array) DB::table('dotacion_docente_exclusiones')->sole();
        DB::table('dotacion_docente_exclusiones')->delete();
        unset($fila['id']);
        $otraSituacion = DB::table('dotacion_docente_exclusiones')->insertGetId($fila);
        $this->assertNotSame($this->situacion, $otraSituacion);
        $this->assertSame(0, $this->generar());
        $cupo = (array) DB::table('dotacion_contrata_habilitaciones')->sole();
        unset($cupo['id']);
        $cupo['bir_situacion_id'] = $otraSituacion;
        try { DB::table('dotacion_contrata_habilitaciones')->insert($cupo); $this->fail('El índice único debe impedir duplicados.'); }
        catch (QueryException) { $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 1); }
    }

    public function test_transaccion_revierte_lote_completo_si_falla_la_escritura(): void
    {
        // Dos lotes: falla el segundo después de escribir los primeros 100 cupos.
        $persona = (array) DB::table('reemplazos_personal')->sole();
        $declaracion = (array) DB::table('declaracion_sostenedores')->sole();
        $situacion = (array) DB::table('dotacion_docente_exclusiones')->sole();
        unset($persona['id'], $declaracion['id'], $situacion['id']);
        for ($n = 2; $n <= 101; $n++) {
            $rut = '99'.str_pad((string) $n, 6, '0', STR_PAD_LEFT).'K';
            DB::table('reemplazos_personal')->insert([...$persona, 'rut' => $rut, 'row_hash' => 'synthetic-batch-'.$n]);
            DB::table('declaracion_sostenedores')->insert([...$declaracion, 'rut' => $rut]);
            DB::table('dotacion_docente_exclusiones')->insert([...$situacion, 'docente_rut' => $rut, 'docente_rut_normalizado' => $rut]);
        }
        DB::statement("CREATE TRIGGER fallo_bir BEFORE INSERT ON dotacion_contrata_habilitaciones WHEN NEW.bir_docente_rut_normalizado = '99000101K' BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END");
        $antes = DB::table('dotacion_docente_exclusiones')->get()->toJson();
        try { $this->generar(); $this->fail('Debe fallar la escritura.'); }
        catch (QueryException) { $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 0); }
        $this->assertSame($antes, DB::table('dotacion_docente_exclusiones')->get()->toJson());
    }

    public function test_aislamiento_de_establecimiento_ano_y_seleccion(): void
    {
        $otro = Establecimiento::create(['rbd' => 99998, 'cod_estab' => 'OTHER', 'nombre_establecimiento' => 'Otro EE sintético']);
        $this->assertEmpty($this->service->resumen($otro, 2027));
        $this->assertEmpty($this->service->resumen($this->ee, 2028));
        $item = $this->service->resumen($this->ee, 2027)->sole();
        $this->expectException(ValidationException::class);
        $this->service->generar($otro, 2027, [$item['clave']], [$item['clave'] => $item['huella']], null);
    }

    public function test_retirar_cupo_vacio_permite_regenerar_sin_modificar_bir(): void
    {
        $this->generar();
        $cupo = DB::table('dotacion_contrata_habilitaciones')->sole();
        $this->assertTrue(app(ContratacionHabilitacionService::class)->revocar($this->ee, 2027, $cupo->id));
        $this->assertSame(1, $this->generar());
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['id' => $this->situacion, 'horas_traspaso_bir' => 19.37]);
    }

    public function test_migracion_reintentable_conserva_cupos_generales_y_cupo_asignado_no_se_retira(): void
    {
        $this->generar();
        $cupo = DB::table('dotacion_contrata_habilitaciones')->sole();
        $generales = app(ContratacionHabilitacionService::class);
        $generales->habilitar($this->ee, 2027, 'plan_estudio', 2, 5, null);
        (require database_path('migrations/2026_10_27_150000_add_origen_bir_to_dotacion_contrata_habilitaciones.php'))->up();
        $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 3);
        $this->assertSame(2, DB::table('dotacion_contrata_habilitaciones')->whereNull('bir_docente_rut_normalizado')->count());
        DB::table('dotacion_docente_asignaciones')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut' => 'VACANTE-'.$cupo->id,
            'docente_rut_normalizado' => DotacionEstablecimientoCalculator::normalizeRut('VACANTE-'.$cupo->id),
            'tipo_asignacion' => 'plan_estudio', 'horas_contrato' => 1, 'estado' => 'activa',
        ]);
        try { $generales->revocar($this->ee, 2027, $cupo->id); $this->fail('Debe preservar el cupo asignado.'); }
        catch (ValidationException) { $this->assertDatabaseHas('dotacion_contrata_habilitaciones', ['id' => $cupo->id]); }
        $this->assertSame(29.37, (float) DotacionEstablecimientoCalculator::build($this->ee, 2027)['resumen']['horas_contrato_docentes']);
    }

    public function test_formulario_se_integra_con_coordinacion_del_padron_y_no_es_accesible_sin_sesion(): void
    {
        $this->get(route('admin.dotacion-establecimiento.bir.index', [$this->ee, 'anio' => 2027]))->assertRedirect(route('login'));
        Schema::create('padron_aplicacion_control', function (Blueprint $table): void { $table->id(); });
        DB::table('padron_aplicacion_control')->insert(['id' => 1]);
        $this->ingresar('admin');
        $item = $this->service->resumen($this->ee, 2027)->sole();
        $this->post(route('admin.dotacion-establecimiento.bir.store', $this->ee), [
            'anio' => 2027, 'cupos' => [$item['clave']], 'huellas' => [$item['clave'] => $item['huella']],
            'horas' => [$item['clave'] => $item['horas']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 1);
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function roles(): array
    {
        return [['admin'], ['coordinador_uatp'], ['coordinador_gdp'], ['supervisor_plani']];
    }

    #[DataProvider('roles')]
    public function test_roles_autorizados_revisan_filtran_y_crean_por_http(string $rol): void
    {
        $this->ingresar($rol);
        $url = route('admin.dotacion-establecimiento.bir.index', [$this->ee, 'anio' => 2027]);
        $this->get($url)->assertOk()->assertSee('Crear docentes por contratar desde BIR')->assertSee('19,37 h')->assertSee('Generar cupo');
        $this->get($url.'&q=basica')->assertOk()->assertSee('Generar cupo');
        $this->get($url.'&bloque=pie')->assertOk()->assertSee('No hay situaciones BIR');
        $item = $this->service->resumen($this->ee, 2027)->sole();
        $payload = ['anio' => 2027, 'cupos' => [$item['clave']], 'huellas' => [$item['clave'] => $item['huella']], 'horas' => [$item['clave'] => $item['horas']]];
        $post = route('admin.dotacion-establecimiento.bir.store', $this->ee);
        $this->post($post, $payload)->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post($post, $payload)->assertRedirect($url)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 1);
        $this->get($url)->assertOk()->assertSee('generado')->assertDontSee('name="cupos[]"', false);
    }

    public function test_rol_activo_no_autorizado_y_csrf_impiden_generar(): void
    {
        $this->ingresar('funcionario_directivo_estab');
        DB::table('model_has_roles')->insert(['model_id' => auth()->id(), 'model_type' => \App\Models\User::class, 'role_id' => 3]);
        auth()->user()->unsetRelation('roles');
        $this->get(route('admin.dotacion-establecimiento.bir.index', [$this->ee, 'anio' => 2027]))->assertForbidden();
        $this->post(route('admin.dotacion-establecimiento.bir.store', $this->ee), ['anio' => 2027])->assertForbidden();
        $this->assertDatabaseCount('dotacion_contrata_habilitaciones', 0);
        $this->app['env'] = 'local'; // Sólo después del bootstrap aislado: activa CSRF real.
        $this->post(route('admin.dotacion-establecimiento.bir.store', $this->ee), ['anio' => 2027])->assertStatus(419);
    }

    public function test_validacion_y_compatibilidad_sin_migracion(): void
    {
        $this->ingresar('admin');
        $post = route('admin.dotacion-establecimiento.bir.store', $this->ee);
        $this->post($post, ['anio' => 2026, 'cupos' => []])->assertRedirect()->assertSessionHasErrors(['anio', 'cupos', 'huellas']);
        Schema::table('dotacion_contrata_habilitaciones', function (Blueprint $table): void {
            $table->dropUnique('dot_cupo_bir_uk'); $table->dropUnique('dot_cupo_bir_persona_uk');
            $table->dropColumn(['bir_situacion_id', 'bir_docente_rut_normalizado']);
        });
        $this->get(route('admin.dotacion-establecimiento.bir.index', [$this->ee, 'anio' => 2027]))->assertOk()->assertSee('Preparación pendiente');
        $this->expectException(ValidationException::class);
        $this->service->generar($this->ee, 2027, ['1:pie'], [], null);
    }

    private function generar(?array $horas = null): int
    {
        $items = $this->service->resumen($this->ee, 2027);
        return $this->service->generar($this->ee, 2027, $items->pluck('clave')->all(), $items->pluck('huella', 'clave')->all(), null, $horas);
    }

    protected function ingresar(string $rol): void
    {
        $id = DB::table('roles')->where('name', $rol)->value('id') ?? DB::table('roles')->insertGetId(['name' => $rol, 'guard_name' => 'web']);
        $this->actingAs($this->testUser(1, $id))->withSession(['active_role' => $rol]);
    }

    private function resetCalculators(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) (new ReflectionProperty($class, $property))->setValue(null, []);
        }
    }
}
