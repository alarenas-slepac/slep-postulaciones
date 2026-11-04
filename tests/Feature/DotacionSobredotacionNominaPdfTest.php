<?php

namespace Tests\Feature;

use App\Exports\DotacionSobredotacionExport;
use App\Exports\DotacionSobredotacionNominaPdfExport;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Models\User;
use App\Services\Dotacion\EdicionBloqueoService;
use App\Support\DotacionNominaDocentes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionSobredotacionNominaPdfTest extends IsolatedSecurityTestCase
{
    protected Establecimiento $ee;

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
            DB::table('establecimientos')->insert(['id' => $id, 'rbd' => 100000 - $id,
                'nombre_establecimiento' => 'Establecimiento sintético '.$id, 'comuna' => 'Comuna de prueba', 'sala_cuna' => $cuna]);
        }
        foreach ([4 => 'funcionario_directivo_estab', 5 => 'coordinador_uatp', 6 => 'coordinador_gdp', 7 => 'supervisor_plani', 8 => 'funcionario_slep'] as $id => $rol) {
            DB::table('roles')->insert(['id' => $id, 'name' => $rol, 'guard_name' => 'web']);
        }
        $this->ee = Establecimiento::findOrFail(1);
    }

    protected function docente(array $changes = []): array
    {
        return array_replace(['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'titulo' => 'Pedagogía de prueba',
            'horas_planta' => 40, 'horas_contrata' => 4, 'tramo' => 'Inicial', 'fecha_antiguedad' => '2010-01-01',
            'horas_contrato_base' => 44, 'horas_contrato' => 44, 'horas_aula' => 20,
            'horas_asignadas_total' => 30, 'horas_reservadas_no_normativas' => 4, 'diferencia' => 14], $changes);
    }

    protected function vacante(string $rut = '99000001-K', array $changes = []): array
    {
        return array_replace(['rut' => $rut, 'horas_contrato_categoria' => 20, 'horas_sobredotacion_total' => 8,
            'horas_sobredotacion_planta' => 6, 'horas_sobredotacion_contrata' => 2], $changes);
    }

    protected function registro(string $bloque, string $tipo, float $horas, string $texto): DotacionSobredotacionJustificacion
    {
        return new DotacionSobredotacionJustificacion(['establecimiento_id' => 1, 'anio' => 2027,
            'docente_rut_normalizado' => '99.000.001-K', 'bloque' => $bloque, 'tipo_horas' => $tipo,
            'horas_detectadas' => $horas, 'justificacion' => $texto]);
    }

    protected function informe(?array $personas = null, ?array $vacantes = null, ?Collection $registros = null): array
    {
        $personas ??= [$this->docente()];
        $vacantes ??= ['plan_estudio' => ['items' => array_map(fn ($p) => $this->vacante($p['rut']), $personas)]];
        return ['bloques' => (new DotacionNominaDocentes)->filasPorBloque(collect($personas), ['vacantes_por_bloque' => $vacantes], $registros ?? collect()),
            'periodos_padron' => '10/2026', 'justificaciones_disponibles' => true];
    }

    private function xpath(array $informe): \DOMXPath
    {
        $datos = [];
        View::composer('admin.dotacion-establecimiento.sobredotacion-nomina-pdf', function ($view) use (&$datos) {
            $datos = $view->getData();
        });
        (new DotacionSobredotacionNominaPdfExport)->documento($this->ee, 2028, $informe);
        // Dompdf libera los nodos del cuerpo al renderizar. Verificar el HTML con los datos reales del exportador.
        $html = view('admin.dotacion-establecimiento.sobredotacion-nomina-pdf', $datos)->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }

    public function test_preserva_los_doce_campos_de_horas_y_totales_del_excel_en_cada_bloque(): void
    {
        $informe = $this->informe(vacantes: [
            'plan_estudio' => ['items' => [$this->vacante()]],
            'parvularia' => ['items' => [$this->vacante(changes: ['horas_contrato_categoria' => 10.25, 'horas_sobredotacion_total' => 3.75, 'horas_sobredotacion_planta' => 1.25, 'horas_sobredotacion_contrata' => 2.5])]],
            'pie' => ['items' => [$this->vacante(changes: ['horas_contrato_categoria' => 13.5, 'horas_sobredotacion_total' => 4, 'horas_sobredotacion_planta' => 0, 'horas_sobredotacion_contrata' => 4])]],
        ]);
        $xpath = $this->xpath($informe);
        $book = (new DotacionSobredotacionExport)->workbook($this->ee, 2028, $informe);
        try {
            foreach ($xpath->query('//div[@class="summary"]') as $index => $summary) {
                $values = array_map(fn ($n) => (float) str_replace(',', '.', trim($n->textContent)), iterator_to_array($xpath->query('.//tbody/tr/td', $summary)));
                $excel = array_map(fn ($c) => (float) $book->getSheet($index)->getCell($c.'7')->getValue(), range('H', 'S'));
                $this->assertEquals($excel, $values);
            }
            foreach ($xpath->query('//div[@class="totals"]') as $index => $total) {
                $values = array_map(fn ($n) => (float) str_replace(',', '.', trim($n->textContent)), iterator_to_array($xpath->query('.//tbody/tr/td', $total)));
                $excel = array_map(fn ($c) => (float) $book->getSheet($index)->getCell($c.'8')->getValue(), range('H', 'S'));
                $this->assertEquals($excel, $values);
            }
            $this->assertSame(3, $xpath->query('//div[@class="summary"]')->length);
        } finally { $book->disconnectWorksheets(); }
    }

    public function test_orden_prelacion_y_fundamentos_por_calidad_vigentes_anteriores_y_sin_saldo(): void
    {
        $informe = $this->informe([
            $this->docente(), $this->docente(['rut' => '99000002-K', 'nombre' => 'Experto sintético', 'tramo' => 'Experto 2']),
        ], ['plan_estudio' => ['items' => [$this->vacante(), $this->vacante('99000002-K')]],
            'pie' => ['items' => [$this->vacante(changes: ['horas_sobredotacion_planta' => 0, 'horas_sobredotacion_contrata' => 8])]]], collect([
                $this->registro('plan_estudio', 'titular', 6, 'Fundamento titular vigente.'),
                $this->registro('plan_estudio', 'contrata', 9, 'Fundamento contrata desactualizado.'),
                $this->registro('pie', 'titular', 3, 'Fundamento previo sin saldo titular.'),
            ]));
        $xpath = $this->xpath($informe);
        $this->assertStringContainsString('Experto sintético', $xpath->query('//p[@class="identity"]')->item(0)->textContent);
        $text = $xpath->document->textContent;
        foreach (['Vigente', 'Desactualizada', 'registrada para 9 h', 'Fundamento titular vigente.', 'Fundamento contrata desactualizado.', 'Fundamento previo sin saldo titular.', 'Sin saldo actual', 'Sin justificación'] as $value) {
            $this->assertStringContainsString($value, $text);
        }
        foreach (['Tramo:', 'Antigüedad: 01-01-2010', 'Pedagogía de prueba', '99000001-K', '10/2026'] as $value) { $this->assertStringContainsString($value, $text); }
    }

    public function test_valores_no_determinados_no_se_convierten_en_cero_y_textos_se_escapan(): void
    {
        $informe = $this->informe([$this->docente(['nombre' => '<script>Docente sintético</script>', 'horas_contrato_base' => null,
            'horas_contrato' => null, 'diferencia' => null, 'fecha_antiguedad' => 'fecha histórica'])], registros: collect([
                $this->registro('plan_estudio', 'titular', 6, '<img src="x">Fundamento completo seguro.'),
            ]));
        $xpath = $this->xpath($informe);
        $this->assertSame(0, $xpath->query('//script|//img')->length);
        $this->assertStringContainsString('<script>Docente sintético</script>', $xpath->document->textContent);
        $this->assertStringContainsString('<img src="x">Fundamento completo seguro.', $xpath->document->textContent);
        $this->assertStringContainsString('fecha histórica', $xpath->document->textContent);
        foreach (['summary', 'totals'] as $class) {
            $this->assertSame('-', trim($xpath->query('//div[@class="'.$class.'"]//tbody/tr/td')->item(0)->textContent));
        }
    }

    public function test_papel_mexico_oficio_vertical_paginacion_y_justificacion_extensa_sin_recorte(): void
    {
        $personas = array_map(fn ($n) => $this->docente(['rut' => (99000000 + $n).'-K', 'nombre' => 'Docente sintético '.$n]), range(1, 24));
        $texto = str_repeat('Fundamento extenso sintético para revisión. ', 200).'FIN DEL FUNDAMENTO COMPLETO.';
        $informe = $this->informe($personas, registros: collect([$this->registro('plan_estudio', 'titular', 6, $texto)]));
        $pdf = (new DotacionSobredotacionNominaPdfExport)->documento($this->ee, 2028, $informe);
        $canvas = $pdf->getDomPDF()->getCanvas();
        $this->assertEqualsWithDelta(612.2835, $canvas->get_width(), .01);
        $this->assertEqualsWithDelta(963.7795, $canvas->get_height(), .01);
        $this->assertGreaterThan(3, $canvas->get_page_count());
        $this->assertStringContainsString($texto, $this->xpath($informe)->document->textContent);
        $this->assertStringStartsWith('%PDF-', $pdf->output());
    }

    public function test_sin_docentes_o_tabla_de_justificaciones_informa_estados_vacios_en_una_pagina(): void
    {
        $informe = $this->informe([]); $informe['justificaciones_disponibles'] = false;
        $pdf = (new DotacionSobredotacionNominaPdfExport)->documento($this->ee, 2026, $informe);
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $text = $this->xpath($informe)->document->textContent;
        $this->assertSame(3, substr_count($text, 'No hay docentes con horas contractuales sin asignación en este bloque.'));
        $this->assertStringContainsString('La tabla de justificaciones no está disponible.', $text);
    }

    public static function roles(): array
    {
        return [[3, 'admin', true], [4, 'funcionario_directivo_estab', true], [5, 'coordinador_uatp', true],
            [6, 'coordinador_gdp', true], [7, 'supervisor_plani', true], [8, 'funcionario_slep', false], [1, 'postulante', false]];
    }

    #[DataProvider('roles')]
    public function test_descarga_por_rol_activo_establecimiento_y_anio(int $id, string $rol, bool $allowed): void
    {
        $user = $this->testUser($id, $id); $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => $rol]);
        $mock = $this->mock(DotacionNominaDocentes::class);
        if ($allowed) { $mock->shouldReceive('datosSobredotacion')->once()->withArgs(fn ($ee, $year) => $ee->id === 1 && $year === 2028)->andReturn($this->informe()); }
        else { $mock->shouldNotReceive('datosSobredotacion'); }
        $response = $this->get(route('admin.dotacion-establecimiento.sobredotacion.nomina.pdf', [1, 'anio' => 2028, 'revision_sobredotacion_q' => 'filtro de pantalla']));
        if ($allowed) {
            $response->assertOk()->assertDownload('nomina_sobredotacion_prelacion_rbd_99999_2028.pdf')
                ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        } else { $response->assertForbidden(); }
    }

    public function test_directivo_solo_descarga_su_establecimiento_incluso_con_otro_rol_inactivo_y_bloqueo_general(): void
    {
        (require database_path('migrations/2026_11_02_140000_create_dotacion_edicion_bloqueos.php'))->up();
        $admin = $this->testUser(10, 3); app(EdicionBloqueoService::class)->guardar(null, true, 0, null, $admin);
        $user = $this->testUser(4, 4); $user->forceFill(['establecimiento_id' => 1])->save();
        DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => User::class, 'model_id' => $user->id]); $user->unsetRelation('roles');
        $this->actingAs($user)->withSession(['active_role' => 'funcionario_directivo_estab']);
        $this->mock(DotacionNominaDocentes::class)->shouldReceive('datosSobredotacion')->once()->andReturn($this->informe());
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.nomina.pdf', [1, 'anio' => 2027]))->assertOk()->assertDownload();
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.nomina.pdf', 2))->assertForbidden();
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.nomina.pdf', 3))->assertNotFound();
        foreach (['invalido', 0, 10000] as $year) {
            $this->getJson(route('admin.dotacion-establecimiento.sobredotacion.nomina.pdf', [1, 'anio' => $year]))->assertUnprocessable()->assertJsonValidationErrors('anio');
        }
        $user->forceFill(['establecimiento_id' => null])->save();
        $this->get(route('admin.dotacion-establecimiento.sobredotacion.nomina.pdf', 1))->assertForbidden();
    }

    public function test_boton_pdf_esta_junto_al_excel_y_conserva_el_pdf_general(): void
    {
        $html = view('admin.dotacion-establecimiento.partials._sobredotacion', ['establecimiento' => $this->ee, 'anio' => 2028,
            'activeRole' => 'admin', 'docentes' => collect(), 'sobredotacion' => \App\Support\DotacionSobredotacionCalculator::build([], [])])->render();
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$html); $xpath = new \DOMXPath($dom);
        $excel = $xpath->query('//a[contains(@href,"sobredotacion/nomina.xlsx")]')->item(0);
        $pdf = $xpath->query('following-sibling::a[1]', $excel)->item(0);
        $this->assertSame(route('admin.dotacion-establecimiento.sobredotacion.nomina.pdf', [1, 'anio' => 2028]), $pdf->getAttribute('href'));
        $this->assertStringContainsString('Exportar sobredotación (PDF)', $pdf->textContent);
        $this->assertStringContainsString('Descargar detalle PDF', $html);
    }
}
