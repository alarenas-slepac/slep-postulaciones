<?php

namespace Tests\Feature;

use App\Exports\DotacionResumenSobredotacionExport;
use App\Http\Controllers\Admin\DotacionDocenteExclusionController;
use App\Models\DotacionDocenteExclusion;
use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProyeccionCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class DotacionSituacionHorasNecesariasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->resetSchemaCaches();

        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
            $t->boolean('especial')->default(false); $t->boolean('sala_cuna')->default(false);
        });
        Schema::create('reemplazos_personal', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->string('rut'); $t->string('nombre');
            $t->integer('anio'); $t->integer('mes'); $t->boolean('vigente')->default(true);
            $t->decimal('jornada', 8, 2);
            $t->string('tipocontrato'); $t->string('estatuto'); $t->string('escalafon');
            $t->string('financiamiento')->default('GENERAL');
            $t->timestamps();
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); $t->string('rbd'); $t->string('rut');
            $t->string('nombre_titulo'); $t->string('nombre_funcion'); $t->string('estamento');
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            $t->string('docente_rut'); $t->string('docente_rut_normalizado');
            $t->string('docente_nombre'); $t->string('tipo_asignacion');
            $t->string('subtipo_asignacion')->nullable(); $t->string('asignatura_nombre')->nullable();
            $t->string('estamento_cobertura')->default('docente');
            $t->unsignedBigInteger('dotacion_funcion_id')->nullable();
            $t->decimal('horas_contrato', 8, 2); $t->string('estado')->default('activa');
        });
        (require database_path('migrations/2026_08_24_090000_create_dotacion_docente_exclusiones_table.php'))->up();

        DB::table('establecimientos')->insert([
            'id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético',
        ]);
        DB::table('reemplazos_personal')->insert([
            'id' => 1, 'establecimiento_id' => 1, 'rut' => '11111111-1', 'nombre' => 'Docente sintético',
            'anio' => 2026, 'mes' => 9, 'jornada' => 44,
            'tipocontrato' => 'PLANTA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA',
        ]);
        DB::table('declaracion_sostenedores')->insert([
            'id' => 1, 'rbd' => '99999', 'rut' => '11111111-1',
            'nombre_titulo' => 'Profesor de Educación Básica', 'nombre_funcion' => 'Docente aula', 'estamento' => 'DOCENTE',
        ]);
        // Contrato completamente asignado: antes impedía guardar la situación.
        DB::table('dotacion_docente_asignaciones')->insert([
            'id' => 1, 'establecimiento_id' => 1, 'anio' => 2026,
            'docente_rut' => '11.111.111-1', 'docente_rut_normalizado' => '111111111',
            'docente_nombre' => 'Docente sintético', 'tipo_asignacion' => 'otra_funcion',
            'asignatura_nombre' => 'Función declarada sintética', 'horas_contrato' => 44, 'dotacion_funcion_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetSchemaCaches();
        parent::tearDown();
    }

    public static function distribuciones(): array
    {
        return [
            'parcial' => [20, 24],
            'todo necesario' => [44, 0],
            'nada necesario' => [0, 44],
            'centésimas' => [19.37, 24.63],
        ];
    }

    #[DataProvider('distribuciones')]
    public function test_distribuye_incluso_con_contrato_totalmente_asignado(float $necesarias, float $noNecesarias): void
    {
        $asignaciones = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        $padron = DB::table('reemplazos_personal')->get()->toJson();
        $response = $this->guardar(['horas_necesarias' => $necesarias, 'horas' => $noNecesarias]);

        $this->assertTrue($response->isRedirect());
        $docente = $this->docente();
        $this->assertSame(44.0, $docente['horas_contrato_base']);
        $this->assertSame($necesarias, $docente['horas_contrato']);
        $this->assertSame($noNecesarias, $docente['horas_excluidas']);
        $this->assertSame(44.0, round($docente['horas_contrato'] + $docente['horas_excluidas'], 2));
        $this->assertSame($asignaciones, DB::table('dotacion_docente_asignaciones')->get()->toJson());
        $this->assertSame($padron, DB::table('reemplazos_personal')->get()->toJson());
    }

    public static function entradasInvalidas(): array
    {
        return [
            'falta una centésima' => [['horas_necesarias' => 20, 'horas' => 23.99]],
            'sobra una centésima' => [['horas_necesarias' => 20, 'horas' => 24.01]],
            'ambas cero' => [['horas_necesarias' => 0, 'horas' => 0]],
            'necesarias negativas' => [['horas_necesarias' => -1, 'horas' => 45]],
            'no necesarias negativas' => [['horas_necesarias' => 45, 'horas' => -1]],
            'necesarias ausentes' => [['horas_necesarias' => null]],
            'no necesarias ausentes' => [['horas' => null]],
            'precisión excesiva' => [['horas_necesarias' => 20.001, 'horas' => 23.999]],
            'motivo inválido' => [['motivo' => 'inexistente']],
            'otro docente' => [['docente_rut' => '22222222-2']],
            'otro año' => [['anio' => 2025]],
        ];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rechaza_distribuciones_invalidas_sin_guardar(array $data): void
    {
        try {
            $this->guardar($data);
            $this->fail('Debió rechazar la situación.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
            $this->assertDatabaseCount('dotacion_docente_exclusiones', 0);
            $this->assertSame(44.0, $this->docente()['horas_contrato']);
        }
    }

    public function test_actualiza_situacion_historica_y_eliminar_restaurara_contrato(): void
    {
        $situacion = DotacionDocenteExclusion::create([
            'establecimiento_id' => 1, 'anio' => 2026, 'docente_rut' => '11111111-1',
            'docente_rut_normalizado' => '111111111', 'docente_nombre' => 'Docente sintético',
            'motivo' => 'traslado', 'horas' => 10, 'created_by' => 12,
        ]);
        $this->assertSame(34.0, $this->docente()['horas_contrato']);
        $this->guardar(['motivo' => 'horas_gremiales']);
        $this->assertDatabaseCount('dotacion_docente_exclusiones', 1);
        $this->assertSame($situacion->id, $this->docente()['exclusion_docente']['id']);
        $this->assertSame(20.0, $this->docente()['horas_contrato']);
        $this->assertSame(12, $situacion->fresh()->created_by);
        $this->assertSame(99, $situacion->fresh()->updated_by);

        app(DotacionDocenteExclusionController::class)->destroy($this->request(), Establecimiento::findOrFail(1), $situacion);
        $this->assertDatabaseCount('dotacion_docente_exclusiones', 0);
        $this->assertSame(44.0, $this->docente()['horas_contrato']);
        $this->assertSame(44.0, $this->docente()['horas_asignadas_total']);
    }

    public function test_valida_contra_padron_actual_y_mantiene_suma_tras_cambio_de_contrato(): void
    {
        $this->guardar();
        DB::table('reemplazos_personal')->where('id', 1)->update(['jornada' => 38]);
        $this->assertSame(14.0, $this->docente()['horas_contrato']);
        $this->assertSame(24.0, $this->docente()['horas_excluidas']);
        try {
            $this->guardar(); // Formulario antiguo: todavía suma 44.
            $this->fail('Debió validar contra las 38 h vigentes.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('38 hora(s)', $exception->errors()['horas'][0]);
        }
        DB::table('reemplazos_personal')->where('id', 1)->update(['jornada' => 10]);
        $this->assertSame(0.0, $this->docente()['horas_contrato']);
        $this->assertSame(10.0, $this->docente()['horas_excluidas']);
    }

    public static function categorias(): array
    {
        return [
            'aula' => ['Profesor de Educación Básica', false, false, 20.0, 0.0, 0.0],
            'parvularia' => ['Pedagogía en Educación de Párvulos', false, false, 20.0, 0.0, 0.0],
            'diferencial' => ['Educadora Diferencial', false, false, 0.0, 0.0, 20.0],
            'coordinación PIE' => ['Profesor de Educación Básica', true, false, 0.0, 0.0, 20.0],
            'parvularia con coordinación histórica' => ['Pedagogía en Educación de Párvulos', true, false, 20.0, 0.0, 0.0],
            'diferencial en especial' => ['Educadora Diferencial', true, true, 20.0, 0.0, 0.0],
        ];
    }

    #[DataProvider('categorias')]
    public function test_aporte_contractual_por_categoria_separa_las_horas_asignadas_fuera_de_su_bloque(
        string $titulo, bool $coordinacion, bool $especial, float $aula, float $parvularia, float $pie
    ): void {
        DB::table('declaracion_sostenedores')->update(['nombre_titulo' => $titulo]);
        DB::table('establecimientos')->update(['especial' => $especial]);
        if ($coordinacion) {
            DB::table('dotacion_docente_asignaciones')->update([
                'tipo_asignacion' => 'funcion_tecnico_pedagogica', 'subtipo_asignacion' => 'pie',
                'asignatura_nombre' => 'Coordinador PIE',
            ]);
        }
        $this->guardar();
        foreach ([44, 30, 5, 60] as $horasAsignadas) {
            DB::table('dotacion_docente_asignaciones')->update(['horas_contrato' => $horasAsignadas]);
            $docentes = collect([$this->docente()]);
            $pieCalculado = (new ReflectionMethod(DotacionAsignacionCalculator::class, 'resumenContratoDocentePie'))
                ->invoke(null, DotacionAsignacionCalculator::assignmentsFor(Establecimiento::findOrFail(1), 2026), $docentes, $especial);
            $separacion = DotacionEstablecimientoCalculator::contratoParvularia($docentes, 20 - $pieCalculado['total'], 0);
            if ($titulo === 'Pedagogía en Educación de Párvulos') {
                $parvularia = max(0.0, 20.0 - $horasAsignadas);
                $aula = round(20.0 - $pie - $parvularia, 2);
            }
            if ($titulo === 'Educadora Diferencial' && ! $especial) {
                $pie = max(0.0, 20.0 - $horasAsignadas);
                $aula = round(20.0 - $pie, 2);
            }
            $this->assertSame($pie, $pieCalculado['total']);
            $this->assertSame($aula, $separacion['horas_contrato_docentes_aula_general']);
            $this->assertSame($parvularia, $separacion['horas_contrato_docentes_parvularia']);
            if (! $especial) {
                $this->assertSame($pie, DotacionAsignacionCalculator::contratoPiePorDocente($docentes->sole()));
            }
            $row = (new DotacionResumenSobredotacionExport)->row(Establecimiento::findOrFail(1), $separacion + [
                'horas_contrato_docentes' => 20, 'horas_contrato_docente_pie' => $pieCalculado['total'],
            ]);
            $this->assertSame([20.0, $aula, $parvularia, $pie], array_slice($row, 10, 4));
        }
    }

    public function test_diferencial_con_normativas_reparte_contrato_efectivo_del_padron(): void
    {
        DB::table('declaracion_sostenedores')->update(['nombre_titulo' => 'Pedagogía en Educación Diferencial']);
        DB::table('dotacion_docente_asignaciones')->update([
            'tipo_asignacion' => 'funcion_tecnico_pedagogica', 'asignatura_nombre' => 'Jefe UTP',
            'dotacion_funcion_id' => null, 'horas_contrato' => 20,
        ]);
        $this->guardar(['horas_necesarias' => 34, 'horas' => 10]);
        $docente = $this->docente();
        $asignaciones = DotacionAsignacionCalculator::assignmentsFor(Establecimiento::findOrFail(1), 2026);
        $this->assertSame(44.0, $docente['horas_contrato_base']);
        $this->assertSame(34.0, $docente['horas_contrato']);
        $this->assertSame(14.0, DotacionAsignacionCalculator::contratoPiePorDocente($docente));
        $this->assertSame(14.0, DotacionAsignacionCalculator::resumenContratoDocentePie($asignaciones, collect([$docente]))['total']);
        $p = DotacionProyeccionCalculator::build(['docentes' => [$docente], 'asignacion' => ['asignaciones' => $asignaciones]], 2026, ['111111111' => false]);
        $this->assertSame(['total' => 34.0, 'aula' => 20.0, 'parvularia' => 0.0, 'pie' => 14.0], $p['contratos_vacantes']);
        $this->assertSame(34.0, $p['horas_vacantes_por_cubrir']);
    }

    public function test_formulario_disponible_con_todas_las_horas_asignadas_y_precarga_ambos_valores(): void
    {
        foreach ([false, true] as $guardada) {
            if ($guardada) {
                $this->guardar();
            }
            $html = view('admin.dotacion-establecimiento.partials._docentes', [
                'docentes' => collect([$this->docente()]), 'establecimiento' => Establecimiento::findOrFail(1),
                'anio' => 2026, 'canManageDocenteExclusiones' => true, 'docenteExclusionesTableReady' => true,
                'motivosExclusionDocente' => DotacionDocenteExclusion::MOTIVOS, 'errors' => new ViewErrorBag,
            ])->render();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame($guardada ? '20' : '44', $xpath->evaluate('string(//input[@name="horas_necesarias"]/@value)'));
            $this->assertSame($guardada ? '24' : '0', $xpath->evaluate('string(//input[@name="horas"]/@value)'));
            $this->assertSame('44', $xpath->evaluate('string(//input[@name="horas"]/@max)'));
            $this->assertStringContainsString('Horas necesarias + horas no necesarias = 44 h', $html);
        }
    }

    public function test_continuidad_se_guarda_sin_cambiar_el_contrato_ni_las_asignaciones_actuales(): void
    {
        $this->instalarContinuidad();
        $this->guardar(['horas_necesarias' => 44, 'horas' => 0, 'motivo' => 'proceso_bir', 'considerar_dotacion_siguiente' => '0']);
        $this->assertFalse(DotacionDocenteExclusion::sole()->considerar_dotacion_siguiente);
        $this->assertSame(['111111111' => false], DotacionDocenteExclusion::continuidadPorRut(1, 2026));
        $this->assertSame([], DotacionDocenteExclusion::continuidadPorRut(1, 2027));
        $this->assertSame([], DotacionDocenteExclusion::continuidadPorRut(2, 2026));
        $this->assertSame(44.0, $this->docente()['horas_contrato']);
        $this->assertSame(44.0, $this->docente()['horas_asignadas_total']);

        $this->guardar(['horas_necesarias' => 44, 'horas' => 0]); // Formulario anterior conserva el check.
        $this->assertFalse(DotacionDocenteExclusion::sole()->considerar_dotacion_siguiente);
        $this->guardar(['horas_necesarias' => 44, 'horas' => 0, 'considerar_dotacion_siguiente' => '1']);
        $this->assertTrue(DotacionDocenteExclusion::sole()->considerar_dotacion_siguiente);
        $this->assertDatabaseCount('dotacion_docente_exclusiones', 1);
    }

    public function test_proceso_bir_siempre_conserva_contrato_completo_y_lo_proyecta_como_vacante(): void
    {
        $this->instalarContinuidad();
        (require database_path('migrations/2026_09_14_170000_add_conservar_horas_to_dotacion_docente_exclusiones.php'))->up();

        $this->guardar([
            'motivo' => 'proceso_bir',
            'horas_necesarias' => 0,
            'horas' => 44,
            'considerar_dotacion_siguiente' => 0,
            'conservar_horas_necesarias' => 1,
        ]);

        $docente = $this->docente();
        $this->assertSame(44.0, $docente['horas_contrato']);
        $this->assertSame(0.0, $docente['horas_excluidas']);
        $this->assertSame(0.0, (float) DotacionDocenteExclusion::sole()->horas);

        $html = view('admin.dotacion-establecimiento.partials._docentes', [
            'docentes' => collect([$docente]),
            'establecimiento' => Establecimiento::findOrFail(1),
            'anio' => 2026,
            'canManageDocenteExclusiones' => true,
            'docenteExclusionesTableReady' => true,
            'motivosExclusionDocente' => DotacionDocenteExclusion::MOTIVOS,
            'errors' => new ViewErrorBag,
            'continuidadDisponible' => true,
            'continuidadPorRut' => DotacionDocenteExclusion::continuidadPorRut(1, 2026),
            'conservacionHorasDisponible' => true,
            'conservacionHorasPorRut' => DotacionDocenteExclusion::conservacionHorasPorRut(1, 2026),
        ])->render();
        $this->assertStringContainsString('Proceso BIR · contrato completo considerado este año', $html);
        $this->assertStringContainsString('data-proceso-bir-select', $html);
        $this->assertStringContainsString('data-proceso-bir-necesarias readonly', $html);
        $this->assertStringContainsString('data-proceso-bir-no-necesarias readonly', $html);

        $proyeccion = DotacionProyeccionCalculator::build([
            'docentes' => collect([$docente]),
            'resumen' => ['establecimiento_especial' => false],
            'asignacion' => ['asignaciones' => DotacionAsignacionCalculator::assignmentsFor(Establecimiento::findOrFail(1), 2026)],
        ], 2026, DotacionDocenteExclusion::continuidadPorRut(1, 2026), DotacionDocenteExclusion::conservacionHorasPorRut(1, 2026));

        $this->assertSame(44.0, $proyeccion['contratos_vacantes']['aula']);
        $this->assertSame(44.0, $proyeccion['horas_vacantes_por_cubrir']);
    }

    public function test_migracion_conserva_situaciones_historicas_marcadas_y_es_idempotente(): void
    {
        $this->guardar();
        $antes = DotacionDocenteExclusion::sole()->getAttributes();
        $this->instalarContinuidad();
        $this->instalarContinuidad();
        $despues = DotacionDocenteExclusion::sole();
        $this->assertTrue($despues->considerar_dotacion_siguiente);
        $this->assertEquals($antes, array_diff_key($despues->getAttributes(), ['considerar_dotacion_siguiente' => true]));
    }

    public function test_rechaza_check_sin_migracion_y_valores_invalidos(): void
    {
        try {
            $this->guardar(['considerar_dotacion_siguiente' => 0]);
            $this->fail('No debe perder silenciosamente la decisión de continuidad.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('considerar_dotacion_siguiente', $exception->errors());
            $this->assertDatabaseCount('dotacion_docente_exclusiones', 0);
        }
        $this->instalarContinuidad();
        $this->expectException(ValidationException::class);
        $this->guardar(['considerar_dotacion_siguiente' => 'tal vez']);
    }

    public function test_check_reabre_estado_guardado_y_restaurar_situacion_elimina_la_salida_prevista(): void
    {
        $this->instalarContinuidad();
        foreach ([1, 0] as $continua) {
            $this->guardar(['considerar_dotacion_siguiente' => $continua]);
            $html = view('admin.dotacion-establecimiento.partials._docentes', [
                'docentes' => collect([$this->docente()]), 'establecimiento' => Establecimiento::findOrFail(1),
                'anio' => 2026, 'canManageDocenteExclusiones' => true, 'docenteExclusionesTableReady' => true,
                'motivosExclusionDocente' => DotacionDocenteExclusion::MOTIVOS, 'errors' => new ViewErrorBag,
                'continuidadDisponible' => true, 'continuidadPorRut' => DotacionDocenteExclusion::continuidadPorRut(1, 2026),
            ])->render();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame((bool) $continua, $xpath->evaluate('boolean(//input[@type="checkbox" and @name="considerar_dotacion_siguiente"]/@checked)'));
            $this->assertSame('0', $xpath->evaluate('string(//input[@type="hidden" and @name="considerar_dotacion_siguiente"]/@value)'));
            $this->assertStringContainsString('El docente continúa en dotación 2027', $html);
        }
        app(DotacionDocenteExclusionController::class)->destroy($this->request(), Establecimiento::findOrFail(1), DotacionDocenteExclusion::sole());
        $this->assertSame([], DotacionDocenteExclusion::continuidadPorRut(1, 2026));
        $this->assertSame(44.0, $this->docente()['horas_asignadas_total']);
    }

    private function instalarContinuidad(): void
    {
        (require database_path('migrations/2026_09_14_160000_add_continuidad_to_dotacion_docente_exclusiones.php'))->up();
    }

    public function test_conservacion_y_salida_se_guardan_independientes_y_con_alcance_anual(): void
    {
        $this->instalarContinuidad();
        $this->guardar(['considerar_dotacion_siguiente' => 0]);
        $antes = DotacionDocenteExclusion::sole()->getAttributes();
        $migracion = require database_path('migrations/2026_09_14_170000_add_conservar_horas_to_dotacion_docente_exclusiones.php');
        $migracion->up();
        $migracion->up();
        $this->assertEquals($antes, array_diff_key(DotacionDocenteExclusion::sole()->getAttributes(), ['conservar_horas_necesarias' => true]));
        $this->assertTrue(DotacionDocenteExclusion::sole()->conservar_horas_necesarias);

        foreach ([[0, 1], [0, 0], [1, 1], [1, 0]] as [$continua, $conserva]) {
            $this->guardar(['considerar_dotacion_siguiente' => $continua, 'conservar_horas_necesarias' => $conserva]);
            $this->assertSame((bool) $continua, DotacionDocenteExclusion::sole()->considerar_dotacion_siguiente);
            $this->assertSame(['111111111' => (bool) $conserva], DotacionDocenteExclusion::conservacionHorasPorRut(1, 2026));
            $this->assertSame([], DotacionDocenteExclusion::conservacionHorasPorRut(1, 2027));
            $this->assertSame([], DotacionDocenteExclusion::conservacionHorasPorRut(2, 2026));
            $this->assertSame(44.0, $this->docente()['horas_contrato_base']);
            $this->assertSame(20.0, $this->docente()['horas_contrato']);
            $this->assertSame(44.0, $this->docente()['horas_asignadas_total']);

            $html = view('admin.dotacion-establecimiento.partials._docentes', [
                'docentes' => collect([$this->docente()]), 'establecimiento' => Establecimiento::findOrFail(1),
                'anio' => 2026, 'canManageDocenteExclusiones' => true, 'docenteExclusionesTableReady' => true,
                'motivosExclusionDocente' => DotacionDocenteExclusion::MOTIVOS, 'errors' => new ViewErrorBag,
                'continuidadDisponible' => true, 'continuidadPorRut' => DotacionDocenteExclusion::continuidadPorRut(1, 2026),
                'conservacionHorasDisponible' => true, 'conservacionHorasPorRut' => DotacionDocenteExclusion::conservacionHorasPorRut(1, 2026),
            ])->render();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            $this->assertSame((bool) $continua, $xpath->evaluate('boolean(//input[@type="checkbox" and @name="considerar_dotacion_siguiente"]/@checked)'));
            $this->assertSame((bool) $conserva, $xpath->evaluate('boolean(//input[@type="checkbox" and @name="conservar_horas_necesarias"]/@checked)'));
            $this->assertSame('0', $xpath->evaluate('string(//input[@type="hidden" and @name="conservar_horas_necesarias"]/@value)'));
            $this->assertStringContainsString('Contemplar horas necesarias en dotación 2027', $html);
        }
        $this->guardar(); // Una pantalla antigua no cambia ninguna de las dos decisiones.
        $this->assertFalse(DotacionDocenteExclusion::sole()->conservar_horas_necesarias);
        $this->assertTrue(DotacionDocenteExclusion::sole()->considerar_dotacion_siguiente);
    }

    public function test_rechaza_conservacion_sin_migracion_y_valores_no_booleanos(): void
    {
        $this->assertFalse(DotacionDocenteExclusion::conservacionHorasDisponible());
        $this->assertSame([], DotacionDocenteExclusion::conservacionHorasPorRut(1, 2026));
        try {
            $this->guardar(['conservar_horas_necesarias' => 1]);
            $this->fail('No puede guardar una decisión sin su columna.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('conservar_horas_necesarias', $exception->errors());
            $this->assertDatabaseCount('dotacion_docente_exclusiones', 0);
        }
        (require database_path('migrations/2026_09_14_170000_add_conservar_horas_to_dotacion_docente_exclusiones.php'))->up();
        $this->expectException(ValidationException::class);
        $this->guardar(['conservar_horas_necesarias' => 'tal vez']);
    }

    #[DataProvider('categorias')]
    public function test_contrato_proyectado_conserva_vacantes_y_descuenta_solo_no_necesarias(
        string $titulo, bool $coordinacion, bool $especial, float $aula, float $parvularia, float $pie
    ): void {
        $this->instalarContinuidad();
        (require database_path('migrations/2026_09_14_170000_add_conservar_horas_to_dotacion_docente_exclusiones.php'))->up();
        DB::table('declaracion_sostenedores')->update(['nombre_titulo' => $titulo]);
        if ($coordinacion) {
            DB::table('dotacion_docente_asignaciones')->update([
                'tipo_asignacion' => 'funcion_tecnico_pedagogica', 'subtipo_asignacion' => 'pie',
                'asignatura_nombre' => 'Coordinador PIE',
            ]);
        }
        $categoria = $pie > 0 ? 'pie' : ($parvularia > 0 ? 'parvularia' : 'aula');
        if ($titulo === 'Educadora Diferencial' && ! $especial) {
            $categoria = 'aula';
        }
        $padron = DB::table('reemplazos_personal')->get()->toJson();
        $asignaciones = DB::table('dotacion_docente_asignaciones')->get()->toJson();
        foreach ([34.0, 0.0, 44.0, 19.37] as $necesarias) {
            foreach ([[0, 1], [0, 0], [1, 1], [1, 0]] as [$continua, $conserva]) {
                $this->guardar([
                    'horas_necesarias' => $necesarias, 'horas' => 44 - $necesarias,
                    'considerar_dotacion_siguiente' => $continua, 'conservar_horas_necesarias' => $conserva,
                ]);
                $base = [
                    'docentes' => collect([$this->docente()]),
                    'resumen' => ['establecimiento_especial' => $especial],
                    'asignacion' => ['asignaciones' => DotacionAsignacionCalculator::assignmentsFor(Establecimiento::findOrFail(1), 2026)],
                ];
                $proyeccion = DotacionProyeccionCalculator::build($base, 2026,
                    DotacionDocenteExclusion::continuidadPorRut(1, 2026), DotacionDocenteExclusion::conservacionHorasPorRut(1, 2026));
                $contrato = $continua || $conserva ? $necesarias : 0.0;
                $vacantes = ! $continua && $conserva ? $necesarias : 0.0;
                $this->assertSame($contrato, $proyeccion['contratos']['total']);
                $this->assertSame($contrato, $proyeccion['contratos'][$categoria]);
                $this->assertSame($vacantes, $proyeccion['horas_vacantes_por_cubrir']);
                $this->assertSame($vacantes, $proyeccion['contratos_vacantes'][$categoria]);
                $this->assertSame($continua ? $necesarias : 0.0, $proyeccion['contratos_cubiertos']['total']);
                $this->assertSame($continua ? $necesarias : 0.0, $proyeccion['docentes'][0]['contrato_proyectado']);
                $this->assertSame((bool) $continua, $proyeccion['docentes'][0]['continua']);
                $this->assertSame(44.0, $proyeccion['docentes'][0]['contrato_base']);
                $this->assertSame($contrato, round(array_sum(array_intersect_key($proyeccion['contratos'], array_flip(['aula', 'parvularia', 'pie']))), 2));
            }
        }
        $this->assertSame($padron, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertSame($asignaciones, DB::table('dotacion_docente_asignaciones')->get()->toJson());
    }

    private function guardar(array $data = []): \Illuminate\Http\RedirectResponse
    {
        return app(DotacionDocenteExclusionController::class)->store($this->request($data), Establecimiento::findOrFail(1));
    }

    private function request(array $data = []): Request
    {
        $request = Request::create('/dotacion/docentes/exclusiones', 'POST', array_replace([
            'anio' => 2026, 'docente_rut' => '11111111-1', 'motivo' => 'horas_gremiales',
            'horas_necesarias' => 20, 'horas' => 24,
        ], $data));
        $request->setUserResolver(fn () => new class {
            public int $id = 99;

            public function activeRoleName(): string
            {
                return 'admin';
            }
        });

        return $request;
    }

    private function docente(): array
    {
        return DotacionEstablecimientoCalculator::docentes(Establecimiento::findOrFail(1), 2026)->sole();
    }

    private function resetSchemaCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) {
                (new ReflectionProperty($class, $name))->setValue(null, []);
            }
        }
    }
}
