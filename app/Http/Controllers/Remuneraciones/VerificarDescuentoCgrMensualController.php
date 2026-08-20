<?php

namespace App\Http\Controllers\Remuneraciones;

use App\Http\Controllers\Controller;
use App\Models\DescuentoCgrDocumentoMensual;
use App\Services\Remuneraciones\DescuentoCgrPdfService;
use Illuminate\View\View;

class VerificarDescuentoCgrMensualController extends Controller
{
    public function __invoke(string $codigo, DescuentoCgrPdfService $documentos): View
    {
        $documento = DescuentoCgrDocumentoMensual::query()
            ->with('descuentoCgr')
            ->where('codigo_verificacion', $codigo)
            ->firstOrFail();
        $verificacion = $documentos->verificarIntegridadMensual($documento);

        return view('remuneraciones.descuentos-cgr.verificar-mensual', compact('documento', 'verificacion'));
    }
}
