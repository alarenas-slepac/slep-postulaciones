<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TipoReemplazoMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('solicitudes_reemplazo', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_reemplazo', 80);
        });
    }

    public function test_it_renames_historical_medical_license_types(): void
    {
        DB::table('solicitudes_reemplazo')->insert([
            ['id' => 1, 'tipo_reemplazo' => 'Licencia Médica (General)'],
            ['id' => 2, 'tipo_reemplazo' => 'Licencia Médica (Pre y/o Post Natal y/o Parental)'],
            ['id' => 3, 'tipo_reemplazo' => 'Sumario Administrativo'],
        ]);

        (require base_path('database/migrations/2026_09_23_120000_rename_licencias_medicas_solicitudes_reemplazo.php'))->up();

        $this->assertDatabaseHas('solicitudes_reemplazo', [
            'id' => 1,
            'tipo_reemplazo' => '1. Enfermedad o accidente común',
        ]);
        $this->assertDatabaseHas('solicitudes_reemplazo', [
            'id' => 2,
            'tipo_reemplazo' => '3. Licencia maternal pre y postnatal.',
        ]);
        $this->assertDatabaseHas('solicitudes_reemplazo', [
            'id' => 3,
            'tipo_reemplazo' => 'Sumario Administrativo',
        ]);
    }
}
