<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionLecturaCache;
use App\Support\DotacionPlanEstudioResolver;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionListadoRendimientoTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCalculators();
        // Sólo tablas sintéticas en SQLite :memory:, nunca el respaldo ni la BD real.
        foreach ([
            '2026_01_23_000001_create_establecimientos_table.php',
            '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public static function years(): array
    {
        return ['año vigente' => [2026], 'proyección con padrón anterior' => [2027]];
    }

    #[DataProvider('years')]
    public function test_listado_conserva_indicadores_y_omite_calculo_de_asignaciones(int $year): void
    {
        [$ee] = $this->course($year);
        foreach ([['PLANTA', 30], ['CONTRATA', 8]] as $index => [$type, $hours]) {
            DB::table('reemplazos_personal')->insert([
                'establecimiento_id' => $ee->id, 'rbd' => $ee->rbd,
                'rut' => '99000001-K', 'nombre' => 'Docente sintético',
                'anio' => 2026, 'mes' => 8, 'jornada' => $hours,
                'jornada_basica' => $hours, 'tipocontrato' => $type,
                'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE',
                'row_hash' => 'synthetic-'.$index,
            ]);
        }
        DB::table('dotacion_docente_asignaciones')->insert([
            'establecimiento_id' => $ee->id, 'anio' => $year,
            'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K',
            'tipo_asignacion' => 'otra_funcion', 'horas_contrato' => 6,
        ]);
        $full = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($ee, $year, false));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $listing = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::resumenListado($ee, $year));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(array_intersect_key($full['resumen'], $listing['resumen']), $listing['resumen']);
        $this->assertSame($full['bloques'], $listing['bloques']);
        $this->assertSame(1, $listing['resumen']['docentes_total']);
        $this->assertEquals(38, $listing['resumen']['horas_contrato_docentes']);
        $this->assertFalse(collect($queries)->contains(fn ($q) => str_contains($q['query'], 'from "dotacion_docente_asignaciones"')));
        $this->assertSame(6, DB::table('dotacion_docente_asignaciones')->value('horas_contrato'));
    }

    public function test_listado_con_cursos_combinados_conserva_total_consolidado(): void
    {
        [$ee, $course] = $this->course(2027);
        $second = $course->replicate();
        $second->letra = 'B';
        $second->save();
        $group = DB::table('dotacion_cursos_combinados')->insertGetId([
            'establecimiento_id' => $ee->id, 'anio' => 2027,
            'nombre' => 'Combinación sintética', 'proporcion' => 'auto', 'activo' => true,
        ]);
        foreach ([$course->id, $second->id] as $id) {
            DB::table('dotacion_curso_combinado_miembros')->insert([
                'dotacion_curso_combinado_id' => $group, 'establecimiento_curso_id' => $id,
            ]);
        }
        $full = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($ee, 2027, false));
        $listing = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::resumenListado($ee, 2027));
        $this->assertSame($full['resumen'], $listing['resumen']);
        $this->assertSame($full['bloques'], $listing['bloques']);
        $this->assertEquals(38, $listing['resumen']['horas_plan_total']);
    }

    #[DataProvider('years')]
    public function test_listado_conserva_libre_disposicion_nt_impartida_por_otro_docente(int $year): void
    {
        [$ee, $course] = $this->course($year, 'NT1');
        $before = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::resumenListado($ee, $year));
        DB::table('dotacion_docente_asignaciones')->insert([
            'establecimiento_id' => $ee->id, 'anio' => $year,
            'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K',
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'libre_disposicion',
            'establecimiento_curso_id' => $course->id, 'horas_plan_pedagogicas' => 2,
            'horas_contrato' => 2, 'proporcion_aplicada' => '65/35',
        ]);
        $full = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($ee, $year, false));
        $listing = DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::resumenListado($ee, $year));
        $this->assertSame(array_intersect_key($full['resumen'], $listing['resumen']), $listing['resumen']);
        $this->assertEquals($before['resumen']['horas_plan_total'] + 2, $listing['resumen']['horas_plan_total']);
        $this->assertSame($full['bloques'], $listing['bloques']);
    }

    public function test_referencias_se_consultan_una_vez_por_proporcion_y_contrato(): void
    {
        $course = (object) ['codigo' => 'B1', 'nombre' => '1 Básico', 'nivel_educativo' => 'Educación Básica'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        DotacionLecturaCache::ejecutar(function () use ($course): void {
            for ($i = 0; $i < 30; $i++) {
                $general = DocenteHorasNoLectivasCalculator::referenceFor($course, 70);
                $prioritarios = DocenteHorasNoLectivasCalculator::referenceFor($course, 90);
                $otroContrato = DocenteHorasNoLectivasCalculator::referenceFor($course, 70, 40);
                $this->assertSame('65_35', $general['proporcion']);
                $this->assertSame('60_40', $prioritarios['proporcion']);
                $this->assertSame(40, $otroContrato['horas_contrato']);
            }
        });
        $this->assertCount(3, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_siguiente_calculo_ve_actualizaciones_de_referencias(): void
    {
        $course = (object) ['codigo' => 'B1', 'nombre' => '1 Básico', 'nivel_educativo' => 'Educación Básica'];
        $before = DotacionLecturaCache::ejecutar(fn () => DocenteHorasNoLectivasCalculator::referenceFor($course, 70));
        DB::table('docente_horas_proporciones')->where('proporcion', '65_35')->where('horas_contrato', 44)
            ->update(['horas_aula_pedagogicas' => 37]);
        $after = DotacionLecturaCache::ejecutar(fn () => DocenteHorasNoLectivasCalculator::referenceFor($course, 70));
        $this->assertEquals(38, $before['horas_aula_pedagogicas']);
        $this->assertEquals(37, $after['horas_aula_pedagogicas']);
    }

    public function test_planes_historicos_sin_asociacion_valida_no_comparten_respaldo_de_otro_curso(): void
    {
        [, $first, $plan] = $this->course(2027);
        $second = $first->replicate();
        $second->letra = 'B';
        $second->regimen_jec = 'Sin JEC';
        $fallback = $plan->replicate();
        $fallback->regimen_jec = 'Sin JEC';
        $fallback->horas_semanales_total = 32;
        $fallback->save();
        // Simular en memoria la asociación histórica inexistente, sin infringir FK.
        $first->plan_estudio_id = $second->plan_estudio_id = 999999;
        DotacionLecturaCache::ejecutar(function () use ($first, $second, $plan, $fallback): void {
            $this->assertSame($plan->id, DotacionPlanEstudioResolver::resolve($first)?->id);
            $this->assertSame($fallback->id, DotacionPlanEstudioResolver::resolve($second)?->id);
        });
        $this->assertSame($plan->id, $first->fresh()->plan_estudio_id);
    }

    public function test_resolver_reutiliza_plan_y_lee_cambios_en_siguiente_calculo(): void
    {
        [, $course, $plan] = $this->course(2027);
        DB::enableQueryLog();
        DB::flushQueryLog();
        DotacionLecturaCache::ejecutar(function () use ($course, $plan): void {
            for ($i = 0; $i < 20; $i++) {
                $this->assertEquals($plan->id, DotacionPlanEstudioResolver::resolve($course)?->id);
            }
        });
        $this->assertCount(4, DB::getQueryLog()); // existencia de tabla + plan + dos relaciones
        DB::disableQueryLog();
        $plan->update(['horas_semanales_total' => 36]);
        $next = DotacionLecturaCache::ejecutar(fn () => DotacionPlanEstudioResolver::resolve($course));
        $this->assertEquals(36, $next->horas_semanales_total);
    }

    private function course(int $year, string $code = '1B'): array
    {
        $ee = Establecimiento::create([
            'rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'Establecimiento sintético',
        ]);
        $catalog = Curso::where('codigo', $code)->firstOrFail();
        $plan = PlanEstudio::create([
            'curso_id' => $catalog->id, 'anio' => $year, 'nombre_plan' => 'Plan sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_total' => 38, 'activo' => true,
        ]);
        $course = EstablecimientoCurso::create([
            'establecimiento_id' => $ee->id, 'rbd' => $ee->rbd, 'curso_id' => $catalog->id,
            'plan_estudio_id' => $plan->id, 'anio' => $year, 'letra' => 'A',
            'nombre_seccion' => $catalog->nombre.' A',
            'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true,
        ]);
        return [$ee, $course, $plan];
    }

    private function resetCalculators(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) {
                (new ReflectionProperty($class, $property))->setValue(null, []);
            }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }
}
