<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\DotacionFuncionesController;
use Illuminate\Http\Request;
use Tests\TestCase;

class DotacionFuncionesNavigationTest extends TestCase
{
    public function test_conserva_el_origen_y_la_pestana_despues_de_enviar_un_formulario(): void
    {
        $controller = new DotacionFuncionesController;
        $contexto = new \ReflectionMethod($controller, 'accionesContexto');
        $request = Request::create(
            '/admin/dotacion-funciones/1/manual?desde_dotacion=1&tab_origen=asignacion',
            'POST',
            ['anio' => 2027]
        );

        $this->assertSame(
            ['desde_dotacion' => 1, 'tab_origen' => 'asignacion'],
            $contexto->invoke($controller, $request)
        );
        $this->assertSame([], $contexto->invoke(
            $controller,
            Request::create('/admin/dotacion-funciones/1/manual', 'POST', ['anio' => 2027])
        ));
    }

    public function test_retorno_rechaza_una_pestana_ajena_a_dotacion(): void
    {
        $controller = new DotacionFuncionesController;
        $contexto = new \ReflectionMethod($controller, 'accionesContexto');
        $request = Request::create('/admin/dotacion-funciones/1?desde_dotacion=1&tab_origen=otra', 'GET');

        $this->assertSame(
            ['desde_dotacion' => 1, 'tab_origen' => 'resumen'],
            $contexto->invoke($controller, $request)
        );
    }
}
