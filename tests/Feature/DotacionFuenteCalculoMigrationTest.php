<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DotacionFuenteCalculoMigrationTest extends TestCase
{
    public function test_amplia_fuente_calculo_y_conserva_las_explicaciones_historicas(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->string('fuente_calculo')->nullable();
        });

        DB::table('dotacion_docente_asignaciones')->insert(['fuente_calculo' => 'Cálculo histórico']);

        $migration = require database_path('migrations/2026_09_28_100000_expand_dotacion_fuente_calculo_column.php');
        $migration->up();

        $this->assertSame('text', Schema::getColumnType('dotacion_docente_asignaciones', 'fuente_calculo'));
        $explicacion = 'Conversión NT1/NT2 según profesión declarada · '
            .'Base contractual completa de Educación Parvularia · '
            .str_repeat('Reparto proporcional de horas del plan y contrato. ', 7);
        $this->assertGreaterThan(255, strlen($explicacion));
        DB::table('dotacion_docente_asignaciones')->insert(['fuente_calculo' => $explicacion]);

        $this->assertSame(
            ['Cálculo histórico', $explicacion],
            DB::table('dotacion_docente_asignaciones')->orderBy('id')->pluck('fuente_calculo')->all()
        );
    }
}
