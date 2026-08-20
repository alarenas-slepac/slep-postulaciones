<?php

namespace App\Http\Controllers\Remuneraciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Remuneraciones\GuardarDescuentoCgrRequest;
use App\Models\DescuentoCgr;
use App\Services\Remuneraciones\CronogramaDescuentoCgrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DescuentoCgrController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin|funcionario_slep']);
    }

    public function index(Request $request): View
    {
        $buscar = trim((string) $request->get('buscar', ''));
        $anio = (int) $request->integer('anio');

        $descuentos = DescuentoCgr::query()
            ->when($buscar !== '', function ($query) use ($buscar) {
                $termino = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $buscar).'%';
                $query->where(fn ($subquery) => $subquery
                    ->where('rut', 'like', $termino)
                    ->orWhere('nombre', 'like', $termino)
                    ->orWhere('numero_resolucion', 'like', $termino));
            })
            ->when($anio > 0, fn ($query) => $query->whereYear('fecha_primer_descuento', $anio))
            ->orderByDesc('fecha_primer_descuento')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $anios = DescuentoCgr::query()
            ->selectRaw('YEAR(fecha_primer_descuento) as anio')
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio');

        return view('remuneraciones.descuentos-cgr.index', compact('descuentos', 'buscar', 'anio', 'anios'));
    }

    public function create(): View
    {
        return view('remuneraciones.descuentos-cgr.form', ['descuentoCgr' => new DescuentoCgr]);
    }

    public function store(GuardarDescuentoCgrRequest $request): RedirectResponse
    {
        $data = $this->datosPersistencia($request);
        $data += $this->guardarPdf($request);
        $data += ['creado_por_id' => $request->user()->id, 'actualizado_por_id' => $request->user()->id];

        $descuento = DescuentoCgr::create($data);

        return redirect()->route('descuentos-cgr.show', $descuento)
            ->with('status', 'Descuento CGR registrado. El cronograma fue calculado con los valores UTM disponibles.');
    }

    public function show(DescuentoCgr $descuentoCgr, CronogramaDescuentoCgrService $cronograma): View
    {
        $descuentoCgr->load('creadoPor', 'actualizadoPor');
        $calculo = $cronograma->calcular($descuentoCgr);

        return view('remuneraciones.descuentos-cgr.show', compact('descuentoCgr', 'calculo'));
    }

    public function edit(DescuentoCgr $descuentoCgr): View
    {
        return view('remuneraciones.descuentos-cgr.form', compact('descuentoCgr'));
    }

    public function update(GuardarDescuentoCgrRequest $request, DescuentoCgr $descuentoCgr): RedirectResponse
    {
        $data = $this->datosPersistencia($request);
        $data['actualizado_por_id'] = $request->user()->id;

        if ($request->hasFile('resolucion_pdf')) {
            $data += $this->guardarPdf($request);
        }

        $descuentoCgr->update($data);

        return redirect()->route('descuentos-cgr.show', $descuentoCgr)
            ->with('status', 'Descuento CGR actualizado y cronograma recalculado.');
    }

    public function pdf(DescuentoCgr $descuentoCgr): mixed
    {
        abort_unless(Storage::disk('local')->exists($descuentoCgr->resolucion_pdf_path), 404);

        return Storage::disk('local')->response(
            $descuentoCgr->resolucion_pdf_path,
            $descuentoCgr->resolucion_pdf_nombre,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline']
        );
    }

    private function datosPersistencia(GuardarDescuentoCgrRequest $request): array
    {
        return Arr::except($request->validated(), ['resolucion_pdf']);
    }

    private function guardarPdf(GuardarDescuentoCgrRequest $request): array
    {
        $archivo = $request->file('resolucion_pdf');
        $anio = substr((string) $request->validated('fecha_primer_descuento'), 0, 4);
        $nombre = Str::uuid().'.pdf';
        $path = $archivo->storeAs("descuentos-cgr/resoluciones/{$anio}", $nombre, 'local');

        return [
            'resolucion_pdf_path' => $path,
            'resolucion_pdf_nombre' => $archivo->getClientOriginalName(),
            'resolucion_pdf_tamano' => $archivo->getSize(),
        ];
    }
}
