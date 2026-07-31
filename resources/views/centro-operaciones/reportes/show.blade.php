@extends('layouts.app')

@push('styles')
    @vite('resources/css/centro-operaciones.css')
@endpush

@section('content')
@php($perfilAdmision = $reporte->establecimiento?->admisionPerfil)
<div class="co-shell">
    <header class="co-hero">
        <div class="co-establishment-identity">
            <div class="co-establishment-logo">
                @if($perfilAdmision?->logoUrl())
                    <img src="{{ $perfilAdmision->logoUrl() }}" alt="Logo de {{ $reporte->establecimiento_nombre }}">
                @else
                    <i class="bi bi-building" aria-hidden="true"></i>
                @endif
            </div>
            <div class="co-establishment-copy">
                <div class="co-eyebrow">Reporte diario · {{ $reporte->fecha_reporte->format('d-m-Y') }}</div>
                <h1>{{ $reporte->establecimiento_nombre }}</h1>
                <p>{{ $reporte->establecimiento_comuna }} · RBD {{ $reporte->establecimiento_rbd ?: '-' }} · versión {{ $reporte->version }}</p>
                <div class="co-establishment-director">
                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                    <span>Director/a</span>
                    <strong>{{ $perfilAdmision?->director_nombre ?: 'No informado en Admisión Escolar' }}</strong>
                </div>
            </div>
        </div>
        <div class="co-hero-actions"><span class="co-badge co-badge--{{ $reporte->estado_general }} co-badge--large">{{ ucfirst($reporte->estado_general) }}</span>@if($puedeEditar)<a class="btn btn-primary" href="{{ route('centro-operaciones.reportes.edit', $reporte) }}"><i class="bi bi-pencil"></i> Editar</a>@endif<a class="btn btn-outline-secondary" href="{{ route('centro-operaciones.reportes.history') }}">Volver</a></div>
    </header>
    @if(session('success'))<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>@endif

    <div class="co-detail-meta">
        <div><span>Enviado por</span><strong>{{ $reporte->reportadoPor?->name ?? 'Usuario no disponible' }}</strong></div>
        <div><span>Última actualización</span><strong>{{ $reporte->reportado_en->format('d-m-Y H:i') }} hrs.</strong></div>
        <div><span>Funcionamiento</span><strong>{{ config("centro_operaciones.funcionamientos.{$reporte->funcionamiento}.label", ucfirst($reporte->funcionamiento)) }}</strong></div>
        <div><span>Prioridad</span><strong>{{ config("centro_operaciones.prioridades.{$reporte->prioridad}.label", ucfirst($reporte->prioridad)) }}</strong></div>
    </div>

    <div class="co-grid co-grid--detail">
        <section class="co-card"><div class="co-card-head"><h2>Estado de servicios</h2></div><div class="co-detail-services">@foreach($reporte->servicios as $servicio)<div><i class="bi {{ config("centro_operaciones.servicios.{$servicio->servicio}.icon", 'bi-circle') }}"></i><span><strong>{{ config("centro_operaciones.servicios.{$servicio->servicio}.label", $servicio->servicio) }}</strong>@if($servicio->observacion)<small>{{ $servicio->observacion }}</small>@endif</span><span class="co-badge co-badge--{{ $servicio->estado }}">{{ ucfirst($servicio->estado) }}</span></div>@endforeach</div></section>
        <section class="co-card"><div class="co-card-head"><h2>Asistencia reportada</h2></div><div class="co-detail-attendance"><div><span>Estudiantes</span><strong>{{ number_format($reporte->estudiantes_presentes, 0, ',', '.') }} / {{ number_format($reporte->matricula_total, 0, ',', '.') }}</strong></div><div><span>Docentes</span><strong>{{ $reporte->docentes_presentes }} / {{ $reporte->docentes_total }}</strong></div><div><span>Asistentes</span><strong>{{ $reporte->asistentes_presentes }} / {{ $reporte->asistentes_total }}</strong></div></div><div class="co-source-note mt-3">Padrón de dotación: {{ $reporte->padron_periodo ?: 'sin período' }}</div></section>
    </div>

    <div class="co-grid co-grid--detail">
        <section class="co-card"><div class="co-card-head"><h2>Incidencias del reporte</h2></div><div class="co-list">@forelse($reporte->incidencias as $incidencia)<div class="co-list-item"><span class="co-status-bar co-status-bar--{{ $incidencia->severidad }}"></span><span><strong>{{ config("centro_operaciones.incidencias.{$incidencia->tipo}.label", $incidencia->tipo) }}</strong><small>{{ $incidencia->descripcion ?: 'Sin detalle' }} · {{ $incidencia->estado === 'resuelta' ? 'Resuelta' : 'Activa' }}</small></span></div>@empty<div class="co-empty">Sin incidencias nuevas en este reporte.</div>@endforelse @if($reporte->incidenciasResueltas->isNotEmpty())<div class="co-list-subtitle">Incidencias anteriores resueltas con este envío</div>@foreach($reporte->incidenciasResueltas as $incidencia)<div class="co-list-item"><span class="co-status-bar co-status-bar--operativo"></span><span><strong>{{ config("centro_operaciones.incidencias.{$incidencia->tipo}.label", $incidencia->tipo) }}</strong><small>Resuelta {{ $incidencia->resuelta_en?->format('d-m-Y H:i') }}</small></span></div>@endforeach @endif</div></section>
        <section class="co-card"><div class="co-card-head"><h2>Afectaciones y apoyo</h2></div>@if($reporte->afectaciones->isNotEmpty())<div class="d-flex flex-wrap gap-2 mb-3">@foreach($reporte->afectaciones as $afectacion)<span class="co-badge co-badge--alerta">{{ config("centro_operaciones.afectaciones.{$afectacion->tipo}.label", $afectacion->tipo) }}</span>@endforeach</div>@endif<p class="mb-2"><strong>Necesita apoyo:</strong> {{ $reporte->necesita_apoyo ? 'Sí' : 'No' }}</p><p class="mb-0 text-muted">{{ $reporte->apoyo_detalle ?: 'Sin solicitud de apoyo detallada.' }}</p></section>
    </div>

    <section class="co-card mb-4"><div class="co-card-head"><h2>Observaciones</h2></div><p class="mb-0">{{ $reporte->observaciones ?: 'Sin observaciones.' }}</p></section>
    <section class="co-card"><div class="co-card-head"><h2>Auditoría de versiones</h2><span class="co-count">{{ $reporte->revisiones->count() }}</span></div><div class="co-timeline">@foreach($reporte->revisiones->sortByDesc('version') as $revision)<div><i></i><span><strong>Versión {{ $revision->version }}</strong><small>{{ $revision->created_at->format('d-m-Y H:i') }} · {{ $revision->editadoPor?->name ?? 'Usuario no disponible' }}</small></span></div>@endforeach</div></section>
</div>
@endsection
