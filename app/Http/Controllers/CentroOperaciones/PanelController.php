<?php

namespace App\Http\Controllers\CentroOperaciones;

use App\Http\Controllers\Controller;
use App\Services\CentroOperaciones\ConsolidadoService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PanelController extends Controller
{
    public function __construct(private readonly ConsolidadoService $consolidado)
    {
    }

    public function index(Request $request): View
    {
        $fecha = $this->fecha($request);

        return view('centro-operaciones.panel', [
            'datos' => $this->consolidado->paraFecha($fecha),
            'modoTv' => false,
        ]);
    }

    public function tv(Request $request): View
    {
        $fecha = $this->fecha($request);

        return view('centro-operaciones.panel', [
            'datos' => $this->consolidado->paraFecha($fecha),
            'modoTv' => true,
        ]);
    }

    public function datos(Request $request): JsonResponse
    {
        return response()->json($this->consolidado->paraFecha($this->fecha($request)));
    }

    private function fecha(Request $request): CarbonImmutable
    {
        $zona = config('centro_operaciones.timezone');
        $hoy = CarbonImmutable::now($zona)->startOfDay();
        $valor = $request->validate(['fecha' => ['nullable', 'date_format:Y-m-d']])['fecha'] ?? null;
        $fecha = $valor ? CarbonImmutable::createFromFormat('Y-m-d', $valor, $zona)->startOfDay() : $hoy;

        abort_if($fecha->isAfter($hoy), 422, 'No es posible consultar una fecha futura.');

        return $fecha;
    }
}
