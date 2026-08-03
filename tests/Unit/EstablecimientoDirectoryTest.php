<?php

namespace Tests\Unit;

use App\Support\Messaging\EstablecimientoDirectory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EstablecimientoDirectoryTest extends TestCase
{
    public function test_filters_establishments_by_director_contact_rbd_and_comuna(): void
    {
        $this->createEstablecimientosTable();
        $this->createAdmisionEstablecimientosTable();

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
            DB::table('admision_establecimientos')->insert([
                'establecimiento_id' => 1,
                'logo_path' => 'admision/establecimientos/1001/logo.webp',
            ]);

            $porDirector = EstablecimientoDirectory::items(['q' => 'Ana Pérez']);
            $porContacto = EstablecimientoDirectory::items(['q' => '234 5678']);
            $porRbdYComuna = EstablecimientoDirectory::items(['q' => '2002', 'comuna' => 'Lota']);

            $this->assertSame('Escuela Isla Santa María', $porDirector->sole()['name']);
            $this->assertSame(
                Storage::disk(config('admision.media_disk', 'public'))->url('admision/establecimientos/1001/logo.webp'),
                $porDirector->sole()['logo_url']
            );
            $this->assertSame('Liceo Costa Sur', $porContacto->sole()['name']);
            $this->assertNull($porContacto->sole()['logo_url']);
            $this->assertSame('Liceo Costa Sur', $porRbdYComuna->sole()['name']);
            $this->assertSame(['Coronel', 'Lota'], EstablecimientoDirectory::comunas()->all());
        } finally {
            Schema::dropIfExists('admision_establecimientos');
            Schema::dropIfExists('establecimientos');
        }
    }

    public function test_directory_remains_available_without_admission_profiles_table(): void
    {
        $this->createEstablecimientosTable();

        try {
            DB::table('establecimientos')->insert([
                'rbd' => 3003,
                'nombre_establecimiento' => 'Escuela sin perfil de admisión',
                'comuna' => 'Santa Juana',
                'director_nombre' => null,
                'director_contacto' => null,
            ]);

            $item = EstablecimientoDirectory::items()->sole();

            $this->assertSame('Escuela sin perfil de admisión', $item['name']);
            $this->assertNull($item['logo_url']);
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

    private function createAdmisionEstablecimientosTable(): void
    {
        Schema::create('admision_establecimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->string('logo_path')->nullable();
        });
    }
}
