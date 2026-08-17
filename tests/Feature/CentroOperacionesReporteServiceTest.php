<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\User;
use App\Services\CentroOperaciones\DatosBaseService;
use App\Services\CentroOperaciones\EstadoService;
use App\Services\CentroOperaciones\IncidenciaCatalogo;
use App\Services\CentroOperaciones\ReporteService;
use App\Services\CentroOperaciones\UnidadOperacionalService;
use Carbon\CarbonImmutable;
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
        $extension = require database_path('migrations/2026_08_05_120000_extend_centro_operaciones_daily_reports.php');

        try {
            $operaciones->up();
            $extension->up();
            $this->createTicketTables();

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

            $service = new ReporteService(
                $datosBase,
                new EstadoService(),
                app(IncidenciaCatalogo::class),
                new UnidadOperacionalService()
            );
            $reporte = $service->crear(
                Establecimiento::query()->findOrFail(1),
                User::query()->findOrFail(1),
                [
                    'funcionamiento' => 'si',
                    'estudiantes_presentes' => 80,
                    'docentes_presentes' => 8,
                    'asistentes_presentes' => 4,
                    'servicios' => [
                        'agua_potable' => 'operativo',
                        'extintores' => 'critico',
                    ],
                    'afectaciones' => [],
                    'incidencias' => ['corte_agua'],
                    'incidencia_detalles' => ['corte_agua' => 'Corte informado en el sector.'],
                    'necesita_apoyo' => false,
                    'prioridad' => 'urgente',
                ]
            );

            $this->assertCount(2, $reporte->incidencias);
            $this->assertDatabaseHas('centro_operaciones_incidencias', [
                'reporte_id' => $reporte->id,
                'establecimiento_id' => 1,
                'tipo' => 'corte_agua',
                'severidad' => 'critico',
                'estado' => 'activa',
            ]);
            $this->assertDatabaseHas('centro_operaciones_incidencias', [
                'reporte_id' => $reporte->id,
                'establecimiento_id' => 1,
                'tipo' => 'extintor_no_operativo',
                'severidad' => 'critico',
                'estado' => 'activa',
            ]);
            $this->assertDatabaseCount('centro_operaciones_tickets', 2);
            $this->assertSame(
                2,
                DB::table('centro_operaciones_tickets')->distinct()->count('incidencia_id')
            );
            $this->assertSame(
                2,
                DB::table('centro_operaciones_tickets')
                    ->where('estado', 'pendiente_asignacion')
                    ->count()
            );
        } finally {
            Schema::dropIfExists('centro_operaciones_tickets');
            Schema::dropIfExists('centro_operaciones_incidente_configuraciones');
            $extension->down();
            $operaciones->down();
            Schema::dropIfExists('users');
            Schema::dropIfExists('establecimientos');
        }
    }

    public function test_control_de_plagas_vencido_persiste_y_se_resuelve_con_una_fecha_vigente(): void
    {
        $this->createPrerequisites();
        $operaciones = require database_path('migrations/2026_07_31_121000_create_centro_operaciones_tables.php');
        $extension = require database_path('migrations/2026_08_05_120000_extend_centro_operaciones_daily_reports.php');

        try {
            $operaciones->up();
            $extension->up();
            CarbonImmutable::setTestNow('2026-08-05 10:00:00');
            $this->createTicketTables();

            DB::table('establecimientos')->insert([
                'id' => 1,
                'nombre_establecimiento' => 'Liceo Nueva Zelandia',
                'rbd' => 12345,
                'comuna' => 'Santa Juana',
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
            $service = new ReporteService(
                $datosBase,
                new EstadoService(),
                app(IncidenciaCatalogo::class),
                new UnidadOperacionalService()
            );
            $establecimiento = Establecimiento::query()->findOrFail(1);
            $usuario = User::query()->findOrFail(1);
            $datos = [
                'funcionamiento' => 'si',
                'estudiantes_presentes' => 80,
                'docentes_presentes' => 8,
                'asistentes_presentes' => 4,
                'servicios' => ['control_plagas' => 'operativo'],
                'afectaciones' => [],
                'incidencias' => [],
                'necesita_apoyo' => false,
                'prioridad' => 'sin_novedad',
            ];

            $primero = $service->crear($establecimiento, $usuario, $datos + [
                'fecha_control_plagas' => '2026-08-04',
            ]);
            $segundo = $service->crear($establecimiento, $usuario, $datos);

            $this->assertSame('2026-08-04', $segundo->fecha_control_plagas->toDateString());
            $this->assertDatabaseCount('centro_operaciones_incidencias', 1);
            $this->assertDatabaseHas('centro_operaciones_incidencias', [
                'reporte_id' => $primero->id,
                'tipo' => 'control_plagas_vencido',
                'estado' => 'activa',
            ]);

            $vigente = $service->crear($establecimiento, $usuario, $datos + [
                'fecha_control_plagas' => '2026-12-31',
            ]);

            $this->assertSame('2026-12-31', $vigente->fecha_control_plagas->toDateString());
            $this->assertDatabaseHas('centro_operaciones_incidencias', [
                'tipo' => 'control_plagas_vencido',
                'estado' => 'resuelta',
                'resuelta_en_reporte_id' => $vigente->id,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
            Schema::dropIfExists('centro_operaciones_tickets');
            Schema::dropIfExists('centro_operaciones_incidente_configuraciones');
            $extension->down();
            $operaciones->down();
            Schema::dropIfExists('users');
            Schema::dropIfExists('establecimientos');
        }
    }

    public function test_internado_y_evacuacion_conservan_su_contexto_y_modalidad(): void
    {
        $this->createPrerequisites();
        $operaciones = require database_path('migrations/2026_07_31_121000_create_centro_operaciones_tables.php');
        $extension = require database_path('migrations/2026_08_05_120000_extend_centro_operaciones_daily_reports.php');

        try {
            $operaciones->up();
            $extension->up();
            $this->createTicketTables();
            DB::table('establecimientos')->insert([
                'id' => 1,
                'nombre_establecimiento' => 'Liceo Nueva Zelandia',
                'rbd' => 12345,
                'comuna' => 'Santa Juana',
                'matricula_total' => 100,
            ]);
            DB::table('users')->insert(['id' => 1]);

            $service = app(ReporteService::class);
            $reporte = $service->crear(
                Establecimiento::query()->findOrFail(1),
                User::query()->findOrFail(1),
                [
                    'unidad_codigo' => 'internado_nueva_zelandia',
                    'funcionamiento' => 'si',
                    'estudiantes_presentes' => 0,
                    'docentes_presentes' => 0,
                    'asistentes_presentes' => 0,
                    'servicios' => ['agua_potable' => 'operativo'],
                    'afectaciones' => ['albergue'],
                    'incidencias' => ['evacuacion'],
                    'incidencia_modalidades' => ['evacuacion' => 'simulacro'],
                    'necesita_apoyo' => false,
                    'prioridad' => 'sin_novedad',
                ]
            );

            $this->assertSame('internado_nueva_zelandia', $reporte->unidad_codigo);
            $this->assertSame('Internado · Liceo Nueva Zelandia', $reporte->establecimiento_nombre);
            $this->assertSame('simulacro', $reporte->incidencias->sole()->modalidad);
            $this->assertSame('albergue', $reporte->afectaciones->sole()->tipo);
        } finally {
            Schema::dropIfExists('centro_operaciones_tickets');
            Schema::dropIfExists('centro_operaciones_incidente_configuraciones');
            $extension->down();
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

    private function createTicketTables(): void
    {
        Schema::create('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo')->unique();
            $table->string('nombre')->nullable();
            $table->string('severidad')->nullable();
            $table->string('unidad_departamento')->nullable();
            $table->string('subdireccion_dependencia')->nullable();
            $table->foreignId('responsable_funcionario_ac_id')->nullable();
            $table->string('segunda_subdireccion_responsable')->nullable();
            $table->foreignId('segundo_responsable_funcionario_ac_id')->nullable();
            $table->unsignedSmallInteger('plazo_dias')->default(4);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('centro_operaciones_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->nullable()->unique();
            $table->foreignId('incidencia_id')->unique();
            $table->foreignId('configuracion_id')->nullable();
            $table->string('unidad_departamento')->nullable();
            $table->string('subdireccion_dependencia')->nullable();
            $table->foreignId('responsable_funcionario_ac_id')->nullable();
            $table->string('segunda_subdireccion_responsable')->nullable();
            $table->foreignId('segundo_responsable_funcionario_ac_id')->nullable();
            $table->foreignId('creado_por_id')->nullable();
            $table->timestamp('vence_en')->nullable();
            $table->string('estado')->default('asignado');
            $table->timestamp('notificado_responsable_en')->nullable();
            $table->timestamp('escalado_en')->nullable();
            $table->timestamp('resuelto_en')->nullable();
            $table->foreignId('resuelto_por_id')->nullable();
            $table->text('resolucion')->nullable();
            $table->timestamps();
        });
    }
}
