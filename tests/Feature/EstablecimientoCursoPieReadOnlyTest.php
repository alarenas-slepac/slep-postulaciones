<?php

namespace Tests\Feature;

use App\Models\EstablecimientoCursoPie;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class EstablecimientoCursoPieReadOnlyTest extends IsolatedSecurityTestCase
{
    private const URL = '/admin/establecimiento-curso-pie';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutExceptionHandling([
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\HttpException::class,
        ]);
        Schema::table('users', fn (Blueprint $table) => $table->unsignedBigInteger('establecimiento_id')->nullable());
        DB::table('roles')->insert([
            ['id' => 4, 'name' => 'funcionario_directivo_estab', 'guard_name' => 'web'],
            ['id' => 5, 'name' => 'coordinador_uatp', 'guard_name' => 'web'],
            ['id' => 6, 'name' => 'supervisor_plani', 'guard_name' => 'web'],
            ['id' => 7, 'name' => 'coordinador_gdp', 'guard_name' => 'web'],
        ]);
        Schema::create('modules', function (Blueprint $table): void {
            $table->id(); $table->string('key'); $table->string('name'); $table->string('section');
            $table->string('icon')->nullable(); $table->integer('sort'); $table->timestamps();
        });
        Schema::create('module_role', function (Blueprint $table): void {
            $table->unsignedBigInteger('module_id'); $table->unsignedBigInteger('role_id');
        });
        foreach ([
            '2026_01_23_000001_create_establecimientos_table.php',
            '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_171000_add_calculo_horas_to_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $module = DB::table('modules')->where('key', 'admin.establecimiento-curso-pie')->value('id');
        DB::table('module_role')->insert(['module_id' => $module, 'role_id' => 6]);
        Schema::create('alumnos_prioritarios_porcentajes', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('establecimiento_id'); $table->integer('anio');
            $table->decimal('porcentaje', 6, 2);
        });
        foreach ([1 => 'Escuela propia sintética', 2 => 'Escuela ajena sintética'] as $id => $nombre) {
            DB::table('establecimientos')->insert([
                'id' => $id, 'rbd' => 99990 + $id, 'cod_estab' => 'SYNTHETIC-'.$id,
                'nombre_establecimiento' => $nombre, 'comuna' => 'Comuna sintética',
            ]);
        }
        $cursoId = DB::table('cursos')->where('codigo', '1B')->value('id');
        foreach ([[1, 1, 'A'], [2, 1, 'B'], [3, 2, 'A']] as [$id, $ee, $letra]) {
            DB::table('establecimiento_cursos')->insert([
                'id' => $id, 'establecimiento_id' => $ee, 'rbd' => 99990 + $ee, 'curso_id' => $cursoId,
                'anio' => 2027, 'letra' => $letra, 'nombre_seccion' => '1° Básico '.$letra,
                'matricula' => 30, 'regimen_jec' => 'CON JEC', 'activo' => true,
            ]);
            if ($id !== 2) {
                EstablecimientoCursoPie::create([
                    'id' => $id, 'establecimiento_id' => $ee, 'establecimiento_curso_id' => $id,
                    'curso_id' => $cursoId, 'anio' => 2027, 'rbd' => 99990 + $ee,
                    'necesidades_transitorias' => 5, 'necesidades_permanentes' => 0, 'total_pie' => 5,
                    'estado' => 'validado', 'observacion' => 'Antecedentes sintéticos a conservar',
                ]);
            }
        }
    }

    private function loginAs(int $role): User
    {
        $user = $this->testUser(role: $role);
        $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => DB::table('roles')->where('id', $role)->value('name')]);

        return $user;
    }

    private function snapshot(): string
    {
        return DB::table('establecimiento_curso_pie')->orderBy('id')->get()->toJson();
    }

    private function payload(int $curso = 1): array
    {
        return [
            'establecimiento_curso_id' => $curso, 'anio' => 2027,
            'necesidades_transitorias' => 6, 'necesidades_permanentes' => 0,
            'observacion' => 'Cambio sintético', 'estado' => 'validado',
        ];
    }

    public function test_directivo_consulta_su_establecimiento_sin_acciones_de_escritura(): void
    {
        $this->loginAs(4);
        $antes = $this->snapshot();
        $listado = $this->get(self::URL.'?anio=2027&establecimiento_id=2')->assertOk()
            ->assertViewHas('canEditPie', false)->assertViewHas('establecimientoId', '1')
            ->assertSee('Escuela propia sintética')->assertDontSee('Escuela ajena sintética');
        foreach (['Registrar PIE', 'Carga masiva', 'Traspasar PIE', 'Editar', '>Eliminar<'] as $accion) {
            $listado->assertDontSee($accion, false);
        }
        $this->get(self::URL.'/1')->assertOk()->assertViewHas('canEditPie', false)
            ->assertSee('Antecedentes sintéticos a conservar')->assertDontSee('> Editar', false);
        $ajeno = DB::table('establecimiento_curso_pie')->where('establecimiento_id', 2)->value('id');
        $this->get(self::URL.'/'.$ajeno)->assertForbidden();
        $this->assertSame($antes, $this->snapshot());
    }

    public static function accionesDeEscritura(): array
    {
        return [
            ['GET', '/create'], ['GET', '/1/edit'], ['GET', '/import'], ['GET', '/template'],
            ['POST', ''], ['PUT', '/1'], ['PATCH', '/1'], ['DELETE', '/1'],
            ['POST', '/import'], ['POST', '/transfer-2026-to-2027'],
        ];
    }

    #[DataProvider('accionesDeEscritura')]
    public function test_directivo_no_puede_escribir_mediante_peticiones_directas(string $method, string $path): void
    {
        $this->loginAs(4);
        $antes = $this->snapshot();
        $this->call($method, self::URL.$path, $this->payload())->assertForbidden();
        $this->assertSame($antes, $this->snapshot());
    }

    public static function editores(): array
    {
        return [[3], [5], [6]];
    }

    #[DataProvider('editores')]
    public function test_administrador_uatp_y_supervisor_conservan_creacion_y_edicion(int $role): void
    {
        $this->loginAs($role);
        $this->get(self::URL.'?anio=2027')->assertOk()->assertViewHas('canEditPie', true);
        $this->get(self::URL.'/1/edit')->assertOk();
        $this->put(self::URL.'/1', $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(6, EstablecimientoCursoPie::findOrFail(1)->total_pie);
        $this->post(self::URL, $this->payload(2))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, EstablecimientoCursoPie::count());
    }

    public function test_gdp_conserva_solo_consulta(): void
    {
        $this->loginAs(7);
        $antes = $this->snapshot();
        $this->get(self::URL.'?anio=2027')->assertOk()->assertViewHas('canEditPie', false);
        $this->put(self::URL.'/1', $this->payload())->assertForbidden();
        $this->assertSame($antes, $this->snapshot());
    }

    public function test_se_respeta_el_rol_directivo_activo_aunque_la_cuenta_tenga_ademas_administrador(): void
    {
        $user = $this->loginAs(4);
        $user->assignRole('admin');
        $antes = $this->snapshot();
        $this->post(self::URL, $this->payload(2))->assertForbidden();
        $this->assertSame($antes, $this->snapshot());
        $this->withSession(['active_role' => 'admin'])->post(self::URL, $this->payload(2))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, EstablecimientoCursoPie::count());
    }
}
