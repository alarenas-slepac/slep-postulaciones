<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Models\CertificadoEmitido;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificadoVerificacionController extends Controller
{
    public function __invoke(Request $request, string $codigo): View
    {
        $certificado = CertificadoEmitido::query()
            ->where('codigo_validacion', strtoupper(trim($codigo)))
            ->first();

        if (! $certificado) {
            abort(404, 'El código de verificación no existe.');
        }

        return view('certificados.verificar', compact('certificado'));
    }
}
