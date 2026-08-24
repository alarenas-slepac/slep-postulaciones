@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $tipoDetalle = in_array($sobredotacionTipo ?? 'aula', ['aula', 'pie'], true) ? ($sobredotacionTipo ?? 'aula') : 'aula';
    $detalle = $sobredotacion[$tipoDetalle] ?? [];
    $sobredotacionItems = collect($detalle['items'] ?? []);
    $sobredotacionResumen = $detalle['resumen'] ?? [];
    $formula = $detalle['formula'] ?? [];
    $esAula = $tipoDetalle === 'aula';
    $resultadoEsSobredotacion = (float) ($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) > 0.01;
    $resultadoEsNecesidad = (float) ($sobredotacionResumen['horas_necesarias_pendientes'] ?? 0) > 0.01;
@endphp

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header">
        <div class="d-flex align-items-start gap-3">
            <span class="dotacion-icon" style="width:40px;height:40px;background:#dc3545;"><i class="bi bi-person-exclamation"></i></span>
            <div>
                <div class="dotacion-eyebrow">Análisis contractual por docente</div>
                <h2 class="h5 fw-bold mb-1">Detalle sobredotación</h2>
                <div class="text-muted small">Separa la Dotación General de la Dotación PIE y distribuye cada necesidad entre docentes, priorizando globalmente las horas Planta antes que Contrata.</div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <ul class="nav nav-pills gap-2 mb-4" role="tablist">
            <li class="nav-item">
                <a class="nav-link {{ $esAula ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'sobredotacion', 'sobredotacion_tipo' => 'aula']) }}">
                    <i class="bi bi-easel2"></i> Horas contrato Aula
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ !$esAula ? 'active' : '' }}" href="{{ route('admin.dotacion-establecimiento.show', [$establecimiento, 'anio' => $anio, 'tab' => 'sobredotacion', 'sobredotacion_tipo' => 'pie']) }}">
                    <i class="bi bi-universal-access"></i> Horas contrato docente PIE
                </a>
            </li>
        </ul>

        <div class="alert {{ $resultadoEsSobredotacion ? 'alert-danger' : ($resultadoEsNecesidad ? 'alert-success' : 'alert-primary') }} border-0 rounded-4">
            <div class="fw-bold mb-1">{{ $esAula ? 'Dotación General' : 'Dotación PIE' }}</div>
            @if ($esAula)
                <div>(Contrato plan + trabajo colaborativo PIE + bloque normativo) − (contrato Aula + bloque declarado).</div>
                <div class="fw-semibold mt-1">({{ $fmt($formula['contrato_plan_pie'] ?? 0) }} + {{ $fmt($formula['bloque_normativo'] ?? 0) }}) − ({{ $fmt($formula['contrato_aula'] ?? 0) }} + {{ $fmt($formula['bloque_declarado'] ?? 0) }})</div>
            @else
                <div>Horas de contrato PIE necesarias − horas contrato docente PIE.</div>
                <div class="fw-semibold mt-1">{{ $fmt($formula['contrato_pie_necesario'] ?? 0) }} − {{ $fmt($formula['contrato_docente_pie'] ?? 0) }}</div>
            @endif
            <div class="fs-4 fw-bold mt-2">
                @if ($resultadoEsSobredotacion)
                    Horas de sobredotación: {{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}
                @elseif ($resultadoEsNecesidad)
                    Horas necesarias: +{{ $fmt($sobredotacionResumen['horas_necesarias_pendientes'] ?? 0) }}
                @else
                    Dotación cuadrada: 0
                @endif
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Docentes analizados</div><div class="h4 fw-bold mb-0">{{ number_format((int) ($sobredotacionResumen['docentes_analizados'] ?? 0), 0, ',', '.') }}</div><div class="small text-muted">Con horas en este bloque</div></div></div>
            <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-danger-subtle h-100"><div class="small text-muted">Con sobredotación</div><div class="h4 fw-bold text-danger mb-0">{{ number_format((int) ($sobredotacionResumen['docentes_sobredotacion'] ?? 0), 0, ',', '.') }}</div><div class="small text-muted">Docentes identificados</div></div></div>
            <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Dotación considerada</div><div class="h4 fw-bold mb-0">{{ $fmt($sobredotacionResumen['horas_dotacion_total'] ?? 0) }}</div><div class="small text-muted">{{ $esAula ? 'Contrato Aula + bloque declarado' : 'Contrato docente PIE' }}</div></div></div>
            <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Horas necesarias</div><div class="h4 fw-bold mb-0">{{ $fmt($sobredotacionResumen['horas_necesarias_total'] ?? 0) }}</div><div class="small text-muted">Necesidad del bloque</div></div></div>
            <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Asignadas registradas</div><div class="h4 fw-bold text-success mb-0">{{ $fmt($sobredotacionResumen['horas_asignadas_registradas'] ?? 0) }}</div><div class="small text-muted">Usadas para ordenar docentes</div></div></div>
            <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-danger-subtle h-100"><div class="small text-muted">Sobredotación total</div><div class="h4 fw-bold text-danger mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}</div><div class="small text-muted">Conciliada con el resumen</div></div></div>
            <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-primary-subtle h-100"><div class="small text-muted">Sobredotación Planta</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_planta'] ?? 0) }}</div><div class="small text-muted">Horas titulares</div></div></div>
            <div class="col-xl-3 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-info-subtle h-100"><div class="small text-muted">Sobredotación Contrata</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_contrata'] ?? 0) }}</div><div class="small text-muted">Horas a contrata</div></div></div>
        </div>
    </div>
