<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\OrdenTrabajoCorrelativo;
use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoConfiguracion;
use App\Models\SolicitudReemplazoProrroga;
use App\Services\OrdenTrabajoFirmaService;
use App\Services\OrdenTrabajoPdfService;
use App\Services\SolicitudReemplazoProrrogaService;
use App\Support\Rut;
use App\Support\SlepSidebarNavigation;
use App\Support\SlepUiRegistry;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\IsolatedSecurityTestCase;

class SolicitudReemplazoProrrogaTest extends IsolatedSecurityTestCase
{
    private const RUTA = 'gestion.solicitudes-reemplazo.prorrogas.';

    private const MIGRACION = '2026_11_26_130000_create_solicitud_reemplazo_prorrogas_table.php';

    private const MIGRACION_OT = '2026_11_26_140000_add_ordenes_trabajo_to_prorrogas_reemplazo.php';

    private const MIGRACION_RESOLUCION_OPCIONAL = '2026_11_26_150000_allow_orden_trabajo_before_prorroga_resolucion.php';

    private const MIGRACION_FIRMANTE = '2026_11_26_160000_add_firmante_to_ordenes_trabajo_reemplazo.php';

    private const RUTA_FIRMA = 'gestion.solicitudes-reemplazo.firma-ot.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureModuleAccess::class);
        $this->withoutVite();
        Schema::create('padron_aplicacion_control', fn (Blueprint $table) => $table->id());
        DB::table('padron_aplicacion_control')->insert(['id' => 1]);
        DB::table('roles')->insert([
            ['id' => 4, 'name' => 'funcionario_slep', 'guard_name' => 'web'],
            ['id' => 5, 'name' => 'coordinador_gdp', 'guard_name' => 'web'],
        ]);
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->integer('rbd');
            $table->string('nombre_establecimiento');
            $table->boolean('sala_cuna')->default(false);
        });
        Schema::create('areas_desempeno', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('slug');
            $table->string('estamento');
        });
        Schema::create('reemplazos_personal', function (Blueprint $table): void {
            $table->id();
            $table->string('estatuto');
        });
        Schema::create('postulant_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
        });
        Schema::create('solicitudes_reemplazo', function (Blueprint $table): void {
            $table->id();
            $table->string('numero_solicitud');
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('reemplazo_personal_id');
            $table->unsignedBigInteger('area_desempeno_id')->nullable();
            $table->unsignedInteger('correlativo')->nullable();
            $table->string('orden_trabajo_pdf_path')->nullable();
            $table->timestamp('orden_trabajo_creada_at')->nullable();
            $table->unsignedBigInteger('postulant_profile_id')->nullable();
            $table->unsignedBigInteger('contrato_trabajo_postulant_profile_id')->nullable();
            $table->string('rut_reemplazo_normalizado')->nullable();
            $table->string('estado');
            $table->date('fecha_inicio_trabajo')->nullable();
            $table->date('fecha_termino');
            $table->string('aaee_categoria')->nullable();
            $table->string('contrato_trabajo_docx_path')->nullable();
            $table->string('resolucion_docente_docx_path')->nullable();
            $table->string('resolucion_docente_firmada_pdf_path')->nullable();
        });
        Schema::create('solicitud_reemplazo_jornadas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('solicitud_reemplazo_id');
            $table->decimal('reemplazo_total', 6, 2)->nullable();
            $table->string('financiamiento')->nullable();
            $table->decimal('reemplazo_basica', 6, 2)->nullable();
            $table->decimal('reemplazo_media', 6, 2)->nullable();
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 101, 'nombre_establecimiento' => 'Escuela de prueba Norte'],
            ['id' => 2, 'rbd' => 102, 'nombre_establecimiento' => 'Escuela de prueba Sur'],
        ]);
        DB::table('reemplazos_personal')->insert([
            ['id' => 1, 'estatuto' => 'DOCENTE'],
            ['id' => 2, 'estatuto' => 'PROFESORA'],
            ['id' => 3, 'estatuto' => 'A.A.E.E.'],
            ['id' => 4, 'estatuto' => 'ASISTENTE DE LA EDUCACIÓN'],
        ]);
        (require database_path('migrations/'.self::MIGRACION))->up();
        (require database_path('migrations/'.self::MIGRACION_OT))->up();
        (require database_path('migrations/'.self::MIGRACION_RESOLUCION_OPCIONAL))->up();
        (require database_path('migrations/2026_08_11_090000_create_solicitud_reemplazo_autorizaciones_docentes_tables.php'))->up();
        (require database_path('migrations/'.self::MIGRACION_FIRMANTE))->up();
    }

    public function test_configuracion_permite_previsualizar_una_firma_sin_publicarla_y_admite_volver_a_la_titular(): void
    {
        $gdp = $this->testUser(1, 5);
        $funcionario = $this->testUser(2, 4);
        $edit = route(self::RUTA_FIRMA.'edit');
        $update = route(self::RUTA_FIRMA.'update');
        $preview = route(self::RUTA_FIRMA.'preview');
        $datos = $this->datosFirmaSubrogante();
        $firmas = app(OrdenTrabajoFirmaService::class);
        $this->actingAs($funcionario)->get($edit)->assertForbidden();
        $this->post($update, $datos)->assertForbidden();
        $this->post($preview, $datos)->assertForbidden();

        $this->actingAs($gdp)->get($edit)->assertOk()->assertSee('Firma de órdenes de trabajo')->assertSee('Previsualizar PDF');
        $menu = SlepUiRegistry::menuGroups($gdp, 'coordinador_gdp');
        $seccion = collect(SlepSidebarNavigation::forRole($menu, 'coordinador_gdp', self::RUTA_FIRMA.'edit')['sections'])->firstWhere('key', 'seleccion');
        $this->assertContains(self::RUTA_FIRMA.'edit', array_column($seccion['links'], 'route'));
        $this->from($edit)->post($update, $datos)->assertSessionHasErrors('subrogante.firma');
        $datos['subrogante']['firma'] = UploadedFile::fake()->image('firma-de-prueba.png', 240, 110);
        $vistas = [];
        View::composer('pdf.orden-trabajo-prorroga', function ($vista) use (&$vistas): void {
            $vistas[] = $vista->getData()['firmaOt'];
        });
        $respuesta = $this->post($preview, $datos)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
        $this->assertSame('Persona subrogante de prueba', $vistas[0]['nombre']);
        $this->assertStringStartsWith('data:image/png;base64,', $vistas[0]['firma']);
        $this->assertDatabaseMissing('solicitud_reemplazo_configuraciones', ['clave' => SolicitudReemplazoConfiguracion::FIRMA_ORDEN_TRABAJO]);
        $this->assertSame([], Storage::disk('local')->allFiles('reemplazos/firmas-ot'));

        $this->post($update, $datos)->assertRedirect($edit)->assertSessionHasNoErrors();
        $subrogante = $firmas->vigente();
        $this->assertSame('Persona subrogante de prueba', $subrogante['nombre']);
        Storage::disk('local')->assertExists($subrogante['archivo']);
        unset($datos['subrogante']['firma']);
        $datos['vigente'] = 'titular';
        $this->post($update, $datos)->assertRedirect($edit)->assertSessionHasNoErrors();
        $this->assertSame($firmas->titularInstitucional(), $firmas->vigente());
        $this->assertSame($subrogante, $firmas->configuracion()['subrogante']);
    }

    public function test_una_ot_de_reemplazo_conserva_el_firmante_y_el_archivo_aunque_se_cambie_la_configuracion(): void
    {
        $gdp = $this->testUser(1, 5);
        $firmas = app(OrdenTrabajoFirmaService::class);
        $datos = $this->datosFirmaSubrogante();
        $datos['subrogante']['firma'] = UploadedFile::fake()->image('firma-de-prueba.jpg', 300, 80);
        $update = route(self::RUTA_FIRMA.'update');
        $this->actingAs($gdp)->post($update, $datos)->assertSessionHasNoErrors();
        $firmante = $firmas->vigente();

        $id = $this->periodo('99000009K', 1, '2027-01-01', '2027-01-31', ['estado' => 'derivada_slep']);
        $orden = SolicitudReemplazo::findOrFail($id);
        $pdf = app(OrdenTrabajoPdfService::class);
        $ruta = $pdf->generateAndStore($orden);
        DB::table('solicitudes_reemplazo')->where('id', $id)
            ->update(['orden_trabajo_pdf_path' => $ruta, 'orden_trabajo_firmante' => json_encode($orden->orden_trabajo_firmante), 'estado' => 'aceptada']);
        $this->assertSame($firmante, $orden->orden_trabajo_firmante);

        $datos['subrogante']['firma'] = UploadedFile::fake()->image('nueva-firma-de-prueba.png', 400, 120);
        $datos['vigente'] = 'titular';
        $this->post($update, $datos)->assertSessionHasNoErrors();
        $this->assertNotSame($firmante['archivo'], $firmas->configuracion()['subrogante']['archivo']);
        Storage::disk('local')->assertExists($firmante['archivo']);
        $this->assertSame($ruta, $pdf->generateAndStore($orden->fresh()));
        $this->assertSame($firmante, $orden->fresh()->orden_trabajo_firmante);
    }

    private function datosFirmaSubrogante(): array
    {
        return [
            'vigente' => 'subrogante',
            'titular' => app(OrdenTrabajoFirmaService::class)->titularInstitucional(),
            'subrogante' => ['nombre' => 'Persona subrogante de prueba', 'cargo' => 'Subdirección subrogante de prueba'],
        ];
    }

    public function test_une_titulares_y_escuelas_consecutivas_pero_no_une_estamentos_ni_dias_con_brechas(): void
    {
        $rut = '99000001K';
        $primera = $this->periodo('99.000.001-k', 1, '2026-06-12', '2026-08-31');
        $segunda = $this->periodo($rut, 2, '2026-09-01', '2026-12-12', ['estado' => 'cerrada', 'establecimiento_id' => 2]);
        $this->periodo($rut, 3, '2026-09-01', '2026-12-12');
        $this->periodo($rut, 2, '2027-02-01', '2027-03-07');
        $this->periodo('99000002K', 1, '2026-06-12', '2026-09-12');
        $this->periodo('99000002K', 3, '2026-09-13', '2026-12-12');
        $this->periodo('99000003K', 3, '2026-06-12', '2026-09-01');
        $this->periodo('99000003K', 4, '2026-09-01', '2026-12-12', ['establecimiento_id' => 2]);
        $this->periodo('99000004K', 1, '2026-06-12', '2026-08-31');
        $this->periodo('99000004K', 2, '2026-09-02', '2026-12-12');
        $this->periodo('99000004K', 2, '2026-09-01', '2026-09-01', ['estado' => 'anulada']);
        $this->periodo('99000005K', 1, '2026-06-13', '2026-12-12');
        $this->periodo('99000006K', 1, '2027-06-30', '2028-03-01');
        DB::table('solicitud_reemplazo_jornadas')->insert(['solicitud_reemplazo_id' => $segunda, 'reemplazo_total' => 38]);

        $servicio = app(SolicitudReemplazoProrrogaService::class);
        $casos = $servicio->casos(2026);
        $this->assertSame(['aaee:99000003K', 'docente:99000001K'], $casos->keys()->sort()->values()->all());
        $docente = $casos->get('docente:'.$rut);
        $this->assertSame('2026-06-12', $docente['inicio_continuidad']);
        $this->assertSame('2026-12-12', $docente['fecha_corte']);
        $this->assertSame([$primera, $segunda], array_column($docente['periodos'], 'id'));
        $this->assertSame(['Escuela de prueba Norte', 'Escuela de prueba Sur'], array_column($docente['periodos'], 'establecimiento'));
        $this->assertEquals(38, $docente['periodos'][1]['horas']);
        $this->assertSame([['desde' => '2027-02-01', 'hasta' => '2027-02-28']], $docente['cobertura']);
        $siguiente = $servicio->casos(2027)->get('docente:99000006K');
        $this->assertSame('2027-12-31', $siguiente['fecha_corte']);
        $this->assertSame([['desde' => '2028-01-01', 'hasta' => '2028-02-29']], $siguiente['cobertura']);
    }

    public function test_bandejas_y_resoluciones_son_independientes_por_estamento_y_restringidas_por_rol(): void
    {
        $funcionario = $this->testUser(1, 4);
        $gdp = $this->testUser(2, 5);
        $admin = $this->testUser(3, 3);
        $persona = $this->testUser(4, 1);
        $perfil = DB::table('postulant_profiles')->insertGetId(['user_id' => $persona->id]);
        $rut = Rut::normalize($persona->rut);
        $doc = $this->periodo($rut, 1, '2026-06-12', '2026-12-12', ['postulant_profile_id' => $perfil]);
        $aaee = $this->periodo($rut, 3, '2026-06-12', '2026-12-12', ['contrato_trabajo_postulant_profile_id' => $perfil]);
        $datosDoc = ['anio' => 2026, 'estamento' => 'docente', 'rut' => $rut];
        $datosAaee = array_replace($datosDoc, ['estamento' => 'aaee']);
        $urlDoc = route(self::RUTA.'show', $datosDoc);
        $urlAaee = route(self::RUTA.'show', $datosAaee);
        $bandeja = route(self::RUTA.'index', ['anio' => 2026]);

        foreach ([$funcionario, $gdp, $admin] as $usuario) {
            $this->actingAs($usuario)->get($bandeja)->assertOk()->assertSee('Prórrogas Reemplazos');
            $rol = $usuario->activeRoleName();
            $menu = SlepUiRegistry::menuGroups($usuario, $rol);
            $navegacion = SlepSidebarNavigation::forRole($menu, $rol, self::RUTA.'show');
            $seleccion = collect($navegacion['sections'])->firstWhere('key', 'seleccion');
            $this->assertSame(self::RUTA.'index', collect($seleccion['links'])->firstWhere('active', true)['route']);
        }
        $this->actingAs($persona)->get($bandeja)->assertForbidden();
        $this->post($urlDoc)->assertForbidden();
        $this->actingAs($funcionario)->get($urlDoc)->assertOk()->assertSee("PRUEBA-{$doc}")->assertDontSee("PRUEBA-{$aaee}");
        $this->get(route(self::RUTA.'index', ['anio' => 2026, 'estamento' => 'aaee']))->assertOk()
            ->assertSee($urlAaee)->assertDontSee($urlDoc);
        $this->get($urlAaee)->assertOk()->assertSee("PRUEBA-{$aaee}")->assertDontSee("PRUEBA-{$doc}");
        $this->post($urlDoc, ['numero_resolucion' => 'REX-PRUEBA'])->assertSessionHasErrors(['fecha_resolucion', 'resolucion']);
        $datos = ['numero_resolucion' => 'REX-PRUEBA', 'fecha_resolucion' => '2026-12-14'];
        $this->post($urlDoc, $datos + ['resolucion' => UploadedFile::fake()->create('prorroga.pdf', 10, 'application/pdf')])
            ->assertRedirect($urlDoc)->assertSessionHasNoErrors();
        $registro = SolicitudReemplazoProrroga::query()->sole();
        $this->assertSame('docente', $registro->estamento);
        $this->assertSame($funcionario->id, $registro->registrada_por_id);
        $this->assertSame([$doc], array_column($registro->antecedentes['periodos'], 'id'));
        $this->assertSame('2027-02-28', $registro->vigencia_hasta->toDateString());
        Storage::disk('local')->assertExists($registro->resolucion_path);

        DB::table('solicitudes_reemplazo')->where('id', $doc)->update(['estado' => 'anulada']);
        $this->get($urlDoc)->assertOk()->assertSee('REX-PRUEBA')->assertSee("PRUEBA-{$doc}");
        $this->get($urlAaee)->assertOk()->assertSee('Pendiente de revisión');
        $this->post($urlDoc, $datos + ['resolucion' => UploadedFile::fake()->create('duplicado.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('resolucion');
        $this->post($urlAaee, $datos + ['resolucion' => UploadedFile::fake()->create('aaee.pdf', 10, 'application/pdf')])
            ->assertRedirect($urlAaee)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('solicitud_reemplazo_prorrogas', 2);
        $urlDescarga = route(self::RUTA.'resolucion', $registro);
        $this->get($urlDescarga)->assertDownload("resolucion-prorroga-{$registro->id}.pdf");
        $this->actingAs($persona)->get($urlDescarga)->assertForbidden();
    }

    public function test_mysql_compila_identificadores_cortos_y_reintenta_solo_la_restriccion_faltante(): void
    {
        $pdo = \Mockery::mock(\PDO::class);
        $pdo->shouldReceive('getAttribute')->with(\PDO::ATTR_SERVER_VERSION)->andReturn('8.0.36');
        $conexion = new MySqlConnection($pdo, 'sga_pruebas', 'sga_', ['driver' => 'mysql', 'prefix_indexes' => true]);
        $conexion->useDefaultSchemaGrammar();
        $sql = [];
        $compilar = function ($nombre, $definicion, $crear = false) use ($conexion, &$sql): void {
            $plano = new Blueprint($conexion, $nombre);
            if ($crear) {
                $plano->create();
            }
            $definicion($plano);
            array_push($sql, ...$plano->toSql());
        };
        $tabla = 'solicitud_reemplazo_prorrogas';
        Schema::shouldReceive('getConnection')->twice()->andReturn($conexion);
        Schema::shouldReceive('hasTable')->with($tabla)->twice()->andReturn(false, true);
        Schema::shouldReceive('hasIndex')->with($tabla, 'srp_periodo_est_rut_uq')->twice()->andReturn(false, true);
        Schema::shouldReceive('getForeignKeys')->with($tabla)->twice()->andReturn([]);
        Schema::shouldReceive('create')->once()->andReturnUsing(fn ($nombre, $definicion) => $compilar($nombre, $definicion, true));
        Schema::shouldReceive('table')->times(3)->andReturnUsing($compilar);
        $migracion = require database_path('migrations/'.self::MIGRACION);

        $migracion->up();
        $this->assertCount(3, $sql);
        $this->assertStringContainsString('srp_periodo_est_rut_uq', $sql[1]);
        $this->assertStringContainsString('srp_usuario_fk', $sql[2]);
        preg_match_all('/\x60([^\x60]+)\x60/', implode(' ', $sql), $identificadores);
        foreach ($identificadores[1] as $identificador) {
            $this->assertLessThanOrEqual(64, strlen($identificador));
        }
        $llave = $sql[2];
        $sql = [];
        $migracion->up();
        $this->assertSame([$llave], $sql);
    }

    public function test_junji_acredita_seis_meses_en_la_misma_sala_cuna_y_exporta_la_nomina_filtrada(): void
    {
        DB::table('establecimientos')->update(['sala_cuna' => true]);
        DB::table('areas_desempeno')->insert([
            ['id' => 10, 'nombre' => 'Educadora de Párvulos', 'slug' => 'educadora_de_parvulos', 'estamento' => 'docente'],
            ['id' => 20, 'nombre' => 'Técnico de Párvulos', 'slug' => 'tecnico_parvulos', 'estamento' => 'asistente'],
        ]);
        $rut = '99000001K';
        $this->periodo($rut, 1, '2027-06-12', '2027-08-31', ['area_desempeno_id' => 10]);
        $ultimo = $this->periodo($rut, 2, '2027-09-01', '2027-12-12', ['area_desempeno_id' => 10]);
        DB::table('solicitud_reemplazo_jornadas')->insert(['solicitud_reemplazo_id' => $ultimo, 'financiamiento' => 'JUNJI', 'reemplazo_total' => 44]);
        $this->periodo('99000002K', 1, '2027-06-12', '2027-08-31', ['area_desempeno_id' => 10]);
        $this->periodo('99000002K', 1, '2027-09-01', '2027-12-12', ['area_desempeno_id' => 10, 'establecimiento_id' => 2]);
        $this->periodo('99000003K', 3, '2027-06-12', '2027-12-12', ['area_desempeno_id' => 20]);
        $servicio = app(SolicitudReemplazoProrrogaService::class);
        $lista = $servicio->casos(2027);
        $this->assertSame(['junji:99000001K', 'junji:99000003K'], $lista->keys()->sort()->values()->all());
        $this->assertSame(['desde' => '2028-02-01', 'hasta' => '2028-02-29'], $servicio->vigencia(2027, 'junji'));

        $usuario = $this->testUser(1, 4);
        $filtros = ['anio' => 2027, 'estamento' => 'junji', 'area_id' => 10];
        $this->actingAs($usuario)->get(route(self::RUTA.'index', $filtros))->assertOk()->assertSee('44 h JUNJI');
        $respuesta = $this->get(route(self::RUTA.'index', $filtros + ['exportar' => 1]));
        $respuesta->assertDownload('Nomina_Prorrogas_junji_2027.xlsx');
        $temporal = tempnam(sys_get_temp_dir(), 'prorrogas-excel-');
        try {
            file_put_contents($temporal, $respuesta->streamedContent());
            $libro = IOFactory::load($temporal);
            $hoja = $libro->getActiveSheet();
            $this->assertSame('JUNJI', $hoja->getTitle());
            $this->assertSame(4, $hoja->getHighestDataRow());
            $this->assertSame('Educadora de Párvulos', $hoja->getCell('F4')->getValue());
            $this->assertSame('44 h JUNJI', $hoja->getCell('H4')->getValue());
            $this->assertSame('29/02/2028', $hoja->getCell('L4')->getFormattedValue());
            $libro->disconnectWorksheets();
        } finally {
            unlink($temporal);
        }

        $url = route(self::RUTA.'show', ['anio' => 2027, 'estamento' => 'junji', 'rut' => $rut]);
        $this->post($url, [
            'numero_resolucion' => 'REX-JUNJI-PRUEBA', 'fecha_resolucion' => '2027-12-14',
            'resolucion' => UploadedFile::fake()->create('junji.pdf', 10, 'application/pdf'),
        ])->assertRedirect($url);
        $registro = SolicitudReemplazoProrroga::query()->sole();
        $this->get($url)->assertOk()->assertSee('01/02/2028')->assertSee('E-198383')->assertDontSee('41 BIS');
        $this->post(route(self::RUTA.'orden-trabajo.generar', $registro), ['regenerar' => 1])->assertSessionHasErrors('orden_trabajo');
        $this->assertNull($registro->fresh()->correlativoOrdenTrabajo);
        $this->post(route(self::RUTA.'orden-trabajo.generar', $registro))->assertRedirect($url);
        Storage::disk('local')->assertExists($registro->fresh()->orden_trabajo_pdf_path);
    }

    public function test_la_orden_usa_el_ultimo_reemplazo_y_reserva_el_numero_para_ambos_modulos(): void
    {
        $gdp = $this->testUser(3, 5);
        $datosFirma = $this->datosFirmaSubrogante();
        $datosFirma['subrogante']['firma'] = UploadedFile::fake()->image('firma-de-prueba.png', 240, 90);
        $this->actingAs($gdp)->post(route(self::RUTA_FIRMA.'update'), $datosFirma)->assertSessionHasNoErrors();
        $firmante = app(OrdenTrabajoFirmaService::class)->vigente();
        $funcionario = $this->testUser(1, 4);
        $funcionario->update(['nombres' => 'Ágata Ejemplo', 'apellido_paterno' => 'Prueba', 'apellido_materno' => 'Simulada']);
        $inicialesEnPdf = [];
        View::composer('pdf.orden-trabajo-prorroga', function ($vista) use (&$inicialesEnPdf): void {
            $inicialesEnPdf[] = $vista->getData()['inicialesEmisor'];
        });
        $persona = $this->testUser(2, 1);
        $rut = Rut::normalize($persona->rut);
        $perfil = DB::table('postulant_profiles')->insertGetId(['user_id' => $persona->id]);
        DB::table('areas_desempeno')->insert(['id' => 10, 'nombre' => 'Docente diferencial', 'slug' => 'docente_diferencial', 'estamento' => 'docente']);
        $historica = $this->periodo($rut, 1, '2027-06-12', '2027-09-01', ['numero_solicitud' => '06275-2027', 'orden_trabajo_pdf_path' => 'historica.pdf']);
        $ultimo = $this->periodo($rut, 2, '2027-09-02', '2027-12-12', [
            'establecimiento_id' => 2, 'area_desempeno_id' => 10, 'postulant_profile_id' => $perfil,
        ]);
        DB::table('solicitud_reemplazo_jornadas')->insert([
            ['solicitud_reemplazo_id' => $ultimo, 'financiamiento' => 'SUBV. GENERAL', 'reemplazo_total' => 41],
            ['solicitud_reemplazo_id' => $ultimo, 'financiamiento' => 'PIE', 'reemplazo_total' => 3],
        ]);
        $this->periodo('99000009K', 1, '2027-10-01', '2027-10-31', [
            'estado' => 'rechazada_uatp', 'correlativo' => 6280, 'numero_solicitud' => '06280-2027',
        ]);
        $migracion = require database_path('migrations/'.self::MIGRACION_OT);
        $migracion->up();
        $migracion->up();

        $datos = ['anio' => 2027, 'estamento' => 'docente', 'rut' => $rut];
        $url = route(self::RUTA.'show', $datos);
        $generar = route(self::RUTA.'orden-trabajo.generar-caso', $datos);
        $this->actingAs($persona)->post($generar)->assertForbidden();
        $this->actingAs($funcionario)->get($url)->assertOk()->assertSee($generar)->assertSee('Generar orden de trabajo')
            ->assertSee('41 h SUBV. GENERAL + 3 h PIE')->assertSee('29/02/2028');
        $this->post($generar)->assertRedirect($url)->assertSessionHasNoErrors();
        $this->post($generar)->assertRedirect($url)->assertSessionHasNoErrors();
        $registro = SolicitudReemplazoProrroga::query()->sole();
        $this->assertFalse($registro->tieneResolucion());
        $this->assertNull($registro->numero_resolucion);
        $this->assertSame('Escuela de prueba Sur', $registro->antecedentes['ultimo_reemplazo']['establecimiento']);
        $this->assertSame(6281, $registro->correlativoOrdenTrabajo->id);
        $this->assertSame($funcionario->id, $registro->orden_trabajo_emitida_por_id);
        $this->assertSame(['áps'], $inicialesEnPdf);
        $this->assertSame($firmante, $registro->orden_trabajo_firmante);
        $disco = Storage::disk('local');
        $disco->assertExists($registro->orden_trabajo_pdf_path);
        $huella = hash('sha256', $disco->get($registro->orden_trabajo_pdf_path));
        $this->get(route(self::RUTA.'resolucion', $registro))->assertNotFound();
        $this->get(route(self::RUTA.'orden-trabajo.descargar', $registro))->assertDownload('orden-trabajo-prorroga-6281.pdf');
        $this->get($url)->assertOk()->assertSee('Descargar OT N° 6281')->assertSee('Registrar prórroga');
        $bandejaPendiente = route(self::RUTA.'index', ['anio' => 2027, 'estado' => 'pendiente']);
        $this->get($bandejaPendiente)->assertOk()->assertSee('OT N° 6281');

        $this->post($url, [
            'numero_resolucion' => 'REX-PRUEBA', 'fecha_resolucion' => '2027-12-14',
            'resolucion' => UploadedFile::fake()->create('prorroga.pdf', 10, 'application/pdf'),
        ])->assertRedirect($url)->assertSessionHasNoErrors();
        $registro->refresh();
        $this->assertDatabaseCount('solicitud_reemplazo_prorrogas', 1);
        $this->assertSame('REX-PRUEBA', $registro->numero_resolucion);
        $this->assertNotNull($registro->resolucion_registrada_at);
        $this->assertSame(6281, $registro->correlativoOrdenTrabajo->id);
        $this->assertSame($huella, hash('sha256', $disco->get($registro->orden_trabajo_pdf_path)));
        $this->get($bandejaPendiente)->assertOk()->assertDontSee('OT N° 6281');

        $rutaAnterior = $registro->orden_trabajo_pdf_path;
        $emitidaAt = $registro->orden_trabajo_emitida_at;
        $regenerar = route(self::RUTA.'orden-trabajo.generar', $registro);
        $this->actingAs($persona)->post($regenerar, ['regenerar' => 1])->assertForbidden();
        $datosFirma['vigente'] = 'titular';
        unset($datosFirma['subrogante']['firma']);
        $this->actingAs($gdp)->post(route(self::RUTA_FIRMA.'update'), $datosFirma)->assertSessionHasNoErrors();
        $this->actingAs($gdp)->get($url)->assertOk()->assertSee('Regenerar orden de trabajo')->assertSee('Descargar OT N° 6281');
        $this->post($regenerar, ['regenerar' => 1])->assertRedirect($url)
            ->assertSessionHas('status', 'Orden de trabajo regenerada con el mismo número.');
        $registro->refresh();
        $this->assertSame(6281, $registro->correlativoOrdenTrabajo->id);
        $this->assertSame($funcionario->id, $registro->orden_trabajo_emitida_por_id);
        $this->assertTrue($emitidaAt->equalTo($registro->orden_trabajo_emitida_at));
        $this->assertSame(['áps', 'áps'], $inicialesEnPdf);
        $this->assertSame($firmante, $registro->orden_trabajo_firmante);
        $this->assertSame('REX-PRUEBA', $registro->numero_resolucion);
        $disco->assertExists($registro->resolucion_path);
        $disco->assertMissing($rutaAnterior);
        $this->assertNotSame($huella, hash('sha256', $disco->get($registro->orden_trabajo_pdf_path)));
        $this->get(route(self::RUTA.'orden-trabajo.descargar', $registro))->assertDownload('orden-trabajo-prorroga-6281.pdf');

        $this->periodo('99000008K', 1, '2027-11-01', '2027-11-30', [
            'estado' => 'rechazada_plani', 'correlativo' => 6283, 'numero_solicitud' => '06283-2027',
        ]);
        $nuevo = $this->periodo($rut, 1, '2028-03-01', '2028-03-31', ['estado' => 'derivada_slep', 'numero_solicitud' => '00002-2028']);
        $solicitud = SolicitudReemplazo::findOrFail($nuevo);
        $pdf = app(OrdenTrabajoPdfService::class);
        $this->assertStringContainsString('06275', $pdf->generateAndStore(SolicitudReemplazo::findOrFail($historica)));
        $rutaNueva = $pdf->generateAndStore($solicitud);
        $this->assertStringContainsString('06284', $rutaNueva);
        $this->assertSame($rutaNueva, $pdf->generateAndStore($solicitud));
        $this->assertSame([6275, 6281, 6284], OrdenTrabajoCorrelativo::query()->orderBy('id')->pluck('id')->all());
        $this->assertStringNotContainsString('41 BIS', app(SolicitudReemplazoProrrogaService::class)->motivo('aaee'));
    }

    public function test_migracion_ot_mysql_compila_y_reintenta_el_ultimo_campo_faltante(): void
    {
        $pdo = \Mockery::mock(\PDO::class);
        $pdo->shouldReceive('getAttribute')->with(\PDO::ATTR_SERVER_VERSION)->andReturn('8.0.36');
        $conexion = new MySqlConnection($pdo, 'sga_pruebas', 'sga_', ['driver' => 'mysql', 'prefix_indexes' => true]);
        $conexion->useDefaultSchemaGrammar();
        $sql = [];
        $compilar = function ($nombre, $definicion, $crear = false) use ($conexion, &$sql): void {
            $plano = new Blueprint($conexion, $nombre);
            if ($crear) {
                $plano->create();
            }
            $definicion($plano);
            array_push($sql, ...$plano->toSql());
        };
        DB::table('orden_trabajo_correlativos')->insert(['origen' => 'historico', 'origen_id' => 0]);
        Schema::shouldReceive('hasTable')->with('orden_trabajo_correlativos')->twice()->andReturn(false, true);
        Schema::shouldReceive('hasIndex')->with('orden_trabajo_correlativos', 'otc_origen_uq')->twice()->andReturn(false, true);
        foreach (['orden_trabajo_pdf_path', 'orden_trabajo_emitida_por_id', 'orden_trabajo_emitida_at'] as $columna) {
            Schema::shouldReceive('hasColumn')->with('solicitud_reemplazo_prorrogas', $columna)->twice()
                ->andReturn(false, $columna !== 'orden_trabajo_emitida_at');
        }
        Schema::shouldReceive('create')->once()->andReturnUsing(fn ($nombre, $definicion) => $compilar($nombre, $definicion, true));
        Schema::shouldReceive('table')->times(5)->andReturnUsing($compilar);
        $migracion = require database_path('migrations/'.self::MIGRACION_OT);
        $migracion->up();

        $this->assertCount(5, $sql);
        $this->assertStringContainsString('auto_increment primary key', $sql[0]);
        $this->assertStringContainsString('otc_origen_uq', $sql[1]);
        preg_match_all('/\x60([^\x60]+)\x60/', implode(' ', $sql), $identificadores);
        foreach ($identificadores[1] as $identificador) {
            $this->assertLessThanOrEqual(64, strlen($identificador));
        }
        $pendiente = $sql[4];
        $sql = [];
        $migracion->up();
        $this->assertSame([$pendiente], $sql);
    }

    public function test_migracion_firmante_mysql_reintenta_el_cambio_faltante(): void
    {
        $pdo = \Mockery::mock(\PDO::class);
        $pdo->shouldReceive('getAttribute')->with(\PDO::ATTR_SERVER_VERSION)->andReturn('8.0.36');
        $conexion = new MySqlConnection($pdo, 'sga_pruebas', 'sga_', ['driver' => 'mysql', 'prefix_indexes' => true]);
        $conexion->useDefaultSchemaGrammar();
        Schema::shouldReceive('hasColumn')->with('solicitudes_reemplazo', 'orden_trabajo_firmante')->twice()->andReturn(false, true);
        Schema::shouldReceive('hasColumn')->with('solicitud_reemplazo_prorrogas', 'orden_trabajo_firmante')->twice()->andReturn(false, false);
        $sql = [];
        Schema::shouldReceive('table')->times(3)->andReturnUsing(function ($tabla, $definicion) use ($conexion, &$sql): void {
            $plano = new Blueprint($conexion, $tabla);
            $definicion($plano);
            array_push($sql, ...$plano->toSql());
        });
        $migracion = require database_path('migrations/'.self::MIGRACION_FIRMANTE);
        $migracion->up();
        $this->assertSame([
            'alter table `sga_solicitudes_reemplazo` add `orden_trabajo_firmante` json null',
            'alter table `sga_solicitud_reemplazo_prorrogas` add `orden_trabajo_firmante` json null',
        ], $sql);
        $pendiente = $sql[1];
        $sql = [];
        $migracion->up();
        $this->assertSame([$pendiente], $sql);
    }

    public function test_mysql_hace_opcionales_los_campos_de_resolucion_y_reanuda_el_cambio_pendiente(): void
    {
        $pdo = \Mockery::mock(\PDO::class);
        $pdo->shouldReceive('getAttribute')->with(\PDO::ATTR_SERVER_VERSION)->andReturn('8.0.36');
        $conexion = new MySqlConnection($pdo, 'sga_pruebas', 'sga_', ['driver' => 'mysql', 'prefix_indexes' => true]);
        $conexion->useDefaultSchemaGrammar();
        $tabla = 'solicitud_reemplazo_prorrogas';
        $campos = ['numero_resolucion', 'fecha_resolucion', 'resolucion_path', 'resolucion_nombre'];
        Schema::shouldReceive('getColumns')->with($tabla)->twice()->andReturn(
            array_map(fn ($campo) => ['name' => $campo, 'nullable' => false], $campos),
            array_map(fn ($campo) => ['name' => $campo, 'nullable' => $campo !== 'fecha_resolucion'], $campos),
        );
        Schema::shouldReceive('hasColumn')->with($tabla, 'resolucion_registrada_at')->twice()->andReturn(false, true);
        $sql = [];
        Schema::shouldReceive('table')->times(6)->andReturnUsing(function ($nombre, $definicion) use ($conexion, &$sql): void {
            $plano = new Blueprint($conexion, $nombre);
            $definicion($plano);
            array_push($sql, ...$plano->toSql());
        });
        $migracion = require database_path('migrations/'.self::MIGRACION_RESOLUCION_OPCIONAL);
        $migracion->up();
        $this->assertCount(5, $sql);
        $this->assertStringContainsString('modify `numero_resolucion` varchar(100) null', $sql[0]);
        $this->assertStringContainsString('modify `fecha_resolucion` date null', $sql[1]);
        $this->assertStringContainsString('modify `resolucion_path` varchar(255) null', $sql[2]);
        $this->assertStringContainsString('modify `resolucion_nombre` varchar(255) null', $sql[3]);
        $this->assertStringContainsString('add `resolucion_registrada_at` timestamp null', $sql[4]);
        $pendiente = $sql[1];
        $sql = [];
        $migracion->up();
        $this->assertSame([$pendiente], $sql);
    }

    private function periodo(string $rut, int $titular, string $desde, string $hasta, array $ajustes = []): int
    {
        $id = DB::table('solicitudes_reemplazo')->count() + 1;

        return DB::table('solicitudes_reemplazo')->insertGetId(array_replace([
            'numero_solicitud' => "PRUEBA-{$id}",
            'rut_reemplazo_normalizado' => $rut,
            'reemplazo_personal_id' => $titular,
            'establecimiento_id' => 1,
            'estado' => 'aceptada',
            'fecha_inicio_trabajo' => $desde,
            'fecha_termino' => $hasta,
        ], $ajustes));
    }
}
