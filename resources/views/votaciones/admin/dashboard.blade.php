@extends('layouts.app')

@section('content')
<div class="va-shell" data-votaciones-admin>
    <header class="va-topbar">
        <div><p class="va-topbar__eyebrow">Centro de control operativo</p><h1>Votaciones CCAF y Mutualidades</h1><p>Seguimiento ejecutivo de jornadas, grupos, rutas e incidencias.</p></div>
        <div class="va-topbar__actions"><a class="btn btn-outline-light" href="{{ route('votaciones.operacion.index') }}"><i class="bi bi-phone"></i> Operación móvil</a><a class="btn btn-light" href="{{ route('votaciones.admin.jornadas.create') }}"><i class="bi bi-plus-circle"></i> Nueva jornada</a></div>
    </header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')

    @if(!$jornada)
        <section class="va-card va-empty"><div><i class="bi bi-calendar2-plus"></i><h2>Aún no existen jornadas</h2><p>Crea la primera jornada para configurar procesos, grupos y recorridos.</p><a class="btn btn-primary" href="{{ route('votaciones.admin.jornadas.create') }}">Crear jornada</a></div></section>
    @else
        <form method="GET" class="va-filterbar" aria-label="Seleccionar jornada del dashboard">
            <label><span>Jornada visualizada</span><select class="form-select" name="jornada" data-auto-submit>@foreach($jornadas as $opcion)<option value="{{ $opcion->slug }}" @selected($opcion->is($jornada))>{{ $opcion->nombre }} · {{ $opcion->fecha->format('d-m-Y') }}</option>@endforeach</select></label>
            <div><span class="va-field-label">Estado</span><span class="va-status va-status--{{ $jornada->estado }}">{{ str($jornada->estado)->replace('_', ' ') }}</span></div>
            <div><span class="va-field-label">Última actualización</span><strong>{{ $jornada->updated_at->timezone(config('votaciones.timezone'))->format('d-m-Y H:i') }}</strong></div>
        </form>

        <section class="va-kpis" aria-label="Indicadores de la jornada">
            @foreach([
                ['total_grupos', 'bi-people', 'Total de grupos', ''], ['grupos_votando', 'bi-check2-circle', 'Grupos en votación', 'green'],
                ['grupos_traslado', 'bi-bus-front', 'Grupos en traslado', 'cyan'], ['grupos_finalizados', 'bi-flag', 'Grupos finalizados', 'green'],
                ['establecimientos_atendidos', 'bi-building-check', 'Establecimientos atendidos', 'green'], ['establecimientos_pendientes', 'bi-hourglass-split', 'Establecimientos pendientes', ''],
                ['incidencias_abiertas', 'bi-exclamation-triangle', 'Incidencias abiertas', 'orange'], ['rutas_con_problemas', 'bi-signpost-2', 'Rutas con problemas', 'red'],
            ] as [$key, $icon, $label, $tone])
                <article class="va-kpi {{ $tone ? 'va-kpi--'.$tone : '' }}"><div class="va-kpi__icon"><i class="bi {{ $icon }}"></i></div><div><strong>{{ $resumen[$key] }}</strong><span>{{ $label }}</span></div></article>
            @endforeach
        </section>

        <div class="va-dashboard-grid">
            <section class="va-card">
                <div class="va-card__header"><div><h2>Estado operacional por grupo</h2><p>Ubicación operativa y avance efectivo del recorrido.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('votaciones.admin.jornadas.show', $jornada) }}#grupos">Gestionar grupos</a></div>
                @if($grupos->isEmpty())
                    <div class="va-empty"><div><i class="bi bi-people"></i><h3>Sin grupos configurados</h3><p>La jornada todavía no tiene equipos ni rutas asignadas.</p></div></div>
                @else
                    <div class="table-responsive"><table class="table va-table va-table-responsive-cards"><thead><tr><th>Grupo</th><th>Estado</th><th>Actual</th><th>Próximo</th><th>Avance</th><th>Incidencias</th></tr></thead><tbody>
                    @foreach($grupos as $fila)
                        @php($grupo = $fila['modelo'])
                        <tr>
                            <td><span class="va-table__title">{{ $grupo->numero }}. {{ $grupo->nombre }}</span><span class="va-table__meta">{{ $grupo->encargado?->display_name ?? 'Sin responsable' }}</span></td>
                            <td data-label="Estado"><span class="va-status va-status--{{ $grupo->estado }}">{{ str($grupo->estado)->replace('_', ' ') }}</span></td>
                            <td data-label="Actual">{{ $fila['actual']?->establecimiento?->nombre_establecimiento ?? '—' }}</td>
                            <td data-label="Próximo">{{ $fila['proxima']?->establecimiento?->nombre_establecimiento ?? '—' }}</td>
                            <td data-label="Avance"><div class="va-progress"><div class="va-progress__bar"><span style="width: {{ $fila['porcentaje'] }}%"></span></div><div class="va-progress__meta"><span>{{ $fila['finalizadas'] }} / {{ $fila['total'] }}</span><strong>{{ $fila['porcentaje'] }}%</strong></div></div></td>
                            <td data-label="Incidencias">{{ $jornada->incidencias->where('grupo_votacion_id', $grupo->id)->count() }}</td>
                        </tr>
                    @endforeach
                    </tbody></table></div>
                @endif
            </section>
            <aside class="va-card"><div class="va-card__header"><div><h2>{{ $jornada->nombre }}</h2><p>{{ $jornada->fecha->format('d-m-Y') }} · {{ $jornada->procesos->pluck('codigo')->join(' + ') }}</p></div></div><div class="va-card__body"><div class="va-quick-actions">
                <a href="{{ route('votaciones.admin.jornadas.show', $jornada) }}"><i class="bi bi-sliders"></i> Gestionar jornada</a>
                <a href="{{ route('votaciones.admin.jornadas.show', $jornada) }}#grupos"><i class="bi bi-people"></i> Gestionar grupos</a>
                <a href="{{ route('votaciones.admin.jornadas.show', $jornada) }}#rutas"><i class="bi bi-signpost-split"></i> Gestionar rutas</a>
                <a href="{{ route('votaciones.admin.incidencias.index', ['jornada' => $jornada->slug]) }}"><i class="bi bi-exclamation-triangle"></i> Ver incidencias</a>
                <a href="{{ route('votaciones.admin.bitacora.index', ['jornada' => $jornada->slug]) }}"><i class="bi bi-clock-history"></i> Ver bitácora</a>
                @if($jornada->publica)<a href="{{ route('public.votaciones.show', $jornada) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Abrir vista pública</a>@endif
            </div></div></aside>
        </div>
    @endif
</div>
@endsection

@push('styles')
    @vite('resources/css/votaciones-admin.css')
@endpush
@push('scripts')
    @vite('resources/js/votaciones-admin.js')
@endpush
