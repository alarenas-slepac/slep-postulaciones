@extends('layouts.app')

@section('content')
<div class="cl-page py-4">
    <div class="cl-hero">
        <div class="cl-hero-main">
            <span class="cl-hero-icon" aria-hidden="true"><i class="bi bi-database-check"></i></span>
            <div>
                <div class="cl-eyebrow">Certificados laborales · Administración</div>
                <h1 class="cl-hero-title">Bases históricas de contratos</h1>
                <p class="cl-hero-subtitle">
                    Versiones utilizadas para calcular vigencia y antigüedad laboral.
                </p>
            </div>
        </div>
        <div class="cl-hero-actions">
            <a href="{{ route('certificados.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver</a>
            <a href="{{ route('certificados.importaciones.create') }}" class="btn btn-primary">
                <i class="bi bi-upload" aria-hidden="true"></i> Nueva importación
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success cl-alert" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i>{{ session('status') }}</div>
    @endif

    <div class="alert alert-info cl-alert">
        Las importaciones se procesan en segundo plano. Una base procesada debe activarse
        explícitamente antes de utilizarse para nuevas emisiones.
    </div>

    <div class="card cl-panel">
        <div class="card-header">
            <div class="cl-panel-kicker">Versiones</div>
            <h2 class="cl-panel-title">Historial de bases importadas</h2>
        </div>
        <div class="table-responsive">
            <table class="table cl-table align-middle">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Estado</th>
                        <th>Activa</th>
                        <th>Registros</th>
                        <th>Subida por</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($importaciones as $importacion)
                        @php
                            $estadoClase = match ($importacion->estado) {
                                'procesado' => 'is-success',
                                'procesado_con_observaciones' => 'is-warning',
                                'pendiente', 'procesando' => 'is-info',
                                default => 'is-danger',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="cl-table-primary">{{ $importacion->nombre_archivo }}</div>
                                <div class="cl-table-meta">
                                    {{ $importacion->created_at?->format('d-m-Y H:i') }}
                                </div>
                            </td>
                            <td><span class="cl-chip {{ $estadoClase }}">{{ ucfirst(str_replace('_', ' ', $importacion->estado)) }}</span></td>
                            <td>
                                @if ($importacion->es_vigente)
                                    <span class="cl-chip is-success">Activa</span>
                                @else
                                    <span class="cl-chip">Histórica</span>
                                @endif
                            </td>
                            <td class="cl-table-meta">
                                <div>Válidos: {{ number_format($importacion->filas_validas, 0, ',', '.') }}</div>
                                <div>Omitidos: {{ number_format($importacion->filas_omitidas, 0, ',', '.') }}</div>
                                <div>Duplicados: {{ number_format($importacion->filas_duplicadas, 0, ',', '.') }}</div>
                            </td>
                            <td>{{ $importacion->subidaPor?->nombre_completo ?: '—' }}</td>
                            <td><div class="cl-row-actions">
                                <a
                                    href="{{ route('certificados.importaciones.show', $importacion) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >Ver detalle</a>
                            </div></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="cl-empty">
                                <i class="bi bi-database" aria-hidden="true"></i>
                                No existen bases históricas importadas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($importaciones->hasPages())
            <div class="card-footer">{{ $importaciones->links() }}</div>
        @endif
    </div>
</div>
@endsection
