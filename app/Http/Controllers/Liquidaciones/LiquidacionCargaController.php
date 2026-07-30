<?php

namespace App\Http\Controllers\Liquidaciones;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLiquidacionCargaPdf;
use App\Models\LiquidacionCarga;
use App\Models\LiquidacionFuncionario;
use App\Services\Liquidaciones\LiquidacionPaqueteImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LiquidacionCargaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:admin|funcionario_slep']);
    }

    public function index(Request $request): View
    {
        $anio = (int) $request->integer('anio', 0);
        $mes = (int) $request->integer('mes', 0);
        $dominio = trim((string) $request->get('dominio', ''));
        $estado = trim((string) $request->get('estado', ''));

        $items = LiquidacionCarga::query()
            ->with('subidaPor')
            ->when($anio > 0, fn ($query) => $query->where('anio', $anio))
            ->when($mes > 0, fn ($query) => $query->where('mes', $mes))
            ->when($dominio !== '', fn ($query) => $query->where('dominio', $dominio))
            ->when($estado !== '', fn ($query) => $query->where('estado', $estado))
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderBy('dominio')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $dominios = LiquidacionCarga::query()->distinct()->orderBy('dominio')->pluck('dominio');
        $anios = LiquidacionCarga::query()->distinct()->orderByDesc('anio')->pluck('anio');
        $estados = collect(['pendiente', 'procesando', 'procesado', 'procesado_con_observaciones', 'fallido']);

        return view('liquidaciones.cargas.index', compact('items', 'dominios', 'anios', 'estados', 'anio', 'mes', 'dominio', 'estado'));
    }

    public function create(): View
    {
        $dominios = ['Coronel', 'Lota', 'San Pedro', 'Santa Juana', 'JUNJI', 'Administración Central'];

        return view('liquidaciones.cargas.create', compact('dominios'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2024', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'dominio' => ['required', 'string', 'max:100'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:204800'],
        ], [
            'pdf.required' => 'Debes seleccionar el PDF de liquidaciones.',
            'pdf.mimes' => 'El archivo debe estar en formato PDF.',
            'pdf.max' => 'El PDF no puede superar los 200 MB.',
        ]);

        $file = $request->file('pdf');
        $dominioSlug = Str::slug($data['dominio']);
        $filename = sprintf('%04d-%02d-%s-%s.pdf', (int) $data['anio'], (int) $data['mes'], $dominioSlug, Str::random(8));
        $path = $file->storeAs(sprintf('liquidaciones/originales/%04d/%02d/%s', (int) $data['anio'], (int) $data['mes'], $dominioSlug), $filename, 'local');

        $carga = LiquidacionCarga::create([
            'mes' => (int) $data['mes'],
            'anio' => (int) $data['anio'],
            'dominio' => $data['dominio'],
            'archivo_original_path' => $path,
            'archivo_original_nombre' => $file->getClientOriginalName(),
            'estado' => 'pendiente',
            'subida_por_id' => $request->user()->id,
        ]);

        ProcessLiquidacionCargaPdf::dispatch($carga->id);

        return redirect()
            ->route('liquidaciones.cargas.show', $carga)
            ->with('status', 'Carga de liquidaciones recibida. El procesamiento quedó en cola; asegúrate de tener activo el worker de Laravel.');
    }


    public function storePaquete(Request $request, LiquidacionPaqueteImportService $importer): RedirectResponse
    {
        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2024', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'dominio' => ['required', 'string', 'max:100'],
            'paquete' => ['required', 'file', 'mimes:zip', 'max:204800'],
        ], [
            'paquete.required' => 'Debes seleccionar el ZIP procesado localmente.',
            'paquete.mimes' => 'El archivo debe estar en formato ZIP.',
            'paquete.max' => 'El ZIP no puede superar los 200 MB.',
        ]);

        $file = $request->file('paquete');
        $dominioSlug = Str::slug($data['dominio']);
        $filename = sprintf('%04d-%02d-%s-paquete-%s.zip', (int) $data['anio'], (int) $data['mes'], $dominioSlug, Str::random(8));
        $path = $file->storeAs(sprintf('liquidaciones/paquetes/%04d/%02d/%s', (int) $data['anio'], (int) $data['mes'], $dominioSlug), $filename, 'local');

        $carga = LiquidacionCarga::create([
            'mes' => (int) $data['mes'],
            'anio' => (int) $data['anio'],
            'dominio' => $data['dominio'],
            'archivo_original_path' => $path,
            'archivo_original_nombre' => $file->getClientOriginalName(),
            'estado' => 'pendiente',
            'subida_por_id' => $request->user()->id,
        ]);

        try {
            $importer->process($carga->fresh());
        } catch (\Throwable $e) {
            $carga->update([
                'estado' => 'fallido',
                'total_errores' => 1,
                'errores' => [$e->getMessage()],
                'procesada_at' => now(),
            ]);

            return redirect()
                ->route('liquidaciones.cargas.show', $carga)
                ->with('error', 'No se pudo importar el paquete ZIP: ' . $e->getMessage());
        }

        return redirect()
            ->route('liquidaciones.cargas.show', $carga)
            ->with('status', 'Paquete ZIP importado. Las liquidaciones individuales quedaron disponibles para usuarios cuyo RUT coincida.');
    }

    public function show(LiquidacionCarga $liquidacionCarga): View
    {
        $liquidacionCarga->load('subidaPor');

        $liquidaciones = $liquidacionCarga->liquidaciones()
            ->where('es_reemplazo', true)
            ->orderBy('nombre')
            ->orderBy('rut_normalizado')
            ->orderBy('pagina_origen')
            ->paginate(50);

        return view('liquidaciones.cargas.show', compact('liquidacionCarga', 'liquidaciones'));
    }

    public function descargar(LiquidacionFuncionario $liquidacion): mixed
    {
        abort_unless($liquidacion->archivo_pdf_path && Storage::disk('local')->exists($liquidacion->archivo_pdf_path), 404);

        $filename = sprintf('liquidacion_%04d_%02d_%s_%s_p%04d.pdf', $liquidacion->anio, $liquidacion->mes, $liquidacion->dominio, $liquidacion->rut_normalizado, $liquidacion->pagina_origen);

        return Storage::disk('local')->download($liquidacion->archivo_pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
