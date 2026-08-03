<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\User;
use App\Services\CentroOperaciones\DatosBaseService;
use App\Services\CentroOperaciones\EstadoService;
use App\Services\CentroOperaciones\ReporteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentroOperacionesReporteServiceTest extends TestCase
{
    public function test_crear_reporte_guarda_el_tipo_obligatorio_de_la_incidencia(): void
    {
        $this->createPrerequisites();
        $operaciones = require database_path('migrations/2026_07_31_121000_create_centro_operaciones_tables.php');

        try {
            $operaciones->up();

            DB::table('establecimientos')->insert([
                'id' => 1,
                'nombre_establecimiento' => 'Establecimiento de prueba',
                'rbd' => 12345,
                'comuna' => 'Coronel',
                'matricula_total' => 100,
            ]);
            DB::table('users')->insert(['id' => 1]);

            $datosBase = new class extends DatosBaseService
            {
                public function paraEstablecimiento(Establecimiento $establecimiento, int $anio): array
                {
                    return [
                        'matricula' => ['total' => 100, 'fuente' => 'prueba'],
                        'dotacion' => ['docentes' => 10, 'asistentes' => 5, 'periodo' => '202608'],
                    ];
                }
            };

            $service = new ReporteService($datosBase, new EstadoService());
            $reporte = $service->crear(
                Establecimiento::query()->findOrFail(1),
                User::query()->findOrFail(1),
                [
                    'funcionamiento' => 'si',
                    'estudiantes_presentes' => 80,
                    'docentes_presentes' => 8,
                    'asistentes_presentes' => 4,
                    'servicios' => ['agua_potable' => 'operativo'],
                    'afectaciones' => [],
                    'incidencias' => ['corte_agua'],
                    'incidencia_detalles' => ['corte_agua' => 'Corte informado en el sector.'],
                    'necesita_apoyo' => false,
                    'prioridad' => 'urgente',
                ]
            );

            $this->assertCount(1, $reporte->incidencias);
            $this->assertSame('corte_agua', $reporte->incidencias->first()->tipo);
            $this->assertDatabaseHas('centro_operaciones_incidencias', [
                'reporte_id' => $reporte->id,
                'establecimiento_id' => 1,
                'tipo' => 'corte_agua',
                'severidad' => 'critico',
                'estado' => 'activa',
            ]);
        } finally {
            $operaciones->down();
            Schema::dropIfExists('users');
            Schema::dropIfExists('establecimientos');
        }
    }

    private function createPrerequisites(): void
    {
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_establecimiento');
            $table->unsignedInteger('rbd')->nullable();
            $table->string('comuna')->nullable();
            $table->unsignedInteger('matricula_total')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->softDeletes();
        });
    }
}
