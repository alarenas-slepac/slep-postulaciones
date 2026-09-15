<?php

namespace Tests\Unit;

use App\Models\Establecimiento;
use App\Support\DotacionFuncionesCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function test_transicion_educativa_se_mantiene_en_2026_y_se_excluye_desde_2027(): void
    {
        $establecimiento = Establecimiento::findOrFail(1);

        $funciones2026 = DotacionFuncionesCalculator::sugerencias($establecimiento, 2026);
        $funciones2027 = DotacionFuncionesCalculator::sugerencias($establecimiento, 2027);

        $this->assertSame(
            ['pise', 'transicion_educativa'],
            $funciones2026->pluck('codigo')->all()
        );
        $this->assertSame(['pise'], $funciones2027->pluck('codigo')->all());
    }
}
