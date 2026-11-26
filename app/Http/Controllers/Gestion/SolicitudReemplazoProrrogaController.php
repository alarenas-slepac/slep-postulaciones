<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\SolicitudReemplazoProrroga;
use App\Services\SolicitudReemplazoProrrogaService;
use App\Support\Rut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SolicitudReemplazoProrrogaController extends Controller
{
    public function __construct(private readonly SolicitudReemplazoProrrogaService $servicio) {}

    public function index(Request $request): View
    {
        $estamentos = SolicitudReemplazoProrroga::ESTAMENTOS;
        $filtros = $request->validate([
            'anio' => ['nullable', 'integer', 'between:1000,9998'],
            'estamento' => ['nullable', Rule::in(array_keys($estamentos))],
            'q' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', Rule::in(['pendiente', 'registrada'])],
        ]);
        $anio = (int) ($filtros['anio'] ?? now()->year);
        $estamento = $filtros['estamento'] ?? 'docente';
        $vigencia = $this->servicio->vigencia($anio);
        $casos = $this->servicio->casos($anio);
        $registradas = SolicitudReemplazoProrroga::query()->where('anio_diciembre', $anio)->get();
        foreach ($registradas as $registrada) {
            $casos->put($registrada->estamento.':'.$registrada->rut, $registrada->antecedentes + ['registrada' => $registrada]);
        }

        $busqueda = Str::lower(Str::ascii($filtros['q'] ?? ''));
        $rutBuscado = preg_match('/^[0-9.\s-]+[0-9Kk]?$/D', $busqueda) ? Rut::normalize($busqueda) : null;
        $estado = $filtros['estado'] ?? null;
        $filas = $casos->filter(function (array $caso) use ($busqueda, $rutBuscado, $estado): bool {
            if ($estado && $estado !== (isset($caso['registrada']) ? 'registrada' : 'pendiente')) {
                return false;
            }
            if ($busqueda === '' || ($rutBuscado && str_contains($caso['rut'], $rutBuscado))) {
                return true;
            }

            $establecimientos = collect($caso['periodos'])->map(fn ($periodo) => $periodo['rbd'].' '.$periodo['establecimiento'])->implode(' ');

            return str_contains(Str::lower(Str::ascii($caso['nombre'].' '.$establecimientos)), $busqueda);
        });
        $conteos = $filas->countBy('estamento');
        $filas = $filas->where('estamento', $estamento)->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $paginador = (new LengthAwarePaginator($filas->forPage($pagina, 20), $filas->count(), 20, $pagina, [
            'path' => $request->url(),
        ]))->withQueryString();

        return view('gestion.solicitudes-reemplazo.prorrogas.index', compact(
            'anio', 'estamentos', 'estamento', 'conteos', 'vigencia', 'paginador', 'filtros',
        ));
    }

    public function show(int $anio, string $estamento, string $rut): View
    {
        $registrada = $this->prorroga($anio, $estamento, $rut)->with('registradaPor')->first();
        $caso = $registrada?->antecedentes ?? $this->caso($anio, $estamento, $rut);
        $vigencia = $this->servicio->vigencia($anio);
        $estamentos = SolicitudReemplazoProrroga::ESTAMENTOS;

        return view('gestion.solicitudes-reemplazo.prorrogas.show', compact(
            'anio', 'estamento', 'rut', 'estamentos', 'caso', 'registrada', 'vigencia',
        ));
    }

    public function store(Request $request, int $anio, string $estamento, string $rut): RedirectResponse
    {
        $datos = $request->validate([
            'numero_resolucion' => ['required', 'string', 'max:100'],
            'fecha_resolucion' => ['required', 'date_format:Y-m-d'],
            'resolucion' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'observacion' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($this->prorroga($anio, $estamento, $rut)->exists()) {
            throw ValidationException::withMessages(['resolucion' => 'La prórroga de este estamento y año ya está registrada.']);
        }

        $caso = $this->caso($anio, $estamento, $rut);
        $vigencia = $this->servicio->vigencia($anio);
        $archivo = $datos['resolucion'];
        $ruta = $archivo->storeAs("reemplazos/prorrogas/{$anio}", Str::uuid().'.pdf', 'local');
        if (! $ruta) {
            throw ValidationException::withMessages(['resolucion' => 'No se pudo guardar el PDF. Intenta nuevamente.']);
        }

        try {
            SolicitudReemplazoProrroga::create([
                'anio_diciembre' => $anio,
                'estamento' => $estamento,
                'rut' => $rut,
                'vigencia_desde' => $vigencia['desde'],
                'vigencia_hasta' => $vigencia['hasta'],
                'antecedentes' => $caso,
                'numero_resolucion' => $datos['numero_resolucion'],
                'fecha_resolucion' => $datos['fecha_resolucion'],
                'observacion' => $datos['observacion'] ?? null,
                'resolucion_path' => $ruta,
                'resolucion_nombre' => Str::limit($archivo->getClientOriginalName(), 255, ''),
                'registrada_por_id' => $request->user()->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            Storage::disk('local')->delete($ruta);
            throw ValidationException::withMessages(['resolucion' => 'La prórroga de este estamento y año ya está registrada.']);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($ruta);
            throw $error;
        }

        return redirect()->route('gestion.solicitudes-reemplazo.prorrogas.show', compact('anio', 'estamento', 'rut'))
            ->with('status', 'Prórroga registrada con la resolución adjunta.');
    }

    public function resolucion(SolicitudReemplazoProrroga $prorroga): StreamedResponse
    {
        $disco = Storage::disk('local');
        abort_unless($disco->exists($prorroga->resolucion_path), 404);

        return $disco->download($prorroga->resolucion_path, "resolucion-prorroga-{$prorroga->id}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function prorroga(int $anio, string $estamento, string $rut): Builder
    {
        return SolicitudReemplazoProrroga::query()
            ->where('anio_diciembre', $anio)->where('estamento', $estamento)->where('rut', $rut);
    }

    private function caso(int $anio, string $estamento, string $rut): array
    {
        $caso = $this->servicio->casos($anio, $rut)->get($estamento.':'.$rut);
        abort_if($caso === null, 404, 'Este reemplazo no cumple seis meses de continuidad en el estamento y año seleccionados.');

        return $caso;
    }
}
