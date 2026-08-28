@php($jornadaContextual = isset($jornada) && $jornada instanceof \App\Models\JornadaVotacion && $jornada->exists)
<nav class="va-nav" aria-label="Navegación del módulo de votaciones">
    <a href="{{ route('votaciones.admin.dashboard') }}" @class(['active' => request()->routeIs('votaciones.admin.dashboard')])><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="{{ route('votaciones.admin.jornadas.index') }}" @class(['active' => request()->routeIs('votaciones.admin.jornadas.*')])><i class="bi bi-calendar-event"></i> Jornadas</a>
    @if($jornadaContextual)
        <a href="{{ route('votaciones.admin.jornadas.show', $jornada) }}#grupos"><i class="bi bi-people"></i> Grupos</a>
        <a href="{{ route('votaciones.admin.jornadas.show', $jornada) }}#rutas"><i class="bi bi-signpost-split"></i> Rutas</a>
    @endif
    @can('votaciones.operate-group')
        <a href="{{ route('votaciones.operacion.index') }}" @class(['active' => request()->routeIs('votaciones.operacion.*')])><i class="bi bi-phone"></i> Operación</a>
    @endcan
    <a href="{{ route('votaciones.admin.incidencias.index', $jornadaContextual ? ['jornada' => $jornada->slug] : []) }}" @class(['active' => request()->routeIs('votaciones.admin.incidencias.*')])><i class="bi bi-exclamation-triangle"></i> Incidencias</a>
    <a href="{{ route('votaciones.admin.bitacora.index', $jornadaContextual ? ['jornada' => $jornada->slug] : []) }}" @class(['active' => request()->routeIs('votaciones.admin.bitacora.*')])><i class="bi bi-clock-history"></i> Bitácora</a>
    @can('votaciones.admin')
        <a href="{{ route('votaciones.admin.procesos.index') }}" @class(['active' => request()->routeIs('votaciones.admin.procesos.*')])><i class="bi bi-gear"></i> Configuración</a>
    @endcan
</nav>
