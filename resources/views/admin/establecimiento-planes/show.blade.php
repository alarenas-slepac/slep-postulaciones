@extends('layouts.app')

@section('content')
    @php
        $ec = $configuracion->establecimientoCurso;
        $plan = $configuracion->planEstudio;
        $estados = \App\Models\EstablecimientoPlanEstudio::ESTADOS;
        $badge = match ($configuracion->estado) {
            'enviado' => 'text-bg-info',
            'observado' => 'text-bg-warning',
            'aprobado' => 'text-bg-success',
            'cerrado' => 'text-bg-secondary',
            default => 'text-bg-primary',
        };
    @endphp

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h4 mb-1">Configuración del plan</h1>
            <div class="text-muted small">{{ $configuracion->establecimiento->nombre_establecimiento ?? 'Establecimiento' }} · {{ $ec->nombre_seccion ?? '' }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.establecimiento-planes.index') }}">Volver</a>
            <a class="btn btn-outline-primary" href="{{ route('admin.establecimiento-planes.edit', $configuracion) }}">Editar</a>
            <form method="POST" action="{{ route('admin.establecimiento-planes.destroy', $configuracion) }}" onsubmit="return confirm('¿Eliminar esta configuración?');">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger" type="submit">Eliminar</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body row g-3">
            <div class="col-md-4"><div class="text-muted small">Establecimiento</div><div class="fw-semibold">{{ $configuracion->establecimiento->rbd ?? '' }} — {{ $configuracion->establecimiento->nombre_establecimiento ?? '' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Curso/sección</div><div class="fw-semibold">{{ $ec->nombre_seccion ?? '' }}</div><div class="small text-muted">{{ $ec->regimen_jec ?? '' }} · {{ $configuracion->anio }}</div></div>
            <div class="col-md-2"><div class="text-muted small">Matrícula</div><div class="fw-semibold">{{ number_format((int) ($ec->matricula ?? 0), 0, ',', '.') }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Estado</div><span class="badge {{ $badge }}">{{ $estados[$configuracion->estado] ?? $configuracion->estado }}</span></div>
            <div class="col-12"><div class="text-muted small">Plan asociado</div><div class="fw-semibold">{{ $plan->nombre_plan ?? '' }}</div><div class="small text-muted">Total: {{ number_format((float) ($plan->horas_semanales_total ?? 0), 2, ',', '.') }} h semanales</div></div>
            @if ($configuracion->observacion)
                <div class="col-12"><div class="text-muted small">Observación</div><div>{{ $configuracion->observacion }}</div></div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Bloques y asignaturas configuradas</div>
        <div class="card-body">
            @foreach ($plan->bloques as $bloque)
                @php
                    $detalles = $detallesPorBloque[$bloque->id] ?? collect();
                    $oficiales = collect($asignaturasOficialesPorBloque[$bloque->tipo_bloque] ?? []);
                    $editable = $bloque->permite_asignaturas_establecimiento || $bloque->permite_asignaturas_personalizadas;
                    $sumaConfigurada = $detalles->sum(fn($d) => (float) $d->horas_semanales);
                    $sumaOficial = $oficiales->sum(fn($d) => (float) ($d['horas_semanales'] ?? 0));
                    $suma = $editable ? $sumaConfigurada : ($sumaOficial > 0 ? $sumaOficial : (float) ($bloque->horas_semanales ?? 0));
                @endphp
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-2">
                        <div>
                            <div class="fw-semibold">{{ $bloque->nombre }}</div>
                            <div class="small text-muted">{{ ucfirst(str_replace('_', ' ', $bloque->tipo_bloque)) }} · Máximo {{ number_format((float) $bloque->horas_semanales, 2, ',', '.') }} h</div>
                        </div>
                        <div class="text-lg-end small">
                            <span class="badge text-bg-light border">{{ $editable ? 'Configurado' : 'Plan oficial' }}: {{ number_format($suma, 2, ',', '.') }} h</span>
                        </div>
                    </div>

                    @if (! $editable && $oficiales->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Asignatura oficial del plan</th>
                                        <th class="text-end">Horas sem.</th>
                                        <th class="text-end">Horas anuales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($oficiales as $oficial)
                                        <tr>
                                            <td>{{ $oficial['asignatura'] ?? 'Asignatura sin nombre' }}</td>
                                            <td class="text-end">{{ number_format((float) ($oficial['horas_semanales'] ?? 0), 2, ',', '.') }}</td>
                                            <td class="text-end">{{ ($oficial['horas_anuales'] ?? null) !== null ? number_format((float) $oficial['horas_anuales'], 2, ',', '.') : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($detalles->isEmpty())
                        <div class="alert alert-light border mb-0 small">
                            @if (! $editable)
                                Este bloque es fijo y se usa como referencia del plan oficial.
                            @else
                                Sin asignaturas configuradas para este bloque.
                            @endif
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Asignatura</th>
                                        <th>Origen</th>
                                        @if ($bloque->tipo_bloque === 'libre_disposicion')
                                            <th>Plan común asociado</th>
                                        @endif
                                        <th class="text-end">Horas sem.</th>
                                        <th class="text-end">Horas anuales</th>
                                        <th>Observación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($detalles as $detalle)
                                        <tr>
                                            <td>{{ $detalle->asignatura->nombre ?? $detalle->nombre_asignatura_personalizada }}</td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $detalle->origen)) }}</td>
                                            @if ($bloque->tipo_bloque === 'libre_disposicion')
                                                <td>{{ $detalle->asignaturaPlanComun->nombre ?? 'Sin asociación' }}</td>
                                            @endif
                                            <td class="text-end">{{ number_format((float) $detalle->horas_semanales, 2, ',', '.') }}</td>
                                            <td class="text-end">{{ $detalle->horas_anuales !== null ? number_format((float) $detalle->horas_anuales, 2, ',', '.') : '—' }}</td>
                                            <td>{{ $detalle->observacion ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection
