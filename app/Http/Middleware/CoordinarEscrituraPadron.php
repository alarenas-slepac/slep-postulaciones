<?php

namespace App\Http\Middleware;

use App\Services\Padron\PadronEscrituraService;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoordinarEscrituraPadron
{
    /** Entradas HTTP identificadas; no sustituye el inventario de SQL/CLI externos. */
    public const CONTROLADORES = [
        'Admin\\DotacionAsignacionController', 'Admin\\DotacionDocenteExclusionController',
        'Admin\\DotacionFuncionesController', 'Admin\\DotacionCursoCombinadoController',
        'Admin\\DotacionProporcionExcepcionController', 'Admin\\DotacionEstablecimientoController',
        'Admin\\EstablecimientoController', 'Admin\\FuncionCatalogoController', 'Admin\\InstitucionCatalogoController', 'Admin\\TituloCatalogoController',
        'Admin\\FuncionarioViaticoAnexoController', 'Admin\\PermisoSinGoceExcepcionController',
        'DeclaracionSostenedorController', 'ReemplazosController',
        'FuncionarioEstab\\SolicitudReemplazoController', 'Gestion\\SolicitudReemplazoGestionController',
        'Gestion\\OrdenTrabajoPdfController',
        'IncumplimientoLaboralController', 'Tramites\\CometidoFuncionarioController',
        'Tramites\\CometidoFuncionarioInformeController', 'Tramites\\CometidoFuncionarioRendicionController',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        [$controller, $action] = array_pad(explode('@', $request->route()?->getActionName() ?? ''), 2, '');
        $controllers = array_map(static fn ($name) => 'App\\Http\\Controllers\\'.$name, self::CONTROLADORES);
        // Excepción heredada: este GET regenera el archivo y actualiza la solicitud.
        $regeneratesOrder = $controller === 'App\\Http\\Controllers\\Gestion\\OrdenTrabajoPdfController'
            && in_array($action, ['show', 'download'], true) && $request->boolean('regenerar');
        if (($request->isMethodSafe() && ! $regeneratesOrder) || ! in_array($controller, $controllers, true)) {
            return $next($request);
        }
        try {
            return app(PadronEscrituraService::class)->ejecutar(function () use ($request, $next) {
                // Se ejecuta antes del binding: los modelos y la validación del
                // controlador se obtienen DESPUÉS de esperar al aplicador.
                $response = $next($request);
                // El pipeline puede convertir excepciones en respuestas antes de
                // devolver el control. No confirmar escrituras parciales al fallar.
                $validationRedirect = $response->isRedirection() && $request->hasSession()
                    && in_array('errors', $request->session()->get('_flash.new', []), true);
                if ($response->getStatusCode() >= 400 || $validationRedirect) {
                    throw new HttpResponseException($response);
                }
                return $response;
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        }
    }
}
