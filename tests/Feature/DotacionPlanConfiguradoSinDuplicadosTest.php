<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionAsignacionController;
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
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionPlanConfiguradoSinDuplicadosTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;
    private EstablecimientoCurso $curso;
    private PlanEstudio $plan;
    private int $configId;
    private int $bloqueBase;
    private int $bloqueLd;

    protected function setUp(): void
    {
        parent::setUp(); // Base :memory:, configuración aislada antes del bootstrap y discos simulados.
        $this->resetCaches();
        foreach (['2026_01_23_000001_create_establecimientos_table.php', '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_05_18_130000_create_cursos_table.php', '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_150000_create_asignaturas_table.php', '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php', '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php', '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php', '2026_07_23_160000_create_dotacion_cursos_combinados_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        // La migración histórica de configuración recrea tablas: aquí sólo se
        // crean tablas nuevas de prueba, sin ejecutar esa migración destructiva.
        Schema::create('establecimiento_planes_estudio', function (Blueprint $t) {
            $t->id();
            foreach (['establecimiento_id', 'establecimiento_curso_id', 'curso_id', 'plan_estudio_id', 'anio'] as $field) { $t->integer($field); }
        });
        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $t) {
            $t->id();
            foreach (['establecimiento_plan_estudio_id', 'plan_estudio_bloque_id', 'asignatura_id', 'asignatura_plan_comun_id', 'orden'] as $field) { $t->integer($field)->nullable(); }
            foreach (['nombre_asignatura_personalizada', 'origen', 'observacion'] as $field) { $t->string($field)->nullable(); }
            $t->decimal('horas_semanales', 8, 2); $t->decimal('horas_anuales', 8, 2)->nullable();
        });
    }

    protected function tearDown(): void { $this->resetCaches(); parent::tearDown(); }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) { (new ReflectionProperty($class, $name))->setValue(null, []); }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
        DotacionCursoCombinadoCalculator::clearCache();
    }

    public static function cursos(): array
    {
        return [['1M', 2027], ['2M', 2027], ['EPJA-1M', 2027], ['EPJA-2M', 2027], ['1M', 2026], ['2M', 2027, 'tiempo_minimo']];
    }

    #[DataProvider('cursos')]
    public function test_configuracion_sustituye_plan_comun_y_conserva_42_horas(string $code, int $year, string $tipoBase = 'plan_comun_formacion_general'): void
    {
        $this->configurar($code, $year, $tipoBase);
        $before = $this->snapshot();
        $needs = $this->necesidades();
        $this->assertSame(42.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertCount(13, $needs);
        $this->assertCount(13, $needs->pluck('key')->unique());
        $this->assertSame(2.0, (float) $needs->firstWhere('titulo', 'Orientación')['horas_plan_requeridas']);
        $this->assertSame(1.0, (float) $needs->firstWhere('titulo', 'Orientación')['horas_libre_disposicion_consolidada']);
        $this->assertFalse($needs->contains('titulo', 'Religión'));
        $this->assertSame(2.0, (float) $needs->firstWhere('titulo', 'Educación Física')['horas_plan_requeridas']);
        $this->assertTrue($needs->contains('titulo', 'Electivo Formación general'));
        $this->assertTrue($needs->contains('titulo', 'Formación Integral 1'));
        $this->assertTrue($needs->contains('titulo', 'Formación Integral 2'));
        $this->assertCount(2, $needs->where('titulo', 'Matemática')); // 7 h base y 1 h LD son necesidades distintas.
        $this->assertSame(8.0, (float) $needs->where('titulo', 'Matemática')->sum('horas_plan_requeridas'));
        $this->assertSame($before, $this->snapshot());
    }

    public function test_conserva_asignaciones_equivalentes_y_separa_la_asignatura_reemplazada_sin_doble_conteo(): void
    {
        $this->configurar('1M', 2027);
        $keys = [$this->key('asignatura', 'Matemática'), $this->key('plan_comun_formacion_general', 'Matemática')];
        $this->asignar($keys[0], 'Matemática', 'asignatura', 3);
        $this->asignar($keys[1], 'Matemática', 'plan_comun_formacion_general', 2);
        $ori = $this->key('asignatura', 'Orientación');
        $ldOri = $this->key('libre_disposicion', 'Orientación');
        $this->asignar($ori, 'Orientación', 'asignatura', 1);
        $this->asignar($ldOri, 'Orientación', 'libre_disposicion', 1);
        $this->asignar($this->key('asignatura', 'Idioma Extranjero: Inglés'), 'Idioma Extranjero: Inglés', 'asignatura', 4);
        $this->asignar($this->key('asignatura', 'Religión'), 'Religión', 'asignatura', 2);
        $before = $this->snapshot();
        $needs = $this->necesidades();
        $math = $needs->first(fn ($n) => $n['titulo'] === 'Matemática' && $n['subtipo_asignacion'] !== 'libre_disposicion');
        $this->assertSame(5.0, $math['horas_plan_asignadas']);
        $this->assertSame(2.0, $math['horas_plan_pendientes']);
        $this->assertCount(2, $math['asignaciones']);
        $this->assertSame(2.0, $needs->firstWhere('titulo', 'Orientación')['horas_plan_asignadas']);
        $this->assertSame(4.0, $needs->firstWhere('titulo', 'Inglés')['horas_plan_asignadas']);
        // Religión fue reemplazada por Educación Física mediante el nombre
        // personalizado: sus horas previas no se trasladan a la materia nueva.
        $this->assertSame(0.0, $needs->firstWhere('titulo', 'Educación Física')['horas_plan_asignadas']);
        $this->assertSame(11.0, (float) $needs->sum('horas_plan_asignadas'));
        foreach ([$keys[0], $keys[1], $ori, $ldOri] as $key) {
            $this->assertNotNull(DotacionAsignacionCalculator::planNeedForKey($this->ee, 2027, $key));
        }
        $huerfanas = (new ReflectionMethod(DotacionAsignacionCalculator::class, 'asignacionesHuerfanas'))->invoke(null,
            DotacionAsignacionCalculator::assignmentsFor($this->ee, 2027), ['plan_estudio' => $needs]);
        $this->assertCount(1, $huerfanas);
        $this->assertSame('Religión', $huerfanas->first()->asignatura_nombre);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_no_oculta_sobreasignaciones_reales_y_valida_el_saldo_combinado(): void
    {
        $this->configurar('2M', 2027);
        $this->asignar($this->key('asignatura', 'Matemática'), 'Matemática', 'asignatura', 6);
        $key = $this->key('plan_comun_formacion_general', 'Matemática');
        $this->asignar($key, 'Matemática', 'plan_comun_formacion_general', 3);
        $math = $this->necesidades()->first(fn ($n) => $n['titulo'] === 'Matemática' && $n['subtipo_asignacion'] !== 'libre_disposicion');
        $this->assertSame(9.0, $math['horas_plan_asignadas']);
        $this->assertSame('excedida', $math['estado']['key']);
        $this->expectException(ValidationException::class);
        (new ReflectionMethod(DotacionAsignacionController::class, 'validatePlanHoursAvailable'))->invoke(
            new DotacionAsignacionController, $this->ee, ['anio' => 2027, 'tipo_asignacion' => 'plan_estudio',
                'necesidad_key' => $key, 'horas_plan_pedagogicas' => 1]);
    }

    public function test_sin_configuracion_del_bloque_base_conserva_oficiales_y_ld(): void
    {
        $this->configurar('1M', 2027);
        DB::table('establecimiento_planes_estudio_asignaturas')->where('plan_estudio_bloque_id', $this->bloqueBase)->delete();
        $needs = $this->necesidades();
        $this->assertSame(42.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertTrue($needs->contains('titulo', 'Religión'));
        $this->assertTrue($needs->contains('titulo', 'Artes Visuales y Música'));
        $this->assertSame(2.0, (float) $needs->firstWhere('titulo', 'Orientación')['horas_plan_requeridas']);
    }

    public function test_no_retira_oficiales_de_otros_bloques_y_completa_configuracion_incompleta(): void
    {
        $this->configurar('1M', 2027);
        $this->plan->asignaturas()->create(['asignatura' => 'Taller diferenciado', 'tipo_bloque' => 'plan_diferenciado_hc', 'horas_semanales' => 18]);
        $this->plan->update(['horas_semanales_total' => 60]);
        $needs = $this->necesidades();
        $this->assertSame(60.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertSame(18.0, (float) $needs->firstWhere('titulo', 'Taller diferenciado')['horas_plan_requeridas']);
        DB::table('establecimiento_planes_estudio_asignaturas')->where('plan_estudio_bloque_id', $this->bloqueBase)
            ->where('asignatura_id', '!=', Asignatura::where('nombre', 'Matemática')->value('id'))->delete();
        $needs = $this->necesidades();
        $this->assertSame(60.0, (float) $needs->sum('horas_plan_requeridas'));
        $this->assertTrue($needs->contains('subtipo_asignacion', 'plan_sin_desglose'));
    }

    private function configurar(string $code, int $year, string $tipoBase = 'plan_comun_formacion_general'): void
    {
        $this->ee = Establecimiento::create(['cod_estab' => 'SINTETICO', 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']);
        $catalogo = Curso::where('codigo', $code)->first() ?? Curso::create(['codigo' => $code, 'nombre' => $code,
            'nivel_educativo' => 'EPJA Media', 'modalidad' => 'EPJA', 'activo' => true]);
        $this->plan = PlanEstudio::create(['curso_id' => $catalogo->id, 'anio' => $year, 'nombre_plan' => 'Plan sintético',
            'regimen_jec' => 'Con JEC', 'horas_semanales_subtotal' => 36, 'horas_semanales_libre_disposicion' => 6,
            'horas_semanales_total' => 42, 'activo' => true]);
        $this->curso = EstablecimientoCurso::create(['establecimiento_id' => $this->ee->id, 'curso_id' => $catalogo->id,
            'plan_estudio_id' => $this->plan->id, 'anio' => $year, 'letra' => 'A', 'nombre_seccion' => $code.' A',
            'matricula' => 20, 'regimen_jec' => 'Con JEC', 'activo' => true]);
        $this->bloqueBase = $this->plan->bloques()->create(['nombre' => 'Tiempo mínimo obligatorio', 'tipo_bloque' => $tipoBase,
            'horas_semanales' => 36, 'activo' => true, 'permite_asignaturas_establecimiento' => true, 'permite_asignaturas_personalizadas' => true])->id;
        $this->bloqueLd = $this->plan->bloques()->create(['nombre' => 'Horas de libre disposición', 'tipo_bloque' => 'libre_disposicion',
            'horas_semanales' => 6, 'activo' => true, 'permite_asignaturas_establecimiento' => true, 'permite_asignaturas_personalizadas' => true])->id;
        $this->configId = DB::table('establecimiento_planes_estudio')->insertGetId(['establecimiento_id' => $this->ee->id,
            'establecimiento_curso_id' => $this->curso->id, 'plan_estudio_id' => $this->plan->id, 'curso_id' => $catalogo->id, 'anio' => $year]);
        foreach (['Lengua y Literatura' => 6, 'Matemática' => 7, 'Historia, Geografía y Ciencias Sociales' => 4,
            'Artes Visuales y Música' => 2, 'Educación Física y Salud' => 2, 'Orientación' => 1, 'Tecnología' => 2,
            'Religión' => 2, 'Ciencias Naturales' => 6, 'Idioma Extranjero: Inglés' => 4] as $name => $hours) {
            $this->plan->asignaturas()->create(['asignatura' => $name, 'horas_semanales' => $hours, 'tipo_bloque' => 'asignatura']);
            $nombre = $name === 'Idioma Extranjero: Inglés' ? 'Inglés' : $name;
            $asig = Asignatura::firstOrCreate(['nombre' => $nombre], ['tipo_asignatura' => 'obligatoria', 'activo' => true]);
            $personalizada = match ($name) { 'Religión' => 'Educación Física', 'Artes Visuales y Música' => 'Electivo Formación general', default => null };
            $this->detalle($this->bloqueBase, $asig->id, $hours, $personalizada);
        }
        $this->plan->asignaturas()->create(['asignatura' => 'Subtotal tiempo mínimo', 'horas_semanales' => 36, 'tipo_bloque' => 'subtotal']);
        $this->plan->asignaturas()->create(['asignatura' => 'Horas de libre disposición', 'horas_semanales' => 6, 'tipo_bloque' => 'libre_disposicion']);
        $this->plan->asignaturas()->create(['asignatura' => 'Total tiempo mínimo', 'horas_semanales' => 42, 'tipo_bloque' => 'total']);
        foreach ([['Historia, Geografía y Ciencias Sociales', 2, 'Formación Integral 1'], ['Ciencias Naturales', 2, 'Formación Integral 2'],
            ['Matemática', 1, null], ['Orientación', 1, null]] as [$name, $hours, $custom]) {
            $asigId = Asignatura::where('nombre', $name)->value('id');
            $this->detalle($this->bloqueLd, $asigId, $hours, $custom, $asigId);
        }
    }

    private function detalle(int $bloque, int $asig, float $horas, ?string $nombre, ?int $planComun = null): void
    {
        DB::table('establecimiento_planes_estudio_asignaturas')->insert(['establecimiento_plan_estudio_id' => $this->configId,
            'plan_estudio_bloque_id' => $bloque, 'asignatura_id' => $asig, 'asignatura_plan_comun_id' => $planComun,
            'nombre_asignatura_personalizada' => $nombre, 'horas_semanales' => $horas, 'orden' => 1]);
    }

    private function necesidades()
    {
        return DotacionLecturaCache::ejecutar(fn () => DotacionAsignacionCalculator::necesidades($this->ee, $this->curso->anio,
            [], [], DotacionAsignacionCalculator::assignmentsFor($this->ee, $this->curso->anio))['plan_estudio']);
    }

    private function key(string $subtipo, string $nombre): string { return 'plan:'.md5($this->curso->id.'|'.$subtipo.'|'.$nombre); }

    private function asignar(string $key, string $nombre, string $subtipo, float $horas): void
    {
        DotacionDocenteAsignacion::create(['establecimiento_id' => $this->ee->id, 'anio' => $this->curso->anio,
            'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente sintético',
            'establecimiento_curso_id' => $this->curso->id, 'plan_estudio_id' => $this->plan->id,
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => $subtipo, 'necesidad_key' => $key,
            'asignatura_nombre' => $nombre, 'horas_plan_pedagogicas' => $horas, 'horas_contrato' => $horas, 'estado' => 'activa']);
    }

    private function snapshot(): string
    {
        return json_encode(collect(['planes_estudio', 'planes_estudio_asignaturas', 'establecimiento_planes_estudio',
            'establecimiento_planes_estudio_asignaturas', 'dotacion_docente_asignaciones'])->mapWithKeys(fn ($t) => [$t => DB::table($t)->orderBy('id')->get()])->all());
    }
}
