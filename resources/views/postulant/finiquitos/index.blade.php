@extends('layouts.app')

@push('styles')
<style>
    .cf-page-header { background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); border: 1px solid #d9e4f3; border-radius: 24px; box-shadow: 0 18px 44px rgba(15, 23, 42, .08); overflow: hidden; }
    .cf-page-header__top { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; padding:1.5rem 1.75rem; }
    .cf-page-header__eyebrow { color:#0d6efd; font-weight:800; text-transform:uppercase; font-size:.75rem; letter-spacing:.06em; }
    .cf-page-header__title { margin: .2rem 0 .35rem; font-weight:900; color:#0f172a; }
    .cf-page-header__subtitle { color:#64748b; margin:0; max-width:780px; }
    .cf-role-pill { display:inline-flex; gap:.45rem; align-items:center; padding:.65rem .9rem; border-radius:999px; border:1px solid #cfe0ff; color:#0d47a1; text-decoration:none; font-weight:800; background:#eff6ff; }
    .cf-summary-strip { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; padding:0 1.75rem 1.5rem; }
    .cf-summary-card { background:#fff; border:1px solid #e2e8f0; border-radius:18px; padding:1rem; }
    .cf-summary-card__label { color:#64748b; font-size:.82rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    .cf-summary-card__value { font-size:1.55rem; font-weight:900; color:#0f172a; }
    .cf-panel { background:#fff; border:1px solid #d9e4f3; border-radius:24px; box-shadow:0 14px 34px rgba(15,23,42,.06); overflow:hidden; }
    .cf-panel__header { padding:1.35rem 1.5rem 1rem; border-bottom:1px solid #e2e8f0; }
    .cf-panel__eyebrow { color:#64748b; font-size:.75rem; text-transform:uppercase; font-weight:900; letter-spacing:.08em; }
    .cf-panel__title { font-size:1.25rem; font-weight:900; color:#0f172a; }
    .cf-panel__subtitle { color:#64748b; margin:.2rem 0 0; }
    .cf-table { width:100%; border-collapse:separate; border-spacing:0; }
    .cf-table th { color:#475569; font-size:.75rem; text-transform:uppercase; letter-spacing:.06em; padding:.9rem 1rem; background:#f8fafc; }
    .cf-table td { padding:1rem; border-top:1px solid #edf2f7; vertical-align:middle; }
    .cf-name { font-weight:900; color:#0f172a; }
    .cf-meta { color:#64748b; font-size:.86rem; }
    .cf-status-badge { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.35rem .65rem; font-weight:900; font-size:.78rem; }
    .cf-status-badge--success { background:#dcfce7; color:#166534; }
    .cf-btn-primary { display:inline-flex; align-items:center; gap:.45rem; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; border-radius:13px; padding:.55rem .8rem; font-weight:900; text-decoration:none; }
    .cf-empty-state { text-align:center; color:#64748b; padding:3rem 1rem; }
    .cf-empty-state__icon { width:58px; height:58px; border-radius:18px; background:#eff6ff; color:#0d47a1; display:inline-flex; align-items:center; justify-content:center; font-size:1.6rem; margin-bottom:.75rem; }
    @media (max-width: 992px) { .cf-summary-strip { grid-template-columns:1fr; } .cf-page-header__top { flex-direction:column; } }
</style>
@endpush

@section('content')
@php
    $fmtFecha = fn ($fecha) => $fecha ? \Illuminate\Support\Carbon::parse($fecha)->format('d-m-Y') : '—';
    $rutFmt = function ($rut) {
        $clean = strtoupper(preg_replace('/[^0-9K]/', '', (string) $rut));
        if (strlen($clean) < 2) return $rut ?: '—';
        $dv = substr($clean, -1);
        $cuerpo = substr($clean, 0, -1);
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $cuerpo) . '-' . $dv;
    };
    $nombreReemplazante = function ($s) {
        $user = $s->contratoPostulante?->user ?: $s->postulante?->user;
        if (!$user) return '—';
        return trim(($user->apellido_paterno ?? '') . ' ' . ($user->apellido_materno ?? '') . ' ' . ($user->nombres ?? '')) ?: '—';
    };
@endphp

<div class="container-fluid py-4">
    <div class="cf-page-header mb-4">
        <div class="cf-page-header__top">
            <div class="d-flex gap-3 align-items-start">
                <span class="cf-empty-state__icon m-0"><i class="bi bi-file-earmark-pdf"></i></span>
                <div>
                    <div class="cf-page-header__eyebrow">Postulante · Documentos laborales</div>
                    <h1 class="cf-page-header__title">Mis Finiquitos</h1>
                    <p class="cf-page-header__subtitle">Consulta y descarga tus finiquitos firmados disponibles. Sólo se muestran documentos en estado completado.</p>
                </div>
            </div>
            <a href="{{ route('dashboard') }}" class="cf-role-pill"><i class="bi bi-arrow-left"></i> Volver al panel</a>
        </div>
        <div class="cf-summary-strip">
            <div class="cf-summary-card">
                <div class="cf-summary-card__label"><i class="bi bi-check2-circle"></i> Disponibles</div>
                <div class="cf-summary-card__value">{{ number_format($finiquitos->count(), 0, ',', '.') }}</div>
            </div>
            <div class="cf-summary-card">
                <div class="cf-summary-card__label"><i class="bi bi-person-vcard"></i> Usuario</div>
                <div class="cf-summary-card__value" style="font-size:1rem;">{{ auth()->user()->rut ? $rutFmt(auth()->user()->rut) : '—' }}</div>
            </div>
            <div class="cf-summary-card">
                <div class="cf-summary-card__label"><i class="bi bi-shield-check"></i> Estado</div>
                <div class="cf-summary-card__value" style="font-size:1rem;">Completado</div>
            </div>
        </div>
    </div>

    <div class="cf-panel">
        <div class="cf-panel__header">
            <div class="cf-panel__eyebrow">Listado</div>
            <div class="cf-panel__title"><i class="bi bi-folder2-open me-1"></i> Finiquitos completados</div>
            <p class="cf-panel__subtitle">Los documentos aparecen cuando GDP carga el finiquito firmado en PDF.</p>
        </div>
        <div class="table-responsive">
            <table class="cf-table">
                <thead>
                    <tr>
                        <th>Solicitud</th>
                        <th>Establecimiento</th>
                        <th>Período</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($finiquitos as $s)
                    <tr>
                        <td>
                            <div class="cf-name">{{ $s->numero_solicitud ?: ('ID ' . $s->id) }}</div>
                            <div class="cf-meta">{{ $nombreReemplazante($s) }}</div>
                        </td>
                        <td>
                            <div class="cf-name">{{ $s->establecimiento?->nombre_establecimiento ?? '—' }}</div>
                            <div class="cf-meta">{{ $s->establecimiento?->comuna ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="cf-name">{{ $fmtFecha($s->fecha_inicio_trabajo) }} a {{ $fmtFecha($s->fecha_termino) }}</div>
                            <div class="cf-meta">Fecha de carga: {{ $fmtFecha($s->finiquito_firmado_cargado_at) }}</div>
                        </td>
                        <td><span class="cf-status-badge cf-status-badge--success"><i class="bi bi-check2-circle"></i> Completado</span></td>
                        <td class="text-end">
                            <a href="{{ route('postulant.finiquitos.descargar', $s) }}" class="cf-btn-primary"><i class="bi bi-download"></i> Descargar PDF</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="cf-empty-state">
                                <div class="cf-empty-state__icon"><i class="bi bi-inbox"></i></div>
                                <div class="fw-bold text-dark mb-1">Sin finiquitos disponibles</div>
                                <div>Actualmente no existen finiquitos completados disponibles para descarga.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
