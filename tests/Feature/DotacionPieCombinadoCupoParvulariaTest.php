<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Models\DotacionCursoCombinado;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Dotacion\NoRenovacionService;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionPieCombinadoCupoParvulariaTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
        $this->withoutVite();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        Schema::create('cursos', function (Blueprint $t): void {
            $t->id(); $t->string('codigo'); $t->string('nombre'); $t->string('nivel_educativo');
        });
        Schema::create('establecimiento_cursos', function (Blueprint $t): void {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->unsignedBigInteger('curso_id');
            $t->unsignedBigInteger('plan_estudio_id')->nullable(); $t->integer('anio');
            $t->string('regimen_jec'); $t->string('nombre_seccion'); $t->timestamps();
        });
        foreach (['2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php',
            '2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->string('estamento_cobertura')->default('docente'); $t->text('excepcion_prelacion')->nullable();
        });
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SYNTHETIC-PIE-NT', 'nombre_establecimiento' => 'Escuela sintética']);
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    private function resetCaches(): void
    {
        DotacionCursoCombinadoCalculator::clearCache();
        foreach ([DotacionAsignacionCalculator::class, DotacionEstablecimientoCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) { (new ReflectionProperty($class, $property))->setValue(null, []); }
        }
    }

    private function preparar(string $nivel = 'NT', string $jec = 'Con JEC', int $anio = 2027, bool $soloSegundo = false): array
    {
        $items = collect(); $miembros = [];
        foreach ([1, 2] as $numero) {
            $codigo = $nivel === 'NT' ? 'NT'.$numero : $numero.'B';
            $curso = DB::table('cursos')->insertGetId(['codigo' => $codigo, 'nombre' => $codigo, 'nivel_educativo' => $nivel === 'NT' ? 'Parvularia' : 'Básica']);
            $eeCurso = EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'curso_id' => $curso,
                'anio' => $anio, 'regimen_jec' => $jec, 'nombre_seccion' => $codigo.' A']);
            $miembros[] = $eeCurso->id;
            if (! $soloSegundo || $numero === 2) {
                $items->push((new ReflectionMethod(DotacionAsignacionCalculator::class, 'needRow'))->invoke(null,
                    'pie_colab:'.$eeCurso->id, 'pie_colaborativo', 'trabajo_colaborativo', [
                        'titulo' => 'Trabajo colaborativo PIE', 'curso_label' => $codigo.' A',
                        'establecimiento_curso_id' => $eeCurso->id, 'horas_contrato' => 3,
                    ], collect()));
            }
        }
        $grupo = DotacionCursoCombinado::create(['establecimiento_id' => $this->ee->id, 'anio' => $anio,
            'nombre' => $nivel === 'NT' ? 'NT1 + NT2 sintético' : '1° + 2° Básico sintético', 'activo' => true]);
        foreach ($miembros as $id) { $grupo->miembros()->create(['establecimiento_curso_id' => $id]); }
        foreach (['plan_estudio', 'parvularia', 'pie'] as $bloque) {
            DB::table('dotacion_contrata_habilitaciones')->insert(['establecimiento_id' => $this->ee->id, 'anio' => $anio, 'bloque' => $bloque, 'horas' => 10]);
        }
        $need = DotacionCursoCombinadoCalculator::applyCollaborativePie($items, collect(), $this->ee, $anio)->sole();
        $docentes = app(ContratacionHabilitacionService::class)->docentesVirtuales($this->ee, $anio);

        return [$need, $docentes];
    }

    public static function casosNt(): array
    {
        return ['con JEC 2027' => ['Con JEC', 2027, false], 'sin JEC 2027' => ['Sin JEC', 2027, false],
            'NEE sólo en NT2' => ['Con JEC', 2027, true], 'histórico 2026' => ['Con JEC', 2026, false]];
    }

    #[DataProvider('casosNt')]
    public function test_el_selector_ofrece_el_cupo_de_parvularia_en_el_grupo_nt(string $jec, int $anio, bool $soloSegundo): void
    {
        [$need, $docentes] = $this->preparar('NT', $jec, $anio, $soloSegundo);
        $parvularia = $docentes->firstWhere('cupo_bloque', 'parvularia');
        $this->assertInstanceOf(EstablecimientoCurso::class, $need['curso'] ?? null);
        $this->assertSame($need['establecimiento_curso_id'], $need['curso']->id);
        $this->assertSame($soloSegundo ? 'NT2' : 'NT1', $need['curso']->curso->codigo);
        $this->assertSame('parvularia', NoRenovacionService::bloqueAsignacion([...$need, 'establecimientoCurso' => $need['curso']], $parvularia));
        $this->assertSame(3.0, $need['horas_contrato_requeridas']);
        $opciones = $this->opciones($need, $docentes, $anio);
        $this->assertContains($parvularia['rut'], $opciones);
        $this->assertNotContains($docentes->firstWhere('cupo_bloque', 'plan_estudio')['rut'], $opciones);
        $this->assertNotContains($docentes->firstWhere('cupo_bloque', 'pie')['rut'], $opciones);
    }

    public function test_el_grupo_basico_conserva_el_cupo_de_plan_general(): void
    {
        [$need, $docentes] = $this->preparar('basica');
        $opciones = $this->opciones($need, $docentes, 2027);
        $this->assertContains($docentes->firstWhere('cupo_bloque', 'plan_estudio')['rut'], $opciones);
        $this->assertNotContains($docentes->firstWhere('cupo_bloque', 'parvularia')['rut'], $opciones);
        $this->assertNotContains($docentes->firstWhere('cupo_bloque', 'pie')['rut'], $opciones);
    }

    public function test_payload_y_validacion_del_servidor_admiten_parvularia_y_respetan_el_saldo(): void
    {
        [$need, $docentes] = $this->preparar('NT', 'Sin JEC');
        $docente = $docentes->firstWhere('cupo_bloque', 'parvularia');
        $controller = app(DotacionAsignacionController::class);
        $data = ['anio' => 2027, 'estamento_cobertura' => 'docente', 'tipo_asignacion' => 'pie_colaborativo',
            'subtipo_asignacion' => 'trabajo_colaborativo', 'horas_contrato' => 3,
            'establecimiento_curso_id' => $need['establecimiento_curso_id'], 'necesidad_key' => $need['key'],
            'dotacion_curso_combinado_id' => $need['dotacion_curso_combinado_id']];
        $request = Request::create('/asignacion', 'POST', $data);
        $payload = (new ReflectionMethod($controller, 'buildPayload'))->invoke($controller, $request, $this->ee, $docente, $data);
        $validar = new ReflectionMethod($controller, 'validateVirtualAssignment');
        DB::transaction(function () use ($validar, $controller, $docente, $payload): void {
            $validar->invoke($controller, $this->ee, $docente, $payload);
            DotacionDocenteAsignacion::create($payload);
        });
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['docente_rut_normalizado' => $docente['rut_normalizado'],
            'tipo_asignacion' => 'pie_colaborativo', 'horas_contrato' => 3, 'dotacion_curso_combinado_id' => $need['dotacion_curso_combinado_id'], 'subvencion' => 'PIE']);
        try {
            $validar->invoke($controller, $this->ee, $docente, array_replace($payload, ['horas_contrato' => 8]));
            $this->fail('El cupo debe conservar su límite contractual.');
        } catch (ValidationException $e) { $this->assertArrayHasKey('horas_contrato', $e->errors()); }
        try {
            $validar->invoke($controller, $this->ee, $docentes->firstWhere('cupo_bloque', 'plan_estudio'), $payload);
            $this->fail('Un cupo de otro bloque no puede recibir estas horas NT.');
        } catch (ValidationException $e) { $this->assertArrayHasKey('docente_rut', $e->errors()); }
    }

    private function opciones(array $need, $docentes, int $anio): array
    {
        $html = view('admin.dotacion-establecimiento.partials._asignacion', [
            'establecimiento' => $this->ee, 'anio' => $anio, 'docentes' => $docentes, 'activeRole' => 'admin',
            'asignacion' => ['docentes' => $docentes, 'asistentes' => [], 'necesidades' => ['pie_colaborativo' => [$need]]],
            'proceso2027' => ['aplica' => $anio === 2027, 'asignacion_habilitada' => true, 'docentes' => $docentes],
            'errors' => new ViewErrorBag,
        ])->render();
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $referencia = $xpath->query('//select[@name="docente_rut"]/optgroup[@data-personal-catalogo]')->item(0)->getAttribute('data-personal-catalogo');

        $this->assertSame(1, $xpath->query('//template[@id="'.$referencia.'"]')->length);

        return array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($xpath->query('//template[@id="'.$referencia.'"]//option')));
    }
}
