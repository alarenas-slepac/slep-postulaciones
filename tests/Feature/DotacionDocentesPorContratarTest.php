<?php

namespace Tests\Feature;

use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Dotacion\NoRenovacionService;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionContratoVigentePorBloque;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionSobredotacionCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionDocentesPorContratarTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCalculators();
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
            '2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php',
            '2026_10_13_200000_add_dotacion_no_renovaciones.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('declaracion_sostenedores', function (Blueprint $table): void {
            $table->id();
            foreach (['rbd', 'rut', 'nombres', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $field) {
                $table->string($field);
            }
        });
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
        });
        $this->ee = Establecimiento::create([
            'rbd' => 99999, 'cod_estab' => 'SYNTHETIC', 'nombre_establecimiento' => 'Escuela sintética de cupos',
        ]);
        DB::table('reemplazos_personal')->insert([
            'establecimiento_id' => $this->ee->id, 'rbd' => 99999, 'rut' => '99000001-K',
            'nombre' => 'Docente sintético', 'anio' => 2026, 'mes' => 10, 'jornada' => 44,
            'tipocontrato' => 'CONTRATA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE',
            'row_hash' => 'synthetic-contract',
        ]);
        DB::table('declaracion_sostenedores')->insert([
            'rbd' => '99999', 'rut' => '99000001-K', 'nombres' => 'Docente sintético',
            'nombre_titulo' => 'Pedagogía en Educación Básica', 'nombre_funcion' => 'DOCENTE', 'estamento' => 'DOCENTE',
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public static function bloquesYAnos(): array
    {
        $cases = [];
        foreach ([2026, 2027] as $anio) {
            foreach (ContratacionHabilitacionService::BLOQUES as $bloque) {
                $cases[$anio.' '.$bloque] = [$anio, $bloque];
            }
        }

        return $cases;
    }

    #[DataProvider('bloquesYAnos')]
    public function test_cupo_suma_contrato_desde_creacion_solo_en_su_ano_y_bloque_y_retiro_lo_descuenta(int $anio, string $bloque): void
    {
        $antes = DB::table('reemplazos_personal')->get()->toJson();
        $base = DotacionEstablecimientoCalculator::build($this->ee, $anio);
        $this->assertSame(44.0, (float) $base['resumen']['horas_contrato_docentes']);
        $this->assertGreaterThan(0, $base['resumen']['sobredotacion_horas']);
        $service = app(ContratacionHabilitacionService::class);
        $service->habilitar($this->ee, $anio, $bloque, 2, 10.5, null);
        $data = DotacionEstablecimientoCalculator::build($this->ee, $anio);
        $resumen = $data['resumen'];

        $this->assertSame(65.0, (float) $resumen['horas_contrato_docentes']);
        $this->assertSame(65.0, (float) $resumen['horas_contrato_docentes_base']);
        $this->assertSame(44.0, $resumen['horas_contrato_docentes_padron']);
        $this->assertSame(21.0, $resumen['horas_contrato_docentes_por_contratar']);
        $this->assertSame(0.0, $resumen['horas_contrato_calculado']);
        $this->assertSame(65.0, $resumen['diferencia_contrato_calculado']);
        $this->assertCount(1, $data['docentes']);
        $this->assertCount(3, $data['asignacion']['docentes']);
        foreach ([
            'plan_estudio' => 'horas_contrato_docentes_aula_general',
            'parvularia' => 'horas_contrato_docentes_parvularia',
            'pie' => 'horas_contrato_docente_pie',
        ] as $key => $campo) {
            $this->assertSame(($key === 'plan_estudio' ? 44.0 : 0.0) + ($key === $bloque ? 21.0 : 0.0), $resumen[$campo]);
        }
        $this->assertSame($resumen, DotacionEstablecimientoCalculator::build($this->ee, $anio, false)['resumen']);
        $this->assertSame(65.0, (float) DotacionEstablecimientoCalculator::resumenListado($this->ee, $anio)['resumen']['horas_contrato_docentes']);
        $referencia = (new DotacionContratoVigentePorBloque)->paraEstablecimiento($this->ee, $anio);
        $this->assertSame($anio === 2026 ? 65.0 : 21.0, array_sum(array_filter($referencia, 'is_float')));
        $this->assertSame(44.0, (float) DotacionEstablecimientoCalculator::build($this->ee, $anio === 2026 ? 2027 : 2026)['resumen']['horas_contrato_docentes']);
        $otra = Establecimiento::create(['rbd' => 99998, 'cod_estab' => 'OTHER', 'nombre_establecimiento' => 'Otra escuela sintética']);
        $this->assertSame(0.0, (float) DotacionEstablecimientoCalculator::build($otra, $anio)['resumen']['horas_contrato_docentes']);

        $sobredotacion = DotacionSobredotacionCalculator::build($data['docentes'], $resumen);
        $this->assertFalse($sobredotacion['aula']['resumen']['tiene_ajuste_no_asociado']);
        $this->assertFalse($sobredotacion['pie']['resumen']['tiene_ajuste_no_asociado']);
        $this->assertCount(1, $sobredotacion['vacantes_por_bloque']['plan_estudio']['items']);
        foreach (DB::table('dotacion_contrata_habilitaciones')->pluck('id') as $id) {
            $this->assertTrue($service->revocar($this->ee, $anio, $id));
        }
        $this->assertSame(44.0, (float) DotacionEstablecimientoCalculator::build($this->ee, $anio)['resumen']['horas_contrato_docentes']);
        $this->assertSame($antes, DB::table('reemplazos_personal')->get()->toJson());
    }

    public function test_asignar_al_cupo_no_duplica_contrato_y_bloquea_su_retiro(): void
    {
        $service = app(ContratacionHabilitacionService::class);
        $service->habilitar($this->ee, 2027, 'pie', 1, 22, null);
        $id = DB::table('dotacion_contrata_habilitaciones')->sole()->id;
        DotacionDocenteAsignacion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut' => 'VACANTE-'.$id,
            'docente_rut_normalizado' => 'VACANTE_'.$id, 'docente_nombre' => 'Docente por contratar sintético',
            'tipo_asignacion' => 'pie_educadora_diferencial', 'horas_contrato' => 6, 'estado' => 'activa',
        ]);
        $resumen = DotacionEstablecimientoCalculator::build($this->ee, 2027)['resumen'];
        $this->assertSame(66.0, (float) $resumen['horas_contrato_docentes']);
        $this->assertSame(22.0, $resumen['horas_contrato_docente_pie']);
        $this->assertSame(6.0, $resumen['horas_contrato_calculado']);
        $this->assertSame(60.0, $resumen['diferencia_contrato_calculado']);
        $this->expectException(ValidationException::class);
        $service->revocar($this->ee, 2027, $id);
    }

    public function test_reemplazo_y_anulacion_conservan_el_total_sin_duplicar_el_contrato_retirado(): void
    {
        $service = app(ContratacionHabilitacionService::class);
        $service->habilitar($this->ee, 2027, 'parvularia', 1, 12, null);
        $antes = DotacionEstablecimientoCalculator::build($this->ee, 2027)['resumen']['horas_contrato_docentes'];
        $registro = app(NoRenovacionService::class)->reemplazar($this->ee, 2027, 'plan_estudio', '99000001-K', 'No renovación sintética debidamente fundada.', null);
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $this->assertSame(56.0, (float) $antes);
        $this->assertSame($antes, $data['resumen']['horas_contrato_docentes']);
        $this->assertCount(0, $data['docentes']);
        $this->assertSame(44.0, $data['resumen']['horas_contrato_docentes_aula_general']);
        $cupo = DB::table('dotacion_contrata_habilitaciones')->where('no_renovacion_id', $registro->id)->sole();
        $service->revocar($this->ee, 2027, $cupo->id);
        $this->assertSame($antes, DotacionEstablecimientoCalculator::build($this->ee, 2027)['resumen']['horas_contrato_docentes']);
        $this->assertCount(1, DotacionEstablecimientoCalculator::docentes($this->ee, 2027));
    }

    public static function roles(): array
    {
        return [['admin'], ['coordinador_uatp'], ['coordinador_gdp'], ['supervisor_plani']];
    }

    #[DataProvider('roles')]
    public function test_roles_autorizados_crean_sin_brecha_por_http_y_se_validan_las_horas(string $rol): void
    {
        $this->withoutExceptionHandling([ValidationException::class]);
        $roleId = DB::table('roles')->where('name', $rol)->value('id')
            ?? DB::table('roles')->insertGetId(['name' => $rol, 'guard_name' => 'web']);
        $this->actingAs($this->testUser(1, $roleId))->withSession(['active_role' => $rol]);
        $url = route('admin.dotacion-establecimiento.contrata-habilitaciones.store', $this->ee);
        $params = ['anio' => 2027, 'accion' => 'crear', 'bloque' => 'plan_estudio', 'cantidad' => 2, 'horas' => 44];
        $this->post($url, [...$params, 'horas' => 44.01])->assertRedirect()->assertSessionHasErrors('horas');
        $this->assertSame(0, DB::table('dotacion_contrata_habilitaciones')->count());
        $this->post($url, $params)->assertRedirect()->assertSessionHas('contrata_success');
        $this->assertSame(88.0, (float) DB::table('dotacion_contrata_habilitaciones')->sum('horas'));
        $this->assertSame(132.0, (float) DotacionEstablecimientoCalculator::build($this->ee, 2027)['resumen']['horas_contrato_docentes']);
    }

    private function resetCalculators(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) {
                (new ReflectionProperty($class, $property))->setValue(null, []);
            }
        }
    }
}
