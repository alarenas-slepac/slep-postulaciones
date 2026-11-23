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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;
use Tests\Support\PreparaExcelFinanciamiento;
use WeakReference;

class DotacionInformeFuncionesTest extends IsolatedSecurityTestCase
{
    use PreparaExcelFinanciamiento;

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
            '2026_05_25_160000_create_establecimiento_curso_pie_table.php',
            '2026_05_25_171000_add_calculo_horas_to_establecimiento_curso_pie_table.php',
            '2026_05_25_183000_create_docente_horas_proporciones_table.php',
            '2026_05_26_190000_create_dotacion_funciones_tables.php',
            '2026_05_28_210000_create_dotacion_docente_asignaciones_table.php',
            '2026_07_22_150000_add_estamento_cobertura_to_dotacion_docente_asignaciones_table.php',
            '2026_09_28_140000_create_dotacion_contrata_habilitaciones_table.php',
            '2026_09_29_190000_create_dotacion_sobredotacion_justificaciones_table.php',
            '2026_11_06_170000_create_dotacion_docente_distribuciones_table.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        $this->instalarExportacionFinanciamiento();
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
                'anio' => $anio, 'letra' => 'A', 'nombre_seccion' => $codigo.' A', 'regimen_jec' => 'No aplica',
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
        $libro = $this->abrirExcelFinanciamiento(2027, ['page' => 2]);
        try {
            $this->assertSame(['Sub. General', 'PIE', 'SEP', 'Pro-Retención', 'Otras fuentes AAEE', 'Proyección PIE', 'Oferta educativa',
                'Niveles y modalidades', 'Detalle oferta educativa', 'Dotación docente', 'Dotación docente glosa'], $libro->getSheetNames());
            $niveles = $libro->getSheetByName('Niveles y modalidades');
            $this->assertSame(['99999', '=Escuela de prueba', 'No', 'Sí', 'Sí', 'Sí', 'No', 'No', 'Sí'], $niveles->rangeToArray('A5:I5')[0]);
            $this->assertSame(DataType::TYPE_STRING, $niveles->getCell('B5')->getDataType());
            $detalle = $libro->getSheetByName('Detalle oferta educativa');
            $this->assertSame('99999', $detalle->getCell('A5')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $detalle->getCell('B5')->getDataType());
            $this->assertSame('Media HC', $detalle->getCell('D5')->getValue());
            $this->assertSame('Sin JEC', $detalle->getCell('E5')->getValue());
            $this->assertSame(12, (int) $detalle->getCell('F5')->getValue());
            $this->assertSame(1, (int) $detalle->getCell('G5')->getValue());
            $this->assertSame('Total', $detalle->getCell('A6')->getValue());
            $this->assertSame(12, (int) $detalle->getCell('F6')->getCalculatedValue());
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
        (require database_path('migrations/2026_05_18_110000_create_alumnos_prioritarios_porcentajes_table.php'))->up();
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

        $libro = $this->abrirExcelFinanciamiento(2027);
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

    public function test_dotacion_docente_prorratea_por_rut_entre_funciones_y_rbd_aunque_el_excel_este_filtrado(): void
    {
        $anio = 2026;
        $reglas = DB::table('dotacion_funciones_reglas')->pluck('id', 'codigo');
        $otro = $this->establecimiento(99998);
        foreach ([
            ['plan_estudio', 8], ['acompanamiento_parvularia', 4], ['pie_colaborativo', 3],
            ['pie_educadora_diferencial', 5], ['plan_normativo', 3], ['otra_funcion', 2],
            ['funcion_tecnico_pedagogica', 5], ['reserva_no_normativa', 30],
        ] as [$tipo, $horas]) {
            $this->asignacion($anio, '99000001-K', $tipo, $tipo, $horas);
        }
        $this->asignacion($anio, '99000001-K', 'funcion_directiva', 'Dirección', 10, ['establecimiento_id' => $otro->id]);
        $this->asignacion($anio, '99000002-K', 'funcion_directiva', 'Cargo de dirección', 18, ['dotacion_funcion_regla_id' => $reglas['director']]);
        $this->asignacion($anio, '99000003-K', 'funcion_directiva', 'Director(a) ADP', 22);
        $this->asignacion($anio, '99000004-K', 'funcion_directiva', 'Inspector(a) General', 7, ['dotacion_funcion_regla_id' => $reglas['inspector_general']]);
        $this->asignacion($anio, '99000005-K', 'otra_funcion', 'Subdirector', 5, ['subtipo_asignacion' => 'otras_funciones_docentes']);
        $this->asignacion($anio, '99000005-K', 'otra_funcion', 'Subdirectora', 4, [
            'subtipo_asignacion' => 'otras_funciones_docentes', 'dotacion_funcion_regla_id' => $reglas['inspector_general'],
        ]);
        $cupo = $this->cupo($this->ee, 'pie', 20, $anio);
        $this->cupo($this->ee, 'plan_estudio', 10, $anio);
        $this->asignacion($anio, $cupo, 'otra_funcion', 'Apoyo', 3);
        $this->asignacion($anio, str_replace('_', '-', $cupo), 'pie_educadora_diferencial', 'Bolsa diferencial', 9);
        $this->asignacion($anio, $cupo, 'plan_estudio', 'Asignación fuera del establecimiento del cupo', 100, ['establecimiento_id' => $otro->id]);
        $this->asignacion($anio, 'VACANTE_99999', 'plan_estudio', 'Cupo inexistente', 100);
        $this->asignacion(2027, '99000001-K', 'plan_estudio', 'Otro año', 50);
        $this->asignacion($anio, '99000001-K', 'plan_estudio', 'Asistente', 50, ['estamento_cobertura' => 'asistente']);
        $this->asignacion($anio, '99000006-K', 'otra_funcion', 'Apoyo de asistente', 6, ['estamento_cobertura' => 'asistente']);

        $libro = $this->abrirExcelFinanciamiento($anio, ['q' => '99999']);
        try {
            $hoja = $libro->getSheetByName('Dotación docente');
            $this->assertSame('99999', $hoja->getCell('A5')->getValue());
            $this->assertSame([2.0, 31.0, 3.8, 62.0, 0.95, 79.0, 6.75, 172.0],
                array_map(fn ($col) => (float) $hoja->getCell($col.'5')->getValue(), range('C', 'J')));
            $this->assertSame('Total', $hoja->getCell('A6')->getValue());
        } finally {
            $libro->disconnectWorksheets();
        }
        $dotacion = app(\App\Support\DotacionDocenteProporcional::class)->resumir($anio);
        $this->assertSame(['docentes' => 0.25, 'horas' => 10.0], $dotacion[$otro->id]['directiva']);
    }

    public function test_dotacion_proyecta_saldos_pendientes_y_excluye_justificados_bir_y_horas_descontadas(): void
    {
        $anio = 2026;
        (require database_path('migrations/2026_08_24_090000_create_dotacion_docente_exclusiones_table.php'))->up();
        foreach ([
            ['99000001K', 'horas_lactancia', 12],
            ['99000002K', 'horas_gremiales', 8],
            ['99000003K', 'proceso_bir', 15],
            ['99000004K', 'horas_gremiales', 15],
        ] as [$rut, $motivo, $horas]) {
            $this->personal($rut, 20);
            DB::table('dotacion_docente_exclusiones')->insert([
                'establecimiento_id' => $this->ee->id, 'anio' => $anio, 'docente_rut' => $rut,
                'docente_rut_normalizado' => $rut, 'docente_nombre' => 'Docente de prueba',
                'motivo' => $motivo, 'horas' => $horas,
            ]);
        }
        $this->personal('99000005K', 20);
        $this->justificar('99000004K', $anio);
        $this->asignacion($anio, '99000001K', 'plan_estudio', 'Aula', 6);
        $this->asignacion($anio, '99000001K', 'otra_funcion', 'Apoyo', 1);
        $this->asignacion($anio, '99000004K', 'plan_estudio', 'Asignación justificada', 4);
        $this->asignacion($anio, '99000005K', 'plan_estudio', 'Aula', 4);
        $this->asignacion($anio, '99000005K', 'reserva_no_normativa', 'Reserva', 5);
        $this->mock(\App\Support\DotacionInformePlanes::class)->shouldReceive('ofertaEducativa')->once()
            ->withArgs(fn ($establecimiento, $year) => $establecimiento->id === $this->ee->id && $year === $anio)
            ->andReturn(['contrato_mas_pie' => 16.0]);

        $fila = app(\App\Support\DotacionDocenteProporcional::class)->resumir($anio)[$this->ee->id];
        $this->assertSame(16.0, $fila['aula']['horas']);
        $this->assertSame(19.0, $fila['tecnico_pedagogica']['horas']);
        $this->assertEqualsWithDelta(3.0, array_sum(array_column($fila, 'docentes')), 0.00001);
        $this->assertSame(20.0, (float) DotacionDocenteAsignacion::sum('horas_contrato'));
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

    public function test_dotacion_2027_concilia_horas_del_pal_y_prorratea_personas_aunque_se_filtre_por_rbd(): void
    {
        (require database_path('migrations/2026_08_24_090000_create_dotacion_docente_exclusiones_table.php'))->up();
        $otro = $this->establecimiento(99998);
        $curso = EstablecimientoCurso::create([
            'establecimiento_id' => $this->ee->id, 'curso_id' => Curso::where('codigo', '1B')->value('id'),
            'anio' => 2027, 'letra' => 'A', 'nombre_seccion' => '1B A', 'regimen_jec' => 'Con JEC',
            'activo' => true, 'matricula' => 20,
        ]);
        $rut = '99000001-K';
        $this->personal($rut, 36);
        $this->personal($rut, 10, ['establecimiento_id' => $otro->id, 'rbd' => $otro->rbd]);
        $this->justificar('99000001K', 2027);
        $this->asignacion(2027, $rut, 'funcion_directiva', 'Dirección', 4);
        $this->asignacion(2027, $rut, 'funcion_directiva', 'Inspector General', 6);
        $this->asignacion(2027, $rut, 'otra_funcion', 'Subdirectora', 3);
        $this->asignacion(2027, $rut, 'otra_funcion', 'Libre disposición', 3, ['subtipo_asignacion' => 'libre_disposicion']);
        $this->asignacion(2027, $rut, 'reserva_no_normativa', 'Reserva', 2, ['subtipo_asignacion' => 'titular']);
        $this->asignacion(2027, $rut, 'plan_estudio', 'Lenguaje', 12, [
            'establecimiento_curso_id' => $curso->id, 'horas_plan_pedagogicas' => 10, 'proporcion_aplicada' => '65/35',
        ]);
        $this->asignacion(2027, $rut, 'funcion_directiva', 'Dirección', 10, ['establecimiento_id' => $otro->id]);

        foreach (['99000002K' => ['proceso_bir', 0], '99000003K' => ['horas_gremiales', 8]] as $persona => [$motivo, $horas]) {
            $this->personal($persona, 20);
            DB::table('dotacion_docente_exclusiones')->insert([
                'establecimiento_id' => $this->ee->id, 'anio' => 2027, 'docente_rut' => $persona,
                'docente_rut_normalizado' => $persona, 'docente_nombre' => 'Docente de prueba',
                'motivo' => $motivo, 'horas' => $horas,
            ]);
        }
        $this->asignacion(2027, '99000002K', 'otra_funcion', 'Asignación BIR', 5);
        $this->personal('99000004K', 20, ['estatuto' => 'AAEE', 'escalafon' => 'ASISTENTE']);
        $this->asignacion(2027, '99000004K', 'otra_funcion', 'Apoyo AAEE', 10, ['estamento_cobertura' => 'asistente']);
        $this->cupo($this->ee, 'pie', 8);

        $libro = $this->abrirExcelFinanciamiento(2027, ['q' => '99999']);
        try {
            $hoja = $libro->getSheetByName('Dotación docente');
            $this->assertSame('99999', $hoja->getCell('A5')->getValue());
            $this->assertEquals(7, $hoja->getCell('D5')->getValue());
            $this->assertEquals(37, $hoja->getCell('F5')->getValue());
            $this->assertEquals(15, $hoja->getCell('H5')->getValue());
            $this->assertEqualsWithDelta(2 + 31 / 41, $hoja->getCell('I5')->getValue(), 0.00001);
            $this->assertEquals(59, $hoja->getCell('J6')->getCalculatedValue());
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    public function test_excel_libera_las_asignaciones_al_pasar_al_siguiente_establecimiento(): void
    {
        $otro = $this->establecimiento(99998);
        $rut = '99000001K';
        $this->personal($rut, 24);
        $this->personal($rut, 20, ['establecimiento_id' => $otro->id, 'rbd' => $otro->rbd]);
        $this->asignacion(2027, $rut, 'otra_funcion', 'Función sintética', 24);
        $this->asignacion(2027, $rut, 'otra_funcion', 'Función sintética', 20, ['establecimiento_id' => $otro->id]);

        $referencias = [];
        $lecturas = [];
        $retuvoAsignaciones = false;
        Event::listen('eloquent.retrieved: '.DotacionDocenteAsignacion::class,
            function (DotacionDocenteAsignacion $asignacion) use (&$referencias, &$lecturas, &$retuvoAsignaciones): void {
                $id = $asignacion->establecimiento_id;
                foreach ($referencias as $anterior => $referencia) {
                    if ($anterior !== $id && $referencia->get() !== null) {
                        $retuvoAsignaciones = true;
                    }
                }
                $referencias[$id] = WeakReference::create($asignacion);
                $lecturas[$id] = ($lecturas[$id] ?? 0) + 1;
            });

        $libro = $this->abrirExcelFinanciamiento(2027);
        try {
            $this->assertFalse($retuvoAsignaciones, 'El cálculo retuvo asignaciones del establecimiento anterior.');
            // El cálculo territorial y las filas del Excel leen cada establecimiento.
            $this->assertGreaterThanOrEqual(2, $lecturas[$this->ee->id] ?? 0);
            $this->assertGreaterThanOrEqual(2, $lecturas[$otro->id] ?? 0);
            foreach ($referencias as $referencia) {
                $this->assertNull($referencia->get());
            }
            $hoja = $libro->getSheetByName('Dotación docente');
            $this->assertEquals(1, $hoja->getCell('I7')->getCalculatedValue());
            $this->assertEquals(44, $hoja->getCell('J7')->getCalculatedValue());
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    public function test_glosa_completa_1199_horas_en_jornadas_de_44_y_conserva_la_distribucion_al_filtrar(): void
    {
        (require database_path('migrations/2026_09_22_180000_create_dotacion_proceso_2027_configuraciones_table.php'))->up();
        $otro = $this->establecimiento(99998);
        $sinBrecha = $this->establecimiento(99997);
        foreach ([[$this->ee, 44, 744], [$otro, 20, 720], [$sinBrecha, 10, 10]] as [$ee, $horas, $maximo]) {
            $this->cupo($ee, 'plan_estudio', $horas);
            DB::table('dotacion_proceso_2027_configuraciones')->insert([
                'establecimiento_id' => $ee->id, 'anio' => 2027,
                'max_horas_bloque_1' => $maximo, 'max_horas_bloque_2' => 0, 'max_horas_bloque_3' => 0,
            ]);
        }
        foreach ([[], ['q' => '99999']] as $filtros) {
            $libro = $this->abrirExcelFinanciamiento(2027, $filtros);
            try {
                $base = $libro->getSheetByName('Dotación docente');
                $glosa = $libro->getSheetByName('Dotación docente glosa');
                $fila = $filtros === [] ? 7 : 5;
                $this->assertSame('99999', $glosa->getCell('A'.$fila)->getValue());
                $this->assertEquals(700, $glosa->getCell('N'.$fila)->getValue());
                $this->assertEquals(583, $glosa->getCell('O'.$fila)->getValue());
                $this->assertEquals(13.25, $glosa->getCell('P'.$fila)->getValue());
                $this->assertEquals(44, $base->getCell('J'.$fila)->getValue());
                $this->assertEquals(627, $glosa->getCell('J'.$fila)->getValue());
                $this->assertEquals(627, $glosa->getCell('F'.$fila)->getValue());
                $this->assertEquals(14.25, $glosa->getCell('E'.$fila)->getValue());
                if ($filtros === []) {
                    $this->assertEquals(0, $glosa->getCell('O5')->getValue());
                    $this->assertEquals(616, $glosa->getCell('O6')->getValue());
                    $this->assertEquals(1199, $glosa->getCell('O8')->getCalculatedValue());
                    $this->assertEquals(27.25, $glosa->getCell('P8')->getCalculatedValue());
                    $this->assertEquals(1273, $glosa->getCell('J8')->getCalculatedValue());
                }
            } finally {
                $libro->disconnectWorksheets();
            }
        }
        $this->assertSame(3, DB::table('dotacion_contrata_habilitaciones')->count());
        $this->assertSame(0, DotacionDocenteAsignacion::query()->count());
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

    private function cupo(Establecimiento $ee, string $bloque, float $horas, int $anio = 2027): string
    {
        $id = DB::table('dotacion_contrata_habilitaciones')->insertGetId([
            'establecimiento_id' => $ee->id, 'anio' => $anio, 'bloque' => $bloque, 'horas' => $horas,
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
