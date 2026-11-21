<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\DotacionDocenteAsignacion;
use App\Models\DotacionDocenteDistribucion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\EstablecimientoCursoPie;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionInformeFunciones;
use App\Support\PieHorasCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionInformeFuncionesTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp(); // SQLite :memory:, almacenamiento simulado y sin configuración cacheada.
        $this->resetCalculators();
        $this->withoutVite();
        $disk = Storage::disk('local');
        $disk->put('informe-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head>@stack(\'styles\')</head><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation($disk->path('informe-views'));
        foreach ([
            '2026_01_23_000001_create_establecimientos_table.php',
            '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_02_03_144224_create_modules_table.php',
            '2026_05_18_130000_create_cursos_table.php',
            '2026_05_18_140000_create_planes_estudio_tables.php',
            '2026_05_18_160000_create_planes_estudio_bloques_table.php',
            '2026_05_18_190000_create_establecimiento_cursos_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_22_150000_add_estamento_cobertura_to_dotacion_docente_asignaciones_table.php',
            '2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php',
            '2026_09_29_190000_create_dotacion_sobredotacion_justificaciones_table.php',
            '2026_11_06_170000_create_dotacion_docente_distribuciones_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        $this->ee = $this->establecimiento(99999);
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
    }

    protected function tearDown(): void
    {
        $this->resetCalculators();
        parent::tearDown();
    }

    public static function roles(): array
    {
        return [['admin', true], ['coordinador_uatp', true], ['coordinador_gdp', true], ['supervisor_plani', true],
            ['funcionario_directivo_estab', false], ['funcionario_ac', false], ['postulante', false], ['coordinador_plani', false]];
    }

    #[DataProvider('roles')]
    public function test_acceso_http_y_boton_se_restringen_al_rol_activo(string $rol, bool $permitido): void
    {
        $this->usarRol($rol); // La cuenta también tiene admin: no debe eludir el rol activo.
        $respuesta = $this->get($this->url(2027));
        if ($permitido) {
            $respuesta->assertOk()->assertViewIs('admin.dotacion-establecimiento.informe-funciones');
        } else {
            $respuesta->assertForbidden();
        }
        $html = view('admin.dotacion-establecimiento.index', [
            'establecimientos' => new LengthAwarePaginator([], 0, 20), 'anio' => 2027,
            'activeRole' => $rol, 'q' => '', 'comuna' => '', 'comunas' => collect(),
        ])->render();
        $this->assertSame($permitido, str_contains($html, 'Informe de dotación por funciones'));
    }

    public static function years(): array
    {
        return [[2026], [2027]];
    }

    #[DataProvider('years')]
    public function test_separa_funciones_conserva_contrato_completo_y_no_duplica_personas_ni_reservas(int $year): void
    {
        $this->personal('99000001-K', 44);
        $this->personal('99000001-K', 6, ['financiamiento' => 'SEP', 'tipocontrato' => 'CONTRATA']);
        foreach ([
            ['funcion_directiva', 'Director(a)', 10], ['funcion_directiva', 'Inspector(a) General', 4],
            ['funcion_tecnico_pedagogica', 'Jefe(a) UTP', 3], ['funcion_tecnico_pedagogica', 'Coordinador(a) de Convivencia Educativa', 2],
            ['plan_estudio', 'Lenguaje', 12], ['plan_normativo', 'CRA', 2], ['pie_colaborativo', 'Trabajo colaborativo PIE', 3],
            ['funcion_tecnico_pedagogica', 'Coordinador PIE', 2], ['pie_educadora_diferencial', 'Bolsa diferencial', 3],
            ['otra_funcion', 'Apoyo adicional', 2], ['reserva_no_normativa', 'Reservadas', 3],
        ] as [$type, $name, $hours]) {
            $this->asignacion($year, '99000001-K', $type, $name, $hours);
        }
        $this->asignacion($year === 2026 ? 2027 : 2026, '99000001-K', 'otra_funcion', 'Otro año', 100);
        $before = DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson();
        $fila = app(DotacionInformeFunciones::class)->fila($this->ee, $year);
        $this->assertSame(1, $fila['docentes_total']);
        $this->assertSame(50.0, $fila['contrato_total']);
        $this->assertSame(43.0, $fila['asignadas']);
        $this->assertSame(3.0, $fila['reservadas']);
        $this->assertSame(4.0, $fila['sin_asignar']);
        $this->assertSame(6.0, $fila['sep']);
        $this->assertSame(['docentes' => 1, 'horas' => 10.0], $fila['grupos']['directiva']);
        $this->assertSame(['docentes' => 1, 'horas' => 9.0], $fila['grupos']['tecnico_pedagogica']);
        $this->assertSame(['docentes' => 1, 'horas' => 17.0], $fila['grupos']['aula']);
        $this->assertSame(['docentes' => 1, 'horas' => 5.0], $fila['grupos']['pie']);
        $this->assertSame(['08/2026'], $fila['periodos']);
        $this->assertSame($before, DB::table('dotacion_docente_asignaciones')->orderBy('id')->get()->toJson());
        $this->get($this->url($year))->assertOk()->assertSee('Horas SEP '.$year)->assertSee('Ver conciliación')->assertViewHas('errores', 0);
    }

    public function test_un_exceso_no_compensa_saldo_de_otro_docente_y_excluye_asistentes_y_cupos_inexistentes(): void
    {
        $this->personal('99000001-K', 10);
        $this->personal('99000002-K', 20);
        $this->personal('99000003-K', 44, ['estatuto' => 'ASISTENTE DE LA EDUCACION', 'escalafon' => 'ASISTENTE']);
        $this->asignacion(2027, '99000001-K', 'plan_estudio', 'Aula', 15);
        $this->asignacion(2027, '99000003-K', 'plan_estudio', 'Aula AAEE', 8, ['estamento_cobertura' => 'asistente']);
        $this->asignacion(2027, 'VACANTE-1', 'plan_estudio', 'Cupo virtual', 44);
        $this->asignacion(2027, '99000009-K', 'plan_estudio', 'Persona no vigente', 44);
        $fila = app(DotacionInformeFunciones::class)->fila($this->ee, 2027);
        $this->assertSame(2, $fila['docentes_total']);
        $this->assertSame(30.0, $fila['contrato_total']);
        $this->assertSame(15.0, $fila['asignadas']);
        $this->assertSame(20.0, $fila['sin_asignar']);
        $this->assertSame(5.0, $fila['sobreasignadas']);
        $this->assertEquals($fila['contrato_total'], $fila['asignadas'] + $fila['reservadas'] + $fila['sin_asignar'] - $fila['sobreasignadas']);
    }

    public function test_excluye_justificados_del_anio_e_incluye_cupos_incluso_sin_asignaciones(): void
    {
        $this->personal('99000001-K', 20, ['financiamiento' => 'SEP']);
        $this->personal('99000002-K', 10);
        $this->asignacion(2027, '99000001-K', 'plan_estudio', 'Aula', 20);
        $this->asignacion(2027, '99000002-K', 'funcion_directiva', 'Dirección', 10);
        $this->justificar('99000001K', 2027);
        $this->justificar('99000002K', 2026);
        $this->cupo($this->ee, 'pie', 6);
        $rutCupo = $this->cupo($this->ee, 'plan_estudio', 8);
        $this->asignacion(2027, $rutCupo, 'plan_estudio', 'Aula del cupo', 5);

        $fila = app(DotacionInformeFunciones::class)->fila($this->ee, 2027);

        $this->assertSame(3, $fila['docentes_total']);
        $this->assertSame(24.0, $fila['contrato_total']);
        $this->assertSame(0.0, $fila['sep']);
        $this->assertSame(9.0, $fila['sin_asignar']);
        $this->assertSame(['docentes' => 1, 'horas' => 5.0], $fila['grupos']['aula']);
        $this->assertSame(['docentes' => 1, 'horas' => 10.0], $fila['grupos']['directiva']);
        $this->get($this->url(2027))->assertOk()->assertViewHas('establecimientos',
            fn ($establecimientos) => $establecimientos->first()['contrato_total'] === 24.0);
    }

    public function test_destinaciones_vigentes_reemplazan_el_cupo_y_conservan_solo_su_saldo(): void
    {
        $destino = $this->establecimiento(99998);
        $this->personal('99000001-K', 26);
        $this->personal('99000002-K', 8);
        $this->personal('99000001-K', 4, ['establecimiento_id' => $destino->id, 'rbd' => $destino->rbd]);
        $pie = $this->cupo($destino, 'pie', 10);
        $general = $this->cupo($destino, 'plan_estudio', 12);
        $this->asignacion(2027, $pie, 'pie_educadora_diferencial', 'Bolsa PIE', 10, ['establecimiento_id' => $destino->id]);
        $this->asignacion(2027, $general, 'plan_estudio', 'Aula', 12, ['establecimiento_id' => $destino->id]);
        $completa = $this->destinar($destino, '99000001K', 'pie', 10, $pie);
        $this->destinar($destino, '99000001K', 'plan_estudio', 5, $general);
        $this->destinar($destino, '99000002K', 'plan_estudio', 3);
        $this->destinar($destino, '99000002K', 'plan_estudio', 7, $general)->update(['anulada_at' => now()]);
        $this->destinar($destino, '99000002K', 'plan_estudio', 50)->update(['anio' => 2026]);
        $this->justificar('99000002K', 2027);

        $informe = app(DotacionInformeFunciones::class);
        $origen = $informe->fila($this->ee, 2027);
        $fila = $informe->fila($destino, 2027);

        $this->assertSame(1, $origen['docentes_total']);
        $this->assertSame(11.0, $origen['contrato_total']);
        $this->assertSame(3, $fila['docentes_total']);
        $this->assertSame(29.0, $fila['contrato_total']);
        $this->assertSame(4.0, $fila['sin_asignar']);
        $this->assertSame(['docentes' => 1, 'horas' => 10.0], $fila['grupos']['pie']);
        $this->assertSame(['docentes' => 3, 'horas' => 15.0], $fila['grupos']['aula']);
        $this->assertSame(22.0, (float) DotacionDocenteAsignacion::sum('horas_contrato'));

        $completa->update(['anulada_at' => now()]);
        $recalculada = $informe->fila($destino, 2027);
        $this->assertSame(4, $recalculada['docentes_total']);
        $this->assertSame(29.0, $recalculada['contrato_total']);
        $this->assertSame(21.0, $informe->fila($this->ee, 2027)['contrato_total']);
    }

    public function test_respeta_padron_del_anio_y_ultimo_mes_incluso_con_sep_mixto(): void
    {
        $this->personal('99000001-K', 40, ['mes' => 7, 'financiamiento' => 'SEP']);
        $this->personal('99000001-K', 30, ['mes' => 8]);
        $this->personal('99000001-K', 5, ['mes' => 8, 'financiamiento' => 'SEP']);
        $this->personal('99000001-K', 4, ['mes' => 8, 'financiamiento' => 'SEP / PIE']);
        $this->personal('99000001-K', 22, ['anio' => 2027, 'mes' => 3, 'financiamiento' => 'SEP']);
        $service = app(DotacionInformeFunciones::class);
        $vigente = $service->fila($this->ee, 2026);
        $proximo = $service->fila($this->ee, 2027);
        $this->assertSame(39.0, $vigente['contrato_total']);
        $this->assertSame(5.0, $vigente['sep']);
        $this->assertSame(4.0, $vigente['sep_sin_desglose']);
        $this->assertSame(22.0, $proximo['contrato_total']);
        $this->assertSame(22.0, $proximo['sep']);
        $this->assertSame(['03/2027'], $proximo['periodos']);
    }

    public function test_excel_financiamiento_imputa_bloques_docentes_y_suma_aaee_del_ultimo_padron(): void
    {
        $this->ee->update(['nombre_establecimiento' => '=Escuela de prueba', 'basica' => true, 'especial' => true]);
        foreach ([['3M-HC', 2027, true, 12], ['3M-TP', 2027, false, 15], ['4M-TP', 2026, true, 13]] as [$codigo, $anio, $activo, $matricula]) {
            EstablecimientoCurso::create([
                'establecimiento_id' => $this->ee->id, 'curso_id' => Curso::where('codigo', $codigo)->value('id'),
                'anio' => $anio, 'letra' => 'A', 'nombre_seccion' => $codigo.' A', 'regimen_jec' => 'Con JEC',
                'activo' => $activo, 'matricula' => $matricula,
            ]);
        }
        $this->personal('99000001-K', 60, ['mes' => 11, 'financiamiento' => 'SEP']);
        foreach ([
            ['plan_estudio', 'Lenguaje', 22, 'Otra'],
            ['plan_normativo', 'CRA', 4, 'Sin clasificar'],
            ['otra_funcion', 'Apoyo', 5, 'General'],
            ['pie_colaborativo', 'Colaboración PIE', 3, 'Otra'],
            ['pie_educadora_diferencial', 'Bolsa diferencial', 8, 'General'],
            ['funcion_tecnico_pedagogica', 'Coordinadora PIE', 2, 'General'],
            ['otra_funcion', 'Apoyo SEP', 7, 'Subvención Escolar Preferencial'],
            ['otra_funcion', 'Apoyo Pro-Retención', 2, 'PRO-RETENCION'],
            ['reserva_no_normativa', 'Reserva', 10, 'SEP'],
        ] as [$tipo, $funcion, $horas, $fuente]) {
            $this->asignacion(2027, '99000001-K', $tipo, $funcion, $horas, ['subvencion' => $fuente]);
        }
        $this->asignacion(2027, '99000001-K', 'otra_funcion', 'Inactiva', 100, ['estado' => 'inactiva', 'subvencion' => 'SEP']);
        $this->asignacion(2026, '99000001-K', 'otra_funcion', 'Otro año', 100, ['subvencion' => 'SEP']);
        $this->asignacion(2027, '99000005-K', 'otra_funcion', 'AAEE', 100, ['subvencion' => 'SEP', 'estamento_cobertura' => 'asistente']);

        $aaee = ['mes' => 11, 'estatuto' => 'AAEE', 'escalafon' => 'ASISTENTE'];
        foreach (['SUB GRAL' => 20, 'SEP' => 8, 'PIE' => 9, 'Pro Retención' => 3, 'Municipal' => 4,
            'PIE / SEP' => 5, 'JUNJI' => 18, 'Sin financiamiento' => 60, '' => 100] as $fuente => $horas) {
            $this->personal('99000005-K', $horas, ['financiamiento' => $fuente] + $aaee);
        }
        $this->personal('99000006-K', 30, ['mes' => 10, 'financiamiento' => 'SEP'] + $aaee);
        $this->personal('99000007-K', 30, ['estatuto' => 'JUNJI', 'financiamiento' => 'SEP'] + $aaee);
        $jardin = $this->establecimiento(99998, ['nombre_establecimiento' => 'Jardín Infantil de prueba']);
        $sala = $this->establecimiento(99997, ['sala_cuna' => true]);
        foreach ([$jardin, $sala] as $ee) {
            $this->personal('99000008-K', 80, ['establecimiento_id' => $ee->id, 'rbd' => $ee->rbd, 'financiamiento' => 'SEP'] + $aaee);
        }

        $this->get($this->url(2027))->assertOk()->assertSee('Excel por financiamiento');
        $respuesta = $this->get($this->url(2027, ['export_financiamiento' => 1, 'page' => 2]));
        $respuesta->assertOk()->assertDownload();
        $disk = Storage::disk('local');
        $disk->put('financiamiento.xlsx', $respuesta->streamedContent());
        $libro = IOFactory::load($disk->path('financiamiento.xlsx'));
        try {
            $this->assertSame(['Sub. General', 'PIE', 'SEP', 'Pro-Retención', 'Otras fuentes AAEE', 'Proyección PIE', 'Oferta educativa', 'Niveles y modalidades'], $libro->getSheetNames());
            $niveles = $libro->getSheetByName('Niveles y modalidades');
            $this->assertSame(['99999', '=Escuela de prueba', 'No', 'Sí', 'Sí', 'Sí', 'No', 'No', 'Sí'], $niveles->rangeToArray('A5:I5')[0]);
            $this->assertSame(DataType::TYPE_STRING, $niveles->getCell('B5')->getDataType());
            foreach (['Sub. General' => 51, 'PIE' => 22, 'SEP' => 15, 'Pro-Retención' => 5] as $nombre => $total) {
                $hoja = $libro->getSheetByName($nombre);
                $this->assertSame('99999', $hoja->getCell('B5')->getValue());
                $this->assertSame('=Escuela de prueba', $hoja->getCell('C5')->getValue());
                $this->assertSame(DataType::TYPE_STRING, $hoja->getCell('C5')->getDataType());
                $this->assertSame((float) $total, (float) $hoja->getCell('D5')->getValue());
                $this->assertSame('99998', $hoja->getCell('B6')->getValue());
                $this->assertSame(0.0, (float) $hoja->getCell('D6')->getValue());
                $this->assertSame((float) $total, (float) $hoja->getCell('D7')->getCalculatedValue());
                $this->assertStringContainsString('11/2026', $hoja->getCell('A2')->getValue());
            }
            $otras = $libro->getSheetByName('Otras fuentes AAEE');
            $this->assertSame('Municipal', $otras->getCell('D5')->getValue());
            $this->assertSame('PIE / SEP', $otras->getCell('D6')->getValue());
            $this->assertSame(9.0, (float) $otras->getCell('E7')->getCalculatedValue());
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    public function test_excel_incluye_proyeccion_pie_con_conversion_por_curso_y_paec_redondeado_por_rbd(): void
    {
        foreach ([
            '2026_05_18_110000_create_alumnos_prioritarios_porcentajes_table.php',
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_171000_add_calculo_horas_to_establecimiento_curso_pie_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        DB::table('alumnos_prioritarios_porcentajes')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'porcentaje' => 80,
        ]);
        $this->establecimiento(99998);
        foreach ([
            ['1B', 'A', 'Con JEC', 2, 0, 2027, true],
            ['5B', 'A', 'Sin JEC', 3, 0, 2027, true],
            ['5B', 'B', 'Sin JEC', 3, 0, 2027, true],
            ['5B', 'C', 'Con JEC', 0, 1, 2027, true],
            ['5B', 'D', 'Con JEC', 0, 0, 2027, true],
            ['5B', 'E', 'Con JEC', 5, 0, 2027, false],
            ['5B', 'A', 'Con JEC', 5, 0, 2026, true],
        ] as [$codigo, $letra, $jec, $neet, $neep, $anio, $activo]) {
            $curso = EstablecimientoCurso::create([
                'establecimiento_id' => $this->ee->id, 'curso_id' => Curso::where('codigo', $codigo)->value('id'),
                'anio' => $anio, 'letra' => $letra, 'nombre_seccion' => $codigo.' '.$letra, 'regimen_jec' => $jec, 'activo' => $activo,
            ]);
            EstablecimientoCursoPie::create([
                'establecimiento_id' => $this->ee->id, 'establecimiento_curso_id' => $curso->id,
                'curso_id' => $curso->curso_id, 'anio' => $anio,
                'necesidades_transitorias' => $neet, 'necesidades_permanentes' => $neep, 'total_pie' => $neet + $neep,
            ] + PieHorasCalculator::calculate($curso, $neet, $neep));
        }

        $respuesta = $this->get($this->url(2027, ['export_financiamiento' => 1]))->assertOk()->assertDownload();
        $disk = Storage::disk('local');
        $disk->put('proyeccion-pie.xlsx', $respuesta->streamedContent());
        $libro = IOFactory::load($disk->path('proyeccion-pie.xlsx'));
        try {
            $hoja = $libro->getSheetByName('Proyección PIE');
            $this->assertSame('99999', $hoja->getCell('B5')->getValue());
            $this->assertSame([4, 8, 1, 29, 9], array_map(fn ($col) => (int) $hoja->getCell($col.'5')->getValue(), range('D', 'H')));
            $this->assertSame('Total', $hoja->getCell('A6')->getValue());
            $this->assertSame(9, (int) $hoja->getCell('H6')->getCalculatedValue());
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    public function test_conversion_de_aula_es_consolidada_y_no_reescribe_las_asignaciones(): void
    {
        $this->personal('99000001-K', 44);
        foreach (['A', 'B'] as $name) {
            $this->asignacion(2027, '99000001-K', 'plan_estudio', $name, 17, ['horas_plan_pedagogicas' => 15, 'proporcion_aplicada' => '65/35']);
        }
        $fila = app(DotacionInformeFunciones::class)->fila($this->ee, 2027);
        $this->assertSame(35.0, $fila['grupos']['aula']['horas']);
        $this->assertSame(9.0, $fila['sin_asignar']);
        $this->assertEquals(34, DotacionDocenteAsignacion::sum('horas_contrato'));
    }

    public function test_respeta_situaciones_continuidad_y_retiros_del_anio_sin_alterar_el_anterior(): void
    {
        (require database_path('migrations/2026_08_24_090000_create_dotacion_docente_exclusiones_table.php'))->up();
        (require database_path('migrations/2026_09_14_160000_add_continuidad_to_dotacion_docente_exclusiones.php'))->up();
        Schema::create('dotacion_no_renovaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); $t->string('docente_rut_normalizado'); $t->timestamp('anulada_at')->nullable();
        });
        $this->personal('99000001-K', 44, ['financiamiento' => 'SEP']);
        $this->personal('99000002-K', 20);
        $this->personal('99000003-K', 30);
        DB::table('dotacion_docente_exclusiones')->insert([
            ['establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut' => '99000001-K', 'docente_rut_normalizado' => '99000001K',
                'docente_nombre' => 'Persona sintética', 'motivo' => 'horas_gremiales', 'horas' => 10, 'considerar_dotacion_siguiente' => true],
            ['establecimiento_id' => $this->ee->id, 'anio' => 2026, 'docente_rut' => '99000002-K', 'docente_rut_normalizado' => '99000002K',
                'docente_nombre' => 'Persona sintética', 'motivo' => 'proceso_bir', 'horas' => 0, 'considerar_dotacion_siguiente' => false],
        ]);
        DB::table('dotacion_no_renovaciones')->insert(['establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut_normalizado' => '99000003K']);
        $this->asignacion(2027, '99000001-K', 'plan_estudio', 'Aula', 20);
        $this->asignacion(2027, '99000001-K', 'reserva_no_normativa', 'Reservadas', 5);
        $this->asignacion(2027, '99000001-K', 'plan_estudio', 'Inactiva', 99, ['estado' => 'inactiva']);
        $this->asignacion(2027, '99000002-K', 'plan_estudio', 'No continúa', 99);
        $service = app(DotacionInformeFunciones::class);
        $actual = $service->fila($this->ee, 2026);
        $proyeccion = $service->fila($this->ee, 2027);
        $this->assertSame(3, $actual['docentes_total']);
        $this->assertSame(94.0, $actual['contrato_total']);
        $this->assertSame(2, $proyeccion['docentes_total']);
        $this->assertSame(74.0, $proyeccion['contrato_original']);
        $this->assertSame(10.0, $proyeccion['horas_excluidas']);
        $this->assertSame(74.0, $proyeccion['contrato_total']);
        $this->assertSame(64.0, $proyeccion['contrato_considerado']);
        $this->assertSame(20.0, $proyeccion['asignadas']);
        $this->assertSame(5.0, $proyeccion['reservadas']);
        $this->assertSame(39.0, $proyeccion['sin_asignar']);
        $this->assertEquals($proyeccion['contrato_total'], $proyeccion['asignadas'] + $proyeccion['reservadas'] + $proyeccion['sin_asignar'] + $proyeccion['horas_excluidas'] - $proyeccion['sobreasignadas']);
        $this->assertSame(44.0, $proyeccion['sep']); // Fuente contractual complementaria, no otra asignación.
    }

    public function test_nombres_de_establecimiento_se_escapan_y_cada_fila_tiene_trece_columnas(): void
    {
        $this->ee->update(['nombre_establecimiento' => '<script>prueba</script>']);
        $response = $this->get($this->url(2027))->assertOk()->assertSee('&lt;script&gt;prueba&lt;/script&gt;', false)->assertDontSee('<script>prueba</script>', false);
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(13, $xpath->query('//tr[@data-informe-rbd]/*')->length);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(1, $xpath->query('//*[@class="table-responsive" and @tabindex="0"]')->length);
        foreach ($xpath->query('//form//input[not(@type="hidden")] | //form//select') as $input) {
            $this->assertSame(1, $xpath->query('//label[@for="'.$input->getAttribute('id').'"]')->length);
        }
    }

    public function test_redondeo_parvularia_completa_jornada_sin_generar_saldo_ficticio(): void
    {
        $docente = ['rut' => '99000001-K', 'titulo' => 'Pedagogía en Educación de Párvulos',
            'horas_contrato' => 43, 'horas_planta' => 43, 'horas_asignadas_total' => 42.63,
            'asignaciones' => [['tipo_asignacion' => 'plan_estudio', 'proporcion_aplicada' => 'Parvularia', 'horas_contrato' => 42.63]]];
        $fila = app(DotacionInformeFunciones::class)->resumir(collect([$docente]));
        $this->assertSame(43.0, $fila['grupos']['aula']['horas']);
        $this->assertSame(0.37, $fila['redondeo']);
        $this->assertSame(0.0, $fila['sin_asignar']);
    }

    public function test_filtra_y_pagina_sin_calcular_establecimientos_fuera_de_pagina(): void
    {
        for ($i = 1; $i <= 12; $i++) { $this->establecimiento(99999 - $i); }
        $this->establecimiento(99980, ['sala_cuna' => true]);
        $called = [];
        $service = $this->mock(DotacionInformeFunciones::class);
        $service->shouldReceive('fila')->times(10)->andReturnUsing(function ($ee, $year) use (&$called) {
            $called[] = $ee->rbd;
            return (new DotacionInformeFunciones)->resumir(collect()) + ['error' => false, 'establecimiento_id' => $ee->id,
                'rbd' => (string) $ee->rbd, 'nombre' => $ee->nombre_establecimiento, 'comuna' => $ee->comuna, 'sep' => 0, 'sep_sin_desglose' => 0];
        });
        $this->get($this->url(2027))->assertOk()->assertViewHas('establecimientos', fn ($p) => $p->total() === 13 && $p->count() === 10);
        $this->assertCount(10, $called);
        $this->assertNotContains(99980, $called);
    }

    public function test_filtros_se_conservan_y_el_estado_vacio_no_inventa_filas(): void
    {
        $this->get($this->url(2026, ['q' => '99999', 'comuna' => 'Comuna sintética']))
            ->assertOk()->assertViewHas('establecimientos', fn ($p) => $p->total() === 1)->assertSee('value="99999"', false);
        $this->get($this->url(2027, ['q' => 'No existe']))->assertOk()->assertSee('No se encontraron establecimientos.')
            ->assertViewHas('establecimientos', fn ($p) => $p->isEmpty());
    }

    public function test_fallo_del_calculo_se_identifica_y_los_totales_se_marcan_parciales(): void
    {
        $this->mock(DotacionInformeFunciones::class)->shouldReceive('fila')->once()->andThrow(new \RuntimeException('Fallo sintético'));
        $this->get($this->url(2027))->assertOk()->assertSee('Cálculo no disponible')->assertSee('Total de esta página (parcial)')
            ->assertViewHas('errores', 1)->assertViewHas('totales', fn ($t) => $t['contrato_total'] === 0.0);
    }

    public function test_rechaza_anios_y_filtros_invalidos(): void
    {
        foreach ([['anio' => 2028], ['anio' => 'incorrecto'], ['anio' => [2027]], ['anio' => 2027, 'q' => ['no']], ['anio' => 2027, 'comuna' => str_repeat('a', 101)]] as $filters) {
            $this->from('/informe')->get(route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1] + $filters))
                ->assertRedirect('/informe')->assertSessionHasErrors();
        }
    }

    private function url(int $anio, array $filters = []): string
    {
        return route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1, 'anio' => $anio] + $filters);
    }

    private function usarRol(string $rol): void
    {
        $id = DB::table('roles')->where('name', $rol)->value('id') ?? DB::table('roles')->insertGetId(['name' => $rol, 'guard_name' => 'web']);
        $user = auth()->user();
        if (! DB::table('model_has_roles')->where('model_id', $user->id)->where('role_id', $id)->exists()) {
            DB::table('model_has_roles')->insert(['role_id' => $id, 'model_type' => $user::class, 'model_id' => $user->id]);
        }
        $user->forceFill(['establecimiento_id' => $this->ee->id])->save();
        $user->unsetRelation('roles');
        $this->withSession(['active_role' => $rol]);
    }

    private function establecimiento(int $rbd, array $extra = []): Establecimiento
    {
        return Establecimiento::create($extra + ['cod_estab' => $rbd, 'rbd' => $rbd, 'nombre_establecimiento' => 'Escuela sintética '.$rbd, 'comuna' => 'Comuna sintética']);
    }

    private function personal(string $rut, int $hours, array $extra = []): void
    {
        DB::table('reemplazos_personal')->insert($extra + [
            'establecimiento_id' => $this->ee->id, 'rbd' => $this->ee->rbd, 'rut' => $rut, 'nombre' => 'Docente sintético',
            'anio' => 2026, 'mes' => 8, 'jornada' => $hours, 'jornada_basica' => $hours, 'tipocontrato' => 'PLANTA',
            'financiamiento' => 'SUB.GENERAL', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => bin2hex(random_bytes(20)),
        ]);
    }

    private function asignacion(int $year, string $rut, string $tipo, string $name, float $hours, array $extra = []): void
    {
        DotacionDocenteAsignacion::create($extra + ['establecimiento_id' => $this->ee->id, 'anio' => $year, 'docente_rut' => $rut,
            'docente_rut_normalizado' => DotacionEstablecimientoCalculator::normalizeRut($rut), 'tipo_asignacion' => $tipo,
            'asignatura_nombre' => $name, 'horas_contrato' => $hours, 'estamento_cobertura' => 'docente']);
    }

    private function justificar(string $rut, int $anio): void
    {
        DB::table('dotacion_sobredotacion_justificaciones')->insert([
            'establecimiento_id' => $this->ee->id, 'anio' => $anio, 'docente_rut_normalizado' => $rut,
            'bloque' => 'plan_estudio', 'tipo_horas' => 'titular', 'horas_detectadas' => 5,
            'justificacion' => 'Justificación sintética de prueba.',
        ]);
    }

    private function cupo(Establecimiento $ee, string $bloque, float $horas): string
    {
        $id = DB::table('dotacion_contrata_habilitaciones')->insertGetId([
            'establecimiento_id' => $ee->id, 'anio' => 2027, 'bloque' => $bloque, 'horas' => $horas,
        ]);

        return 'VACANTE_'.$id;
    }

    private function destinar(Establecimiento $destino, string $rut, string $bloque, float $horas, ?string $cupo = null): DotacionDocenteDistribucion
    {
        return DotacionDocenteDistribucion::create([
            'solicitud_uuid' => (string) Str::uuid(), 'anio' => 2027, 'origen_id' => $this->ee->id,
            'destino_id' => $destino->id, 'docente_rut_normalizado' => $rut, 'bloque' => $bloque,
            'tipo_horas' => 'titular', 'horas' => $horas,
            'antecedente' => $cupo ? ['destino_cupo' => ['bloque' => $bloque, 'tipo' => 'cupos', 'persona' => $cupo]] : [],
        ]);
    }

    private function resetCalculators(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) { (new ReflectionProperty($class, $name))->setValue(null, []); }
        }
        (new ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
    }
}
