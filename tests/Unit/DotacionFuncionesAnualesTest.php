<?php

namespace Tests\Unit;

use App\Models\Establecimiento;
use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionFuncionesCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DotacionFuncionesAnualesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->integer('rbd');
            $table->string('nombre_establecimiento');
        });
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('nombre');
        });
        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id();
            $table->integer('establecimiento_id');
            $table->integer('curso_id');
            $table->integer('anio');
            $table->string('letra')->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('matricula')->default(0);
        });
        Schema::create('establecimiento_curso_pie', function (Blueprint $table): void {
            $table->id();
            $table->integer('establecimiento_id');
            $table->integer('establecimiento_curso_id');
            $table->integer('anio');
            $table->integer('total_pie')->default(0);
        });
        Schema::create('dotacion_establecimiento_configuraciones', function (Blueprint $table): void {
            $table->id();
            $table->integer('establecimiento_id');
            $table->integer('anio');
            $table->boolean('director_adp')->default(false);
        });
        Schema::create('dotacion_funciones_reglas', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('categoria');
            $table->string('nombre');
            $table->string('tipo_regla');
            $table->integer('horas_fijas')->nullable();
            $table->integer('horas_minimas')->nullable();
            $table->integer('horas_maximas')->nullable();
            $table->integer('umbral_matricula')->nullable();
            $table->integer('horas_bajo_umbral')->nullable();
            $table->integer('horas_sobre_umbral')->nullable();
            $table->boolean('permite_multiples')->default(false);
            $table->boolean('declarable')->default(false);
            $table->boolean('obligatoria')->default(false);
            $table->boolean('requiere_validacion')->default(true);
            $table->text('fundamento')->nullable();
            $table->boolean('vigente')->default(true);
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->integer('anio');
            $table->integer('establecimiento_id');
            $table->string('docente_rut');
            $table->string('docente_rut_normalizado')->nullable();
            $table->string('docente_nombre');
            $table->string('estamento_cobertura')->default('docente');
            $table->string('tipo_asignacion');
            $table->string('subtipo_asignacion')->nullable();
            $table->string('subvencion')->nullable();
            $table->string('necesidad_key')->nullable();
            $table->string('asignatura_nombre')->nullable();
            $table->integer('dotacion_funcion_id')->nullable();
            $table->integer('dotacion_funcion_regla_id')->nullable();
            $table->decimal('horas_plan_pedagogicas', 8, 2)->nullable();
            $table->decimal('horas_contrato', 8, 2);
            $table->string('estado')->default('activa');
        });

        DB::table('establecimientos')->insert([
            'id' => 1,
            'rbd' => 99999,
            'nombre_establecimiento' => 'Establecimiento sintético',
        ]);
        DB::table('dotacion_funciones_reglas')->insert([
            [
                'codigo' => 'pise',
                'categoria' => 'planes_programas',
                'nombre' => 'PISE',
                'tipo_regla' => 'fija',
                'horas_fijas' => 3,
                'umbral_matricula' => null,
                'horas_bajo_umbral' => null,
                'horas_sobre_umbral' => null,
                'declarable' => false,
                'vigente' => true,
            ],
            [
                'codigo' => 'transicion_educativa',
                'categoria' => 'planes_programas',
                'nombre' => 'Transición educativa',
                'tipo_regla' => 'nt1_nt2',
                'horas_fijas' => null,
                'umbral_matricula' => 40,
                'horas_bajo_umbral' => 20,
                'horas_sobre_umbral' => 44,
                'declarable' => false,
                'vigente' => true,
            ],
        ]);
    }

    public function test_transicion_educativa_se_excluye_de_todos_los_anios_y_calculos(): void
    {
        $establecimiento = Establecimiento::findOrFail(1);

        $funciones2026 = DotacionFuncionesCalculator::sugerencias($establecimiento, 2026);
        $funciones2027 = DotacionFuncionesCalculator::sugerencias($establecimiento, 2027);

        $this->assertSame(['pise'], $funciones2026->pluck('codigo')->all());
        $this->assertSame(['pise'], $funciones2027->pluck('codigo')->all());

        $transicion = \App\Models\DotacionFuncionRegla::query()->where('codigo', 'transicion_educativa')->firstOrFail();
        $this->assertNull(DotacionFuncionesCalculator::calcularHorasRegla($transicion, ['matricula_nt1_nt2' => 40]));
    }

    public function test_director_adp_solo_se_calcula_cuando_esta_habilitado_en_el_establecimiento_y_anio(): void
    {
        DB::table('dotacion_funciones_reglas')->insert([
            'codigo' => 'director_adp',
            'categoria' => 'directiva',
            'nombre' => 'Director(a) ADP',
            'tipo_regla' => 'director_adp',
            'horas_fijas' => 44,
            'umbral_matricula' => null,
            'horas_bajo_umbral' => null,
            'horas_sobre_umbral' => null,
            'declarable' => false,
            'vigente' => true,
        ]);

        $establecimiento = Establecimiento::findOrFail(1);
        $this->assertFalse(DotacionFuncionesCalculator::sugerencias($establecimiento, 2026)->contains('codigo', 'director_adp'));

        DB::table('dotacion_establecimiento_configuraciones')->insert([
            'establecimiento_id' => $establecimiento->id,
            'anio' => 2026,
            'director_adp' => true,
        ]);

        $directorAdp = DotacionFuncionesCalculator::sugerencias($establecimiento, 2026)
            ->firstWhere('codigo', 'director_adp');

        $this->assertNotNull($directorAdp);
        $this->assertSame('directiva', $directorAdp['categoria']);
        $this->assertSame(44, $directorAdp['horas_sugeridas']);
    }

    public function test_director_adp_habilitado_agrega_plaza_automatica_por_asumir_de_44_horas(): void
    {
        $schemaCache = new \ReflectionProperty(DotacionAsignacionCalculator::class, 'schemaTableCache');
        $schemaCache->setValue([]);

        $establecimiento = Establecimiento::findOrFail(1);
        $resultado = DotacionAsignacionCalculator::build($establecimiento, 2026, collect(), [], [
            'directiva' => [
                'label' => 'Funciones directivas',
                'items' => [[
                    'codigo' => 'director_adp',
                    'nombre' => 'Director(a) ADP',
                    'horas' => 44,
                    'dotacion_funcion_regla_id' => 73,
                    'origen' => 'Función normativa',
                ]],
            ],
        ]);

        $necesidad = $resultado['necesidades']['funciones']->sole();
        $asignacion = $necesidad['asignaciones']->sole();

        $this->assertTrue($necesidad['asignacion_automatica']);
        $this->assertSame('pendiente', $necesidad['estado']['key']);
        $this->assertSame(44.0, $necesidad['horas_contrato_asignadas']);
        $this->assertSame(0.0, $necesidad['horas_contrato_asignadas_calculo']);
        $this->assertTrue($asignacion->asignacion_automatica);
        $this->assertSame('Docente Directivo por asumir', $asignacion->docente_nombre);
        $this->assertSame(44.0, $asignacion->horas_contrato);
        $this->assertNull($asignacion->id);
    }

    public function test_asignacion_real_de_director_adp_reemplaza_la_plaza_automatica(): void
    {
        $schemaCache = new \ReflectionProperty(DotacionAsignacionCalculator::class, 'schemaTableCache');
        $schemaCache->setValue([]);
        DB::table('dotacion_docente_asignaciones')->insert([
            'anio' => 2026,
            'establecimiento_id' => 1,
            'docente_rut' => '11.111.111-1',
            'docente_rut_normalizado' => '111111111',
            'docente_nombre' => 'Directivo asignado',
            'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'funcion_directiva',
            'subtipo_asignacion' => 'directiva',
            'subvencion' => 'General',
            'asignatura_nombre' => 'Director(a) ADP',
            'dotacion_funcion_regla_id' => 73,
            'horas_contrato' => 44,
            'estado' => 'activa',
        ]);

        $resultado = DotacionAsignacionCalculator::build(Establecimiento::findOrFail(1), 2026, collect(), [], [
            'directiva' => [
                'label' => 'Funciones directivas',
                'items' => [[
                    'codigo' => 'director_adp',
                    'nombre' => 'Director(a) ADP',
                    'horas' => 44,
                    'dotacion_funcion_regla_id' => 73,
                ]],
            ],
        ]);

        $necesidad = $resultado['necesidades']['funciones']->sole();
        $asignacion = $necesidad['asignaciones']->sole();

        $this->assertFalse($necesidad['asignacion_automatica']);
        $this->assertSame('Directivo asignado', $asignacion->docente_nombre);
        $this->assertSame(44.0, $resultado['resumen']['horas_asignadas']);
    }

    public function test_directiva_y_plan_normativo_solo_son_necesarios_al_asignarlos_a_docente_real(): void
    {
        $schemaCache = new \ReflectionProperty(DotacionAsignacionCalculator::class, 'schemaTableCache');
        $schemaCache->setValue([]);
        $piseReglaId = (int) DB::table('dotacion_funciones_reglas')->where('codigo', 'pise')->value('id');
        $bloques = [
            'directiva' => [
                'label' => 'Funciones directivas',
                'items' => [[
                    'codigo' => 'director_adp',
                    'nombre' => 'Director(a) ADP',
                    'horas' => 44,
                    'dotacion_funcion_regla_id' => 73,
                ]],
            ],
            'planes_programas' => [
                'label' => 'Planes normativos',
                'items' => [[
                    'codigo' => 'pise',
                    'nombre' => 'PISE',
                    'horas' => 3,
                    'dotacion_funcion_regla_id' => $piseReglaId,
                ]],
            ],
        ];

        $sinDocente = DotacionAsignacionCalculator::build(
            Establecimiento::findOrFail(1),
            2026,
            collect(),
            [],
            $bloques
        );
        $necesidadesSinDocente = $sinDocente['necesidades']['funciones']->keyBy('titulo');

        $this->assertSame(44.0, $sinDocente['resumen']['horas_requeridas']);
        $this->assertTrue($necesidadesSinDocente['Director(a) ADP']['necesidad_activada_por_docente']);
        $this->assertSame(44.0, $necesidadesSinDocente['Director(a) ADP']['horas_contrato_requeridas_calculo']);
        $this->assertSame(0.0, $necesidadesSinDocente['PISE']['horas_contrato_requeridas_calculo']);

        $bloquesContrato = [
            'directiva' => ['automaticas' => 44, 'declaradas' => 0, 'total' => 44, 'items' => $bloques['directiva']['items']],
            'planes_programas' => ['automaticas' => 3, 'declaradas' => 0, 'total' => 3, 'items' => $bloques['planes_programas']['items']],
        ];
        $ajustarBloques = new \ReflectionMethod(
            \App\Support\DotacionEstablecimientoCalculator::class,
            'considerarNormativasSoloConDocenteAsignado'
        );
        $bloquesSinDocente = $ajustarBloques->invoke(
            null,
            $bloquesContrato,
            $sinDocente['necesidades']['funciones']
        );
        $this->assertSame(44.0, $bloquesSinDocente['directiva']['total']);
        $this->assertSame(0.0, $bloquesSinDocente['planes_programas']['total']);

        $proyeccionDirectorAdp = \App\Support\DotacionProyeccionCalculator::build([
            'resumen' => ['horas_dotacion_funciones_normativas' => 44],
            'docentes' => [],
            'asignacion' => [
                'asignaciones' => $sinDocente['asignaciones'],
                'necesidades' => ['funciones' => $sinDocente['necesidades']['funciones']],
            ],
        ], 2026, []);
        $coberturaDirectorAdp = $proyeccionDirectorAdp['coberturas']['funciones'][0];
        $this->assertSame(44.0, $proyeccionDirectorAdp['necesarias']['funciones_normativas']);
        $this->assertSame(44.0, $proyeccionDirectorAdp['brechas']['aula']);
        $this->assertSame(44.0, $coberturaDirectorAdp['requeridas']);
        $this->assertSame(0.0, $coberturaDirectorAdp['asignadas_base']);

        DB::table('dotacion_docente_asignaciones')->insert([
            [
                'anio' => 2026,
                'establecimiento_id' => 1,
                'docente_rut' => '11.111.111-1',
                'docente_rut_normalizado' => '111111111',
                'docente_nombre' => 'Directivo asignado',
                'estamento_cobertura' => 'docente',
                'tipo_asignacion' => 'funcion_directiva',
                'subtipo_asignacion' => 'directiva',
                'subvencion' => 'General',
                'asignatura_nombre' => 'Director(a) ADP',
                'dotacion_funcion_regla_id' => 73,
                'horas_contrato' => 44,
                'estado' => 'activa',
            ],
            [
                'anio' => 2026,
                'establecimiento_id' => 1,
                'docente_rut' => '22.222.222-2',
                'docente_rut_normalizado' => '222222222',
                'docente_nombre' => 'Docente PISE',
                'estamento_cobertura' => 'docente',
                'tipo_asignacion' => 'plan_normativo',
                'subtipo_asignacion' => 'planes_programas',
                'subvencion' => 'General',
                'asignatura_nombre' => 'PISE',
                'dotacion_funcion_regla_id' => $piseReglaId,
                'horas_contrato' => 3,
                'estado' => 'activa',
            ],
        ]);

        $conDocente = DotacionAsignacionCalculator::build(
            Establecimiento::findOrFail(1),
            2026,
            collect(),
            [],
            $bloques
        );

        $this->assertSame(47.0, $conDocente['resumen']['horas_requeridas']);
        $this->assertSame(47.0, $conDocente['resumen']['horas_asignadas']);
        $this->assertTrue($conDocente['necesidades']['funciones']->firstWhere('titulo', 'Director(a) ADP')['necesidad_activada_por_docente']);
        $this->assertSame(3.0, $conDocente['necesidades']['funciones']->firstWhere('titulo', 'PISE')['horas_contrato_requeridas_calculo']);

        $bloquesConDocente = $ajustarBloques->invoke(
            null,
            $bloquesContrato,
            $conDocente['necesidades']['funciones']
        );
        $this->assertSame(44.0, $bloquesConDocente['directiva']['total']);
        $this->assertSame(3.0, $bloquesConDocente['planes_programas']['total']);
    }

    public function test_asignacion_de_director_adp_exige_docente_44_horas_y_habilitacion_anual(): void
    {
        $reglaId = DB::table('dotacion_funciones_reglas')->insertGetId([
            'codigo' => 'director_adp',
            'categoria' => 'directiva',
            'nombre' => 'Director(a) ADP',
            'tipo_regla' => 'director_adp',
            'horas_fijas' => 44,
            'declarable' => false,
            'vigente' => true,
        ]);
        DB::table('dotacion_establecimiento_configuraciones')->insert([
            'establecimiento_id' => 1,
            'anio' => 2026,
            'director_adp' => true,
        ]);

        $controller = app(DotacionAsignacionController::class);
        $method = new \ReflectionMethod(DotacionAsignacionController::class, 'validateDirectorAdpAssignment');
        $data = [
            'tipo_asignacion' => 'funcion_directiva',
            'estamento_cobertura' => 'docente',
            'dotacion_funcion_regla_id' => $reglaId,
            'horas_contrato' => 44,
        ];

        $this->assertNull($method->invoke($controller, Establecimiento::findOrFail(1), 2026, $data));

        try {
            $method->invoke($controller, Establecimiento::findOrFail(1), 2026, [...$data, 'horas_contrato' => 43]);
            $this->fail('La asignación Director(a) ADP debe rechazar contratos distintos de 44 horas.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('horas_contrato', $exception->errors());
        }

        try {
            $method->invoke($controller, Establecimiento::findOrFail(1), 2026, [...$data, 'estamento_cobertura' => 'asistente']);
            $this->fail('La asignación Director(a) ADP debe rechazar coberturas que no sean docentes.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('estamento_cobertura', $exception->errors());
        }

        DB::table('dotacion_establecimiento_configuraciones')
            ->where('establecimiento_id', 1)
            ->where('anio', 2026)
            ->update(['director_adp' => false]);

        try {
            $method->invoke($controller, Establecimiento::findOrFail(1), 2026, $data);
            $this->fail('La asignación Director(a) ADP debe requerir que el cargo esté habilitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('dotacion_funcion_regla_id', $exception->errors());
        }
    }
}
