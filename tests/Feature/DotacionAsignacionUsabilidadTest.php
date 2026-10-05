<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\DotacionDocenteAsignacion;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionAsignacionUsabilidadTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // El render directo necesita la misma sesión que adjunta el middleware web.
        $this->app['request']->setLaravelSession($this->app['session.store']);
    }

    public function test_conserva_la_asignacion_fallida_sin_contaminar_las_otras_necesidades(): void
    {
        session()->flashInput([
            'anio' => 2026, 'tipo_asignacion' => 'plan_estudio', 'necesidad_key' => 'plan:uno',
            'docente_rut' => '99000001-K', 'estamento_cobertura' => 'docente',
            'horas_plan_pedagogicas' => '1.25', 'observacion' => 'Antecedente <script>prueba</script>',
        ]);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['horas_plan_pedagogicas' => 'Revise el saldo contractual.']));
        $xpath = $this->xpath($this->renderAsignacion($errors));
        $first = $xpath->query('//form[.//input[@name="necesidad_key" and @value="plan:uno"]]')->item(0);
        $second = $xpath->query('//form[.//input[@name="necesidad_key" and @value="plan:dos"]]')->item(0);
        $this->assertSame('1.25', $xpath->query('.//input[@name="horas_plan_pedagogicas"]', $first)->item(0)->getAttribute('value'));
        $this->assertSame('3', $xpath->query('.//input[@name="horas_plan_pedagogicas"]', $second)->item(0)->getAttribute('value'));
        $this->assertSame('99000001-K', $xpath->query('.//select[@name="docente_rut"]//option[@selected]', $first)->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('.//select[@name="docente_rut"]//option[@selected]', $second)->length);
        $this->assertSame(1, $xpath->query('.//*[@data-dotacion-form-errors]', $first)->length);
        $this->assertSame(0, $xpath->query('.//*[@data-dotacion-form-errors]', $second)->length);
        $this->assertSame(0, $xpath->query('.//script', $first)->length);
        $this->assertSame('Antecedente <script>prueba</script>', $xpath->query('.//input[@name="observacion"]', $first)->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('//details[@data-dotacion-editor and @open]')->length);
        $this->assertSame(1, $xpath->query('ancestor::details[@data-dotacion-editor and @open]', $first)->length);
        $this->assertSame(0, $xpath->query('ancestor::details[@data-dotacion-editor and @open]', $second)->length);
    }

    public function test_recupera_asistente_y_contrato_aaee_sin_cambiar_la_subvencion_fija(): void
    {
        session()->flashInput([
            'anio' => 2026, 'tipo_asignacion' => 'plan_estudio', 'necesidad_key' => 'plan:uno',
            'docente_rut' => '99000002-K', 'estamento_cobertura' => 'asistente',
            'horas_plan_pedagogicas' => '2', 'horas_contrato' => '2.5', 'subvencion' => 'PIE',
        ]);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['horas_contrato' => 'Contrato de prueba no válido.']));
        $xpath = $this->xpath($this->renderAsignacion($errors));
        $form = $xpath->query('//form[.//input[@value="plan:uno"]]')->item(0);
        $this->assertSame('asistente', $xpath->query('.//select[@name="estamento_cobertura"]/option[@selected]', $form)->item(0)->getAttribute('value'));
        $this->assertSame('99000002-K', $xpath->query('.//select[@name="docente_rut"]//option[@selected]', $form)->item(0)->getAttribute('value'));
        $contract = $xpath->query('.//input[@name="horas_contrato"]', $form)->item(0);
        $this->assertSame('2.5', $contract->getAttribute('value'));
        $this->assertFalse($contract->hasAttribute('disabled'));
        $this->assertSame('General', $xpath->query('.//input[@name="subvencion"]', $form)->item(0)->getAttribute('value'));
    }

    public function test_un_valor_antiguo_no_agrega_personas_al_selector_y_otro_anio_no_restaura_el_formulario(): void
    {
        session()->flashInput(['anio' => 2027, 'tipo_asignacion' => 'plan_estudio', 'necesidad_key' => 'plan:uno', 'docente_rut' => 'PERSONA-NO-ELEGIBLE', 'horas_plan_pedagogicas' => '100']);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['docente_rut' => 'Persona no elegible.']));
        $xpath = $this->xpath($this->renderAsignacion($errors));
        $this->assertSame(0, $xpath->query('//option[@value="PERSONA-NO-ELEGIBLE"]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-dotacion-form-errors]')->length);
        $this->assertSame('3', $xpath->query('//form[.//input[@value="plan:uno"]]//input[@name="horas_plan_pedagogicas"]')->item(0)->getAttribute('value'));
    }

    public function test_los_filtros_identifican_pendientes_y_las_etiquetas_apuntan_a_campos_unicos(): void
    {
        $xpath = $this->xpath($this->renderAsignacion(new ViewErrorBag));
        $this->assertSame(2, $xpath->query('//*[@data-dotacion-need]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-dotacion-need and @data-pending="1"]')->length);
        foreach ($xpath->query('//form[contains(@class,"dotacion-assignment-form")]//input[not(@type="hidden")] | //form[contains(@class,"dotacion-assignment-form")]//select') as $field) {
            $id = $field->getAttribute('id');
            $this->assertNotSame('', $id);
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
            $this->assertSame(1, $xpath->query('//label[@for="'.$id.'"]')->length);
        }
    }

    public function test_las_asignaciones_registradas_comparten_el_identificador_de_su_necesidad(): void
    {
        $xpath = $this->xpath($this->renderAsignacion(new ViewErrorBag));
        $key = sha1('plan:dos');
        $this->assertSame(1, $xpath->query('//tr[@data-dotacion-need="'.$key.'"]')->length);
        $related = $xpath->query('//tr[@data-dotacion-related="'.$key.'"]');
        $this->assertSame(1, $related->length);
        $this->assertStringContainsString('Docente de prueba', $related->item(0)->textContent);
        $this->assertSame(1, $xpath->query('.//button[@aria-label="Eliminar asignación de Docente de prueba"]', $related->item(0))->length);
    }

    public function test_los_editores_se_abren_a_demanda_y_conservan_las_etiquetas_del_resumen_movil(): void
    {
        $xpath = $this->xpath($this->renderAsignacion(new ViewErrorBag));
        $this->assertSame(2, $xpath->query('//details[@data-dotacion-editor]')->length);
        $this->assertSame(0, $xpath->query('//details[@data-dotacion-editor and @open]')->length);
        foreach ($xpath->query('//details[@data-dotacion-editor]') as $editor) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$editor->getAttribute('id').'"]')->length);
            $this->assertSame(1, $xpath->query('./summary', $editor)->length);
            $this->assertSame(1, $xpath->query('.//form[contains(@class,"dotacion-assignment-form")]', $editor)->length);
        }
        foreach ($xpath->query('//tr[@data-dotacion-need]') as $row) {
            $this->assertSame(7, $xpath->query('./td[@data-label and @role="cell"]', $row)->length);
            $this->assertSame('Horas aula', $xpath->query('./td[3]', $row)->item(0)->getAttribute('data-label'));
            $this->assertSame('Saldo aula', $xpath->query('./td[5]', $row)->item(0)->getAttribute('data-label'));
        }
    }

    public function test_el_avance_no_compensa_una_asignatura_pendiente_con_sobreasignacion_en_otra(): void
    {
        $data = $this->data();
        $data['asignacion']['necesidades']['plan_estudio'][1]['horas_plan_asignadas'] = 6;
        $xpath = $this->xpath($this->renderAsignacion(new ViewErrorBag, $data));
        $progress = $xpath->query('//*[@role="progressbar"]')->item(0);
        $this->assertSame('50', $progress->getAttribute('aria-valuenow'));
        $this->assertSame('100', $progress->getAttribute('aria-valuemax'));
        $this->assertStringContainsString('1 de 2 asignatura(s) pendientes', $xpath->query('//*[@data-dotacion-course]')->item(0)->textContent);
    }

    public function test_un_error_directivo_abre_solo_su_editor_y_conserva_las_unidades_de_funciones(): void
    {
        $data = $this->data();
        $function = ['tipo_asignacion' => 'funcion_directiva', 'subtipo_asignacion' => 'directiva', 'horas_plan_requeridas' => null, 'horas_contrato_requeridas' => 44];
        $data['asignacion']['necesidades']['funciones'] = [
            $function + ['key' => 'funcion:director', 'titulo' => 'Director(a) ADP', 'dotacion_funcion_regla_id' => 1, 'asignacion_automatica' => true],
            array_replace($function, ['key' => 'funcion:apoyo', 'titulo' => 'Apoyo', 'tipo_asignacion' => 'otra_funcion', 'horas_contrato_requeridas' => 2]),
        ];
        session()->flashInput(['anio' => 2026, 'tipo_asignacion' => 'funcion_directiva', 'necesidad_key' => 'funcion:director', 'docente_rut' => '99000001-K']);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['docente_rut' => 'Revise el contrato de prueba.']));
        $xpath = $this->xpath($this->renderAsignacion($errors, $data));
        $this->assertSame(4, $xpath->query('//details[@data-dotacion-editor]')->length);
        $open = $xpath->query('//details[@data-dotacion-editor and @open]');
        $this->assertSame(1, $open->length);
        $this->assertSame('99000001-K', $xpath->query('.//option[@selected]', $open->item(0))->item(0)->getAttribute('value'));
        $this->assertSame(2, $xpath->query('//tr[@data-section="funciones"]/td[@data-label="Saldo contrato"]')->length);
    }

    public function test_la_navegacion_precede_la_configuracion_y_los_indicadores_en_la_vista_de_trabajo(): void
    {
        $html = $this->renderPagina();
        $xpath = $this->xpath($html);
        $this->assertLessThan(strpos($html, 'id="dotacion-configuracion"'), strpos($html, '<nav class="dotacion-pill-tabs'));
        $this->assertLessThan(strpos($html, 'data-kpi-row='), strpos($html, '<nav class="dotacion-pill-tabs'));
        foreach (['dotacion-configuracion', 'dotacion-indicadores'] as $id) {
            $this->assertSame('collapse', $xpath->query('//*[@id="'.$id.'"]')->item(0)->getAttribute('class'));
        }
        $current = $xpath->query('//nav[@aria-label="Secciones de dotación"]//a[@aria-current="page"]');
        $this->assertSame(1, $current->length);
        $this->assertStringContainsString('tab=asignacion', $current->item(0)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//nav//a[contains(@href,"tab=sobredotacion")]')->length);
        $this->assertSame(1, $xpath->query('//a[contains(@href,"dotacion-funciones")]')->length);

        // Vista local opcional para comprobar la interfaz: solo datos sintéticos.
        if (getenv('DOTACION_UI_PREVIEW') === '1') {
            $dir = base_path('.codex_work/dotacion-usabilidad-preview');
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            file_put_contents($dir.'/preview.html', $html);
            session()->flashInput(['anio' => 2026, 'tipo_asignacion' => 'plan_estudio', 'necesidad_key' => 'plan:uno', 'docente_rut' => '99000001-K', 'horas_plan_pedagogicas' => '1.25', 'estamento_cobertura' => 'docente', 'observacion' => 'Observación de prueba']);
            $errors = (new ViewErrorBag)->put('default', new MessageBag(['horas_plan_pedagogicas' => 'Mensaje de validación sintético: revise las horas.']));
            file_put_contents($dir.'/error.html', $this->renderPagina($errors));
        }
    }

    private function renderPagina(?ViewErrorBag $errors = null): string
    {
        $disk = Storage::disk('local');
        // La vista sintética utiliza el build, nunca un servidor Vite abierto por el usuario.
        \Illuminate\Support\Facades\Vite::useHotFile($disk->path('ui-views/nonexistent-hot'));
        $disk->put('ui-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">@vite([\'resources/js/app.js\', \'resources/scss/app.scss\'])<link rel="stylesheet" href="/vendor/bootstrap-icons/font/bootstrap-icons.css">@stack(\'styles\')</head><body><main class="slep-content p-3">@yield(\'content\')</main>@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation($disk->path('ui-views'));
        return view('admin.dotacion-establecimiento.show', $this->data() + [
            'activeRole' => 'admin', 'tab' => 'asignacion', 'resumen' => [], 'cursos' => [],
            'alertas' => [], 'canViewSobredotacion' => false, 'errors' => $errors ?? new ViewErrorBag,
        ])->render();
    }

    private function renderAsignacion(ViewErrorBag $errors, ?array $data = null): string
    {
        return view('admin.dotacion-establecimiento.partials._asignacion', ($data ?? $this->data()) + ['errors' => $errors])->render();
    }

    private function data(): array
    {
        $ee = new Establecimiento(['rbd' => '99999', 'nombre_establecimiento' => 'Establecimiento de prueba']);
        $ee->id = 99999;
        $base = [
            'tipo_asignacion' => 'plan_estudio', 'subtipo_asignacion' => 'plan_comun', 'bloque' => 'Plan común',
            'curso_label' => '1° Básico A', 'horas_plan_requeridas' => 3, 'horas_contrato_requeridas' => 4,
            'horas_plan_asignadas' => 0, 'horas_plan_pendientes' => 3,
        ];
        $docentes = collect([[
            'rut' => '99000001-K', 'rut_normalizado' => '99000001K', 'nombre' => 'Docente de prueba',
            'titulo' => 'Pedagogía en Educación Básica', 'horas_contrato' => 44, 'horas_asignadas_total' => 40,
            'horas_titulares_disponibles' => 4, 'horas_contrata_disponibles' => 0,
        ]]);
        $asignacionRegistrada = new DotacionDocenteAsignacion([
            'anio' => 2026, 'docente_rut' => '99000001-K', 'docente_nombre' => 'Docente de prueba',
            'tipo_asignacion' => 'plan_estudio', 'asignatura_nombre' => 'Artes Visuales',
            'subvencion' => 'General', 'horas_plan_pedagogicas' => 3, 'horas_contrato' => 4,
        ]);
        $asignacionRegistrada->id = 99999;
        return [
            'establecimiento' => $ee, 'anio' => 2026, 'docentes' => $docentes,
            'asignacion' => [
                'docentes' => $docentes,
                'asistentes' => [['rut' => '99000002-K', 'nombre' => 'Asistente de prueba', 'funcion' => 'Asistente', 'horas_contrato' => 44]],
                'necesidades' => ['plan_estudio' => [
                    $base + ['key' => 'plan:uno', 'titulo' => 'Matemática'],
                    array_replace($base, ['key' => 'plan:dos', 'titulo' => 'Artes Visuales', 'horas_plan_asignadas' => 3, 'horas_plan_pendientes' => 0, 'asignaciones' => [$asignacionRegistrada]]),
                ]],
            ],
        ];
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        return new \DOMXPath($dom);
    }
}
