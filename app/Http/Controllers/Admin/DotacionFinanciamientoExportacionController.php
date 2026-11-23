<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerarExcelFinanciamiento;
use App\Models\DotacionFinanciamientoExportacion;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class DotacionFinanciamientoExportacionController extends Controller
{
    public function create(Request $request)
    {
        [$anio, $filtros] = $this->datos($request);

        return $this->pantalla($anio, $filtros);
    }

    public function store(Request $request, Dispatcher $bus)
    {
        [$anio, $filtros] = $this->datos($request);
        abort_unless(DotacionFinanciamientoExportacion::disponible(), 503);
        $userId = $request->user()->id;

        try {
            $solicitud = DB::transaction(function () use ($userId, $anio, $filtros, $bus): DotacionFinanciamientoExportacion {
                // Serializa solicitudes simultáneas de la misma cuenta.
                User::query()->lockForUpdate()->findOrFail($userId);
                $activa = DotacionFinanciamientoExportacion::query()->where('user_id', $userId)
                    ->whereIn('estado', ['pendiente', 'procesando'])->where('expires_at', '>', now())->latest()->first();
                if ($activa) {
                    return $activa;
                }

                $nueva = DotacionFinanciamientoExportacion::create([
                    'user_id' => $userId, 'anio' => $anio, 'filtros' => $filtros,
                    'estado' => 'pendiente', 'expires_at' => now()->addHour(),
                ]);
                // La cola y la solicitud se escriben en la misma base de datos.
                $bus->dispatch(new GenerarExcelFinanciamiento($nueva->id));

                return $nueva;
            }, 3);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.dotacion-establecimiento.financiamiento.create', ['anio' => $anio] + $filtros)
                ->withErrors(['financiamiento' => 'No se pudo poner el Excel en cola. Intente nuevamente.']);
        }

        $respuesta = redirect()->route('admin.dotacion-establecimiento.financiamiento.show', $solicitud->id, 303);
        if ($solicitud->anio !== $anio || $solicitud->filtros !== $filtros) {
            $respuesta->with('financiamiento_aviso', 'Ya hay otro Excel en preparación para su cuenta. Al terminar, podrá solicitar el informe con los nuevos filtros.');
        }

        return $respuesta;
    }

    public function show(Request $request, string $exportacion)
    {
        $solicitud = $this->propia($request, $exportacion);
        $estado = $solicitud->estadoActual();
        if ($request->expectsJson()) {
            return response()->json(['estado' => $estado])->header('Cache-Control', 'private, no-store');
        }

        return $this->pantalla($solicitud->anio, $solicitud->filtros, $solicitud, $estado);
    }

    public function download(Request $request, string $exportacion)
    {
        $solicitud = $this->propia($request, $exportacion);
        if ($solicitud->estadoActual() !== 'listo') {
            return redirect()->route('admin.dotacion-establecimiento.financiamiento.show', $solicitud->id);
        }

        return response()->download(Storage::disk('local')->path($solicitud->ruta()), $solicitud->nombreDescarga(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function datos(Request $request): array
    {
        $data = $request->validate([
            'anio' => ['required', 'integer', Rule::in([2026, 2027])],
            'q' => ['nullable', 'string', 'max:150'],
            'comuna' => ['nullable', 'string', 'max:100'],
        ]);

        return [(int) $data['anio'], [
            'q' => trim((string) ($data['q'] ?? '')),
            'comuna' => trim((string) ($data['comuna'] ?? '')),
        ]];
    }

    private function propia(Request $request, string $id): DotacionFinanciamientoExportacion
    {
        abort_unless(DotacionFinanciamientoExportacion::disponible(), 404);

        return DotacionFinanciamientoExportacion::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function pantalla(int $anio, array $filtros, ?DotacionFinanciamientoExportacion $solicitud = null, ?string $estado = null)
    {
        $disponible = $solicitud !== null || DotacionFinanciamientoExportacion::disponible();

        return response()->view('admin.dotacion-establecimiento.exportacion-financiamiento',
            compact('anio', 'filtros', 'solicitud', 'estado', 'disponible'))
            ->header('Cache-Control', 'private, no-store');
    }
}
