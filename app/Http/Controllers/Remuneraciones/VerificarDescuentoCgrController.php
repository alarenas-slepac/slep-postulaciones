<?php

namespace App\Http\Controllers\Remuneraciones;

use App\Http\Controllers\Controller;
use App\Models\DescuentoCgr;
use App\Services\Remuneraciones\DescuentoCgrPdfService;
use Illuminate\View\View;

class VerificarDescuentoCgrController extends Controller
{
    public function __invoke(string $codigo, DescuentoCgrPdfService $documentos): View
    {
        $descuentoCgr = DescuentoCgr::query()
            ->where('codigo_verificacion', $codigo)
            ->firstOrFail();
        $verificacion = $documentos->verificarIntegridad($descuentoCgr);

        return view('remuneraciones.descuentos-cgr.verificar', compact('descuentoCgr', 'verificacion'));
    }
}
