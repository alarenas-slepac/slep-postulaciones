<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Models\SolicitudReemplazoProrroga;
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
use Tests\Support\IsolatedSecurityTestCase;

class SolicitudReemplazoProrrogaTest extends IsolatedSecurityTestCase
{
    private const RUTA = 'gestion.solicitudes-reemplazo.prorrogas.';

    private const MIGRACION = '2026_11_26_130000_create_solicitud_reemplazo_prorrogas_table.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureModuleAccess::class);
        $this->withoutVite();
        DB::table('roles')->insert([
            ['id' => 4, 'name' => 'funcionario_slep', 'guard_name' => 'web'],
            ['id' => 5, 'name' => 'coordinador_gdp', 'guard_name' => 'web'],
        ]);
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->integer('rbd');
            $table->string('nombre_establecimiento');
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
        $this->assertSame([['desde' => '2028-01-01', 'hasta' => '2028-02-28']], $siguiente['cobertura']);
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
