<?php

namespace Tests\Feature;

use App\Models\CentroOperacionesIncidencia;
use App\Models\CentroOperacionesRiesgoEvaluacion;
use App\Models\CentroOperacionesRiesgoModelo;
use App\Services\CentroOperaciones\PrioridadIncidenciaService;
use App\Services\CentroOperaciones\RiesgoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentroOperacionesRiesgoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearPrerequisitos();
        (require database_path('migrations/2026_08_20_120000_create_centro_operaciones_riesgo_tables.php'))->up();
        (require database_path('migrations/2026_08_20_121000_extend_centro_operaciones_incident_risk_priority.php'))->up();
    }

    protected function tearDown(): void
    {
        foreach ([
            'centro_operaciones_riesgo_respuestas',
            'centro_operaciones_riesgo_evaluaciones',
            'centro_operaciones_riesgo_opciones',
            'centro_operaciones_riesgo_dimensiones',
            'centro_operaciones_riesgo_modelos',
            'centro_operaciones_incidencias',
            'centro_operaciones_incidente_configuraciones',
            'centro_operaciones_reportes',
            'establecimientos',
            'users',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }
        parent::tearDown();
    }

    public function test_catalogo_extraido_contiene_diez_dimensiones_cincuenta_opciones_y_peso_cien(): void
    {
        $this->assertDatabaseCount('centro_operaciones_riesgo_dimensiones', 10);
        $this->assertDatabaseCount('centro_operaciones_riesgo_opciones', 50);
        $this->assertSame(100, (int) DB::table('centro_operaciones_riesgo_dimensiones')->sum('peso'));
        $this->assertDatabaseHas('centro_operaciones_riesgo_dimensiones', [
            'codigo' => 'actores_movilizados',
            'peso' => 8,
        ]);
        $this->assertDatabaseHas('centro_operaciones_riesgo_evaluaciones', [
            'establecimiento_id' => 99,
            'irte' => 35,
            'categoria' => 'estable',
        ]);
        $this->assertStringContainsString(
            "'co_riesgo_eval_establecimiento_fk'",
            file_get_contents(database_path('migrations/2026_08_20_120000_create_centro_operaciones_riesgo_tables.php'))
        );
    }

    public function test_reproduce_el_irte_35_del_excel_sin_convertir_errores_en_cero(): void
    {
        $modelo = CentroOperacionesRiesgoModelo::query()->with('dimensiones.opciones')->firstOrFail();
        $scores = [1, 2, 1, 2, 4, 2, 1, 2, 2, 1];
        $selecciones = [];
        foreach ($modelo->dimensiones as $indice => $dimension) {
            $selecciones[$dimension->id] = $dimension->opciones->firstWhere('score', $scores[$indice])->id;
        }

        $resultado = app(RiesgoService::class)->calcular($modelo, $selecciones);

        $this->assertSame(35, $resultado['irte']);
        $this->assertSame('estable', $resultado['categoria']);
        $this->assertSame('sin_alerta', $resultado['alerta']);
        $this->assertSame(['Exposición reputacional'], $resultado['motivos']);
    }

    public function test_un_factor_score_cinco_activa_alerta_roja_y_accion_inmediata_aunque_el_irte_sea_bajo(): void
    {
        $modelo = CentroOperacionesRiesgoModelo::query()->with('dimensiones.opciones')->firstOrFail();
        $selecciones = [];
        foreach ($modelo->dimensiones as $dimension) {
            $score = $dimension->codigo === 'exposicion_reputacional' ? 5 : 1;
            $selecciones[$dimension->id] = $dimension->opciones->firstWhere('score', $score)->id;
        }

        $resultado = app(RiesgoService::class)->calcular($modelo, $selecciones);

        $this->assertSame(26, $resultado['irte']);
        $this->assertSame('estable', $resultado['categoria']);
        $this->assertSame('roja', $resultado['alerta']);
        $this->assertSame($modelo->accion_factor_critico, $resultado['accion']);
    }

    public function test_la_vigencia_incluye_completo_el_dia_de_termino(): void
    {
        $evaluacion = new CentroOperacionesRiesgoEvaluacion([
            'vigente_hasta' => now(config('centro_operaciones.timezone'))->toDateString(),
        ]);

        $this->assertFalse($evaluacion->esta_vencida);

        $evaluacion->vigente_hasta = now(config('centro_operaciones.timezone'))->subDay()->toDateString();

        $this->assertTrue($evaluacion->esta_vencida);
    }

    public function test_prioridad_combina_incidencia_riesgo_matricula_y_regla_critica(): void
    {
        DB::table('establecimientos')->insert([
            ['id' => 1, 'nombre_establecimiento' => 'Escuela uno', 'rbd' => 1, 'comuna' => 'Lota', 'matricula_total' => 500],
            ['id' => 2, 'nombre_establecimiento' => 'Escuela dos', 'rbd' => 2, 'comuna' => 'Coronel', 'matricula_total' => 100],
        ]);
        DB::table('centro_operaciones_reportes')->insert(['id' => 1, 'establecimiento_id' => 1]);
        DB::table('centro_operaciones_incidente_configuraciones')->insert([
            'tipo' => 'dano_estructural',
            'nombre' => 'Daño estructural',
            'severidad' => 'critico',
            'familia' => 'infraestructura',
            'impacto_base' => 5,
            'urgencia_base' => 5,
            'prioridad_minima' => 'P1',
            'plazo_dias' => 4,
            'sla_horas' => 2,
            'forzar_p1' => true,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('centro_operaciones_incidencias')->insert([
            'id' => 1,
            'reporte_id' => 1,
            'establecimiento_id' => 1,
            'fecha_incidencia' => now()->toDateString(),
            'tipo' => 'dano_estructural',
            'severidad' => 'critico',
            'estado' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $incidencia = CentroOperacionesIncidencia::query()->findOrFail(1);
        $resultado = app(PrioridadIncidenciaService::class)->recalcular($incidencia);

        $this->assertSame('P1', $resultado['prioridad_nivel']);
        $this->assertSame('infraestructura', $resultado['familia']);
        $this->assertGreaterThanOrEqual(80, $resultado['prioridad_puntaje']);
        $this->assertStringContainsString('regla crítica', $resultado['prioridad_motivo']);
    }

    private function crearPrerequisitos(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->softDeletes();
        });
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_establecimiento');
            $table->unsignedInteger('rbd')->nullable();
            $table->string('comuna')->nullable();
            $table->unsignedInteger('matricula_total')->nullable();
        });
        Schema::create('centro_operaciones_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id');
        });
        Schema::create('centro_operaciones_incidente_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 80)->unique();
            $table->string('nombre')->nullable();
            $table->string('severidad')->nullable();
            $table->unsignedSmallInteger('plazo_dias')->default(4);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('centro_operaciones_incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id');
            $table->foreignId('establecimiento_id')->nullable();
            $table->date('fecha_incidencia');
            $table->string('tipo', 48);
            $table->string('severidad', 16);
            $table->text('descripcion')->nullable();
            $table->string('estado', 16);
            $table->timestamps();
        });
        DB::table('establecimientos')->insert([
            'id' => 99,
            'nombre_establecimiento' => 'ESCUELA DIFERENCIAL PIERRE MENDES FRANCE',
            'rbd' => 99999,
            'comuna' => 'SAN PEDRO',
            'matricula_total' => 91,
        ]);
    }
}
