@extends('layouts.app')

@section('content')
<div class="va-shell va-operation" data-votaciones-admin>
    <header class="va-operation-hero"><div class="va-operation-hero__top"><div><p class="va-topbar__eyebrow">Operación en terreno</p><h1>Mis grupos</h1><p>Selecciona un grupo asignado para continuar su recorrido.</p></div>@can('votaciones.manage-jornadas')<a class="btn btn-sm btn-outline-light" href="{{ route('votaciones.admin.dashboard') }}"><i class="bi bi-speedometer2"></i> Centro de control</a>@endcan</div></header>
    @include('votaciones.partials.alertas')
    <div class="row g-3 mt-1">
        @forelse($grupos as $grupo)
            @php
                $finalizadas = $grupo->rutas->filter(fn($ruta) => $ruta->visita?->estado === 'finalizada')->count();
                $total = $grupo->rutas->count();
                $porcentaje = $total ? (int) round(($finalizadas / $total) * 100) : 0;
                $actual = $grupo->rutas->first(fn($ruta) => in_array($ruta->visita?->estado, ['en_traslado', 'en_votacion'], true));
            @endphp
            <div class="col-md-6"><a href="{{ route('votaciones.operacion.show', $grupo) }}" class="va-card d-block h-100 p-3 text-decoration-none text-body"><div class="d-flex align-items-start justify-content-between gap-2"><div><span class="va-field-label">{{ $grupo->jornada->nombre }}</span><h2 class="h5 mb-1 text-dark">{{ $grupo->numero }}. {{ $grupo->nombre }}</h2></div><span class="va-status va-status--{{ $grupo->estado }}">{{ str($grupo->estado)->replace('_', ' ') }}</span></div><div class="va-next mt-3"><span>Ubicación operacional</span><strong>{{ $actual?->establecimiento?->nombre_establecimiento ?? ($grupo->estado === 'pendiente' ? 'Recorrido aún no iniciado' : 'Sin visita activa') }}</strong></div><div class="va-progress mt-3"><div class="va-progress__bar"><span style="width:{{ $porcentaje }}%"></span></div><div class="va-progress__meta"><span>{{ $finalizadas }} de {{ $total }} visitas</span><strong>{{ $porcentaje }}%</strong></div></div>@if($grupo->incidencias_abiertas_count)<div class="text-warning-emphasis small fw-bold mt-2"><i class="bi bi-exclamation-triangle"></i> {{ $grupo->incidencias_abiertas_count }} incidencia(s) abierta(s)</div>@endif</a></div>
        @empty
            <div class="col-12"><section class="va-card va-empty"><div><i class="bi bi-people"></i><h2>No tienes grupos activos asignados</h2><p>Cuando una jornada sea publicada y te asignen a un equipo, aparecerá aquí.</p></div></section></div>
        @endforelse
    </div>
</div>
@endsection

@push('styles')
    @vite('resources/css/votaciones-admin.css')
@endpush
@push('scripts')
    @vite('resources/js/votaciones-admin.js')
@endpush
