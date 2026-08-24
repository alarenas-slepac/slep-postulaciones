@php
    $fmt = fn ($value) => \App\Support\DotacionEstablecimientoCalculator::formatHoras($value);
    $sobredotacionItems = collect($sobredotacion['items'] ?? []);
    $sobredotacionResumen = $sobredotacion['resumen'] ?? [];
@endphp

<div class="card dotacion-section mb-4">
    <div class="dotacion-section-header">
        <div class="d-flex align-items-start gap-3">
            <span class="dotacion-icon" style="width:40px;height:40px;background:#dc3545;"><i class="bi bi-person-exclamation"></i></span>
            <div>
                <div class="dotacion-eyebrow">Análisis contractual por docente</div>
                <h2 class="h5 fw-bold mb-1">Detalle sobredotación</h2>
                <div class="text-muted small">Compara las horas de contrato consideradas con las horas asignadas. Las asignaciones cubren primero Planta y luego Contrata, por lo que el saldo de Contrata se identifica antes como sobredotación.</div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Docentes analizados</div><div class="h4 fw-bold mb-0">{{ number_format((int) ($sobredotacionResumen['docentes_analizados'] ?? 0), 0, ',', '.') }}</div><div class="small text-muted">Nómina vigente</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-danger-subtle h-100"><div class="small text-muted">Con sobredotación</div><div class="h4 fw-bold text-danger mb-0">{{ number_format((int) ($sobredotacionResumen['docentes_sobredotacion'] ?? 0), 0, ',', '.') }}</div><div class="small text-muted">Docentes identificados</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Horas contrato totales</div><div class="h4 fw-bold mb-0">{{ $fmt($sobredotacionResumen['horas_contrato_total'] ?? 0) }}</div><div class="small text-muted">Contrato considerado</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-light h-100"><div class="small text-muted">Horas asignadas</div><div class="h4 fw-bold text-success mb-0">{{ $fmt($sobredotacionResumen['horas_asignadas_total'] ?? 0) }}</div><div class="small text-muted">Asignación registrada</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-danger-subtle h-100"><div class="small text-muted">Sobredotación total</div><div class="h4 fw-bold text-danger mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}</div><div class="small text-muted">Horas sin asignación</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-primary-subtle h-100"><div class="small text-muted">Sobredotación Planta</div><div class="h4 fw-bold text-primary mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_planta'] ?? 0) }}</div><div class="small text-muted">Horas titulares</div></div></div>
            <div class="col-xl-2 col-md-4 col-sm-6"><div class="p-3 rounded-4 bg-info-subtle h-100"><div class="small text-muted">Sobredotación Contrata</div><div class="h4 fw-bold text-info mb-0">{{ $fmt($sobredotacionResumen['horas_sobredotacion_contrata'] ?? 0) }}</div><div class="small text-muted">Horas a contrata</div></div></div>
        </div>
    </div>
</div>

<div class="alert alert-info border-0 rounded-4 small">
    <i class="bi bi-info-circle"></i>
    <strong>Regla de prioridad:</strong> para cada docente se imputan primero sus horas asignadas al componente Planta y después al componente Contrata. Las horas excluidas mediante una situación docente no forman parte del contrato considerado en este análisis.
    El cálculo es individual: una sobrecarga de otro docente no compensa horas sin asignar de esta nómina.
</div>

<div class="card dotacion-section">
    <div class="dotacion-section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="dotacion-eyebrow">Docentes con horas contractuales sin asignar</div>
            <h2 class="h5 fw-bold mb-1">Nómina de sobredotación</h2>
            <div class="text-muted small">El listado incluye únicamente docentes cuyo contrato considerado supera sus horas asignadas.</div>
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
                    <th class="text-end">Contrato total</th>
                    <th class="text-end">Planta</th>
                    <th class="text-end">Contrata</th>
                    <th class="text-end">Asignadas</th>
                    <th class="text-end">Sobredotación total</th>
                    <th class="text-end">Sobredotación Planta</th>
                    <th class="text-end">Sobredotación Contrata</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sobredotacionItems as $docente)
                    <tr>
                        <td class="text-nowrap fw-semibold">{{ $docente['rut'] }}</td>
                        <td><div class="fw-bold">{{ $docente['nombre'] }}</div><div class="small text-muted">{{ $docente['tipo_contrato'] }}</div></td>
                        <td>{{ $docente['funcion'] }}</td>
                        <td class="text-end fw-semibold">{{ $fmt($docente['horas_contrato_total']) }}</td>
                        <td class="text-end text-primary">{{ $fmt($docente['horas_contrato_planta']) }}</td>
                        <td class="text-end text-info">{{ $fmt($docente['horas_contrato_contrata']) }}</td>
                        <td class="text-end text-success fw-semibold">{{ $fmt($docente['horas_asignadas_total']) }}</td>
                        <td class="text-end text-danger fw-bold">{{ $fmt($docente['horas_sobredotacion_total']) }}</td>
                        <td class="text-end text-primary fw-semibold">{{ $fmt($docente['horas_sobredotacion_planta']) }}</td>
                        <td class="text-end text-info fw-semibold">{{ $fmt($docente['horas_sobredotacion_contrata']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-5"><i class="bi bi-check-circle text-success fs-3 d-block mb-2"></i>No se identificaron docentes con horas contractuales sin asignar.</td></tr>
                @endforelse
            </tbody>
            @if ($sobredotacionItems->isNotEmpty())
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="7">Total docentes con sobredotación</td>
                        <td class="text-end text-danger">{{ $fmt($sobredotacionResumen['horas_sobredotacion_total'] ?? 0) }}</td>
                        <td class="text-end text-primary">{{ $fmt($sobredotacionResumen['horas_sobredotacion_planta'] ?? 0) }}</td>
                        <td class="text-end text-info">{{ $fmt($sobredotacionResumen['horas_sobredotacion_contrata'] ?? 0) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
