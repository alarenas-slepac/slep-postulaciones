<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionDocenteExclusionController;
use App\Http\Controllers\Admin\DotacionEstablecimientoController;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Models\DotacionDocenteExclusion;
use App\Models\Establecimiento;
use App\Models\User;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionSituacionesDirectivoConsultaTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutExceptionHandling([\Symfony\Component\HttpKernel\Exception\HttpException::class]);
        $this->resetSchemaCaches();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        // El bloqueo MySQL del padrón no forma parte de estas pruebas SQLite.
        $this->withoutMiddleware(CoordinarEscrituraPadron::class);
        Schema::table('users', fn (Blueprint $table) => $table->integer('establecimiento_id')->nullable());
        Schema::create('modules', function (Blueprint $table): void {
            $table->id(); $table->string('key'); $table->string('name')->nullable();
            $table->string('section')->nullable(); $table->string('icon')->nullable();
            $table->integer('sort')->nullable(); $table->timestamps();
        });
        Schema::create('module_role', function (Blueprint $table): void {
            $table->integer('module_id'); $table->integer('role_id'); $table->timestamps();
        });
        DB::table('modules')->insert(['id' => 1, 'key' => 'admin.dotacion-establecimiento']);
        DB::table('module_role')->insert(['module_id' => 1, 'role_id' => 4]);
        DB::table('roles')->insert([
            ['id' => 4, 'name' => 'funcionario_directivo_estab', 'guard_name' => 'web'],
            ['id' => 5, 'name' => 'coordinador_uatp', 'guard_name' => 'web'],
            ['id' => 6, 'name' => 'coordinador_gdp', 'guard_name' => 'web'],
            ['id' => 7, 'name' => 'supervisor_plani', 'guard_name' => 'web'],
        ]);
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id(); $table->integer('rbd'); $table->string('nombre_establecimiento');
            $table->boolean('sala_cuna')->default(false); $table->timestamps();
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99001, 'nombre_establecimiento' => 'Establecimiento de prueba A'],
            ['id' => 2, 'rbd' => 99002, 'nombre_establecimiento' => 'Establecimiento de prueba B'],
        ]);
        foreach ([
            '2026_08_24_090000_create_dotacion_docente_exclusiones_table.php',
            '2026_09_14_160000_add_continuidad_to_dotacion_docente_exclusiones.php',
            '2026_09_14_170000_add_conservar_horas_to_dotacion_docente_exclusiones.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    protected function tearDown(): void
    {
        $this->resetSchemaCaches();
        parent::tearDown();
    }

    public function test_directivo_no_puede_crear_actualizar_ni_eliminar_mediante_peticion_http(): void
    {
        $user = $this->testUser(1, 4);
        $user->forceFill(['establecimiento_id' => 1])->save();
        $id = DB::table('dotacion_docente_exclusiones')->insertGetId($this->situacion());
        $antes = DB::table('dotacion_docente_exclusiones')->get()->toArray();
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $url = route('admin.dotacion-establecimiento.docentes.exclusiones.store', 1);
        $this->post($url, $this->datosFormulario(['docente_rut' => '99000002K']))->assertForbidden();
        $this->post($url, $this->datosFormulario())->assertForbidden();
        $this->delete(route('admin.dotacion-establecimiento.docentes.exclusiones.destroy', [1, $id]))->assertForbidden();
        $this->assertEquals($antes, DB::table('dotacion_docente_exclusiones')->get()->toArray());
    }

    public function test_cuenta_administradora_con_rol_activo_directivo_tampoco_puede_escribir(): void
    {
        $user = $this->testUser(1, 3);
        $user->forceFill(['establecimiento_id' => 1])->save();
        DB::table('model_has_roles')->insert(['role_id' => 4, 'model_type' => User::class, 'model_id' => $user->id]);
        $id = DB::table('dotacion_docente_exclusiones')->insertGetId($this->situacion());
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->post(route('admin.dotacion-establecimiento.docentes.exclusiones.store', 1), $this->datosFormulario())->assertForbidden();
        $this->delete(route('admin.dotacion-establecimiento.docentes.exclusiones.destroy', [1, $id]))->assertForbidden();
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['id' => $id, 'motivo' => 'fuero_maternal', 'horas' => 2]);
    }

    public function test_los_cuatro_roles_de_gestion_conservan_creacion_actualizacion_y_eliminacion(): void
    {
        $this->crearPadron();
        $controller = app(DotacionDocenteExclusionController::class);
        $ee = Establecimiento::findOrFail(1);
        foreach ([3 => 'admin', 5 => 'coordinador_uatp', 6 => 'coordinador_gdp', 7 => 'supervisor_plani'] as $roleId => $role) {
            $user = $this->testUser($roleId, $roleId);
            session(['active_role' => $role]);
            $request = $this->request($user, $this->datosFormulario());
            $this->assertTrue($controller->store($request, $ee)->isRedirect());
            $this->assertDatabaseHas('dotacion_docente_exclusiones', ['anio' => 2027, 'motivo' => 'horas_gremiales', 'horas' => 4, 'created_by' => $user->id]);
            $request->merge(['motivo' => 'horas_lactancia', 'horas_necesarias' => 42, 'horas' => 2]);
            $this->assertTrue($controller->store($request, $ee)->isRedirect());
            $this->assertDatabaseHas('dotacion_docente_exclusiones', ['anio' => 2027, 'motivo' => 'horas_lactancia', 'horas' => 2, 'updated_by' => $user->id]);
            $this->assertTrue($controller->destroy($request, $ee, DotacionDocenteExclusion::query()->sole())->isRedirect());
            $this->assertDatabaseCount('dotacion_docente_exclusiones', 0);
        }
    }

    public function test_consulta_separa_anios_y_establecimientos_normaliza_rut_y_no_modifica_registros(): void
    {
        DB::table('dotacion_docente_exclusiones')->insert([
            $this->situacion(['docente_rut_normalizado' => '99.000.001-k']),
            $this->situacion(['anio' => 2027, 'motivo' => 'horas_gremiales', 'horas' => 4]),
            $this->situacion(['establecimiento_id' => 2, 'motivo' => 'traslado']),
            $this->situacion(['anio' => 2025, 'motivo' => 'renuncia_voluntaria']),
        ]);
        $antes = DB::table('dotacion_docente_exclusiones')->orderBy('id')->get()->toArray();
        $this->assertSame('fuero_maternal', DotacionDocenteExclusion::situacionesPorRut(1, 2026)['99000001K']->motivo);
        $this->assertSame('horas_gremiales', DotacionDocenteExclusion::situacionesPorRut(1, 2027)['99000001K']->motivo);
        $this->assertSame([], DotacionDocenteExclusion::situacionesPorRut(1, 2028));
        $this->assertEquals($antes, DB::table('dotacion_docente_exclusiones')->orderBy('id')->get()->toArray());
    }

    public function test_detalle_envia_situacion_anterior_y_actual_al_directivo_sin_permiso_de_edicion(): void
    {
        $this->crearPadron();
        DB::table('dotacion_docente_exclusiones')->insert([
            $this->situacion(), $this->situacion(['anio' => 2027, 'motivo' => 'horas_gremiales', 'horas' => 4]),
        ]);
        $user = $this->testUser(1, 4);
        $user->forceFill(['establecimiento_id' => 1])->save();
        session(['active_role' => 'funcionario_directivo_estab']);
        $request = Request::create('/dotacion', 'GET', ['anio' => 2027, 'tab' => 'docentes']);
        $request->setUserResolver(fn () => $user);
        $data = app(DotacionEstablecimientoController::class)->show($request, Establecimiento::findOrFail(1))->getData();
        $this->assertFalse($data['canManageDocenteExclusiones']);
        $this->assertSame('fuero_maternal', $data['situacionesDocentesAnteriores']['99000001K']->motivo);
        $this->assertSame('horas_gremiales', $data['docentes']->sole()['exclusion_docente']['motivo']);
        $html = view('admin.dotacion-establecimiento.partials._docentes', $data)->render();
        $this->assertStringContainsString('Año anterior · 2026', $html);
        $this->assertStringContainsString('Situación actual · 2027', $html);
        $this->assertStringContainsString('Fuero maternal', $html);
        $this->assertStringContainsString('Horas gremiales', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('Seleccione una situación', $html);
    }

    public function test_directivo_no_consulta_otro_establecimiento(): void
    {
        $user = $this->testUser(1, 4);
        $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab'])
            ->get(route('admin.dotacion-establecimiento.show', [2, 'anio' => 2027, 'tab' => 'docentes']))->assertForbidden();
    }

    public function test_vista_no_inventa_situacion_actual_a_partir_del_anio_anterior_y_no_admite_formulario_directivo(): void
    {
        $anterior = new DotacionDocenteExclusion($this->situacion());
        $html = view('admin.dotacion-establecimiento.partials._docentes', [
            'anio' => 2027, 'activeRole' => 'funcionario_directivo_estab',
            // Defensa visual aun si otro consumidor pasa la bandera equivocada.
            'canManageDocenteExclusiones' => true, 'docenteExclusionesTableReady' => true,
            'situacionesDocentesAnteriores' => ['99000001K' => $anterior],
            'docentes' => collect([$this->docenteVista()]),
        ])->render();
        $this->assertStringContainsString('Fuero maternal', $html);
        $this->assertSame(1, substr_count($html, 'Sin situación registrada.'));
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('Guardar situación', $html);
    }

    public function test_consulta_sin_tabla_y_sin_columnas_opcionales_conserva_compatibilidad(): void
    {
        Schema::table('dotacion_docente_exclusiones', fn (Blueprint $table) =>
            $table->dropColumn(['considerar_dotacion_siguiente', 'conservar_horas_necesarias']));
        $datos = $this->situacion();
        unset($datos['considerar_dotacion_siguiente'], $datos['conservar_horas_necesarias']);
        DB::table('dotacion_docente_exclusiones')->insert($datos);
        $this->assertSame('fuero_maternal', DotacionDocenteExclusion::situacionesPorRut(1, 2026)['99000001K']->motivo);
        $html = view('admin.dotacion-establecimiento.partials._docentes', [
            'anio' => 2027, 'activeRole' => 'funcionario_directivo_estab',
            'situacionesDocentesAnteriores' => DotacionDocenteExclusion::situacionesPorRut(1, 2026),
            'docentes' => collect([$this->docenteVista()]),
        ])->render();
        $this->assertStringNotContainsString('Continuidad en', $html);
        Schema::drop('dotacion_docente_exclusiones'); // Sólo SQLite en memoria.
        $this->assertSame([], DotacionDocenteExclusion::situacionesPorRut(1, 2026));
    }

    private function request(User $user, array $data): Request
    {
        $request = Request::create('/situacion', 'POST', $data);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function datosFormulario(array $cambios = []): array
    {
        return array_replace(['anio' => 2027, 'docente_rut' => '99.000.001-k',
            'motivo' => 'horas_gremiales', 'horas_necesarias' => 40, 'horas' => 4], $cambios);
    }

    private function situacion(array $cambios = []): array
    {
        return array_replace(['establecimiento_id' => 1, 'anio' => 2026, 'docente_rut' => '99.000.001-k',
            'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente de prueba',
            'motivo' => 'fuero_maternal', 'horas' => 2, 'considerar_dotacion_siguiente' => true,
            'conservar_horas_necesarias' => false, 'created_at' => now(), 'updated_at' => now()], $cambios);
    }

    private function docenteVista(): array
    {
        return ['rut' => '99000001-K', 'nombre' => 'Docente de prueba', 'titulo' => 'Pedagogía en Educación Básica',
            'horas_contrato' => 44, 'horas_aula' => 0, 'horas_asignadas_total' => 0, 'diferencia' => 44,
            'funcion' => 'DOCENTE', 'estamento' => 'DOCENTE', 'niveles_declarados' => 'Básica',
            'tipo_contrato' => 'PLANTA', 'financiamiento' => 'General', 'mes' => 8, 'anio' => 2026];
    }

    private function crearPadron(): void
    {
        foreach ([
            '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('reemplazos_personal', function (Blueprint $table): void {
            $table->id(); $table->integer('establecimiento_id'); $table->string('rut'); $table->string('nombre');
            $table->integer('anio'); $table->integer('mes'); $table->decimal('jornada', 8, 2);
            $table->decimal('jornada_basica', 8, 2)->nullable(); $table->decimal('jornada_media', 8, 2)->nullable();
            foreach (['tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'row_hash'] as $campo) {
                $table->string($campo)->nullable();
            }
            $table->timestamps();
        });
        DB::table('reemplazos_personal')->insert(['establecimiento_id' => 1, 'rut' => '99000001K',
            'nombre' => 'Docente de prueba', 'anio' => 2026, 'mes' => 8, 'jornada' => 44,
            'tipocontrato' => 'PLANTA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'prueba-consulta']);
        $this->resetSchemaCaches();
    }

    private function resetSchemaCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $nombre) {
                (new ReflectionProperty($class, $nombre))->setValue(null, []);
            }
        }
    }
}
