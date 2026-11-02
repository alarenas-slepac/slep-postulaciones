<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SolicitudReemplazoModificacionTerminoMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('solicitudes_reemplazo', fn (Blueprint $table) => $table->id());
    }

    public function test_it_creates_the_auditable_term_modification_history(): void
    {
        (require base_path('database/migrations/2026_09_23_130000_create_solicitud_reemplazo_modificaciones_termino_table.php'))->up();

        $this->assertTrue(Schema::hasTable('solicitudes_reemplazo_modificaciones_termino'));
        $this->assertTrue(Schema::hasColumns('solicitudes_reemplazo_modificaciones_termino', [
            'causal',
            'fecha_termino_anterior',
            'fecha_termino_nueva',
            'carta_renuncia_path',
            'resolucion_renuncia_path',
            'orden_trabajo_anterior_path',
            'resolucion_docente_docx_anterior_path',
            'resolucion_docente_firmada_anterior_path',
            'finalizada_at',
        ]));
    }

    public function test_licencia_attachment_column_preserves_existing_history(): void
    {
        (require database_path('migrations/2026_09_23_130000_create_solicitud_reemplazo_modificaciones_termino_table.php'))->up();
        DB::table('solicitudes_reemplazo')->insert(['id' => 1]);
        $anterior = ['solicitud_reemplazo_id' => 1, 'causal' => 'renuncia_voluntaria', 'estado_origen' => 'cerrado',
            'fecha_termino_anterior' => '2026-11-30', 'fecha_termino_nueva' => '2026-11-15', 'motivo' => 'Antecedente histórico de prueba',
            'carta_renuncia_path' => 'historico/carta.pdf', 'resolucion_renuncia_path' => 'historico/resolucion.pdf'];
        DB::table('solicitudes_reemplazo_modificaciones_termino')->insert($anterior);
        (require database_path('migrations/2026_11_02_100000_add_respaldo_licencia_to_modificaciones_termino.php'))->up();
        $this->assertDatabaseCount('solicitudes_reemplazo_modificaciones_termino', 1);
        $this->assertDatabaseHas('solicitudes_reemplazo_modificaciones_termino', $anterior + ['respaldo_licencia_path' => null]);
    }
}
