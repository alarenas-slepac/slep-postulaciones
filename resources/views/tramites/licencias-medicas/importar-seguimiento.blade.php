@extends('layouts.app')

@push('styles')
<style>
    .cf-page-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);overflow:hidden}.cf-page-header__top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:1.5rem 1.75rem}.cf-page-header__eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.45rem}.cf-page-header__eyebrow-icon{width:2.75rem;height:2.75rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1d4ed8 0%,#2563eb 100%);color:#fff;box-shadow:0 10px 24px rgba(37,99,235,.28);font-size:1.2rem}.cf-page-header__title{font-size:clamp(1.7rem,2vw,2.2rem);line-height:1.1;font-weight:800;color:#0f172a;margin-bottom:.4rem}.cf-page-header__subtitle{color:#475569;font-size:1rem;margin-bottom:0;max-width:60rem}.cf-panel{background:#fff;border:1px solid #d9e4f3;border-radius:22px;box-shadow:0 18px 42px rgba(15,23,42,.07);padding:1.25rem}.cf-btn-primary,.cf-btn-secondary,.cf-btn-outline{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:12px;padding:.65rem .9rem;font-weight:800;text-decoration:none;border:1px solid transparent}.cf-btn-primary{background:#2563eb;color:#fff}.cf-btn-secondary{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.cf-btn-outline{background:#fff;color:#334155;border-color:#cbd5e1}.cf-section-title{font-size:1rem;font-weight:900;color:#0f172a;display:flex;align-items:center;gap:.5rem;margin-bottom:1rem}.cf-help{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:18px;padding:1rem;color:#475569}.cf-summary-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:1rem}.cf-summary-card{border:1px solid #dbeafe;border-radius:18px;background:#fff;padding:1rem;box-shadow:0 12px 24px rgba(15,23,42,.06)}.cf-summary-card__label{font-size:.74rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.06em}.cf-summary-card__value{font-size:1.55rem;font-weight:900;color:#0f172a}.cf-table{width:100%;border-collapse:separate;border-spacing:0 .55rem}.cf-table thead th{font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;padding:.4rem .75rem}.cf-table tbody tr{background:#fff;box-shadow:0 10px 22px rgba(15,23,42,.05)}.cf-table tbody td{padding:.85rem .75rem;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;vertical-align:top}.cf-table tbody td:first-child{border-left:1px solid #e2e8f0;border-radius:14px 0 0 14px}.cf-table tbody td:last-child{border-right:1px solid #e2e8f0;border-radius:0 14px 14px 0}@media(max-width:992px){.cf-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cf-page-header__top{flex-direction:column}.cf-table{display:block;overflow-x:auto}}
</style>
@endpush

@section('content')
@php($result = session('import_result'))
<div class="container-fluid py-4">
    <div class="cf-page-header mb-4">
        <div class="cf-page-header__top">
            <div>
                <div class="cf-page-header__eyebrow"><span class="cf-page-header__eyebrow-icon"><i class="bi bi-file-earmark-spreadsheet"></i></span> Licencias médicas</div>
                <h1 class="cf-page-header__title">Importar seguimiento histórico</h1>
                <p class="cf-page-header__subtitle">Carga las hojas 2026, 2025 y datos del Excel de seguimiento actual, normalizando RUT, folio de licencia y asociación con Administración Central o establecimientos.</p>
            </div>
            <a href="{{ route('tramites.licencias-medicas.index') }}" class="cf-btn-outline"><i class="bi bi-arrow-left"></i> Volver al listado</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success rounded-4 shadow-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger rounded-4 shadow-sm"><strong>Revise la carga:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="cf-panel">
                <div class="cf-section-title"><i class="bi bi-cloud-arrow-up"></i> Archivo de seguimiento</div>
                <form method="POST" action="{{ route('tramites.licencias-medicas.importar-seguimiento.store') }}" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label class="form-label fw-bold">Excel seguimiento licencias médicas</label>
                        <input type="file" name="archivo_seguimiento" accept=".xlsx,.xls" class="form-control" required>
                        <div class="form-text">Se procesan las hojas 2026, 2025 y datos. El archivo original queda guardado como respaldo de importación. Para planillas grandes, el importador usa lectura streaming XLSX, evitando cargar el libro completo en memoria. Esto reduce errores 500/504 en cPanel.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Tipo ingreso por defecto</label>
                        <select name="tipo_ingreso_default" class="form-select" required>
                            <option value="3" selected>3 - Licencia médica electrónica / formato habitual</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="4">4</option>
                        </select>
                        <div class="form-text">El Excel histórico trae cuerpo y DV, pero no siempre trae el tipo de ingreso. Este valor se usa para construir el folio completo tipo-cuerpo-DV.</div>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('tramites.licencias-medicas.index') }}" class="cf-btn-outline">Cancelar</a>
                        <button class="cf-btn-primary" type="submit"><i class="bi bi-upload"></i> Importar seguimiento</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="cf-panel h-100">
                <div class="cf-section-title"><i class="bi bi-info-circle"></i> Reglas aplicadas</div>
                <div class="cf-help">
                    <p class="mb-2"><strong>Duplicidad:</strong> no se crean registros repetidos. El sistema valida por tipo de ingreso, cuerpo y DV de licencia médica.</p>
                    <p class="mb-2"><strong>RUT:</strong> se normaliza a formato sin puntos ni guion, con DV incluido, para cruces posteriores con remuneraciones y COMPIN.</p>
                    <p class="mb-2"><strong>Dependencia:</strong> se busca primero en funcionarios de Administración Central y luego en reemplazos_personal usando sólo el mes más reciente. Si no cruza, se mantiene la dependencia/comuna del Excel como dato manual.</p>
                    <p class="mb-2"><strong>Historial:</strong> cada creación o actualización queda registrada con usuario, fecha y origen de importación, usando registro liviano para evitar saturación en planillas de 10.000+ filas.</p><p class="mb-0"><strong>Procesamiento masivo:</strong> las hojas se leen por bloques, se desactiva el query log y se reutiliza el cruce de funcionarios por RUT para reducir carga del servidor.</p>
                </div>
            </div>
        </div>
    </div>

    @if($result)
        @php($totales = $result['totales'] ?? [])
        <div class="cf-panel mt-4">
            <div class="cf-section-title"><i class="bi bi-clipboard-check"></i> Resultado de importación</div>
            <div class="cf-summary-grid mb-4">
                <div class="cf-summary-card"><div class="cf-summary-card__label">Filas leídas</div><div class="cf-summary-card__value">{{ number_format($totales['filas'] ?? 0,0,',','.') }}</div></div>
                <div class="cf-summary-card"><div class="cf-summary-card__label">Importadas</div><div class="cf-summary-card__value">{{ number_format($totales['importadas'] ?? 0,0,',','.') }}</div></div>
                <div class="cf-summary-card"><div class="cf-summary-card__label">Actualizadas</div><div class="cf-summary-card__value">{{ number_format($totales['actualizadas'] ?? 0,0,',','.') }}</div></div>
                <div class="cf-summary-card"><div class="cf-summary-card__label">Omitidas</div><div class="cf-summary-card__value">{{ number_format($totales['omitidas'] ?? 0,0,',','.') }}</div></div>
                <div class="cf-summary-card"><div class="cf-summary-card__label">Duplicadas</div><div class="cf-summary-card__value">{{ number_format($totales['duplicadas'] ?? 0,0,',','.') }}</div></div>
                <div class="cf-summary-card"><div class="cf-summary-card__label">Inconsistencias</div><div class="cf-summary-card__value">{{ number_format($totales['inconsistencias'] ?? 0,0,',','.') }}</div></div>
            </div>

            @if(!empty($result['resumen']['hojas']))
                <table class="cf-table">
                    <thead><tr><th>Hoja</th><th>Estado</th><th>Filas</th><th>Importadas</th><th>Actualizadas</th><th>Omitidas</th><th>Inconsistencias</th></tr></thead>
                    <tbody>
                    @foreach($result['resumen']['hojas'] as $hoja => $info)
                        <tr>
                            <td><strong>{{ $hoja }}</strong></td>
                            <td>{{ $info['estado'] ?? '-' }}</td>
                            <td>{{ number_format($info['filas'] ?? 0,0,',','.') }}</td>
                            <td>{{ number_format($info['importadas'] ?? 0,0,',','.') }}</td>
                            <td>{{ number_format($info['actualizadas'] ?? 0,0,',','.') }}</td>
                            <td>{{ number_format($info['omitidas'] ?? 0,0,',','.') }}</td>
                            <td>{{ number_format($info['inconsistencias'] ?? 0,0,',','.') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            @if(!empty($result['resumen']['inconsistencias']))
                <div class="mt-4">
                    <h5 class="fw-bold">Muestra de inconsistencias</h5>
                    <table class="cf-table">
                        <thead><tr><th>Hoja</th><th>Fila</th><th>Motivo</th></tr></thead>
                        <tbody>
                        @foreach($result['resumen']['inconsistencias'] as $item)
                            <tr><td>{{ $item['hoja'] ?? '-' }}</td><td>{{ $item['fila'] ?? '-' }}</td><td>{{ $item['motivo'] ?? '-' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
