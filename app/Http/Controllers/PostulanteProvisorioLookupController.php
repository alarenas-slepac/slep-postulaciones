<?php

namespace App\Http\Controllers;

use App\Models\PostulanteProvisorio;
use App\Support\RutChile;
use Illuminate\Http\Request;

class PostulanteProvisorioLookupController extends Controller
{
    public function __invoke(Request $request)
    {
        $rawRut = (string) $request->query('rut', '');
        $norm = RutChile::normalize($rawRut);

        if (!$norm) {
            return response()->json(['found' => false]);
        }

        $p = PostulanteProvisorio::where('rut', $norm['rut'])->first();

        if (!$p) {
            return response()->json(['found' => false, 'rut' => $norm['rut']]);
        }

        [$apPat, $apMat] = RutChile::splitApellidos($p->apellidos);

        return response()->json([
            'found' => true,
            'rut' => $p->rut,
            'nombres' => $p->nombres,
            'apellidos' => $p->apellidos,
            'apellido_paterno' => $apPat,
            'apellido_materno' => $apMat,
            'email' => $p->email,
            'emails' => $p->emails ?? [], // ideal: cast a array
        ]);
    }
}
