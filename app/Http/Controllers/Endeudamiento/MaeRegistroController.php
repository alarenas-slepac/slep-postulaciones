<?php

namespace App\Http\Controllers\Endeudamiento;

use App\Http\Controllers\Controller;
use App\Models\MaeRegistro;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaeRegistroController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin']);
    }

    public function index(Request $request): View
    {
        $anio = (int) $request->integer('anio', 0);
        $mes = (int) $request->integer('mes', 0);
        $dominio = trim((string) $request->get('dominio', ''));
        $q = trim((string) $request->get('q', ''));
        $soloVigentes = $request->boolean('solo_vigentes', true);
        $conOtros = $request->boolean('con_otros', false);

        $items = MaeRegistro::query()
            ->with(['carga'])
            ->when($soloVigentes, function ($query) {
                $query->whereHas('carga', fn($q) => $q->where('es_vigente', true));
            })
            ->when($anio > 0, fn($query) => $query->where('anio', $anio))
            ->when($mes > 0, fn($query) => $query->where('mes', $mes))
            ->when($dominio !== '', fn($query) => $query->where('dominio', $dominio))
            ->when($conOtros, fn($query) => $query->where('total_otros_descuentos', '>', 0))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('rut', 'like', "%{$q}%")
                        ->orWhere('nombre_completo', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderBy('dominio')
            ->orderBy('nombre_completo')
            ->paginate(25)
            ->withQueryString();

        $dominios = MaeRegistro::query()->distinct()->orderBy('dominio')->pluck('dominio');
        $anios = MaeRegistro::query()->distinct()->orderByDesc('anio')->pluck('anio');

        return view('endeudamiento.registros.index', compact('items', 'dominios', 'anios', 'anio', 'mes', 'dominio', 'q', 'soloVigentes', 'conOtros'));
    }

    public function show(MaeRegistro $maeRegistro): View
    {
        $maeRegistro->load(['carga', 'descuentos', 'otrosDescuentos']);

        return view('endeudamiento.registros.show', compact('maeRegistro'));
    }
}
