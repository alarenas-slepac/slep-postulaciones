<?php

namespace Tests\Feature;

use App\Models\DotacionEstablecimientoConfiguracion;
use App\Models\Establecimiento;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionMatriculaVisibilidadTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $disk = Storage::disk('local');
        $disk->put('matricula-views/layouts/app.blade.php', '<!doctype html><html><head>@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation($disk->path('matricula-views'));
    }

    public function test_el_directivo_no_recibe_matricula_en_los_listados_incluso_sin_resultados(): void
    {
        foreach (['dotacion-establecimiento' => 9, 'dotacion-funciones' => 12] as $module => $columns) {
            foreach ([true, false] as $withRows) {
                $data = $this->data('funcionario_directivo_estab');
                $ee = $data['establecimiento'];
                $ee->dotacion_establecimiento_resumen = $ee->dotacion_resumen = $data['resumen'];
                $data['establecimientos'] = new LengthAwarePaginator($withRows ? [$ee] : [], $withRows ? 1 : 0, 20);
                $html = view('admin.'.$module.'.index', $data)->render();
                $this->assertMatriculaGeneralAbsent($html);
                $this->assertStringNotContainsString('>Matrícula', $html);
                $this->assertTableColumns($html, '//table', $columns);
                if ($module === 'dotacion-establecimiento') {
                    $this->assertStringContainsString('col-xl-4 col-md-6', $html);
                    $this->assertStringContainsString('Horas contrato docentes', $html);
                }
            }
        }
    }

    public function test_el_resumen_directivo_muestra_matricula_por_nivel_y_grupo_sin_el_total_general(): void
    {
        $html = view('admin.dotacion-establecimiento.show', $this->data('funcionario_directivo_estab'))->render();
        $this->assertMatriculaGeneralAbsent($html);
        $xpath = $this->xpath($html);
        $this->assertSame(2, $xpath->query('//*[@data-kpi-row="generales"]/*')->length);
        $this->assertStringContainsString('row-cols-md-2', $html);
        $this->assertTableColumns($html, '//table[thead/tr/th[1][normalize-space(.)="Nivel"]]', 10);
        $this->assertStringContainsString('Matrícula 2027', $html);
        $this->assertSame('2.345', trim($xpath->query('//tr[td[1][normalize-space(.)="Básica sintética"]]/td[2]')->item(0)->textContent));
        $this->assertSame('10.000', trim($xpath->query('//tr[td[1]//div[normalize-space(.)="Grupo sintético"]]/td[2]')->item(0)->textContent));
        $this->assertSame('', trim($xpath->query('//tfoot/tr[td[1][normalize-space(.)="Total establecimiento"]]/td[2]')->item(0)->textContent));
        $this->assertStringContainsString('Grupo sintético', $html);
        $this->assertStringContainsString('Libre disposición NT1/NT2 de otros docentes', $html);
        $this->assertStringContainsString('Horas contrato docentes', $html);
    }

    public function test_la_tabla_de_cursos_vacia_tambien_ajusta_sus_columnas(): void
    {
        $data = $this->data('funcionario_directivo_estab');
        $data['cursos'] = [];
        $html = view('admin.dotacion-establecimiento.show', $data)->render();
        $this->assertMatriculaGeneralAbsent($html);
        $this->assertTableColumns($html, '//table[thead/tr/th[1][normalize-space(.)="Nivel"]]', 10);
    }

    public function test_el_pdf_directivo_muestra_detalle_de_matricula_y_omite_el_total_general(): void
    {
        $html = view('admin.dotacion-establecimiento.pdf', $this->data('funcionario_directivo_estab'))->render();
        $this->assertMatriculaGeneralAbsent($html);
        $this->assertTableColumns($html, '//table[thead/tr/th[1][normalize-space(.)="Nivel"]]', 10);
        $xpath = $this->xpath($html);
        $summary = $xpath->query('//table[contains(@class,"summary")][1]')->item(0);
        $this->assertSame(0, $xpath->query('.//th[normalize-space(.)="Matrícula"]', $summary)->length);
        $this->assertSame('2.345', trim($xpath->query('//tr[td[1][normalize-space(.)="Básica sintética"]]/td[2]')->item(0)->textContent));
        $this->assertSame('10.000', trim($xpath->query('//tr[td[1]/strong[normalize-space(.)="Grupo sintético"]]/td[2]')->item(0)->textContent));
        $this->assertSame('', trim($xpath->query('//tr[td[1][normalize-space(.)="Total establecimiento"]]/td[2]')->item(0)->textContent));
        $this->assertStringContainsString('Grupo sintético', $html);
        $this->assertStringContainsString('Contrato', $html);
    }

    public function test_se_muestra_matricula_por_curso_al_crear_o_editar_cursos_combinados(): void
    {
        foreach (['funcionario_directivo_estab', 'admin'] as $role) {
            $data = $this->data($role);
            $courses = [
                ['id' => 91, 'label' => 'NT1 sintético', 'matricula' => 23456, 'disponible' => true],
                ['id' => 92, 'label' => 'NT2 sintético', 'matricula' => 34567, 'disponible' => true],
            ];
            $data['cursosCombinados'] = [
                'tables_ready' => true, 'cursos_disponibles' => $courses,
                'grupos' => [['id' => 99, 'nombre' => 'Grupo sintético', 'activo' => true, 'proporcion' => 'auto',
                    'proporcion_label' => 'Automática', 'observacion' => '', 'miembros' => $courses, 'asignaturas' => []]],
            ];
            $data['canManageCursosCombinados'] = true;
            $html = view('admin.dotacion-establecimiento.partials._cursos_combinados', $data)->render();
            $this->assertSame(4, $this->xpath($html)->query('//input[@name="curso_ids[]"]')->length);
            $this->assertStringContainsString('NT1 sintético', $html);
            $this->assertStringContainsString('NT2 sintético', $html);
            $this->assertMatriculaGeneralAbsent($html);
            $this->assertStringContainsString('Matrícula 23456', $html);
            $this->assertStringContainsString('Matrícula 34567', $html);
        }
    }

    public function test_funciones_omite_matricula_tambien_en_fundamentos_y_atributos_de_busqueda(): void
    {
        foreach (['funcionario_directivo_estab', 'admin'] as $role) {
            $data = $this->data($role) + [
                'volverUrl' => '/prueba', 'accionesContexto' => [],
                'contexto' => ['matricula_total' => 12345, 'cursos_nee' => 2],
                'config' => new DotacionEstablecimientoConfiguracion,
                'categorias' => ['tecnico_pedagogica' => 'Técnico pedagógicas'],
                'bloquesConsolidados' => [], 'manuales' => [],
                'canEdit' => true, 'canValidate' => false, 'canConfigureDirectorAdp' => false,
                'sugerencias' => ['tecnico_pedagogica' => collect([
                    ['codigo' => 'cra', 'nombre_funcion' => 'CRA', 'horas_sugeridas' => 4,
                        'detalle' => 'Matrícula total del establecimiento: 12.345. Umbral: 300 estudiantes.'],
                    ['codigo' => 'jefe_utp', 'nombre_funcion' => 'Jefe UTP', 'horas_sugeridas' => 44,
                        'detalle' => 'Horas sugeridas según catálogo base de dotación.'],
                ])],
            ];
            $html = view('admin.dotacion-funciones.show', $data)->render();
            $this->assertStringContainsString('CRA', $html);
            $this->assertStringContainsString('Horas sugeridas según catálogo base de dotación.', $html);
            $this->assertStringContainsString('Sugerencia: 5 hrs.', $html);
            if ($role === 'funcionario_directivo_estab') {
                $this->assertMatriculaGeneralAbsent($html);
                $this->assertStringContainsString('col-md-6', $html);
            } else {
                $this->assertStringContainsString('Matrícula total', $html);
                $this->assertStringContainsString('12.345', $html);
            }
        }
    }

    public function test_los_demas_roles_conservan_los_indicadores_y_los_valores_de_matricula(): void
    {
        foreach (['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'] as $role) {
            $data = $this->data($role);
            $ee = $data['establecimiento'];
            $ee->dotacion_establecimiento_resumen = $ee->dotacion_resumen = $data['resumen'];
            $data['establecimientos'] = new LengthAwarePaginator([$ee], 1, 20);
            foreach (['dotacion-establecimiento.index', 'dotacion-establecimiento.show', 'dotacion-establecimiento.pdf', 'dotacion-funciones.index'] as $view) {
                $html = view('admin.'.$view, $data)->render();
                $this->assertStringContainsString('Matrícula', $html, $role.' '.$view);
                $this->assertStringContainsString('12.345', $html, $role.' '.$view);
                if (in_array($view, ['dotacion-establecimiento.show', 'dotacion-establecimiento.pdf'], true)) {
                    $this->assertTableColumns($html, '//table[thead/tr/th[1][normalize-space(.)="Nivel"]]', 10);
                }
            }
        }
    }

    private function assertMatriculaGeneralAbsent(string $html): void
    {
        foreach (['12.345', '12345', '>Matrícula total', '>Matrícula página', 'no puede ver', 'no puedes ver', 'matrícula restringida'] as $text) {
            $this->assertStringNotContainsString($text, $html);
        }
    }

    private function assertTableColumns(string $html, string $query, int $columns): void
    {
        $xpath = $this->xpath($html);
        $tables = $xpath->query($query);
        $this->assertGreaterThan(0, $tables->length);
        foreach ($tables as $table) {
            foreach ($xpath->query('./thead/tr | ./tbody/tr | ./tfoot/tr', $table) as $row) {
                $count = 0;
                foreach ($xpath->query('./td | ./th', $row) as $cell) {
                    $count += max(1, (int) $cell->getAttribute('colspan'));
                }
                $this->assertSame($columns, $count, trim($row->textContent));
            }
        }
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }

    private function data(string $role): array
    {
        $ee = new Establecimiento(['rbd' => '99999', 'nombre_establecimiento' => 'Establecimiento sintético', 'comuna' => 'Comuna sintética']);
        $ee->id = 99999;
        $totals = ['matricula' => 12345, 'cursos' => 2, 'horas' => 60, 'horas_contrato_equivalente' => 70,
            'trabajo_colaborativo_pie' => 6, 'contrato_mas_trabajo_colaborativo_pie' => 76];
        $level = ['label' => 'Básica sintética', 'matricula' => 2345, 'cursos' => 2, 'horas_variable' => false,
            'horas_por_nivel' => 30, 'total_horas' => 60, 'sin_horas_plan' => 0];
        $group = ['label' => 'Educación Básica', 'niveles' => ['basica'], 'totales' => array_replace($totals, ['matricula' => 2345])];

        return [
            'establecimiento' => $ee, 'anio' => 2027, 'activeRole' => $role, 'tab' => 'resumen',
            'q' => '', 'comuna' => '', 'comunas' => collect(), 'errors' => new ViewErrorBag,
            'resumen' => ['matricula_total' => 12345, 'cursos_total' => 2, 'docentes_total' => 1,
                'horas_contrato_docentes' => 44, 'horas_totales' => 44],
            'bloques' => [], 'alertas' => [], 'docentes' => [], 'generatedAt' => now(), 'generatedBy' => null,
            'proceso2027' => ['aplica' => false], 'canViewSobredotacion' => false,
            'proporcionExcepcionTableReady' => false, 'canManageProporcionExcepcion' => false,
            'cursos' => ['grupos' => ['basica' => $group], 'rows' => ['basica' => $level], 'totales' => $totals,
                'resumen_cursos_planes' => ['grupos' => ['basica' => $group], 'rows' => ['basica' => $level],
                    'totales' => $totals, 'totales_combinados' => array_replace($totals, ['matricula' => 10000]), 'tiene_cursos_combinados' => true,
                    'combinados' => [['label' => 'Grupo sintético', 'miembros_label' => 'NT1 + NT2', 'matricula' => 10000, 'cursos' => 2]],
                    'refuerzo_plan_general' => ['horas' => 8, 'horas_contrato_equivalente' => 9, 'contrato_mas_trabajo_colaborativo_pie' => 9]]],
        ];
    }
}
