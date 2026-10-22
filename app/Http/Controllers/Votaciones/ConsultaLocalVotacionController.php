<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Models\JornadaVotacion;
use App\Support\Votaciones\RutConsulta;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ConsultaLocalVotacionController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->pantalla($request);
    }

    public function consultar(Request $request): Response
    {
        // No usar redirect()->withInput(): el RUT y el resultado no se guardan en sesión.
        $validator = Validator::make($request->only(['rut', 'jornada']), [
            'rut' => ['required', 'string', 'max:20'], 'jornada' => ['required', 'string', 'max:255'],
        ]);
        $rut = RutConsulta::normalizar($request->input('rut'));
        if ($validator->fails() || $rut === null) {
            return $this->pantalla($request, error: 'Ingresa un RUT válido con su dígito verificador y selecciona una jornada.', status: 422);
        }

        return DB::transaction(function () use ($request, $rut): Response {
            // La importación/publicación usa el mismo bloqueo: resultado consistente.
            $jornada = $this->publicadas()->where('slug', $request->input('jornada'))->sharedLock()->first();
            if (! $jornada) {
                return $this->pantalla($request, error: 'La consulta de esta jornada no está disponible.', status: 404);
            }
            $resultados = $jornada->padronConsulta()->where('rut', $rut)->with('local')->orderBy('rbd')->get();

            return $this->pantalla($request, resultados: $resultados, consultada: $jornada);
        });
    }

    private function publicadas()
    {
        return JornadaVotacion::query()->where('consulta_publica', true)->where('estado', '!=', JornadaVotacion::SUSPENDIDA)
            ->whereHas('procesos', fn ($q) => $q->where('activo', true));
    }

    private function pantalla(Request $request, $resultados = null, ?JornadaVotacion $consultada = null, ?string $error = null, int $status = 200): Response
    {
        return response()->view('public.votaciones.consulta-local', [
            'jornadas' => $this->publicadas()->with(['procesos' => fn ($q) => $q->where('activo', true)])->orderByDesc('fecha')->get(['id', 'nombre', 'slug']),
            'seleccionada' => $consultada?->slug ?? (is_string($request->input('jornada')) ? $request->input('jornada') : ''),
            'resultados' => $resultados, 'consultada' => $consultada, 'errorConsulta' => $error,
        ], $status);
    }
}
