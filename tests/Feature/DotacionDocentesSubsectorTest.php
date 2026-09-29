<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionDocentesSubsector;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DotacionDocentesSubsectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_09_29_130000_create_dotacion_docente_subsectores_table.php'))->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dotacion_docente_subsectores');
        parent::tearDown();
    }

    public function test_consolida_asignaturas_por_nivel_y_muestra_solo_los_niveles_presentes_en_orden(): void
    {
        $necesidades = collect([
            $this->necesidad('epja', 'Lenguaje', 'EPJA'),
            $this->necesidad('basica-a', 'Matemática', 'Enseñanza Básica'),
            $this->necesidad('parvularia', 'Lenguaje', 'NT1'),
            $this->necesidad('basica-b', 'Matemática', 'Enseñanza Básica'),
            $this->necesidad('especial', 'Arte', 'Educación Especial'),
            $this->necesidad('media', 'Historia', 'Enseñanza Media'),
        ]);
        $resumen = DotacionDocentesSubsector::resumen($this->establecimiento(), 2027, $necesidades, $this->docentes());

        $this->assertSame(['parvularia', 'basica', 'media', 'especial', 'epja'], $resumen['grupos']->keys()->all());
        $this->assertSame(5, $resumen['total']);
        $this->assertSame(2, count($resumen['grupos']['basica']['asignaturas'][0]['cursos']));
        $this->assertNotSame(
            DotacionDocentesSubsector::keyParaNecesidad($necesidades[0]),
            DotacionDocentesSubsector::keyParaNecesidad($necesidades[2])
        );
        $this->assertFalse($resumen['completo']);
    }

    public function test_conserva_docentes_con_horas_historicas_y_completa_al_guardar_los_restantes(): void
    {
        $matematica = $this->necesidad('basica-a', 'Matemática', 'Enseñanza Básica');
        $historia = $this->necesidad('basica-b', 'Historia', 'Enseñanza Básica');
        $matematica['asignaciones'] = [[
            'docente_rut_normalizado' => '111111111',
            'estamento_cobertura' => 'docente',
        ]];
        DB::table('dotacion_docente_subsectores')->insert([
            'establecimiento_id' => 1,
            'anio' => 2027,
            'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($historia),
            'nivel' => 'basica',
            'asignatura_nombre' => 'Historia',
            'docente_rut_normalizado' => '222222222',
        ]);

        $resumen = DotacionDocentesSubsector::resumen(
            $this->establecimiento(), 2027, collect([$matematica, $historia]), $this->docentes()
        );

        $this->assertTrue($resumen['completo']);
        $this->assertSame(2, $resumen['completas']);
        $this->assertSame(['111111111'], $resumen['grupos']['basica']['asignaturas']->firstWhere('nombre', 'Matemática')['docentes']);
        $this->assertSame(['222222222'], $resumen['grupos']['basica']['asignaturas']->firstWhere('nombre', 'Historia')['docentes']);
    }

    public function test_nueva_etapa_pasa_de_pendiente_a_completada_al_asociar_docente(): void
    {
        $necesidad = $this->necesidad('NT1 A', 'Lenguaje', 'NT1');
        $datos = [
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0]],
            'asignacion' => ['necesidades' => ['plan_estudio' => [$necesidad]], 'asignaciones' => [], 'docentes' => $this->docentes()->all()],
            'docentes' => $this->docentes()->all(),
            'cursos_combinados' => ['resumen' => ['grupos_activos' => 0]],
        ];
        $pendiente = DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027, $datos);
        $this->assertFalse($pendiente['pasos']['subsectores']['completo']);

        DB::table('dotacion_docente_subsectores')->insert([
            'establecimiento_id' => 1,
            'anio' => 2027,
            'asignatura_key' => DotacionDocentesSubsector::keyParaNecesidad($necesidad),
            'nivel' => 'parvularia',
            'asignatura_nombre' => 'Lenguaje',
            'docente_rut_normalizado' => '111111111',
        ]);
        $completo = DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027, $datos);
        $this->assertTrue($completo['pasos']['subsectores']['completo']);
    }

    public function test_cupo_virtual_de_parvularia_no_se_ofrece_en_otros_niveles(): void
    {
        $cupo = ['rut' => 'VACANTE-7', 'cupo_contrata_id' => 7, 'cupo_bloque' => 'parvularia'];

        $this->assertTrue(DotacionDocentesSubsector::docenteAdmisible($cupo, 'parvularia'));
        $this->assertFalse(DotacionDocentesSubsector::docenteAdmisible($cupo, 'basica'));
        $this->assertFalse(DotacionDocentesSubsector::docenteAdmisible(
            ['rut' => 'VACANTE-8', 'cupo_contrata_id' => 8, 'cupo_bloque' => 'pie'], 'parvularia'
        ));
    }

    private function establecimiento(): Establecimiento
    {
        $establecimiento = new Establecimiento();
        $establecimiento->id = 1;

        return $establecimiento;
    }

    private function docentes(): \Illuminate\Support\Collection
    {
        return collect([
            ['rut' => '11111111-1', 'rut_normalizado' => '111111111', 'nombre' => 'Docente titular', 'prioridad_2027' => 2],
            ['rut' => '22222222-2', 'rut_normalizado' => '222222222', 'nombre' => 'Docente contrata', 'prioridad_2027' => 4],
        ]);
    }

    private function necesidad(string $cursoLabel, string $asignatura, string $nivel): array
    {
        $curso = new Curso(['nombre' => $nivel, 'nivel_educativo' => $nivel]);
        $establecimientoCurso = new EstablecimientoCurso();
        $establecimientoCurso->setRelation('curso', $curso);

        return [
            'curso' => $establecimientoCurso,
            'curso_label' => $cursoLabel,
            'titulo' => $asignatura,
            'asignatura_nombre' => $asignatura,
            'horas_plan_requeridas' => 2,
            'asignaciones' => [],
        ];
    }
}
