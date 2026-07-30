@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Cargas de liquidaciones</h1>
            <p class="text-muted mb-0">Carga mensual de PDFs de remuneraciones por dominio. El sistema publica sólo liquidaciones de reemplazo/suplencia.</p>
        </div>
        <a href="{{ route('liquidaciones.cargas.create') }}" class="btn btn-primary"><i class="bi bi-upload"></i> Nueva carga</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Mes</label>
                <select name="mes" class="form-select">
                    <option value="">Todos</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected((int) $mes === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Año</label>
                <select name="anio" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($anios as $item)
                        <option value="{{ $item }}" @selected((int) $anio === (int) $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Dominio</label>
                <select name="dominio" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($dominios as $item)
                        <option value="{{ $item }}" @selected($dominio === $item)>{{ $item }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($estados as $item)
                        <option value="{{ $item }}" @selected($estado === $item)>{{ str_replace('_', ' ', ucfirst($item)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filtrar</button>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Periodo</th>
                        <th>Dominio</th>
                        <th>Estado</th>
                        <th class="text-end">Páginas</th>
                        <th class="text-end">Con RUT</th>
                        <th class="text-end">Reemplazos</th>
                        <th class="text-end">Publicadas</th>
                        <th>Subida por</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->mesNombre() }} {{ $item->anio }}</td>
                            <td>{{ $item->dominio }}</td>
                            <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', $item->estado) }}</span></td>
                            <td class="text-end">{{ number_format($item->total_paginas, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($item->total_con_rut, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($item->total_reemplazos, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($item->total_publicadas, 0, ',', '.') }}</td>
                            <td>{{ optional($item->subidaPor)->display_name ?? 'Sistema' }}</td>
                            <td class="text-end">
                                <a href="{{ route('liquidaciones.cargas.show', $item) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No hay cargas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $items->links() }}</div>
</div>
@endsection
