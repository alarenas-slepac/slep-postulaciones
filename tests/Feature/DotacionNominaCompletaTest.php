<?php

namespace Tests\Feature;

use App\Exports\DotacionNominaCompletaExport;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\Establecimiento;
use App\Support\DocenteHorasNoLectivasCalculator;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionNominaCompleta;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionNominaCompletaTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetCaches();
        $this->withoutMiddleware([EnsureModuleAccess::class, CoordinarEscrituraPadron::class]);
        $this->withoutVite();
        Storage::disk('local')->put('nomina-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head>@stack(\'styles\')</head><body>@yield(\'content\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('nomina-views'));
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        foreach ([
            '2026_01_23_000001_create_establecimientos_table.php',
            '2026_01_29_000001_create_reemplazos_personal_table.php',
            '2026_09_29_190000_create_dotacion_sobredotacion_justificaciones_table.php',
        ] as $migration) { (require database_path('migrations/'.$migration))->up(); }
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rbd'); $t->string('rut');
            foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $campo) { $t->string($campo)->nullable(); }
        });
        Schema::create('dotacion_docente_exclusiones', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->integer('anio');
            $t->string('docente_rut'); $t->string('docente_rut_normalizado'); $t->string('motivo');
            $t->decimal('horas', 8, 2)->default(0); $t->boolean('considerar_dotacion_siguiente')->default(true);
            $t->boolean('posee_fuero_maternal')->default(false); $t->decimal('horas_traspaso_bir', 8, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('dotacion_no_renovaciones', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->integer('anio');
            $t->string('docente_rut_normalizado'); $t->timestamp('anulada_at')->nullable();
        });
        foreach ([4 => 'funcionario_directivo_estab', 5 => 'coordinador_uatp', 6 => 'funcionario_slep'] as $id => $name) {
            DB::table('roles')->insert(['id' => $id, 'name' => $name, 'guard_name' => 'web']);
        }
        foreach ([1 => ['Escuela sintética A', false], 2 => ['Escuela sintética B', false], 3 => ['Sala cuna sintética', true]] as $id => [$nombre, $sala]) {
            Establecimiento::create(['id' => $id, 'cod_estab' => 99990 + $id, 'rbd' => 99990 + $id, 'nombre_establecimiento' => $nombre, 'comuna' => 'Comuna de prueba', 'sala_cuna' => $sala]);
        }
        $this->personal(1, '99000001-K', 22, 'PLANTA', 'Pedagogía en Educación Básica');
        $this->personal(1, '99000001-K', 22, 'CONTRATA', 'Pedagogía en Educación Básica');
        $this->personal(2, '99000001-K', 10, 'CONTRATA', 'Pedagogía en Educación Básica');
        $this->personal(1, '99000002-K', 44, 'PLANTA', 'Pedagogía en Educación de Párvulos');
        $this->personal(1, '99000003-K', 40, 'CONTRATA', 'Educación Diferencial');
        $this->personal(1, '99000004-K', 30, 'CONTRATA', 'Pedagogía en Inglés');
        $this->personal(1, '99000005-K', 20, 'CONTRATA', 'Pedagogía en Música');
        $this->personal(1, '99000006-K', 0, 'CONTRATA', '');
        $this->personal(1, '99000007-K', 44, 'INDEFINIDO', 'Asistente', 'ASISTENTE');
        $this->personal(1, '99000008-K', 44, 'REEMPLAZO', 'Pedagogía en Educación Básica');
        $this->personal(1, '99000009-K', 42, 'PLANTA', 'Pedagogía en Educación Básica', 'DOCENTE', 7);
        $this->personal(3, '99000010-K', 44, 'PLANTA', 'Pedagogía en Educación de Párvulos');
        DB::table('dotacion_docente_exclusiones')->insert([
            ['establecimiento_id' => 1, 'anio' => 2027, 'docente_rut' => '99000002-K', 'docente_rut_normalizado' => '99000002K', 'motivo' => 'trabaja_slep', 'horas' => 44, 'considerar_dotacion_siguiente' => true],
            ['establecimiento_id' => 1, 'anio' => 2026, 'docente_rut' => '99000003-K', 'docente_rut_normalizado' => '99000003K', 'motivo' => 'proceso_bir', 'horas' => 0, 'considerar_dotacion_siguiente' => false],
        ]);
        DB::table('dotacion_no_renovaciones')->insert(['establecimiento_id' => 1, 'anio' => 2027, 'docente_rut_normalizado' => '99000004K']);
        DB::table('dotacion_sobredotacion_justificaciones')->insert([
            'establecimiento_id' => 1, 'anio' => 2027, 'docente_rut_normalizado' => '99000005K',
            'bloque' => 'plan_estudio', 'tipo_horas' => 'contrata', 'horas_detectadas' => 20, 'justificacion' => 'Fundamento sintético de prueba.',
        ]);
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
    }

    protected function tearDown(): void
    {
        $this->resetCaches();
        parent::tearDown();
    }

    private function resetCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) { (new \ReflectionProperty($class, $property))->setValue(null, []); }
        }
        (new \ReflectionProperty(DocenteHorasNoLectivasCalculator::class, 'proportionRowsCache'))->setValue(null, []);
        DocenteHorasNoLectivasCalculator::clearExceptionCache();
    }

    private function personal(int $ee, string $rut, int $horas, string $tipo, string $titulo, string $estamento = 'DOCENTE', int $mes = 8): void
    {
        DB::table('reemplazos_personal')->insert([
            'establecimiento_id' => $ee, 'rbd' => 99990 + $ee, 'rut' => $rut, 'nombre' => 'Docente sintético '.$rut,
            'anio' => 2026, 'mes' => $mes, 'jornada' => $horas, 'jornada_basica' => $horas,
            'tipocontrato' => $tipo, 'estatuto' => $estamento, 'escalafon' => $estamento,
            'row_hash' => hash('sha256', implode('|', [$ee, $rut, $tipo, $mes, $horas])),
        ]);
        DB::table('declaracion_sostenedores')->updateOrInsert(['rbd' => (string) (99990 + $ee), 'rut' => $rut], [
            'nombres' => 'Docente sintético '.$rut, 'nombre_titulo' => $titulo, 'estamento' => $estamento, 'nombre_funcion' => $estamento,
        ]);
    }

    private function filas()
    {
        return app(DotacionNominaCompleta::class)->datos(Establecimiento::where('sala_cuna', false)->get(), 2027);
    }

    public function test_consolida_el_padron_original_e_incluye_todos_los_estados_sin_alterar_datos(): void
    {
        $antes = DB::table('reemplazos_personal')->get()->toJson();
        $filas = $this->filas();
        $this->assertCount(7, $filas);
        $this->assertSame(188.0, (float) $filas->sum('contrato_original'));
        $normal = $filas->where('establecimiento_id', 1)->firstWhere('rut', '99000001-K');
        $this->assertSame(44.0, $normal['contrato_original']);
        $this->assertSame(22.0, $normal['titulares']); $this->assertSame(22.0, $normal['contrata']);
        $this->assertSame(2, $normal['registros_contrato']);
        $this->assertSame('Pedagogía en Educación Básica', $normal['titulo']);
        $parvularia = $filas->firstWhere('rut', '99000002-K');
        $this->assertSame(0.0, $parvularia['contrato_considerado']);
        $this->assertSame(44.0, $parvularia['parvularia']);
        $this->assertSame(40.0, $filas->firstWhere('rut', '99000003-K')['pie']);
        $this->assertStringContainsString('no continúa', $filas->firstWhere('rut', '99000003-K')['estado_dotacion']);
        $this->assertStringContainsString('no renovación', $filas->firstWhere('rut', '99000004-K')['estado_dotacion']);
        $this->assertSame($filas->firstWhere('rut', '99000004-K')['contrato_original'], $filas->firstWhere('rut', '99000004-K')['contrato_considerado']);
        $this->assertTrue($filas->firstWhere('rut', '99000005-K')['justificada']);
        $this->assertSame('Sin título declarado', $filas->firstWhere('rut', '99000006-K')['titulo']);
        foreach ($filas as $fila) { $this->assertEquals($fila['contrato_original'], $fila['plan_estudio'] + $fila['parvularia'] + $fila['pie']); }
        $this->assertSame($antes, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertSame(['personas' => 6, 'registros' => 7, 'establecimientos' => 2, 'contrato' => 188.0], app(DotacionNominaCompleta::class)->resumen($filas));
    }

    public function test_docente_de_varios_bloques_no_duplica_su_contrato_y_especial_conserva_regla(): void
    {
        $nomina = app(DotacionNominaCompleta::class);
        $docente = ['rut' => '99000011-K', 'nombre' => 'Diferencial mixta', 'titulo' => 'Educación Diferencial',
            'horas_contrato_base' => 44, 'horas_contrato' => 20,
            'asignaciones' => [['id' => 1, 'tipo_asignacion' => 'plan_estudio', 'estado' => 'activa', 'docente_rut' => '99000011-K', 'horas_contrato' => 8]]];
        $ee = Establecimiento::findOrFail(1);
        $filas = $nomina->filas($ee, collect([$docente]));
        $this->assertCount(1, $filas);
        $this->assertSame(['plan_estudio', 'pie'], $filas->first()['bloques']);
        $this->assertSame(8.0, $filas->first()['plan_estudio']); $this->assertSame(36.0, $filas->first()['pie']);
        $this->assertSame(44.0, $nomina->filtrar($filas, ['bloque' => 'pie'])->first()['contrato_original']);
        $ee->especial = true;
        $especial = $nomina->filas($ee, collect([$docente]))->sole();
        $this->assertSame(44.0, $especial['plan_estudio']); $this->assertSame(0.0, $especial['pie']);
    }

    public function test_vista_filtra_por_bloque_e_incluye_el_titulo_y_los_ajustados(): void
    {
        $this->get(route('admin.dotacion-establecimiento.nomina-completa.index', ['anio' => 2027, 'bloque' => 'parvularia']))
            ->assertOk()->assertSee('Nómina completa por bloque')->assertSee('Pedagogía en Educación de Párvulos')
            ->assertSee('99000002-K')->assertDontSee('99000003-K')->assertSee('Exportar nómina completa (Excel)')
            ->assertViewHas('resumen', fn ($r) => $r['registros'] === 1 && $r['contrato'] === 44.0);
        $this->get(route('admin.dotacion-establecimiento.nomina-completa.index', ['anio' => 2027, 'q' => 'diferencial']))
            ->assertOk()->assertSee('99000003-K')->assertDontSee('99000001-K');
    }

    public function test_excel_consolida_en_una_fila_y_el_resumen_concilia_con_todos_los_resultados(): void
    {
        $response = $this->get(route('admin.dotacion-establecimiento.nomina-completa.export', ['anio' => 2027, 'establecimiento_id' => 0]));
        $response->assertOk()->assertDownload('nomina_completa_docentes_por_bloque_2027.xlsx');
        $path = Storage::disk('local')->path('nomina.xlsx');
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        try {
            $this->assertSame(['Docentes', 'Resumen establecimientos', 'Definiciones'], $book->getSheetNames());
            $sheet = $book->getSheet(0);
            $this->assertSame(7, count(array_filter($sheet->rangeToArray('D7:D13'), fn ($r) => $r[0] !== null)));
            $this->assertEquals(188, $sheet->getCell('I15')->getValue());
            $this->assertEquals(44, $sheet->getCell('I7')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('F7')->getDataType());
            $this->assertEquals(178, $book->getSheet(1)->getCell('E7')->getValue());
            $this->assertEquals(10, $book->getSheet(1)->getCell('E8')->getValue());
        } finally { $book->disconnectWorksheets(); }
    }

    public function test_excel_exporta_todas_las_paginas_y_respeta_filtros(): void
    {
        for ($i = 20; $i < 71; $i++) { $this->personal(2, '99000'.$i.'-K', 1, 'CONTRATA', 'Pedagogía sintética'); }
        $params = ['anio' => 2027, 'establecimiento_id' => 2];
        $this->get(route('admin.dotacion-establecimiento.nomina-completa.index', $params))->assertOk()
            ->assertViewHas('items', fn ($p) => $p->count() === 50 && $p->total() === 52);
        $response = $this->get(route('admin.dotacion-establecimiento.nomina-completa.export', $params + ['page' => 2]))->assertOk();
        $path = Storage::disk('local')->path('nomina-todas.xlsx'); file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        try { $this->assertEquals(61, $book->getSheet(0)->getCell('I60')->getValue()); }
        finally { $book->disconnectWorksheets(); }
    }

    public function test_directivo_solo_consulta_y_exporta_su_establecimiento(): void
    {
        $user = $this->testUser(id: 2, role: 4); $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->get(route('admin.dotacion-establecimiento.nomina-completa.index', ['anio' => 2027]))
            ->assertOk()->assertViewHas('resumen', fn ($r) => $r['establecimientos'] === 1 && $r['contrato'] === 178.0)
            ->assertDontSee('Escuela sintética B');
        foreach (['index', 'export'] as $action) {
            $this->get(route('admin.dotacion-establecimiento.nomina-completa.'.$action, ['anio' => 2027, 'establecimiento_id' => 2]))->assertForbidden();
        }
        $this->get(route('admin.dotacion-establecimiento.nomina-completa.export', ['anio' => 2027]))->assertOk();
    }

    public function test_roles_no_autorizados_y_filtros_invalidos_no_acceden(): void
    {
        $this->get(route('admin.dotacion-establecimiento.nomina-completa.index', ['bloque' => 'no_existe']))->assertSessionHasErrors('bloque');
        $this->get(route('admin.dotacion-establecimiento.nomina-completa.export', ['establecimiento_id' => 3]))->assertNotFound();
        $this->actingAs($this->testUser(id: 2, role: 6))->withSession(['active_role' => 'funcionario_slep']);
        foreach (['index', 'export'] as $action) { $this->get(route('admin.dotacion-establecimiento.nomina-completa.'.$action))->assertForbidden(); }
    }

    public function test_excel_sin_resultados_y_textos_que_parecen_formulas_se_conservan_como_texto(): void
    {
        $export = new DotacionNominaCompletaExport;
        $filas = $this->filas();
        $filas = collect([array_replace($filas->first(), ['titulo' => '=1+1', 'nombre' => '+DOCENTE SINTÉTICO'])]);
        $book = $export->workbook(2027, $filas);
        try {
            $this->assertSame('=1+1', $book->getSheet(0)->getCell('F7')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $book->getSheet(0)->getCell('F7')->getDataType());
        } finally { $book->disconnectWorksheets(); }
        $empty = $export->workbook(2027, collect());
        try { $this->assertSame(0.0, $empty->getSheet(0)->getCell('I9')->getValue()); }
        finally { $empty->disconnectWorksheets(); }
    }
}
