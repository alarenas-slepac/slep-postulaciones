<?php

namespace Tests\Feature;

use App\Models\JornadaVotacion;
use App\Models\VotanteConsulta;
use App\Services\Votaciones\BitacoraVotacionService;
use App\Services\Votaciones\PadronConsultaExcel;
use App\Services\Votaciones\PadronConsultaService;
use App\Support\RutChile;
use App\Support\Votaciones\RutConsulta;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\IsolatedSecurityTestCase;

class ConsultaLocalVotacionTest extends IsolatedSecurityTestCase
{
    private JornadaVotacion $jornada;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        app()->setLocale('es');
        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id(); $t->unsignedInteger('rbd')->unique(); $t->string('nombre_establecimiento');
            $t->string('comuna')->nullable(); $t->decimal('latitud', 10, 7)->nullable();
            $t->decimal('longitud', 10, 7)->nullable(); $t->string('director_contacto')->nullable();
        });
        Schema::create('admision_establecimientos', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('establecimiento_id')->unique(); $t->string('direccion_publica', 500)->nullable();
        });
        Schema::create('modules', function (Blueprint $t): void { $t->id(); $t->string('key'); });
        DB::table('modules')->insert(['key' => 'votaciones']);
        foreach (['model_has_permissions', 'role_has_permissions'] as $table) {
            Schema::create($table, function (Blueprint $t): void {
                $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id')->nullable();
                $t->unsignedBigInteger('model_id')->nullable(); $t->string('model_type')->nullable();
            });
        }
        (require database_path('migrations/2026_08_27_210000_create_votaciones_module_tables.php'))->up();
        (require database_path('migrations/2026_10_21_210000_create_consulta_local_votacion.php'))->up();
        $this->jornada = JornadaVotacion::create(['nombre' => 'Jornada de prueba', 'slug' => 'jornada-prueba', 'fecha' => '2026-10-28']);
        $this->jornada->procesos()->attach(DB::table('procesos_votacion')->pluck('id'));
        Storage::disk('local')->put('test-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head>@stack(\'styles\')</head><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('test-views'));
    }

    public function test_importa_las_dos_hojas_sin_exponer_ni_publicar_automaticamente(): void
    {
        $analysis = $this->analizar();
        $this->assertSame(0, $analysis['resumen']['errores']);
        $this->assertSame('2026-10-28', reset($analysis['locales'])['dia']);
        $this->assertSame('09:00:00', reset($analysis['locales'])['hora_inicio']);
        $this->cargar($analysis);
        $this->assertSame(1, $this->jornada->padronConsulta()->count());
        $this->assertFalse($this->jornada->refresh()->consulta_publica);
        $this->get(route('public.consulta-votacion.index'))->assertOk()->assertDontSee('Funcionaria de prueba');
        $metadata = json_decode(DB::table('bitacora_votacion')->value('metadata'), true);
        $this->assertArrayNotHasKey('rut', $metadata);
        $this->assertStringNotContainsString($this->rut(), json_encode($metadata));
    }

    public function test_consulta_sin_login_por_rut_muestra_solo_las_asignaciones_de_la_persona(): void
    {
        $analysis = $this->analizar(voters: [$this->votante(), $this->votante(['RUT' => $this->rut(99000002), 'NOMBRE' => 'Otra persona de prueba'])]);
        $this->cargar($analysis);
        $this->publicar();
        $response = $this->post(route('public.consulta-votacion.consultar'), ['rut' => RutChile::format($this->rut()), 'jornada' => $this->jornada->slug]);
        $response->assertOk()->assertSee('Funcionaria de prueba')->assertSee('Local de prueba')->assertSee('28')->assertSee('09:00')->assertSee('Grupo: 1')->assertSee('RBD del local:')->assertDontSee('Otra persona de prueba');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertHeader('Referrer-Policy', 'no-referrer');
        $response->assertSessionMissing('_old_input');
        $this->assertStringNotContainsString('value="'.RutChile::format($this->rut()).'"', $response->getContent());
        $this->assertStringNotContainsString($this->rut(), $response->getContent());
    }

    public function test_get_no_consulta_rut_ni_entrega_el_padron_en_el_html(): void
    {
        $this->cargar(); $this->publicar();
        $this->get(route('public.consulta-votacion.index', ['rut' => $this->rut()]))->assertOk()->assertDontSee('Funcionaria de prueba')->assertDontSee('Local de prueba');
    }

    public function test_rechaza_rut_invalido_arrays_y_no_lo_guarda_en_sesion(): void
    {
        foreach (['99000001-0', ['rut' => $this->rut()], 'texto'.$this->rut()] as $rut) {
            $this->post(route('public.consulta-votacion.consultar'), ['rut' => $rut, 'jornada' => $this->jornada->slug])->assertStatus(422)->assertSessionMissing('_old_input')->assertDontSee('Funcionaria de prueba');
        }
    }

    public function test_rut_no_encontrado_tiene_mensaje_util_sin_datos_de_otras_personas(): void
    {
        $this->cargar(); $this->publicar();
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(99000003), 'jornada' => $this->jornada->slug])->assertOk()->assertSee('No encontramos una asignación')->assertDontSee('Funcionaria de prueba');
    }

    public function test_suspender_retirar_o_desactivar_procesos_oculta_la_consulta(): void
    {
        $this->cargar(); $this->publicar();
        foreach (['suspendida', 'retirada', 'procesos'] as $case) {
            $this->jornada->forceFill(['estado' => $case === 'suspendida' ? 'suspendida' : 'borrador', 'consulta_publica' => $case !== 'retirada'])->save();
            DB::table('procesos_votacion')->update(['activo' => $case !== 'procesos']);
            $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])->assertNotFound()->assertDontSee('Funcionaria de prueba');
        }
    }

    public function test_conserva_multiples_establecimientos_para_un_mismo_rut(): void
    {
        $analysis = $this->analizar(voters: [$this->votante(), $this->votante(['RBD' => 9002, 'ESTABLECIMIENTO' => 'Segundo establecimiento'])], locals: [$this->local(), $this->local(['RBD' => 9002, 'ESTABLECIMIENTO' => 'Segundo establecimiento', 'HORARIO' => '11:30'])]);
        $this->cargar($analysis); $this->publicar();
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])->assertOk()->assertSee('Segundo establecimiento')->assertSee('09:00')->assertSee('11:30');
    }

    public function test_mismo_local_puede_tener_horarios_distintos_segun_rbd_de_origen(): void
    {
        $analysis = $this->analizar(locals: [$this->local(), $this->local(['RBD' => 9002, 'HORARIO' => '12:30'])]);
        $this->assertSame(0, $analysis['resumen']['errores']);
        $this->assertCount(2, $analysis['locales']);
    }

    public function test_horario_contradictorio_y_votante_sin_coincidencia_bloquean_toda_la_carga(): void
    {
        $this->cargar();
        $before = DB::table('votacion_padron_consulta')->get()->toJson();
        $analysis = $this->analizar(voters: [$this->votante(['RBD' => 9999])], locals: [$this->local(), $this->local(['HORARIO' => '14:00'])]);
        $this->assertSame(2, $analysis['resumen']['errores']);
        $this->assertStringContainsString('filas 2 y 3', $analysis['errores'][0]);
        try {
            $this->cargar($analysis, true); $this->fail('Debió rechazar el archivo.');
        } catch (ValidationException) {
            $this->assertSame($before, DB::table('votacion_padron_consulta')->get()->toJson());
        }
    }

    public function test_omite_duplicados_exactos_y_admite_grupo_vacio_sin_inventarlo(): void
    {
        $analysis = $this->analizar(voters: [$this->votante(['GRUPO' => null]), $this->votante(['GRUPO' => null])], locals: [$this->local(), $this->local()]);
        $this->assertSame(1, $analysis['resumen']['duplicados_padron']);
        $this->assertSame(1, $analysis['resumen']['duplicados_locales']);
        $this->assertSame(1, $analysis['resumen']['sin_grupo']);
        $this->cargar($analysis); $this->publicar();
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])->assertOk()->assertSee('Grupo: Por confirmar');
    }

    public function test_normaliza_encabezados_tildes_espacios_cero_y_orden_de_columnas(): void
    {
        $headers = array_reverse(PadronConsultaExcel::PADRON);
        $headers[array_search('TIPO_DE_CONTRATO', $headers)] = 'TIPO_DE_CONTRAT0';
        $headers = array_map(fn ($h) => ' '.str_replace('_', ' ', $h).' ', $headers);
        $book = $this->libro(headers: $headers);
        $analysis = app(PadronConsultaExcel::class)->analizar($book);
        $this->assertSame(0, $analysis['resumen']['errores']);
        $this->assertSame('Contrata', $analysis['padron'][0]['tipo_contrato']);
    }

    public function test_valida_fechas_y_horas_y_acepta_serial_excel_y_formato_del_archivo_original(): void
    {
        foreach ([['2026-10-28', '9:00', '2026-10-28'], ['28/10/2026', '09:00 hrs.', '2026-10-28'], ['10/28/2026', '09:00', '2026-10-28'], [46323, .375, '2026-10-28']] as [$date, $time, $expected]) {
            $analysis = $this->analizar(locals: [$this->local(['DIA' => $date, 'HORARIO' => $time])]);
            $this->assertSame(0, $analysis['resumen']['errores']);
            $this->assertSame($expected, reset($analysis['locales'])['dia']);
            $this->assertSame('09:00:00', reset($analysis['locales'])['hora_inicio']);
        }
        foreach ([['2026-02-30', '9:00'], ['2026-10-28', '25:00'], ['', '09:00']] as [$date, $time]) {
            $analysis = $this->analizar(locals: [$this->local(['DIA' => $date, 'HORARIO' => $time])]);
            $this->assertGreaterThan(0, $analysis['resumen']['errores']);
        }
    }

    public function test_rechaza_formulas_hojas_incompletas_y_archivo_que_no_es_excel(): void
    {
        foreach ([$this->libro(voters: [$this->votante(['NOMBRE' => '=1+1'])]), $this->libro(headers: array_slice(PadronConsultaExcel::PADRON, 1))] as $path) {
            try { app(PadronConsultaExcel::class)->analizar($path); $this->fail('Debió rechazar la estructura.'); }
            catch (\InvalidArgumentException $e) { $this->assertStringNotContainsString($this->rut(), $e->getMessage()); }
        }
        $path = Storage::disk('local')->path('falso.xlsx'); file_put_contents($path, 'no es un archivo excel');
        $this->expectException(\InvalidArgumentException::class);
        app(PadronConsultaExcel::class)->analizar($path);
    }

    public function test_reimportacion_exige_confirmacion_y_retira_publicacion_sin_afectar_otras_jornadas(): void
    {
        $this->cargar(); $this->publicar();
        $other = JornadaVotacion::create(['nombre' => 'Otra jornada', 'slug' => 'otra', 'fecha' => '2026-10-29']);
        $admin = $this->testUser(5, 3);
        app(PadronConsultaService::class)->importar($other, $this->analizar(), $admin, false);
        $otherBefore = $other->padronConsulta()->get()->toJson();
        try { app(PadronConsultaService::class)->importar($this->jornada, $this->analizar(), $admin, false); $this->fail('Debe confirmar.'); }
        catch (ValidationException) { $this->assertTrue($this->jornada->refresh()->consulta_publica); }
        app(PadronConsultaService::class)->importar($this->jornada, $this->analizar(voters: [$this->votante(['NOMBRE' => 'Nombre actualizado'])]), $admin, true);
        $this->assertFalse($this->jornada->refresh()->consulta_publica);
        $this->assertSame('Nombre actualizado', $this->jornada->padronConsulta()->sole()->nombre);
        $this->assertSame($otherBefore, $other->padronConsulta()->get()->toJson());
    }

    public function test_error_de_bitacora_revierte_reemplazo_y_estado_de_publicacion(): void
    {
        $this->cargar(); $this->publicar();
        $before = DB::table('votacion_padron_consulta')->get()->toJson();
        $user = $this->testUser(8, 3);
        $this->mock(BitacoraVotacionService::class)->shouldReceive('registrar')->once()->andThrow(new \RuntimeException('Fallo simulado'));
        try { app(PadronConsultaService::class)->importar($this->jornada, $this->analizar(), $user, true); $this->fail('Debe revertir.'); }
        catch (\RuntimeException) {
            $this->assertSame($before, DB::table('votacion_padron_consulta')->get()->toJson());
            $this->assertTrue($this->jornada->refresh()->consulta_publica);
        }
    }

    public function test_administracion_requiere_login_y_rol_administrador_para_todas_las_acciones(): void
    {
        $this->get(route('votaciones.admin.padrones.index'))->assertRedirect(route('login'));
        $user = $this->testUser();
        $this->actingAs($user)->get(route('votaciones.admin.padrones.index'))->assertForbidden();
        $this->post(route('votaciones.admin.padrones.importar', $this->jornada))->assertForbidden();
        $this->patch(route('votaciones.admin.padrones.publicacion', $this->jornada), ['publicar' => 1])->assertForbidden();
        $this->assertSame(0, VotanteConsulta::count());
    }

    public function test_administrador_puede_cargar_revisar_publicar_y_retirar_desde_la_pantalla(): void
    {
        $admin = $this->testUser(1, 3);
        $this->actingAs($admin)->get(route('votaciones.admin.padrones.index'))->assertOk()->assertSee('Padrón y locales de votación');
        $file = new UploadedFile($this->libro(), 'padron.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
        $this->post(route('votaciones.admin.padrones.importar', $this->jornada), ['archivo' => $file])->assertRedirect()->assertSessionHas('success');
        $this->patch(route('votaciones.admin.padrones.publicacion', $this->jornada), ['publicar' => 1])->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($this->jornada->refresh()->consulta_publica);
        $this->patch(route('votaciones.admin.padrones.publicacion', $this->jornada), ['publicar' => 0])->assertRedirect();
        $this->assertFalse($this->jornada->refresh()->consulta_publica);
    }

    public function test_administrador_recibe_diagnostico_por_fila_sin_publicar_datos_del_padron(): void
    {
        $this->actingAs($this->testUser(1, 3));
        $file = new UploadedFile($this->libro(voters: [$this->votante(['RBD' => 9999])]), 'padron.xlsx', null, null, true);
        $this->post(route('votaciones.admin.padrones.importar', $this->jornada), ['archivo' => $file])->assertRedirect()->assertSessionHas('diagnostico_padron');
        $diagnostic = session('diagnostico_padron');
        $this->assertSame(1, $diagnostic['resumen']['errores']);
        $this->assertStringNotContainsString('Funcionaria de prueba', json_encode($diagnostic));
        $this->get(route('votaciones.admin.padrones.index'))->assertOk()->assertSee('fila 2')->assertSee('No se modificó el padrón existente');
        $this->assertSame(0, VotanteConsulta::count());
    }

    public function test_no_publica_padron_vacio_sin_procesos_o_jornada_suspendida(): void
    {
        foreach (['vacio', 'procesos', 'suspendida'] as $case) {
            if ($case !== 'vacio') $this->cargar(null, true);
            $this->jornada->procesos()->sync($case === 'procesos' ? [] : DB::table('procesos_votacion')->pluck('id'));
            $this->jornada->update(['estado' => $case === 'suspendida' ? 'suspendida' : 'borrador']);
            try { $this->publicar(); $this->fail('No debe publicar.'); }
            catch (ValidationException) { $this->assertFalse($this->jornada->refresh()->consulta_publica); }
        }
    }

    public function test_limita_intentos_de_busqueda_por_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.99']);
        for ($i = 0; $i < 15; $i++) {
            $this->post(route('public.consulta-votacion.consultar'), ['rut' => 'invalido'])->assertStatus(422);
        }
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => 'invalido'])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_consulta_y_publicacion_exigen_csrf_fuera_del_bypass_de_pruebas(): void
    {
        $this->app['env'] = 'local';
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])->assertStatus(419);
        $this->actingAs($this->testUser(1, 3))->patch(route('votaciones.admin.padrones.publicacion', $this->jornada), ['publicar' => 1])->assertStatus(419);
        $this->assertFalse($this->jornada->refresh()->consulta_publica);
        $this->withSession(['_token' => 'csrf-sintetico-de-prueba'])->post(route('public.consulta-votacion.consultar'), ['_token' => 'csrf-sintetico-de-prueba', 'rut' => 'invalido'])->assertStatus(422);
    }

    public function test_rechaza_hoja_que_supera_el_limite_de_filas_antes_de_cargar_el_padron(): void
    {
        $path = $this->libro();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $book->getSheet(0)->setCellValue('A20002', 'fuera del limite');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        $this->expectException(\InvalidArgumentException::class);
        app(PadronConsultaExcel::class)->analizar($path);
    }

    public function test_rut_normalizado_valida_digito_k_y_rechaza_inferencia_de_identidad(): void
    {
        $this->assertSame($this->rut(), RutConsulta::normalizar(RutChile::format($this->rut())));
        $body = 99000001;
        while (RutChile::dv($body) !== 'K') $body++;
        $this->assertSame($body.'K', RutConsulta::normalizar($body.'-k'));
        $this->assertNull(RutConsulta::normalizar('abc'.$this->rut()));
        $this->assertNull(RutConsulta::normalizar('99000001-0'));
    }

    public function test_mapa_y_direccion_se_obtienen_del_rbd_de_destino_y_no_del_origen_o_id(): void
    {
        DB::table('establecimientos')->insert([
            ['id' => 9501, 'rbd' => 9001, 'nombre_establecimiento' => 'Origen', 'comuna' => 'Comuna origen', 'latitud' => -36.1, 'longitud' => -72.1, 'director_contacto' => null],
            ['id' => 42, 'rbd' => 9501, 'nombre_establecimiento' => 'Destino', 'comuna' => 'Comuna destino', 'latitud' => -37.1, 'longitud' => -73.1, 'director_contacto' => 'CONTACTO_INTERNO_NO_PUBLICAR'],
        ]);
        DB::table('admision_establecimientos')->insert([
            ['establecimiento_id' => 9501, 'direccion_publica' => 'Direccion del origen que no debe usarse'],
            ['establecimiento_id' => 42, 'direccion_publica' => 'Calle de prueba 123'],
        ]);
        $this->cargar(); $this->publicar();
        $this->get(route('public.consulta-votacion.index'))->assertDontSee('Calle de prueba 123')->assertDontSee('data-cv-map', false);
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])
            ->assertOk()->assertSee('Calle de prueba 123')->assertSee('Comuna destino')
            ->assertSee('data-latitude="-37.1"', false)->assertSee('data-longitude="-73.1"', false)
            ->assertSee('Cómo llegar')->assertSee('destination=-37.1%2C-73.1', false)
            ->assertDontSee('Direccion del origen que no debe usarse')->assertDontSee('CONTACTO_INTERNO_NO_PUBLICAR');
    }

    public function test_local_sin_establecimiento_relacionado_conserva_resultado_y_explica_ubicacion_pendiente(): void
    {
        $this->cargar(); $this->publicar();
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])
            ->assertOk()->assertSee('Local de prueba')->assertSee('Dirección por confirmar')->assertSee('Ubicación en mapa por confirmar')
            ->assertDontSee('data-cv-map', false)->assertDontSee('Cómo llegar');
    }

    public function test_coordenadas_ausentes_fuera_de_rango_o_cero_no_generan_un_mapa_ficticio(): void
    {
        DB::table('establecimientos')->insert(['id' => 42, 'rbd' => 9501, 'nombre_establecimiento' => 'Destino']);
        DB::table('admision_establecimientos')->insert(['establecimiento_id' => 42, 'direccion_publica' => 'Calle de prueba 123']);
        $this->cargar(); $this->publicar();
        foreach ([[null, -73.1], [-37.1, null], [0, 0], [91, -73.1], [-37.1, 181]] as [$lat, $lng]) {
            DB::table('establecimientos')->where('id', 42)->update(['latitud' => $lat, 'longitud' => $lng]);
            $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])
                ->assertOk()->assertSee('Calle de prueba 123')->assertSee('Ubicación en mapa por confirmar')
                ->assertDontSee('data-cv-map', false)->assertDontSee('Cómo llegar');
        }
    }

    public function test_coordenadas_validas_sin_direccion_muestran_mapa_y_estado_de_direccion(): void
    {
        DB::table('establecimientos')->insert(['id' => 42, 'rbd' => 9501, 'nombre_establecimiento' => 'Destino', 'latitud' => -37.1, 'longitud' => -73.1]);
        $this->cargar(); $this->publicar();
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])
            ->assertOk()->assertSee('Dirección por confirmar')->assertSee('data-cv-map', false)->assertSee('Cómo llegar');
    }

    public function test_actualizar_la_ubicacion_del_catalogo_se_refleja_sin_reimportar_el_padron(): void
    {
        DB::table('establecimientos')->insert(['id' => 42, 'rbd' => 9501, 'nombre_establecimiento' => 'Destino', 'latitud' => -37.1, 'longitud' => -73.1]);
        DB::table('admision_establecimientos')->insert(['establecimiento_id' => 42, 'direccion_publica' => 'Direccion inicial']);
        $this->cargar(); $this->publicar();
        DB::table('establecimientos')->where('id', 42)->update(['latitud' => -37.2]);
        DB::table('admision_establecimientos')->where('establecimiento_id', 42)->update(['direccion_publica' => 'Direccion actualizada']);
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])
            ->assertOk()->assertSee('Direccion actualizada')->assertSee('data-latitude="-37.2"', false)->assertDontSee('Direccion inicial');
        $this->assertSame(1, $this->jornada->padronConsulta()->count());
    }

    public function test_textos_del_catalogo_y_nombre_del_local_se_escapan_en_la_tarjeta_del_mapa(): void
    {
        DB::table('establecimientos')->insert(['id' => 42, 'rbd' => 9501, 'nombre_establecimiento' => 'Destino', 'latitud' => -37.1, 'longitud' => -73.1]);
        DB::table('admision_establecimientos')->insert(['establecimiento_id' => 42, 'direccion_publica' => '<script>direccion()</script>']);
        $analysis = $this->analizar(locals: [$this->local(['LUGAR_DE_VOTACION' => '<img src=x onerror=local()>'])]);
        $this->cargar($analysis); $this->publicar();
        $this->post(route('public.consulta-votacion.consultar'), ['rut' => $this->rut(), 'jornada' => $this->jornada->slug])
            ->assertOk()->assertDontSee('<script>direccion()</script>', false)->assertDontSee('<img src=x onerror=local()>', false)
            ->assertSee('&lt;script&gt;direccion()&lt;/script&gt;', false)->assertSee('&lt;img src=x onerror=local()&gt;', false);
    }

    private function cargar(?array $analysis = null, bool $confirmar = false): void
    {
        $user = \App\Models\User::first() ?? $this->testUser(1, 3);
        app(PadronConsultaService::class)->importar($this->jornada, $analysis ?? $this->analizar(), $user, $confirmar);
    }

    private function publicar(): void
    {
        app(PadronConsultaService::class)->publicar($this->jornada, true, \App\Models\User::first() ?? $this->testUser(1, 3));
    }

    private function analizar(?array $voters = null, ?array $locals = null): array
    {
        return app(PadronConsultaExcel::class)->analizar($this->libro($voters, $locals));
    }

    private function rut(int $body = 99000001): string
    {
        return $body.RutChile::dv($body);
    }

    private function votante(array $override = []): array
    {
        return array_replace(['RUT' => $this->rut(), 'NOMBRE' => 'Funcionaria de prueba', 'RBD' => 9001, 'ESTABLECIMIENTO' => 'Origen de prueba', 'TIPO_DE_CONTRATO' => 'Contrata', 'GRUPO' => '1', 'RBD_LUGAR_DE_VOTACION' => 9501, 'LUGAR_DE_VOTACION' => 'Local de prueba'], $override);
    }

    private function local(array $override = []): array
    {
        return array_replace(['RBD' => 9001, 'ESTABLECIMIENTO' => 'Origen de prueba', 'RBD_LUGAR_DE_VOTACION' => 9501, 'LUGAR_DE_VOTACION' => 'Local de prueba', 'HORARIO' => '9:00', 'DIA' => '2026-10-28'], $override);
    }

    private function libro(?array $voters = null, ?array $locals = null, ?array $headers = null): string
    {
        $book = new Spreadsheet;
        $voterSheet = $book->getActiveSheet()->setTitle('PADRON VOTACIÓN');
        $headers ??= PadronConsultaExcel::PADRON;
        $voterSheet->fromArray([$headers], null, 'A1');
        foreach ($voters ?? [$this->votante()] as $i => $voter) {
            $values = [];
            foreach ($headers as $h) {
                $key = str_replace([' ', 'TIPO_DE_CONTRAT0'], ['_', 'TIPO_DE_CONTRATO'], trim($h));
                $key = $key === 'TIPO_DE_CONTRAT0' ? 'TIPO_DE_CONTRATO' : $key;
                $values[] = $voter[$key] ?? null;
            }
            $voterSheet->fromArray([$values], null, 'A'.($i + 2));
        }
        $localSheet = $book->createSheet()->setTitle('LOCALES VOTACION');
        $localSheet->fromArray([array_map(fn ($h) => $h.' ', PadronConsultaExcel::LOCALES)], null, 'A1');
        foreach ($locals ?? [$this->local()] as $i => $local) $localSheet->fromArray([array_values($local)], null, 'A'.($i + 2));
        $path = Storage::disk('local')->path('padron-'.bin2hex(random_bytes(8)).'.xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
