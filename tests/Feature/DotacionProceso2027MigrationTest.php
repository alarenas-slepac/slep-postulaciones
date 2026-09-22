<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DotacionProceso2027MigrationTest extends TestCase
{
    public function test_completa_claves_foraneas_si_la_tabla_quedo_parcial_en_un_intento_previo(): void
    {
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->text('observacion')->nullable();
        });
        Schema::create('dotacion_proceso_2027_configuraciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedSmallInteger('anio');
            $table->unsignedBigInteger('combinacion_confirmada_by')->nullable();
            $table->unsignedBigInteger('maximos_configurados_by')->nullable();
        });

        try {
            $migration = require database_path('migrations/2026_09_22_180000_create_dotacion_proceso_2027_configuraciones_table.php');
            $migration->up();
            $migration->up();
            $normativasMigration = require database_path('migrations/2026_09_22_191000_add_funciones_normativas_to_dotacion_proceso_2027_configuraciones_table.php');
            $normativasMigration->up();
            $normativasMigration->up();

            $foreignColumns = collect(Schema::getForeignKeys('dotacion_proceso_2027_configuraciones'))
                ->flatMap(fn (array $foreignKey) => $foreignKey['columns'] ?? [])
                ->all();

            $this->assertEqualsCanonicalizing([
                'establecimiento_id',
                'combinacion_confirmada_by',
                'maximos_configurados_by',
                'funciones_normativas_configuradas_by',
            ], $foreignColumns);
            $this->assertTrue(Schema::hasColumn('dotacion_docente_asignaciones', 'excepcion_prelacion'));
            $this->assertTrue(Schema::hasColumn('dotacion_proceso_2027_configuraciones', 'funciones_normativas'));
        } finally {
            Schema::dropIfExists('dotacion_docente_asignaciones');
            Schema::dropIfExists('dotacion_proceso_2027_configuraciones');
            Schema::dropIfExists('users');
            Schema::dropIfExists('establecimientos');
        }
    }
}
