<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionProceso2027TpPlanesTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id();
            $t->integer('rbd');
            $t->string('nombre_establecimiento');
        });
        Schema::create('cursos', function (Blueprint $t): void {
            $t->id();
            $t->string('nombre');
        });
        Schema::create('planes_estudio', function (Blueprint $t): void {
            $t->id();
            $t->decimal('horas_semanales_libre_disposicion', 8, 2);
            $t->decimal('horas_semanales_total', 8, 2);
        });
        Schema::create('planes_estudio_bloques', function (Blueprint $t): void {
            $t->id();
            $t->integer('plan_estudio_id');
            $t->string('tipo_bloque');
            $t->integer('orden');
            $t->decimal('horas_semanales', 8, 2);
            $t->boolean('activo');
            $t->boolean('permite_asignaturas_establecimiento');
            $t->boolean('permite_asignaturas_personalizadas');
        });
        Schema::create('establecimiento_cursos', function (Blueprint $t): void {
            $t->id();
            $t->integer('establecimiento_id');
            $t->integer('curso_id');
            $t->integer('plan_estudio_id');
            $t->integer('anio');
            $t->string('nombre_seccion');
            $t->string('letra');
            $t->integer('matricula');
            $t->boolean('activo');
        });
        Schema::create('establecimiento_planes_estudio', function (Blueprint $t): void {
            $t->id();
            $t->integer('establecimiento_id');
            $t->integer('establecimiento_curso_id');
            $t->integer('plan_estudio_id');
            $t->integer('curso_id');
            $t->integer('anio');
            $t->string('estado');
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $t): void {
            $t->id();
            $t->integer('establecimiento_plan_estudio_id');
            $t->integer('plan_estudio_bloque_id');
            $t->decimal('horas_semanales', 8, 2);
        });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'EE sintético TP']);
        DB::table('cursos')->insert(['id' => 1, 'nombre' => '3° Medio TP']);
        DB::table('planes_estudio')->insert(['id' => 1, 'horas_semanales_libre_disposicion' => 6, 'horas_semanales_total' => 42]);
        foreach ([
            [1, 'plan_comun_formacion_general_electivo', 2, true],
            [2, 'plan_diferenciado_tp', 22, true],
            [3, 'libre_disposicion', 4, true],
            [4, 'plan_comun_formacion_general', 14, false],
            [5, 'total', 42, false],
        ] as [$id, $tipo, $horas, $editable]) {
            DB::table('planes_estudio_bloques')->insert(['id' => $id, 'plan_estudio_id' => 1, 'tipo_bloque' => $tipo,
                'orden' => $id, 'horas_semanales' => $horas, 'activo' => true,
                'permite_asignaturas_establecimiento' => $editable, 'permite_asignaturas_personalizadas' => $editable]);
        }
        $this->curso(1, 1, 1, '3° Medio TP A');
        $this->horas(1, [2]);
        $this->horas(2, [6, 6, 6, 2, 2]);
        $this->horas(3, [2, 2]);
    }

    public static function grados(): array
    {
        return [['3° Medio TP'], ['4° Medio TP']];
    }

    #[DataProvider('grados')]
    public function test_acepta_las_42_horas_con_electivo_2_especialidad_22_y_libre_restante_4(string $grado): void
    {
        DB::table('cursos')->where('id', 1)->update(['nombre' => $grado]);
        DB::table('establecimiento_cursos')->where('id', 1)->update(['nombre_seccion' => $grado.' A']);
        $antes = DB::table('establecimiento_planes_estudio_asignaturas')->orderBy('id')->get()->toJson();
        $estado = $this->estado();

        $this->assertTrue($estado['completo']);
        $this->assertSame(1, $estado['configurados']);
        $this->assertSame([], $estado['pendientes']);
        $this->assertSame(0, $estado['libre_disposicion_pendiente']);
        $this->assertSame($antes, DB::table('establecimiento_planes_estudio_asignaturas')->orderBy('id')->get()->toJson());
        $this->assertSame(6.0, (float) DB::table('planes_estudio')->value('horas_semanales_libre_disposicion'));
    }

    public function test_los_13_tp_completos_no_quedan_pendientes_entre_27_cursos(): void
    {
        $detallesTp = DB::table('establecimiento_planes_estudio_asignaturas')->get()->map(fn ($d) => [
            'plan_estudio_bloque_id' => $d->plan_estudio_bloque_id, 'horas_semanales' => $d->horas_semanales,
        ])->all();
        for ($id = 2; $id <= 13; $id++) {
            $this->curso($id, 1, 1, ($id <= 7 ? '3°' : '4°').' Medio TP '.$id);
            foreach ($detallesTp as $detalle) {
                DB::table('establecimiento_planes_estudio_asignaturas')->insert($detalle + ['establecimiento_plan_estudio_id' => $id]);
            }
        }
        DB::table('cursos')->insert(['id' => 2, 'nombre' => 'Curso general sintético']);
        DB::table('planes_estudio')->insert(['id' => 2, 'horas_semanales_libre_disposicion' => 6, 'horas_semanales_total' => 38]);
        DB::table('planes_estudio_bloques')->insert(['id' => 6, 'plan_estudio_id' => 2, 'tipo_bloque' => 'libre_disposicion',
            'orden' => 1, 'horas_semanales' => 6, 'activo' => true, 'permite_asignaturas_establecimiento' => true, 'permite_asignaturas_personalizadas' => true]);
        for ($id = 14; $id <= 27; $id++) {
            $this->curso($id, 2, 2, 'Curso general '.$id);
            DB::table('establecimiento_planes_estudio_asignaturas')->insert([
                'establecimiento_plan_estudio_id' => $id, 'plan_estudio_bloque_id' => 6, 'horas_semanales' => 6,
            ]);
        }
        $estado = $this->estado();
        $this->assertSame(27, $estado['total']);
        $this->assertSame(27, $estado['configurados']);
        $this->assertSame([], $estado['pendientes']);
        $this->assertSame(0, $estado['libre_disposicion_pendiente']);
        $this->assertTrue($estado['completo']);
    }

    public function test_detecta_la_libre_disposicion_realmente_incompleta(): void
    {
        $this->horas(3, [2, 1]);
        $this->assertFalse($this->estado()['completo']);
        $this->assertSame(1, $this->estado()['libre_disposicion_pendiente']);
    }

    public function test_el_electivo_no_se_puede_sustituir_por_libre_disposicion(): void
    {
        $this->horas(1, [1]);
        $this->assertFalse($this->estado()['completo']);
        $this->assertSame(0, $this->estado()['libre_disposicion_pendiente']);
    }

    public function test_la_especialidad_debe_completar_sus_22_horas(): void
    {
        $this->horas(2, [6, 6, 6, 2, 1]);
        $this->assertFalse($this->estado()['completo']);
        $this->assertSame(0, $this->estado()['libre_disposicion_pendiente']);
    }

    public function test_el_plan_completo_debe_estar_enviado(): void
    {
        DB::table('establecimiento_planes_estudio')->update(['estado' => 'borrador']);
        $this->assertFalse($this->estado()['completo']);
    }

    public function test_conserva_la_cabecera_como_respaldo_si_no_hay_bloque_libre_activo(): void
    {
        DB::table('planes_estudio_bloques')->where('id', 3)->update(['activo' => false]);
        $this->assertFalse($this->estado()['completo']);
        $this->assertSame(1, $this->estado()['libre_disposicion_pendiente']);
    }

    public function test_un_bloque_libre_activo_en_cero_no_hereda_horas_de_la_cabecera(): void
    {
        DB::table('planes_estudio_bloques')->where('id', 3)->update(['horas_semanales' => 0,
            'permite_asignaturas_establecimiento' => false, 'permite_asignaturas_personalizadas' => false]);
        DB::table('planes_estudio')->update(['horas_semanales_total' => 38]);
        $this->horas(3, []);
        $this->assertTrue($this->estado()['completo']);
        $this->assertSame(0, $this->estado()['libre_disposicion_pendiente']);
    }

    private function horas(int $bloque, array $horas): void
    {
        DB::table('establecimiento_planes_estudio_asignaturas')->where('establecimiento_plan_estudio_id', 1)->where('plan_estudio_bloque_id', $bloque)->delete();
        foreach ($horas as $valor) {
            DB::table('establecimiento_planes_estudio_asignaturas')->insert([
                'establecimiento_plan_estudio_id' => 1, 'plan_estudio_bloque_id' => $bloque, 'horas_semanales' => $valor,
            ]);
        }
    }

    private function curso(int $id, int $curso, int $plan, string $nombre): void
    {
        DB::table('establecimiento_cursos')->insert(['id' => $id, 'establecimiento_id' => 1, 'curso_id' => $curso,
            'plan_estudio_id' => $plan, 'anio' => 2027, 'letra' => 'A', 'nombre_seccion' => $nombre, 'matricula' => 20, 'activo' => true]);
        DB::table('establecimiento_planes_estudio')->insert(['id' => $id, 'establecimiento_id' => 1, 'curso_id' => $curso,
            'plan_estudio_id' => $plan, 'establecimiento_curso_id' => $id, 'anio' => 2027, 'estado' => 'enviado']);
    }

    private function estado(): array
    {
        $proceso = DotacionProceso2027Calculator::resumen(Establecimiento::findOrFail(1), 2027, [
            'cursos' => ['totales' => ['cursos' => DB::table('establecimiento_cursos')->count(), 'sin_horas_plan' => 0]],
            'asignacion' => ['necesidades' => [], 'asignaciones' => []], 'docentes' => [],
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ]);

        return $proceso['estado_planes'];
    }
}
