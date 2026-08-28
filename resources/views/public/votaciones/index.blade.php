@extends('layouts.votaciones-public')
@section('content')
<section class="vp-hero vp-hero--index">
    <div class="vp-container vp-hero__grid">
        <div>
            <p class="vp-eyebrow"><span></span> Seguimiento territorial</p>
            <h1>Centro público de jornadas de votación</h1>
            <p class="vp-hero__lead">Consulta en un solo lugar el avance de los recorridos institucionales por los establecimientos del territorio.</p>
        </div>
        <div class="vp-hero__signal" aria-hidden="true"><span></span><i></i><b></b><strong>EN VIVO</strong></div>
    </div>
</section>
<section class="vp-container vp-section vp-section--index">
    <div class="vp-section-heading"><div><p class="vp-kicker">Jornadas disponibles</p><h2>Seguimiento en tiempo real</h2></div><p>Selecciona una jornada para revisar grupos, establecimientos, recorridos y estado de avance.</p></div>
    <div class="vp-grid-cards">
        @forelse($jornadas as $jornada)
            <a class="vp-card vp-journey-card" href="{{ route('public.votaciones.show',$jornada) }}">
                <div class="vp-journey-card__top"><span class="vp-badge vp-badge--{{ $jornada->estado }}"><i></i>{{ str($jornada->estado)->replace('_',' ')->title() }}</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M14 7l5 5-5 5"/></svg></div>
                <p class="vp-journey-card__date">{{ $jornada->fecha->translatedFormat('d \d\e F \d\e Y') }}</p>
                <h2>{{ $jornada->nombre }}</h2>
                <span class="vp-journey-card__action">Abrir centro de seguimiento</span>
            </a>
        @empty
            <div class="vp-empty vp-empty--large"><span aria-hidden="true">○</span><strong>No hay jornadas públicas disponibles</strong><p>Cuando exista una jornada publicada aparecerá en este espacio.</p></div>
        @endforelse
    </div>
</section>
@endsection
