@extends('layouts.votaciones-public')
@section('content')
<section class="vp-hero"><div class="vp-container"><p class="vp-eyebrow">Seguimiento territorial</p><h1>Jornadas de votación</h1><p>Consulta el avance de los recorridos en los establecimientos del territorio.</p></div></section>
<section class="vp-container vp-section"><div class="vp-grid-cards">
@forelse($jornadas as $jornada)<a class="vp-card" href="{{ route('public.votaciones.show',$jornada) }}"><span class="vp-badge vp-badge--{{ $jornada->estado }}">{{ str($jornada->estado)->replace('_',' ')->title() }}</span><h2>{{ $jornada->nombre }}</h2><p>{{ $jornada->fecha->translatedFormat('d \d\e F \d\e Y') }}</p></a>@empty<div class="vp-empty">No hay jornadas públicas disponibles.</div>@endforelse
</div></section>
@endsection
