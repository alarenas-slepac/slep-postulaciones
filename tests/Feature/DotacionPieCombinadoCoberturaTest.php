<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEtapasPendientes;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionPieCombinadoCoberturaTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('dotacion_proceso_2027_configuraciones', function (Blueprint $table): void {
            $table->id();
            $table->integer('establecimiento_id');
            $table->integer('anio');
            $table->string('decision_combinacion');
            $table->decimal('max_horas_bloque_1', 8, 2);
            $table->decimal('max_horas_bloque_2', 8, 2);
            $table->decimal('max_horas_bloque_3', 8, 2);
            $table->json('funciones_normativas')->nullable();
        });
        DB::table('dotacion_proceso_2027_configuraciones')->insert([
            'establecimiento_id' => 99999, 'anio' => 2027,
            'decision_combinacion' => 'combinaciones_configuradas',
            'max_horas_bloque_1' => 0, 'max_horas_bloque_2' => 58, 'max_horas_bloque_3' => 0,
        ]);
    }

    public static function coberturas(): array
    {
        return [
            'tres horas en el primer curso' => [[[101, 3]], 3.0, 0.0],
            'tres horas en el otro curso' => [[[102, 3]], 3.0, 0.0],
            'tres horas en la clave combinada' => [[[0, 3]], 3.0, 0.0],
            'horas repartidas entre ambos cursos' => [[[101, 1], [102, 2]], 3.0, 0.0],
            'sin asignar' => [[], 0.0, 3.0],
            'cobertura parcial en el otro curso' => [[[102, 1.5]], 1.5, 1.5],
            'exceso historico visible' => [[[101, 3], [102, 3]], 6.0, 0.0],
            'asistente en el otro curso' => [[[102, 3, 'asistente']], 3.0, 0.0],
        ];
    }

    #[DataProvider('coberturas')]
    public function test_la_cobertura_del_grupo_acredita_cualquiera_de_sus_cursos(array $filas, float $asignadas, float $pendientes): void
    {
        $asignaciones = collect($filas)->map(fn ($fila, $index) => (object) [
            'id' => $index + 1, 'tipo_asignacion' => 'pie_colaborativo',
            'necesidad_key' => $fila[0] === 0 ? 'pie_colab_combinado|10' : 'pie_colab:'.$fila[0],
            'establecimiento_curso_id' => $fila[0], 'horas_contrato' => (float) $fila[1],
            'estamento_cobertura' => $fila[2] ?? 'docente', 'estado' => 'activa',
        ]);
        // Construir las filas originales con la misma función que genera las
        // necesidades reales: incluyen los campos derivados *_calculo.
        $necesidades = $this->necesidades($asignaciones);
        $originales = serialize([$necesidades, $asignaciones]);
        $grupo = DotacionCursoCombinadoCalculator::consolidateCollaborativePie(
            $necesidades, $this->grupos(), $asignaciones
        )->first();
        $proceso = $this->proceso($grupo, $asignaciones->all());

        $this->assertSame(3.0, $grupo['horas_contrato_requeridas']);
        $this->assertSame(3.0, $grupo['horas_contrato_requeridas_calculo']);
        $this->assertSame($asignadas, $grupo['horas_contrato_asignadas']);
        $this->assertSame($asignadas, $grupo['horas_contrato_asignadas_calculo']);
        $this->assertSame($pendientes, $grupo['horas_contrato_pendientes']);
        $this->assertSame('bloque_2', $proceso['need_blocks'][$grupo['key']]);
        $this->assertSame(58.0, $proceso['bloques']['bloque_2']['requeridas']);
        $this->assertSame(55.0 + min(3.0, $asignadas), $proceso['bloques']['bloque_2']['asignadas_obligatorias']);
        $this->assertSame($pendientes, $proceso['bloques']['bloque_2']['pendientes']);
        $this->assertSame($pendientes === 0.0, $proceso['pasos']['asignacion']['completo']);
        $this->assertSame($pendientes === 0.0, $proceso['funciones_no_normativas_habilitadas']);
        $this->assertSame($originales, serialize([$necesidades, $asignaciones]));
        $this->assertSame($asignadas > 3 ? 'excedida' : ($pendientes === 0.0 ? 'cubierta' : ($asignadas > 0 ? 'parcial' : 'pendiente')), $grupo['estado']['key']);

        if ($pendientes === 0.0) {
            $this->assertArrayNotHasKey('asignacion', DotacionEtapasPendientes::porEtapa($proceso));
        }
        if (($filas[0][2] ?? '') === 'asistente') {
            $this->assertSame(3.0, $proceso['bloques']['bloque_2']['asignadas_asistentes_obligatorias']);
            $this->assertSame(55.0, $proceso['bloques']['bloque_2']['asignadas']);
        }
    }

    public function test_no_usa_horas_de_un_curso_ajeno_y_no_modifica_grupos_inactivos(): void
    {
        $ajena = (object) ['id' => 9, 'tipo_asignacion' => 'pie_colaborativo',
            'necesidad_key' => 'pie_colab:103', 'horas_contrato' => 3.0];
        $asignaciones = collect([$ajena]);
        $items = $this->necesidades($asignaciones);
        $items[] = $this->necesidad(103, $asignaciones);
        $resultado = DotacionCursoCombinadoCalculator::consolidateCollaborativePie($items, $this->grupos(), $asignaciones);
        $this->assertCount(2, $resultado);
        $this->assertSame(3.0, $resultado->firstWhere('establecimiento_curso_id', 103)['horas_contrato_asignadas']);
        $this->assertSame(3.0, $resultado->firstWhere('dotacion_curso_combinado_id', 10)['horas_contrato_pendientes']);

        $grupos = $this->grupos();
        $grupos[0]['activo'] = false;
        $this->assertSame($items, DotacionCursoCombinadoCalculator::consolidateCollaborativePie($items, $grupos, $asignaciones)->all());
    }

    private function necesidades($asignaciones): array
    {
        return [$this->necesidad(101, $asignaciones), $this->necesidad(102, $asignaciones)];
    }

    private function necesidad(int $curso, $asignaciones): array
    {
        return (new ReflectionMethod(DotacionAsignacionCalculator::class, 'needRow'))->invoke(null,
            'pie_colab:'.$curso, 'pie_colaborativo', 'trabajo_colaborativo', [
                'titulo' => 'Trabajo colaborativo PIE', 'curso_label' => 'Curso de prueba '.$curso,
                'establecimiento_curso_id' => $curso, 'horas_contrato' => 3.0,
            ], $asignaciones);
    }

    private function grupos(): array
    {
        return [['id' => 10, 'nombre' => 'NT1 + NT2 de prueba', 'activo' => true,
            'miembros' => [['establecimiento_curso_id' => 101], ['establecimiento_curso_id' => 102]]]];
    }

    private function proceso(array $pie, array $asignaciones): array
    {
        $plan = ['key' => 'plan:grupo', 'tipo_asignacion' => 'plan_estudio', 'establecimiento_curso_id' => 101,
            'horas_plan_requeridas' => 38, 'horas_plan_asignadas' => 38, 'horas_contrato_requeridas' => 55];
        $asignaciones[] = (object) ['id' => 20, 'necesidad_key' => 'plan:grupo', 'tipo_asignacion' => 'plan_estudio',
            'horas_contrato' => 55, 'estamento_cobertura' => 'docente', 'estado' => 'activa'];
        $establecimiento = new Establecimiento;
        $establecimiento->id = 99999;

        return DotacionProceso2027Calculator::resumen($establecimiento, 2027, [
            'resumen' => ['contrato_plan_general_mas_trabajo_colaborativo_pie' => 0,
                'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 58,
                'contrato_plan_por_ensenanza_desglose' => ['contrato_plan_parvularia' => 55]],
            'cursos' => ['configuracion_planes' => ['completo' => true, 'total' => 2, 'configurados' => 2],
                'rows' => ['NT1' => ['detalles' => [['establecimiento_curso_id' => 101]]],
                    'NT2' => ['detalles' => [['establecimiento_curso_id' => 102]]]]],
            'asignacion' => ['necesidades' => ['plan_estudio' => [$plan], 'pie_colaborativo' => [$pie]],
                'asignaciones' => $asignaciones],
            'docentes' => [], 'cursos_combinados' => ['resumen' => ['grupos_activos' => 1]],
        ]);
    }
}
