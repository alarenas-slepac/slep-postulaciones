<?php

namespace Tests\Feature;

use App\Models\IdoneidadPsicologicaFuncionario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IdoneidadPsicologicaMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('idoneidad_psicologica_funcionarios');
        Schema::dropIfExists('idoneidad_psicologica_solicitudes');
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('establecimientos', fn (Blueprint $table) => $table->id());
        Schema::create('reemplazos_personal', fn (Blueprint $table) => $table->id());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('idoneidad_psicologica_funcionarios');
        Schema::dropIfExists('idoneidad_psicologica_solicitudes');
        Schema::dropIfExists('reemplazos_personal');
        Schema::dropIfExists('establecimientos');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_crea_proceso_y_nomina_historica_con_claves_foraneas_cortas(): void
    {
        $migration = require base_path('database/migrations/2026_09_23_100000_create_idoneidad_psicologica_solicitudes_tables.php');
        $migration->up();
        $cargoSnapshotMigration = require base_path('database/migrations/2026_09_23_110000_add_cargo_snapshot_to_idoneidad_psicologica_funcionarios_table.php');
        $cargoSnapshotMigration->up();

        $this->assertTrue(Schema::hasTable('idoneidad_psicologica_solicitudes'));
        $this->assertTrue(Schema::hasTable('idoneidad_psicologica_funcionarios'));
        $this->assertTrue(Schema::hasColumn('idoneidad_psicologica_funcionarios', 'rut_normalizado'));
        $this->assertTrue(Schema::hasColumn('idoneidad_psicologica_funcionarios', 'comuna'));
        $this->assertTrue(Schema::hasColumn('idoneidad_psicologica_funcionarios', 'cargo_clave'));
        $this->assertTrue(Schema::hasColumn('idoneidad_psicologica_funcionarios', 'solicitud_reemplazo_id'));

        $solicitudId = DB::table('idoneidad_psicologica_solicitudes')->insertGetId([
            'fecha_inicio' => '2026-09-01',
            'fecha_termino' => '2026-09-30',
            'estado' => 'solicitada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('idoneidad_psicologica_funcionarios')->insert([
            'solicitud_id' => $solicitudId,
            'establecimiento_id' => null,
            'rut' => '11.111.111-1',
            'rut_normalizado' => '11111111',
            'nombre' => 'Persona de prueba',
            'comuna' => 'STA. JUANA',
            'estado' => 'solicitado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, DB::table('idoneidad_psicologica_funcionarios')->count());
        $this->assertSame('Santa Juana', IdoneidadPsicologicaFuncionario::query()->sole()->comuna);
    }
}
