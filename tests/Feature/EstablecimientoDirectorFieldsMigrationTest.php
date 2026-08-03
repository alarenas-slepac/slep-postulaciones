<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EstablecimientoDirectorFieldsMigrationTest extends TestCase
{
    public function test_adds_director_fields_and_backfills_historical_admission_data(): void
    {
        $this->createPrerequisites();

        $migration = require database_path('migrations/2026_08_03_120000_add_director_contact_to_establecimientos.php');

        try {
            $migration->up();

            DB::table('establecimientos')->where('id', 2)->update([
                'director_nombre' => 'Director actualizado manualmente',
                'director_contacto' => 'contacto.actualizado@liceo.cl',
            ]);
            DB::table('admision_establecimientos')->where('establecimiento_id', 2)->update([
                'director_nombre' => 'Nombre histórico distinto',
                'email_publico' => 'historico@liceo.cl',
            ]);

            $migration->up();

            $this->assertTrue(Schema::hasColumn('establecimientos', 'director_nombre'));
            $this->assertTrue(Schema::hasColumn('establecimientos', 'director_contacto'));
            $this->assertDatabaseHas('establecimientos', [
                'id' => 1,
                'director_nombre' => 'María González',
                'director_contacto' => 'direccion@escuela.cl',
            ]);
            $this->assertDatabaseHas('establecimientos', [
                'id' => 2,
                'director_nombre' => 'Director actualizado manualmente',
                'director_contacto' => 'contacto.actualizado@liceo.cl',
            ]);
        } finally {
            $migration->down();
            Schema::dropIfExists('admision_establecimientos');
            Schema::dropIfExists('establecimientos');
        }
    }

    private function createPrerequisites(): void
    {
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_establecimiento');
        });
        Schema::create('admision_establecimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->string('director_nombre')->nullable();
            $table->string('email_publico')->nullable();
            $table->string('telefono_publico')->nullable();
        });

        DB::table('establecimientos')->insert([
            ['id' => 1, 'nombre_establecimiento' => 'Escuela Uno'],
            ['id' => 2, 'nombre_establecimiento' => 'Escuela Dos'],
        ]);
        DB::table('admision_establecimientos')->insert([
            [
                'establecimiento_id' => 1,
                'director_nombre' => 'María González',
                'email_publico' => 'direccion@escuela.cl',
                'telefono_publico' => '+56 41 111 2233',
            ],
            [
                'establecimiento_id' => 2,
                'director_nombre' => 'Director ya registrado',
                'email_publico' => null,
                'telefono_publico' => '+56 41 222 3344',
            ],
        ]);
    }
}
