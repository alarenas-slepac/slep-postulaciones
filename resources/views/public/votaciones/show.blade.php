@extends('layouts.votaciones-public')
@section('content')
<div id="votaciones-publicas" data-state-url="{{ route('public.votaciones.estado',$jornada) }}" data-routing-url="{{ route('public.votaciones.ruta-vial',$jornada) }}" data-polling-ms="{{ config('votaciones.polling_ms',10000) }}">
<section class="vp-hero"><div class="vp-container"><p class="vp-eyebrow">Estado de la jornada</p><h1 data-vp-title>{{ $jornada->nombre }}</h1><p data-vp-meta></p><p data-vp-description></p><div class="vp-status-line"><span data-vp-status></span><span data-vp-updated aria-live="polite"></span></div></div></section>
<section class="vp-container vp-section">
<div class="vp-summary" data-vp-summary></div>
<div class="vp-toolbar"><label>Comuna<select data-vp-commune><option value="">Todas</option></select></label><label>Grupo<select data-vp-group><option value="">Todos</option></select></label><label>Estado<select data-vp-state><option value="">Todos</option><option value="pendiente">Pendiente</option><option value="en_traslado">En traslado</option><option value="en_votacion">En votación</option><option value="finalizada">Finalizada</option></select></label><label class="vp-search">Buscar establecimiento<input type="search" data-vp-search placeholder="Nombre, RBD o comuna"></label></div>
<section class="vp-search-results" data-vp-search-results hidden aria-live="polite"></section>
<div class="vp-alert" data-vp-error hidden role="status"></div><div class="vp-alert vp-alert--warning" data-vp-incidents hidden></div>
<div class="vp-distance-summary" data-vp-distance-summary aria-live="polite"><span>Calculando distancias por carretera...</span></div>
<div class="vp-layout"><div id="vp-map" aria-label="Mapa de recorridos"></div><aside><h2>Recorridos</h2><div data-vp-routes></div></aside></div>
</section>
<script type="application/json" id="votaciones-estado-inicial">{!! json_encode($estadoInicial, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}</script>
</div>
@endsection
