<?php

namespace App\Http\Controllers\Funcionarios;

use App\Http\Controllers\Controller;
use App\Services\Funcionarios\ConsultaRut;
use App\Services\Funcionarios\FuncionarioConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultaFuncionarioController extends Controller
{
    public function index()
    {
        return response()->view('funcionarios.consulta')->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    public function consultar(Request $request, FuncionarioConsultaService $service): JsonResponse
    {
        // La API sólo admite el cuerpo JSON: ni query string ni rutas contienen un RUT.
        $rut = ConsultaRut::normalize($request->json('rut'));
        if (!$rut) {
            return response()->json(['error' => 'rut_invalido', 'message' => 'Ingresa un RUT con DV válido, sin puntos ni guion.',
                'errors' => ['rut' => ['El RUT debe incluir un dígito verificador válido.']]], 422);
        }
        try {
            return response()->json($service->consultar($rut));
        } catch (\Throwable) {
            // Capturar antes del pipeline de Laravel: no registrar SQL/bindings con el RUT.
            return response()->json(['error' => 'servicio_no_disponible',
                'message' => 'No fue posible completar la consulta. Intenta más tarde.'], 503);
        }
    }
}
