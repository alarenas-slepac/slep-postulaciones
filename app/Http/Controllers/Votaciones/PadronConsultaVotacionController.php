<?php

namespace App\Http\Controllers\Votaciones;

use App\Http\Controllers\Controller;
use App\Models\JornadaVotacion;
use App\Services\Votaciones\PadronConsultaExcel;
use App\Services\Votaciones\PadronConsultaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PadronConsultaVotacionController extends Controller
{
    public function index(Request $request): View
    {
        $jornadas = JornadaVotacion::query()->with('procesos')->withCount(['padronConsulta', 'localesConsulta'])->orderByDesc('fecha')->paginate(12)->withQueryString();
        $jornada = $request->filled('jornada')
            ? JornadaVotacion::query()->with('procesos')->withCount(['padronConsulta', 'localesConsulta'])->where('slug', (string) $request->string('jornada'))->firstOrFail()
            : $jornadas->first();

        return view('votaciones.admin.padron-consulta', [
            'jornadas' => $jornadas, 'jornada' => $jornada,
            'locales' => $jornada?->localesConsulta()->orderBy('dia')->orderBy('hora_inicio')->orderBy('rbd_origen')->get(),
        ]);
    }

    public function importar(Request $request, JornadaVotacion $jornada, PadronConsultaExcel $excel, PadronConsultaService $service): RedirectResponse
    {
        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx', 'max:10240'], 'confirmar' => ['sometimes', 'accepted']]);
        try {
            $analysis = $excel->analizar($request->file('archivo')->getRealPath());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['archivo' => $e->getMessage()]);
        }
        $redirect = redirect()->route('votaciones.admin.padrones.index', ['jornada' => $jornada->slug]);
        if ($analysis['resumen']['errores'] > 0) {
            return $redirect->with('diagnostico_padron', ['resumen' => $analysis['resumen'], 'errores' => $analysis['errores']]);
        }
        $service->importar($jornada, $analysis, $request->user(), $request->boolean('confirmar'));

        return $redirect->with('success', 'Padrón importado. Revisa los locales y publica la consulta cuando la información esté confirmada.')
            ->with('diagnostico_padron', ['resumen' => $analysis['resumen'], 'errores' => []]);
    }

    public function publicacion(Request $request, JornadaVotacion $jornada, PadronConsultaService $service): RedirectResponse
    {
        $request->validate(['publicar' => ['required', 'boolean']]);
        $service->publicar($jornada, $request->boolean('publicar'), $request->user());

        return redirect()->route('votaciones.admin.padrones.index', ['jornada' => $jornada->slug])
            ->with('success', $request->boolean('publicar') ? 'Consulta pública habilitada.' : 'Consulta pública retirada.');
    }
}
