<?php

namespace Tests\Feature;

use App\Models\DotacionDocenteAsignacion;
use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Dotacion\NoRenovacionService;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionContratoVigentePorBloque;
use App\Support\DotacionConciliacionGeneral;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use App\Support\DotacionSobredotacionCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

    #[DataProvider('bloquesYAnos')]
    public function test_nomina_incluye_cupos_contrato_y_bloque_sin_duplicarlos_ni_ofrecer_situaciones(int $anio, string $bloque): void
    {
        $service = app(ContratacionHabilitacionService::class);
        $service->habilitar($this->ee, $anio, $bloque, 2, 10.5, null);
        $ids = DB::table('dotacion_contrata_habilitaciones')->pluck('id');
        // Un título heredado de otro bloque no cambia el bloque del cupo.
        DB::table('dotacion_contrata_habilitaciones')->update(['titulo' => 'Educación Diferencial']);
        $data = DotacionEstablecimientoCalculator::build($this->ee, $anio);
        $proceso = $anio >= 2027 ? DotacionProceso2027Calculator::resumen($this->ee, $anio, $data) : ['aplica' => false];
        $html = view('admin.dotacion-establecimiento.partials._docentes', [
            ...$data, 'establecimiento' => $this->ee, 'anio' => $anio, 'proceso2027' => $proceso,
            'activeRole' => 'admin', 'canViewSobredotacion' => true,
            'canManageDocenteExclusiones' => true, 'docenteExclusionesTableReady' => true,
        ])->render();
        $xpath = $this->nominaXpath($html);
        $this->assertSame(3, $xpath->query('//tr[@data-revision-row]')->length);
        $this->assertStringContainsString('1 docente · 2 cupos', $html);
        foreach ($ids as $id) {
            $row = $xpath->query('//tr[@data-revision-row="'.sha1('VACANTE-'.$id).'"]')->item(0);
            $this->assertNotNull($row);
            $this->assertSame($bloque, $row->getAttribute('data-revision-block'));
            $this->assertStringContainsString('Educación Diferencial', $row->textContent);
            $this->assertStringContainsString('10,50', $row->textContent);
            $this->assertStringContainsString('Pendiente asignación', $row->textContent);
            $detalle = $xpath->query('//tr[@data-revision-related="'.sha1('VACANTE-'.$id).'"]')->item(0);
            $this->assertSame(0, $xpath->query('.//form', $detalle)->length);
            $this->assertSame(0, $xpath->query('.//a[contains(@href,"tab=sobredotacion")]', $detalle)->length);
            $this->assertStringContainsString('Cupo por contratar', $detalle->textContent);
            $this->assertStringNotContainsString('Reemplazos personal', $detalle->textContent);
        }
        $this->assertCount(1, $data['docentes']); // La vista no modifica padrón/proyecciones.
        $this->assertSame(65.0, $data['resumen']['horas_contrato_docentes']);
        $duplicado = view('admin.dotacion-establecimiento.partials._docentes', [
            ...$data, 'docentes' => $data['asignacion']['docentes'],
            'docentesVirtualesPorCupo' => $data['asignacion']['docentes']->whereNotNull('cupo_contrata_id'),
            'anio' => $anio,
        ])->render();
        $this->assertSame(3, $this->nominaXpath($duplicado)->query('//tr[@data-revision-row]')->length);
        $this->assertTrue($service->revocar($this->ee, $anio, $ids->first()));
        $final = DotacionEstablecimientoCalculator::build($this->ee, $anio);
        $sinCupo = view('admin.dotacion-establecimiento.partials._docentes', [...$final, 'anio' => $anio])->render();
        $this->assertSame(2, $this->nominaXpath($sinCupo)->query('//tr[@data-revision-row]')->length);
        $this->assertStringNotContainsString('VACANTE-'.$ids->first().' ·', $sinCupo);
        $this->assertSame(54.5, $final['resumen']['horas_contrato_docentes']);
    }

    public function test_nomina_del_cupo_muestra_asignaciones_reservas_y_saldo_actualizados(): void
    {
        $service = app(ContratacionHabilitacionService::class);
        $service->habilitar($this->ee, 2027, 'plan_estudio', 1, 12, null);
        $id = DB::table('dotacion_contrata_habilitaciones')->value('id');
        foreach (['plan_estudio' => 2, 'reserva_no_normativa' => 2] as $tipo => $horas) {
            DotacionDocenteAsignacion::create([
                'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut' => 'VACANTE-'.$id,
                'docente_rut_normalizado' => 'VACANTE_'.$id, 'tipo_asignacion' => $tipo,
                'horas_contrato' => $horas, 'horas_plan_pedagogicas' => $tipo === 'plan_estudio' ? 2 : null,
                'proporcion_aplicada' => '65/35', 'estado' => 'activa',
            ]);
        }
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $virtual = $service->docentesVirtuales($this->ee, 2027)->sole();
        $this->assertSame(2.0, $virtual['horas_aula_65_35']);
        $this->assertSame(8.0, $virtual['diferencia']);
        $this->assertSame('faltan_horas', $virtual['estado_cuadratura']['key']);
        $html = view('admin.dotacion-establecimiento.partials._docentes', [...$data, 'anio' => 2027])->render();
        $xpath = $this->nominaXpath($html);
        $row = $xpath->query('//tr[@data-revision-row="'.sha1('VACANTE-'.$id).'"]')->item(0);
        $this->assertSame('2', trim($xpath->query('.//*[@data-docente-value="asignadas"]', $row)->item(0)->textContent));
        $this->assertSame('2', trim($xpath->query('.//*[@data-docente-value="reservadas"]', $row)->item(0)->textContent));
        $this->assertStringContainsString('8', $row->textContent);
        $this->assertSame(56.0, $data['resumen']['horas_contrato_docentes']);
        $this->assertSame(4.0, $data['resumen']['horas_contrato_calculado']);
        $this->assertSame(52.0, $data['resumen']['diferencia_contrato_calculado']);
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

    public static function plazasAdp(): array
    {
        return [[2026, 44.0], [2027, 44.0], [2026, 22.0], [2027, 22.0]];
    }

    #[DataProvider('plazasAdp')]
    public function test_adp_cubre_y_suma_contrato_hasta_asignar_persona_sin_duplicar_cupos(int $anio, float $horas): void
    {
        $regla = DB::table('dotacion_funciones_reglas')->insertGetId([
            'codigo' => 'director_adp', 'categoria' => 'directiva', 'nombre' => 'Director(a) ADP',
            'tipo_regla' => 'director_adp', 'horas_fijas' => $horas, 'vigente' => true,
        ]);
        DB::table('dotacion_establecimiento_configuraciones')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => $anio, 'director_adp' => true,
        ]);
        foreach (ContratacionHabilitacionService::BLOQUES as $bloque) {
            app(ContratacionHabilitacionService::class)->habilitar($this->ee, $anio, $bloque, 1, 10, null);
        }
        $data = DotacionEstablecimientoCalculator::build($this->ee, $anio);
        $necesidad = $data['asignacion']['necesidades']['funciones']->firstWhere('codigo', 'director_adp');
        $this->assertSame('Asumido provisionalmente', $necesidad['estado']['label']);
        $this->assertSame(0.0, $necesidad['horas_contrato_pendientes']);
        $this->assertSame($horas, $necesidad['horas_contrato_asignadas_calculo']);
        $this->assertSame(74.0 + $horas, $data['resumen']['horas_contrato_docentes']);
        $this->assertSame(54.0 + $horas, $data['resumen']['horas_contrato_docentes_aula_general']);
        $this->assertSame($horas, $data['resumen']['horas_contrato_docentes_por_asumir']);
        $this->assertSame(30.0, $data['resumen']['horas_contrato_docentes_por_contratar']);
        $this->assertSame($horas, $data['resumen']['horas_contrato_calculado']);
        $this->assertCount(1, $data['docentes']);
        $this->assertCount(4, $data['asignacion']['docentes']); // No ofrece la plaza automática como persona.
        $this->assertCount(1, $data['asignacion']['plazas_por_asumir']);
        $this->assertSame($data['resumen'], DotacionEstablecimientoCalculator::build($this->ee, $anio, false)['resumen']);
        $this->assertSame(74.0 + $horas, DotacionEstablecimientoCalculator::resumenListado($this->ee, $anio)['resumen']['horas_contrato_docentes']);
        $referencia = (new DotacionContratoVigentePorBloque)->paraEstablecimiento($this->ee, $anio);
        $this->assertSame(($anio === 2026 ? 44.0 : 0.0) + 30.0 + $horas, array_sum(array_filter($referencia, 'is_float')));
        $this->assertSame(44.0, DotacionEstablecimientoCalculator::build($this->ee, $anio === 2026 ? 2027 : 2026)['resumen']['horas_contrato_docentes']);
        $otra = Establecimiento::create(['rbd' => 99998, 'cod_estab' => 'OTHER', 'nombre_establecimiento' => 'Otra escuela sintética']);
        $this->assertEmpty(DotacionAsignacionCalculator::plazasPorAsumir($otra, $anio));

        $proceso = DotacionProceso2027Calculator::resumen($this->ee, 2027, $data);
        $this->assertSame($horas, $proceso['bloques']['bloque_1']['asignadas']);
        $this->assertSame($horas, $proceso['bloques']['bloque_1']['asignadas_obligatorias']);
        $this->assertSame(0.0, $proceso['bloques']['bloque_1']['pendientes']);
        $this->assertSame(0.0, $proceso['bloques']['bloque_1']['sin_padron_asignadas']);
        $sobredotacion = DotacionSobredotacionCalculator::build($data['docentes'], $data['resumen']);
        $this->assertFalse($sobredotacion['aula']['resumen']['tiene_ajuste_no_asociado']);
        $conciliacion = DotacionConciliacionGeneral::build(
            $data['asignacion']['docentes']->concat($data['asignacion']['plazas_por_asumir']),
            $data['resumen'], $proceso, $sobredotacion
        );
        $this->assertSame($horas, $conciliacion['asignadas']);
        $this->assertSame(54.0, $conciliacion['saldo_neto']);
        $html = view('admin.dotacion-establecimiento.partials._asignacion', [
            ...$data, 'establecimiento' => $this->ee, 'anio' => $anio,
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ])->render();
        $this->assertStringContainsString('Asumido provisionalmente', $html);
        $this->assertStringContainsString('cuentan como asignadas y contratadas', $html);
        $this->assertStringContainsString('Asignar docente directivo', $html);

        $asignacion = DotacionDocenteAsignacion::create([
            'establecimiento_id' => $this->ee->id, 'anio' => $anio, 'docente_rut' => '99000001-K',
            'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente sintético',
            'tipo_asignacion' => 'funcion_directiva', 'subtipo_asignacion' => 'directiva',
            'dotacion_funcion_regla_id' => $regla, 'horas_contrato' => $horas, 'estado' => 'activa',
        ]);
        $real = DotacionEstablecimientoCalculator::build($this->ee, $anio);
        $this->assertEmpty($real['asignacion']['plazas_por_asumir']);
        $this->assertSame(74.0, $real['resumen']['horas_contrato_docentes']);
        $this->assertSame(30.0, $real['resumen']['horas_contrato_docentes_por_contratar']);
        $this->assertSame($horas, $real['resumen']['horas_contrato_calculado']);
        $this->assertSame(74.0, DotacionEstablecimientoCalculator::resumenListado($this->ee, $anio)['resumen']['horas_contrato_docentes']);
        $this->assertFalse($real['asignacion']['necesidades']['funciones']->firstWhere('codigo', 'director_adp')['asignacion_automatica']);
        $asignacion->update(['estado' => 'inactiva']);
        $this->assertSame(74.0 + $horas, DotacionEstablecimientoCalculator::build($this->ee, $anio)['resumen']['horas_contrato_docentes']);
    }

    public static function jornadasAdp(): array
    {
        return [[44.0], [22.0]];
    }

    #[DataProvider('jornadasAdp')]
    public function test_sustituye_adp_por_http_con_tope_justo_sin_exigir_un_segundo_cupo(float $horas): void
    {
        $this->withoutExceptionHandling([ValidationException::class]);
        Schema::table('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->string('estamento_cobertura')->default('docente');
            $table->integer('dotacion_curso_combinado_id')->nullable();
            $table->integer('dotacion_curso_combinado_asignatura_id')->nullable();
            $table->text('excepcion_prelacion')->nullable();
        });
        Schema::create('dotacion_proceso_2027_configuraciones', function (Blueprint $table): void {
            $table->id();
            $table->integer('establecimiento_id');
            $table->integer('anio');
            $table->string('decision_combinacion');
            foreach ([1, 2, 3] as $bloque) {
                $table->decimal('max_horas_bloque_'.$bloque, 8, 2)->nullable();
            }
            $table->json('funciones_normativas')->nullable();
        });
        DB::table('dotacion_funciones_reglas')->update(['vigente' => false]);
        $regla = DB::table('dotacion_funciones_reglas')->insertGetId([
            'codigo' => 'director_adp', 'categoria' => 'directiva', 'nombre' => 'Director(a) ADP',
            'tipo_regla' => 'director_adp', 'horas_fijas' => 44, 'vigente' => true,
        ]);
        DB::table('dotacion_establecimiento_configuraciones')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'director_adp' => true,
        ]);
        $curso = DB::table('cursos')->where('codigo', '1B')->value('id');
        $plan = DB::table('planes_estudio')->insertGetId([
            'curso_id' => $curso, 'anio' => 2027, 'nombre_plan' => 'Plan sintético mínimo',
            'regimen_jec' => 'sin_jec', 'horas_semanales_total' => 1,
        ]);
        DB::table('establecimiento_cursos')->insert([
            'establecimiento_id' => $this->ee->id, 'curso_id' => $curso, 'plan_estudio_id' => $plan,
            'anio' => 2027, 'nombre_seccion' => '1° Básico A', 'matricula' => 1, 'regimen_jec' => 'sin_jec',
        ]);
        DB::table('dotacion_proceso_2027_configuraciones')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'decision_combinacion' => 'sin_combinacion',
            'funciones_normativas' => json_encode(['_horas' => ['director_adp' => $horas]]),
        ]);
        $data = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $proceso = DotacionProceso2027Calculator::resumen($this->ee, 2027, $data);
        (require database_path('migrations/2026_09_29_130000_create_dotacion_docente_subsectores_table.php'))->up();
        foreach ($proceso['docentes_subsector']['asignaturas'] as $asignatura) {
            DB::table('dotacion_docente_subsectores')->insert([
                'establecimiento_id' => $this->ee->id, 'anio' => 2027,
                'asignatura_key' => $asignatura['key'], 'asignatura_nombre' => $asignatura['nombre'],
                'nivel' => $asignatura['nivel'], 'docente_rut_normalizado' => '99000001K',
            ]);
        }
        DB::table('dotacion_proceso_2027_configuraciones')->update([
            'max_horas_bloque_1' => $proceso['bloques']['bloque_1']['requeridas'],
            'max_horas_bloque_2' => 0, 'max_horas_bloque_3' => 0,
        ]);
        $preparado = DotacionProceso2027Calculator::resumen($this->ee, 2027);
        $this->assertTrue($preparado['asignacion_habilitada'], json_encode($preparado['pasos']));
        $htmlProceso = view('admin.dotacion-establecimiento.partials._proceso_2027', [
            ...$data, 'establecimiento' => $this->ee, 'anio' => 2027, 'proceso2027' => $preparado,
        ])->render();
        $this->assertStringContainsString('Incluye '.(int) $horas.' h de plaza por asumir, cubierta provisionalmente', $htmlProceso);
        $user = $this->testUser(1, 3);
        $this->actingAs($user)->withSession(['active_role' => 'admin']);
        $url = route('admin.dotacion-establecimiento.asignaciones.store', $this->ee);
        $payload = [
            'anio' => 2027, 'docente_rut' => '99000001-K', 'estamento_cobertura' => 'docente',
            'tipo_asignacion' => 'funcion_directiva', 'subtipo_asignacion' => 'directiva',
            'dotacion_funcion_regla_id' => $regla, 'horas_contrato' => $horas,
            'necesidad_key' => $data['asignacion']['necesidades']['funciones']->firstWhere('codigo', 'director_adp')['key'],
        ];
        $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(1, DB::table('dotacion_docente_asignaciones')->count());
        $final = DotacionEstablecimientoCalculator::build($this->ee, 2027);
        $this->assertSame(44.0, $final['resumen']['horas_contrato_docentes']);
        $this->assertSame($horas, $final['resumen']['horas_contrato_calculado']);
        $this->assertEmpty($final['asignacion']['plazas_por_asumir']);
        $this->post($url, $payload)->assertRedirect()->assertSessionHasErrors('horas_contrato');
        $this->assertSame(1, DB::table('dotacion_docente_asignaciones')->count());
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
        $this->withoutVite();
        Storage::disk('local')->put('nomina-views/layouts/app.blade.php', '<html><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('nomina-views'));
        $this->get(route('admin.dotacion-establecimiento.show', [$this->ee, 'anio' => 2027, 'tab' => 'docentes']))
            ->assertOk()->assertSee('Nómina de docentes y cupos por contratar')->assertSee('1 docente · 2 cupos')
            ->assertSee('Docente por contratar #1')->assertSee('Docente por contratar #2')->assertSee('132');
    }

    private function nominaXpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);
        return new \DOMXPath($dom);
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
