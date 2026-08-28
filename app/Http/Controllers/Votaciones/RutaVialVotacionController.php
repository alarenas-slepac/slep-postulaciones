<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Models\JornadaVotacion;
use App\Services\Votaciones\RutaVialVotacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RutaVialVotacionController extends Controller
{
    public function publica(JornadaVotacion $jornada, RutaVialVotacionService $service): JsonResponse
    {
        abort_unless($jornada->publica && in_array($jornada->estado, [
            JornadaVotacion::PUBLICADA,
            JornadaVotacion::EN_CURSO,
            JornadaVotacion::FINALIZADA,
        ], true), 404);

        return response()->json($service->obtener($jornada))
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function admin(Request $request, JornadaVotacion $jornada, RutaVialVotacionService $service): JsonResponse
    {
        abort_unless($request->user()?->can('votaciones.manage-jornadas'), 403);

        return response()->json($service->obtener($jornada))
            ->header('Cache-Control', 'private, max-age=300');
    }
}
