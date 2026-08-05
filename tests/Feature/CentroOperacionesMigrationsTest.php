<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentroOperacionesMigrationsTest extends TestCase
{
    public function test_migrations_create_the_module_and_operational_schema(): void
    {
        $this->createPrerequisites();

        $matricula = require database_path('migrations/2026_07_31_120000_add_matricula_total_to_establecimientos.php');
        $operaciones = require database_path('migrations/2026_07_31_121000_create_centro_operaciones_tables.php');
        $extension = require database_path('migrations/2026_08_05_120000_extend_centro_operaciones_daily_reports.php');
        $modulo = require database_path('migrations/2026_07_31_122000_register_centro_operaciones_module.php');

        try {
            $matricula->up();
            $operaciones->up();
            $extension->up();
            $modulo->up();

            $this->assertTrue(Schema::hasColumn('establecimientos', 'matricula_total'));
            $this->assertTrue(Schema::hasTable('centro_operaciones_reportes'));
            $this->assertTrue(Schema::hasTable('centro_operaciones_incidencias'));
            $this->assertTrue(Schema::hasColumn('centro_operaciones_reportes', 'unidad_codigo'));
            $this->assertTrue(Schema::hasColumn('centro_operaciones_reportes', 'fecha_control_plagas'));
            $this->assertTrue(Schema::hasColumn('centro_operaciones_incidencias', 'modalidad'));
            $this->assertDatabaseHas('modules', ['key' => 'centro-operaciones']);
            $this->assertSame(6, DB::table('module_role')->count());
        } finally {
            $modulo->down();
            $extension->down();
            $operaciones->down();
            $matricula->down();
            Schema::dropIfExists('module_role');
            Schema::dropIfExists('roles');
            Schema::dropIfExists('modules');
            Schema::dropIfExists('users');
            Schema::dropIfExists('establecimientos');
        }
    }

    private function createPrerequisites(): void
    {
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('asignacion_zona')->default(0);
        });
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('section');
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('module_role', function (Blueprint $table) {
            $table->foreignId('module_id');
            $table->foreignId('role_id');
            $table->timestamps();
            $table->unique(['module_id', 'role_id']);
        });

        DB::table('roles')->insert(collect([
            'admin',
            'director_ejecutivo',
            'funcionario_slep',
            'coordinador_gdp',
            'coordinador_uatp',
            'funcionario_directivo_estab',
        ])->map(fn (string $name) => ['name' => $name])->all());
    }
}
