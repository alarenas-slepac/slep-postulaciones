<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Controllers\Admin\DotacionFuncionesController;
use App\Http\Controllers\Admin\DotacionProceso2027Controller;
use App\Models\DotacionFuncionRegla;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionFuncionesCalculator;
use App\Support\DotacionFuncionesNormativas2027;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DotacionFuncionesNormativasHorasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->partialMock(\App\Services\Padron\PadronPeriodoService::class, function ($mock): void {
            $mock->shouldReceive('anioDisponibleParaDotacion')->andReturn(null);
        });
        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id();
            $t->integer('rbd');
            $t->string('nombre_establecimiento');
        });
        Schema::create('cursos', function (Blueprint $t): void {
            $t->id();
            $t->string('nombre');
        });
        Schema::create('establecimiento_cursos', function (Blueprint $t): void {
            $t->id();
            $t->integer('establecimiento_id');
            $t->integer('curso_id');
            $t->integer('anio');
            $t->boolean('activo')->default(true);
            $t->integer('matricula')->default(0);
        });
        Schema::create('establecimiento_curso_pie', function (Blueprint $t): void {
            $t->id();
            $t->integer('establecimiento_id');
            $t->integer('establecimiento_curso_id');
            $t->integer('curso_id')->nullable();
            $t->integer('anio');
            $t->integer('total_pie')->default(0);
        });
        Schema::create('dotacion_establecimiento_configuraciones', function (Blueprint $t): void {
            $t->id();
            $t->integer('establecimiento_id');
            $t->integer('anio');
            $t->boolean('director_adp')->default(false);
        });
        Schema::create('dotacion_funciones_reglas', function (Blueprint $t): void {
            $t->id();
            $t->string('codigo');
            $t->string('categoria');
            $t->string('nombre');
            $t->string('tipo_regla');
            $t->integer('horas_fijas')->nullable();
            $t->boolean('declarable')->default(false);
            $t->boolean('vigente')->default(true);
        });
        Schema::create('dotacion_funciones_establecimiento', function (Blueprint $t): void {
            $t->id();
            $t->integer('establecimiento_id');
            $t->integer('anio');
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id();
            $t->integer('anio');
            $t->integer('establecimiento_id');
            $t->string('docente_rut');
            $t->string('docente_rut_normalizado');
            $t->string('docente_nombre');
            $t->string('estamento_cobertura')->default('docente');
            $t->string('tipo_asignacion');
            $t->string('subtipo_asignacion');
            $t->string('necesidad_key')->nullable();
            $t->integer('dotacion_funcion_regla_id')->nullable();
            $t->integer('dotacion_funcion_id')->nullable();
            $t->string('asignatura_nombre')->nullable();
            $t->string('subvencion')->default('General');
            $t->decimal('horas_contrato', 8, 2);
            $t->decimal('horas_plan_pedagogicas', 8, 2)->nullable();
            $t->string('estado')->default('activa');
        });
        Schema::create('dotacion_proceso_2027_configuraciones', function (Blueprint $t): void {
            $t->id();
            $t->integer('establecimiento_id');
            $t->integer('anio');
            $t->string('decision_combinacion')->nullable();
            foreach ([1, 2, 3] as $i) {
                $t->decimal('max_horas_bloque_'.$i, 8, 2)->nullable();
            }
            $t->json('funciones_normativas')->nullable();
            $t->integer('funciones_normativas_configuradas_by')->nullable();
            $t->timestamp('funciones_normativas_configuradas_at')->nullable();
            $t->timestamps();
        });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']);
        DB::table('dotacion_funciones_reglas')->insert([
            'id' => 1, 'codigo' => 'encargado_convivencia', 'categoria' => 'tecnico_pedagogica',
            'nombre' => 'Encargado(a) de Convivencia Escolar', 'tipo_regla' => 'fija', 'horas_fijas' => 44,
        ]);
    }

    private function establecimiento(): Establecimiento
    {
        return Establecimiento::findOrFail(1);
    }

    private function necesidad(): array
    {
        return DotacionAsignacionCalculator::funcionNormativaParaAsignacion($this->establecimiento(), 2027, ['dotacion_funcion_regla_id' => 1]);
    }

    private function configurar(float $horas): void
    {
        DotacionProceso2027Configuracion::updateOrCreate(['establecimiento_id' => 1, 'anio' => 2027], [
            'funciones_normativas' => [$this->necesidad()['key'] => true, '_horas' => ['encargado_convivencia' => $horas]],
        ]);
    }

    private function asignar(float $horas, string $estamento = 'docente'): int
    {
        return DB::table('dotacion_docente_asignaciones')->insertGetId([
            'anio' => 2027, 'establecimiento_id' => 1, 'docente_rut' => '11111111-1', 'docente_rut_normalizado' => '111111111',
            'docente_nombre' => 'Persona sintética', 'estamento_cobertura' => $estamento, 'tipo_asignacion' => 'funcion_tecnico_pedagogica',
            'subtipo_asignacion' => 'tecnico_pedagogica', 'dotacion_funcion_regla_id' => 1,
            'necesidad_key' => $this->necesidad()['key'], 'horas_contrato' => $horas,
        ]);
    }

    private function solicitud(array $funciones, string $role = 'admin', int $establecimientoId = 1): Request
    {
        $request = Request::create('/configurar-normativas', 'POST', [
            'anio' => 2027, 'funciones_normativas_configuradas' => 1, 'funciones_normativas' => $funciones,
        ]);
        $request->setUserResolver(fn () => new class($role, $establecimientoId)
        {
            public int $id = 1;

            public function __construct(private string $role, public int $establecimiento_id) {}

            public function activeRoleName(): string
            {
                return $this->role;
            }
        });
        $request->setLaravelSession(app('session')->driver());

        return $request;
    }

    public function test_definicion_antigua_conserva_horas_calculadas_y_renombrado_no_cambia_la_clave(): void
    {
        $necesidad = $this->necesidad();
        $this->assertSame(44.0, $necesidad['horas_contrato_requeridas']);
        $this->assertSame('funcion:c3b72b40c82759fcebca36a4a54db183', $necesidad['key']);
        $this->assertSame('Coordinador(a) de Convivencia Educativa', $necesidad['titulo']);
        $this->assertSame('Encargado(a) de Convivencia Escolar', DotacionFuncionRegla::find(1)->getRawOriginal('nombre'));
        DotacionProceso2027Configuracion::create(['establecimiento_id' => 1, 'anio' => 2027, 'funciones_normativas' => [$necesidad['key'] => true]]);
        $this->assertSame(44.0, $this->necesidad()['horas_contrato_requeridas']);
    }

    public function test_administrador_guarda_reduccion_y_se_aplica_a_necesidades_y_proceso_sin_afectar_2026(): void
    {
        $key = $this->necesidad()['key'];
        app(DotacionProceso2027Controller::class)->update($this->solicitud([['key' => $key, 'usar' => 1, 'horas' => 22.5]]), $this->establecimiento());
        $config = DotacionProceso2027Configuracion::firstOrFail();
        $this->assertTrue($config->funciones_normativas[$key]);
        $this->assertSame(22.5, $config->funciones_normativas['_horas']['encargado_convivencia']);
        $this->assertSame(1, $config->funciones_normativas_configuradas_by);
        $this->assertNotNull($config->funciones_normativas_configuradas_at);
        $this->assertSame(22.5, $this->necesidad()['horas_contrato_requeridas']);
        $proceso = DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027);
        $this->assertSame(44.0, $proceso['bloques']['bloque_1']['horas_normativas_potenciales']);
        $this->assertSame(22.5, $proceso['bloques']['bloque_1']['horas_normativas_definidas']);
        $this->assertSame(22.5, $proceso['bloques']['bloque_1']['requeridas']);
        $this->assertSame(44, DotacionFuncionesCalculator::sugerencias($this->establecimiento(), 2026)->sole()['horas_sugeridas']);
        $resumen = (new \ReflectionMethod(DotacionFuncionesController::class, 'resumenEstablecimiento'))
            ->invoke(app(DotacionFuncionesController::class), $this->establecimiento(), 2027);
        $this->assertSame(22.5, $resumen['consolidado_por_bloque']['tecnico_pedagogica']['total']);
    }

    public function test_reduccion_con_asignaciones_previas_muestra_exceso_sin_alterarlas_y_permite_restaurar_horas(): void
    {
        $this->asignar(44);
        $this->configurar(22);
        $proceso = DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027);
        $funcion = $proceso['funciones_normativas']->sole();
        $this->assertSame(22.0, $funcion['exceso_asignado']);
        $this->assertSame(44.0, $funcion['horas_asignadas']);
        $this->assertSame(44.0, $funcion['horas_potenciales']);
        $this->assertSame(44.0, (float) DB::table('dotacion_docente_asignaciones')->value('horas_contrato'));
        app(DotacionProceso2027Controller::class)->update($this->solicitud([['key' => $funcion['key'], 'usar' => 1, 'horas' => 44]]), $this->establecimiento());
        $this->assertSame(44.0, $this->necesidad()['horas_contrato_requeridas']);
    }

    public function test_definicion_en_cero_sigue_visible_y_puede_activarse_de_nuevo(): void
    {
        $this->configurar(0);
        $proceso = DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027);
        $this->assertSame(0.0, $proceso['funciones_normativas']->sole()['horas']);
        $this->assertSame(44.0, $proceso['funciones_normativas']->sole()['horas_potenciales']);
        $this->assertSame(0.0, $proceso['bloques']['bloque_1']['requeridas']);
        $this->configurar(12);
        $this->assertSame(12.0, $this->necesidad()['horas_contrato_requeridas']);
    }

    public function test_no_guarda_horas_superiores_al_calculo(): void
    {
        $this->expectException(ValidationException::class);
        app(DotacionProceso2027Controller::class)->update($this->solicitud([['key' => $this->necesidad()['key'], 'usar' => 1, 'horas' => 45]]), $this->establecimiento());
    }

    public function test_no_guarda_horas_negativas(): void
    {
        $this->expectException(ValidationException::class);
        app(DotacionProceso2027Controller::class)->update($this->solicitud([['key' => $this->necesidad()['key'], 'usar' => 1, 'horas' => -1]]), $this->establecimiento());
    }

    public function test_directivo_no_puede_configurar_otro_establecimiento(): void
    {
        try {
            app(DotacionProceso2027Controller::class)->update($this->solicitud([], 'funcionario_directivo_estab', 2), $this->establecimiento());
            $this->fail('Debe rechazar una configuración fuera de su establecimiento.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_directivo_no_puede_cambiar_horas_de_convivencia_incluso_sin_carga_anual(): void
    {
        $key = $this->necesidad()['key'];
        $this->expectException(ValidationException::class);
        app(DotacionProceso2027Controller::class)->update($this->solicitud([['key' => $key, 'usar' => 1, 'horas' => 22]], 'funcionario_directivo_estab'), $this->establecimiento());
    }

    public function test_carga_anual_prevalece_sobre_json_local_y_aplica_en_necesidades_y_proceso(): void
    {
        (require database_path('migrations/2026_09_30_180000_create_dotacion_convivencia_horas_table.php'))->up();
        $this->configurar(44);
        DB::table('dotacion_convivencia_horas')->insert(['establecimiento_id' => 1, 'anio' => 2027, 'horas' => 18.5]);
        $this->asignar(22);
        $this->assertSame(18.5, $this->necesidad()['horas_contrato_requeridas']);
        $proceso = DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027);
        $this->assertSame(18.5, $proceso['bloques']['bloque_1']['horas_normativas_definidas']);
        $this->assertSame(3.5, $proceso['funciones_normativas']->sole()['exceso_asignado']);
        $this->assertSame(44, DotacionFuncionesCalculator::sugerencias($this->establecimiento(), 2026)->sole()['horas_sugeridas']);
        DB::table('dotacion_convivencia_horas')->where('anio', 2027)->update(['horas' => 0]);
        $this->assertSame(0.0, $this->necesidad()['horas_contrato_requeridas']);
    }

    public function test_configuracion_individual_no_puede_sobrescribir_horas_de_carga_anual(): void
    {
        (require database_path('migrations/2026_09_30_180000_create_dotacion_convivencia_horas_table.php'))->up();
        DB::table('dotacion_convivencia_horas')->insert(['establecimiento_id' => 1, 'anio' => 2027, 'horas' => 18.5]);
        $key = $this->necesidad()['key'];
        $this->expectException(ValidationException::class);
        app(DotacionProceso2027Controller::class)->update($this->solicitud([['key' => $key, 'usar' => 1, 'horas' => 44]], 'admin'), $this->establecimiento());
    }

    public function test_carga_anual_limita_asignaciones_de_convivencia_fuera_de_2027(): void
    {
        (require database_path('migrations/2026_09_30_180000_create_dotacion_convivencia_horas_table.php'))->up();
        DB::table('dotacion_convivencia_horas')->insert(['establecimiento_id' => 1, 'anio' => 2026, 'horas' => 18]);
        $payload = ['anio' => 2026, 'tipo_asignacion' => 'funcion_tecnico_pedagogica',
            'subtipo_asignacion' => 'tecnico_pedagogica', 'dotacion_funcion_regla_id' => 1, 'horas_contrato' => 19];
        $this->expectException(ValidationException::class);
        (new \ReflectionMethod(DotacionAsignacionController::class, 'validateProceso2027Assignment'))
            ->invoke(app(DotacionAsignacionController::class), $this->establecimiento(), [], $payload);
    }

    public function test_servidor_rechaza_nueva_asignacion_que_excede_definicion_incluyendo_cobertura_aaee(): void
    {
        $this->configurar(22);
        $this->asignar(10, 'asistente');
        $payload = ['anio' => 2027, 'tipo_asignacion' => 'funcion_tecnico_pedagogica',
            'subtipo_asignacion' => 'tecnico_pedagogica', 'dotacion_funcion_regla_id' => 1, 'horas_contrato' => 13];
        try {
            (new \ReflectionMethod(DotacionAsignacionController::class, 'validateProceso2027Assignment'))
                ->invoke(app(DotacionAsignacionController::class), $this->establecimiento(), [], $payload);
            $this->fail('Debe respetar el total definido antes de evaluar la prelación.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('22 h definidas', $e->errors()['horas_contrato'][0]);
        }
    }

    public function test_editar_asignacion_excluye_sus_horas_anteriores_y_no_duplica_el_total(): void
    {
        $id = $this->asignar(44);
        $this->configurar(22);
        DotacionFuncionesNormativas2027::validarAsignacion($this->necesidad(), 22, $id);
        $this->assertSame(44.0, (float) DB::table('dotacion_docente_asignaciones')->value('horas_contrato'));
    }

    public function test_director_adp_respeta_horas_definidas_en_la_plaza_y_en_la_validacion(): void
    {
        DB::table('dotacion_funciones_reglas')->insert([
            'id' => 2, 'codigo' => 'director_adp', 'categoria' => 'directiva', 'nombre' => 'Director(a) ADP',
            'tipo_regla' => 'director_adp', 'horas_fijas' => 44,
        ]);
        DB::table('dotacion_establecimiento_configuraciones')->insert(['establecimiento_id' => 1, 'anio' => 2027, 'director_adp' => true]);
        DotacionProceso2027Configuracion::create(['establecimiento_id' => 1, 'anio' => 2027,
            'funciones_normativas' => ['_horas' => ['director_adp' => 22]],
        ]);
        $asignacion = DotacionAsignacionCalculator::build($this->establecimiento(), 2027, collect(), [],
            DotacionEstablecimientoCalculator::bloquesDotacion($this->establecimiento(), 2027));
        $director = $asignacion['necesidades']['funciones']->firstWhere('codigo', 'director_adp');
        $this->assertSame(22.0, $director['horas_contrato_requeridas']);
        $this->assertSame(22.0, $director['horas_contrato_asignadas']);
        $method = new \ReflectionMethod(DotacionAsignacionController::class, 'validateDirectorAdpAssignment');
        $payload = ['tipo_asignacion' => 'funcion_directiva', 'estamento_cobertura' => 'docente', 'dotacion_funcion_regla_id' => 2, 'horas_contrato' => 22];
        $this->assertNull($method->invoke(app(DotacionAsignacionController::class), $this->establecimiento(), 2027, $payload));
        $this->expectException(ValidationException::class);
        $method->invoke(app(DotacionAsignacionController::class), $this->establecimiento(), 2027, [...$payload, 'horas_contrato' => 44]);
    }

    public function test_formulario_muestra_horas_definidas_maximo_calculado_y_exceso_con_asignaciones(): void
    {
        $this->asignar(44);
        $this->configurar(22);
        $datos = [
            'proceso2027' => DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027),
            'establecimiento' => $this->establecimiento(), 'anio' => 2027, 'tab' => 'sobredotacion',
            'canManageProceso2027Normativas' => true,
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ];
        $html = view('admin.dotacion-establecimiento.partials._proceso_2027', $datos)->render();
        $this->assertStringContainsString('Coordinador(a) de Convivencia Educativa', $html);
        $this->assertStringContainsString('Las asignaciones exceden la definición en 22 h.', $html);
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($documento);
        $campo = $xpath->query('//input[@name="funciones_normativas[0][horas]"]')->item(0);
        $this->assertNotNull($campo);
        $this->assertSame('22', $campo->getAttribute('value'));
        $this->assertSame('44', $campo->getAttribute('max'));
        $this->assertSame(1, $xpath->query('//label[@for="'.$campo->getAttribute('id').'"]')->length);

        $soloLectura = view('admin.dotacion-establecimiento.partials._proceso_2027', [
            ...$datos, 'canManageProceso2027Normativas' => false,
        ])->render();
        $this->assertStringContainsString('Definidas: 22 h', $soloLectura);
        $this->assertStringNotContainsString('name="funciones_normativas[0][horas]"', $soloLectura);
    }

    public function test_asignacion_admite_la_precision_de_las_horas_definidas_y_limita_al_pendiente(): void
    {
        $this->configurar(22.37);
        $this->asignar(10);
        $data = DotacionEstablecimientoCalculator::build($this->establecimiento(), 2027);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', [
            ...$data, 'establecimiento' => $this->establecimiento(), 'anio' => 2027,
            'proceso2027' => DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027, $data),
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ])->render();
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$html);
        $campo = (new \DOMXPath($documento))->query('//input[@name="horas_contrato" and @type="number"]')->item(0);
        $this->assertNotNull($campo);
        $this->assertSame('0.01', $campo->getAttribute('step'));
        $this->assertSame('12.37', $campo->getAttribute('value'));
        $this->assertSame('12.37', $campo->getAttribute('max'));
    }
}
