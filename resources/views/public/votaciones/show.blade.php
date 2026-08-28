@extends('layouts.votaciones-public')
@section('content')
<div id="votaciones-publicas" data-state-url="{{ route('public.votaciones.estado',$jornada) }}" data-routing-url="{{ route('public.votaciones.ruta-vial',$jornada) }}" data-polling-ms="{{ config('votaciones.polling_ms',10000) }}">
    <section class="vp-hero vp-hero--tracking">
        <div class="vp-container vp-hero__grid">
            <div class="vp-hero__content">
                <p class="vp-eyebrow"><span></span> Votación CCAF y consulta de mutualidades</p>
                <h1 data-vp-title>{{ $jornada->nombre }}</h1>
                <p class="vp-hero__meta" data-vp-meta></p>
                <p class="vp-hero__lead" data-vp-description></p>
                <div class="vp-status-line"><span data-vp-status></span><span class="vp-live-update" data-vp-updated aria-live="polite"></span></div>
            </div>
            <div class="vp-hero__signal" aria-hidden="true"><span></span><i></i><b></b><strong>SEGUIMIENTO<br>EN VIVO</strong></div>
        </div>
    </section>

    <section class="vp-container vp-dashboard" data-vp-dashboard>
        <section class="vp-search-hub" aria-labelledby="vp-search-title">
            <div class="vp-search-hub__title"><span class="vp-search-hub__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/></svg></span><div><p class="vp-kicker">Consulta rápida</p><h2 id="vp-search-title">¿Dónde debo votar?</h2><p>Encuentra tu establecimiento y conoce el estado actual de su grupo.</p></div></div>
            <label class="vp-main-search"><span class="vp-sr-only">Buscar establecimiento, RBD o comuna</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6"/><path d="m16 16 4 4"/></svg><input type="search" data-vp-search placeholder="Buscar establecimiento, RBD o comuna..." autocomplete="off"><button type="button" data-vp-clear-search hidden aria-label="Limpiar búsqueda">×</button></label>
            <div class="vp-filter-row">
                <label><span>Comuna</span><select data-vp-commune><option value="">Todas las comunas</option></select></label>
                <label><span>Grupo</span><select data-vp-group><option value="">Todos los grupos</option></select></label>
                <label><span>Estado</span><select data-vp-state><option value="">Todos los estados</option><option value="pendiente">Pendiente</option><option value="en_traslado">En traslado</option><option value="en_votacion">Votación en curso</option><option value="finalizada">Finalizada</option></select></label>
                <button class="vp-reset-filters" type="button" data-vp-reset-filters><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2-5.3M4 4v5h5"/></svg>Restablecer</button>
            </div>
        </section>

        <section class="vp-search-results" data-vp-search-results hidden aria-live="polite"></section>
        <div class="vp-alert" data-vp-error hidden role="status"></div>
        <div class="vp-alert vp-alert--warning" data-vp-incidents hidden></div>

        <section class="vp-summary" data-vp-summary aria-label="Resumen ejecutivo de la jornada"></section>

        <section class="vp-groups-overview" aria-labelledby="vp-groups-title">
            <div class="vp-section-heading vp-section-heading--compact"><div><p class="vp-kicker">Despliegue territorial</p><h2 id="vp-groups-title">Estado de los grupos</h2></div><p>Selecciona un grupo para ver su recorrido y enfocarlo en el mapa.</p></div>
            <div class="vp-group-cards" data-vp-group-cards></div>
        </section>

        <section class="vp-monitor-grid" aria-label="Mapa y recorrido seleccionado">
            <div class="vp-map-shell">
                <header class="vp-map-shell__header"><div><p class="vp-kicker">Mapa territorial</p><h2>Recorridos de la jornada</h2></div><div class="vp-map-shell__status"><span class="vp-live-dot"></span><span data-vp-map-updated>Información actualizada</span></div></header>
                <div class="vp-map-frame">
                    <div id="vp-map" aria-label="Mapa de recorridos planificados por establecimiento"></div>
                    <div class="vp-map-loading" data-vp-map-loading><span></span><p>Preparando mapa territorial...</p></div>
                    <div class="vp-map-legend" aria-label="Leyenda del mapa"><strong>Leyenda</strong><span><i class="is-pending">○</i>Pendiente</span><span><i class="is-transit">→</i>En traslado</span><span><i class="is-voting">●</i>Votación en curso</span><span><i class="is-finished">✓</i>Finalizado</span><span><i class="is-incident">!</i>Incidencia</span></div>
                </div>
                <div class="vp-map-shell__footer"><span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/></svg>Recorridos planificados, no ubicación GPS personal</span><span>Leaflet · OpenStreetMap</span></div>
                <div class="vp-distance-summary" data-vp-distance-summary aria-live="polite"><span class="vp-skeleton">Calculando distancias por carretera...</span></div>
            </div>

            <aside class="vp-route-panel" data-vp-route-panel aria-live="polite"><div class="vp-panel-skeleton"><span></span><span></span><span></span></div></aside>
        </section>

        <section class="vp-information-band"><span aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/></svg></span><div><strong>¿Cómo interpretar esta información?</strong><p>El mapa refleja el itinerario previamente configurado y el avance informado por cada grupo. Los tiempos y distancias son estimaciones referenciales y no representan seguimiento GPS en tiempo real.</p></div></section>
    </section>
    <script type="application/json" id="votaciones-estado-inicial">{!! json_encode($estadoInicial, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
</div>
@endsection
