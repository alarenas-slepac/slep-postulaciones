<?php

namespace App\Http\Controllers\Tramites;

use App\Http\Controllers\Controller;
use App\Models\LicenciaMedicaImportacion;
use App\Models\LicenciaMedicaImportacionError;
use App\Services\LicenciasMedicas\LicenciaSeguimientoImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LicenciaMedicaImportacionErrorController extends Controller
{
    public function index(Request $request)
    {
        $query = LicenciaMedicaImportacionError::query()
            ->with(['importacion', 'licenciaMedica'])
            ->latest('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('q')) {
            $busqueda = trim((string) $request->input('q'));
            $query->where(function ($subquery) use ($busqueda) {
                $subquery->where('folio_recibido', 'like', "%{$busqueda}%")
                    ->orWhere('rut_recibido', 'like', "%{$busqueda}%")
                    ->orWhere('motivo', 'like', "%{$busqueda}%")
                    ->orWhereHas('importacion', fn ($importacion) => $importacion->where('nombre_archivo', 'like', "%{$busqueda}%"));
            });
        }

        $errores = $query->paginate(20, ['*'], 'errores')->withQueryString();
        $metricas = [
            'total' => LicenciaMedicaImportacionError::count(),
            'pendientes' => LicenciaMedicaImportacionError::where('estado', LicenciaMedicaImportacionError::ESTADO_PENDIENTE)->count(),
            'corregidos' => LicenciaMedicaImportacionError::where('estado', LicenciaMedicaImportacionError::ESTADO_CORREGIDO)->count(),
            'resueltos' => LicenciaMedicaImportacionError::where('estado', LicenciaMedicaImportacionError::ESTADO_RESUELTO)->count(),
        ];
        $importaciones = LicenciaMedicaImportacion::query()
            ->withCount('errores')
            ->where('tipo', 'seguimiento_excel')
            ->where(function ($query) {
                $query->where('total_inconsistencias', '>', 0)->orWhereHas('errores');
            })
            ->latest('id')
            ->paginate(10, ['*'], 'cargas')
            ->withQueryString();

        return view('tramites.licencias-medicas.errores-importacion.index', compact('errores', 'metricas', 'importaciones'));
    }

    public function show(LicenciaMedicaImportacionError $errorImportacion)
    {
        $errorImportacion->load(['importacion.usuario', 'licenciaMedica', 'corregidoPor', 'reprocesadoPor']);

        return view('tramites.licencias-medicas.errores-importacion.show', [
            'errorImportacion' => $errorImportacion,
            'valores' => $errorImportacion->valoresEfectivos(),
        ]);
    }

    public function update(
        Request $request,
        LicenciaMedicaImportacionError $errorImportacion,
        LicenciaSeguimientoImportService $servicio
    ) {
        $data = $request->validate([
            'licencia' => ['nullable', 'string', 'max:80'],
            'dv' => ['nullable', 'regex:/^[0-9Kk]$/'],
            'rut' => ['nullable', 'string', 'max:30'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'accion' => ['required', Rule::in(['guardar', 'reprocesar'])],
        ]);

        $servicio->corregirError($errorImportacion, $data, $request->user()->id);
        if ($data['accion'] === 'reprocesar') {
            $servicio->reprocesarError($errorImportacion->fresh(), $request->user()->id);

            return redirect()
                ->route('tramites.licencias-medicas.errores.show', $errorImportacion)
                ->with('success', 'La corrección fue guardada y la fila se reprocesó correctamente.');
        }

        return redirect()
            ->route('tramites.licencias-medicas.errores.show', $errorImportacion)
            ->with('success', 'Corrección guardada. La fila quedó disponible para reproceso.');
    }

    public function reprocesar(
        Request $request,
        LicenciaMedicaImportacionError $errorImportacion,
        LicenciaSeguimientoImportService $servicio
    ) {
        $servicio->reprocesarError($errorImportacion, $request->user()->id);

        return back()->with('success', 'La fila fue reprocesada y vinculada con la licencia resultante.');
    }

    public function indexar(
        LicenciaMedicaImportacion $importacion,
        LicenciaSeguimientoImportService $servicio
    ) {
        try {
            $cantidad = $servicio->indexarErrores($importacion);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'importacion' => 'No fue posible reconstruir los errores. Revise la disponibilidad y el formato del archivo original.',
            ]);
        }

        return redirect()
            ->route('tramites.licencias-medicas.errores.index')
            ->with('success', "Se reconstruyeron {$cantidad} errores desde el archivo original sin reaplicar las filas válidas.");
    }
}
