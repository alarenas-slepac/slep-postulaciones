<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Models\JornadaVotacion;
use App\Services\Votaciones\EstadoPublicoVotacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PublicVotacionController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $jornadas = JornadaVotacion::query()->where('publica', true)->whereIn('estado', ['publicada', 'en_curso', 'finalizada'])->orderByDesc('fecha')->get();
        if ($jornadas->count() === 1) {
            return redirect()->route('public.votaciones.show', $jornadas->first());
        }

        return view('public.votaciones.index', compact('jornadas'));
    }

    public function show(JornadaVotacion $jornada, EstadoPublicoVotacionService $service): View
    {
        abort_unless($jornada->publica && in_array($jornada->estado, ['publicada', 'en_curso', 'finalizada'], true), 404);

        return view('public.votaciones.show', ['jornada' => $jornada, 'estadoInicial' => $service->obtener($jornada)]);
    }

    public function estado(JornadaVotacion $jornada, EstadoPublicoVotacionService $service): JsonResponse
    {
        abort_unless($jornada->publica && in_array($jornada->estado, ['publicada', 'en_curso', 'finalizada'], true), 404);

        return response()->json($service->obtener($jornada))->header('Cache-Control', 'public, max-age=5');
    }
}
