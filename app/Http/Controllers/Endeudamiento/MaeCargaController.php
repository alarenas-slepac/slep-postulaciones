<?php

namespace App\Http\Controllers\Endeudamiento;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMaeCargaImport;
use App\Models\MaeCarga;
use App\Services\MaeImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class MaeCargaController extends Controller
{
    public function __construct(private readonly MaeImportService $importService)
    {
        $this->middleware(['auth', 'ensure.role:admin']);
    }

    public function index(Request $request): View
    {
        $anio = (int) $request->integer('anio', 0);
        $mes = (int) $request->integer('mes', 0);
        $dominio = trim((string) $request->get('dominio', ''));
        $estado = trim((string) $request->get('estado', ''));

        $items = MaeCarga::query()
            ->with(['subidaPor', 'reemplazaCarga'])
            ->when($anio > 0, fn($query) => $query->where('anio', $anio))
            ->when($mes > 0, fn($query) => $query->where('mes', $mes))
            ->when($dominio !== '', fn($query) => $query->where('dominio', $dominio))
            ->when($estado !== '', fn($query) => $query->where('estado', $estado))
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->orderBy('dominio')
            ->orderByDesc('version')
            ->paginate(20)
            ->withQueryString();

        $dominios = MaeCarga::query()->distinct()->orderBy('dominio')->pluck('dominio');
        $anios = MaeCarga::query()->distinct()->orderByDesc('anio')->pluck('anio');
        $estados = collect(['pendiente', 'procesando', 'procesado', 'procesado_con_observaciones', 'fallido']);

        return view('endeudamiento.cargas.index', compact('items', 'dominios', 'anios', 'anio', 'mes', 'dominio', 'estado', 'estados'));
    }

    public function create(): View
    {
        $dominios = ['Coronel', 'Lota', 'San Pedro', 'Santa Juana', 'JUNJI', 'Administración Central'];

        return view('endeudamiento.cargas.create', compact('dominios'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2024', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'dominio' => ['required', 'string', 'max:100'],
            'motivo_reemplazo' => ['nullable', 'string', 'max:500'],
            'excel' => ['required', 'file', 'mimes:xlsx,xls'],
        ], [
            'excel.required' => 'Debes seleccionar un archivo MAE.',
            'excel.mimes' => 'El archivo debe ser Excel (.xlsx o .xls).',
        ]);

        try {
            $carga = $this->importService->enqueueImport($request->file('excel'), $data, (int) $request->user()->id);
            ProcessMaeCargaImport::dispatch($carga->id);

            return redirect()
                ->route('endeudamiento.cargas.show', $carga)
                ->with('status', 'Carga MAE recibida correctamente. La importación quedó en cola para procesarse en segundo plano.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error importando MAE de endeudamiento', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'excel' => 'No fue posible importar el archivo MAE. Revisa la estructura del archivo y vuelve a intentar.',
                ]);
        }
    }

    public function show(MaeCarga $maeCarga): View
    {
        $maeCarga->load(['subidaPor', 'reemplazaCarga']);

        $versiones = MaeCarga::query()
            ->where('anio', $maeCarga->anio)
            ->where('mes', $maeCarga->mes)
            ->where('dominio', $maeCarga->dominio)
            ->orderByDesc('version')
            ->get();

        $resumen = [
            'registros' => $maeCarga->registros()->count(),
            'descuentos' => $maeCarga->registros()->withCount('descuentos')->get()->sum('descuentos_count'),
            'otros_descuentos' => $maeCarga->registros()->withCount('otrosDescuentos')->get()->sum('otros_descuentos_count'),
            'cuotas_importaciones' => $maeCarga->cuotasImportaciones()->count(),
            'descuentos_con_cuota' => DB::table('mae_registro_descuentos as d')
                ->join('mae_registros as r', 'r.id', '=', 'd.mae_registro_id')
                ->where('r.mae_carga_id', $maeCarga->id)
                ->whereNotNull('d.cuota_actual')
                ->count(),
        ];

        $muestra = $maeCarga->registros()->latest('id')->limit(15)->get();

        return view('endeudamiento.cargas.show', compact('maeCarga', 'versiones', 'resumen', 'muestra'));
    }

    public function activarVersion(MaeCarga $maeCarga): RedirectResponse
    {
        MaeCarga::query()
            ->where('anio', $maeCarga->anio)
            ->where('mes', $maeCarga->mes)
            ->where('dominio', $maeCarga->dominio)
            ->update(['es_vigente' => false, 'updated_at' => now()]);

        $maeCarga->update(['es_vigente' => true, 'updated_at' => now()]);

        return redirect()
            ->route('endeudamiento.cargas.show', $maeCarga)
            ->with('status', 'La versión seleccionada quedó activa para el período y dominio.');
    }
}
