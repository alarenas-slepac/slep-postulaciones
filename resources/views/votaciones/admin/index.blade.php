@extends('layouts.app')

@section('content')
<div class="va-shell" data-votaciones-admin>
    <header class="va-topbar"><div><p class="va-topbar__eyebrow">Gestión de jornadas</p><h1>Jornadas de votación</h1><p>Configura, publica y supervisa cada proceso electoral.</p></div><div class="va-topbar__actions"><a class="btn btn-light" href="{{ route('votaciones.admin.jornadas.create') }}"><i class="bi bi-plus-circle"></i> Nueva jornada</a></div></header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')

    <form method="GET" class="va-filterbar">
        <label><span>Buscar jornada</span><input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o slug"></label>
        <label><span>Estado</span><select class="form-select" name="estado"><option value="">Todos</option>@foreach(\App\Models\JornadaVotacion::ESTADOS as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ str($estado)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
        <button class="btn btn-primary"><i class="bi bi-search"></i> Filtrar</button>
        @if(request()->hasAny(['q', 'estado']))<a class="btn btn-outline-secondary" href="{{ route('votaciones.admin.jornadas.index') }}">Limpiar</a>@endif
    </form>

    <section class="va-card">
        @if($jornadas->isEmpty())
            <div class="va-empty"><div><i class="bi bi-calendar-x"></i><h2>No se encontraron jornadas</h2><p>Ajusta los filtros o crea una nueva jornada de votación.</p><a class="btn btn-primary" href="{{ route('votaciones.admin.jornadas.create') }}">Crear jornada</a></div></div>
        @else
            <div class="table-responsive"><table class="table va-table va-table-responsive-cards"><thead><tr><th>Jornada</th><th>Fecha / procesos</th><th>Estado</th><th>Grupos</th><th>Establecimientos</th><th>Avance</th><th>Publicación</th><th></th></tr></thead><tbody>
            @foreach($jornadas as $jornada)
                @php
                    $rutas = $jornada->grupos->flatMap->rutas;
                    $atendidas = $rutas->filter(fn($ruta) => $ruta->visita?->estado === 'finalizada')->count();
                    $total = $rutas->count();
                    $porcentaje = $total ? (int) round(($atendidas / $total) * 100) : 0;
                @endphp
                <tr>
                    <td><span class="va-table__title">{{ $jornada->nombre }}</span><span class="va-table__meta">{{ $jornada->slug }}</span></td>
                    <td data-label="Fecha / procesos"><strong>{{ $jornada->fecha->format('d-m-Y') }}</strong><span class="va-table__meta">{{ $jornada->procesos->pluck('codigo')->join(' + ') }}</span></td>
                    <td data-label="Estado"><span class="va-status va-status--{{ $jornada->estado }}">{{ str($jornada->estado)->replace('_', ' ') }}</span></td>
                    <td data-label="Grupos">{{ $jornada->grupos_count }}</td><td data-label="Establecimientos">{{ $total }}</td>
                    <td data-label="Avance"><div class="va-progress"><div class="va-progress__bar"><span style="width: {{ $porcentaje }}%"></span></div><div class="va-progress__meta"><span>{{ $atendidas }} / {{ $total }}</span><strong>{{ $porcentaje }}%</strong></div></div></td>
                    <td data-label="Publicación"><i class="bi {{ $jornada->publica ? 'bi-eye text-success' : 'bi-eye-slash text-muted' }}"></i> {{ $jornada->publica ? 'Pública' : 'Privada' }}</td>
                    <td data-label="Acciones"><div class="va-table__actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('votaciones.admin.jornadas.show', $jornada) }}">Administrar</a></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
        @endif
    </section>
    <div class="mt-3">{{ $jornadas->links() }}</div>
</div>
@endsection

@push('styles')
    @vite('resources/css/votaciones-admin.css')
@endpush
@push('scripts')
    @vite('resources/js/votaciones-admin.js')
@endpush
