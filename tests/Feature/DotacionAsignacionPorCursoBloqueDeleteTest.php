<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class DotacionAsignacionPorCursoBloqueDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedSmallInteger('anio');
            $table->string('estado');
            $table->string('docente_rut_normalizado');
            $table->timestamps();
        });
        DB::table('dotacion_docente_asignaciones')->insert([
            ['id' => 1, 'establecimiento_id' => 10, 'anio' => 2027, 'estado' => 'activa', 'docente_rut_normalizado' => '111111111'],
            ['id' => 2, 'establecimiento_id' => 10, 'anio' => 2027, 'estado' => 'activa', 'docente_rut_normalizado' => '222222222'],
            ['id' => 3, 'establecimiento_id' => 20, 'anio' => 2027, 'estado' => 'activa', 'docente_rut_normalizado' => '333333333'],
            ['id' => 4, 'establecimiento_id' => 10, 'anio' => 2026, 'estado' => 'activa', 'docente_rut_normalizado' => '444444444'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dotacion_docente_asignaciones');
        parent::tearDown();
    }

    public function test_elimina_lote_del_establecimiento_y_anio_sin_afectar_otros_registros(): void
    {
        $cantidad = $this->deleteBatch([1, 2]);

        $this->assertSame(2, $cantidad);
        $this->assertSame([3, 4], DB::table('dotacion_docente_asignaciones')->orderBy('id')->pluck('id')->all());
    }

    public function test_un_id_de_otro_establecimiento_cancela_todo_el_lote(): void
    {
        try {
            $this->deleteBatch([1, 3]);
            $this->fail('El lote debe rechazarse si contiene una asignación ajena.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('curso_label', $exception->errors());
        }

        $this->assertSame([1, 2, 3, 4], DB::table('dotacion_docente_asignaciones')->orderBy('id')->pluck('id')->all());
    }

    public function test_un_id_de_otro_anio_cancela_todo_el_lote(): void
    {
        try {
            $this->deleteBatch([1, 4]);
            $this->fail('El lote debe rechazarse si contiene una asignación de otro año.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('curso_label', $exception->errors());
        }

        $this->assertSame([1, 2, 3, 4], DB::table('dotacion_docente_asignaciones')->orderBy('id')->pluck('id')->all());
    }

    private function deleteBatch(array $ids): int
    {
        $establecimiento = new Establecimiento();
        $establecimiento->id = 10;

        return (new ReflectionMethod(DotacionAsignacionController::class, 'deleteCourseBlockAssignments'))
            ->invoke(app(DotacionAsignacionController::class), $establecimiento, 2027, DotacionDocenteAsignacion::findMany($ids));
    }
}
