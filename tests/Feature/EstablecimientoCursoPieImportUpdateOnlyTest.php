<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EstablecimientoCursoPieController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EstablecimientoCursoPieImportUpdateOnlyTest extends TestCase
{
    public function test_import_updates_existing_pie_and_reports_rows_without_a_pie_record(): void
    {
        $this->createTables();
        $path = tempnam(sys_get_temp_dir(), 'pie-import-');
        $this->assertNotFalse($path);

        try {
            $this->seedRecords();
            $this->writeImportFile($path);

            $user = new class
            {
                public int $id = 99;
                public ?int $establecimiento_id = null;

                public function activeRoleName(): string
                {
                    return 'admin';
                }
            };

            $request = Request::create(
                '/admin/establecimiento-curso-pie/import',
                'POST',
                ['anio' => 2026],
                [],
                ['archivo' => new UploadedFile($path, 'actualizacion-pie.xlsx', null, null, true)]
            );
            $request->setUserResolver(fn () => $user);

            $response = app(EstablecimientoCursoPieController::class)->importStore($request);
            $result = $response->getData()['importResult'];

            $this->assertSame('admin.establecimiento-curso-pie.import', $response->name());
            $this->assertSame(2, $result['read']);
            $this->assertSame(1, $result['updated']);
            $this->assertSame(1, $result['not_updated']);
            $this->assertSame(3, $result['errors'][0]['fila']);
            $this->assertStringContainsString('solo actualiza registros existentes', $result['errors'][0]['motivo']);

            $this->assertDatabaseHas('establecimiento_curso_pie', [
                'establecimiento_curso_id' => 10,
                'anio' => 2026,
                'necesidades_transitorias' => 0,
                'necesidades_permanentes' => 0,
                'total_pie' => 0,
                'observacion' => 'Actualizado desde plantilla',
                'estado' => 'en_revision',
                'updated_by' => 99,
            ]);
            $this->assertDatabaseMissing('establecimiento_curso_pie', [
                'establecimiento_curso_id' => 11,
                'anio' => 2026,
            ]);
            $this->assertSame(1, DB::table('establecimiento_curso_pie')->count());
        } finally {
            @unlink($path);
            Schema::dropIfExists('establecimiento_curso_pie');
            Schema::dropIfExists('establecimiento_cursos');
            Schema::dropIfExists('cursos');
            Schema::dropIfExists('establecimientos');
        }
    }

    private function createTables(): void
    {
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('rbd');
            $table->string('nombre_establecimiento')->nullable();
            $table->timestamps();
        });

        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo');
            $table->string('nivel_educativo')->nullable();
            $table->string('modalidad')->nullable();
            $table->timestamps();
        });

        Schema::create('establecimiento_cursos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedInteger('rbd')->nullable();
            $table->unsignedBigInteger('curso_id');
            $table->unsignedBigInteger('plan_estudio_id')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->string('letra')->nullable();
            $table->string('nombre_seccion')->nullable();
            $table->unsignedInteger('matricula')->default(0);
            $table->string('regimen_jec')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('establecimiento_curso_pie', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('establecimiento_curso_id');
            $table->unsignedBigInteger('curso_id')->nullable();
            $table->unsignedBigInteger('plan_estudio_id')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->unsignedInteger('rbd')->nullable();
            $table->unsignedSmallInteger('necesidades_transitorias')->default(0);
            $table->unsignedSmallInteger('necesidades_permanentes')->default(0);
            $table->unsignedSmallInteger('total_pie')->default(0);
            $table->text('observacion')->nullable();
            $table->string('estado')->default('borrador');
            $table->string('regimen_calculo')->nullable();
            $table->unsignedSmallInteger('neet_calculo')->nullable();
            $table->unsignedSmallInteger('neep_calculo')->nullable();
            $table->unsignedInteger('total_crono_minutos')->nullable();
            $table->unsignedInteger('prof_educ_dif_minutos')->nullable();
            $table->unsignedInteger('pae_minutos')->nullable();
            $table->text('calculo_observacion')->nullable();
            $table->timestamp('calculado_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    private function seedRecords(): void
    {
        DB::table('establecimientos')->insert([
            'id' => 1,
            'rbd' => 5001,
            'nombre_establecimiento' => 'Establecimiento de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cursos')->insert([
            'id' => 1,
            'nombre' => 'Primero Básico',
            'codigo' => '1B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('establecimiento_cursos')->insert([
            [
                'id' => 10,
                'establecimiento_id' => 1,
                'rbd' => 5001,
                'curso_id' => 1,
                'anio' => 2026,
                'letra' => 'A',
                'nombre_seccion' => '1B A',
                'matricula' => 30,
                'regimen_jec' => 'CON JEC',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'establecimiento_id' => 1,
                'rbd' => 5001,
                'curso_id' => 1,
                'anio' => 2026,
                'letra' => 'B',
                'nombre_seccion' => '1B B',
                'matricula' => 30,
                'regimen_jec' => 'CON JEC',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('establecimiento_curso_pie')->insert([
            'id' => 100,
            'establecimiento_id' => 1,
            'establecimiento_curso_id' => 10,
            'curso_id' => 1,
            'anio' => 2026,
            'rbd' => 5001,
            'necesidades_transitorias' => 4,
            'necesidades_permanentes' => 1,
            'total_pie' => 5,
            'observacion' => 'Valor anterior',
            'estado' => 'en_revision',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function writeImportFile(string $path): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['RBD', 'CURSO', 'LETRA', 'ANIO', 'NEET', 'NEEP', 'OBSERVACION'],
            [5001, '1B', 'A', 2026, 0, 0, 'Actualizado desde plantilla'],
            [5001, '1B', 'B', 2026, 1, 0, 'No debe crearse'],
        ]);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}
