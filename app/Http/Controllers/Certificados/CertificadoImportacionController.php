<?php

namespace App\Http\Controllers\Certificados;

use App\Http\Controllers\Controller;
use App\Jobs\ProcesarCertificadoImportacion;
use App\Models\CertificadoImportacion;
use App\Services\Certificados\ContratoHistoricoImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CertificadoImportacionController extends Controller
{
    public function __construct(
        private readonly ContratoHistoricoImportService $importService
    ) {}

    public function index(): View
    {
        $this->autorizarOperador();

        $importaciones = CertificadoImportacion::query()
            ->with('subidaPor')
            ->latest('id')
            ->paginate(20);

        return view('certificados.importaciones.index', compact('importaciones'));
    }

    public function create(): View
    {
        $this->autorizarOperador();

        return view('certificados.importaciones.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autorizarOperador();

        $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'],
        ], [
            'excel.required' => 'Selecciona el archivo histórico de contratos.',
            'excel.mimes' => 'El archivo debe ser Excel (.xlsx o .xls).',
            'excel.max' => 'El archivo no puede superar los 50 MB.',
        ]);

        try {
            $importacion = $this->importService->encolar(
                $request->file('excel'),
                (int) $request->user()->id
            );
            ProcesarCertificadoImportacion::dispatch($importacion->id);

            return redirect()
                ->route('certificados.importaciones.show', $importacion)
                ->with(
                    'status',
                    'Archivo recibido. La importación se procesará en segundo plano.'
                );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('No fue posible encolar la base histórica de certificados', [
                'message' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'excel' => 'No fue posible iniciar la importación.',
            ]);
        }
    }

    public function show(CertificadoImportacion $importacion): View
    {
        $this->autorizarOperador();

        $importacion->load('subidaPor');

        return view('certificados.importaciones.show', compact('importacion'));
    }

    public function activar(CertificadoImportacion $importacion): RedirectResponse
    {
        $this->autorizarOperador();

        abort_unless(
            in_array(
                $importacion->estado,
                ['procesado', 'procesado_con_observaciones'],
                true
            ),
            422,
            'La importación todavía no está disponible para activación.'
        );

        DB::transaction(function () use ($importacion) {
            CertificadoImportacion::query()
                ->where('es_vigente', true)
                ->update(['es_vigente' => false]);
            $importacion->update([
                'es_vigente' => true,
                'activado_at' => now(),
            ]);
        });

        return back()->with(
            'status',
            'La base histórica seleccionada quedó activa para nuevas emisiones.'
        );
    }

    private function autorizarOperador(): void
    {
        $user = request()->user();
        $rolActivo = Str::lower(trim((string) $user?->activeRoleName()));

        abort_unless(
            in_array(
                $rolActivo,
                (array) config('certificados.roles_emision_general', []),
                true
            ),
            403
        );
    }
}
