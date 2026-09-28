<?php

namespace Tests\Feature;

use App\Exports\DotacionResumenSobredotacionExport;
use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\DeclaracionSostenedor;
use App\Models\DotacionDocenteAsignacion;
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
        $this->assertGreaterThan(255, mb_strlen($payload['fuente_calculo']));
    }

    public function test_plan_estudio_fuerza_subvencion_general_incluso_en_libre_disposicion(): void
    {
        $this->curso(1, 'NT1', 'Con JEC', 38);
        $persona = ['titulo' => 'Pedagogía en Educación de Párvulos', 'rut' => '111111111',
            'rut_normalizado' => '111111111', 'nombre' => 'Educadora sintética'];
        $metodo = new ReflectionMethod(DotacionAsignacionController::class, 'buildPayload');

        foreach (['tiempo_minimo' => 'SEP', 'libre_disposicion' => 'Libre disposición'] as $subtipo => $subvencion) {
            $payload = $metodo->invoke(new DotacionAsignacionController, Request::create('/'), $this->establecimiento(), $persona, [
                'tipo_asignacion' => 'plan_estudio',
                'subtipo_asignacion' => $subtipo,
                'establecimiento_curso_id' => 1,
                'anio' => 2026,
                'horas_plan_pedagogicas' => 3,
                'subvencion' => $subvencion,
            ]);

            $this->assertSame('General', $payload['subvencion']);
            $this->assertSame($subtipo, $payload['subtipo_asignacion']);
        }
    }

    public function test_acompanamiento_parvularia_no_duplica_cobertura_de_libre_disposicion(): void
    {
        $this->curso(1, 'NT1', 'Con JEC', 38, false);
        DB::table('planes_estudio_asignaturas')->where('asignatura', 'Lenguaje')
            ->update(['tipo_bloque' => 'libre_disposicion', 'horas_semanales' => 6]);
        DB::table('planes_estudio_asignaturas')->where('asignatura', 'Matemática')
            ->update(['horas_semanales' => 32]);

        $establecimiento = $this->establecimiento();
        $base = DotacionAsignacionCalculator::necesidades($establecimiento, 2026, [], [], collect());
        $libre = $base['plan_estudio']->first(fn ($item) => ($item['subtipo_asignacion'] ?? '') === 'libre_disposicion');
        $this->assertNotNull($libre);

        $otro = (new DotacionDocenteAsignacion)->forceFill([
            'tipo_asignacion' => 'plan_estudio', 'estamento_cobertura' => 'docente',
            'necesidad_key' => $libre['key'], 'horas_plan_pedagogicas' => 6, 'horas_contrato' => 7,
        ]);
        $otro->setRelation('declaracionSostenedor', (new DeclaracionSostenedor)->forceFill(['nombre_titulo' => 'Pedagogía en Educación Básica']));
        $educadora = (new DotacionDocenteAsignacion)->forceFill([
            'tipo_asignacion' => 'acompanamiento_parvularia', 'estamento_cobertura' => 'docente',
            'necesidad_key' => $libre['key'], 'horas_plan_pedagogicas' => 6, 'horas_contrato' => 8.68,
        ]);
        $needs = DotacionAsignacionCalculator::necesidades($establecimiento, 2026, [], [], collect([$otro, $educadora]));
        $libre = $needs['plan_estudio']->first(fn ($item) => ($item['key'] ?? '') === $libre['key']);

        $this->assertSame(6.0, $libre['horas_plan_asignadas']);
        $this->assertSame(0.0, $libre['horas_plan_pendientes']);
        $this->assertSame(6.0, $libre['horas_externas_libre_disposicion']);
        $this->assertSame(6.0, $libre['horas_acompanamiento_asignadas']);
        $this->assertSame(0.0, $libre['horas_acompanamiento_disponibles']);
        $this->assertCount(1, $libre['asignaciones']);
        $this->assertCount(1, $libre['acompanamientos']);
    }

    public function test_acompanamiento_exige_horas_de_otro_docente_y_calcula_contrato_de_educadora(): void
    {
        $this->curso(1, 'NT2', 'Con JEC', 38, false);
        DB::table('planes_estudio_asignaturas')->where('asignatura', 'Lenguaje')
            ->update(['tipo_bloque' => 'libre_disposicion', 'horas_semanales' => 6]);
        DB::table('planes_estudio_asignaturas')->where('asignatura', 'Matemática')
            ->update(['horas_semanales' => 32]);
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); $t->string('nombre_titulo');
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); $t->string('estado');
            $t->string('tipo_asignacion'); $t->string('subtipo_asignacion')->nullable();
            $t->string('estamento_cobertura'); $t->string('necesidad_key');
            $t->integer('declaracion_sostenedor_id')->nullable(); $t->integer('establecimiento_curso_id')->nullable();
            $t->decimal('horas_plan_pedagogicas', 8, 2); $t->decimal('horas_contrato', 8, 2);
            $t->string('asignatura_nombre')->nullable(); $t->string('docente_nombre')->nullable();
            $t->string('docente_rut')->nullable(); $t->string('docente_rut_normalizado')->nullable();
        });
        $this->resetCaches();
        $establecimiento = $this->establecimiento();
        $need = DotacionAsignacionCalculator::necesidades($establecimiento, 2026, [], [], collect())['plan_estudio']
            ->first(fn ($item) => ($item['subtipo_asignacion'] ?? '') === 'libre_disposicion');
        $this->assertNotNull($need);
        DB::table('declaracion_sostenedores')->insert(['id' => 1, 'nombre_titulo' => 'Pedagogía en Educación Básica']);
        DB::table('dotacion_docente_asignaciones')->insert([
            'establecimiento_id' => 1, 'anio' => 2026, 'estado' => 'activa',
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'libre_disposicion',
            'estamento_cobertura' => 'docente', 'necesidad_key' => $need['key'],
            'declaracion_sostenedor_id' => 1, 'establecimiento_curso_id' => 1,
            'horas_plan_pedagogicas' => 6, 'horas_contrato' => 7,
            'asignatura_nombre' => $need['asignatura_nombre'], 'docente_nombre' => 'Otro docente',
            'docente_rut' => '111111111', 'docente_rut_normalizado' => '111111111',
        ]);
        $controller = new DotacionAsignacionController;
        $payload = (new ReflectionMethod($controller, 'buildPayload'))->invoke(
            $controller, Request::create('/'), $establecimiento,
            ['titulo' => 'Pedagogía en Educación de Párvulos', 'rut' => '222222222',
                'rut_normalizado' => '222222222', 'nombre' => 'Educadora sintética'],
            ['tipo_asignacion' => 'acompanamiento_parvularia', 'estamento_cobertura' => 'docente',
                'necesidad_key' => $need['key'], 'anio' => 2026, 'horas_plan_pedagogicas' => 6,
                'subvencion' => 'SEP', 'establecimiento_curso_id' => 999]
        );
        $this->assertSame('General', $payload['subvencion']);
        $this->assertSame('libre_disposicion', $payload['subtipo_asignacion']);
        $this->assertSame(1, $payload['establecimiento_curso_id']);
        $this->assertSame(8.68, $payload['horas_contrato']);
        (new ReflectionMethod($controller, 'validateAcompanamientoParvularia'))
            ->invoke($controller, $establecimiento, $payload);

        DB::table('dotacion_docente_asignaciones')->insert([
            'establecimiento_id' => 1, 'anio' => 2026, 'estado' => 'activa',
            'tipo_asignacion' => 'acompanamiento_parvularia', 'subtipo_asignacion' => 'libre_disposicion',
            'estamento_cobertura' => 'docente', 'necesidad_key' => $need['key'],
            'declaracion_sostenedor_id' => null, 'establecimiento_curso_id' => 1,
            'horas_plan_pedagogicas' => 6, 'horas_contrato' => 8.68,
            'asignatura_nombre' => $need['asignatura_nombre'], 'docente_nombre' => 'Educadora sintética',
            'docente_rut' => '222222222', 'docente_rut_normalizado' => '222222222',
        ]);
        try {
            (new ReflectionMethod($controller, 'validateAcompanamientoParvularia'))
                ->invoke($controller, $establecimiento, null, DotacionDocenteAsignacion::findOrFail(1));
            $this->fail('La asignación del otro docente no puede eliminarse mientras exista acompañamiento.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('horas_plan_pedagogicas', $e->errors());
        }

        $payload['horas_plan_pedagogicas'] = 6.25;
        $this->expectException(ValidationException::class);
        (new ReflectionMethod($controller, 'validateAcompanamientoParvularia'))
            ->invoke($controller, $establecimiento, $payload);
    }

    public function test_tabla_cpeip_limita_a_35_horas_pedagogicas_y_41_de_contrato_aula(): void
    {
        $this->curso(1, 'NT1', 'Con JEC', 38, false);
        Schema::create('docente_horas_proporciones', function (Blueprint $t): void {
            $t->id(); $t->string('proporcion'); $t->integer('horas_contrato');
            $t->decimal('horas_aula_pedagogicas', 8, 2); $t->boolean('vigente');
        });
        foreach ([7 => 6, 11 => 10, 29 => 25, 41 => 35, 42 => 36, 44 => 38] as $contrato => $aula) {
            DB::table('docente_horas_proporciones')->insert([
                'proporcion' => '65_35', 'horas_contrato' => $contrato,
                'horas_aula_pedagogicas' => $aula, 'vigente' => true,
            ]);
        }
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); $t->string('estado');
            $t->string('docente_rut_normalizado'); $t->string('proporcion_aplicada');
            $t->string('tipo_asignacion'); $t->integer('establecimiento_curso_id');
            $t->decimal('horas_plan_pedagogicas', 8, 2); $t->decimal('horas_contrato', 8, 2);
        });
        $this->resetCaches();
        $persona = ['titulo' => 'Pedagogía en Educación de Párvulos', 'rut' => '222222222',
            'rut_normalizado' => '222222222', 'nombre' => 'Educadora sintética'];
        $controller = new DotacionAsignacionController;
        $data = ['tipo_asignacion' => 'plan_estudio', 'estamento_cobertura' => 'docente',
            'establecimiento_curso_id' => 1, 'anio' => 2026, 'horas_plan_pedagogicas' => 35];
        $payload = (new ReflectionMethod($controller, 'buildPayload'))->invoke(
            $controller, Request::create('/'), $this->establecimiento(), $persona, $data
        );
        $this->assertSame(41.0, $payload['horas_contrato']);
        $this->assertSame(26.25, $payload['horas_cronologicas_aula']);
        $this->assertSame('NT JEC · CPEIP 65/35', $payload['proporcion_aplicada']);
        (new ReflectionMethod($controller, 'validateLimiteAulaParvularia'))
            ->invoke($controller, $this->establecimiento(), $payload);

        foreach ([1 => 10, 2 => 25] as $id => $horas) {
            DB::table('dotacion_docente_asignaciones')->insert([
                'id' => $id, 'establecimiento_id' => 1, 'anio' => 2026, 'estado' => 'activa',
                'docente_rut_normalizado' => '222222222', 'proporcion_aplicada' => 'NT JEC · CPEIP 65/35',
                'tipo_asignacion' => 'plan_estudio', 'establecimiento_curso_id' => 1,
                'horas_plan_pedagogicas' => $horas, 'horas_contrato' => 0,
            ]);
        }
        $recalcular = new ReflectionMethod($controller, 'recalcularContratoAulaParvularia');
        $recalcular->invoke($controller, $this->establecimiento(), 2026, '222222222');
        $this->assertEqualsCanonicalizing([11.0, 30.0], DB::table('dotacion_docente_asignaciones')
            ->orderBy('id')->pluck('horas_contrato')->map(fn ($value) => (float) $value)->all());
        DB::table('dotacion_docente_asignaciones')->where('id', 1)->delete();
        $recalcular->invoke($controller, $this->establecimiento(), 2026, '222222222');
        $this->assertSame(29.0, (float) DB::table('dotacion_docente_asignaciones')->where('id', 2)->value('horas_contrato'));

        $payloadExcedido = (new ReflectionMethod($controller, 'buildPayload'))->invoke(
            $controller, Request::create('/'), $this->establecimiento(), $persona,
            array_replace($data, ['horas_plan_pedagogicas' => 35.25])
        );
        $this->expectException(ValidationException::class);
        (new ReflectionMethod($controller, 'validateLimiteAulaParvularia'))
            ->invoke($controller, $this->establecimiento(), $payloadExcedido);
    }
}
