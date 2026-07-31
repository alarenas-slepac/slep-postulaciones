<?php

namespace App\Http\Controllers\CentroOperaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\CentroOperaciones\GuardarReporteRequest;
use App\Models\CentroOperacionesIncidencia;
use App\Models\CentroOperacionesReporte;
use App\Models\Establecimiento;
use App\Services\CentroOperaciones\DatosBaseService;
use App\Services\CentroOperaciones\ReporteService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReporteController extends Controller
{
    public function __construct(
        private readonly DatosBaseService $datosBase,
        private readonly ReporteService $reportes,
    ) {
    }

    public function create(Request $request): View
    {
        $establecimiento = $this->establecimientoDelUsuario($request);

        return $this->formulario($establecimiento);
    }

    public function store(GuardarReporteRequest $request): RedirectResponse
    {
        $establecimiento = $this->establecimientoDelUsuario($request);
        $reporte = $this->reportes->crear($establecimiento, $request->user(), $request->validated());

        return redirect()
            ->route('centro-operaciones.reportes.show', $reporte)
            ->with('success', 'El reporte diario fue enviado y ya forma parte del consolidado territorial.');
    }

    public function history(Request $request): View
    {
        $query = CentroOperacionesReporte::query()
            ->with(['establecimiento', 'reportadoPor'])
            ->latest('reportado_en');

        if (! $this->puedeVerTerritorio($request)) {
            $establecimiento = $this->establecimientoDelUsuario($request);
            $query->where('establecimiento_id', $establecimiento->id);
        }

        if ($request->filled('fecha')) {
            $request->validate(['fecha' => ['date_format:Y-m-d']]);
            $query->whereDate('fecha_reporte', (string) $request->string('fecha'));
        }

        return view('centro-operaciones.reportes.history', [
            'reportes' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function show(Request $request, CentroOperacionesReporte $reporte): View
    {
        $this->autorizarLectura($request, $reporte);

        return view('centro-operaciones.reportes.show', [
            'reporte' => $reporte->load([
                'establecimiento.admisionPerfil',
                'reportadoPor',
                'servicios',
                'afectaciones',
                'incidencias',
                'incidenciasResueltas',
                'revisiones.editadoPor',
            ]),
            'puedeEditar' => $this->puedeEditar($request, $reporte),
        ]);
    }

    public function edit(Request $request, CentroOperacionesReporte $reporte): View
    {
        abort_unless($this->puedeEditar($request, $reporte), 403);
        $establecimiento = $reporte->establecimiento;
        abort_unless($establecimiento, 422, 'El establecimiento asociado ya no está disponible.');

        return $this->formulario($establecimiento, $reporte->load(['servicios', 'afectaciones', 'incidencias']));
    }

    public function update(
        GuardarReporteRequest $request,
        CentroOperacionesReporte $reporte
    ): RedirectResponse {
        abort_unless($this->puedeEditar($request, $reporte), 403);
        $reporte = $this->reportes->actualizar($reporte, $request->user(), $request->validated());

        return redirect()
            ->route('centro-operaciones.reportes.show', $reporte)
            ->with('success', 'El reporte fue actualizado; la versión anterior quedó registrada en la auditoría.');
    }

    private function formulario(
        Establecimiento $establecimiento,
        ?CentroOperacionesReporte $reporte = null
    ): View {
        $hoy = CarbonImmutable::now(config('centro_operaciones.timezone'));
        $establecimiento->loadMissing('admisionPerfil');
        $perfilAdmision = $establecimiento->admisionPerfil;
        $datosBase = $reporte
            ? [
                'matricula' => ['total' => $reporte->matricula_total, 'fuente' => $reporte->matricula_fuente],
                'dotacion' => [
                    'docentes' => $reporte->docentes_total,
                    'asistentes' => $reporte->asistentes_total,
                    'periodo' => $reporte->padron_periodo,
                ],
            ]
            : $this->datosBase->paraEstablecimiento($establecimiento, $hoy->year);

        $incidenciasActivas = CentroOperacionesIncidencia::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('estado', 'activa')
            ->when($reporte, fn ($query) => $query->where('reporte_id', '!=', $reporte->id))
            ->oldest('created_at')
            ->get();

        return view('centro-operaciones.reportes.form', compact(
            'establecimiento',
            'reporte',
            'datosBase',
            'incidenciasActivas',
            'hoy',
            'perfilAdmision'
        ));
    }

    private function establecimientoDelUsuario(Request $request): Establecimiento
    {
        abort_unless($request->user()?->hasRole(config('centro_operaciones.rol_reporte')), 403);
        abort_unless($request->user()->establecimiento_id, 422, 'El usuario no tiene un establecimiento asociado.');

        return Establecimiento::query()->findOrFail($request->user()->establecimiento_id);
    }

    private function autorizarLectura(Request $request, CentroOperacionesReporte $reporte): void
    {
        if ($this->puedeVerTerritorio($request)) {
            return;
        }

        abort_unless(
            $request->user()?->hasRole(config('centro_operaciones.rol_reporte'))
                && (int) $request->user()->establecimiento_id === (int) $reporte->establecimiento_id,
            403
        );
    }

    private function puedeVerTerritorio(Request $request): bool
    {
        return $request->user()?->hasAnyRole(config('centro_operaciones.roles_visualizacion', [])) === true;
    }

    private function puedeEditar(Request $request, CentroOperacionesReporte $reporte): bool
    {
        $hoy = CarbonImmutable::now(config('centro_operaciones.timezone'))->toDateString();

        return $request->user()?->hasRole(config('centro_operaciones.rol_reporte')) === true
            && (int) $request->user()->establecimiento_id === (int) $reporte->establecimiento_id
            && $reporte->fecha_reporte?->toDateString() === $hoy;
    }
}
