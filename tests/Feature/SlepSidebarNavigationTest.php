<?php

namespace Tests\Feature;

use App\Support\SlepSidebarNavigation;
use App\Support\SlepUiRegistry;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class SlepSidebarNavigationTest extends TestCase
{
    public function test_agrupa_todos_los_accesos_autorizados_y_prioriza_segun_rol(): void
    {
        $expectedOrder = [
            'admin' => ['operaciones', 'seleccion'],
            'funcionario_slep' => ['seleccion', 'remuneraciones'],
            'coordinador_gdp' => ['personal', 'seleccion'],
            'coordinador_uatp' => ['dotacion'],
        ];

        foreach ($expectedOrder as $role => $priority) {
            $original = SlepUiRegistry::menuGroups($this->viewer(), $role);
            $navigation = SlepSidebarNavigation::forRole($original, $role, 'dashboard');
            $sections = collect($navigation['sections']);
            $routes = collect($navigation['direct'])->concat($sections->pluck('links')->flatten(1))->pluck('route');

            $this->assertSame($priority, $sections->pluck('key')->take(count($priority))->all());
            $this->assertEqualsCanonicalizing(collect($original)->flatten(1)->pluck('route')->unique()->all(), $routes->all());
            $this->assertSame(['dashboard', 'messages.index'], array_column($navigation['direct'], 'route'));
            $this->assertSame('Inicio', $navigation['direct'][0]['label']);
            $this->assertTrue($navigation['direct'][0]['active']);
            $this->assertSame(0, $sections->where('active', true)->count());

            $agendamiento = 'gestion.agendamientos-recursos.index';
            if ($routes->contains($agendamiento)) {
                $this->assertSame(1, $routes->filter(fn ($route) => $route === $agendamiento)->count());
                $this->assertContains($agendamiento, array_column($sections->firstWhere('key', 'cometidos')['links'], 'route'));
            }
        }
    }

    public function test_solo_se_aplica_a_los_roles_indicados_y_oculta_secciones_sin_permisos(): void
    {
        foreach (['postulante', 'funcionario', 'funcionario_estab', 'supervisor_plani', 'funcionario_ac', null] as $role) {
            $this->assertNull(SlepSidebarNavigation::forRole([], $role, 'dashboard'));
        }

        $role = 'coordinador_uatp';
        $original = SlepUiRegistry::menuGroups($this->viewer(['centro-operaciones']), $role);
        $navigation = SlepSidebarNavigation::forRole($original, $role, 'dashboard');
        $this->assertNotContains('operaciones', array_column($navigation['sections'], 'key'));
        $this->assertNotContains('remuneraciones', array_column($navigation['sections'], 'key'));
    }

    public function test_abre_la_seccion_activa_y_prioriza_rutas_exactas_y_prefijos_especificos(): void
    {
        $original = SlepUiRegistry::menuGroups($this->viewer(), 'admin');
        $cases = [
            ['gestion.solicitudes-reemplazo.finiquitos.show', 'seleccion', 'gestion.solicitudes-reemplazo.finiquitos.index'],
            ['reemplazos.buscador-postulantes.show', 'seleccion', 'reemplazos.buscador-postulantes.index'],
            ['reemplazos.documents.forUser', 'seleccion', 'reemplazos.buscador-postulantes.index'],
            ['admin.dotacion-establecimiento.show', 'dotacion', 'admin.dotacion-establecimiento.index'],
            ['centro-operaciones.riesgos.configuracion', 'operaciones', 'centro-operaciones.riesgos.configuracion'],
            ['centro-operaciones.riesgos.index', 'operaciones', 'centro-operaciones.riesgos.index'],
        ];

        foreach ($cases as [$route, $section, $link]) {
            $navigation = SlepSidebarNavigation::forRole($original, 'admin', $route);
            $activeSection = collect($navigation['sections'])->where('active', true)->sole();
            $this->assertSame($section, $activeSection['key']);
            $activeLinks = collect($navigation['sections'])->pluck('links')->flatten(1)->where('active', true);
            $this->assertSame($link, $activeLinks->sole()['route']);
        }
    }

    public function test_renderiza_desplegables_nativos_y_solo_un_enlace_marcado_como_actual(): void
    {
        $role = 'coordinador_gdp';
        $original = SlepUiRegistry::menuGroups($this->viewer(), $role);
        $navigation = SlepSidebarNavigation::forRole($original, $role, 'tramites.cometidos-funcionarios.show');
        $html = view('partials.sidebar-sections', compact('navigation'))->render();
        $dom = new DOMDocument;
        $dom->loadHTML('<meta charset="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $this->assertCount(2, $xpath->query('//nav/ul/li/a'));
        $this->assertCount(count($navigation['sections']), $xpath->query('//nav//details/summary'));
        $this->assertCount(1, $xpath->query('//details[@open and @data-sidebar-section="cometidos"]'));
        $this->assertCount(1, $xpath->query('//a[@aria-current="page"]'));
        $this->assertSame(route('tramites.cometidos-funcionarios.index'), $xpath->evaluate('string(//a[@aria-current="page"]/@href)'));
    }

    private function viewer(array $denied = []): object
    {
        return new class ($denied)
        {
            public function __construct(private array $denied) {}

            public function canModule(string $module, ?string $role = null): bool
            {
                return ! in_array($module, $this->denied, true);
            }

            public function can(string $permission): bool
            {
                return true;
            }
        };
    }
}
