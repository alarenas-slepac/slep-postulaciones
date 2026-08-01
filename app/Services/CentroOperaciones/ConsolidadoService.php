<?php

namespace App\Services\CentroOperaciones;

use App\Models\CentroOperacionesIncidencia;
use App\Models\CentroOperacionesReporte;
use App\Models\Establecimiento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ConsolidadoService
{
    public function __construct(
        private readonly DatosBaseService $datosBase,
        private readonly EstadoService $estadoService,
    ) {
    }

    /** @return array<string, mixed> */
    public function paraFecha(CarbonImmutable $fecha): array
    {
        $zona = config('centro_operaciones.timezone');
        $hoy = CarbonImmutable::now($zona);
        $puntoCorte = $fecha->isSameDay($hoy) ? $hoy : $fecha->endOfDay();
        $establecimientos = Establecimiento::query()
            ->with('admisionPerfil:id,establecimiento_id,logo_path')
            ->orderBy('comuna')
            ->orderBy('nombre_establecimiento')
            ->get();

        $reportes = CentroOperacionesReporte::query()
            ->whereDate('fecha_reporte', $fecha->toDateString())
            ->where('reportado_en', '<=', $puntoCorte)
            ->with(['servicios', 'afectaciones', 'incidencias', 'reportadoPor'])
            ->orderByDesc('reportado_en')
            ->orderByDesc('id')
            ->get();
        $ultimos = $reportes->unique('establecimiento_id')->keyBy('establecimiento_id');

        $incidenciasActivas = CentroOperacionesIncidencia::query()
            ->where('created_at', '<=', $puntoCorte)
            ->where(function ($query) use ($puntoCorte) {
                $query->whereNull('resuelta_en')->orWhere('resuelta_en', '>', $puntoCorte);
            })
            ->with(['establecimiento', 'reporte'])
            ->orderByDesc('created_at')
            ->get();
        $incidenciasPorEstablecimiento = $incidenciasActivas->groupBy('establecimiento_id');

        $matriculas = $this->datosBase->matriculasPara($establecimientos, $fecha->year);
        $dotaciones = $this->datosBase->dotacionesPara($establecimientos);

        $filas = $establecimientos->map(function (Establecimiento $establecimiento) use (
            $ultimos,
            $incidenciasPorEstablecimiento,
            $matriculas,
            $dotaciones
        ) {
            /** @var CentroOperacionesReporte|null $reporte */
            $reporte = $ultimos->get($establecimiento->id);
            $activas = $incidenciasPorEstablecimiento->get($establecimiento->id, collect());
            $estado = $reporte ? $this->estadoService->paraReporte($reporte, $activas) : 'sin_reporte';

            return [
                'id' => $establecimiento->id,
                'rbd' => $establecimiento->rbd,
                'nombre' => $establecimiento->nombre_establecimiento,
                'comuna' => $establecimiento->comuna ?: 'Sin comuna',
                'logo_url' => $establecimiento->admisionPerfil?->logoUrl(),
                'latitud' => $establecimiento->latitud !== null ? (float) $establecimiento->latitud : null,
                'longitud' => $establecimiento->longitud !== null ? (float) $establecimiento->longitud : null,
                'estado' => $estado,
                'reporte_id' => $reporte?->id,
                'reportado_en' => $reporte?->reportado_en?->toIso8601String(),
                'matricula_total' => $reporte?->matricula_total ?? $matriculas[$establecimiento->id]['total'],
                'estudiantes_presentes' => $reporte?->estudiantes_presentes,
                'docentes_total' => $reporte?->docentes_total ?? $dotaciones[$establecimiento->id]['docentes'],
                'docentes_presentes' => $reporte?->docentes_presentes,
                'asistentes_total' => $reporte?->asistentes_total ?? $dotaciones[$establecimiento->id]['asistentes'],
                'asistentes_presentes' => $reporte?->asistentes_presentes,
                'incidencias_activas' => $activas->count(),
                'servicios' => $reporte?->servicios->mapWithKeys(fn ($servicio) => [
                    $servicio->servicio => $servicio->estado,
                ])->all() ?? [],
            ];
        });

        $reportados = $filas->whereNotNull('reporte_id');
        $metricas = [
            'establecimientos_total' => $filas->count(),
            'reportados' => $reportados->count(),
            'operativos' => $filas->where('estado', 'operativo')->count(),
            'alertas' => $filas->where('estado', 'alerta')->count(),
            'criticos' => $filas->where('estado', 'critico')->count(),
            'sin_reporte' => $filas->where('estado', 'sin_reporte')->count(),
            'cobertura_reportes' => $this->porcentaje($reportados->count(), $filas->count()),
            'estudiantes_presentes' => (int) $reportados->sum('estudiantes_presentes'),
            'matricula_reportada' => (int) $reportados->sum('matricula_total'),
            'matricula_territorial' => (int) collect($matriculas)->sum('total'),
            'asistencia_estudiantes' => $this->porcentaje(
                (int) $reportados->sum('estudiantes_presentes'),
                (int) $reportados->sum('matricula_total')
            ),
            'funcionarios_presentes' => (int) $reportados->sum(fn ($fila) =>
                (int) $fila['docentes_presentes'] + (int) $fila['asistentes_presentes']
            ),
            'dotacion_reportada' => (int) $reportados->sum(fn ($fila) =>
                (int) $fila['docentes_total'] + (int) $fila['asistentes_total']
            ),
            'dotacion_territorial' => (int) collect($dotaciones)->sum(fn ($fila) =>
                (int) $fila['docentes'] + (int) $fila['asistentes']
            ),
            'asistencia_funcionarios' => $this->porcentaje(
                (int) $reportados->sum(fn ($fila) =>
                    (int) $fila['docentes_presentes'] + (int) $fila['asistentes_presentes']
                ),
                (int) $reportados->sum(fn ($fila) =>
                    (int) $fila['docentes_total'] + (int) $fila['asistentes_total']
                )
            ),
            'incidencias_activas' => $incidenciasActivas->count(),
            'incidencias_del_dia' => $reportes->sum(fn (CentroOperacionesReporte $reporte) => $reporte->incidencias->count()),
        ];

        $comunas = $filas->groupBy('comuna')->map(function (Collection $grupo, string $comuna) {
            $reportados = $grupo->whereNotNull('reporte_id');

            return [
                'comuna' => $comuna,
                'establecimientos' => $grupo->count(),
                'reportados' => $reportados->count(),
                'operativos' => $grupo->where('estado', 'operativo')->count(),
                'alertas' => $grupo->where('estado', 'alerta')->count(),
                'criticos' => $grupo->where('estado', 'critico')->count(),
                'sin_reporte' => $grupo->where('estado', 'sin_reporte')->count(),
                'asistencia' => $this->porcentaje(
                    (int) $reportados->sum('estudiantes_presentes'),
                    (int) $reportados->sum('matricula_total')
                ),
            ];
        })->values();

        $servicios = collect(config('centro_operaciones.servicios'))->map(function ($meta, string $codigo) use ($reportados) {
            $estados = $reportados->map(fn ($fila) => $fila['servicios'][$codigo] ?? null)->filter();

            return [
                'codigo' => $codigo,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'operativos' => $estados->filter(fn ($estado) => $estado === 'operativo')->count(),
                'alertas' => $estados->filter(fn ($estado) => $estado === 'alerta')->count(),
                'criticos' => $estados->filter(fn ($estado) => $estado === 'critico')->count(),
                'porcentaje_operativo' => $this->porcentaje(
                    $estados->filter(fn ($estado) => $estado === 'operativo')->count(),
                    $estados->count()
                ),
            ];
        })->values();

        $alertas = $filas->whereIn('estado', ['alerta', 'critico'])
            ->sortByDesc(fn ($fila) => $this->estadoService->orden($fila['estado']))
            ->values();

        return [
            'fecha' => $fecha->toDateString(),
            'actualizado_en' => $hoy->toIso8601String(),
            'metricas' => $metricas,
            'comunas' => $comunas->all(),
            'establecimientos' => $filas->values()->all(),
            'servicios' => $servicios->all(),
            'alertas' => $alertas->all(),
            'incidencias_activas' => $incidenciasActivas->map(fn (CentroOperacionesIncidencia $incidencia) => [
                'id' => $incidencia->id,
                'establecimiento' => $incidencia->establecimiento?->nombre_establecimiento
                    ?? $incidencia->reporte?->establecimiento_nombre,
                'comuna' => $incidencia->establecimiento?->comuna
                    ?? $incidencia->reporte?->establecimiento_comuna,
                'tipo' => $incidencia->tipo,
                'label' => config("centro_operaciones.incidencias.{$incidencia->tipo}.label", $incidencia->tipo),
                'severidad' => $incidencia->severidad,
                'descripcion' => $incidencia->descripcion,
                'creada_en' => $incidencia->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function porcentaje(int $parte, int $total): float
    {
        return $total > 0 ? round(($parte / $total) * 100, 1) : 0.0;
    }
}
