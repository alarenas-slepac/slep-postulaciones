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
        $this->assertSame('cubierta', $necesidad['estado']['key']);
        $this->assertSame(44.0, $necesidad['horas_contrato_asignadas']);
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
