@extends('layouts.app')

@push('styles')
<style>
    .lm-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);padding:1.5rem 1.75rem}.lm-panel{background:#fff;border:1px solid #d9e4f3;border-radius:22px;box-shadow:0 18px 42px rgba(15,23,42,.07);padding:1.25rem}.lm-title{font-weight:900;color:#0f172a}.lm-btn-primary,.lm-btn-danger,.lm-btn-outline{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:12px;padding:.65rem .9rem;font-weight:800;text-decoration:none;border:1px solid transparent}.lm-btn-primary{background:#2563eb;color:#fff}.lm-btn-danger{background:#fff1f2;color:#be123c;border-color:#fecdd3}.lm-btn-outline{background:#fff;color:#334155;border-color:#cbd5e1}.lm-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:1rem}.lm-card{border:1px solid #dbeafe;border-radius:16px;padding:1rem}.lm-card-label{font-size:.72rem;text-transform:uppercase;font-weight:800;color:#64748b}.lm-card-value{font-size:1.45rem;font-weight:900;color:#0f172a}.lm-table{width:100%;border-collapse:collapse}.lm-table th,.lm-table td{padding:.65rem;border-bottom:1px solid #e2e8f0}.lm-table th{font-size:.75rem;text-transform:uppercase;color:#64748b}@media(max-width:992px){.lm-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:576px){.lm-summary{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="lm-header mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="text-uppercase small fw-bold text-muted mb-2">Prevalidación #{{ $importacion->id }}</div>
            <h1 class="lm-title h2 mb-2">{{ $importacion->nombre_archivo }}</h1>
            <p class="text-muted mb-0">Dimensión: Resolución COMPIN · Estado de carga: <strong>{{ ucfirst($importacion->estado) }}</strong></p>
        </div>
        <a href="{{ route('tramites.licencias-medicas.actualizaciones.index') }}" class="lm-btn-outline"><i class="bi bi-arrow-left"></i> Volver a cargas</a>
    </div>

    @if(session('success'))<div class="alert alert-success rounded-4 shadow-sm">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger rounded-4 shadow-sm"><strong>No fue posible completar la operación:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="lm-panel mb-4">
        <div class="lm-summary">
            <div class="lm-card"><div class="lm-card-label">Filas leídas</div><div class="lm-card-value">{{ number_format($resumen['filas'] ?? 0, 0, ',', '.') }}</div></div>
            <div class="lm-card"><div class="lm-card-label">Actualizables</div><div class="lm-card-value">{{ number_format($resumen['actualizables'] ?? 0, 0, ',', '.') }}</div></div>
            <div class="lm-card"><div class="lm-card-label">Sin cambios</div><div class="lm-card-value">{{ number_format($resumen['sin_cambios'] ?? 0, 0, ',', '.') }}</div></div>
            <div class="lm-card"><div class="lm-card-label">Duplicadas archivo</div><div class="lm-card-value">{{ number_format($resumen['duplicadas_archivo'] ?? 0, 0, ',', '.') }}</div></div>
            <div class="lm-card"><div class="lm-card-label">Inconsistencias</div><div class="lm-card-value">{{ number_format($resumen['inconsistencias'] ?? 0, 0, ',', '.') }}</div></div>
        </div>
    </div>

    @if($importacion->estado === 'prevalidado')
        <div class="lm-panel mb-4">
            <h2 class="lm-title h5">Confirmar actualización</h2>
            <p class="text-muted">Al confirmar se volverá a validar el archivo. Si cualquier estado cambió desde esta previsualización, no se aplicará ninguna fila.</p>
            @if(($resumen['actualizables'] ?? 0) > 0)
                <form method="POST" action="{{ route('tramites.licencias-medicas.actualizaciones.confirmar', $importacion) }}" class="row g-3">
                    @csrf
                    <div class="col-lg-9"><label class="form-label fw-bold">Fundamento general</label><textarea name="observacion" rows="2" minlength="5" maxlength="1000" class="form-control" required>{{ old('observacion') }}</textarea><div class="form-text">La observación incluida en una fila del Excel tiene prioridad; este fundamento se usa para las demás.</div></div>
                    <div class="col-lg-3 d-flex align-items-end"><button type="submit" class="lm-btn-primary w-100" onclick="return confirm('¿Aplicar todos los cambios válidos de esta carga?');"><i class="bi bi-check2-circle"></i> Confirmar cambios</button></div>
                </form>
            @else
                <div class="alert alert-warning mb-0">La carga no contiene cambios aplicables.</div>
            @endif
        </div>
    @elseif($importacion->estado === 'procesado')
        <div class="lm-panel mb-4">
            <h2 class="lm-title h5">Revertir carga</h2>
            <p class="text-muted">La reversa restaura todos los estados anteriores. Se bloqueará completamente si alguna licencia tuvo un cambio posterior.</p>
            <form method="POST" action="{{ route('tramites.licencias-medicas.actualizaciones.revertir', $importacion) }}" class="row g-3">
                @csrf
                <div class="col-lg-9"><label class="form-label fw-bold">Motivo de la reversa</label><textarea name="observacion_reversion" rows="2" minlength="5" maxlength="1000" class="form-control" required>{{ old('observacion_reversion') }}</textarea></div>
                <div class="col-lg-3 d-flex align-items-end"><button type="submit" class="lm-btn-danger w-100" onclick="return confirm('¿Revertir completamente esta carga?');"><i class="bi bi-arrow-counterclockwise"></i> Revertir carga</button></div>
            </form>
        </div>
    @elseif($importacion->estado === 'revertido')
        <div class="alert alert-info rounded-4 shadow-sm">Esta carga fue revertida el {{ optional($importacion->revertido_at)->format('d-m-Y H:i') }}.</div>
    @endif

    @if(!empty($resumen['muestras_inconsistencias']))
        <div class="lm-panel">
            <h2 class="lm-title h5 mb-3">Muestra de inconsistencias</h2>
            <div class="table-responsive">
                <table class="lm-table"><thead><tr><th>Fila</th><th>Motivo</th></tr></thead><tbody>
                @foreach($resumen['muestras_inconsistencias'] as $item)
                    <tr><td>{{ $item['fila'] ?? '-' }}</td><td>{{ $item['motivo'] ?? '-' }}</td></tr>
                @endforeach
                </tbody></table>
            </div>
            @if(($resumen['inconsistencias'] ?? 0) > count($resumen['muestras_inconsistencias']))
                <div class="form-text mt-2">Se muestran las primeras {{ count($resumen['muestras_inconsistencias']) }} inconsistencias.</div>
            @endif
        </div>
    @endif
</div>
@endsection
