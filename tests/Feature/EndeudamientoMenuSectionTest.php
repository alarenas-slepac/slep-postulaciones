<?php

namespace Tests\Feature;

use App\Support\ModuleRegistry;
use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EndeudamientoMenuSectionTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('modules');
        parent::tearDown();
    }

    public function test_endeudamiento_se_muestra_en_remuneraciones_y_no_en_gestion_y_control(): void
    {
        $usuario = new class
        {
            public function canModule(string $module, ?string $role = null): bool
            {
                return true;
            }
        };

        $grupos = SlepUiRegistry::menuGroups($usuario, 'funcionario_slep');
        $remuneraciones = collect($grupos['Remuneraciones'] ?? [])->pluck('label');
        $gestion = collect($grupos['Gestión y control'] ?? [])->pluck('label');

        $this->assertSame('Remuneraciones', ModuleRegistry::defaultMeta('endeudamiento')['section']);
        $this->assertContains('Endeudamiento', $remuneraciones);
        $this->assertNotContains('Endeudamiento', $gestion);
    }

    public function test_migracion_actualiza_la_seccion_persistida_sin_alterar_el_modulo(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('section');
            $table->timestamps();
        });
        DB::table('modules')->insert([
            'key' => 'endeudamiento',
            'name' => 'Endeudamiento',
            'section' => 'Operación',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migracion = require database_path('migrations/2026_08_21_090000_move_endeudamiento_module_to_remuneraciones.php');
        $migracion->up();

        $this->assertDatabaseHas('modules', [
            'key' => 'endeudamiento',
            'name' => 'Endeudamiento',
            'section' => 'Remuneraciones',
        ]);

        $migracion->down();
        $this->assertDatabaseHas('modules', [
            'key' => 'endeudamiento',
            'section' => 'Operación',
        ]);
    }
}
