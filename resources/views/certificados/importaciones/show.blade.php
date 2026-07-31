@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Detalle de importación</h1>
            <p class="text-muted mb-0">{{ $importacion->nombre_archivo }}</p>
        </div>
        <a href="{{ route('certificados.importaciones.index') }}" class="btn btn-outline-secondary">
            Volver
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (in_array($importacion->estado, ['pendiente', 'procesando'], true))
        <div class="alert alert-info">
            La importación está {{ $importacion->estado === 'pendiente' ? 'en cola' : 'siendo procesada' }}.
            Actualiza esta página para revisar su avance.
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Resumen</h2>
                    <dl class="row small mb-0">
                        <dt class="col-5">Estado</dt>
                        <dd class="col-7">{{ $importacion->estado }}</dd>
                        <dt class="col-5">Base activa</dt>
                        <dd class="col-7">{{ $importacion->es_vigente ? 'Sí' : 'No' }}</dd>
                        <dt class="col-5">Total filas</dt>
                        <dd class="col-7">{{ number_format($importacion->total_filas, 0, ',', '.') }}</dd>
                        <dt class="col-5">Válidas</dt>
                        <dd class="col-7">{{ number_format($importacion->filas_validas, 0, ',', '.') }}</dd>
                        <dt class="col-5">Omitidas</dt>
                        <dd class="col-7">{{ number_format($importacion->filas_omitidas, 0, ',', '.') }}</dd>
                        <dt class="col-5">Duplicadas</dt>
                        <dd class="col-7">{{ number_format($importacion->filas_duplicadas, 0, ',', '.') }}</dd>
                        <dt class="col-5">Procesada</dt>
                        <dd class="col-7">{{ $importacion->procesado_at?->format('d-m-Y H:i') ?: '—' }}</dd>
                        <dt class="col-5">Activada</dt>
                        <dd class="col-7">{{ $importacion->activado_at?->format('d-m-Y H:i') ?: '—' }}</dd>
                    </dl>

                    @if (
                        ! $importacion->es_vigente
                        && in_array($importacion->estado, ['procesado', 'procesado_con_observaciones'], true)
                    )
                        <hr>
                        <form method="POST" action="{{ route('certificados.importaciones.activar', $importacion) }}">
                            @csrf
                            <button
                                class="btn btn-primary"
                                onclick="return confirm('¿Activar esta versión para las nuevas emisiones?')"
                            >
                                <i class="bi bi-check-circle"></i> Activar esta base
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5">Observaciones de importación</h2>
                    @if ($importacion->errores)
                        <div class="list-group list-group-flush small">
                            @foreach ($importacion->errores as $error)
                                <div class="list-group-item px-0">
                                    <strong>Fila {{ $error['fila'] ?? '—' }}:</strong>
                                    {{ $error['mensaje'] ?? 'Registro omitido.' }}
                                </div>
                            @endforeach
                        </div>
                        @if ($importacion->filas_omitidas > count($importacion->errores))
                            <p class="small text-muted mt-3 mb-0">
                                Se muestran las primeras {{ count($importacion->errores) }} observaciones.
                            </p>
                        @endif
                    @else
                        <p class="text-muted mb-0">No se registraron observaciones.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
