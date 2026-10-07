@php
    $anioSituacion = (int) ($anio ?? $docente['anio']);
    $rutSituacion = \App\Support\DotacionEstablecimientoCalculator::normalizeRut($docente['rut_normalizado'] ?? $docente['rut']);
    $situacionAnterior = ($situacionesDocentesAnteriores ?? [])[$rutSituacion] ?? null;
@endphp
<section class="card border-0 shadow-sm rounded-4 mt-3" aria-labelledby="{{ $collapseId }}-situaciones-titulo">
    <div class="card-body">
        <h3 class="h6 fw-bold mb-3" id="{{ $collapseId }}-situaciones-titulo"><i class="bi bi-person-badge text-primary" aria-hidden="true"></i> Situaciones docentes</h3>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="bg-light border rounded-3 p-3 h-100" data-situacion-anio="{{ $anioSituacion - 1 }}">
                    <h4 class="h6 fw-semibold">Año anterior · {{ $anioSituacion - 1 }}</h4>
                    @if ($situacionAnterior)
                        <div class="fw-semibold">{{ $situacionAnterior->motivo_label }}</div>
                        <div class="small mt-2">Horas no necesarias registradas: <strong>{{ $fmt($situacionAnterior->horas) }} h</strong></div>
                        @if ($situacionAnterior->considerar_dotacion_siguiente !== null)
                            <div class="small mt-1">Continuidad en {{ $anioSituacion }}: <strong>{{ $situacionAnterior->considerar_dotacion_siguiente ? 'Sí' : 'No' }}</strong></div>
                        @endif
                        @if ($situacionAnterior->conservar_horas_necesarias !== null)
                            <div class="small mt-1">Conservar horas necesarias en {{ $anioSituacion }}: <strong>{{ $situacionAnterior->conservar_horas_necesarias ? 'Sí' : 'No' }}</strong></div>
                        @endif
                    @else
                        <div class="text-muted">Sin situación registrada.</div>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-light border rounded-3 p-3 h-100" data-situacion-anio="{{ $anioSituacion }}">
                    <h4 class="h6 fw-semibold">Situación actual · {{ $anioSituacion }}</h4>
                    @if ($exclusionDocente)
                        <div class="fw-semibold">{{ $exclusionDocente['motivo_label'] }}</div>
                        <div class="small mt-2">Horas necesarias: <strong>{{ $fmt($docente['horas_contrato']) }} h</strong></div>
                        <div class="small mt-1">Horas no necesarias: <strong>{{ $fmt($exclusionDocente['horas']) }} h</strong></div>
                        @if ($continuidadDisponible ?? false)
                            <div class="small mt-1">Continuidad en {{ $anioSituacion + 1 }}: <strong>{{ $continuaDotacion ? 'Sí' : 'No' }}</strong></div>
                        @endif
                        @if ($conservacionHorasDisponible ?? false)
                            <div class="small mt-1">Conservar horas necesarias en {{ $anioSituacion + 1 }}: <strong>{{ $conservarHoras ? 'Sí' : 'No' }}</strong></div>
                        @endif
                    @else
                        <div class="text-muted">Sin situación registrada.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
