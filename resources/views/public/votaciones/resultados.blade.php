@extends('layouts.consulta-votacion')
@section('title', 'Resultados de votación')
@push('styles')
    @vite('resources/css/votaciones-resultados.css')
@endpush

@section('content')
<div class="vr-shell">
    <header class="cv-panel cv-page-header mb-4"><div><span class="cv-eyebrow"><i class="bi bi-bar-chart" aria-hidden="true"></i> CCAF · Consulta de Mutualidades</span><h1>Resultados de votación</h1><p class="mb-0">Escrutinios generales publicados por la organización al cierre de cada día.</p></div><a class="btn cv-secondary" href="{{ route('public.consulta-votacion.index', $jornada ? ['jornada' => $jornada->slug] : []) }}"><i class="bi bi-geo-alt" aria-hidden="true"></i> Consulta tu local</a></header>
    @if(!$jornada)
        <section class="cv-panel cv-empty"><i class="bi bi-hourglass-split" aria-hidden="true"></i><h2>Aún no hay resultados publicados</h2><p>Los resultados estarán disponibles después de su revisión y publicación por la organización.</p></section>
    @else
        <section class="cv-panel mb-4" aria-labelledby="jornada-titulo">
            <h2 id="jornada-titulo">{{ $jornada->nombre }}</h2>
            @if($jornadas->count() > 1)<form method="GET" action="{{ route('public.resultados-votacion.index') }}" class="row g-3 align-items-end mb-4"><div class="col-md-9"><label class="form-label" for="jornada">Jornada</label><select class="form-select" name="jornada" id="jornada">@foreach($jornadas as $item)<option value="{{ $item->slug }}" @selected($jornada->id === $item->id)>{{ $item->nombre }}</option>@endforeach</select></div><div class="col-md-3"><button class="btn cv-secondary w-100">Ver resultados</button></div></form>@endif
            <nav class="vr-day-nav" aria-label="Seleccionar resultados por día"><a class="btn {{ !$fecha ? 'cv-primary' : 'cv-secondary' }}" href="{{ route('public.resultados-votacion.index', ['jornada' => $jornada->slug]) }}" @if(!$fecha) aria-current="page" @endif>Acumulado</a>@foreach($dias as $dia)<a class="btn {{ $fecha === $dia->fecha->toDateString() ? 'cv-primary' : 'cv-secondary' }}" href="{{ route('public.resultados-votacion.index', ['jornada' => $jornada->slug, 'fecha' => $dia->fecha->toDateString()]) }}" @if($fecha === $dia->fecha->toDateString()) aria-current="page" @endif>{{ $dia->fecha->format('d-m-Y') }}</a>@endforeach</nav>
        </section>
        <div class="vr-section-heading mb-3"><div><span class="cv-eyebrow">Resultados parciales</span><h2>{{ $fecha ? 'Escrutinio del '.\Carbon\Carbon::parse($fecha)->format('d-m-Y') : 'Acumulado de días publicados' }}</h2><p class="text-secondary mb-0">{{ $dias->count() }} {{ $dias->count() === 1 ? 'día publicado' : 'días publicados' }} · Última publicación: {{ $dias->max('publicado_at')->timezone(config('votaciones.timezone'))->format('d-m-Y H:i') }}</p></div><span class="cv-badge"><i class="bi bi-check-circle" aria-hidden="true"></i> Datos publicados</span></div>
        <div class="vr-ballots">
            @foreach($resumen as $clave => $papeleta)
                <section class="cv-panel vr-ballot" aria-labelledby="resultado-{{ $clave }}">
                    <div class="vr-ballot-heading"><span class="cv-icon" aria-hidden="true"><i class="bi bi-ui-checks"></i></span><span class="cv-eyebrow mb-0">Papeleta {{ $loop->iteration }}</span></div>
                    <h3 id="resultado-{{ $clave }}">{{ $papeleta['titulo'] }}</h3><p class="text-secondary vr-question">{{ $papeleta['pregunta'] }}</p>
                    @foreach($papeleta['opciones'] as $opcion => $label)
                        @php($cantidad = $papeleta['conteos'][$opcion])
                        <div class="vr-option"><div><span id="opcion-{{ $clave }}-{{ $opcion }}">{{ $label }}</span><strong>{{ number_format($cantidad, 0, ',', '.') }} <small>{{ number_format($papeleta['validos'] ? $cantidad / $papeleta['validos'] * 100 : 0, 1, ',', '.') }} %</small></strong></div><progress class="vr-bar" value="{{ $cantidad }}" max="{{ max(1, $papeleta['validos']) }}" aria-labelledby="opcion-{{ $clave }}-{{ $opcion }}">{{ $cantidad }}</progress></div>
                    @endforeach
                    <dl class="vr-totals mt-auto mb-0"><div><dt>Votos válidos</dt><dd>{{ number_format($papeleta['validos'], 0, ',', '.') }}</dd></div><div><dt>Nulos</dt><dd>{{ number_format($papeleta['conteos']['nulos'], 0, ',', '.') }}</dd></div><div><dt>Blancos</dt><dd>{{ number_format($papeleta['conteos']['blancos'], 0, ',', '.') }}</dd></div><div><dt>Total de papeletas</dt><dd>{{ number_format($papeleta['emitidos'], 0, ',', '.') }}</dd></div></dl>
                </section>
            @endforeach
        </div>
        <p class="vr-method mt-4"><i class="bi bi-info-circle" aria-hidden="true"></i> Los porcentajes se calculan sobre los votos válidos de cada papeleta. Nulos y blancos se muestran por separado. El acumulado incluye únicamente los días publicados. Cada papeleta se cuenta por separado.</p>
    @endif
</div>
@endsection
