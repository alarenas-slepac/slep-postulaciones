@extends('layouts.app')

@push('styles')
<style>
    .lm-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);padding:1.5rem 1.75rem}.lm-panel{background:#fff;border:1px solid #d9e4f3;border-radius:22px;box-shadow:0 18px 42px rgba(15,23,42,.07);padding:1.25rem}.lm-title{font-weight:900;color:#0f172a}.lm-btn-primary,.lm-btn-secondary,.lm-btn-outline{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:12px;padding:.65rem .9rem;font-weight:800;text-decoration:none;border:1px solid transparent}.lm-btn-primary{background:#2563eb;color:#fff}.lm-btn-secondary{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.lm-btn-outline{background:#fff;color:#334155;border-color:#cbd5e1}.lm-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.lm-card{border:1px solid #dbeafe;border-radius:16px;padding:1rem}.lm-card-label{font-size:.72rem;text-transform:uppercase;font-weight:800;color:#64748b}.lm-card-value{font-size:1.45rem;font-weight:900;color:#0f172a}.lm-table{width:100%;border-collapse:collapse}.lm-table th,.lm-table td{padding:.75rem;border-bottom:1px solid #e2e8f0;vertical-align:middle}.lm-table th{font-size:.75rem;text-transform:uppercase;color:#64748b}.lm-pill{display:inline-flex;border-radius:999px;padding:.3rem .6rem;font-size:.76rem;font-weight:800;background:#fff7ed;color:#9a3412}.lm-pill.is-corrected{background:#eff6ff;color:#1d4ed8}.lm-pill.is-resolved{background:#ecfdf5;color:#047857}@media(max-width:992px){.lm-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.lm-table{display:block;overflow-x:auto}}@media(max-width:576px){.lm-summary{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="lm-header mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="text-uppercase small fw-bold text-muted mb-2"><i class="bi bi-exclamation-triangle me-1"></i> Licencias médicas</div>
            <h1 class="lm-title h2 mb-2">Errores de importación</h1>
            <p class="text-muted mb-0">Consulta, corrige y reprocesa filas rechazadas sin eliminar el dato original ni volver a ejecutar las filas válidas.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('tramites.licencias-medicas.importar-seguimiento') }}" class="lm-btn-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> Importar seguimiento</a>
            <a href="{{ route('tramites.licencias-medicas.index') }}" class="lm-btn-outline"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success rounded-4 shadow-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger rounded-4 shadow-sm"><strong>No fue posible completar la operación:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="lm-panel mb-4">
        <div class="lm-summary">
            <div class="lm-card"><div class="lm-card-label">Total registrados</div><div class="lm-card-value">{{ number_format($metricas['total'], 0, ',', '.') }}</div></div>
            <div class="lm-card"><div class="lm-card-label">Pendientes</div><div class="lm-card-value text-danger">{{ number_format($metricas['pendientes'], 0, ',', '.') }}</div></div>
            <div class="lm-card"><div class="lm-card-label">Con corrección</div><div class="lm-card-value text-primary">{{ number_format($metricas['corregidos'], 0, ',', '.') }}</div></div>
            <div class="lm-card"><div class="lm-card-label">Resueltos</div><div class="lm-card-value text-success">{{ number_format($metricas['resueltos'], 0, ',', '.') }}</div></div>
        </div>
    </div>

    <div class="lm-panel mb-4">
        <h2 class="lm-title h5 mb-3">Buscar registros</h2>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-lg-7"><label class="form-label fw-bold">Archivo, folio, RUT o motivo</label><input type="text" name="q" value="{{ request('q') }}" class="form-control"></div>
            <div class="col-lg-3"><label class="form-label fw-bold">Estado</label><select name="estado" class="form-select"><option value="">Todos</option><option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente</option><option value="corregido" @selected(request('estado') === 'corregido')>Corregido</option><option value="resuelto" @selected(request('estado') === 'resuelto')>Resuelto</option></select></div>
            <div class="col-lg-2 d-flex gap-2"><button class="lm-btn-primary flex-fill" type="submit"><i class="bi bi-search"></i> Filtrar</button><a href="{{ route('tramites.licencias-medicas.errores.index') }}" class="lm-btn-outline"><i class="bi bi-x-lg"></i></a></div>
        </form>
    </div>

    <div class="lm-panel mb-4">
        <h2 class="lm-title h5 mb-3">Registros con observaciones o errores</h2>
        <div class="table-responsive">
            <table class="lm-table">
                <thead><tr><th>Importación</th><th>Ubicación</th><th>Folio / RUT recibido</th><th>Motivo</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse($errores as $errorImportacion)
                    <tr>
                        <td><strong>#{{ $errorImportacion->importacion_id }}</strong><br><small class="text-muted">{{ $errorImportacion->importacion?->nombre_archivo }}</small></td>
                        <td>{{ $errorImportacion->hoja ?: '-' }} / fila {{ $errorImportacion->fila ?: '-' }}</td>
                        <td>{{ $errorImportacion->folio_recibido ?: '-' }}<br><small class="text-muted">{{ $errorImportacion->rut_recibido ?: '-' }}</small></td>
                        <td>{{ $errorImportacion->motivo }}</td>
                        <td><span class="lm-pill {{ $errorImportacion->estado === 'corregido' ? 'is-corrected' : ($errorImportacion->estado === 'resuelto' ? 'is-resolved' : '') }}">{{ ucfirst($errorImportacion->estado) }}</span></td>
                        <td class="text-end"><a href="{{ route('tramites.licencias-medicas.errores.show', $errorImportacion) }}" class="lm-btn-outline"><i class="bi bi-pencil-square"></i> Revisar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay errores registrados con los filtros seleccionados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $errores->links() }}</div>
    </div>

    <div class="lm-panel">
        <h2 class="lm-title h5 mb-2">Cargas históricas con inconsistencias</h2>
        <p class="text-muted">Para cargas anteriores a este parche, reconstruir sólo lee el archivo original y registra sus filas rechazadas. No modifica licencias válidas.</p>
        <div class="table-responsive">
            <table class="lm-table">
                <thead><tr><th>Carga</th><th>Fecha</th><th>Inconsistencias informadas</th><th>Errores detallados</th><th></th></tr></thead>
                <tbody>
                @forelse($importaciones as $importacion)
                    <tr>
                        <td><strong>#{{ $importacion->id }} · {{ $importacion->nombre_archivo }}</strong></td>
                        <td>{{ optional($importacion->created_at)->format('d-m-Y H:i') }}</td>
                        <td>{{ number_format($importacion->total_inconsistencias, 0, ',', '.') }}</td>
                        <td>{{ number_format($importacion->errores_count, 0, ',', '.') }}</td>
                        <td class="text-end">
                            @if($importacion->errores_count === 0 && $importacion->total_inconsistencias > 0)
                                <form method="POST" action="{{ route('tramites.licencias-medicas.errores.indexar', $importacion) }}" onsubmit="return confirm('¿Reconstruir los errores desde el archivo original? Las filas válidas no serán procesadas.');">
                                    @csrf
                                    <button type="submit" class="lm-btn-secondary"><i class="bi bi-arrow-repeat"></i> Reconstruir errores</button>
                                </form>
                            @else
                                <span class="text-muted small">Detalle disponible</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No hay cargas históricas con inconsistencias.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $importaciones->links() }}</div>
    </div>
</div>
@endsection
