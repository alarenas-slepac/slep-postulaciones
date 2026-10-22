<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Votaciones\GuardarResultadoDiarioRequest;
use App\Models\JornadaVotacion;
use App\Models\ResultadoDiarioVotacion;
use App\Services\Votaciones\ResultadoDiarioService;
use App\Support\Votaciones\PapeletasVotacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResultadoDiarioVotacionController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['jornada' => ['nullable', 'string', 'max:255'], 'fecha' => ['nullable', 'date_format:Y-m-d']]);
        $jornadas = JornadaVotacion::query()->orderByDesc('fecha')->get(['id', 'slug', 'nombre', 'estado']);
        $jornada = ! empty($data['jornada']) ? JornadaVotacion::where('slug', $data['jornada'])->firstOrFail() : $jornadas->first();
        $fecha = $data['fecha'] ?? now(config('votaciones.timezone'))->toDateString();
        $dias = $jornada?->resultadosDiarios()->orderByDesc('fecha')->get() ?? collect();
        $resultado = $dias->first(fn ($dia) => $dia->fecha->toDateString() === $fecha);
        $papeletas = $jornada ? PapeletasVotacion::para($jornada, $resultado) : [];
        $resumen = PapeletasVotacion::resumir(PapeletasVotacion::deDias($dias), $dias);

        return view('votaciones.admin.resultados', compact('jornadas', 'jornada', 'fecha', 'dias', 'resultado', 'papeletas', 'resumen'));
    }

    public function guardar(GuardarResultadoDiarioRequest $request, JornadaVotacion $jornada, ResultadoDiarioService $service): RedirectResponse
    {
        $resultado = $service->guardar($jornada, $request->validated(), $request->user());

        return redirect()->route('votaciones.admin.resultados.index', ['jornada' => $jornada->slug, 'fecha' => $resultado->fecha->toDateString()])
            ->with('success', 'Escrutinio guardado. Revisa los datos y pulsa Publicar día para hacerlo visible.');
    }

    public function publicacion(Request $request, JornadaVotacion $jornada, ResultadoDiarioVotacion $resultado, ResultadoDiarioService $service): RedirectResponse
    {
        $data = $request->validate(['publicar' => ['required', 'boolean'], 'version' => ['required', 'integer', 'min:1']]);
        $service->publicar($jornada, $resultado, $request->boolean('publicar'), (int) $data['version'], $request->user());

        return redirect()->route('votaciones.admin.resultados.index', ['jornada' => $jornada->slug, 'fecha' => $resultado->fecha->toDateString()])
            ->with('success', $request->boolean('publicar') ? 'Resultados del día publicados.' : 'Resultados del día retirados de la vista pública.');
    }

    public function publico(Request $request): View
    {
        $data = $request->validate(['jornada' => ['nullable', 'string', 'max:255'], 'fecha' => ['nullable', 'date_format:Y-m-d']]);
        $jornadas = JornadaVotacion::query()->where('estado', '!=', JornadaVotacion::SUSPENDIDA)
            ->whereHas('resultadosDiarios', fn ($q) => $q->whereNotNull('publicado_at'))
            ->orderByDesc('fecha')->get(['id', 'slug', 'nombre']);
        $jornada = ! empty($data['jornada']) ? $jornadas->firstWhere('slug', $data['jornada']) : $jornadas->first();
        abort_if(! empty($data['jornada']) && ! $jornada, 404);
        // Selección explícita: ni observaciones internas ni usuarios del registro llegan a la vista pública.
        $dias = $jornada?->resultadosDiarios()->whereNotNull('publicado_at')->orderBy('fecha')
            ->get(['fecha', 'conteos', 'publicado_at']) ?? collect();
        abort_if($jornada && $dias->isEmpty(), 404);
        $fecha = $data['fecha'] ?? null;
        abort_if($fecha && ! $dias->contains(fn ($dia) => $dia->fecha->toDateString() === $fecha), 404);
        $seleccionados = $fecha ? $dias->filter(fn ($dia) => $dia->fecha->toDateString() === $fecha) : $dias;
        $resumen = PapeletasVotacion::resumir(PapeletasVotacion::deDias($seleccionados), $seleccionados);

        return view('public.votaciones.resultados', compact('jornadas', 'jornada', 'dias', 'fecha', 'resumen'));
    }
}
