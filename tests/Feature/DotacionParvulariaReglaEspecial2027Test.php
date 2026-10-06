<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Services\DotacionProporcionRecalculationService;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionContratoParvulariaCalculator;
use App\Support\DotacionContratoPlanCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionParvulariaReglaEspecial2027Test extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); $t->timestamps();
        });
        Schema::create('cursos', function (Blueprint $t): void {
            $t->id(); $t->string('codigo'); $t->string('nombre'); $t->string('nivel_educativo');
        });
        Schema::create('planes_estudio', function (Blueprint $t): void {
            $t->id(); $t->integer('curso_id'); $t->integer('anio'); $t->string('nombre_plan');
            $t->string('regimen_jec'); $t->decimal('horas_semanales_total', 8, 2); $t->boolean('activo');
        });
        Schema::create('establecimiento_cursos', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('curso_id'); $t->integer('plan_estudio_id');
            $t->integer('anio'); $t->string('regimen_jec'); $t->string('nombre_seccion'); $t->boolean('activo');
        });
        Schema::create('planes_estudio_asignaturas', function (Blueprint $t): void {
            $t->id(); $t->integer('plan_estudio_id'); $t->integer('orden');
        });
        Schema::create('planes_estudio_bloques', function (Blueprint $t): void {
            $t->id(); $t->integer('plan_estudio_id'); $t->integer('orden');
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); $t->string('nombre_titulo');
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            foreach (['docente_rut', 'docente_rut_normalizado', 'docente_nombre', 'tipo_asignacion', 'estado',
                'estamento_cobertura', 'proporcion_aplicada', 'subtipo_asignacion', 'asignatura_nombre', 'necesidad_key'] as $field) {
                $t->string($field)->nullable();
            }
            $t->integer('establecimiento_curso_id')->nullable(); $t->integer('declaracion_sostenedor_id')->nullable();
            $t->integer('dotacion_curso_combinado_id')->nullable();
            $t->decimal('horas_plan_pedagogicas', 8, 2)->nullable(); $t->decimal('horas_contrato', 8, 2);
            $t->decimal('horas_cronologicas_aula', 8, 2)->nullable(); $t->text('fuente_calculo')->nullable();
            $t->integer('updated_by')->nullable(); $t->timestamps();
        });
        Schema::create('docente_horas_proporciones', function (Blueprint $t): void {
            $t->id(); $t->string('proporcion'); $t->integer('horas_contrato');
            $t->decimal('horas_aula_pedagogicas', 8, 2); $t->boolean('vigente');
        });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99001, 'nombre_establecimiento' => 'EE sintético']);
        DB::table('cursos')->insert(['id' => 1, 'codigo' => 'NT1', 'nombre' => 'NT1', 'nivel_educativo' => 'Educación Parvularia']);
        DB::table('planes_estudio')->insert(['id' => 1, 'curso_id' => 1, 'anio' => 2027,
            'nombre_plan' => 'NT1 Con JEC', 'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 38, 'activo' => true]);
        DB::table('establecimiento_cursos')->insert(['id' => 1, 'establecimiento_id' => 1, 'curso_id' => 1,
            'plan_estudio_id' => 1, 'anio' => 2027, 'regimen_jec' => 'Con JEC', 'nombre_seccion' => 'NT1 A', 'activo' => true]);
        DB::table('declaracion_sostenedores')->insert(['id' => 1, 'nombre_titulo' => 'Pedagogía en Educación de Párvulos']);
        foreach ([4 => 3, 11 => 10, 21 => 18, 30 => 26, 33 => 28, 35 => 30, 41 => 35, 44 => 38] as $contrato => $aula) {
            DB::table('docente_horas_proporciones')->insert(['proporcion' => '65_35',
                'horas_contrato' => $contrato, 'horas_aula_pedagogicas' => $aula, 'vigente' => true]);
        }
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    public function test_treinta_horas_no_se_sustituyen_por_la_tabla_general_al_guardar(): void
    {
        $payload = $this->payload(30);
        $this->assertSame(44.0, $payload['horas_contrato']);
        $this->assertSame('NT Con JEC · base contractual 55 h', $payload['proporcion_aplicada']);
        $this->assertStringContainsString('30 / 38', $payload['fuente_calculo']);
        $this->assertStringContainsString('Redondeo hacia arriba', $payload['fuente_calculo']);
        $this->assertSame(22.5, $payload['horas_cronologicas_aula']);
    }

    public function test_reproduce_resumen_con_26_de_plan_y_4_de_acompanamiento_sin_escribir_datos(): void
    {
        $this->cargarCaso();
        $antes = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        $resumen = DotacionAsignacionCalculator::assignmentsByRut(Establecimiento::findOrFail(1), 2027)['99000001K'];
        $this->assertSame(30.0, $resumen['aula']);
        $this->assertSame(44.0, $resumen['contrato_especial']);
        $this->assertSame(3.0, $resumen['pie']);
        $this->assertSame(47.0, $resumen['total']);
        $this->assertSame(4.0, $resumen['total'] - 43);
        $this->assertSame($antes, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
    }

    public function test_limite_individual_43_menos_pie_3_usa_la_base_especial(): void
    {
        $this->assertSame(40.0, $this->payload(27.63)['horas_contrato']);
        $this->assertSame(41.0, $this->payload(27.64)['horas_contrato']);
        $this->assertLessThanOrEqual(43, $this->payload(27.63)['horas_contrato'] + 3);
        $this->assertGreaterThan(43, $this->payload(27.64)['horas_contrato'] + 3);
        $this->assertSame(0.0, 43 - $this->payload(27.63)['horas_contrato'] - 3);
    }

    public function test_redondea_el_total_una_vez_y_no_cada_asignatura(): void
    {
        $this->cargarCaso();
        $filas = DotacionDocenteAsignacion::query()->where('tipo_asignacion', '!=', 'pie_colaborativo')->get();
        // Redondear por asignatura daría 46; el total 30/38*55 debe dar 44.
        $this->assertSame(44.0, (float) DotacionContratoPlanCalculator::consolidar($filas)->sum('horas_contrato'));
        $this->assertSame(44.0, (float) DotacionContratoPlanCalculator::consolidar($filas->reverse())->sum('horas_contrato'));
        $unaFila = array_replace($filas->first()->toArray(), ['horas_plan_pedagogicas' => 30]);
        $this->assertSame(44.0, DotacionContratoPlanCalculator::consolidar(collect([$unaFila]))->sole()['horas_contrato']);
    }

    public function test_cursos_con_bases_distintas_se_suman_antes_del_redondeo_por_docente(): void
    {
        $fila = ['id' => 1, 'anio' => 2027, 'establecimiento_id' => 1, 'docente_rut' => '99000001K',
            'tipo_asignacion' => 'plan_estudio', 'proporcion_aplicada' => 'NT Con JEC · base contractual 55 h',
            'horas_plan_pedagogicas' => 3, 'horas_contrato' => 4.34, 'establecimiento_curso_id' => 1,
            'fuente_calculo' => 'Reparto proporcional: 3 / 38 h de plan × 55 h de contrato.'];
        $otroCurso = array_replace($fila, ['id' => 2, 'establecimiento_curso_id' => 2,
            'horas_plan_pedagogicas' => 20, 'proporcion_aplicada' => 'NT Sin JEC · base contractual 31 h',
            'fuente_calculo' => 'Reparto proporcional: 20 / 32 h de plan × 31 h de contrato.']);
        $otroEstablecimiento = array_replace($fila, ['id' => 3, 'establecimiento_id' => 2]);
        $calculadas = DotacionContratoPlanCalculator::consolidar(collect([$fila, $otroCurso, $otroEstablecimiento]));
        // ceil(3/38*55 + 20/32*31) = 24, no 25 por redondear cada curso.
        $this->assertSame([5.0, 19.0, 5.0], $calculadas->pluck('horas_contrato')->all());
        $this->assertSame(24.0, (float) $calculadas->where('establecimiento_id', 1)->sum('horas_contrato'));
    }

    public function test_total_entero_no_se_incrementa_por_residuos_de_coma_flotante(): void
    {
        $this->assertSame(55.0, $this->payload(38)['horas_contrato']);
        $this->assertSame(11.0, $this->payload(7.6)['horas_contrato']);
    }

    public function test_el_incremento_y_la_eliminacion_recalculan_el_total_sin_redondear_cada_asignatura(): void
    {
        $this->cargarCaso();
        DB::table('dotacion_docente_asignaciones')->where('id', 7)->delete();
        $payload = $this->payload(2);
        $this->assertSame(3.0, $payload['horas_contrato']);
        $metodo = new ReflectionMethod(DotacionAsignacionController::class, 'recalcularContratoAulaParvularia');
        $metodo->invoke(new DotacionAsignacionController, Establecimiento::findOrFail(1), 2027, '99000001K');
        $this->assertSame(41.0, round((float) DB::table('dotacion_docente_asignaciones')->where('tipo_asignacion', '!=', 'pie_colaborativo')->sum('horas_contrato'), 2));
        $this->assertSame(3.0, (float) DB::table('dotacion_docente_asignaciones')->where('tipo_asignacion', 'pie_colaborativo')->value('horas_contrato'));
    }

    public function test_recalculo_explicito_incluye_acompanamiento_y_es_idempotente(): void
    {
        $this->cargarCaso();
        $service = app(DotacionProporcionRecalculationService::class);
        $resultado = $service->recalculate(Establecimiento::findOrFail(1), 2027);
        $this->assertSame(['total' => 7, 'actualizadas' => 7, 'omitidas' => 0], $resultado);
        $this->assertSame(44.0, round((float) DB::table('dotacion_docente_asignaciones')->where('tipo_asignacion', '!=', 'pie_colaborativo')->sum('horas_contrato'), 2));
        $this->assertSame(7, DB::table('dotacion_docente_asignaciones')->where('proporcion_aplicada', 'NT Con JEC · base contractual 55 h')->count());
        $this->assertSame(['total' => 7, 'actualizadas' => 0, 'omitidas' => 0], $service->recalculate(Establecimiento::findOrFail(1), 2027));
    }

    public function test_preserva_historicos_asistentes_pie_y_filas_sin_referencia(): void
    {
        $this->cargarCaso();
        $rows = DotacionDocenteAsignacion::query()->orderBy('id')->get();
        $rows->each(fn ($row) => $row->anio = 2026);
        $this->assertSame(38.0, (float) DotacionContratoPlanCalculator::consolidar($rows)->sum('horas_contrato'));
        $rows->each(function ($row): void { $row->anio = 2027; $row->estamento_cobertura = 'asistente'; });
        $this->assertSame(38.0, (float) DotacionContratoPlanCalculator::consolidar($rows)->sum('horas_contrato'));
        $fila = ['id' => 1, 'anio' => 2027, 'establecimiento_id' => 1, 'docente_rut' => '99000001K',
            'tipo_asignacion' => 'plan_estudio', 'proporcion_aplicada' => 'NT JEC · CPEIP 65/35',
            'horas_plan_pedagogicas' => 30, 'horas_contrato' => 35, 'establecimiento_curso_id' => 999];
        $this->assertSame($fila, DotacionContratoParvulariaCalculator::consolidar(collect([$fila]))->sole());
    }

    public function test_no_mezcla_docentes_cursos_anios_ni_grupos_combinados(): void
    {
        $fila = ['id' => 1, 'anio' => 2027, 'establecimiento_id' => 1, 'docente_rut' => '99000001K',
            'tipo_asignacion' => 'plan_estudio', 'proporcion_aplicada' => 'NT Con JEC · base contractual 55 h',
            'horas_plan_pedagogicas' => 3, 'horas_contrato' => 4.34, 'establecimiento_curso_id' => 1,
            'fuente_calculo' => 'Reparto proporcional: 3 / 38 h de plan × 55 h de contrato.'];
        $otras = [array_replace($fila, ['id' => 2, 'docente_rut' => '99000002K']),
            array_replace($fila, ['id' => 3, 'establecimiento_curso_id' => 2]),
            array_replace($fila, ['id' => 4, 'anio' => 2028]),
            array_replace($fila, ['id' => 5, 'dotacion_curso_combinado_id' => 1]),
            array_replace($fila, ['id' => 6, 'dotacion_curso_combinado_id' => 2])];
        $calculadas = DotacionContratoPlanCalculator::consolidar(collect([$fila, ...$otras]));
        $this->assertSame([5.0, 5.0, 4.0, 5.0, 5.0, 4.0], $calculadas->pluck('horas_contrato')->all());
    }

    public function test_base_especial_no_depende_de_que_exista_tabla_cpeip(): void
    {
        Schema::drop('docente_horas_proporciones'); // Sólo SQLite aislado.
        $this->assertSame(44.0, $this->payload(30)['horas_contrato']);
    }

    public function test_nt1_y_nt2_sin_jec_conservan_sus_bases_distintas(): void
    {
        DB::table('establecimiento_cursos')->where('id', 1)->update(['regimen_jec' => 'Sin JEC']);
        DB::table('planes_estudio')->where('id', 1)->update(['regimen_jec' => 'Sin JEC',
            'nombre_plan' => 'NT1 Sin JEC', 'horas_semanales_total' => 32]);
        $this->assertSame(33.0, $this->payload(30)['horas_contrato']);
        DB::table('cursos')->where('id', 1)->update(['codigo' => 'NT2', 'nombre' => 'NT2']);
        DB::table('establecimiento_cursos')->where('id', 1)->update(['nombre_seccion' => 'NT2 A']);
        $payload = $this->payload(30);
        $this->assertSame(30.0, $payload['horas_contrato']);
        $this->assertSame('NT Sin JEC · base contractual 31 h', $payload['proporcion_aplicada']);
    }

    public function test_limite_de_aula_valida_el_contrato_especial_y_no_la_tabla_general(): void
    {
        $metodo = new ReflectionMethod(DotacionAsignacionController::class, 'validateLimiteAulaParvularia');
        $metodo->invoke(new DotacionAsignacionController, Establecimiento::findOrFail(1), $this->payload(27.63));
        try {
            $metodo->invoke(new DotacionAsignacionController, Establecimiento::findOrFail(1), $this->payload(30));
            $this->fail('El aula equivalente a 44 h de contrato supera el límite de 41 h de aula contractual.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('base especial', $exception->errors()['horas_plan_pedagogicas'][0]);
        }
    }

    public function test_recalculo_fallido_no_deja_metadatos_o_importes_actualizados_parcialmente(): void
    {
        $this->cargarCaso();
        $antes = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        $actualizaciones = 0;
        DB::listen(function ($query) use (&$actualizaciones): void {
            if (str_starts_with($query->sql, 'update') && str_contains($query->sql, 'dotacion_docente_asignaciones')
                && ++$actualizaciones === 2) {
                throw new \RuntimeException('Fallo simulado de recálculo');
            }
        });
        try {
            app(DotacionProporcionRecalculationService::class)->recalculate(Establecimiento::findOrFail(1), 2027);
            $this->fail('Se esperaba el fallo simulado.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo simulado de recálculo', $exception->getMessage());
        }
        $this->assertSame($antes, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
    }

    private function payload(float $aula): array
    {
        return (new ReflectionMethod(DotacionAsignacionController::class, 'buildPayload'))->invoke(
            new DotacionAsignacionController, Request::create('/'), Establecimiento::findOrFail(1),
            ['titulo' => 'Pedagogía en Educación de Párvulos', 'rut' => '99000001K',
                'rut_normalizado' => '99000001K', 'nombre' => 'Docente de prueba'],
            ['anio' => 2027, 'tipo_asignacion' => 'plan_estudio', 'estamento_cobertura' => 'docente',
                'establecimiento_curso_id' => 1, 'horas_plan_pedagogicas' => $aula]);
    }

    private function cargarCaso(): void
    {
        // Reproduce sólo las cantidades del caso con identidad y claves sintéticas.
        foreach ([[3, 4], [3, 3], [4, 5], [8, 9], [8, 9], [2, 2], [2, 3]] as $i => [$aula, $contrato]) {
            DB::table('dotacion_docente_asignaciones')->insert([
                'id' => $i + 1, 'anio' => 2027, 'establecimiento_id' => 1, 'estado' => 'activa',
                'docente_rut' => '99000001K', 'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente de prueba',
                'tipo_asignacion' => $i < 5 ? 'plan_estudio' : 'acompanamiento_parvularia',
                'estamento_cobertura' => 'docente', 'proporcion_aplicada' => 'NT JEC · CPEIP 65/35',
                'fuente_calculo' => 'Tabla CPEIP 65/35 · aula NT1/NT2 JEC', 'establecimiento_curso_id' => 1,
                'declaracion_sostenedor_id' => 1, 'horas_plan_pedagogicas' => $aula, 'horas_contrato' => $contrato,
            ]);
        }
        DB::table('dotacion_docente_asignaciones')->insert(['id' => 8, 'anio' => 2027, 'establecimiento_id' => 1,
            'estado' => 'activa', 'docente_rut' => '99000001K', 'docente_rut_normalizado' => '99000001K',
            'tipo_asignacion' => 'pie_colaborativo', 'estamento_cobertura' => 'docente', 'horas_contrato' => 3]);
    }

    private function resetCaches(): void
    {
        foreach ([DotacionAsignacionCalculator::class, DotacionEstablecimientoCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) {
                (new ReflectionProperty($class, $name))->setValue(null, []);
            }
        }
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
    }
}
