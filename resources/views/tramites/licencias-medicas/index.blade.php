@extends('layouts.app')

@push('styles')
<style>
    .cf-page-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);overflow:hidden}.cf-page-header__top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:1.5rem 1.75rem 1.25rem}.cf-page-header__eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.45rem}.cf-page-header__eyebrow-icon{width:2.75rem;height:2.75rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1d4ed8 0%,#2563eb 100%);color:#fff;box-shadow:0 10px 24px rgba(37,99,235,.28);font-size:1.2rem}.cf-page-header__title{font-size:clamp(1.7rem,2vw,2.2rem);line-height:1.1;font-weight:800;color:#0f172a;margin-bottom:.4rem}.cf-page-header__subtitle{color:#475569;font-size:1rem;margin-bottom:0;max-width:60rem}.cf-summary-strip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:1rem;padding:1.2rem 1.75rem 1.75rem;border-top:1px solid #e5edf6;background:linear-gradient(180deg,#fcfdff 0%,#f8fbff 100%)}.cf-summary-card{border:1px solid #dbeafe;border-radius:18px;background:#fff;padding:1rem;box-shadow:0 12px 24px rgba(15,23,42,.06)}.cf-summary-card__label{font-size:.78rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.06em}.cf-summary-card__value{font-size:1.7rem;font-weight:900;color:#0f172a}.cf-btn-primary,.cf-btn-secondary,.cf-btn-outline,.cf-btn-danger{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:12px;padding:.65rem .9rem;font-weight:800;text-decoration:none;border:1px solid transparent}.cf-btn-primary{background:#2563eb;color:#fff}.cf-btn-secondary{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.cf-btn-outline{background:#fff;color:#334155;border-color:#cbd5e1}.cf-btn-danger{background:#fee2e2;color:#991b1b;border-color:#fecaca}.cf-panel{background:#fff;border:1px solid #d9e4f3;border-radius:22px;box-shadow:0 18px 42px rgba(15,23,42,.07);padding:1.25rem}.cf-table{width:100%;border-collapse:separate;border-spacing:0 .7rem}.cf-table thead th{font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;padding:.4rem .75rem}.cf-table tbody tr{background:#fff;box-shadow:0 10px 22px rgba(15,23,42,.06)}.cf-table tbody td{padding:1rem .75rem;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;vertical-align:middle}.cf-table tbody td:first-child{border-left:1px solid #e2e8f0;border-radius:16px 0 0 16px}.cf-table tbody td:last-child{border-right:1px solid #e2e8f0;border-radius:0 16px 16px 0}.cf-pill{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.35rem .65rem;font-size:.78rem;font-weight:800;background:#eef2ff;color:#3730a3}.cf-pill.is-warn{background:#fff7ed;color:#9a3412}.cf-pill.is-ok{background:#ecfdf5;color:#047857}@media(max-width:992px){.cf-summary-strip{grid-template-columns:repeat(2,minmax(0,1fr))}.cf-page-header__top{flex-direction:column}.cf-table{display:block;overflow-x:auto}}
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="cf-page-header mb-4">
        <div class="cf-page-header__top">
            <div>
                <div class="cf-page-header__eyebrow"><span class="cf-page-header__eyebrow-icon"><i class="bi bi-heart-pulse"></i></span> Gestión de licencias médicas</div>
                <h1 class="cf-page-header__title">Licencias médicas</h1>
                <p class="cf-page-header__subtitle">Ingreso digital o escaneado, respaldo documental, control por folio tipo-cuerpo-DV y asociación con Administración Central o con el padrón vigente de establecimientos.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if($permisos['importacion'])
                    <a href="{{ route('tramites.licencias-medicas.importar-seguimiento') }}" class="cf-btn-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> Importar seguimiento</a>
                    <a href="{{ route('tramites.licencias-medicas.errores.index') }}" class="cf-btn-secondary"><i class="bi bi-exclamation-triangle"></i> Errores de importación</a>
                    <a href="{{ route('tramites.licencias-medicas.actualizaciones.index') }}" class="cf-btn-secondary"><i class="bi bi-arrow-repeat"></i> Actualización masiva</a>
                @endif
                @if($permisos['configuracion'])
                    <a href="{{ route('tramites.licencias-medicas.feriados.index') }}" class="cf-btn-secondary"><i class="bi bi-calendar2-week"></i> Feriados</a>
                @endif
                @if($permisos['digitacion'])
                    <a href="{{ route('tramites.licencias-medicas.create') }}" class="cf-btn-primary"><i class="bi bi-plus-circle"></i> Nueva licencia</a>
                @endif
            </div>
        </div>
        <div class="cf-summary-strip">
            <div class="cf-summary-card"><div class="cf-summary-card__label">Total</div><div class="cf-summary-card__value">{{ number_format($metricas['total'],0,',','.') }}</div></div>
            <div class="cf-summary-card"><div class="cf-summary-card__label">Mes actual</div><div class="cf-summary-card__value">{{ number_format($metricas['mes'],0,',','.') }}</div></div>
            <div class="cf-summary-card"><div class="cf-summary-card__label">Digitales</div><div class="cf-summary-card__value">{{ number_format($metricas['digitales'],0,',','.') }}</div></div>
            <div class="cf-summary-card"><div class="cf-summary-card__label">Escaneadas</div><div class="cf-summary-card__value">{{ number_format($metricas['escaneadas'],0,',','.') }}</div></div>
            <div class="cf-summary-card"><div class="cf-summary-card__label">Adm. Central</div><div class="cf-summary-card__value">{{ number_format($metricas['administracion_central'] ?? 0,0,',','.') }}</div></div>
            <div class="cf-summary-card"><div class="cf-summary-card__label">Sin asociación</div><div class="cf-summary-card__value">{{ number_format($metricas['sin_asociacion'],0,',','.') }}</div></div>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success rounded-4 shadow-sm">{{ session('success') }}</div>@endif

    <div class="cf-panel mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label fw-bold">Buscar</label><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Folio, RUT o nombre"></div>
            <div class="col-md-2"><label class="form-label fw-bold">Año</label><input type="number" name="anio" value="{{ request('anio') }}" class="form-control" placeholder="2026"></div>
            <div class="col-md-1"><label class="form-label fw-bold">Mes</label><input type="number" min="1" max="12" name="mes" value="{{ request('mes') }}" class="form-control" placeholder="1-12"></div>
            <div class="col-md-2"><label class="form-label fw-bold">Estado administrativo</label><select name="estado_administrativo" class="form-select"><option value="">Todos</option>@foreach($estadosAdministrativos as $codigo => $etiqueta)<option value="{{ $codigo }}" @selected(request('estado_administrativo') === $codigo)>{{ $etiqueta }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label fw-bold">Origen</label><select name="origen" class="form-select"><option value="">Todos</option><option value="digital_pdf" @selected(request('origen')==='digital_pdf')>Digital</option><option value="escaneada_manual" @selected(request('origen')==='escaneada_manual')>Escaneada</option><option value="importacion_excel_seguimiento" @selected(request('origen')==='importacion_excel_seguimiento')>Importación</option></select></div>
            <div class="col-md-2 d-flex gap-2"><button class="cf-btn-secondary flex-fill" type="submit"><i class="bi bi-search"></i> Filtrar</button><a href="{{ route('tramites.licencias-medicas.index') }}" class="cf-btn-outline"><i class="bi bi-x-lg"></i></a></div>
        </form>
    </div>

    <div class="cf-panel">
        <table class="cf-table">
            <thead><tr><th>Folio</th><th>Funcionario</th><th>Reposo</th><th>Origen</th><th>Dependencia</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                @forelse($licencias as $licencia)
                    <tr>
                        <td><strong>{{ $licencia->folio_licencia }}</strong><br><small class="text-muted">Tipo {{ $licencia->tipo_ingreso_licencia }} / cuerpo {{ $licencia->cuerpo_licencia }}</small></td>
                        <td><strong>{{ $licencia->nombre_funcionario }}</strong><br><small class="text-muted">{{ $licencia->rut_formateado }}</small></td>
                        <td>{{ optional($licencia->fecha_inicio)->format('d-m-Y') }} @if($licencia->fecha_termino) al {{ $licencia->fecha_termino->format('d-m-Y') }} @endif<br><small class="text-muted">{{ $licencia->dias_solicitados ?? '-' }} solicitados / {{ $licencia->dias_corridos ?? '-' }} corridos / {{ $licencia->dias_laborales ?? '-' }} laborales</small></td>
                        <td>
                            @php
                                $origenLabel = match($licencia->origen_ingreso) {
                                    'digital_pdf' => 'Digital',
                                    'escaneada_manual' => 'Escaneada',
                                    'importacion_excel_seguimiento' => 'Importación',
                                    default => 'Otro',
                                };
                            @endphp
                            <span class="cf-pill {{ $licencia->origen_ingreso === 'digital_pdf' ? 'is-ok' : 'is-warn' }}">{{ $origenLabel }}</span>
                        </td>
                        <td>
                            @if($licencia->tipo_dependencia === 'administracion_central')
                                <strong>Administración Central</strong><br><small class="text-muted">{{ $licencia->subdireccion ?: ($licencia->unidad_departamento ?: '-') }}</small>
                            @else
                                {{ $licencia->establecimiento_nombre ?: 'Sin asociación' }}<br><small class="text-muted">{{ $licencia->comuna ?: '-' }}</small>
                            @endif
                        </td>
                        <td><span class="cf-pill">{{ $licencia->estado_administrativo_label }}</span></td>
                        <td class="text-end"><a href="{{ route('tramites.licencias-medicas.show', $licencia) }}" class="cf-btn-outline"><i class="bi bi-eye"></i> Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-5">No hay licencias médicas registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-3">{{ $licencias->links() }}</div>
    </div>
</div>
@endsection
