<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionDocenteExclusionController;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;
use Tests\TestCase;

class DotacionDocenteExclusionTest extends TestCase
{
    public function test_situacion_descuenta_hasta_el_saldo_contractual_sin_asignar(): void
    {
        $this->createTables();

        try {
            $establecimiento = Establecimiento::query()->create([
                'rbd' => 12345,
                'nombre_establecimiento' => 'Establecimiento de prueba',
                'sala_cuna' => false,
            ]);
            ReemplazoPersonal::query()->create([
                'establecimiento_id' => $establecimiento->id,
                'rut' => '11111111-1',
                'nombre' => 'Docente de prueba',
                'anio' => 2026,
                'mes' => 8,
                'jornada' => 44,
                'tipocontrato' => 'PLANTA',
                'estatuto' => 'DOCENTE',
                'escalafon' => 'DOCENTE AULA',
                'row_hash' => 'docente-prueba-2026',
            ]);
            DotacionDocenteAsignacion::query()->create([
                'establecimiento_id' => $establecimiento->id,
                'anio' => 2026,
                'docente_rut' => '11111111-1',
                'docente_rut_normalizado' => '111111111',
                'docente_nombre' => 'Docente de prueba',
                'estamento_cobertura' => 'docente',
                'tipo_asignacion' => 'otra_funcion',
                'horas_contrato' => 30,
                'estado' => 'activa',
            ]);

            $this->resetSchemaCaches();
            $controller = app(DotacionDocenteExclusionController::class);

            try {
                $controller->store($this->request(['horas' => 15]), $establecimiento);
                $this->fail('Se esperaba una validación por superar las horas pendientes de asignación.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'Sólo puede excluir horas contractuales sin asignación. El docente dispone de 14 hora(s) por asignar.',
                    $exception->errors()['horas'][0]
                );
            }

            $response = $controller->store($this->request(['horas' => 14]), $establecimiento);

            $this->assertTrue($response->isRedirect());
            $this->assertDatabaseHas('dotacion_docente_exclusiones', [
                'establecimiento_id' => $establecimiento->id,
                'anio' => 2026,
                'docente_rut_normalizado' => '111111111',
                'motivo' => 'sumario_administrativo',
                'horas' => 14,
            ]);

            $docente = DotacionEstablecimientoCalculator::docentes($establecimiento, 2026)->first();
            $this->assertSame(44.0, $docente['horas_contrato_base']);
            $this->assertSame(14.0, $docente['horas_excluidas']);
            $this->assertSame(30.0, $docente['horas_contrato']);
            $this->assertSame(0.0, $docente['diferencia']);
            $this->assertSame('sin_declaracion', $docente['estado_cuadratura']['key']);
        } finally {
            Schema::dropIfExists('dotacion_docente_exclusiones');
            Schema::dropIfExists('dotacion_docente_asignaciones');
            Schema::dropIfExists('reemplazos_personal');
            Schema::dropIfExists('establecimientos');
            $this->resetSchemaCaches();
        }
    }

    private function request(array $overrides = []): Request
    {
        $request = Request::create('/dotacion/docentes/exclusiones', 'POST', array_merge([
            'anio' => 2026,
            'docente_rut' => '11111111-1',
            'motivo' => 'sumario_administrativo',
            'horas' => 14,
        ], $overrides));
        $request->setUserResolver(fn () => new class
        {
            public int $id = 99;

            public function activeRoleName(): string
            {
                return 'admin';
            }
        });

        return $request;
    }

    private function createTables(): void
    {
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('rbd')->nullable();
            $table->string('nombre_establecimiento');
            $table->boolean('sala_cuna')->default(false);
            $table->timestamps();
        });
        Schema::create('reemplazos_personal', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->string('rut', 20);
            $table->string('nombre');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->decimal('jornada', 8, 2)->nullable();
            $table->decimal('jornada_basica', 8, 2)->nullable();
            $table->decimal('jornada_media', 8, 2)->nullable();
            $table->string('tipocontrato')->nullable();
            $table->string('financiamiento')->nullable();
            $table->string('estatuto')->nullable();
            $table->string('escalafon')->nullable();
            $table->string('row_hash')->nullable();
            $table->timestamps();
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->foreignId('establecimiento_id');
            $table->string('docente_rut', 32);
            $table->string('docente_rut_normalizado', 32);
            $table->string('docente_nombre')->nullable();
            $table->string('estamento_cobertura', 24)->default('docente');
            $table->string('tipo_asignacion', 64);
            $table->string('asignatura_nombre')->nullable();
            $table->decimal('horas_plan_pedagogicas', 8, 2)->nullable();
            $table->decimal('horas_contrato', 8, 2)->default(0);
            $table->string('proporcion_aplicada')->nullable();
            $table->string('estado', 32)->default('activa');
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_08_24_090000_create_dotacion_docente_exclusiones_table.php');
        $migration->up();
    }

    private function resetSchemaCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $propertyName) {
                $property = new ReflectionProperty($class, $propertyName);
                $property->setValue(null, []);
            }
        }
    }
}