</div>

<div class="alert alert-info border-0 rounded-4 small">
    <i class="bi bi-info-circle"></i>
    <strong>Regla de prioridad:</strong> la necesidad total se cubre primero con horas Planta y luego con horas Contrata. Dentro de cada calidad contractual se priorizan los docentes con más horas asignadas en el bloque analizado. Las horas PIE se separan de Aula y las exclusiones docentes continúan descontadas del contrato vigente.
</div>

@if ($sobredotacionResumen['tiene_ajuste_no_asociado'] ?? false)
    <div class="alert alert-warning border-0 rounded-4 small">
        <i class="bi bi-exclamation-triangle"></i> Existe una diferencia entre el total institucional y las horas individualizables. Se muestra como un registro “no asociado a docente” para conservar la conciliación y facilitar su revisión.
    </div>
@endif

<div class="card dotacion-section">
    <div class="dotacion-section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="dotacion-eyebrow">{{ $esAula ? 'Contrato Aula y bloque declarado' : 'Contrato docente PIE' }}</div>
            <h2 class="h5 fw-bold mb-1">Nómina de sobredotación</h2>
            <div class="text-muted small">El listado contiene sólo los docentes con horas remanentes después de distribuir la necesidad del bloque.</div>
        </div>
        <span class="badge rounded-pill text-bg-danger">{{ $sobredotacionItems->count() }} registro(s)</span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>RUT</th>
                    <th>Docente</th>
                    <th>Función</th>
                    <th class="text-end">{{ $esAula ? 'Contrato Aula' : 'Contrato docente PIE' }}</th>
                    @if ($esAula)<th class="text-end">Bloque declarado</th>@endif
                    <th class="text-end">Dotación considerada</th>
                    <th class="text-end">Asignadas registradas</th>
                    <th class="text-end">Necesidad cubierta</th>
                    <th class="text-end">Sobredotación total</th>
                    <th class="text-end">Planta</th>
                    <th class="text-end">Contrata</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sobredotacionItems as $docente)
                    <tr class="{{ ($docente['es_ajuste'] ?? false) ? 'table-warning' : '' }}">
                        <td class="text-nowrap fw-semibold">{{ $docente['rut'] }}</td>
                        <td><div class="fw-bold">{{ $docente['nombre'] }}</div><div class="small text-muted">{{ $docente['tipo_contrato'] }}</div></td>
                        <td>{{ $docente['funcion'] }}</td>
                        <td class="text-end fw-semibold">{{ $fmt($docente['horas_contrato_categoria']) }}</td>
                        @if ($esAula)<td class="text-end text-secondary">{{ $fmt($docente['horas_bloque_declarado']) }}</td>@endif
                        <td class="text-end fw-semibold">{{ $fmt($docente['horas_dotacion_total']) }}</td>
                        <td class="text-end text-success">{{ $fmt($docente['horas_asignadas_relevantes']) }}</td>
                        <td class="text-end">{{ $fmt($docente['horas_necesidad_cubierta']) }}</td>
                        <td class="text-end text-danger fw-bold">{{ $fmt($docente['horas_sobredotacion_total']) }}</td>
                        <td class="text-end text-primary fw-semibold">{{ $fmt($docente['horas_sobredotacion_planta']) }}</td>
                        <td class="text-end text-info fw-semibold">{{ $fmt($docente['horas_sobredotacion_contrata']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $esAula ? 11 : 10 }}" class="text-center text-muted py-5"><i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>No se identificaron docentes con sobredotación en este bloque.</td></tr>
                @endforelse
            </tbody>
            @if ($sobredotacionItems->isNotEmpty())
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="{{ $esAula ? 8 : 7 }}">Total sobredotación {{ $esAula ? 'Aula' : 'PIE' }}</td>
                        <td class="text-end text-danger">{{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}</td>
                        <td class="text-end text-primary">{{ $fmt($sobredotacionResumen['horas_sobredotacion_planta'] ?? 0) }}</td>
                        <td class="text-end text-info">{{ $fmt($sobredotacionResumen['horas_sobredotacion_contrata'] ?? 0) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
