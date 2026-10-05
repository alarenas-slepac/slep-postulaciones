<?php

namespace Tests\Feature;

use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionRevisionUsabilidadTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['request']->setLaravelSession($this->app['session.store']);
    }

    public function test_resumen_separa_reservas_y_saldo_sin_compensar_vacantes_revisables(): void
    {
        $data = $this->data();
        $xpath = $this->xpath($this->render('docentes', $data));
        $general = $xpath->query('//*[@data-contract-block="bloque_1"]')->item(0);
        foreach (['vigente' => '100 h', 'asignadas' => '80 h', 'reservadas' => '10 h', 'saldo' => '10 h'] as $field => $value) {
            $this->assertSame($value, trim($xpath->query('.//*[@data-contract-value="'.$field.'"]', $general)->item(0)->textContent));
        }
        $this->assertStringContainsString('14 h', $general->textContent);
        $this->assertStringContainsString('Cobertura AAEE: 6 h', $general->textContent);
        $this->assertSame(3, $xpath->query('//*[@data-contract-block]')->length);
        $data['proceso2027']['bloques']['bloque_1']['asignadas'] = 115;
        $over = $this->xpath($this->render('docentes', $data));
        $this->assertSame('-15 h', trim($over->query('//*[@data-contract-block="bloque_1"]//*[@data-contract-value="saldo"]')->item(0)->textContent));
    }

    public function test_neteo_nt_y_reservas_se_muestran_sin_alterar_los_datos_recibidos(): void
    {
        $data = $this->data();
        $original = $data['docentes']->all();
        $xpath = $this->xpath($this->render('docentes', $data));
        $rows = $xpath->query('//tr[@data-revision-row]');
        $this->assertSame('Docente Básica de prueba', trim($xpath->query('./td[2]/div[1]', $rows->item(0))->item(0)->textContent));
        $this->assertSame('37', trim($xpath->query('.//*[@data-docente-value="asignadas"]', $rows->item(0))->item(0)->textContent));
        $this->assertSame('3', trim($xpath->query('.//*[@data-docente-value="reservadas"]', $rows->item(0))->item(0)->textContent));
        $this->assertSame('reserva', trim(str_replace('saldo', '', $rows->item(0)->getAttribute('data-revision-state'))));
        $nt = $rows->item(1);
        $this->assertSame('cuadra', trim($nt->getAttribute('data-revision-state')));
        $this->assertStringContainsString('43', $xpath->query('.//*[@data-docente-value="asignadas"]', $nt)->item(0)->textContent);
        $this->assertStringContainsString('0,37 h de redondeo NT', $nt->textContent);
        $this->assertSame($original, $data['docentes']->all());
        $this->assertSame('0 h', trim($xpath->query('//*[@data-contract-block="bloque_2"]//*[@data-contract-value="saldo"]')->item(0)->textContent));
        foreach ($rows as $row) {
            $this->assertSame(10, $xpath->query('./td', $row)->length);
            $key = $row->getAttribute('data-revision-row');
            $this->assertSame(1, $xpath->query('//tr[@data-revision-related="'.$key.'"]')->length);
        }
    }

    public function test_enlaces_conservan_anio_docente_y_permisos_y_los_bloques_admiten_contratos_mixtos(): void
    {
        $data = $this->data();
        $xpath = $this->xpath($this->render('docentes', $data));
        $this->assertSame('plan_estudio pie', $xpath->query('//tr[@data-revision-row]')->item(0)->getAttribute('data-revision-block'));
        $links = $xpath->query('//a[contains(@href,"asig_buscar")]');
        $this->assertSame(2, $links->length);
        parse_str(parse_url($links->item(0)->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['anio' => '2027', 'tab' => 'asignacion', 'asig_buscar' => '99000001-K'], $query);
        $this->assertSame(1, $xpath->query('//a[contains(@href,"tab=sobredotacion")]')->length);
        $data['canViewSobredotacion'] = false;
        $restricted = $this->xpath($this->render('docentes', $data));
        $this->assertSame(0, $restricted->query('//a[contains(@href,"tab=sobredotacion")]')->length);
        $this->assertSame(0, $restricted->query('//form')->length);
    }

    public function test_justificaciones_pendientes_y_desactualizadas_permanecen_por_docente_y_bloque(): void
    {
        $data = $this->data();
        $data['justificacionesSobredotacion'] = collect([
            DotacionSobredotacionJustificacion::clave('plan_estudio', '99000001-K', 'titular') => new DotacionSobredotacionJustificacion([
                'horas_detectadas' => 3, 'justificacion' => 'Fundamento anterior sintético.',
            ]),
        ]);
        session()->flashInput(['anio' => 2027, 'docente_rut' => '99000001-K', 'bloque' => 'plan_estudio', 'tipo_horas' => 'titular', 'justificacion' => 'Texto <script>de prueba</script>']);
        $data['errors'] = (new ViewErrorBag)->put('default', new MessageBag(['justificacion' => 'Revise el fundamento de prueba.']));
        $xpath = $this->xpath($this->render('sobredotacion', $data));
        $row = $xpath->query('//tr[@data-revision-error="1"]')->item(0);
        $this->assertStringContainsString('justificacion', $row->getAttribute('data-revision-state'));
        $this->assertSame(1, $xpath->query('//tr[@data-revision-error="1"]')->length);
        $this->assertSame(1, $xpath->query('//textarea[contains(@class,"is-invalid")]')->length);
        $this->assertSame('Texto <script>de prueba</script>', $xpath->query('//textarea[contains(@class,"is-invalid")]')->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//textarea//script')->length);
        $this->assertStringContainsString('La justificación anterior correspondía a 3 h', $xpath->document->textContent);
        $this->assertSame(1, $xpath->query('//a[contains(@href,"revision_docentes_q")]')->length);
        $data['canManageJustificacionesSobredotacion'] = false;
        $readonly = $this->xpath($this->render('sobredotacion', $data));
        $this->assertSame(0, $readonly->query('//textarea')->length);
        $this->assertStringContainsString('Ver motivos', $readonly->document->textContent);
    }

    public function test_anios_historicos_y_bloques_vacios_no_inventan_topes_ni_saldos(): void
    {
        $data = $this->data();
        $data['anio'] = 2026;
        $data['proceso2027'] = ['aplica' => false];
        $data['docentes'] = collect();
        $xpath = $this->xpath($this->render('docentes', $data));
        $this->assertSame(0, $xpath->query('//*[@data-contract-block]')->length);
        $this->assertSame(0, $xpath->query('//tr[@data-revision-row]')->length);
        $this->assertStringContainsString('No se encontraron docentes vigentes', $xpath->document->textContent);
        $data['sobredotacion']['vacantes_por_bloque'] = [];
        $empty = $this->xpath($this->render('sobredotacion', $data));
        $this->assertSame(3, substr_count($empty->document->textContent, 'No hay horas contractuales sin asignación en este bloque.'));
    }

    public function test_sobrecarga_sin_funciones_declaradas_es_visible_y_un_ee_especial_no_se_clasifica_en_pie(): void
    {
        $data = $this->data();
        $data['resumen']['establecimiento_especial'] = true;
        $persona = $data['docentes']->first();
        $persona['horas_asignadas_total'] = 49;
        $persona['diferencia'] = -5;
        $data['docentes'] = collect([$persona]);
        $xpath = $this->xpath($this->render('sobredotacion', $data));
        $row = $xpath->query('//section[@aria-labelledby="revision-sobrecargas-titulo"]//tr[@data-revision-row]')->item(0);
        $this->assertNotNull($row);
        $this->assertSame('sobrecarga', $row->getAttribute('data-revision-state'));
        $this->assertSame('plan_estudio', $row->getAttribute('data-revision-block'));
        $this->assertSame('5 h', trim($xpath->query('./td[4]', $row)->item(0)->textContent));
        $this->assertSame(1, $xpath->query('.//a[contains(@href,"revision_docentes_state=sobrecarga")]', $row)->length);
        $docentes = $this->xpath($this->render('docentes', $data));
        $this->assertSame('plan_estudio', $docentes->query('//tr[@data-revision-row]')->item(0)->getAttribute('data-revision-block'));
    }

    public function test_etiquetas_de_filtros_y_columnas_son_unicas_y_los_detalles_conservan_conversion(): void
    {
        foreach (['docentes', 'sobredotacion'] as $tab) {
            $xpath = $this->xpath($this->render($tab, $this->data()));
            foreach ($xpath->query('//*[@data-revision-filter]') as $control) {
                $id = $control->getAttribute('id');
                $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
                $this->assertSame(1, $xpath->query('//label[@for="'.$id.'"]')->length);
            }
        }
        $html = $this->render('docentes', $this->data());
        $this->assertStringContainsString('Contrato 65/35', $html);
        $this->assertStringContainsString('Contrato 60/40', $html);
        $this->assertStringContainsString('Contrato ocupado, incluye reservas', $html);
        if (getenv('DOTACION_UI_PREVIEW') === '1') {
            foreach (['docentes', 'sobredotacion'] as $tab) $this->preview($tab);
        }
    }

    private function render(string $tab, array $data): string
    {
        return view('admin.dotacion-establecimiento.partials._'.$tab, $data)->render();
    }

    private function data(): array
    {
        $ee = new Establecimiento(['rbd' => '99999', 'nombre_establecimiento' => 'Establecimiento sintético']);
        $ee->id = 99999;
        $base = [
            'rut' => '99000001-K', 'nombre' => 'Docente Básica de prueba', 'titulo' => 'Pedagogía en Educación Básica',
            'horas_contrato' => 44, 'horas_contrato_base' => 44, 'horas_asignadas_total' => 40, 'horas_reservadas_no_normativas' => 3,
            'horas_aula' => 29, 'horas_contrato_65_35' => 34, 'horas_funciones_total' => 3, 'horas_contrato_pie' => 2,
            'horas_planta' => 44, 'horas_contrata' => 0, 'diferencia' => 4, 'redondeo_parvularia' => 0,
            'estado_cuadratura' => ['key' => 'faltan_horas', 'label' => 'Faltan horas', 'class' => 'text-bg-warning'],
            'prioridad_2027_label' => '2. Titular · Avanzado', 'funcion' => 'DOCENTE', 'niveles_declarados' => 'Básica',
            'tipo_contrato' => 'PLANTA', 'estamento' => 'DOCENTE', 'financiamiento' => 'General',
            'tramo' => 'Avanzado', 'fecha_antiguedad' => null, 'mes' => 8, 'anio' => 2026,
        ];
        $fila = ['rut' => $base['rut'], 'nombre' => $base['nombre'], 'funcion' => 'DOCENTE', 'tipo_contrato' => 'PLANTA',
            'horas_contrato_categoria' => 44, 'horas_sobredotacion_total' => 4, 'horas_sobredotacion_planta' => 4, 'horas_sobredotacion_contrata' => 0];
        return [
            'establecimiento' => $ee, 'anio' => 2027, 'errors' => new ViewErrorBag,
            'canViewSobredotacion' => true, 'canManageDocenteExclusiones' => false,
            'justificacionesSobredotacionTableReady' => true, 'canManageJustificacionesSobredotacion' => true,
            'docentes' => collect([$base, array_replace($base, ['rut' => '99000002-K', 'nombre' => 'Educadora de prueba',
                'titulo' => 'Pedagogía en Educación de Párvulos', 'horas_contrato' => 43, 'horas_contrato_base' => 43,
                'horas_planta' => 43, 'horas_asignadas_total' => 42.63, 'horas_reservadas_no_normativas' => 0,
                'horas_contrato_pie' => 0, 'diferencia' => 0.37, 'redondeo_parvularia' => 0.37])]),
            'resumen' => ['horas_contrato_docentes_aula_general' => 100, 'horas_contrato_docentes_parvularia' => 43, 'horas_contrato_docente_pie' => 44],
            'proceso2027' => ['aplica' => true, 'bloques' => [
                'bloque_1' => ['asignadas' => 90, 'reservadas_no_normativas' => 10, 'maximo' => 95, 'asignadas_asistentes_obligatorias' => 6],
                'bloque_2' => ['asignadas' => 42.63, 'redondeo_parvularia' => 0.37, 'maximo' => 58],
                'bloque_3' => ['asignadas' => 44, 'maximo' => 44],
            ]],
            'sobredotacion' => ['aula' => [], 'protegidos' => collect(), 'vacantes_por_bloque' => [
                'plan_estudio' => ['items' => collect([$fila]), 'horas_total' => 14, 'horas_planta' => 14, 'horas_contrata' => 0],
            ]],
        ];
    }

    private function preview(string $tab): void
    {
        $disk = Storage::disk('local');
        Vite::useHotFile($disk->path('ui-views/nonexistent-hot'));
        preg_match('/<style>.*?<\/style>/s', file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php')), $styles);
        $disk->put('ui-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">@vite([\'resources/js/app.js\', \'resources/scss/app.scss\'])<link rel="stylesheet" href="/vendor/bootstrap-icons/font/bootstrap-icons.css">@stack(\'styles\')</head><body><main class="slep-content p-3">@yield(\'content\')</main>@stack(\'scripts\')</body></html>');
        $disk->put('ui-views/revision.blade.php', '@extends(\'layouts.app\') @push(\'styles\') @vite([\'resources/css/dotacion-establecimiento.css\', \'resources/js/dotacion-asignacion.js\']) @endpush @section(\'content\') <div class="dotacion-workspace">@include(\'admin.dotacion-establecimiento.partials._\'.$tab)</div> @endsection');
        $this->app['view']->getFinder()->prependLocation($disk->path('ui-views'));
        $dir = base_path('.codex_work/dotacion-usabilidad-preview');
        if (! is_dir($dir)) mkdir($dir, 0777, true);
        $html = view('revision', ['tab' => $tab] + $this->data())->render();
        $html = str_replace('</head>', ($styles[0] ?? '').'</head>', $html);
        file_put_contents($dir.'/'.$tab.'.html', str_replace('http://localhost/build/', '/build/', $html));
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }
}
