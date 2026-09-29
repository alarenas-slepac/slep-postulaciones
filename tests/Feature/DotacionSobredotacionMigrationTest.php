<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DotacionSobredotacionMigrationTest extends TestCase
{
    public function test_crea_la_tabla_con_clave_foranea_corta_e_indices_sin_duplicarlos(): void
    {
        Schema::create('establecimientos', fn (Blueprint $table) => $table->id());

        try {
            $migration = require database_path('migrations/2026_09_29_190000_create_dotacion_sobredotacion_justificaciones_table.php');
            $migration->up();
            $migration->up();

            $this->assertTrue(Schema::hasIndex(
                'dotacion_sobredotacion_justificaciones', 'dsj_est_anio_docente_bloque_tipo_unique'
            ));
            $foreignKeys = collect(Schema::getForeignKeys('dotacion_sobredotacion_justificaciones'));
            $this->assertCount(1, $foreignKeys);
            $this->assertSame(['establecimiento_id'], $foreignKeys->first()['columns']);
            $this->assertLessThanOrEqual(64, strlen('dsj_est_fk'));
        } finally {
            Schema::dropIfExists('dotacion_sobredotacion_justificaciones');
            Schema::dropIfExists('establecimientos');
        }
    }

    public function test_completa_la_tabla_que_quedo_creada_tras_fallar_la_clave_foranea(): void
    {
        Schema::create('establecimientos', fn (Blueprint $table) => $table->id());
        DB::table('establecimientos')->insert(['id' => 1]);
        Schema::create('dotacion_sobredotacion_justificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->unsignedSmallInteger('anio');
            $table->string('docente_rut_normalizado', 32);
            $table->string('bloque', 24);
            $table->string('tipo_horas', 16);
            $table->decimal('horas_detectadas', 8, 2);
            $table->text('justificacion');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
        });
        DB::table('dotacion_sobredotacion_justificaciones')->insert([
            'establecimiento_id' => 1, 'anio' => 2027, 'docente_rut_normalizado' => '111111111',
            'bloque' => 'plan_estudio', 'tipo_horas' => 'titular',
            'horas_detectadas' => 2, 'justificacion' => 'Dato de prueba conservado',
        ]);

        try {
            $migration = require database_path('migrations/2026_09_29_190000_create_dotacion_sobredotacion_justificaciones_table.php');
            $migration->up();
            $migration->up();

            $this->assertSame(1, DB::table('dotacion_sobredotacion_justificaciones')->count());
            $this->assertTrue(Schema::hasIndex(
                'dotacion_sobredotacion_justificaciones', 'dsj_est_anio_docente_bloque_tipo_unique'
            ));
            $this->assertCount(1, Schema::getForeignKeys('dotacion_sobredotacion_justificaciones'));
        } finally {
            Schema::dropIfExists('dotacion_sobredotacion_justificaciones');
            Schema::dropIfExists('establecimientos');
        }
    }
}
