@extends('layouts.app')

@section('content')
<div class="va-shell" data-votaciones-admin>
    <header class="va-topbar"><div><p class="va-topbar__eyebrow">Trazabilidad inmutable</p><h1>Bitácora operacional</h1><p>Línea de tiempo de acciones, cambios de estado y correcciones administrativas.</p></div></header>
    @include('votaciones.partials.admin-nav')

    <form method="GET" class="va-filterbar">
        <label><span>Jornada</span><select class="form-select" name="jornada"><option value="">Todas</option>@foreach($jornadas as $opcion)<option value="{{ $opcion->slug }}" @selected(request('jornada') === $opcion->slug)>{{ $opcion->nombre }}</option>@endforeach</select></label>
        <label><span>Grupo</span><select class="form-select" name="grupo"><option value="">Todos</option>@foreach($grupos as $grupo)<option value="{{ $grupo->id }}" @selected((int) request('grupo') === $grupo->id)>{{ $grupo->numero }}. {{ $grupo->nombre }}</option>@endforeach</select></label>
        <label><span>Evento</span><select class="form-select" name="evento"><option value="">Todos</option>@foreach($tiposEvento as $tipo)<option value="{{ $tipo }}" @selected(request('evento') === $tipo)>{{ str($tipo)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
        <label><span>Usuario</span><select class="form-select" name="usuario"><option value="">Todos</option>@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected((int) request('usuario') === $usuario->id)>{{ $usuario->display_name }}</option>@endforeach</select></label>
        <label><span>Fecha</span><input class="form-control" type="date" name="fecha" value="{{ request('fecha') }}"></label>
        <label><span>Establecimiento</span><input class="form-control" type="search" name="establecimiento" value="{{ request('establecimiento') }}" placeholder="Nombre o RBD"></label>
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
        @if(request()->query())<a class="btn btn-outline-secondary" href="{{ route('votaciones.admin.bitacora.index') }}">Limpiar</a>@endif
    </form>

    <section class="va-card"><div class="va-card__header"><div><h2>{{ $eventos->total() }} eventos encontrados</h2><p>Los registros no pueden editarse ni eliminarse.</p></div><span class="va-status"><i class="bi bi-lock-fill"></i> Inmutable</span></div><div class="va-card__body">
        @if($eventos->isEmpty())
            <div class="va-empty"><div><i class="bi bi-clock-history"></i><h2>Sin eventos para mostrar</h2><p>Ajusta los filtros para consultar otro tramo de la bitácora.</p></div></div>
        @else
            <div class="va-timeline">
                @foreach($eventos as $evento)
                    <article class="va-timeline__item"><time class="va-timeline__time" datetime="{{ $evento->created_at->toIso8601String() }}">{{ $evento->created_at->timezone(config('votaciones.timezone'))->format('H:i:s') }}<br>{{ $evento->created_at->timezone(config('votaciones.timezone'))->format('d-m-Y') }}</time><span class="va-timeline__dot"></span><div class="va-timeline__content"><strong>{{ str($evento->evento)->replace('_', ' ')->title() }}</strong><p>{{ $evento->descripcion }}</p><small>{{ $evento->jornada?->nombre }}@if($evento->grupo) · {{ $evento->grupo->nombre }}@endif @if($evento->ruta?->establecimiento) · {{ $evento->ruta->establecimiento->nombre_establecimiento }}@endif · Usuario: {{ $evento->usuario?->display_name ?? 'Sistema' }}</small></div></article>
                @endforeach
            </div>
        @endif
    </div></section>
    <div class="mt-3">{{ $eventos->links() }}</div>
</div>
@endsection

@push('styles')
    @vite('resources/css/votaciones-admin.css')
@endpush
@push('scripts')
    @vite('resources/js/votaciones-admin.js')
@endpush
