@extends('layouts.app')

@section('content')
@php($canManageRestrictedRuts = auth()->user()?->hasAnyRole(['admin', 'coordinador_gdp']))
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Restricciones para ejercer</h1>
            <p class="text-muted mb-0">Consulta consolidada de bloqueos judiciales y manuales.</p>
        </div>
        @if ($canManageRestrictedRuts)
            <div class="d-flex gap-2">
                <a href="{{ route('admin.restricted-ruts.import') }}" class="btn btn-outline-primary">
                    <i class="bi bi-upload"></i> Carga judicial
                </a>
                <a href="{{ route('admin.restricted-ruts.manual.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Bloqueo manual
                </a>
            </div>
        @endif
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @unless ($canManageRestrictedRuts)
        <div class="alert alert-info">Esta vista está disponible en modo solo lectura para tu rol.</div>
    @endunless

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="RUT, nombre, juzgado, comentario...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Origen</label>
                    <select name="origin" class="form-select">
                        <option value="">Todos</option>
                        <option value="court" @selected($origin==='court')>Judicial</option>
                        <option value="manual" @selected($origin==='manual')>Manual</option>
                        <option value="both" @selected($origin==='both')>Ambos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="blocked" @selected($status==='blocked')>Bloqueado</option>
                        <option value="unblocked" @selected($status==='unblocked')>No bloqueado</option>
                        <option value="court" @selected($status==='court')>Judicial activo</option>
                        <option value="manual" @selected($status==='manual')>Manual vigente</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Buscar</button>
                    <a href="{{ route('admin.restricted-ruts.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>RUT</th>
                        <th>Nombre referencia</th>
                        <th>Origen</th>
                        <th>Estado actual</th>
                        <th>Detalle</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($items as $item)
                    @php($flags = app(\App\Services\RestrictedRutService::class)->currentStatus($item))
                    <tr>
                        <td class="fw-semibold">{{ $item->rut_formatted }}</td>
                        <td>{{ $item->display_name ?: ($item->courtRecord?->nombre ?: '—') }}</td>
                        <td>
                            @if ($item->courtRecord)
                                <span class="badge bg-info-subtle text-info-emphasis border">Judicial</span>
                            @endif
                            @if ($item->manualRecord)
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border">Manual</span>
                            @endif
                        </td>
                        <td>
                            @if ($flags['blocked'])
                                <span class="badge bg-danger">Bloqueado</span>
                            @else
                                        <span class="badge text-bg-success">No bloqueado</span>
                            @endif
                        </td>
                        <td class="small text-muted">
                            @if ($item->courtRecord)
                                <div>Judicial: {{ $item->courtRecord->juzgado_origen ?: 'Sin juzgado' }}</div>
                            @endif
                            @if ($item->manualRecord)
                                <div>Manual: {{ optional($item->manualRecord->fecha_inicio_prohibicion)->format('d-m-Y') }} al {{ optional($item->manualRecord->fecha_termino_prohibicion)->format('d-m-Y') }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.restricted-ruts.show', $item) }}" class="btn btn-sm btn-outline-primary">Ver ficha</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No hay registros para los filtros aplicados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())
            <div class="card-body border-top">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
