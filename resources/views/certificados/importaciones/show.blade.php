@extends('layouts.app')

@php
    $estadoClase = match ($importacion->estado) {
        'procesado' => 'is-success',
        'procesado_con_observaciones' => 'is-warning',
        'pendiente', 'procesando' => 'is-info',
        default => 'is-danger',
    };
@endphp

@section('content')
<div class="cl-page py-4">
    <div class="cl-hero">
        <div class="cl-hero-main">
            <span class="cl-hero-icon" aria-hidden="true"><i class="bi bi-database-check"></i></span>
            <div>
                <div class="cl-eyebrow">Certificados laborales · Bases históricas</div>
                <h1 class="cl-hero-title">Detalle de importación</h1>
                <p class="cl-hero-subtitle">{{ $importacion->nombre_archivo }}</p>
            </div>
        </div>
        <div class="cl-hero-actions">
            <a href="{{ route('certificados.importaciones.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success cl-alert" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i>{{ session('status') }}</div>
    @endif

    @if (in_array($importacion->estado, ['pendiente', 'procesando'], true))
        <div class="alert alert-info cl-alert" role="status">
            La importación está {{ $importacion->estado === 'pendiente' ? 'en cola' : 'siendo procesada' }}.
            Actualiza esta página para revisar su avance.
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card cl-panel h-100">
                <div class="card-header">
                    <div class="cl-panel-kicker">Estado y registros</div>
                    <h2 class="cl-panel-title">Resumen</h2>
                </div>
                <div class="card-body">
                    <dl class="row cl-detail-list mb-0">
                        <dt class="col-5">Estado</dt>
                        <dd class="col-7"><span class="cl-chip {{ $estadoClase }}">{{ ucfirst(str_replace('_', ' ', $importacion->estado)) }}</span></dd>
                        <dt class="col-5">Base activa</dt>
                        <dd class="col-7"><span class="cl-chip {{ $importacion->es_vigente ? 'is-success' : '' }}">{{ $importacion->es_vigente ? 'Sí' : 'No' }}</span></dd>
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
                        <hr class="my-4">
                        <form method="POST" action="{{ route('certificados.importaciones.activar', $importacion) }}">
                            @csrf
                            <button
                                class="btn btn-primary"
                                onclick="return confirm('¿Activar esta versión para las nuevas emisiones?')"
                            >
                                <i class="bi bi-check-circle" aria-hidden="true"></i> Activar esta base
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card cl-panel h-100">
                <div class="card-header">
                    <div class="cl-panel-kicker">Procesamiento</div>
                    <h2 class="cl-panel-title">Observaciones de importación</h2>
                </div>
                <div class="card-body">
                    @if ($importacion->errores)
                        <div class="list-group list-group-flush">
                            @foreach ($importacion->errores as $error)
                                <div class="list-group-item px-0 cl-table-meta">
                                    <strong>Fila {{ $error['fila'] ?? '—' }}:</strong>
                                    {{ $error['mensaje'] ?? 'Registro omitido.' }}
                                </div>
                            @endforeach
                        </div>
                        @if ($importacion->filas_omitidas > count($importacion->errores))
                            <p class="cl-section-help mt-3 mb-0">
                                Se muestran las primeras {{ count($importacion->errores) }} observaciones.
                            </p>
                        @endif
                    @else
                        <p class="cl-section-help mb-0">No se registraron observaciones.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
