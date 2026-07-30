@extends('layouts.app')

@push('styles')
<style>
    .cf-page-header{background:linear-gradient(180deg,#fff 0%,#f8fbff 100%);border:1px solid #d9e4f3;border-radius:24px;box-shadow:0 18px 44px rgba(15,23,42,.08);overflow:hidden}.cf-page-header__top{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:1.5rem 1.75rem}.cf-page-header__eyebrow{display:inline-flex;align-items:center;gap:.5rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.45rem}.cf-page-header__eyebrow-icon{width:2.75rem;height:2.75rem;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1d4ed8 0%,#2563eb 100%);color:#fff;box-shadow:0 10px 24px rgba(37,99,235,.28);font-size:1.2rem}.cf-page-header__title{font-size:clamp(1.7rem,2vw,2.2rem);line-height:1.1;font-weight:800;color:#0f172a;margin-bottom:.4rem}.cf-page-header__subtitle{color:#475569;font-size:1rem;margin-bottom:0;max-width:60rem}.cf-summary-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;padding:0 1.75rem 1.75rem}.cf-summary-card{border:1px solid #dbeafe;border-radius:18px;background:#fff;padding:1rem;box-shadow:0 12px 24px rgba(15,23,42,.06)}.cf-summary-card__label{font-size:.78rem;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.06em}.cf-summary-card__value{font-size:1.7rem;font-weight:900;color:#0f172a}.cf-panel{background:#fff;border:1px solid #d9e4f3;border-radius:22px;box-shadow:0 18px 42px rgba(15,23,42,.07);padding:1.25rem}.cf-section-title{font-weight:900;color:#0f172a;display:flex;align-items:center;gap:.5rem;margin-bottom:1rem}.cf-btn-primary,.cf-btn-secondary,.cf-btn-outline,.cf-btn-danger{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:12px;padding:.65rem .9rem;font-weight:800;text-decoration:none;border:1px solid transparent}.cf-btn-primary{background:#2563eb;color:#fff}.cf-btn-secondary{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.cf-btn-outline{background:#fff;color:#334155;border-color:#cbd5e1}.cf-btn-danger{background:#fee2e2;color:#991b1b;border-color:#fecaca}.cf-table{width:100%;border-collapse:separate;border-spacing:0 .7rem}.cf-table thead th{font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;padding:.4rem .75rem}.cf-table tbody tr{background:#fff;box-shadow:0 10px 22px rgba(15,23,42,.06)}.cf-table tbody td{padding:1rem .75rem;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;vertical-align:middle}.cf-table tbody td:first-child{border-left:1px solid #e2e8f0;border-radius:16px 0 0 16px}.cf-table tbody td:last-child{border-right:1px solid #e2e8f0;border-radius:0 16px 16px 0}.cf-pill{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:.35rem .65rem;font-size:.78rem;font-weight:800;background:#eef2ff;color:#3730a3}.cf-pill.is-ok{background:#ecfdf5;color:#047857}.cf-pill.is-warn{background:#fff7ed;color:#9a3412}@media(max-width:992px){.cf-summary-strip{grid-template-columns:1fr}.cf-page-header__top{flex-direction:column}.cf-table{display:block;overflow-x:auto}}
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="cf-page-header mb-4">
        <div class="cf-page-header__top">
            <div>
                <div class="cf-page-header__eyebrow"><span class="cf-page-header__eyebrow-icon"><i class="bi bi-calendar2-week"></i></span> Configuración licencias médicas</div>
                <h1 class="cf-page-header__title">Feriados para cálculo de días laborales</h1>
                <p class="cf-page-header__subtitle">Administra los feriados que se descuentan junto a sábados y domingos al calcular los días laborales de una licencia médica.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('tramites.licencias-medicas.index') }}" class="cf-btn-outline"><i class="bi bi-arrow-left"></i> Volver a licencias</a>
            </div>
        </div>
        <div class="cf-summary-strip">
            <div class="cf-summary-card"><div class="cf-summary-card__label">Año</div><div class="cf-summary-card__value">{{ $metricas['anio'] }}</div></div>
            <div class="cf-summary-card"><div class="cf-summary-card__label">Feriados activos</div><div class="cf-summary-card__value">{{ number_format($metricas['activos'],0,',','.') }}</div></div>
            <div class="cf-summary-card"><div class="cf-summary-card__label">Total año</div><div class="cf-summary-card__value">{{ number_format($metricas['total'],0,',','.') }}</div></div>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success rounded-4 shadow-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger rounded-4 shadow-sm"><strong>Revise el formulario:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="cf-panel">
                <div class="cf-section-title"><i class="bi bi-plus-circle"></i> Agregar feriado</div>
                <form method="POST" action="{{ route('tramites.licencias-medicas.feriados.store') }}" class="row g-3">
                    @csrf
                    <div class="col-12"><label class="form-label fw-bold">Fecha</label><input type="date" name="fecha" class="form-control" value="{{ old('fecha') }}" required></div>
                    <div class="col-12"><label class="form-label fw-bold">Nombre</label><input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" placeholder="Ej.: Fiestas Patrias" required></div>
                    <div class="col-12"><label class="form-label fw-bold">Tipo</label><select name="tipo" class="form-select" required>@foreach(['nacional'=>'Nacional','regional'=>'Regional','institucional'=>'Institucional','otro'=>'Otro'] as $k=>$v)<option value="{{ $k }}" @selected(old('tipo','nacional')===$k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="activo" id="activoNuevo" checked><label class="form-check-label fw-bold" for="activoNuevo">Activo para cálculo</label></div></div>
                    <div class="col-12"><button type="submit" class="cf-btn-primary w-100"><i class="bi bi-save"></i> Guardar feriado</button></div>
                </form>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="cf-panel mb-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3"><label class="form-label fw-bold">Año</label><input type="number" name="anio" class="form-control" value="{{ request('anio', $anio) }}"></div>
                    <div class="col-md-3"><label class="form-label fw-bold">Tipo</label><select name="tipo" class="form-select"><option value="">Todos</option>@foreach(['nacional'=>'Nacional','regional'=>'Regional','institucional'=>'Institucional','otro'=>'Otro'] as $k=>$v)<option value="{{ $k }}" @selected(request('tipo')===$k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label fw-bold">Estado</label><select name="estado" class="form-select"><option value="todos" @selected(request('estado')==='todos')>Todos</option><option value="activos" @selected(request('estado','activos')==='activos')>Activos</option><option value="inactivos" @selected(request('estado')==='inactivos')>Inactivos</option></select></div>
                    <div class="col-md-3 d-flex gap-2"><button class="cf-btn-secondary flex-fill" type="submit"><i class="bi bi-search"></i> Filtrar</button><a href="{{ route('tramites.licencias-medicas.feriados.index') }}" class="cf-btn-outline"><i class="bi bi-x-lg"></i></a></div>
                </form>
            </div>

            <div class="cf-panel">
                <div class="cf-section-title"><i class="bi bi-list-check"></i> Feriados registrados</div>
                <table class="cf-table">
                    <thead><tr><th>Fecha</th><th>Nombre</th><th>Tipo</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    @forelse($feriados as $feriado)
                        <tr>
                            <td><strong>{{ $feriado->fecha->format('d-m-Y') }}</strong></td>
                            <td><input type="text" name="nombre" form="form-update-feriado-{{ $feriado->id }}" class="form-control" value="{{ $feriado->nombre }}" required></td>
                            <td><select name="tipo" form="form-update-feriado-{{ $feriado->id }}" class="form-select">@foreach(['nacional'=>'Nacional','regional'=>'Regional','institucional'=>'Institucional','otro'=>'Otro'] as $k=>$v)<option value="{{ $k }}" @selected($feriado->tipo===$k)>{{ $v }}</option>@endforeach</select></td>
                            <td><div class="form-check"><input class="form-check-input" type="checkbox" value="1" name="activo" form="form-update-feriado-{{ $feriado->id }}" id="activo{{ $feriado->id }}" @checked($feriado->activo)><label class="form-check-label" for="activo{{ $feriado->id }}"><span class="cf-pill {{ $feriado->activo ? 'is-ok' : 'is-warn' }}">{{ $feriado->activo ? 'Activo' : 'Inactivo' }}</span></label></div></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2 flex-wrap">
                                    <form id="form-update-feriado-{{ $feriado->id }}" method="POST" action="{{ route('tramites.licencias-medicas.feriados.update', $feriado) }}" class="m-0">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit" class="cf-btn-secondary"><i class="bi bi-save"></i></button>
                                    </form>
                                    <form method="POST" action="{{ route('tramites.licencias-medicas.feriados.destroy', $feriado) }}" class="m-0" onsubmit="return confirm('¿Eliminar este feriado?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cf-btn-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-5">No hay feriados registrados para el filtro seleccionado.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="mt-3">{{ $feriados->links() }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
