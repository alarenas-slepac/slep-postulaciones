@extends('layouts.app')

@push('styles')
<style>
    .cf-page-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);overflow:hidden}.cf-page-header__top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:1.5rem 1.75rem}.cf-page-header__eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.45rem}.cf-page-header__eyebrow-icon{width:2.75rem;height:2.75rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1d4ed8 0%,#2563eb 100%);color:#fff;box-shadow:0 10px 24px rgba(37,99,235,.28);font-size:1.2rem}.cf-page-header__title{font-size:clamp(1.7rem,2vw,2.2rem);line-height:1.1;font-weight:800;color:#0f172a;margin-bottom:.4rem}.cf-page-header__subtitle{color:#475569;font-size:1rem;margin-bottom:0;max-width:60rem}.cf-panel{background:#fff;border:1px solid #d9e4f3;border-radius:22px;box-shadow:0 18px 42px rgba(15,23,42,.07);padding:1.25rem}.cf-section-title{font-weight:900;color:#0f172a;display:flex;align-items:center;gap:.5rem;margin-bottom:1rem}.cf-btn-primary,.cf-btn-secondary,.cf-btn-outline{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:12px;padding:.65rem .9rem;font-weight:800;text-decoration:none;border:1px solid transparent}.cf-btn-primary{background:#2563eb;color:#fff}.cf-btn-secondary{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.cf-btn-outline{background:#fff;color:#334155;border-color:#cbd5e1}.cf-data-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.cf-data{border:1px solid #e2e8f0;border-radius:16px;padding:.85rem;background:#fff}.cf-data-label{font-size:.75rem;font-weight:900;color:#64748b;text-transform:uppercase;letter-spacing:.06em}.cf-data-value{font-weight:800;color:#0f172a;margin-top:.2rem}.cf-pill{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.35rem .65rem;font-size:.78rem;font-weight:800;background:#eef2ff;color:#3730a3}.cf-pill.is-warn{background:#fff7ed;color:#9a3412}.cf-pill.is-ok{background:#ecfdf5;color:#047857}@media(max-width:992px){.cf-data-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cf-page-header__top{flex-direction:column}}@media(max-width:576px){.cf-data-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $nombresDimension = [
        'administrativo' => 'Administrativo',
        'compin' => 'Resolución COMPIN',
        'recuperacion' => 'Recuperación financiera',
    ];
    $estadoActualPorDimension = [
        'administrativo' => $licencia->estado_administrativo_codigo,
        'compin' => $licencia->estado_compin_codigo,
        'recuperacion' => $licencia->estado_recuperacion_codigo,
    ];
