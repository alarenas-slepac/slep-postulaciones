<?php

namespace Tests\Feature;

use App\Exports\DotacionResumenSobredotacionExport;
use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionContratoEnsenanzaCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionCursosPlanesResumenCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionParvulariaCalculator;
use App\Support\DotacionProfesionDocenteResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class DotacionParvulariaBasesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->resetCaches();
        Schema::create('cursos', function (Blueprint $t) {
            $t->id(); $t->string('codigo'); $t->string('nombre');
        });
        Schema::create('establecimiento_cursos', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('curso_id'); $t->integer('plan_estudio_id');
            $t->integer('anio'); $t->string('regimen_jec'); $t->string('nombre_seccion'); $t->string('letra')->default('A');
            $t->boolean('activo')->default(true); $t->integer('matricula')->default(10);
        });
        Schema::create('planes_estudio', function (Blueprint $t) {
            $t->id(); $t->integer('curso_id'); $t->integer('anio'); $t->string('regimen_jec');
            $t->decimal('horas_semanales_total', 8, 2); $t->boolean('activo')->default(true);
        });
        Schema::create('planes_estudio_asignaturas', function (Blueprint $t) {
            $t->id(); $t->integer('plan_estudio_id'); $t->integer('orden')->default(1);
            $t->string('asignatura'); $t->string('tipo_bloque'); $t->decimal('horas_semanales', 8, 2);
        });
        Schema::create('planes_estudio_bloques', function (Blueprint $t) {
            $t->id(); $t->integer('plan_estudio_id'); $t->integer('orden'); $t->boolean('activo');
            $t->string('tipo_bloque'); $t->decimal('horas_semanales', 8, 2);
        });
        Schema::create('establecimiento_curso_pie', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            $t->integer('curso_id'); $t->integer('establecimiento_curso_id'); $t->integer('total_pie');
        });
        Schema::create('dotacion_cursos_combinados', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); $t->string('nombre');
            $t->string('proporcion')->default('auto'); $t->boolean('activo')->default(true);
        });
        Schema::create('dotacion_curso_combinado_miembros', function (Blueprint $t) {
            $t->id(); $t->integer('dotacion_curso_combinado_id'); $t->integer('establecimiento_curso_id');
        });
        Schema::create('dotacion_curso_combinado_asignaturas', function (Blueprint $t) {
            $t->id(); $t->integer('dotacion_curso_combinado_id'); $t->string('asignatura_key');
        });
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) {
                (new ReflectionProperty($class, $property))->setValue(null, []);
            }
        }
        DotacionCursoCombinadoCalculator::clearCache();
        (new ReflectionProperty(\App\Support\DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
    }

    private function curso(int $id, string $nivel, string $jec, float $horas, bool $pie = true): EstablecimientoCurso
    {
        DB::table('cursos')->insert(['id' => $id, 'codigo' => $nivel, 'nombre' => $nivel]);
        DB::table('planes_estudio')->insert(['id' => $id, 'curso_id' => $id, 'anio' => 2026, 'regimen_jec' => $jec, 'horas_semanales_total' => $horas]);
        DB::table('establecimiento_cursos')->insert(['id' => $id, 'establecimiento_id' => 1, 'curso_id' => $id,
            'plan_estudio_id' => $id, 'anio' => 2026, 'regimen_jec' => $jec, 'nombre_seccion' => $nivel.' A']);
        foreach (['Lenguaje', 'Matemática'] as $asignatura) {
            DB::table('planes_estudio_asignaturas')->insert(['plan_estudio_id' => $id, 'asignatura' => $asignatura,
                'tipo_bloque' => 'tiempo_minimo', 'horas_semanales' => $horas / 2]);
        }
        if ($pie) {
            DB::table('establecimiento_curso_pie')->insert(['establecimiento_id' => 1, 'anio' => 2026,
                'curso_id' => $id, 'establecimiento_curso_id' => $id, 'total_pie' => 1]);
        }

        return EstablecimientoCurso::with(['curso', 'planEstudio'])->findOrFail($id);
    }

    private function establecimiento(): Establecimiento
    {
        return (new Establecimiento)->forceFill(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética']);
    }

    public static function escenarios(): array
    {
        return [
            ['NT1', 'Con JEC', 38, 55], ['NT2', 'Con JEC', 38, 55],
            ['NT1', 'Sin JEC', 32, 35], ['NT2', 'Sin JEC', 30, 31],
        ];
    }

    #[DataProvider('escenarios')]
    public function test_base_completa_parcial_pie_y_exportacion(string $nivel, string $jec, float $horas, float $base): void
    {
        $curso = $this->curso(1, $nivel, $jec, $horas);
        $cursos = DotacionEstablecimientoCalculator::cursosPorNivel($this->establecimiento(), 2026);
        $this->assertSame($base, $cursos['totales']['horas_contrato_equivalente']);
        $this->assertSame($base + 3, $cursos['totales']['contrato_mas_trabajo_colaborativo_pie']);
        $persona = ['titulo' => 'Pedagogía en Educación de Párvulos'];
        $mitad = DotacionProfesionDocenteResolver::conversionNt($curso, $horas / 2, $persona);
        $this->assertSame($base / 2, $mitad['horas_contrato_equivalente_redondeado']);
        $need = DotacionEstablecimientoCalculator::contratoEquivalenteAsignacion($curso, $horas / 2);
        $this->assertSame($base / 2, $need['horas_contrato_equivalente_redondeado']);
        $split = DotacionContratoEnsenanzaCalculator::split($cursos, [], $base);
        $row = (new DotacionResumenSobredotacionExport)->row($this->establecimiento(), [
            'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => $split['contrato_parvularia_mas_pie'],
            'horas_contrato_docentes_parvularia' => 44,
        ]);
        $this->assertSame($base + 3, $row[5]);
        $this->assertSame($base + 3 - 44, $row[15]);
        DB::table('establecimiento_curso_pie')->update(['total_pie' => 0]);
        $sinPie = DotacionEstablecimientoCalculator::cursosPorNivel($this->establecimiento(), 2026);
        $this->assertSame($base, $sinPie['totales']['contrato_mas_trabajo_colaborativo_pie']);
    }

    #[DataProvider('regimenes')]
    public function test_combinado_usa_una_base_aunque_nt2_sea_representante(string $jec, float $base): void
    {
        $curso = $this->curso(1, 'NT2', $jec, $jec === 'Con JEC' ? 38 : 30);
        $this->curso(2, 'NT1', $jec, $jec === 'Con JEC' ? 38 : 32);
        DB::table('dotacion_cursos_combinados')->insert(['id' => 1, 'establecimiento_id' => 1, 'anio' => 2026, 'nombre' => 'NT combinado']);
        foreach ([1, 2] as $id) {
            DB::table('dotacion_curso_combinado_miembros')->insert(['dotacion_curso_combinado_id' => 1, 'establecimiento_curso_id' => $id]);
        }
        $cursos = DotacionEstablecimientoCalculator::cursosPorNivel($this->establecimiento(), 2026);
        $needs = DotacionAsignacionCalculator::necesidades($this->establecimiento(), 2026, $cursos, [], collect());
        $plan = $needs['plan_estudio'];
        $this->assertCount(2, $plan);
        $this->assertSame($base, (float) $plan->sum('horas_contrato_requeridas'));
        $this->assertSame(3.0, (float) $needs['pie_colaborativo']->sum('horas_contrato_requeridas'));
        $grupo = DotacionCursoCombinadoCalculator::summary($this->establecimiento(), 2026, $plan)['grupos'];
        $this->assertSame($base, $grupo->sole()['totales']['horas_contrato']);
        $resumen = DotacionCursosPlanesResumenCalculator::build($cursos, $grupo);
        $this->assertSame($base + 3, $resumen['totales']['contrato_mas_trabajo_colaborativo_pie']);
        $conversion = DotacionProfesionDocenteResolver::conversionNt($curso, $jec === 'Con JEC' ? 19 : 16, ['titulo' => 'Pedagogía en Educación de Párvulos'], $plan->first()['proporcion_key'], $plan->first());
        $this->assertSame($base / 2, $conversion['horas_contrato_equivalente_redondeado']);
    }

    public static function regimenes(): array
    {
        return [['Con JEC', 55.0], ['Sin JEC', 35.0]];
    }

    public function test_refuerzos_se_consolidan_solo_en_grupos_activos(): void
    {
        $refuerzos = [1 => ['horas_plan' => 6.0], 2 => ['horas_plan' => 6.0], 3 => ['horas_plan' => 4.0]];
        $grupo = ['activo' => true, 'miembros' => [['id' => 1], ['id' => 2]]];
        $resultado = DotacionParvulariaCalculator::consolidarRefuerzos($refuerzos, [$grupo]);
        $this->assertSame(10.0, (float) collect($resultado)->sum('horas_plan'));
        $this->assertSame(4.0, $resultado[3]['horas_plan']);
        $grupo['activo'] = false;
        $this->assertSame($refuerzos, DotacionParvulariaCalculator::consolidarRefuerzos($refuerzos, [$grupo]));
    }

    public function test_sin_jec_no_admite_otro_titulo_en_el_controlador(): void
    {
        $this->curso(1, 'NT1', 'Sin JEC', 32);
        $this->expectException(ValidationException::class);
        (new ReflectionMethod(DotacionAsignacionController::class, 'buildPayload'))->invoke(
            new DotacionAsignacionController, Request::create('/'), $this->establecimiento(), ['titulo' => 'Profesor de Básica'],
            ['tipo_asignacion' => 'plan_estudio', 'establecimiento_curso_id' => 1, 'anio' => 2026, 'horas_plan_pedagogicas' => 16]
        );
    }

    public function test_refuerzo_real_de_dos_cursos_se_cuenta_una_vez_y_va_a_general(): void
    {
        $this->curso(1, 'NT1', 'Con JEC', 38);
        $this->curso(2, 'NT2', 'Con JEC', 38);
        Schema::create('docente_horas_proporciones', function (Blueprint $t) {
            $t->id(); $t->string('proporcion'); $t->integer('horas_contrato');
            $t->decimal('horas_aula_pedagogicas', 8, 2); $t->boolean('vigente')->default(true);
        });
        DB::table('docente_horas_proporciones')->insert(['proporcion' => '65_35', 'horas_contrato' => 7, 'horas_aula_pedagogicas' => 6]);
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); $t->string('estado');
            $t->string('tipo_asignacion'); $t->string('subtipo_asignacion');
            $t->integer('establecimiento_curso_id'); $t->decimal('horas_plan_pedagogicas', 8, 2);
        });
        foreach ([1, 2] as $id) {
            DB::table('dotacion_docente_asignaciones')->insert(['establecimiento_id' => 1, 'anio' => 2026,
                'estado' => 'activa', 'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'libre_disposicion',
                'establecimiento_curso_id' => $id, 'horas_plan_pedagogicas' => 6]);
        }
        $antes = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        $separados = DotacionEstablecimientoCalculator::cursosPorNivel($this->establecimiento(), 2026);
        $this->assertSame(12.0, $separados['totales']['horas_plan_refuerzo_ld_otro_docente']);
        DB::table('dotacion_cursos_combinados')->insert(['id' => 1, 'establecimiento_id' => 1, 'anio' => 2026, 'nombre' => 'Grupo sintético']);
        foreach ([1, 2] as $id) {
            DB::table('dotacion_curso_combinado_miembros')->insert(['dotacion_curso_combinado_id' => 1, 'establecimiento_curso_id' => $id]);
        }
        $cursos = DotacionEstablecimientoCalculator::cursosPorNivel($this->establecimiento(), 2026);
        $this->assertSame(6.0, $cursos['totales']['horas_plan_refuerzo_ld_otro_docente']);
        $this->assertSame(7.0, $cursos['totales']['horas_contrato_refuerzo_ld_otro_docente']);
        $grupo = [['id' => 1, 'activo' => true, 'miembros' => [['id' => 1], ['id' => 2]],
            'totales' => ['horas_contrato' => 55, 'horas_requeridas' => 38]]];
        $split = DotacionContratoEnsenanzaCalculator::split($cursos, $grupo, 62);
        $this->assertSame(58.0, $split['contrato_parvularia_mas_pie']);
        $this->assertSame(7.0, $split['contrato_general_mas_pie']);
        $resumen = DotacionCursosPlanesResumenCalculator::build($cursos, $grupo);
        $this->assertSame(7.0, $resumen['refuerzo_plan_general']['horas_contrato_equivalente']);
        $this->assertSame(65.0, $resumen['totales']['contrato_mas_trabajo_colaborativo_pie']);
        $this->assertSame($antes, DB::table('dotacion_docente_asignaciones')->get()->toJson());
    }

    public function test_sin_jec_no_admite_otro_titulo_en_pie(): void
    {
        $this->curso(1, 'NT2', 'Sin JEC', 30);
        $this->expectException(ValidationException::class);
        (new ReflectionMethod(DotacionAsignacionController::class, 'buildPayload'))->invoke(
            new DotacionAsignacionController, Request::create('/'), $this->establecimiento(), ['titulo' => 'Profesor de Básica'],
            ['tipo_asignacion' => 'pie_colaborativo', 'establecimiento_curso_id' => 1, 'anio' => 2026, 'horas_contrato' => 3]
        );
    }

    public function test_sin_jec_no_admite_libre_disposicion_aunque_sea_educadora(): void
    {
        $this->curso(1, 'NT1', 'Sin JEC', 32);
        $this->expectException(ValidationException::class);
        (new ReflectionMethod(DotacionAsignacionController::class, 'buildPayload'))->invoke(
            new DotacionAsignacionController, Request::create('/'), $this->establecimiento(), ['titulo' => 'Pedagogía en Educación de Párvulos'],
            ['tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'libre_disposicion',
                'establecimiento_curso_id' => 1, 'anio' => 2026, 'horas_plan_pedagogicas' => 2]
        );
    }

    public function test_payload_distribuye_contrato_y_no_utiliza_el_valor_enviado_por_cliente(): void
    {
        $this->curso(1, 'NT1', 'Con JEC', 38);
        $persona = ['titulo' => 'Pedagogía en Educación de Párvulos', 'rut' => '111111111',
            'rut_normalizado' => '111111111', 'nombre' => 'Educadora sintética'];
        $payload = (new ReflectionMethod(DotacionAsignacionController::class, 'buildPayload'))->invoke(
            new DotacionAsignacionController, Request::create('/'), $this->establecimiento(), $persona,
            ['tipo_asignacion' => 'plan_estudio', 'establecimiento_curso_id' => 1, 'anio' => 2026,
                'horas_plan_pedagogicas' => 19, 'horas_contrato' => 999]
        );
        $this->assertSame(27.5, $payload['horas_contrato']);
        $this->assertStringContainsString('19 / 38', $payload['fuente_calculo']);
    }
}
