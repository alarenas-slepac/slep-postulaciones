<?php

namespace Tests\Feature;

use App\Models\DotacionEstablecimientoConfiguracion;
use App\Models\DotacionFuncionEstablecimiento;
use App\Models\Establecimiento;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionConfiguracionUsabilidadTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $disk = Storage::disk('local');
        Vite::useHotFile($disk->path('ui-views/nonexistent-hot'));
        $disk->put('ui-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">@vite([\'resources/js/app.js\', \'resources/scss/app.scss\'])<link rel="stylesheet" href="/vendor/bootstrap-icons/font/bootstrap-icons.css">@stack(\'styles\')</head><body><main class="slep-content p-3">@yield(\'content\')</main>@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation($disk->path('ui-views'));
    }

    public function test_la_siguiente_etapa_y_sus_enlaces_respetan_el_anio_y_los_roles(): void
    {
        $data = $this->data();
        foreach (['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'supervisor_plani'] as $role) {
            $data['activeRole'] = $role;
            $html = view('admin.dotacion-establecimiento.partials._proceso_etapas', $data)->render();
            $xpath = $this->xpath($html);
            $this->assertSame(6, $xpath->query('//nav//li')->length);
            $this->assertSame(1, $xpath->query('//*[@aria-current="step"]')->length);
            $this->assertStringContainsString('Planes de estudio', $xpath->query('//*[@aria-current="step"]')->item(0)->textContent);
            $this->assertSame(2, $xpath->query('//a[contains(@href,"establecimiento-planes")]')->length);
            foreach ($xpath->query('//a[contains(@href,"establecimiento-planes")]') as $link) {
                parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
                $this->assertSame('2027', $query['anio']);
                $this->assertSame('99999', $query['establecimiento_id']);
            }
        }
        $data['activeRole'] = 'coordinador_gdp';
        $readonly = $this->xpath(view('admin.dotacion-establecimiento.partials._proceso_etapas', $data)->render());
        $this->assertSame(0, $readonly->query('//a[contains(@href,"establecimiento-planes")]')->length);
        $this->assertStringContainsString('Solicite la configuración', $readonly->document->textContent);
        foreach ($data['proceso2027']['pasos'] as &$step) $step['completo'] = true;
        unset($step);
        $complete = $this->xpath(view('admin.dotacion-establecimiento.partials._proceso_etapas', $data)->render());
        $this->assertSame(0, $complete->query('//*[@aria-current="step"]')->length);
        $this->assertStringContainsString('Cobertura obligatoria completa', $complete->document->textContent);
        $data['proceso2027']['aplica'] = false;
        $this->assertSame('', trim(view('admin.dotacion-establecimiento.partials._proceso_etapas', $data)->render()));
    }

    public function test_los_bloqueos_muestran_maximos_faltantes_y_necesidades_sin_alterar_la_cobertura(): void
    {
        $data = $this->data();
        $original = $data['proceso2027'];
        $html = view('admin.dotacion-establecimiento.partials._proceso_2027', $data)->render();
        $xpath = $this->xpath($html);
        $this->assertStringContainsString('falta definir el máximo autorizado', $xpath->document->textContent);
        $this->assertStringContainsString('el máximo autorizado de 40 h es inferior a las 58 h necesarias', $xpath->document->textContent);
        $this->assertSame(1, $xpath->query('//a[contains(@href,"asig_pendientes=1")]')->length);
        foreach (['dotacion-decision-combinacion', 'dotacion-maximos', 'dotacion-definicion-normativas'] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
        }
        foreach ($xpath->query('//input[@name] | //select[@name] | //textarea[@name]') as $field) {
            if ($field->getAttribute('type') === 'hidden') continue;
            $id = $field->getAttribute('id');
            $this->assertNotSame('', $id);
            $this->assertSame(1, $xpath->query('//label[@for="'.$id.'"]')->length);
        }
        $this->assertSame($original, $data['proceso2027']);
        $data['canManageProceso2027Maximos'] = false;
        $restricted = $this->xpath(view('admin.dotacion-establecimiento.partials._proceso_2027', $data)->render());
        $this->assertSame(0, $restricted->query('//input[starts-with(@name,"max_horas_")]')->length);
        $this->assertSame(0, $restricted->query('//a[contains(@href,"maximos-bloques")]')->length);
    }

    public function test_las_asociaciones_conservan_cursos_titulos_y_error_solo_en_la_asignatura_del_anio(): void
    {
        $data = $this->data();
        $data['proceso2027']['pasos']['planes']['completo'] = true;
        $data['proceso'] = $data['proceso2027'];
        session()->flashInput(['anio' => 2027, 'asignatura_key' => 'basica-artes', 'docentes' => ['99000002K']]);
        $data['errors'] = (new ViewErrorBag)->put('default', new MessageBag(['docentes' => 'Error sintético de asociación.']));
        $xpath = $this->xpath(view('admin.dotacion-establecimiento.partials._docentes_subsector', $data)->render());
        $this->assertSame(1, $xpath->query('//form[@data-catalog-error="1"]')->length);
        $this->assertSame('subsector-form-basica-artes', $xpath->query('//form[@data-catalog-error="1"]')->item(0)->getAttribute('id'));
        $this->assertSame(1, $xpath->query('//*[@id="dotacion-subsector-basica" and contains(@class,"show")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="subsector-docentes-basica-artes"]/option[@selected and @value="99000002K"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="subsector-docentes-parvularia-artes"]/option[@selected and @value="99000001K"]')->length);
        $this->assertStringContainsString('1° Básico A · 2° Básico A', $xpath->document->textContent);
        $row = $xpath->query('//*[@id="subsector-form-basica-artes"]')->item(0);
        $this->assertStringContainsString('Pedagogía en Educación Básica', $row->getAttribute('data-catalog-search'));
        $this->assertSame(2, $xpath->query('//*[@id="subsector-docentes-basica-artes"]/option')->length);
        $this->assertSame(3, $xpath->query('//*[@id="subsector-docentes-parvularia-artes"]/option')->length);
        session()->flashInput(['anio' => 2026, 'asignatura_key' => 'basica-artes', 'docentes' => ['99000002K']]);
        $historical = $this->xpath(view('admin.dotacion-establecimiento.partials._docentes_subsector', $data)->render());
        $this->assertSame(0, $historical->query('//form[@data-catalog-error="1"]')->length);
        $this->assertSame(1, $historical->query('//*[@id="subsector-docentes-basica-artes"]/option[@selected and @value="99000001K"]')->length);
    }

    public function test_filtros_de_cargas_mantienen_cero_como_configurado_y_descargas_completas(): void
    {
        $data = $this->data();
        $data['filas'] = collect([
            ['rbd' => '99998', 'nombre' => 'EE de prueba A', 'matricula' => 10, 'max_horas_bloque_1' => 0, 'max_horas_bloque_2' => 0, 'max_horas_bloque_3' => 0],
            ['rbd' => '99999', 'nombre' => 'EE de prueba B', 'matricula' => 20, 'max_horas_bloque_1' => 100, 'max_horas_bloque_2' => null, 'max_horas_bloque_3' => 44],
        ]);
        $maximos = $this->xpath(view('admin.dotacion-funciones.maximos-carga', $data)->render());
        $this->assertSame(1, $maximos->query('//tr[@data-catalog-state="completo"]')->length);
        $this->assertSame(1, $maximos->query('//tr[@data-catalog-state="pendiente"]')->length);
        $this->assertStringContainsString('año anterior: 2026', $maximos->document->textContent);
        $this->assertSame(1, $maximos->query('//a[contains(@href,"plantilla") and contains(@href,"anio=2027")]')->length);
        $this->assertSame(1, $maximos->query('//form[@data-dotacion-save]//input[@type="file"]')->length);
        $data['filas'] = collect([
            ['rbd' => '99998', 'nombre' => 'EE de prueba A', 'matricula' => 10, 'horas' => 30, 'asignadas' => 44, 'carga_anual' => true],
            ['rbd' => '99999', 'nombre' => 'EE de prueba B', 'matricula' => 20, 'horas' => 0, 'asignadas' => 0, 'carga_anual' => false],
        ]);
        $convivencia = $this->xpath(view('admin.dotacion-funciones.convivencia-carga', $data)->render());
        $this->assertSame(1, $convivencia->query('//tr[contains(@data-catalog-state,"exceso")]')->length);
        $this->assertStringContainsString('Exceso asignado: 14 h', $convivencia->document->textContent);
        $this->assertStringContainsString('0 define cero horas', $convivencia->document->textContent);
        $data['filas'] = collect();
        $this->assertStringContainsString('No hay establecimientos', view('admin.dotacion-funciones.maximos-carga', $data)->render());
    }

    public function test_funciones_separan_estados_acciones_y_errores_de_cada_formulario(): void
    {
        $data = $this->funciones();
        session()->flashInput(['formulario' => 'observar:2', 'observacion' => 'Motivo <script>de prueba</script>']);
        $data['errors'] = (new ViewErrorBag)->put('default', new MessageBag(['observacion' => 'Corrija el motivo de prueba.']));
        $xpath = $this->xpath(view('admin.dotacion-funciones.show', $data)->render());
        $this->assertSame(1, $xpath->query('//tr[@data-catalog-error="1"]')->length);
        $this->assertSame('', $xpath->query('//*[@id="funcion-observar-1"]')->item(0)->getAttribute('value'));
        $this->assertSame('Motivo <script>de prueba</script>', $xpath->query('//*[@id="funcion-observar-2"]')->item(0)->getAttribute('value'));
        $this->assertSame('Parámetro anterior de prueba', $xpath->query('//*[@id="funciones-observacion-config"]')->item(0)->textContent);
        foreach ($xpath->query('//input[not(@type="hidden")] | //textarea | //select') as $field) {
            $this->assertSame(1, $xpath->query('//label[@for="'.$field->getAttribute('id').'"]')->length);
        }
        $data['canValidate'] = false;
        $data['canEdit'] = false;
        $data['canConfigureDirectorAdp'] = false;
        $restricted = $this->xpath(view('admin.dotacion-funciones.show', $data)->render());
        $this->assertSame(0, $restricted->query('//input[@name="horas_aprobadas"]')->length);
        $this->assertSame(0, $restricted->query('//form[.//input[@name="_method"]]')->length);
        $data['canEdit'] = true;
        $data['proceso2027'] = ['aplica' => true, 'funciones_no_normativas_habilitadas' => false];
        $blocked = $this->xpath(view('admin.dotacion-funciones.show', $data)->render());
        $this->assertSame(0, $blocked->query('//*[@id="funciones-form-5-nombre_funcion"]')->length);
        $this->assertSame(1, $blocked->query('//a[contains(@href,"#dotacion-etapas-titulo")]')->length);
    }

    public function test_anio_historico_no_bloquea_declaraciones_y_recupera_el_tipo_solo_en_su_formulario(): void
    {
        $data = $this->funciones();
        $data['anio'] = 2026;
        session()->flashInput(['formulario' => 'otra-funcion', 'tipo' => 'orientador', 'nombre_funcion' => 'Nombre de prueba', 'horas_declaradas' => 8]);
        $data['errors'] = (new ViewErrorBag)->put('default', new MessageBag(['descripcion_funcion' => 'Revise la descripción de prueba.', 'horas_declaradas' => 'Revise las horas de prueba.']));
        $xpath = $this->xpath(view('admin.dotacion-funciones.show', $data)->render());
        $this->assertSame(1, $xpath->query('//*[@id="funciones-form-6-tipo"]/option[@selected and @value="orientador"]')->length);
        $this->assertSame('', $xpath->query('//*[@id="funciones-form-5-nombre_funcion"]')->item(0)->getAttribute('value'));
        $this->assertSame('Nombre de prueba', $xpath->query('//*[@id="funciones-form-6-nombre_funcion"]')->item(0)->getAttribute('value'));
        $this->assertSame(2, $xpath->query('//*[@data-save-field-error]')->length);
        $this->assertSame(0, $xpath->query('//textarea//*[@data-save-field-error]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="funciones-form-6-descripcion_funcion-error"]')->length);
        $this->assertSame(0, $xpath->query('//a[contains(@href,"#dotacion-etapas-titulo")]')->length);
    }

    public function test_cursos_combinados_tienen_etiquetas_unicas_y_conservan_sus_acciones(): void
    {
        $data = $this->data();
        $courses = collect([['id' => 1, 'label' => 'NT1 A', 'matricula' => 10, 'disponible' => true], ['id' => 2, 'label' => 'NT2 A', 'matricula' => 10, 'disponible' => true]]);
        $group = ['id' => 1, 'nombre' => 'Grupo sintético', 'activo' => true, 'proporcion' => 'nt_jec', 'proporcion_label' => 'NT con JEC', 'observacion' => 'Antecedente sintético', 'miembros' => $courses->all(), 'asignaturas' => [['curso_combinado_asignatura_key' => 'lenguajes', 'titulo' => 'Lenguajes Artísticos', 'horas_plan_requeridas' => 4]]];
        $data['canManageCursosCombinados'] = true;
        $data['cursosCombinados'] = ['tables_ready' => true, 'cursos_disponibles' => $courses, 'grupos' => [$group]];
        $xpath = $this->xpath(view('admin.dotacion-establecimiento.partials._cursos_combinados', $data)->render());
        foreach ($xpath->query('//input[not(@type="hidden")] | //textarea | //select') as $field) {
            $id = $field->getAttribute('id');
            $this->assertNotSame('', $id);
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
            $this->assertSame(1, $xpath->query('//label[@for="'.$id.'"]')->length);
        }
        $this->assertSame(2, $xpath->query('//form[@data-dotacion-save]')->length);
        $this->assertSame(1, $xpath->query('//form[@onsubmit]//input[@name="_method" and @value="DELETE"]')->length);
        $data['canManageCursosCombinados'] = false;
        $this->assertSame(0, $this->xpath(view('admin.dotacion-establecimiento.partials._cursos_combinados', $data)->render())->query('//form')->length);
    }

    public function test_previsualizacion_sintetica_optativa_del_proceso_y_catalogo_amplio(): void
    {
        $data = $this->data();
        $data['proceso2027']['pasos']['planes']['completo'] = true;
        $html = view('admin.dotacion-establecimiento.partials._proceso_2027', $data)->render();
        $this->assertStringContainsString('Docentes por asignatura', $html);
        if (getenv('DOTACION_UI_PREVIEW') !== '1') return;
        $disk = Storage::disk('local');
        $disk->put('ui-views/configuracion.blade.php', '@extends(\'layouts.app\') @push(\'styles\') @vite([\'resources/css/dotacion-establecimiento.css\', \'resources/js/dotacion-asignacion.js\']) @endpush @section(\'content\') <div class="dotacion-workspace">@include(\'admin.dotacion-establecimiento.partials._proceso_etapas\') <button class="btn btn-outline-primary rounded-pill mb-3" data-bs-toggle="collapse" data-bs-target="#dotacion-config-panel" aria-controls="dotacion-config-panel" aria-expanded="false">Configuración y avance</button><div id="dotacion-config-panel" class="collapse">@include(\'admin.dotacion-establecimiento.partials._proceso_2027\')</div></div> @endsection');
        $this->preview('configuracion', view('configuracion', $data)->render());
        $large = $data;
        $large['proceso2027']['docentes_subsector']['grupos'] = collect(['basica' => ['label' => 'Educación Básica', 'asignaturas' => collect(range(1, 100))->map(fn ($id) => ['key' => 'prueba-'.$id, 'nivel' => 'basica', 'nombre' => 'Asignatura de prueba '.$id, 'horas_aula' => 4, 'cursos' => ['1° Básico A', '2° Básico A'], 'docentes' => ['99000001K'], 'completo' => true])]]);
        $large['proceso2027']['docentes_subsector']['docentes'] = collect(range(1, 60))->map(fn ($id) => ['rut' => '99'.str_pad((string) $id, 6, '0', STR_PAD_LEFT).'-K', 'nombre' => 'Docente sintético '.$id, 'titulo' => 'Pedagogía en Educación Básica']);
        $this->preview('catalogo-amplio', view('configuracion', $large)->render());
        $this->preview('funciones', view('admin.dotacion-funciones.show', $this->funciones())->render());
    }

    private function data(): array
    {
        $ee = new Establecimiento(['rbd' => '99999', 'nombre_establecimiento' => 'Establecimiento sintético']);
        $ee->id = 99999;
        $labels = ['planes' => 'Planes de estudio', 'subsectores' => 'Docentes por asignatura', 'combinaciones' => 'Combinación de cursos', 'normativas' => 'Funciones normativas', 'maximos' => 'Máximos por bloque', 'asignacion' => 'Asignación obligatoria'];
        $block = ['label' => 'Plan general', 'horas_normativas_potenciales' => 0, 'maximo' => null, 'titulares_asignadas' => 0, 'contrata_asignadas' => 0, 'sin_padron_asignadas' => 0, 'asignadas' => 0, 'asignadas_obligatorias' => 0, 'pendientes' => 10, 'saldo_maximo' => null, 'requeridas' => 10, 'maximo_insuficiente' => false];
        return [
            'establecimiento' => $ee, 'anio' => 2027, 'tab' => 'sobredotacion', 'activeRole' => 'admin',
            'errors' => new ViewErrorBag, 'canManageProceso2027Maximos' => true, 'canConfigureFuncionesNormativas2027' => true,
            'proceso2027' => ['aplica' => true, 'pasos' => collect($labels)->map(fn ($label) => ['label' => $label, 'completo' => false])->all(),
                'funciones_no_normativas_habilitadas' => false, 'funciones_normativas' => [],
                'bloques' => ['bloque_1' => $block, 'bloque_2' => array_replace($block, ['label' => 'Educación Parvularia', 'maximo' => 40, 'requeridas' => 58, 'maximo_insuficiente' => true])],
                'docentes_subsector' => ['disponible' => true, 'grupos' => collect(['parvularia' => ['label' => 'Educación Parvularia', 'asignaturas' => collect([['key' => 'parvularia-artes', 'nivel' => 'parvularia', 'nombre' => 'Lenguajes Artísticos', 'horas_aula' => 4, 'cursos' => ['NT1 A', 'NT2 A'], 'docentes' => ['99000001K'], 'completo' => true]])], 'basica' => ['label' => 'Educación Básica', 'asignaturas' => collect([['key' => 'basica-artes', 'nivel' => 'basica', 'nombre' => 'Artes Visuales', 'horas_aula' => 4, 'cursos' => ['1° Básico A', '2° Básico A'], 'docentes' => ['99000001K'], 'completo' => true]])]]),
                    'docentes' => collect([['rut' => '99000001-K', 'nombre' => 'Docente sintético uno', 'titulo' => 'Pedagogía en Educación Básica'], ['rut' => '99000002-K', 'nombre' => 'Docente sintético dos', 'titulo' => 'Pedagogía en Educación Física'], ['rut' => 'CUPONT1', 'nombre' => 'Cupo de prueba', 'cupo_contrata_id' => 1, 'cupo_bloque' => 'parvularia']])]],
        ];
    }

    private function funciones(): array
    {
        $base = $this->data();
        $base['proceso2027'] = ['aplica' => false];
        $manuales = collect([1, 2])->map(function ($id) {
            $f = new DotacionFuncionEstablecimiento(['nombre_funcion' => 'Función sintética '.$id, 'categoria' => 'tecnico_pedagogica', 'horas_declaradas' => 3, 'estado' => 'en_revision']);
            $f->id = $id;
            return $f;
        });
        return $base + ['volverUrl' => '/prueba', 'accionesContexto' => [], 'contexto' => ['matricula_total' => 20, 'cursos_nee' => 1],
            'resumen' => ['horas_totales' => 6], 'config' => new DotacionEstablecimientoConfiguracion(['director_adp' => false, 'observacion' => 'Parámetro anterior de prueba']),
            'categorias' => ['tecnico_pedagogica' => 'Funciones técnico-pedagógicas'], 'bloquesConsolidados' => ['tecnico_pedagogica' => 'Funciones técnico-pedagógicas'],
            'sugerencias' => [], 'manuales' => ['tecnico_pedagogica' => $manuales], 'canEdit' => true, 'canValidate' => true, 'canConfigureDirectorAdp' => true];
    }

    private function preview(string $name, string $html): void
    {
        preg_match('/<style>.*?<\/style>/s', file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php')), $styles);
        $dir = base_path('.codex_work/dotacion-usabilidad-preview');
        if (! is_dir($dir)) mkdir($dir, 0777, true);
        $html = str_replace('</head>', ($styles[0] ?? '').'</head>', $html);
        file_put_contents($dir.'/'.$name.'.html', str_replace('http://localhost/build/', '/build/', $html));
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }
}
