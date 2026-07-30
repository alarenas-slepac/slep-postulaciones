<?php

namespace App\Http\Controllers\Liquidaciones;

use App\Http\Controllers\Controller;
use App\Models\LiquidacionFuncionario;
use App\Services\Liquidaciones\LiquidacionPdfImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MisLiquidacionesController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'ensure.role:postulante|funcionario']);
    }

    public function index(Request $request): View
    {
        $rutNormalizado = LiquidacionPdfImportService::normalizeRut($request->user()->rut ?? '');

        $items = LiquidacionFuncionario::query()
            ->where('rut_normalizado', $rutNormalizado)
            ->where('es_reemplazo', true)
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderBy('dominio')
            ->orderBy('pagina_origen')
            ->latest('id')
            ->paginate(15);

        return view('liquidaciones.mis.index', compact('items', 'rutNormalizado'));
    }

    public function descargar(Request $request, LiquidacionFuncionario $liquidacion): mixed
    {
        $rutNormalizado = LiquidacionPdfImportService::normalizeRut($request->user()->rut ?? '');

        abort_unless($liquidacion->rut_normalizado === $rutNormalizado, 403);
        abort_unless($liquidacion->es_reemplazo, 403);
        abort_unless($liquidacion->archivo_pdf_path && Storage::disk('local')->exists($liquidacion->archivo_pdf_path), 404);

        $filename = sprintf('mi_liquidacion_%04d_%02d_%s_p%04d.pdf', $liquidacion->anio, $liquidacion->mes, $liquidacion->dominio, $liquidacion->pagina_origen);

        return Storage::disk('local')->download($liquidacion->archivo_pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function ver(Request $request, LiquidacionFuncionario $liquidacion): mixed
    {
        $rutNormalizado = LiquidacionPdfImportService::normalizeRut($request->user()->rut ?? '');

        abort_unless($liquidacion->rut_normalizado === $rutNormalizado, 403);
        abort_unless($liquidacion->es_reemplazo, 403);
        abort_unless($liquidacion->archivo_pdf_path && Storage::disk('local')->exists($liquidacion->archivo_pdf_path), 404);

        $inlineName = sprintf('liquidacion_%04d_%02d_%s_p%04d.pdf', $liquidacion->anio, $liquidacion->mes, $liquidacion->dominio, $liquidacion->pagina_origen);

        return Storage::disk('local')->response($liquidacion->archivo_pdf_path, $inlineName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $inlineName . '"',
        ]);
    }
}
