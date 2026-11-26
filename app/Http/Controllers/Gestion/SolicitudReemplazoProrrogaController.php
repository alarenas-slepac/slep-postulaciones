<?php

namespace App\Http\Controllers\Gestion;

use App\Exports\ProrrogasReemplazosExport;
use App\Http\Controllers\Controller;
use App\Models\OrdenTrabajoCorrelativo;
use App\Models\SolicitudReemplazoProrroga;
use App\Services\OrdenTrabajoPdfService;
use App\Services\RestrictedRutService;
use App\Services\SolicitudReemplazoProrrogaService;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    public function index(Request $request): View|StreamedResponse
    {
        $estamentos = SolicitudReemplazoProrroga::ESTAMENTOS;
        $filtros = $request->validate([
            'anio' => ['nullable', 'integer', 'between:1000,9998'],
            'estamento' => ['nullable', Rule::in(array_keys($estamentos))],
            'q' => ['nullable', 'string', 'max:150'],
            'estado' => ['nullable', Rule::in(['pendiente', 'registrada'])],
            'area_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $anio = (int) ($filtros['anio'] ?? now()->year);
        $estamento = $filtros['estamento'] ?? 'docente';
        $vigencia = $this->servicio->vigencia($anio, $estamento);
        $casos = $this->servicio->casos($anio);
        $casos = $casos->replace($this->expedientes($anio));
        $areasJunji = $casos->where('estamento', 'junji')->pluck('ultimo_reemplazo.cargo', 'ultimo_reemplazo.area_id')
            ->filter()->sort(SORT_NATURAL | SORT_FLAG_CASE);

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
        if ($estamento === 'junji' && isset($filtros['area_id'])) {
            $filas = $filas->where('ultimo_reemplazo.area_id', (int) $filtros['area_id'])->values();
            $conteos->put('junji', $filas->count());
        }
        if ($request->boolean('exportar')) {
            return app(ProrrogasReemplazosExport::class)->download($filas, $anio, $estamento, $vigencia);
        }
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $paginador = (new LengthAwarePaginator($filas->forPage($pagina, 20), $filas->count(), 20, $pagina, [
            'path' => $request->url(),
        ]))->withQueryString();

        return view('gestion.solicitudes-reemplazo.prorrogas.index', compact(
            'anio', 'estamentos', 'estamento', 'conteos', 'vigencia', 'paginador', 'filtros', 'areasJunji',
        ));
    }

    public function show(int $anio, string $estamento, string $rut): View
    {
        $caso = $this->expedientes($anio, $rut)->get($estamento.':'.$rut) ?? $this->caso($anio, $estamento, $rut);
        $expediente = $caso['expediente'] ?? null;
        $registrada = $caso['registrada'] ?? null;
        $ordenDisponible = $expediente?->orden_trabajo_pdf_path && Storage::disk('local')->exists($expediente->orden_trabajo_pdf_path);
        $vigencia = $this->servicio->vigencia($anio, $estamento);
        $estamentos = SolicitudReemplazoProrroga::ESTAMENTOS;

        return view('gestion.solicitudes-reemplazo.prorrogas.show', compact(
            'anio', 'estamento', 'rut', 'estamentos', 'caso', 'expediente', 'registrada', 'vigencia', 'ordenDisponible',
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
        $archivo = $datos['resolucion'];
        $ruta = $archivo->storeAs("reemplazos/prorrogas/{$anio}", Str::uuid().'.pdf', 'local');
        if (! $ruta) {
            throw ValidationException::withMessages(['resolucion' => 'No se pudo guardar el PDF. Intenta nuevamente.']);
        }

        try {
            DB::transaction(function () use ($anio, $estamento, $rut, $datos, $ruta, $archivo, $request): void {
                $expediente = $this->bloquearOCrearExpediente($anio, $estamento, $rut);
                if ($expediente->tieneResolucion()) {
                    throw ValidationException::withMessages(['resolucion' => 'La prórroga de este estamento y año ya está registrada.']);
                }
                $expediente->update([
                    'numero_resolucion' => $datos['numero_resolucion'],
                    'fecha_resolucion' => $datos['fecha_resolucion'],
                    'observacion' => $datos['observacion'] ?? null,
                    'resolucion_path' => $ruta,
                    'resolucion_nombre' => Str::limit($archivo->getClientOriginalName(), 255, ''),
                    'registrada_por_id' => $request->user()->id,
                    'resolucion_registrada_at' => now(),
                ]);
            });
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
        abort_unless($prorroga->tieneResolucion() && $disco->exists($prorroga->resolucion_path), 404);

        return $disco->download($prorroga->resolucion_path, "resolucion-prorroga-{$prorroga->id}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function generarOrden(Request $request, SolicitudReemplazoProrroga $prorroga, OrdenTrabajoPdfService $pdf): RedirectResponse
    {
        return $this->emitirOrden($request, $pdf, fn () =>
            SolicitudReemplazoProrroga::query()->lockForUpdate()->findOrFail($prorroga->id));
    }

    public function generarOrdenCaso(Request $request, int $anio, string $estamento, string $rut, OrdenTrabajoPdfService $pdf): RedirectResponse
    {
        return $this->emitirOrden($request, $pdf, fn () => $this->bloquearOCrearExpediente($anio, $estamento, $rut));
    }

    private function emitirOrden(Request $request, OrdenTrabajoPdfService $pdf, Closure $expedienteBloqueado): RedirectResponse
    {
        $archivoGenerado = null;
        try {
            $parametros = DB::transaction(function () use ($request, $expedienteBloqueado, $pdf, &$archivoGenerado): array {
                $registro = $expedienteBloqueado();
                $caso = $this->datosExpediente($registro);
                $estamento = $caso['estamento'];
                $parametros = ['anio' => $registro->anio_diciembre, 'estamento' => $estamento, 'rut' => $registro->rut];
                if ($registro->orden_trabajo_pdf_path && Storage::disk('local')->exists($registro->orden_trabajo_pdf_path)) {
                    return $parametros;
                }
                $ultimo = $caso['ultimo_reemplazo'];
                if ($estamento === 'junji' && ! isset($registro->antecedentes['ultimo_reemplazo'])) {
                    $vigente = $this->caso($registro->anio_diciembre, 'junji', $registro->rut);
                    if ($vigente['ultimo_reemplazo']['establecimiento_id'] !== ($ultimo['establecimiento_id'] ?? null)) {
                        throw ValidationException::withMessages(['orden_trabajo' => 'El establecimiento de la resolución debe coincidir con el que acredita la continuidad JUNJI.']);
                    }
                    $caso = $vigente;
                    $ultimo = $vigente['ultimo_reemplazo'];
                }
                if (empty($ultimo['establecimiento']) || empty($ultimo['cargo']) || empty($ultimo['horas'])
                    || empty($ultimo['financiamiento']) || collect($ultimo['financiamiento'])->contains(fn ($fila) => $fila['fuente'] === '')) {
                    throw ValidationException::withMessages(['orden_trabajo' => 'Completa el establecimiento, cargo, jornada y financiamiento en el reemplazo de origen.']);
                }
                $vigencia = $this->servicio->vigencia($registro->anio_diciembre, $estamento);
                if (app(RestrictedRutService::class)->hasCourtRestrictionRut($registro->rut)
                    || app(RestrictedRutService::class)->hasCourtRestrictionRut($registro->rut, CarbonImmutable::parse($vigencia['desde']))) {
                    throw ValidationException::withMessages(['orden_trabajo' => 'El reemplazante tiene una restricción judicial registrada.']);
                }

                $numero = OrdenTrabajoCorrelativo::reservar(OrdenTrabajoCorrelativo::PRORROGA, $registro->id)->id;
                $ultimo['desglose'] = $this->servicio->financiamiento($ultimo);
                $archivoGenerado = $pdf->generarProrroga($registro, $numero, $ultimo, $vigencia, $this->servicio->motivo($estamento));
                unset($caso['expediente'], $caso['registrada']);
                $registro->update([
                    'antecedentes' => $caso,
                    'orden_trabajo_pdf_path' => $archivoGenerado,
                    'orden_trabajo_emitida_por_id' => $request->user()->id,
                    'orden_trabajo_emitida_at' => now(),
                ]);

                return $parametros;
            });
        } catch (Throwable $error) {
            if ($archivoGenerado) {
                Storage::disk('local')->delete($archivoGenerado);
            }
            throw $error;
        }

        return redirect()->route('gestion.solicitudes-reemplazo.prorrogas.show', $parametros)
            ->with('status', 'Orden de trabajo disponible para descargar.');
    }

    public function descargarOrden(SolicitudReemplazoProrroga $prorroga): StreamedResponse
    {
        $disco = Storage::disk('local');
        $numero = $prorroga->correlativoOrdenTrabajo?->id;
        abort_unless($numero && $prorroga->orden_trabajo_pdf_path && $disco->exists($prorroga->orden_trabajo_pdf_path), 404);

        return $disco->download($prorroga->orden_trabajo_pdf_path, "orden-trabajo-prorroga-{$numero}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function bloquearOCrearExpediente(int $anio, string $estamento, string $rut): SolicitudReemplazoProrroga
    {
        $existente = $this->expedientes($anio, $rut)->get($estamento.':'.$rut)['expediente'] ?? null;
        $consulta = SolicitudReemplazoProrroga::query()->lockForUpdate();
        if ($existente) {
            return $consulta->findOrFail($existente->id);
        }

        $caso = $this->caso($anio, $estamento, $rut);
        $vigencia = $this->servicio->vigencia($anio, $estamento);

        return $consulta->createOrFirst(
            ['anio_diciembre' => $anio, 'estamento' => $estamento, 'rut' => $rut],
            ['vigencia_desde' => $vigencia['desde'], 'vigencia_hasta' => $vigencia['hasta'], 'antecedentes' => $caso],
        );
    }

    private function expedientes(int $anio, ?string $rut = null): Collection
    {
        return SolicitudReemplazoProrroga::query()->where('anio_diciembre', $anio)
            ->when($rut !== null, fn ($query) => $query->where('rut', $rut))
            ->with('registradaPor', 'correlativoOrdenTrabajo')->orderBy('id')->get()
            ->map(fn (SolicitudReemplazoProrroga $registro) => $this->datosExpediente($registro))
            ->keyBy(fn (array $caso) => $caso['estamento'].':'.$caso['rut']);
    }

    private function datosExpediente(SolicitudReemplazoProrroga $registro): array
    {
        $caso = $registro->antecedentes;
        $ultimo = $this->servicio->ultimoReemplazo($caso);
        $caso['ultimo_reemplazo'] = $ultimo;
        $caso['estamento'] = ($ultimo['estamento'] ?? null) === 'junji' ? 'junji' : $registro->estamento;
        $caso['expediente'] = $registro;
        if ($registro->tieneResolucion()) {
            $caso['registrada'] = $registro;
        }

        return $caso;
    }

    private function caso(int $anio, string $estamento, string $rut): array
    {
        $caso = $this->servicio->casos($anio, $rut)->get($estamento.':'.$rut);
        abort_if($caso === null, 404, 'Este reemplazo no cumple seis meses de continuidad en el estamento y año seleccionados.');

        return $caso;
    }
}
