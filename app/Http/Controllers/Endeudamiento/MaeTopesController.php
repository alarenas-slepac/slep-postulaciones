<?php

namespace App\Http\Controllers\Endeudamiento;

use App\Http\Controllers\Controller;
use App\Models\MaeRegistro;
use App\Services\MaeTopesCalculatorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MaeTopesController extends Controller
{
    public function __construct(private readonly MaeTopesCalculatorService $service)
    {
        $this->middleware(['auth', 'ensure.role:admin']);
    }

    public function index(Request $request): View
    {
        $filters = [
            'anio' => (int) $request->integer('anio', 0),
            'mes' => (int) $request->integer('mes', 0),
            'dominio' => trim((string) $request->get('dominio', '')),
            'carga_id' => (int) $request->integer('carga_id', 0),
            'q' => trim((string) $request->get('q', '')),
            'rut' => trim((string) $request->get('rut', '')),
            'nombre' => trim((string) $request->get('nombre', '')),
            'solo_vigentes' => $request->boolean('solo_vigentes', true),
            'estado' => trim((string) $request->get('estado', '')),
        ];

        $options = $this->service->filters();
        $result = null;
        if (
            $filters['anio'] > 0
            || $filters['mes'] > 0
            || $filters['dominio'] !== ''
            || $filters['carga_id'] > 0
            || $filters['q'] !== ''
            || $filters['rut'] !== ''
            || $filters['nombre'] !== ''
            || $filters['estado'] !== ''
        ) {
            $result = $this->service->paginate($filters, 30);
        }

        return view('endeudamiento.topes.index', [
            'filters' => $filters,
            'options' => $options,
            'result' => $result,
        ]);
    }

    public function show(Request $request, MaeRegistro $maeRegistro): View
    {
        $analysis = $this->service->analyzeById((int) $maeRegistro->id);
        abort_unless($analysis !== null, 404);

        return view('endeudamiento.topes.show', [
            'analysis' => $analysis,
            'backUrl' => route('endeudamiento.topes.index', $request->query()),
        ]);
    }

    public function exportPdf(Request $request, MaeRegistro $maeRegistro)
    {
        $analysis = $this->service->analyzeById((int) $maeRegistro->id);
        abort_unless($analysis !== null, 404);

        $periodo = sprintf('%02d-%04d', (int) $analysis['registro']->mes, (int) $analysis['registro']->anio);
        $rut = Str::slug((string) ($analysis['registro']->rut_dv ?: $analysis['registro']->rut ?: 'sin-rut'));
        $filename = sprintf('endeudamiento_detalle_%s_%s.pdf', $rut, $periodo);

        try {
            $pdfContent = Pdf::loadView('pdf.endeudamiento-topes-detalle', [
                'analysis' => $analysis,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape')->output();
        } catch (\Throwable $e) {
            Log::error('Falló la exportación PDF del detalle de cálculo de endeudamiento.', [
                'mae_registro_id' => $maeRegistro->id,
                'query' => $request->query(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return redirect()
                ->route('endeudamiento.topes.show', array_merge($request->query(), ['maeRegistro' => $maeRegistro->id]))
                ->withErrors(['general' => 'No se pudo generar el PDF del detalle de cálculo. Revisa el log del sistema para más detalle.']);
        }

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function export(Request $request)
    {
        $filters = [
            'anio' => (int) $request->integer('anio', 0),
            'mes' => (int) $request->integer('mes', 0),
            'dominio' => trim((string) $request->get('dominio', '')),
            'carga_id' => (int) $request->integer('carga_id', 0),
            'q' => trim((string) $request->get('q', '')),
            'rut' => trim((string) $request->get('rut', '')),
            'nombre' => trim((string) $request->get('nombre', '')),
            'solo_vigentes' => $request->boolean('solo_vigentes', true),
            'estado' => trim((string) $request->get('estado', '')),
        ];

        $filename = 'endeudamiento_topes_' . now()->format('Ymd_His') . '_' . Str::slug(($filters['dominio'] ?: 'general')) . '.xlsx';
        $tmp = storage_path('app/tmp');
        if (!is_dir($tmp) && !mkdir($tmp, 0775, true) && !is_dir($tmp)) {
            Log::error('No se pudo crear storage/app/tmp para exportación de topes.', [
                'filters' => $filters,
                'path' => $tmp,
            ]);

            return redirect()
                ->route('endeudamiento.topes.index', $request->query())
                ->withErrors(['general' => 'No se pudo preparar la carpeta temporal para exportar el archivo.']);
        }

        $path = $tmp . DIRECTORY_SEPARATOR . $filename;

        try {
            $this->service->export($filters, $path);
        } catch (\Throwable $e) {
            Log::error('Falló la exportación de topes imponibles de endeudamiento.', [
                'filters' => $filters,
                'path' => $path,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            if (is_file($path)) {
                @unlink($path);
            }

            return redirect()
                ->route('endeudamiento.topes.index', $request->query())
                ->withErrors(['general' => 'No se pudo generar la exportación Excel. Revisa el log del sistema para más detalle.']);
        }

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }
}
