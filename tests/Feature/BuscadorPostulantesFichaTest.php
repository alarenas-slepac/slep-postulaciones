<?php

namespace Tests\Feature;

use App\Http\Controllers\Reemplazos\BuscadorPostulantesController;
use App\Models\AreaDesempeno;
use App\Models\Commune;
use App\Models\Establecimiento;
use App\Models\PostulantProfile;
use App\Models\PostulantProfileContrato;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class BuscadorPostulantesFichaTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00', 'America/Santiago'));
        $this->withoutVite();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        Storage::disk('local')->put('ficha-ui/layouts/app.blade.php', '<!doctype html><html lang="es"><head>@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('ficha-ui'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function roles(): array
    {
        return [
            ['admin', true, true, true], ['funcionario_slep', true, true, true],
            ['coordinador_gdp', false, true, true], ['coordinador_uatp', false, false, true],
            ['funcionario_estab', false, false, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_conserva_permisos_de_vinculos_trabajo_externo_y_documentos(string $rol, bool $contratos, bool $externo, bool $documentos): void
    {
        $viewer = \Mockery::mock(User::class)->makePartial();
        $viewer->forceFill(['id' => 900]);
        $viewer->shouldReceive('hasRole')->andReturnUsing(fn ($name) => $name === $rol);
        $viewer->shouldReceive('hasAnyRole')->andReturnUsing(fn ($names) => in_array($rol, (array) $names, true));
        $viewer->shouldReceive('canModule')->with('gestion.solicitudes-reemplazo')->andReturn(false);
        $this->actingAs($viewer);
        $request = Request::create('/'); $request->setUserResolver(fn () => $viewer);
        $controller = new BuscadorPostulantesController;
        $manage = (new \ReflectionMethod($controller, 'canManageContratoLaboral'))->invoke($controller, $request);
        $external = (new \ReflectionMethod($controller, 'canManageTrabajoExterno'))->invoke($controller, $request);
        $this->assertSame($contratos, $manage); $this->assertSame($externo, $external);
        $html = $this->render(['canManageContratoLaboral' => $manage, 'canManageTrabajoExterno' => $external]);
        $xp = $this->xpath($html);
        $this->assertSame($contratos ? 1 : 0, $xp->query('//form[@data-ficha-form="contrato"]')->length);
        $this->assertSame($externo ? 1 : 0, $xp->query('//form[@data-ficha-form="externo"]')->length);
        $this->assertSame($documentos, str_contains($html, 'Ver documentos del usuario'));
        $this->assertStringContainsString('Ver perfil PDF', $html);
        $this->assertStringContainsString('Descargar perfil', $html);
    }

    public function test_ficha_muestra_datos_estados_simultaneos_y_auditoria_sin_consultas_adicionales(): void
    {
        $perfil = $this->profile();
        $perfil->user->forceFill(['trabaja_en_otro_lugar' => true, 'trabaja_en_otro_lugar_observacion' => 'Antecedente externo de prueba', 'trabaja_en_otro_lugar_marcado_en' => now()]);
        $perfil->user->setRelation('trabajoExternoMarcadoPor', $this->person('Responsable sintético'));
        $perfil->setRelation('contratosLaboralesActivos', collect([$this->contract(11), $this->contract(12)]));
        $reemplazos = collect([$this->request()]);
        DB::enableQueryLog(); DB::flushQueryLog();
        $html = $this->render(['profile' => $perfil, 'reemplazosActivos' => $reemplazos]);
        $this->assertSame([], DB::getQueryLog());
        $xp = $this->xpath($html);
        $this->assertSame(1, $xp->query('//h1')->length);
        $this->assertSame(3, $xp->query('//*[@id="ficha-laboral"]//section[contains(@class,"slep-record-state ")]')->length);
        $this->assertSame(2, $xp->query('//*[@id="ficha-laboral"]//section[contains(@class,"--warning")]//li')->length);
        foreach (['Persona sintética', 'Comuna sintética', '99000001K', '30 h', '31-12-2026', 'Responsable sintético', '28-10-2026', '2026-0099', 'Antecedente externo de prueba', '0 años'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['ficha-datos', 'ficha-laboral', 'ficha-restricciones', 'ficha-solicitudes'] as $id) {
            $this->assertSame(1, $xp->query('//nav//a[@href="#'.$id.'"]')->length);
        }
        $this->assertSame(0, $xp->query('//form')->length);
    }

    public function test_historial_desactivado_o_vencido_conserva_motivo_y_responsable(): void
    {
        $perfil = $this->profile();
        $last = $this->contract(11)->forceFill(['activo' => false, 'fecha_termino' => '2026-10-27', 'motivo_desactivacion' => 'Término anticipado sintético', 'desactivado_at' => now()])
            ->setRelation('desactivadoPor', $this->person('Responsable sintético'));
        $perfil->setRelation('ultimaVinculacionLaboral', $last);
        $html = $this->render(['profile' => $perfil]);
        foreach (['Última vinculación registrada', 'Término anticipado sintético', 'Responsable sintético', 'La fecha de término registrada ya venció.', 'Sin vinculación laboral activa registrada'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('slep-record-state--warning', $html);
        $last->forceFill(['fecha_termino' => '2026-10-28']);
        $this->assertStringNotContainsString('La fecha de término registrada ya venció.', $this->render(['profile' => $perfil]));
    }

    public function test_formularios_conservan_campos_acciones_y_valores_de_todos_los_vinculos(): void
    {
        $perfil = $this->profile()->setRelation('contratosLaboralesActivos', collect([$this->contract(11), $this->contract(12)]));
        $html = $this->render(['profile' => $perfil, 'canManageContratoLaboral' => true, 'canManageTrabajoExterno' => true]);
        $xp = $this->xpath($html);
        $this->assertSame(2, $xp->query('//*[@data-ficha-rows]//fieldset')->length);
        $this->assertSame(2, $xp->query('//form[@data-ficha-form="desactivar"]//input[@name="_method" and @value="DELETE"]')->length);
        $this->assertSame(4, $xp->query('//form//input[@name="_token"]')->length);
        $this->assertSame(4, $xp->query('//select[@name="tipo_contrato"]/option')->length);
        $this->assertSame(1, $xp->query('//input[@name="fecha_termino" and @value="2026-12-31"]')->length);
        foreach ([0, 1] as $index) {
            $this->assertSame(1, $xp->query('//select[@name="vinculaciones['.$index.'][establecimiento_id]"]//option[@selected and @value="1"]')->length);
            $this->assertSame(1, $xp->query('//input[@name="vinculaciones['.$index.'][cantidad_horas]" and @value="30"]')->length);
        }
        $ids = [];
        foreach ($xp->query('//*[@id]') as $element) { $ids[] = $element->getAttribute('id'); }
        $this->assertSame(count($ids), count(array_unique($ids)));
        $this->assertSame(0, $xp->query('//input[@type="number" or @type="date"] | //*[@data-bs-toggle="tooltip"]')->length);
        $this->assertStringContainsString('se cierran y se reemplazan por este conjunto', $html);
    }

    public function test_errores_reabren_registro_con_filas_y_valores_ingresados(): void
    {
        session()->flashInput(['tipo_contrato' => 'Honorarios', 'fecha_termino' => '2026-11-25', 'vinculaciones' => [
            ['establecimiento_id' => 1, 'cantidad_horas' => 61], ['establecimiento_id' => 2, 'cantidad_horas' => 14],
        ]]);
        $html = $this->render(['canManageContratoLaboral' => true, 'errors' => $this->errors(['vinculaciones.0.cantidad_horas' => 'No puede superar 60 horas.'])]);
        $xp = $this->xpath($html);
        $this->assertSame(1, $xp->query('//*[@data-ficha-error-modal="contratoLaboralModal"]')->length);
        $this->assertSame(1, $xp->query('//select[@name="tipo_contrato"]/option[@value="Honorarios" and @selected]')->length);
        $this->assertSame(1, $xp->query('//input[@name="vinculaciones[0][cantidad_horas]" and @value="61" and contains(@class,"is-invalid")]')->length);
        $this->assertSame(1, $xp->query('//select[@name="vinculaciones[1][establecimiento_id]"]//option[@value="2" and @selected]')->length);
        $this->assertStringContainsString('2026-11-25', $html);
        $this->assertStringContainsString('No puede superar 60 horas.', $html);
    }

    public function test_error_de_finalizacion_solo_recupera_el_modal_del_vinculo_correcto(): void
    {
        $perfil = $this->profile()->setRelation('contratosLaboralesActivos', collect([$this->contract(11), $this->contract(12)]));
        session()->flashInput(['_ficha_contrato_id' => 12, 'motivo_desactivacion' => 'abc']);
        $params = ['profile' => $perfil, 'canManageContratoLaboral' => true, 'errors' => $this->errors(['motivo_desactivacion' => 'Escribe al menos cinco caracteres.'])];
        $xp = $this->xpath($this->render($params));
        $this->assertSame(1, $xp->query('//*[@data-ficha-error-modal="desactivarContratoLaboralModal12"]')->length);
        $this->assertSame('abc', $xp->query('//textarea[@id="motivo-desactivar-12"]')->item(0)->textContent);
        $this->assertSame('', $xp->query('//textarea[@id="motivo-desactivar-11"]')->item(0)->textContent);
        session()->flashInput(['_ficha_contrato_id' => 999]);
        $this->assertSame(1, $this->xpath($this->render($params))->query('//*[@data-ficha-error-modal=""]')->length);
    }

    public function test_restricciones_y_datos_son_texto_escapado_incluidos_errores_y_entradas_malformadas(): void
    {
        $perfil = $this->profile(); $perfil->user->forceFill(['nombres' => '<script>alert(1)</script>']);
        session()->flashInput(['fecha_termino' => ['no' => 'texto'], 'tipo_contrato' => [], 'vinculaciones' => [null, ['cantidad_horas' => ['malformado']]]]);
        $html = $this->render(['profile' => $perfil, 'canManageContratoLaboral' => true, 'restrictionContext' => [
            'court_active' => true, 'manual_active' => true, 'court_name' => 'Nombre sintético', 'court_run' => '99000001-K',
            'court_juzgado_origen' => 'Tribunal sintético', 'court_rit' => 'RIT de prueba', 'court_fecha_fallo' => '01-10-2026',
            'court_inhabilidad' => 'Inhabilidad sintética', 'manual_start' => '02-10-2026', 'manual_end' => '31-10-2026', 'manual_comment' => '<img src=x onerror=alert(2)>',
        ]]);
        foreach (['Tribunal sintético', 'RIT de prueba', '01-10-2026', '02-10-2026', '31-10-2026', 'Bloqueo judicial vigente', 'Bloqueo manual vigente', '&lt;script&gt;', '&lt;img'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertSame(1, $this->xpath($html)->query('//*[@data-ficha-rows]//fieldset')->length);
    }

    public function test_solicitudes_respetan_acceso_y_enlace_de_retorno(): void
    {
        foreach ([false, true] as $access) {
            $viewer = \Mockery::mock(User::class)->makePartial(); $viewer->forceFill(['id' => 900]);
            $viewer->shouldReceive('canModule')->with('gestion.solicitudes-reemplazo')->andReturn($access);
            $this->actingAs($viewer); Gate::before(fn () => false);
            $html = $this->render(['solicitudes' => collect([$this->request()])]); $xp = $this->xpath($html);
            $links = $xp->query('//*[@id="ficha-solicitudes"]//a');
            $this->assertSame($access ? 1 : 0, $links->length);
            if ($access) { $this->assertStringContainsString('return_to=', $links->item(0)->getAttribute('href')); }
            foreach (['2026-0099', 'Escuela sintética 1', 'Educación Básica', 'Aceptada Reemplazante'] as $text) { $this->assertStringContainsString($text, $html); }
        }
    }

    private function errors(array $messages): ViewErrorBag
    {
        $bag = new ViewErrorBag; $bag->put('default', new MessageBag($messages)); return $bag;
    }

    private function person(string $name = 'Persona sintética'): User
    {
        return (new User)->forceFill(['id' => 1, 'nombres' => $name, 'rut' => '99000001-K', 'email' => 'persona@example.test'])
            ->setRelation('communes', collect([(new Commune)->forceFill(['name' => 'Comuna sintética'])]))
            ->setRelation('trabajoExternoMarcadoPor', null);
    }

    private function school(int $id = 1): Establecimiento
    {
        return (new Establecimiento)->forceFill(['id' => $id, 'rbd' => 99000 + $id, 'nombre_establecimiento' => 'Escuela sintética '.$id]);
    }

    private function profile(): PostulantProfile
    {
        return (new PostulantProfile)->forceFill(['id' => 10, 'telefono1' => '+56900000000', 'estamento' => 'DOCENTE', 'anios_experiencia' => 0])
            ->setRelation('user', $this->person())->setRelation('comuna', null)
            ->setRelation('areaDesempeno', (new AreaDesempeno)->forceFill(['nombre' => 'Educación Básica']))
            ->setRelation('contratosLaboralesActivos', collect())->setRelation('ultimaVinculacionLaboral', null);
    }

    private function contract(int $id): PostulantProfileContrato
    {
        return (new PostulantProfileContrato)->forceFill(['id' => $id, 'establecimiento_id' => 1, 'tipo_contrato' => 'Contrata', 'cantidad_horas' => 30, 'fecha_termino' => '2026-12-31', 'created_at' => now(), 'activo' => true])
            ->setRelation('establecimiento', $this->school())->setRelation('registradoPor', $this->person('Responsable sintético'));
    }

    private function request(): SolicitudReemplazo
    {
        return (new SolicitudReemplazo)->forceFill(['id' => 99, 'numero_solicitud' => '2026-0099', 'estado' => 'aceptada_reemplazante', 'fecha_termino' => '2026-11-05'])
            ->setRelation('establecimiento', $this->school())->setRelation('areaDesempeno', (new AreaDesempeno)->forceFill(['nombre' => 'Educación Básica']));
    }

    private function render(array $extra = []): string
    {
        $profile = $extra['profile'] ?? $this->profile();
        return view('reemplazos.buscador-postulantes.show', $extra + [
            'profile' => $profile, 'user' => $profile->user, 'errors' => new ViewErrorBag,
            'canManageContratoLaboral' => false, 'canManageTrabajoExterno' => false,
            'establecimientosPorComuna' => collect(['Comuna sintética' => collect([$this->school(), $this->school(2)])]),
            'restrictionContext' => [], 'solicitudes' => collect(), 'reemplazosActivos' => collect(),
        ])->render();
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument; @$dom->loadHTML('<?xml encoding="UTF-8">'.$html); return new \DOMXPath($dom);
    }
}
