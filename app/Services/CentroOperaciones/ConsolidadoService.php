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
        private readonly UnidadOperacionalService $unidades,
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
        $contextos = $establecimientos->flatMap(function (Establecimiento $establecimiento) {
            $principal = [[
                'clave' => $this->unidades->clave((int) $establecimiento->id, null),
                'establecimiento' => $establecimiento,
                'unidad_codigo' => null,
                'unidad' => null,
            ]];
            $anexos = $this->unidades->paraEstablecimiento($establecimiento)
                ->map(fn (array $unidad, string $codigo) => [
                    'clave' => $this->unidades->clave((int) $establecimiento->id, $codigo),
                    'establecimiento' => $establecimiento,
                    'unidad_codigo' => $codigo,
                    'unidad' => $unidad,
                ])
                ->values()
                ->all();

            return [...$principal, ...$anexos];
        });

        $reportes = CentroOperacionesReporte::query()
            ->whereDate('fecha_reporte', $fecha->toDateString())
            ->where('reportado_en', '<=', $puntoCorte)
            ->with(['servicios', 'afectaciones', 'incidencias', 'reportadoPor'])
            ->orderByDesc('reportado_en')
            ->orderByDesc('id')
            ->get();
        $ultimos = $reportes
            ->unique(fn (CentroOperacionesReporte $reporte) => $this->unidades->clave(
                (int) $reporte->establecimiento_id,
                $reporte->unidad_codigo
            ))
            ->keyBy(fn (CentroOperacionesReporte $reporte) => $this->unidades->clave(
                (int) $reporte->establecimiento_id,
                $reporte->unidad_codigo
            ));

        $incidenciasActivas = CentroOperacionesIncidencia::query()
            ->where('created_at', '<=', $puntoCorte)
            ->where(function ($query) use ($puntoCorte) {
                $query->whereNull('resuelta_en')->orWhere('resuelta_en', '>', $puntoCorte);
            })
            ->with(['establecimiento', 'reporte'])
            ->orderByDesc('created_at')
            ->get();
        $incidenciasPorContexto = $incidenciasActivas->groupBy(fn (CentroOperacionesIncidencia $incidencia) =>
            $this->unidades->clave((int) $incidencia->establecimiento_id, $incidencia->unidad_codigo)
        );

        $matriculas = $this->datosBase->matriculasPara($establecimientos, $fecha->year);
        $dotaciones = $this->datosBase->dotacionesPara($establecimientos);

        $filas = $contextos->map(function (array $contexto) use (
            $ultimos,
            $incidenciasPorContexto,
            $matriculas,
            $dotaciones
        ) {
            /** @var Establecimiento $establecimiento */
            $establecimiento = $contexto['establecimiento'];
            $unidadCodigo = $contexto['unidad_codigo'];
            $unidad = $contexto['unidad'];
            /** @var CentroOperacionesReporte|null $reporte */
            $reporte = $ultimos->get($contexto['clave']);
            $activas = $incidenciasPorContexto->get($contexto['clave'], collect());
            $estado = $reporte ? $this->estadoService->paraReporte($reporte, $activas) : 'sin_reporte';
            $matriculaBase = $unidadCodigo
                ? (int) ($unidad['matricula_total'] ?? 0)
                : (int) $matriculas[$establecimiento->id]['total'];
            $docentesBase = $unidadCodigo
                ? (int) ($unidad['docentes_total'] ?? 0)
                : (int) $dotaciones[$establecimiento->id]['docentes'];
            $asistentesBase = $unidadCodigo
                ? (int) ($unidad['asistentes_total'] ?? 0)
                : (int) $dotaciones[$establecimiento->id]['asistentes'];

            return [
                'id' => $contexto['clave'],
                'rbd' => $establecimiento->rbd,
                'nombre' => $unidad['nombre_reporte'] ?? $establecimiento->nombre_establecimiento,
                'comuna' => $establecimiento->comuna ?: 'Sin comuna',
                'logo_url' => $establecimiento->admisionPerfil?->logoUrl(),
                'latitud' => $establecimiento->latitud !== null ? (float) $establecimiento->latitud : null,
                'longitud' => $establecimiento->longitud !== null ? (float) $establecimiento->longitud : null,
                'estado' => $estado,
                'reporte_id' => $reporte?->id,
                'reportado_en' => $reporte?->reportado_en?->toIso8601String(),
                'matricula_total' => $reporte?->matricula_total ?? $matriculaBase,
                'estudiantes_presentes' => $reporte?->estudiantes_presentes,
                'docentes_total' => $reporte?->docentes_total ?? $docentesBase,
                'docentes_presentes' => $reporte?->docentes_presentes,
                'asistentes_total' => $reporte?->asistentes_total ?? $asistentesBase,
                'asistentes_presentes' => $reporte?->asistentes_presentes,
                'incidencias_activas' => $activas->count(),
                'unidad_codigo' => $unidadCodigo,
                'servicios' => $reporte?->servicios->mapWithKeys(fn ($servicio) => [
                    $servicio->servicio => $servicio->estado,
                ])->all() ?? [],
            ];
        });

        $reportados = $filas->whereNotNull('reporte_id');
        $unidadesConfiguradas = $contextos->whereNotNull('unidad_codigo');
        $matriculaUnidades = (int) $unidadesConfiguradas->sum(fn (array $contexto) =>
            (int) ($contexto['unidad']['matricula_total'] ?? 0)
        );
        $dotacionUnidades = (int) $unidadesConfiguradas->sum(fn (array $contexto) =>
            (int) ($contexto['unidad']['docentes_total'] ?? 0)
                + (int) ($contexto['unidad']['asistentes_total'] ?? 0)
        );
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
            'matricula_territorial' => (int) collect($matriculas)->sum('total') + $matriculaUnidades,
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
            ) + $dotacionUnidades,
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
                'establecimiento' => $incidencia->unidad_codigo
                    ? ($incidencia->reporte?->establecimiento_nombre
                        ?? $this->unidades->nombreReporte($incidencia->establecimiento, $incidencia->unidad_codigo))
                    : ($incidencia->establecimiento?->nombre_establecimiento
                        ?? $incidencia->reporte?->establecimiento_nombre),
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
