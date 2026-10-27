@extends('layouts.consulta-votacion')
@section('title', 'Resultados de votación')
@push('styles')
    @vite('resources/css/votaciones-resultados.css')
@endpush

@section('content')
<div class="vr-shell">
    <header class="cv-panel cv-page-header mb-4"><div><span class="cv-eyebrow"><i class="bi bi-bar-chart" aria-hidden="true"></i> CCAF · Consulta de Mutualidades</span><h1>Resultados de votación</h1><p class="mb-0">{{ $publicado ? 'Consolidado de todos los días incluidos en la versión publicada por la organización.' : 'Escrutinios generales publicados por la organización.' }}</p></div><a class="btn cv-secondary" href="{{ route('public.consulta-votacion.index', $jornada ? ['jornada' => $jornada->slug] : []) }}"><i class="bi bi-geo-alt" aria-hidden="true"></i> Consulta tu local</a></header>
    @if(!$jornada)
        <section class="cv-panel cv-empty"><i class="bi bi-hourglass-split" aria-hidden="true"></i><h2>Aún no hay resultados publicados</h2><p>Los resultados estarán disponibles después de su revisión y publicación por la organización.</p></section>
    @else
        <section class="cv-panel mb-4" aria-labelledby="jornada-titulo">
            <h2 id="jornada-titulo">{{ $jornada->nombre }}</h2>
            @if($jornadas->count() > 1)<form method="GET" action="{{ route('public.resultados-votacion.index') }}" class="row g-3 align-items-end mb-4"><div class="col-md-9"><label class="form-label" for="jornada">Jornada</label><select class="form-select" name="jornada" id="jornada">@foreach($jornadas as $item)<option value="{{ $item->slug }}" @selected($jornada->id === $item->id)>{{ $item->nombre }}</option>@endforeach</select></div><div class="col-md-3"><button class="btn cv-secondary w-100">Ver resultados</button></div></form>@endif
            <nav class="vr-day-nav" aria-label="Seleccionar resultados por día"><a class="btn {{ !$fecha ? 'cv-primary' : 'cv-secondary' }}" href="{{ route('public.resultados-votacion.index', ['jornada' => $jornada->slug]) }}" @if(!$fecha) aria-current="page" @endif>Acumulado</a>@foreach($dias as $dia)<a class="btn {{ $fecha === $dia->fecha->toDateString() ? 'cv-primary' : 'cv-secondary' }}" href="{{ route('public.resultados-votacion.index', ['jornada' => $jornada->slug, 'fecha' => $dia->fecha->toDateString()]) }}" @if($fecha === $dia->fecha->toDateString()) aria-current="page" @endif>{{ $dia->fecha->format('d-m-Y') }}</a>@endforeach</nav>
        </section>
        <div class="vr-section-heading mb-3"><div><span class="cv-eyebrow">{{ $publicado ? 'Consolidado publicado · versión '.$publicado->version : 'Resultados parciales' }}</span><h2>{{ $fecha ? 'Escrutinio del '.\Carbon\Carbon::parse($fecha)->format('d-m-Y') : ($publicado ? 'Resultado consolidado de todos los días' : 'Acumulado de días publicados') }}</h2><p class="text-secondary mb-0">{{ $dias->count() }} {{ $dias->count() === 1 ? 'día incluido' : 'días incluidos' }} · Publicación: {{ $dias->max('publicado_at')->timezone(config('votaciones.timezone'))->format('d-m-Y H:i') }}</p></div><span class="cv-badge"><i class="bi bi-check-circle" aria-hidden="true"></i> Datos publicados</span></div>
        @include('votaciones.partials.participacion', ['tituloParticipacion' => $fecha ? 'Participación del día respecto del padrón total' : 'Participación acumulada respecto del padrón total'])
        @include('votaciones.partials.resumen-papeletas', ['resumen' => $resumen, 'prefijo' => 'resultado'])
        <p class="vr-method mt-4"><i class="bi bi-info-circle" aria-hidden="true"></i> Los porcentajes se calculan sobre los votos válidos de cada papeleta. Nulos y blancos se muestran por separado. {{ $publicado ? 'Se muestran los datos de la versión publicada.' : 'El acumulado incluye únicamente los días publicados.' }} Cada papeleta se cuenta por separado.</p>
    @endif
</div>
@endsection
