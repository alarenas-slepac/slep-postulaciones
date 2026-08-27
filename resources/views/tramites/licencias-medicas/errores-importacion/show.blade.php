@extends('layouts.app')

@push('styles')
<style>
    .lm-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);padding:1.5rem 1.75rem}.lm-panel{background:#fff;border:1px solid #d9e4f3;border-radius:22px;box-shadow:0 18px 42px rgba(15,23,42,.07);padding:1.25rem}.lm-title{font-weight:900;color:#0f172a}.lm-btn-primary,.lm-btn-secondary,.lm-btn-outline{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:12px;padding:.65rem .9rem;font-weight:800;text-decoration:none;border:1px solid transparent}.lm-btn-primary{background:#2563eb;color:#fff}.lm-btn-secondary{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.lm-btn-outline{background:#fff;color:#334155;border-color:#cbd5e1}.lm-data{border:1px solid #e2e8f0;border-radius:16px;padding:.85rem;height:100%}.lm-data-label{font-size:.72rem;text-transform:uppercase;font-weight:800;color:#64748b}.lm-data-value{font-weight:800;color:#0f172a;margin-top:.2rem}.lm-pill{display:inline-flex;border-radius:999px;padding:.3rem .6rem;font-size:.76rem;font-weight:800;background:#fff7ed;color:#9a3412}.lm-pill.is-corrected{background:#eff6ff;color:#1d4ed8}.lm-pill.is-resolved{background:#ecfdf5;color:#047857}
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="lm-header mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="text-uppercase small fw-bold text-muted mb-2">Error de importación #{{ $errorImportacion->id }}</div>
            <h1 class="lm-title h2 mb-2">{{ $errorImportacion->importacion?->nombre_archivo }}</h1>
            <p class="text-muted mb-0">Hoja {{ $errorImportacion->hoja ?: '-' }} · fila {{ $errorImportacion->fila ?: '-' }} · <span class="lm-pill {{ $errorImportacion->estado === 'corregido' ? 'is-corrected' : ($errorImportacion->estado === 'resuelto' ? 'is-resolved' : '') }}">{{ ucfirst($errorImportacion->estado) }}</span></p>
        </div>
        <a href="{{ route('tramites.licencias-medicas.errores.index') }}" class="lm-btn-outline"><i class="bi bi-arrow-left"></i> Volver a errores</a>
    </div>

    @if(session('success'))<div class="alert alert-success rounded-4 shadow-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger rounded-4 shadow-sm"><strong>Revise la corrección:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="lm-panel mb-4">
        <h2 class="lm-title h5 mb-3">Diagnóstico registrado</h2>
        <div class="row g-3">
            <div class="col-lg-3"><div class="lm-data"><div class="lm-data-label">Código</div><div class="lm-data-value">{{ $errorImportacion->codigo_error }}</div></div></div>
            <div class="col-lg-9"><div class="lm-data"><div class="lm-data-label">Motivo</div><div class="lm-data-value">{{ $errorImportacion->ultimo_error ?: $errorImportacion->motivo }}</div></div></div>
        </div>
    </div>

    @if($errorImportacion->estado === 'resuelto')
        <div class="lm-panel mb-4">
            <h2 class="lm-title h5 mb-3">Resultado del reproceso</h2>
            <p class="mb-2">Resultado: <strong>{{ ucfirst($errorImportacion->resultado_reproceso) }}</strong></p>
            <p class="text-muted">Reprocesado el {{ optional($errorImportacion->reprocesado_at)->format('d-m-Y H:i') }} por {{ $errorImportacion->reprocesadoPor?->name ?: 'Sistema' }}.</p>
            @if($errorImportacion->licenciaMedica)
                <a href="{{ route('tramites.licencias-medicas.show', $errorImportacion->licenciaMedica) }}" class="lm-btn-primary"><i class="bi bi-eye"></i> Ver licencia {{ $errorImportacion->licenciaMedica->folio_licencia }}</a>
            @endif
        </div>
    @elseif($errorImportacion->fila === null)
        <div class="alert alert-warning rounded-4 shadow-sm">Este error corresponde al archivo o a su cabecera y no contiene una fila que pueda corregirse individualmente.</div>
    @else
        <div class="lm-panel mb-4">
            <h2 class="lm-title h5 mb-2">Corrección controlada</h2>
            <p class="text-muted">Sólo debe modificar los valores que causaron el rechazo. El contenido original permanece almacenado para auditoría.</p>
            <form method="POST" action="{{ route('tramites.licencias-medicas.errores.update', $errorImportacion) }}" class="row g-3">
                @csrf
                @method('PATCH')
                <div class="col-lg-4"><label class="form-label fw-bold">N.º licencia o folio</label><input type="text" name="licencia" value="{{ old('licencia', $valores['licencia'] ?? '') }}" class="form-control"><div class="form-text">Puede indicar cuerpo-DV o tipo-cuerpo-DV.</div></div>
                <div class="col-lg-2"><label class="form-label fw-bold">DV licencia</label><input type="text" name="dv" value="{{ old('dv', $valores['dv'] ?? '') }}" maxlength="1" class="form-control"></div>
                <div class="col-lg-3"><label class="form-label fw-bold">RUT funcionario</label><input type="text" name="rut" value="{{ old('rut', $valores['rut'] ?? '') }}" class="form-control"></div>
                <div class="col-lg-3"><label class="form-label fw-bold">Nombre funcionario</label><input type="text" name="nombre" value="{{ old('nombre', $valores['nombre'] ?? '') }}" class="form-control"></div>
                <div class="col-12 d-flex justify-content-end gap-2 flex-wrap">
                    <button type="submit" name="accion" value="guardar" class="lm-btn-outline"><i class="bi bi-save"></i> Guardar corrección</button>
                    <button type="submit" name="accion" value="reprocesar" class="lm-btn-primary" onclick="return confirm('¿Guardar la corrección y reprocesar esta fila?');"><i class="bi bi-arrow-repeat"></i> Guardar y reprocesar</button>
                </div>
            </form>
        </div>

        @if($errorImportacion->estado === 'corregido')
            <div class="lm-panel">
                <h2 class="lm-title h5 mb-2">Reprocesar corrección guardada</h2>
                <p class="text-muted">Intentos anteriores: {{ $errorImportacion->intentos_reproceso }}.</p>
                <form method="POST" action="{{ route('tramites.licencias-medicas.errores.reprocesar', $errorImportacion) }}" onsubmit="return confirm('¿Reprocesar esta fila con la corrección guardada?');">
                    @csrf
                    <button type="submit" class="lm-btn-secondary"><i class="bi bi-arrow-repeat"></i> Reprocesar ahora</button>
                </form>
            </div>
        @endif
    @endif
</div>
@endsection
