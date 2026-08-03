<?php

namespace Tests\Unit;

use App\Support\Messaging\EstablecimientoDirectory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EstablecimientoDirectoryTest extends TestCase
{
    public function test_filters_establishments_by_director_contact_rbd_and_comuna(): void
    {
        $this->createEstablecimientosTable();

        try {
            DB::table('establecimientos')->insert([
                [
                    'rbd' => 1001,
                    'nombre_establecimiento' => 'Escuela Isla Santa María',
                    'comuna' => 'Coronel',
                    'director_nombre' => 'Ana Pérez',
                    'director_contacto' => 'ana.perez@escuela.cl',
                ],
                [
                    'rbd' => 2002,
                    'nombre_establecimiento' => 'Liceo Costa Sur',
                    'comuna' => 'Lota',
                    'director_nombre' => 'Luis Soto',
                    'director_contacto' => '+56 41 234 5678',
                ],
            ]);

            $porDirector = EstablecimientoDirectory::items(['q' => 'Ana Pérez']);
            $porContacto = EstablecimientoDirectory::items(['q' => '234 5678']);
            $porRbdYComuna = EstablecimientoDirectory::items(['q' => '2002', 'comuna' => 'Lota']);

            $this->assertSame('Escuela Isla Santa María', $porDirector->sole()['name']);
            $this->assertSame('Liceo Costa Sur', $porContacto->sole()['name']);
            $this->assertSame('Liceo Costa Sur', $porRbdYComuna->sole()['name']);
            $this->assertSame(['Coronel', 'Lota'], EstablecimientoDirectory::comunas()->all());
        } finally {
            Schema::dropIfExists('establecimientos');
        }
    }

    private function createEstablecimientosTable(): void
    {
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('rbd');
            $table->string('nombre_establecimiento');
            $table->string('comuna')->nullable();
            $table->string('director_nombre')->nullable();
            $table->string('director_contacto')->nullable();
        });
    }
}
