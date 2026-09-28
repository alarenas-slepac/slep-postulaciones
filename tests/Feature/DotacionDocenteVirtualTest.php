<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class DotacionDocenteVirtualTest extends TestCase
{
    public function test_cupo_pie_es_docente_provisional_asignable_y_no_se_puede_retirar_con_horas(): void
    {
        $this->createTables();

        try {
            $establecimiento = Establecimiento::query()->create(['nombre_establecimiento' => 'Escuela de prueba']);
            $cupoId = DB::table('dotacion_contrata_habilitaciones')->insertGetId([
                'establecimiento_id' => $establecimiento->id,
                'anio' => 2026,
                'bloque' => 'pie',
                'horas' => 30,
            ]);
            $this->resetSchemaCache();
            $service = app(ContratacionHabilitacionService::class);
            $docente = $service->docenteVirtual($establecimiento, 2026, "VACANTE-$cupoId");
            $this->assertSame("VACANTE_$cupoId", DotacionEstablecimientoCalculator::normalizeRut("VACANTE-$cupoId"));
            $this->assertSame('pie', $docente['cupo_bloque']);
            $this->assertSame(30.0, $docente['horas_contrato']);

            $controller = app(DotacionAsignacionController::class);
            $controller->store($this->request($cupoId, 20), $establecimiento);
            $this->assertDatabaseHas('dotacion_docente_asignaciones', [
                'docente_rut_normalizado' => "VACANTE_$cupoId",
                'horas_contrato' => 20,
                'tipo_asignacion' => 'pie_educadora_diferencial',
            ]);
            $contratoVigentePie = DotacionAsignacionCalculator::resumenContratoDocentePie(
                DotacionAsignacionCalculator::assignmentsFor($establecimiento, 2026),
                collect()
            );
            $this->assertSame(0.0, $contratoVigentePie['total']);
            $this->assertSame(10.0, $service->docenteVirtual($establecimiento, 2026, "VACANTE-$cupoId")['diferencia']);

            try {
                $controller->store($this->request($cupoId, 11), $establecimiento);
                $this->fail('El cupo no puede superar las 30 horas.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('horas_contrato', $exception->errors());
            }

            try {
                $service->revocar($establecimiento, 2026, $cupoId);
                $this->fail('Un cupo con asignaciones no se puede retirar.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('habilitacion', $exception->errors());
            }
            DB::table('dotacion_docente_asignaciones')->delete();
            $this->assertTrue($service->revocar($establecimiento, 2026, $cupoId));
        } finally {
            $this->dropTables();
        }
    }

    public function test_el_cupo_parvularia_solo_admite_necesidades_nt_y_no_pie_especializado(): void
    {
        $this->createTables();

        try {
            $establecimiento = Establecimiento::query()->create(['nombre_establecimiento' => 'Escuela de prueba']);
            $cupoId = DB::table('dotacion_contrata_habilitaciones')->insertGetId([
                'establecimiento_id' => $establecimiento->id,
                'anio' => 2026,
                'bloque' => 'parvularia',
                'horas' => 30,
            ]);
            $cursoNtId = DB::table('cursos')->insertGetId(['codigo' => 'NT1']);
            $cursoBasicaId = DB::table('cursos')->insertGetId(['codigo' => '1B']);
            $establecimientoCursoNt = DB::table('establecimiento_cursos')->insertGetId(['establecimiento_id' => $establecimiento->id, 'curso_id' => $cursoNtId]);
            $establecimientoCursoBasica = DB::table('establecimiento_cursos')->insertGetId(['establecimiento_id' => $establecimiento->id, 'curso_id' => $cursoBasicaId]);
            $this->resetSchemaCache();
            $persona = app(ContratacionHabilitacionService::class)->docenteVirtual($establecimiento, 2026, "VACANTE-$cupoId");
            $validar = new ReflectionMethod(DotacionAsignacionController::class, 'validateVirtualAssignment');
            $controller = app(DotacionAsignacionController::class);

            $validar->invoke($controller, $establecimiento, $persona, [
                'anio' => 2026, 'tipo_asignacion' => 'plan_estudio',
                'establecimiento_curso_id' => $establecimientoCursoNt, 'horas_contrato' => 30,
            ]);
            $this->addToAssertionCount(1);
            $validar->invoke($controller, $establecimiento, $persona, [
                'anio' => 2026, 'tipo_asignacion' => 'acompanamiento_parvularia',
                'subtipo_asignacion' => 'libre_disposicion',
                'establecimiento_curso_id' => $establecimientoCursoNt, 'horas_contrato' => 8,
            ]);
            $this->addToAssertionCount(1);

            foreach ([
                ['tipo_asignacion' => 'plan_estudio', 'establecimiento_curso_id' => $establecimientoCursoBasica],
                ['tipo_asignacion' => 'acompanamiento_parvularia', 'establecimiento_curso_id' => $establecimientoCursoBasica],
                ['tipo_asignacion' => 'pie_educadora_diferencial', 'establecimiento_curso_id' => null],
            ] as $noPermitida) {
                try {
                    $validar->invoke($controller, $establecimiento, $persona, [
                        'anio' => 2026, 'horas_contrato' => 10, ...$noPermitida,
                    ]);
                    $this->fail('El cupo de Parvularia no debe cubrir otro bloque.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('docente_rut', $exception->errors());
                }
            }
        } finally {
            $this->dropTables();
        }
    }

    private function request(int $cupoId, float $horas): Request
    {
        $request = Request::create('/dotacion/asignaciones', 'POST', [
            'anio' => 2026,
            'docente_rut' => "VACANTE-$cupoId",
            'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'pie_educadora_diferencial',
            'horas_contrato' => $horas,
        ]);
        $request->setUserResolver(fn () => new class
        {
            public int $id = 7;

            public function activeRoleName(): string
            {
                return 'admin';
            }
        });

        return $request;
    }

    private function createTables(): void
    {
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_establecimiento');
            $table->timestamps();
        });
        Schema::create('dotacion_contrata_habilitaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->unsignedSmallInteger('anio');
            $table->string('bloque', 20);
            $table->decimal('horas', 5, 2);
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->foreignId('establecimiento_id');
            $table->string('docente_rut', 32);
            $table->string('docente_rut_normalizado', 32);
            $table->string('docente_nombre')->nullable();
            $table->unsignedBigInteger('reemplazos_personal_id')->nullable();
            $table->unsignedBigInteger('declaracion_sostenedor_id')->nullable();
            $table->string('estamento_cobertura')->default('docente');
            $table->string('tipo_asignacion', 64);
            $table->string('subtipo_asignacion', 64)->nullable();
            $table->string('subvencion', 80)->nullable();
            $table->string('necesidad_key', 180)->nullable();
            $table->unsignedBigInteger('establecimiento_curso_id')->nullable();
            $table->unsignedBigInteger('dotacion_curso_combinado_id')->nullable();
            $table->unsignedBigInteger('dotacion_curso_combinado_asignatura_id')->nullable();
            $table->unsignedBigInteger('plan_estudio_id')->nullable();
            $table->unsignedBigInteger('plan_bloque_id')->nullable();
            $table->unsignedBigInteger('asignatura_id')->nullable();
            $table->string('asignatura_nombre')->nullable();
            $table->unsignedBigInteger('dotacion_funcion_id')->nullable();
            $table->unsignedBigInteger('dotacion_funcion_regla_id')->nullable();
            $table->decimal('horas_plan_pedagogicas', 8, 2)->nullable();
            $table->decimal('horas_contrato', 8, 2);
            $table->decimal('horas_cronologicas_aula', 8, 2)->nullable();
            $table->string('proporcion_aplicada')->nullable();
            $table->text('fuente_calculo')->nullable();
            $table->text('observacion')->nullable();
            $table->text('excepcion_prelacion')->nullable();
            $table->string('estado')->default('activa');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('cursos', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
        });
        Schema::create('establecimiento_cursos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->foreignId('curso_id');
        });
    }

    private function dropTables(): void
    {
        Schema::dropIfExists('establecimiento_cursos');
        Schema::dropIfExists('cursos');
        Schema::dropIfExists('dotacion_docente_asignaciones');
        Schema::dropIfExists('dotacion_contrata_habilitaciones');
        Schema::dropIfExists('establecimientos');
        $this->resetSchemaCache();
    }

    private function resetSchemaCache(): void
    {
        foreach ([DotacionAsignacionCalculator::class, DotacionEstablecimientoCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) {
                (new ReflectionProperty($class, $name))->setValue([]);
            }
        }
    }
}
