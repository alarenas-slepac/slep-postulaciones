<?php

namespace Tests\Feature;

use App\Models\Asignatura;
use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\PlanEstudio;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionCursoCombinadoCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionHcBloquesConfiguradosTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    private EstablecimientoCurso $curso;

    private PlanEstudio $plan;

    private int $configId;

    private array $bloques = [];

    private string $nombreDiferenciado;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
        foreach (['2026_01_23_000001_create_establecimientos_table.php', '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_18_130000_create_cursos_table.php', '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_150000_create_asignaturas_table.php', '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php', '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php', '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        // Tablas nuevas en SQLite :memory:, sin ejecutar la migración histórica que recrea la configuración.
        Schema::create('establecimiento_planes_estudio', function (Blueprint $t) {
            $t->id();
            foreach (['establecimiento_id', 'establecimiento_curso_id', 'curso_id', 'plan_estudio_id', 'anio'] as $field) {
                $t->integer($field);
            }
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $t) {
            $t->id();
            foreach (['establecimiento_plan_estudio_id', 'plan_estudio_bloque_id', 'asignatura_id', 'asignatura_plan_comun_id', 'orden'] as $field) {
                $t->integer($field)->nullable();
            }
            foreach (['nombre_asignatura_personalizada', 'origen', 'observacion'] as $field) {
                $t->string($field)->nullable();
            }
            $t->decimal('horas_semanales', 8, 2);
            $t->decimal('horas_anuales', 8, 2)->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    public static function planesHc(): array
    {
        $cases = [];
        foreach (['3M-HC', '4M-HC'] as $code) {
            $cases[$code.' histórico'] = [$code, 'plan_diferenciado', 'plan_comun_electivo', 'Plan Diferenciado Humanístico-Científica'];
            $cases[$code.' importado'] = [$code, 'asignatura', 'asignatura', 'Plan Diferenciado Humanístico-Científico'];
            $cases[$code.' tiempo mínimo'] = [$code, 'tiempo_minimo', 'tiempo_minimo', 'PLAN DIFERENCIADO HUMANISTICO CIENTIFICO'];
        }

        return $cases;
    }

    #[DataProvider('planesHc')]
    public function test_desglose_hc_reemplaza_totales_y_conserva_asignaciones(string $code, string $tipoHc, string $tipoElectivo, string $nombreHc): void
    {
        $this->configurar($code, $tipoHc, $tipoElectivo, $nombreHc);
        foreach ($this->materias() as [$nombre, $horas, $tipo]) {
            $this->asignar($nombre, $horas, $tipo);
        }
        $before = $this->snapshot();
        $needs = $this->necesidades();

        $this->assertSame(42.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertSame(20.0, (float) $needs->sum('horas_plan_asignadas'));
        $this->assertSame(22.0, (float) $needs->sum('horas_plan_pendientes'));
        $this->assertFalse($needs->contains('titulo', $nombreHc));
        $this->assertFalse($needs->contains('titulo', 'Plan Común Formación General Electivo'));
        $this->assertSame(18.0, (float) $needs->where('subtipo_asignacion', 'plan_diferenciado_hc')->sum('horas_plan_requeridas'));
        $this->assertSame(2.0, (float) $needs->where('subtipo_asignacion', 'plan_comun_formacion_general_electivo')->sum('horas_plan_requeridas'));
        foreach ($this->materias() as [$nombre, $horas, $tipo]) {
            $need = $needs->firstWhere('titulo', $nombre);
            $this->assertSame($horas, $need['horas_plan_requeridas']);
            $this->assertSame($horas, $need['horas_plan_asignadas']);
            $this->assertSame(0.0, $need['horas_plan_pendientes']);
            $this->assertCount(1, $need['asignaciones']);
            $this->assertSame($this->key($tipo, $nombre), $need['key']);
            $this->assertNotNull(DotacionAsignacionCalculator::planNeedForKey($this->ee, 2027, $need['key']));
        }
        $huerfanas = (new ReflectionMethod(DotacionAsignacionCalculator::class, 'asignacionesHuerfanas'))->invoke(null,
            DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027), ['plan_estudio' => $needs]);
        $this->assertCount(0, $huerfanas);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_sin_desglose_conserva_los_dos_bloques_oficiales(): void
    {
        $this->configurar('3M-HC');
        DB::table('establecimiento_planes_estudio_asignaturas')->delete();
        $needs = $this->necesidades();
        $this->assertSame(42.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertSame(18.0, $needs->firstWhere('titulo', $this->nombreDiferenciado)['horas_plan_requeridas']);
        $this->assertSame(2.0, $needs->firstWhere('titulo', 'Plan Común Formación General Electivo')['horas_plan_requeridas']);
    }

    public function test_configurar_solo_electivo_no_retira_diferenciado_ni_otras_modalidades(): void
    {
        $this->configurar('4M-HC');
        DB::table('establecimiento_planes_estudio_asignaturas')->where('plan_estudio_bloque_id', $this->bloques['plan_diferenciado_hc'])->delete();
        $this->plan->asignaturas()->create(['asignatura' => 'Plan Diferenciado Técnico-Profesional', 'tipo_bloque' => 'plan_diferenciado', 'horas_semanales' => 22]);
        $this->plan->update(['horas_semanales_total' => 64]);
        $needs = $this->necesidades();
        $this->assertSame(64.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertTrue($needs->contains('titulo', $this->nombreDiferenciado));
        $this->assertTrue($needs->contains('titulo', 'Plan Diferenciado Técnico-Profesional'));
        $this->assertTrue($needs->contains('titulo', 'Artes'));
        $this->assertFalse($needs->contains('titulo', 'Plan Común Formación General Electivo'));
    }

    public function test_configuracion_incompleta_conserva_horas_faltantes_reales(): void
    {
        $this->configurar('3M-HC');
        DB::table('establecimiento_planes_estudio_asignaturas')->where('nombre_asignatura_personalizada', 'Taller de Literatura')->delete();
        $needs = $this->necesidades();
        $this->assertSame(42.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertFalse($needs->contains('titulo', $this->nombreDiferenciado));
        $this->assertSame(6.0, $needs->firstWhere('subtipo_asignacion', 'plan_sin_desglose')['horas_plan_pendientes']);
    }

    public function test_plan_comun_configurado_no_oculta_electivo_importado_sin_desglose(): void
    {
        $this->configurar('4M-HC', 'asignatura', 'asignatura');
        DB::table('establecimiento_planes_estudio_asignaturas')->where('plan_estudio_bloque_id', $this->bloques['plan_comun_formacion_general_electivo'])->delete();
        $bloque = $this->plan->bloques()->create(['nombre' => 'Plan común', 'tipo_bloque' => 'plan_comun_formacion_general',
            'horas_semanales' => 14, 'activo' => true]);
        DB::table('establecimiento_planes_estudio_asignaturas')->insert(['establecimiento_plan_estudio_id' => $this->configId,
            'plan_estudio_bloque_id' => $bloque->id, 'asignatura_id' => Asignatura::where('nombre', 'Lengua y Literatura')->value('id'),
            'nombre_asignatura_personalizada' => 'Plan común configurado', 'horas_semanales' => 14, 'orden' => 1]);
        $needs = $this->necesidades();
        $this->assertSame(42.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertSame(14.0, $needs->firstWhere('titulo', 'Plan común configurado')['horas_plan_requeridas']);
        $this->assertSame(2.0, $needs->firstWhere('titulo', 'Plan Común Formación General Electivo')['horas_plan_pendientes']);
        $this->assertFalse($needs->contains('subtipo_asignacion', 'plan_sin_desglose'));
    }

    public function test_equivalencia_electivo_conserva_claves_y_suma_ambas_asignaciones_una_vez(): void
    {
        $this->configurar('3M-HC');
        $this->plan->asignaturas()->where('asignatura', 'Plan Común Formación General Electivo')->update(['asignatura' => 'Artes']);
        $this->asignar('Artes', 1, 'plan_comun_electivo');
        $this->asignar('Artes', 1, 'plan_comun_formacion_general_electivo');
        $before = $this->snapshot();
        $needs = $this->necesidades();
        $artes = $needs->firstWhere('titulo', 'Artes');
        $this->assertSame(42.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertSame(2.0, (float) $needs->sum('horas_plan_asignadas'));
        $this->assertSame(0.0, $artes['horas_plan_pendientes']);
        $this->assertCount(2, $artes['asignaciones']);
        foreach (['plan_comun_electivo', 'plan_comun_formacion_general_electivo'] as $tipo) {
            $this->assertNotNull(DotacionAsignacionCalculator::planNeedForKey($this->ee, 2027, $this->key($tipo, 'Artes')));
        }
        $huerfanas = (new ReflectionMethod(DotacionAsignacionCalculator::class, 'asignacionesHuerfanas'))->invoke(null,
            DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027), ['plan_estudio' => $needs]);
        $this->assertCount(0, $huerfanas);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_asignacion_del_total_antiguo_no_se_reparte_entre_tres_materias(): void
    {
        $this->configurar('4M-HC');
        $this->asignar($this->nombreDiferenciado, 18, 'plan_diferenciado');
        $before = $this->snapshot();
        $needs = $this->necesidades();
        $this->assertSame(42.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertSame(0.0, (float) $needs->where('subtipo_asignacion', 'plan_diferenciado_hc')->sum('horas_plan_asignadas'));
        $huerfanas = (new ReflectionMethod(DotacionAsignacionCalculator::class, 'asignacionesHuerfanas'))->invoke(null,
            DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027), ['plan_estudio' => $needs]);
        $this->assertCount(1, $huerfanas);
        $this->assertSame($before, $this->snapshot());
    }

    private function configurar(string $code, string $tipoHc = 'plan_diferenciado', string $tipoElectivo = 'plan_comun_electivo', string $nombreHc = 'Plan Diferenciado Humanístico-Científica'): void
    {
        $this->nombreDiferenciado = $nombreHc;
        $this->ee = Establecimiento::create(['cod_estab' => 'SINTETICO', 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']);
        $catalogo = Curso::where('codigo', $code)->firstOrFail();
        $this->plan = PlanEstudio::create(['curso_id' => $catalogo->id, 'anio' => 2027, 'nombre_plan' => 'Plan HC sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_subtotal' => 34, 'horas_semanales_libre_disposicion' => 8,
            'horas_semanales_total' => 42, 'activo' => true]);
        $this->curso = EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'curso_id' => $catalogo->id,
            'plan_estudio_id' => $this->plan->id, 'anio' => 2027, 'letra' => 'A', 'nombre_seccion' => $code.' A',
            'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true]);
        foreach (['Lengua y Literatura' => 3, 'Matemática' => 3, 'Inglés' => 2, 'Educación Ciudadana' => 2,
            'Filosofía' => 2, 'Ciencias para la Ciudadanía' => 2] as $nombre => $horas) {
            $this->plan->asignaturas()->create(['asignatura' => $nombre, 'tipo_bloque' => 'asignatura', 'horas_semanales' => $horas]);
        }
        foreach ([[$nombreHc, $tipoHc, 18], ['Plan Común Formación General Electivo', $tipoElectivo, 2],
            ['Horas de libre disposición', 'libre_disposicion', 8]] as [$nombre, $tipo, $horas]) {
            $this->plan->asignaturas()->create(['asignatura' => $nombre, 'tipo_bloque' => $tipo, 'horas_semanales' => $horas]);
        }
        foreach (['plan_diferenciado_hc' => 18, 'plan_comun_formacion_general_electivo' => 2] as $tipo => $horas) {
            $this->bloques[$tipo] = $this->plan->bloques()->create(['nombre' => $tipo, 'tipo_bloque' => $tipo,
                'horas_semanales' => $horas, 'activo' => true, 'permite_asignaturas_establecimiento' => true,
                'permite_asignaturas_personalizadas' => true])->id;
        }
        $this->configId = DB::table('establecimiento_planes_estudio')->insertGetId(['establecimiento_id' => $this->ee->id,
            'establecimiento_curso_id' => $this->curso->id, 'plan_estudio_id' => $this->plan->id, 'curso_id' => $catalogo->id, 'anio' => 2027]);
        foreach ($this->materias() as [$nombre, $horas, $tipo]) {
            DB::table('establecimiento_planes_estudio_asignaturas')->insert(['establecimiento_plan_estudio_id' => $this->configId,
                'plan_estudio_bloque_id' => $this->bloques[$tipo], 'asignatura_id' => Asignatura::where('nombre', $nombre)->value('id'),
                'nombre_asignatura_personalizada' => $nombre, 'horas_semanales' => $horas, 'orden' => 1]);
        }
    }

    private function materias(): array
    {
        return [['Biología Celular y Molecular', 6.0, 'plan_diferenciado_hc'], ['Economía y Sociedad', 6.0, 'plan_diferenciado_hc'],
            ['Taller de Literatura', 6.0, 'plan_diferenciado_hc'], ['Artes', 2.0, 'plan_comun_formacion_general_electivo']];
    }

    private function necesidades()
    {
        return DotacionLecturaCache::ejecutar(fn () => DotacionAsignacionCalculator::necesidades($this->ee, 2027,
            [], [], DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027))['plan_estudio']);
    }

    private function key(string $tipo, string $nombre): string
    {
        return 'plan:'.md5($this->curso->id.'|'.$tipo.'|'.$nombre);
    }

    private function asignar(string $nombre, float $horas, string $tipo): void
    {
        DotacionDocenteAsignacion::create(['establecimiento_id' => $this->ee->id, 'anio' => 2027,
            'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente sintético',
            'establecimiento_curso_id' => $this->curso->id, 'plan_estudio_id' => $this->plan->id,
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => $tipo, 'necesidad_key' => $this->key($tipo, $nombre),
            'asignatura_nombre' => $nombre, 'horas_plan_pedagogicas' => $horas, 'horas_contrato' => $horas, 'estado' => 'activa']);
    }

    private function snapshot(): string
    {
        return json_encode(collect(['planes_estudio', 'planes_estudio_asignaturas', 'establecimiento_planes_estudio',
            'establecimiento_planes_estudio_asignaturas', 'dotacion_docente_asignaciones'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()])->all());
    }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) {
                (new ReflectionProperty($class, $name))->setValue(null, []);
            }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }
}