@endphp
<div class="container-fluid py-4">
    <div class="cf-page-header mb-4">
        <div class="cf-page-header__top">
            <div>
                <div class="cf-page-header__eyebrow"><span class="cf-page-header__eyebrow-icon"><i class="bi bi-heart-pulse"></i></span> Detalle licencia médica</div>
                <h1 class="cf-page-header__title">{{ $licencia->folio_licencia }}</h1>
                <p class="cf-page-header__subtitle">{{ $licencia->nombre_funcionario }} — {{ $licencia->rut_formateado }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if($licencia->archivo_licencia_path)
                    <a href="{{ route('tramites.licencias-medicas.archivo', $licencia) }}" class="cf-btn-secondary"><i class="bi bi-download"></i> Descargar respaldo</a>
                @endif
                @if($permisos['configuracion'])
                    <a href="{{ route('tramites.licencias-medicas.feriados.index') }}" class="cf-btn-secondary"><i class="bi bi-calendar2-week"></i> Feriados</a>
                @endif
                <a href="{{ route('tramites.licencias-medicas.index') }}" class="cf-btn-outline"><i class="bi bi-arrow-left"></i> Volver</a>
            </div>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success rounded-4 shadow-sm">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger rounded-4 shadow-sm">
            <strong>No fue posible actualizar el estado:</strong>
            <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="cf-panel mb-4">
                <div class="cf-section-title"><i class="bi bi-clipboard2-pulse"></i> Datos de la licencia</div>
                <div class="cf-data-grid">
                    <div class="cf-data"><div class="cf-data-label">Folio</div><div class="cf-data-value">{{ $licencia->folio_licencia }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Origen</div><div class="cf-data-value"><span class="cf-pill {{ $licencia->origen_ingreso === 'digital_pdf' ? 'is-ok' : 'is-warn' }}">{{ match($licencia->origen_ingreso) { 'digital_pdf' => 'Digital PDF', 'escaneada_manual' => 'Escaneada/manual', 'importacion_excel_seguimiento' => 'Importación de seguimiento', default => 'Otro' } }}</span></div></div>
                    <div class="cf-data"><div class="cf-data-label">Tipo LM</div><div class="cf-data-value">{{ $licencia->tipo_licencia_descripcion }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Sistema salud</div><div class="cf-data-value">{{ $licencia->sistema_salud ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Institución salud</div><div class="cf-data-value">{{ $licencia->institucion_salud ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Inicio</div><div class="cf-data-value">{{ optional($licencia->fecha_inicio)->format('d-m-Y') ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Término</div><div class="cf-data-value">{{ optional($licencia->fecha_termino)->format('d-m-Y') ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Días solicitados</div><div class="cf-data-value">{{ $licencia->dias_solicitados ?? '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Días corridos</div><div class="cf-data-value">{{ $licencia->dias_corridos ?? '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Días laborales</div><div class="cf-data-value">{{ $licencia->dias_laborales ?? '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Extracción PDF</div><div class="cf-data-value">{{ $licencia->extraccion_pdf_estado ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Confianza</div><div class="cf-data-value">{{ $licencia->extraccion_pdf_confianza ?: '-' }}</div></div>
                </div>
                @if($permisos['digitacion'])
                    <form method="POST" action="{{ route('tramites.licencias-medicas.recalcular-dias', $licencia) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="cf-btn-secondary" onclick="return confirm('¿Recalcular días corridos y laborales usando los feriados activos?');">
                            <i class="bi bi-arrow-repeat"></i> Recalcular días laborales
                        </button>
                        <div class="form-text mt-2">El cálculo descuenta sábados, domingos y feriados activos cargados en el módulo.</div>
                    </form>
                @endif
            </div>

            <div class="cf-panel mb-4">
                <div class="cf-section-title"><i class="bi bi-signpost-split"></i> Estados del proceso</div>
                <div class="row g-3">
                    @foreach($nombresDimension as $dimension => $nombreDimension)
                        @php
                            $codigoActual = $estadoActualPorDimension[$dimension];
                        @endphp
                        <div class="col-lg-4">
                            <div class="cf-data h-100">
                                <div class="cf-data-label">{{ $nombreDimension }}</div>
                                <div class="cf-data-value mb-3">{{ $opcionesEstado[$dimension][$codigoActual] ?? 'Sin clasificar' }}</div>
                                @if($puedeGestionarEstado[$dimension])
                                    <form method="POST" action="{{ route('tramites.licencias-medicas.estado.update', $licencia) }}" class="row g-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="dimension" value="{{ $dimension }}">
                                        <div class="col-12">
                                            <label class="form-label small">Nuevo estado</label>
                                            <select name="estado_codigo" class="form-select" required>
                                                @foreach($opcionesEstado[$dimension] as $codigo => $etiqueta)
                                                    <option value="{{ $codigo }}" @selected($codigoActual === $codigo)>{{ $etiqueta }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small">Fundamento del cambio</label>
                                            <textarea name="observacion" rows="2" minlength="5" maxlength="1000" class="form-control" required></textarea>
                                        </div>
                                        <div class="col-12"><button type="submit" class="cf-btn-secondary w-100"><i class="bi bi-check2-circle"></i> Actualizar</button></div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="cf-panel mb-4">
                <div class="cf-section-title"><i class="bi bi-person-badge"></i> Funcionario y asociación</div>
                <div class="cf-data-grid">
                    <div class="cf-data"><div class="cf-data-label">Funcionario</div><div class="cf-data-value">{{ $licencia->nombre_funcionario }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">RUT</div><div class="cf-data-value">{{ $licencia->rut_formateado }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Tipo dependencia</div><div class="cf-data-value">{{ $licencia->tipo_dependencia === 'administracion_central' ? 'Administración Central' : ($licencia->tipo_dependencia === 'establecimiento' ? 'Establecimiento' : 'Sin asociación') }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Establecimiento/Dependencia</div><div class="cf-data-value">{{ $licencia->tipo_dependencia === 'administracion_central' ? ($licencia->subdireccion ?: 'Administración Central') : ($licencia->establecimiento_nombre ?: 'Sin asociación') }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Comuna</div><div class="cf-data-value">{{ $licencia->comuna ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Unidad / Departamento</div><div class="cf-data-value">{{ $licencia->unidad_departamento ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Cargo / Grado</div><div class="cf-data-value">{{ trim(($licencia->cargo ?: '') . ($licencia->grado ? ' / Grado ' . $licencia->grado : '')) ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Fuente asociación</div><div class="cf-data-value">{{ $licencia->fuente_asociacion_funcionario ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Periodo padrón</div><div class="cf-data-value">{{ $licencia->periodo_reemplazos_usado ?: '-' }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Estamento / Escalafón</div><div class="cf-data-value">{{ $licencia->estamento ?: ($licencia->escalafon ?: '-') }}</div></div>
                    <div class="cf-data"><div class="cf-data-label">Calidad jurídica</div><div class="cf-data-value">{{ $licencia->calidad_juridica ?: '-' }}</div></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="cf-panel mb-4">
                <div class="cf-section-title"><i class="bi bi-paperclip"></i> Respaldo documental</div>
                @if($licencia->archivo_licencia_path)
                    <p class="mb-2"><strong>{{ $licencia->archivo_licencia_nombre }}</strong></p>
                    <p class="text-muted small mb-3">{{ $licencia->archivo_licencia_mime }} — {{ $licencia->archivo_licencia_size ? number_format($licencia->archivo_licencia_size / 1024, 1, ',', '.') . ' KB' : 'tamaño no informado' }}</p>
                    <a href="{{ route('tramites.licencias-medicas.archivo', $licencia) }}" class="cf-btn-primary w-100"><i class="bi bi-download"></i> Descargar archivo</a>
                @else
                    <p class="text-muted mb-0">No hay archivo de respaldo asociado.</p>
                @endif
            </div>
            <div class="cf-panel">
                <div class="cf-section-title"><i class="bi bi-clock-history"></i> Historial</div>
                @forelse($licencia->historial as $h)
                    <div class="border-start ps-3 mb-3">
                        <strong>{{ $h->accion }}</strong><br>
                        <small class="text-muted">{{ optional($h->created_at)->format('d-m-Y H:i') }} — {{ optional($h->usuario)->name ?: 'Sistema' }}</small>
                        @if($h->estado_dimension)
                            @php
                                $dimensionHistorial = $h->estado_dimension;
                                $estadoAnterior = $h->estado_anterior
                                    ? ($opcionesEstado[$dimensionHistorial][$h->estado_anterior] ?? $h->estado_anterior)
                                    : 'Sin estado';
                                $estadoNuevo = $opcionesEstado[$dimensionHistorial][$h->estado_nuevo] ?? ($h->estado_nuevo ?: 'Sin estado');
                            @endphp
                            <div class="small fw-bold mt-1">{{ $nombresDimension[$dimensionHistorial] ?? ucfirst($dimensionHistorial) }}: {{ $estadoAnterior }} → {{ $estadoNuevo }}</div>
                        @endif
                        <div class="text-muted small">{{ $h->descripcion }}</div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Sin historial registrado.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
