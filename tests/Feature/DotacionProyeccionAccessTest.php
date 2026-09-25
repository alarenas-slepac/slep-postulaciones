<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionEstablecimientoController;
use App\Models\Establecimiento;
use App\Support\DotacionProyeccionCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DotacionProyeccionAccessTest extends TestCase
{
    public static function roles(): array
    {
        return [
            ['admin', true], ['coordinador_uatp', true], ['coordinador_gdp', true],
            ['supervisor_plani', true], ['funcionario_directivo_estab', false],
            ['funcionario_slep', false], [null, false],
        ];
    }

    #[DataProvider('roles')]
    public function test_permiso_y_boton_segun_rol_activo(?string $role, bool $permitido): void
    {
        $this->assertSame($permitido, DotacionProyeccionCalculator::canView($role));
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php'));
        $start = strpos($source, '<div class="dotacion-hero');
        $end = strpos($source, '@if (!empty($alertas))', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $html = Blade::render(substr($source, $start, $end - $start), [
            'activeRole' => $role, 'anio' => 2026,
            'establecimiento' => $this->establecimiento(),
        ]);
        $this->assertStringContainsString('desde_dotacion=1', $html);
        $this->assertStringContainsString('tab_origen=resumen', $html);
        if ($permitido) {
            $this->assertStringContainsString('Proyección 2027', $html);
            $this->assertStringContainsString('proyeccion=1', $html);
        } else {
            $this->assertStringNotContainsString('Proyección 2027', $html);
            $this->assertStringNotContainsString('proyeccion=1', $html);
        }
    }

    public function test_directivo_no_puede_abrir_proyeccion_por_url_aunque_tenga_otro_rol(): void
    {
        foreach (['1', 'true', 'on', 'yes'] as $valor) {
            $request = $this->request(['proyeccion' => $valor, 'anio' => 2026]);
            try {
                app(DotacionEstablecimientoController::class)->show($request, $this->establecimiento());
                $this->fail('El acceso directo debe ser rechazado antes de calcular la dotación.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_enlace_a_funciones_conserva_la_pestana_de_asignacion(): void
    {
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/show.blade.php'));
        $start = strpos($source, '<div class="dotacion-hero');
        $end = strpos($source, '@if (!empty($alertas))', $start);
        $html = Blade::render(substr($source, $start, $end - $start), [
            'activeRole' => 'admin', 'anio' => 2027, 'tab' => 'asignacion',
            'establecimiento' => $this->establecimiento(),
        ]);

        $this->assertStringContainsString('tab_origen=asignacion', $html);
        $this->assertStringContainsString('data-dotacion-contexto-salida', $html);
    }

    public function test_directivo_conserva_autorizacion_al_modulo_y_a_su_establecimiento(): void
    {
        $controller = app(DotacionEstablecimientoController::class);
        $request = $this->request();
        $this->assertSame('funcionario_directivo_estab',
            (new \ReflectionMethod($controller, 'authorizeDotacionAccess'))->invoke($controller, $request));
        (new \ReflectionMethod($controller, 'authorizeEstablecimientoScope'))->invoke($controller, $request, $this->establecimiento());
        $route = app('router')->getRoutes()->getByName('admin.dotacion-establecimiento.show');
        $this->assertStringContainsString('funcionario_directivo_estab', implode(',', $route->gatherMiddleware()));
    }

    private function establecimiento(): Establecimiento
    {
        return (new Establecimiento)->forceFill([
            'id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético',
            'comuna' => 'Comuna sintética', 'sala_cuna' => false,
        ]);
    }

    private function request(array $query = []): Request
    {
        $request = Request::create('/admin/dotacion-establecimiento/1', 'GET', $query);
        $request->setUserResolver(fn () => new class {
            public int $establecimiento_id = 1;

            public function activeRoleName(): string
            {
                return 'funcionario_directivo_estab';
            }

            public function hasRole(string $role): bool
            {
                return in_array($role, ['admin', 'funcionario_directivo_estab'], true);
            }
        });

        return $request;
    }
}
