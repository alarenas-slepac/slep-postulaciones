@extends('layouts.app')

@push('styles')
<style>
    .lm-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);padding:1.5rem 1.75rem}.lm-panel{background:#fff;border:1px solid #d9e4f3;border-radius:22px;box-shadow:0 18px 42px rgba(15,23,42,.07);padding:1.25rem}.lm-title{font-weight:900;color:#0f172a}.lm-btn-primary,.lm-btn-secondary,.lm-btn-outline{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:12px;padding:.65rem .9rem;font-weight:800;text-decoration:none;border:1px solid transparent}.lm-btn-primary{background:#2563eb;color:#fff}.lm-btn-secondary{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.lm-btn-outline{background:#fff;color:#334155;border-color:#cbd5e1}.lm-step{border:1px solid #dbeafe;border-radius:16px;padding:1rem;background:#f8fbff}.lm-table{width:100%;border-collapse:separate;border-spacing:0 .55rem}.lm-table th{font-size:.75rem;text-transform:uppercase;color:#64748b;padding:.4rem .7rem}.lm-table td{padding:.8rem .7rem;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;background:#fff}.lm-table td:first-child{border-left:1px solid #e2e8f0;border-radius:13px 0 0 13px}.lm-table td:last-child{border-right:1px solid #e2e8f0;border-radius:0 13px 13px 0}.lm-pill{display:inline-flex;border-radius:999px;padding:.3rem .6rem;font-size:.76rem;font-weight:800;background:#eef2ff;color:#3730a3}@media(max-width:992px){.lm-table{display:block;overflow-x:auto}}
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="lm-header mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="text-uppercase small fw-bold text-muted mb-2"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Licencias médicas</div>
            <h1 class="lm-title h2 mb-2">Actualización masiva de estados</h1>
            <p class="text-muted mb-0">Prevalida la planilla completa antes de modificar estados y permite revertir una carga mientras no existan cambios posteriores.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('tramites.licencias-medicas.actualizaciones.plantilla') }}" class="lm-btn-secondary"><i class="bi bi-download"></i> Descargar plantilla</a>
            <a href="{{ route('tramites.licencias-medicas.index') }}" class="lm-btn-outline"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success rounded-4 shadow-sm">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger rounded-4 shadow-sm"><strong>Revise la carga:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="lm-panel h-100">
                <h2 class="lm-title h5 mb-3"><i class="bi bi-cloud-arrow-up me-1"></i> Nueva prevalidación</h2>
                <form method="POST" action="{{ route('tramites.licencias-medicas.actualizaciones.prevalidar') }}" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label fw-bold">Dimensión</label>
                        <select name="dimension" class="form-select" required>
                            @foreach($dimensiones as $codigo => $etiqueta)
                                <option value="{{ $codigo }}" @selected(old('dimension', 'compin') === $codigo)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Archivo Excel</label>
                        <input type="file" name="archivo_estados" accept=".xlsx,.xls" class="form-control" required>
                        <div class="form-text">Máximo 20.000 filas y 50 MB. Columnas: FOLIO_LICENCIA, RUT, ESTADO y OBSERVACION opcional.</div>
                    </div>
                    <div class="col-12"><button type="submit" class="lm-btn-primary w-100"><i class="bi bi-search"></i> Prevalidar sin aplicar cambios</button></div>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="lm-panel h-100">
                <h2 class="lm-title h5 mb-3"><i class="bi bi-shield-check me-1"></i> Flujo protegido</h2>
                <div class="row g-3">
                    <div class="col-md-6"><div class="lm-step"><strong>1. Identificación</strong><div class="small text-muted mt-1">Comprueba folio completo, RUT y dígito verificador.</div></div></div>
                    <div class="col-md-6"><div class="lm-step"><strong>2. Prevalidación</strong><div class="small text-muted mt-1">Clasifica cambios, estados repetidos, duplicados y conflictos.</div></div></div>
                    <div class="col-md-6"><div class="lm-step"><strong>3. Confirmación</strong><div class="small text-muted mt-1">Vuelve a validar y aplica todos los cambios en una transacción.</div></div></div>
                    <div class="col-md-6"><div class="lm-step"><strong>4. Reversa</strong><div class="small text-muted mt-1">Restaura la carga completa si ninguna licencia fue modificada después.</div></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="lm-panel mt-4">
        <h2 class="lm-title h5 mb-3"><i class="bi bi-clock-history me-1"></i> Cargas de actualización</h2>
        <table class="lm-table">
            <thead><tr><th>Archivo</th><th>Fecha</th><th>Estado</th><th>Filas</th><th>Actualizables/aplicadas</th><th>Inconsistencias</th><th></th></tr></thead>
            <tbody>
            @forelse($importaciones as $importacion)
                @php($resumen = (array) $importacion->resumen_json)
                <tr>
                    <td><strong>{{ $importacion->nombre_archivo }}</strong><br><small class="text-muted">{{ optional($importacion->usuario)->name ?: 'Sistema' }}</small></td>
                    <td>{{ optional($importacion->created_at)->format('d-m-Y H:i') }}</td>
                    <td><span class="lm-pill">{{ ucfirst($importacion->estado) }}</span></td>
                    <td>{{ number_format($importacion->total_filas, 0, ',', '.') }}</td>
                    <td>{{ number_format($importacion->estado === 'prevalidado' ? ($resumen['actualizables'] ?? 0) : $importacion->total_actualizadas, 0, ',', '.') }}</td>
                    <td>{{ number_format($importacion->total_inconsistencias, 0, ',', '.') }}</td>
                    <td class="text-end"><a href="{{ route('tramites.licencias-medicas.actualizaciones.show', $importacion) }}" class="lm-btn-outline"><i class="bi bi-eye"></i> Revisar</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Aún no existen cargas de actualización masiva.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-3">{{ $importaciones->links() }}</div>
    </div>
</div>
@endsection
