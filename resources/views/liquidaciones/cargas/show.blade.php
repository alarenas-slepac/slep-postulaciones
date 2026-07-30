@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Detalle de carga</h1>
            <p class="text-muted mb-0">{{ $liquidacionCarga->mesNombre() }} {{ $liquidacionCarga->anio }} · {{ $liquidacionCarga->dominio }}</p>
        </div>
        <a href="{{ route('liquidaciones.cargas.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Estado</div><div class="fw-semibold">{{ str_replace('_', ' ', $liquidacionCarga->estado) }}</div></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Páginas</div><div class="h5 mb-0">{{ number_format($liquidacionCarga->total_paginas, 0, ',', '.') }}</div></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Con RUT</div><div class="h5 mb-0">{{ number_format($liquidacionCarga->total_con_rut, 0, ',', '.') }}</div></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Reemplazos</div><div class="h5 mb-0">{{ number_format($liquidacionCarga->total_reemplazos, 0, ',', '.') }}</div></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Publicadas</div><div class="h5 mb-0">{{ number_format($liquidacionCarga->total_publicadas, 0, ',', '.') }}</div></div></div></div>
        <div class="col-md-2"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Errores</div><div class="h5 mb-0">{{ number_format($liquidacionCarga->total_errores, 0, ',', '.') }}</div></div></div></div>
    </div>

    @if ($liquidacionCarga->archivo_original_nombre)
        <div class="alert alert-light border small">
            <strong>Archivo cargado:</strong> {{ $liquidacionCarga->archivo_original_nombre }}
        </div>
    @endif

    @if ($liquidacionCarga->estado === 'pendiente' || $liquidacionCarga->estado === 'procesando')
        <div class="alert alert-info">La carga aún está en procesamiento. Actualiza esta página para revisar el avance.</div>
    @endif

    @if (!empty($liquidacionCarga->errores))
        <div class="alert alert-warning">
            <strong>Observaciones del procesamiento:</strong>
            <ul class="mb-0 mt-2 small">
                @foreach (array_slice($liquidacionCarga->errores, 0, 20) as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Liquidaciones publicadas para reemplazos</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>RUT PDF</th>
                        <th>RUT normalizado</th>
                        <th>Contrato detectado</th>
                        <th class="text-end">Página</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($liquidaciones as $item)
                        <tr>
                            <td>{{ $item->nombre ?: 'Sin nombre detectado' }}</td>
                            <td>{{ $item->rut_original }}</td>
                            <td><code>{{ $item->rut_normalizado }}</code></td>
                            <td>{{ $item->tipo_contrato_detectado ?: 'Reemplazo detectado' }}</td>
                            <td class="text-end">{{ $item->pagina_origen }}</td>
                            <td class="text-end">
                                <a href="{{ route('liquidaciones.cargas.liquidaciones.descargar', $item) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Descargar</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay liquidaciones de reemplazo publicadas para esta carga.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $liquidaciones->links() }}</div>
</div>
@endsection
