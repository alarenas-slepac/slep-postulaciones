<?php

namespace Tests\Feature;

use App\Exports\DotacionSobredotacionExport;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Models\User;
use App\Services\Dotacion\EdicionBloqueoService;
use App\Support\DotacionNominaDocentes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionSobredotacionExportTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureModuleAccess::class, CoordinarEscrituraPadron::class]);
        $this->withoutVite();
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('establecimiento_id')->nullable());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna'); $t->boolean('sala_cuna')->default(false);
        });
        foreach ([1 => false, 2 => false, 3 => true] as $id => $cuna) {
            DB::table('establecimientos')->insert(['id' => $id, 'rbd' => 99999 - $id + 1,
                'nombre_establecimiento' => 'Establecimiento sintético '.$id, 'comuna' => 'Comuna de prueba', 'sala_cuna' => $cuna]);
        }
        foreach ([4 => 'funcionario_directivo_estab', 5 => 'coordinador_uatp', 6 => 'coordinador_gdp', 7 => 'supervisor_plani', 8 => 'funcionario_slep'] as $id => $rol) {
            DB::table('roles')->insert(['id' => $id, 'name' => $rol, 'guard_name' => 'web']);
        }
        $this->ee = Establecimiento::findOrFail(1);
    }

    private function docente(array $changes = []): array
    {
        return array_replace([
            'rut' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Pedagogía de prueba',
            'horas_planta' => 40, 'horas_contrata' => 4, 'tramo' => 'Inicial', 'fecha_antiguedad' => '2010-01-01',
            'horas_contrato_base' => 44, 'horas_contrato' => 44, 'horas_aula' => 20,
            'horas_asignadas_total' => 30, 'horas_reservadas_no_normativas' => 4, 'diferencia' => 14,
        ], $changes);
    }

    private function vacante(string $rut = '99000001-K', array $changes = []): array
    {
        return array_replace(['rut' => $rut, 'horas_contrato_categoria' => 20,
            'horas_sobredotacion_total' => 8, 'horas_sobredotacion_planta' => 6, 'horas_sobredotacion_contrata' => 2], $changes);
    }

    private function registro(string $bloque, string $tipo, float $horas, string $texto): DotacionSobredotacionJustificacion
    {
        return new DotacionSobredotacionJustificacion(['establecimiento_id' => 1, 'anio' => 2027,
            'docente_rut_normalizado' => '99.000.001-K', 'bloque' => $bloque, 'tipo_horas' => $tipo,
            'horas_detectadas' => $horas, 'justificacion' => $texto]);
    }

    private function informe(array $changes = []): array
    {
        $bloques = (new DotacionNominaDocentes)->filasPorBloque(collect([$this->docente()]), [
            'vacantes_por_bloque' => ['plan_estudio' => ['items' => [$this->vacante()]]],
        ], collect());

        return array_replace(['bloques' => $bloques, 'periodos_padron' => '10/2026', 'justificaciones_disponibles' => true], $changes);
    }

    public function test_separa_por_bloque_conservando_prelacion_y_excluye_docentes_sin_saldo_cupos_y_asistentes(): void
    {
        $personas = collect([
            $this->docente(['rut' => '99000004-K', 'nombre' => 'Contrata', 'horas_planta' => 0]),
            $this->docente(['rut' => '99000003-K', 'nombre' => 'Experto', 'tramo' => 'Experto 2']),
            $this->docente(),
            $this->docente(['rut' => '99000002-K', 'nombre' => 'No Regido', 'horas_planta' => 0, 'tramo' => 'No Regido Ley 20903']),
            $this->docente(['rut' => '99000005-K', 'nombre' => 'Sin saldo']),
            $this->docente(['rut' => 'CUPO-1', 'cupo_contrata_id' => 1]),
            $this->docente(['rut' => '99000006-K', 'estamento_cobertura' => 'asistente']),
        ]);
        $items = $personas->reject(fn ($p) => $p['nombre'] === 'Sin saldo')->map(fn ($p) => $this->vacante($p['rut']))->all();
        $bloques = (new DotacionNominaDocentes)->filasPorBloque($personas, ['vacantes_por_bloque' => [
            'plan_estudio' => ['items' => $items], 'parvularia' => ['items' => array_reverse($items)],
        ]], collect());
        $this->assertSame(['plan_estudio', 'parvularia', 'pie'], array_keys($bloques));
        foreach (['plan_estudio', 'parvularia'] as $key) {
            $this->assertSame(['No Regido', 'Experto', 'Docente sintético', 'Contrata'], $bloques[$key]->pluck('nombre')->all());
            $this->assertEquals(20, $bloques[$key]->first()['contrato_bloque']);
            $this->assertEquals(8, $bloques[$key]->first()['saldo_bloque']);
        }
        $this->assertCount(0, $bloques['pie']);
    }

    public function test_justificaciones_por_tipo_y_bloque_vigentes_desactualizadas_vacias_y_sin_saldo(): void
    {
        $registros = collect([
            $this->registro('plan_estudio', 'titular', 6, 'Justificación titular sintética.'),
            $this->registro('plan_estudio', 'contrata', 9, 'Fundamento contrata anterior.'),
            $this->registro('parvularia', 'titular', 6, '   '),
            $this->registro('pie', 'titular', 6, 'Fundamento sin saldo titular.'),
        ]);
        $filas = (new DotacionNominaDocentes)->filasPorBloque(collect([$this->docente()]), ['vacantes_por_bloque' => [
            'plan_estudio' => ['items' => [$this->vacante('99.000.001-K')]],
            'parvularia' => ['items' => [$this->vacante()]],
            'pie' => ['items' => [$this->vacante('99000001-K', ['horas_sobredotacion_planta' => 0, 'horas_sobredotacion_contrata' => 8])]],
        ]], $registros);
        $plan = $filas['plan_estudio']->first();
        $this->assertSame('Justificación titular sintética.', $plan['justificacion_titular']); $this->assertSame('Vigente', $plan['estado_titular']);
        $this->assertSame('Fundamento contrata anterior.', $plan['justificacion_contrata']);
        $this->assertStringContainsString('Desactualizada', $plan['estado_contrata']); $this->assertStringContainsString('9 h', $plan['estado_contrata']);
        $this->assertEquals(6, $plan['con_justificacion']); $this->assertEquals(2, $plan['sin_justificacion']);
        $nt = $filas['parvularia']->first();
        $this->assertSame('Sin justificación', $nt['justificacion_titular']); $this->assertSame('Sin justificación', $nt['justificacion_contrata']);
        $this->assertEquals(0, $nt['con_justificacion']); $this->assertEquals(8, $nt['sin_justificacion']);
        $pie = $filas['pie']->first(); $this->assertSame('Fundamento sin saldo titular.', $pie['justificacion_titular']);
        $this->assertSame('Sin saldo actual', $pie['estado_titular']); $this->assertEquals(8, $pie['sin_justificacion']);
        $sinSaldo = (new DotacionNominaDocentes)->filasPorBloque(collect([$this->docente()]), [], $registros);
        foreach ($sinSaldo as $rows) { $this->assertCount(0, $rows); }
    }

    public function test_xlsx_separa_hojas_con_fechas_numeros_totales_y_fundamentos_como_texto_seguro(): void
    {
        $bloques = (new DotacionNominaDocentes)->filasPorBloque(collect([$this->docente(['nombre' => '=SUM(1,2)'])]), [
            'vacantes_por_bloque' => ['plan_estudio' => ['items' => [$this->vacante()]]],
        ], collect([$this->registro('plan_estudio', 'titular', 6, '=HYPERLINK("https://example.test")')]));
        $book = app(DotacionSobredotacionExport::class)->workbook($this->ee, 2028, $this->informe(['bloques' => $bloques]));
        $path = Storage::disk('local')->path('sobredotacion-sintetica.xlsx'); (new Xlsx($book))->save($path); $book->disconnectWorksheets();
        $loaded = IOFactory::load($path);
        try {
            $this->assertSame(['Plan de estudio', 'Educación Parvularia', 'PIE', 'Definiciones'], $loaded->getSheetNames());
            $sheet = $loaded->getSheet(0);
            $this->assertStringContainsString('2028', $sheet->getCell('H6')->getValue());
            $this->assertStringContainsString('10/2026', $sheet->getCell('A3')->getValue());
            $this->assertSame('=SUM(1,2)', $sheet->getCell('C7')->getValue()); $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C7')->getDataType());
            $this->assertSame('=HYPERLINK("https://example.test")', $sheet->getCell('T7')->getValue()); $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('T7')->getDataType());
            $this->assertSame('Sin justificación', $sheet->getCell('V7')->getValue());
            $this->assertSame('2010-01-01', Date::excelToDateTimeObject($sheet->getCell('G7')->getValue())->format('Y-m-d'));
            $this->assertSame('dd-mm-yyyy', $sheet->getStyle('G7')->getNumberFormat()->getFormatCode());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('O7')->getDataType()); $this->assertEquals(8, $sheet->getCell('O8')->getValue());
            $this->assertEquals(6, $sheet->getCell('R8')->getValue()); $this->assertEquals(2, $sheet->getCell('S8')->getValue());
            $this->assertSame('A6:W7', $sheet->getAutoFilter()->getRange()); $this->assertSame('D7', $sheet->getFreezePane());
            $this->assertStringContainsString('No hay docentes', $loaded->getSheet(1)->getCell('A7')->getValue());
            $this->assertEquals(0, $loaded->getSheet(1)->getCell('O8')->getValue());
        } finally { $loaded->disconnectWorksheets(); }
    }

    public static function roles(): array
    {
        return [[3, 'admin', true], [4, 'funcionario_directivo_estab', true], [5, 'coordinador_uatp', true], [6, 'coordinador_gdp', true], [7, 'supervisor_plani', true], [8, 'funcionario_slep', false], [1, 'postulante', false]];
    }

    #[DataProvider('roles')]
    public function test_descarga_por_rol_activo_y_anio(int $id, string $rol, bool $allowed): void
    {
        $user = $this->testUser($id, $id); $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => $rol]);
        $mock = $this->mock(DotacionNominaDocentes::class);
        if ($allowed) { $mock->shouldReceive('datosSobredotacion')->once()->withArgs(fn ($ee, $year) => $ee->id === 1 && $year === 2028)->andReturn($this->informe()); }
        else { $mock->shouldNotReceive('datosSobredotacion'); }
        $response = $this->get(route('admin.dotacion-establecimiento.sobredotacion.export', [1, 'anio' => 2028]));
        if ($allowed) {
            $response->assertOk()->assertDownload('nomina_sobredotacion_prelacion_rbd_99999_2028.xlsx')
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringStartsWith('PK', $response->streamedContent());
        } else { $response->assertForbidden(); }
    }

    public function test_directivo_no_exporta_otro_establecimiento_y_lectura_sigue_disponible_con_bloqueo(): void
    {
        (require database_path('migrations/2026_11_02_140000_create_dotacion_edicion_bloqueos.php'))->up();
        $admin = $this->testUser(10, 3); app(EdicionBloqueoService::class)->guardar(null, true, 0, null, $admin);
        $user = $this->testUser(4, 4); $user->forceFill(['establecimiento_id' => 1])->save();
        DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => User::class, 'model_id' => $user->id]); $user->unsetRelation('roles');
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->mock(DotacionNominaDocentes::class)->shouldReceive('datosSobredotacion')->once()->andReturn($this->informe());
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.export', [1, 'anio' => 2027]))->assertOk()->assertDownload();
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.export', 2))->assertForbidden();
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.export', 3))->assertNotFound();
        $this->getJson(route('admin.dotacion-establecimiento.sobredotacion.export', [1, 'anio' => 'invalido']))->assertUnprocessable()->assertJsonValidationErrors('anio');
        $user->forceFill(['establecimiento_id' => null])->save();
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.export', 1))->assertForbidden();
    }
}
