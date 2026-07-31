@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Bases históricas de contratos</h1>
            <p class="text-muted mb-0">
                Versiones utilizadas para calcular vigencia y antigüedad laboral.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('certificados.index') }}" class="btn btn-outline-secondary">Volver</a>
            <a href="{{ route('certificados.importaciones.create') }}" class="btn btn-primary">
                <i class="bi bi-upload"></i> Nueva importación
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="alert alert-info">
        Las importaciones se procesan en segundo plano. Una base procesada debe activarse
        explícitamente antes de utilizarse para nuevas emisiones.
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Archivo</th>
                        <th>Estado</th>
                        <th>Activa</th>
                        <th>Registros</th>
                        <th>Subida por</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($importaciones as $importacion)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $importacion->nombre_archivo }}</div>
                                <div class="small text-muted">
                                    {{ $importacion->created_at?->format('d-m-Y H:i') }}
                                </div>
                            </td>
                            <td><span class="badge text-bg-light border">{{ $importacion->estado }}</span></td>
                            <td>
                                @if ($importacion->es_vigente)
                                    <span class="badge text-bg-success">Activa</span>
                                @else
                                    <span class="badge text-bg-secondary">Histórica</span>
                                @endif
                            </td>
                            <td class="small">
                                <div>Válidos: {{ number_format($importacion->filas_validas, 0, ',', '.') }}</div>
                                <div>Omitidos: {{ number_format($importacion->filas_omitidas, 0, ',', '.') }}</div>
                                <div>Duplicados: {{ number_format($importacion->filas_duplicadas, 0, ',', '.') }}</div>
                            </td>
                            <td>{{ $importacion->subidaPor?->nombre_completo ?: '—' }}</td>
                            <td class="text-end">
                                <a
                                    href="{{ route('certificados.importaciones.show', $importacion) }}"
                                    class="btn btn-sm btn-outline-primary"
                                >Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No existen bases históricas importadas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($importaciones->hasPages())
            <div class="card-body border-top">{{ $importaciones->links() }}</div>
        @endif
    </div>
</div>
@endsection
