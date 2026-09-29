<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DotacionProceso2027PlanConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->integer('rbd');
            $table->string('nombre_establecimiento');
        });
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('planes_estudio', function (Blueprint $table): void {
            $table->id();
            $table->decimal('horas_semanales_libre_disposicion', 8, 2)->nullable();
        });
        Schema::create('planes_estudio_bloques', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_estudio_id');
            $table->string('tipo_bloque');
            $table->decimal('horas_semanales', 8, 2);
            $table->boolean('permite_asignaturas_establecimiento')->default(false);
            $table->boolean('permite_asignaturas_personalizadas')->default(false);
            $table->boolean('activo')->default(true);
        });
        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('curso_id');
            $table->unsignedBigInteger('plan_estudio_id')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->string('letra')->nullable();
            $table->string('nombre_seccion')->nullable();
            $table->integer('matricula')->default(0);
            $table->boolean('activo')->default(true);
        });
        Schema::create('establecimiento_planes_estudio', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('establecimiento_curso_id');
            $table->unsignedBigInteger('plan_estudio_id');
            $table->unsignedBigInteger('curso_id');
            $table->unsignedSmallInteger('anio');
            $table->string('estado');
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_plan_estudio_id');
            $table->unsignedBigInteger('plan_estudio_bloque_id');
            $table->decimal('horas_semanales', 8, 2);
        });
        Schema::create('dotacion_proceso_2027_configuraciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedSmallInteger('anio');
            $table->string('decision_combinacion')->nullable();
            $table->decimal('max_horas_bloque_1', 8, 2)->nullable();
            $table->decimal('max_horas_bloque_2', 8, 2)->nullable();
            $table->decimal('max_horas_bloque_3', 8, 2)->nullable();
            $table->json('funciones_normativas')->nullable();
        });

        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento de prueba']);
        DB::table('cursos')->insert(['id' => 1, 'nombre' => 'NT1']);
        DB::table('planes_estudio')->insert(['id' => 1, 'horas_semanales_libre_disposicion' => 6]);
        DB::table('planes_estudio_bloques')->insert([
            'id' => 1,
            'plan_estudio_id' => 1,
            'tipo_bloque' => 'libre_disposicion',
            'horas_semanales' => 6,
            'permite_asignaturas_establecimiento' => true,
            'permite_asignaturas_personalizadas' => true,
            'activo' => true,
        ]);
        DB::table('establecimiento_cursos')->insert([
            'id' => 1,
            'establecimiento_id' => 1,
            'curso_id' => 1,
            'plan_estudio_id' => 1,
            'anio' => 2027,
            'letra' => 'A',
            'nombre_seccion' => 'NT1 A',
            'matricula' => 20,
            'activo' => true,
        ]);
        DB::table('establecimiento_planes_estudio')->insert([
            'id' => 1,
            'establecimiento_id' => 1,
            'establecimiento_curso_id' => 1,
            'plan_estudio_id' => 1,
            'curso_id' => 1,
            'anio' => 2027,
            'estado' => 'enviado',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dotacion_proceso_2027_configuraciones');
        Schema::dropIfExists('establecimiento_planes_estudio_asignaturas');
        Schema::dropIfExists('establecimiento_planes_estudio');
        Schema::dropIfExists('establecimiento_cursos');
        Schema::dropIfExists('planes_estudio_bloques');
        Schema::dropIfExists('planes_estudio');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('establecimientos');

        parent::tearDown();
    }

    public function test_exige_las_horas_de_libre_disposicion_antes_de_completar_planes(): void
    {
        $establecimiento = Establecimiento::findOrFail(1);
        $primero = DotacionProceso2027Calculator::resumen($establecimiento, 2027, $this->data());

        $this->assertFalse($primero['pasos']['planes']['completo']);
        $this->assertSame(1, $primero['estado_planes']['libre_disposicion_pendiente']);

        DB::table('establecimiento_planes_estudio_asignaturas')->insert([
            'establecimiento_plan_estudio_id' => 1,
            'plan_estudio_bloque_id' => 1,
            'horas_semanales' => 6,
        ]);
        $segundo = DotacionProceso2027Calculator::resumen($establecimiento, 2027, $this->data());

        $this->assertTrue($segundo['pasos']['planes']['completo']);
        $this->assertSame(0, $segundo['estado_planes']['libre_disposicion_pendiente']);
    }

    public function test_habilita_funciones_no_normativas_con_aula_y_pie_cubiertos_aunque_el_contrato_por_asignatura_sea_menor(): void
    {
        DB::table('dotacion_proceso_2027_configuraciones')->insert([
            'establecimiento_id' => 1,
            'anio' => 2027,
            'decision_combinacion' => 'sin_combinacion',
            'max_horas_bloque_1' => 12,
            'max_horas_bloque_2' => 0,
            'max_horas_bloque_3' => 0,
            'funciones_normativas' => json_encode([]),
        ]);
        $data = [
            'resumen' => [
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 10,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 0,
                'contrato_plan_por_ensenanza_desglose' => [
                    'contrato_plan_general' => 10,
                    'contrato_plan_parvularia' => 0,
                ],
            ],
            'cursos' => [
                'totales' => ['cursos' => 1, 'sin_horas_plan' => 0],
                'configuracion_planes' => ['completo' => true, 'total' => 1, 'configurados' => 1],
            ],
            'asignacion' => [
                'necesidades' => ['plan_estudio' => [[
                    'key' => 'plan:general',
                    'horas_plan_requeridas' => 6,
                    'horas_plan_asignadas' => 6,
                ]]],
                'asignaciones' => [[
                    'necesidad_key' => 'plan:general',
                    'tipo_asignacion' => 'plan_estudio',
                    'horas_contrato' => 8,
                ]],
            ],
            'docentes' => [[
                'rut_normalizado' => '111111111',
                'nombre' => 'Docente de prueba',
                'horas_contrato' => 12,
                'horas_planta' => 0,
                'horas_contrata' => 12,
                'horas_asignadas_total' => 8,
            ]],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ];

        $proceso = DotacionProceso2027Calculator::resumen(Establecimiento::findOrFail(1), 2027, $data);

        $this->assertTrue($proceso['funciones_no_normativas_habilitadas']);
        $this->assertSame(0.0, $proceso['bloques']['bloque_1']['pendientes']);
        $this->assertSame(2.0, $proceso['bloques']['bloque_1']['saldo_maximo']);
        $this->assertSame(2.0, $proceso['capacidad_no_normativas']);

        $dataConNoNormativa = $data;
        $dataConNoNormativa['asignacion']['asignaciones'][] = [
            'tipo_asignacion' => 'otra_funcion', 'dotacion_funcion_id' => 10, 'horas_contrato' => 1,
        ];
        $conNoNormativa = DotacionProceso2027Calculator::resumen(Establecimiento::findOrFail(1), 2027, $dataConNoNormativa);
        $this->assertSame(1.0, $conNoNormativa['bloques']['bloque_1']['saldo_maximo']);

        $data['asignacion']['necesidades']['plan_estudio'][0]['horas_plan_asignadas'] = 5;
        $incompleto = DotacionProceso2027Calculator::resumen(Establecimiento::findOrFail(1), 2027, $data);
        $this->assertFalse($incompleto['funciones_no_normativas_habilitadas']);
    }

    public function test_habilita_funciones_no_normativas_al_completar_la_ultima_necesidad_con_aaee(): void
    {
        DB::table('dotacion_proceso_2027_configuraciones')->insert([
            'establecimiento_id' => 1,
            'anio' => 2027,
            'decision_combinacion' => 'sin_combinacion',
            'max_horas_bloque_1' => 20,
            'max_horas_bloque_2' => 0,
            'max_horas_bloque_3' => 0,
            'funciones_normativas' => json_encode([]),
        ]);
        $asistente = [
            'id' => 6, 'necesidad_key' => 'funcion:normativa',
            'tipo_asignacion' => 'funcion_directiva', 'estamento_cobertura' => 'asistente',
            'horas_contrato' => 6, 'estado' => 'activa',
        ];
        $data = [
            'resumen' => [
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 10,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 0,
                'contrato_plan_por_ensenanza_desglose' => [
                    'contrato_plan_general' => 10,
                    'contrato_plan_parvularia' => 0,
                ],
            ],
            'cursos' => [
                'totales' => ['cursos' => 1, 'sin_horas_plan' => 0],
                'configuracion_planes' => ['completo' => true, 'total' => 1, 'configurados' => 1],
            ],
            'asignacion' => [
                'necesidades' => [
                    'plan_estudio' => [[
                        'key' => 'plan:general', 'horas_plan_requeridas' => 6, 'horas_plan_asignadas' => 6,
                    ]],
                    'funciones' => [[
                        'key' => 'funcion:normativa', 'tipo_asignacion' => 'funcion_directiva',
                        'horas_contrato_requeridas' => 6, 'horas_contrato_asignadas' => 6,
                        'asignaciones' => [$asistente],
                    ]],
                ],
                'asignaciones' => [[
                    'necesidad_key' => 'plan:general', 'tipo_asignacion' => 'plan_estudio',
                    'horas_contrato' => 8,
                ], $asistente],
            ],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ];

        $proceso = DotacionProceso2027Calculator::resumen(Establecimiento::findOrFail(1), 2027, $data);

        $this->assertSame(0.0, $proceso['bloques']['bloque_1']['pendientes']);
        $this->assertSame(6.0, $proceso['bloques']['bloque_1']['asignadas_asistentes_obligatorias']);
        $this->assertTrue($proceso['funciones_no_normativas_habilitadas']);
    }

    public function test_reserva_horas_sin_acreditar_cobertura_obligatoria_y_al_vincular_no_las_duplica(): void
    {
        DB::table('dotacion_proceso_2027_configuraciones')->insert([
            'establecimiento_id' => 1,
            'anio' => 2027,
            'decision_combinacion' => 'sin_combinacion',
            'max_horas_bloque_1' => 12,
            'max_horas_bloque_2' => 0,
            'max_horas_bloque_3' => 0,
            'funciones_normativas' => json_encode([]),
        ]);
        $data = [
            'resumen' => [
                'contrato_plan_general_mas_trabajo_colaborativo_pie' => 10,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 0,
            ],
            'cursos' => [
                'totales' => ['cursos' => 1, 'sin_horas_plan' => 0],
                'configuracion_planes' => ['completo' => true, 'total' => 1, 'configurados' => 1],
            ],
            'asignacion' => [
                'necesidades' => ['plan_estudio' => [[
                    'key' => 'plan:general', 'horas_plan_requeridas' => 6, 'horas_plan_asignadas' => 0,
                ]]],
                'asignaciones' => [],
            ],
            'docentes' => [[
                'rut_normalizado' => '111111111',
                'nombre' => 'Docente de prueba',
                'horas_contrato' => 12,
                'horas_planta' => 12,
                'horas_contrata' => 0,
                'horas_asignadas_total' => 0,
            ]],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ];
        $establecimiento = Establecimiento::findOrFail(1);
        $inicial = DotacionProceso2027Calculator::resumen($establecimiento, 2027, $data);
        $this->assertSame(2.0, $inicial['capacidad_reserva_no_normativa']);
        $this->assertSame(10.0, $inicial['bloques']['bloque_1']['pendientes']);

        $data['asignacion']['asignaciones'][] = [
            'docente_rut_normalizado' => '111111111',
            'tipo_asignacion' => 'reserva_no_normativa',
            'horas_contrato' => 2,
        ];
        $data['docentes'][0]['horas_asignadas_total'] = 2;
        $reservado = DotacionProceso2027Calculator::resumen($establecimiento, 2027, $data);
        $this->assertSame(2.0, $reservado['bloques']['bloque_1']['reservadas_no_normativas']);
        $this->assertSame(10.0, $reservado['bloques']['bloque_1']['pendientes']);
        $this->assertSame(0.0, $reservado['capacidad_reserva_no_normativa']);
        $this->assertSame(2.0, $reservado['bloques']['bloque_1']['titulares_asignadas']);

        $data['asignacion']['asignaciones'][0]['tipo_asignacion'] = 'otra_funcion';
        $data['asignacion']['asignaciones'][0]['dotacion_funcion_id'] = 7;
        $vinculado = DotacionProceso2027Calculator::resumen($establecimiento, 2027, $data);
        $this->assertSame(0.0, $vinculado['bloques']['bloque_1']['reservadas_no_normativas']);
        $this->assertSame(2.0, $vinculado['bloques']['bloque_1']['asignadas_no_normativas']);
        $this->assertSame(2.0, $vinculado['bloques']['bloque_1']['asignadas']);
    }

    private function data(): array
    {
        return [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => ['necesidades' => [], 'asignaciones' => []],
            'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ];
    }
}
