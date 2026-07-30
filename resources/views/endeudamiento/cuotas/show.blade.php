@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Resultado de complementación de cuotas</h1>
            <p class="text-muted mb-0">{{ $importacion->columna_origen }} · {{ sprintf('%02d/%04d', $importacion->carga?->mes, $importacion->carga?->anio) }} · {{ $importacion->carga?->dominio }} · v{{ $importacion->carga?->version }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('endeudamiento.cuotas.create', ['carga_id' => $importacion->mae_carga_id]) }}" class="btn btn-primary">Nueva complementación</a>
            <a href="{{ route('endeudamiento.cuotas.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Filas procesadas</div><div class="h4 mb-0">{{ number_format($importacion->total_filas, 0, ',', '.') }}</div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm border-success h-100"><div class="card-body"><div class="text-muted small">Cuotas asociadas</div><div class="h4 mb-0 text-success">{{ number_format($importacion->total_asociadas, 0, ',', '.') }}</div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm border-danger h-100"><div class="card-body"><div class="text-muted small">Filas con error</div><div class="h4 mb-0 text-danger">{{ number_format($importacion->total_errores, 0, ',', '.') }}</div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Estado</div><div class="h5 mb-0">{{ str_replace('_', ' ', $importacion->estado) }}</div></div></div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Antecedentes</h2>
                    <dl class="row small mb-0">
                        <dt class="col-5">Carga MAE</dt><dd class="col-7">{{ sprintf('%02d/%04d', $importacion->carga?->mes, $importacion->carga?->anio) }} · {{ $importacion->carga?->dominio }} · v{{ $importacion->carga?->version }}</dd>
                        <dt class="col-5">Descuento</dt><dd class="col-7">{{ $importacion->columna_origen }}</dd>
                        <dt class="col-5">Archivo</dt><dd class="col-7">{{ $importacion->nombre_archivo }}</dd>
                        <dt class="col-5">Usuario</dt><dd class="col-7">{{ $importacion->creadoPor?->display_name ?? $importacion->creadoPor?->nombre_completo ?? '—' }}</dd>
                        <dt class="col-5">Procesado</dt><dd class="col-7">{{ $importacion->procesado_at?->format('d-m-Y H:i') ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Resumen de validación</h2>
                    @php($resumen = $importacion->resumen_json ?? [])
                    @if (isset($resumen['error']))
                        <div class="alert alert-danger mb-0">{{ $resumen['error'] }}</div>
                    @else
                        <div class="row row-cols-2 g-2 small">
                            @foreach ([
                                'rut_no_encontrado' => 'RUT no encontrados',
                                'sin_descuento' => 'Sin descuento',
                                'duplicadas' => 'Filas duplicadas',
                                'ambiguas' => 'Coincidencias ambiguas',
                                'datos_invalidos' => 'Datos inválidos',
                            ] as $key => $label)
                                <div class="col"><div class="border rounded p-2"><div class="text-muted">{{ $label }}</div><div class="fw-semibold">{{ number_format($resumen[$key] ?? 0, 0, ',', '.') }}</div></div></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light fw-semibold">Detalle por fila</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fila</th>
                        <th>RUT</th>
                        <th>Cuota actual</th>
                        <th>Total cuotas</th>
                        <th>Observación</th>
                        <th>Estado</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($detalles as $detalle)
                        <tr class="{{ $detalle->estado === 'asociada' ? '' : 'table-warning' }}">
                            <td>{{ $detalle->numero_fila }}</td>
                            <td>{{ $detalle->rut ?: '—' }}</td>
                            <td>
                                @if ($detalle->cuota_actual === null)
                                    —
                                @elseif ((int) $detalle->cuota_actual === 0)
                                    <span class="badge text-bg-info">0 · sin inicio</span>
                                @else
                                    {{ $detalle->cuota_actual }}
                                @endif
                            </td>
                            <td>
                                @if ($detalle->total_cuotas === null)
                                    —
                                @elseif ((int) $detalle->total_cuotas === 0)
                                    <span class="badge text-bg-info">0 · indefinido</span>
                                @else
                                    {{ $detalle->total_cuotas }}
                                @endif
                            </td>
                            <td>{{ $detalle->observacion ?: '—' }}</td>
                            <td>
                                @if ($detalle->estado === 'asociada')
                                    <span class="badge text-bg-success">Asociada</span>
                                @else
                                    <span class="badge text-bg-warning">Error</span>
                                @endif
                            </td>
                            <td class="small">{{ $detalle->mensaje }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No existen filas registradas para esta importación.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($detalles->hasPages())
            <div class="card-body border-top">{{ $detalles->links() }}</div>
        @endif
    </div>
</div>
@endsection
