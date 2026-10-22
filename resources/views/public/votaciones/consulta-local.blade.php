@extends('layouts.consulta-votacion')

@if($resultados !== null && $resultados->isNotEmpty())
    @push('map-assets')
        @vite('resources/js/consulta-votacion-mapa.js')
    @endpush
@endif

@section('content')
<div class="cv-consulta-grid">
    <section class="cv-intro" aria-labelledby="titulo-consulta">
        <span class="cv-eyebrow"><i class="bi bi-check2-square" aria-hidden="true"></i> Votaciones institucionales</span>
        <h1 id="titulo-consulta">Tu participación<br><span>comienza aquí.</span></h1>
        <p class="cv-lead">Consulta tu local de votación para la elección de caja de compensación y la consulta de mutualidad.</p>
        <div class="cv-steps" aria-label="Cómo consultar">
            <div><span class="cv-step-number">1</span><p><strong>Ingresa tu RUT</strong><small>Incluye el dígito verificador.</small></p></div>
            <div><span class="cv-step-number">2</span><p><strong>Revisa tu asignación</strong><small>Local, grupo, día y hora de inicio.</small></p></div>
            <div><span class="cv-step-number">3</span><p><strong>Acude al local indicado</strong><small>Puede ser distinto de donde trabajas.</small></p></div>
        </div>
    </section>
    <section class="cv-panel cv-search-panel" aria-labelledby="titulo-formulario">
        <div class="cv-panel-heading"><span class="cv-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span><div><h2 id="titulo-formulario">¿Dónde debo votar?</h2><p>Busca tu información en el padrón.</p></div></div>
        @if($errorConsulta)
            <div class="alert cv-alert-error" role="alert"><i class="bi bi-exclamation-circle me-2" aria-hidden="true"></i>{{ $errorConsulta }}</div>
        @endif
        @if($jornadas->isEmpty())
            <div class="cv-empty"><i class="bi bi-calendar2-event" aria-hidden="true"></i><h3>Consulta aún no disponible</h3><p>Las jornadas aparecerán aquí cuando se publique su padrón.</p></div>
        @else
            <form method="POST" action="{{ route('public.consulta-votacion.consultar') }}#resultado-consulta" autocomplete="off">
                @csrf
                @if($jornadas->count() === 1)
                    <input type="hidden" name="jornada" value="{{ $jornadas->first()->slug }}">
                    <div class="cv-context mb-4"><span class="cv-eyebrow">Jornada</span><strong>{{ $jornadas->first()->nombre }}</strong><small>{{ $jornadas->first()->procesos->pluck('nombre')->join(' · ') }}</small></div>
                @else
                    <div class="mb-4"><label class="form-label" for="jornada">Jornada de votación</label><select class="form-select" name="jornada" id="jornada" required><option value="">Selecciona una jornada</option>@foreach($jornadas as $j)<option value="{{ $j->slug }}" @selected($seleccionada === $j->slug)>{{ $j->nombre }} · {{ $j->procesos->pluck('codigo')->join(' / ') }}</option>@endforeach</select></div>
                @endif
                <div class="mb-4">
                    <label class="form-label" for="rut">Tu RUT</label>
                    <input class="form-control cv-rut-input {{ $errorConsulta ? 'is-invalid' : '' }}" id="rut" name="rut" type="text" maxlength="20" required autocomplete="off" spellcheck="false" autocapitalize="characters" aria-describedby="rut-ayuda" @if($errorConsulta) aria-invalid="true" @endif placeholder="Ingresa tu RUT con dígito verificador">
                    <div class="form-text" id="rut-ayuda">Puedes escribirlo con o sin puntos y guion.</div>
                </div>
                <button class="btn cv-primary w-100" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Consultar mi local</button>
                <p class="cv-privacy"><i class="bi bi-shield-check" aria-hidden="true"></i> La consulta muestra únicamente las asignaciones del RUT ingresado.</p>
            </form>
        @endif
    </section>
</div>
@if($resultados !== null)
    <section id="resultado-consulta" class="cv-results mt-4 mt-md-5" aria-labelledby="resultado-titulo" aria-live="polite" tabindex="-1">
        <div class="d-flex align-items-start gap-3 mb-3"><span class="cv-icon"><i class="bi bi-person-check" aria-hidden="true"></i></span><div><h2 id="resultado-titulo">Resultado de tu consulta</h2><p class="text-secondary mb-0">{{ $consultada->nombre }}</p></div></div>
        @forelse($resultados as $votante)
            @php($ubicacion = \App\Support\Votaciones\UbicacionLocalConsulta::para($votante->local))
            <article class="cv-panel cv-result-card mb-3">
                <div class="cv-result-top"><div><span class="cv-eyebrow">Funcionario/a</span><h3>{{ $votante->nombre }}</h3></div><span class="cv-badge"><i class="bi bi-check-circle" aria-hidden="true"></i> Registro en el padrón</span></div>
                <div class="cv-result-grid">
                    <div class="cv-destination"><span class="cv-eyebrow">Debes dirigirte a</span><div class="cv-establishment-heading">@include('public.votaciones.partials.establecimiento-logo', ['logo' => $votante->local->establecimientoDestino?->admisionPerfil?->logoUrl(), 'nombre' => $votante->local->lugar_votacion])<div><h4>{{ $votante->local->lugar_votacion }}</h4><p class="mb-0">RBD del local: <strong>{{ $votante->local->rbd_local }}</strong></p></div></div><span class="cv-group mt-2">Grupo: {{ $votante->grupo ?? 'Por confirmar' }}</span></div>
                    <dl class="cv-schedule mb-0"><div><dt><i class="bi bi-calendar3" aria-hidden="true"></i> Día de votación</dt><dd>{{ $votante->local->dia->translatedFormat('l d') }} de {{ $votante->local->dia->translatedFormat('F Y') }}</dd></div><div><dt><i class="bi bi-clock" aria-hidden="true"></i> Hora de inicio</dt><dd>{{ substr($votante->local->hora_inicio, 0, 5) }} <small>hrs.</small></dd></div></dl>
                </div>
                <section class="cv-location" aria-labelledby="ubicacion-titulo-{{ $loop->index }}">
                    <div class="cv-location-header">
                        <div><h4 id="ubicacion-titulo-{{ $loop->index }}"><i class="bi bi-map" aria-hidden="true"></i> Ubicación del local de votación</h4><p class="mb-0">{{ $votante->local->lugar_votacion }} · RBD {{ $votante->local->rbd_local }}</p></div>
                        @if($ubicacion['enlace'])<a class="btn cv-secondary" href="{{ $ubicacion['enlace'] }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-sign-turn-right" aria-hidden="true"></i> Cómo llegar</a>@endif
                    </div>
                    <div class="cv-location-address"><i class="bi bi-geo-alt" aria-hidden="true"></i><div><span>Dirección del local</span><strong>{{ $ubicacion['direccion'] ?? 'Dirección por confirmar' }}</strong>@if($ubicacion['comuna'])<small>{{ $ubicacion['comuna'] }}</small>@endif</div></div>
                    @if($ubicacion['validas'])
                        <div class="cv-local-map" data-cv-map data-latitude="{{ $ubicacion['latitud'] }}" data-longitude="{{ $ubicacion['longitud'] }}" data-name="{{ $votante->local->lugar_votacion }}" data-rbd="{{ $votante->local->rbd_local }}" role="region" aria-label="Mapa del local de votación {{ $votante->local->lugar_votacion }}"></div>
                        <p class="cv-map-notice" data-cv-map-error hidden role="status">No se pudo cargar el mapa completo. Puedes usar «Cómo llegar» para abrir la ubicación.</p>
                        <noscript><p class="cv-map-notice">Activa JavaScript para ver el mapa o usa «Cómo llegar» para abrir la ubicación.</p></noscript>
                    @else
                        <p class="cv-location-pending mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Ubicación en mapa por confirmar. Consulta con la organización antes de dirigirte al local.</p>
                    @endif
                </section>
                <div class="cv-work">@include('public.votaciones.partials.establecimiento-logo', ['logo' => $votante->establecimientoOrigen?->admisionPerfil?->logoUrl(), 'nombre' => $votante->establecimiento])<div><span>Establecimiento donde trabajas</span><strong>{{ $votante->establecimiento }}</strong><small>RBD {{ $votante->rbd }} · {{ $votante->tipo_contrato }}</small></div></div>
                @if(!$votante->grupo)<p class="cv-inline-note mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> El padrón aún no indica tu grupo. Confírmalo con la organización de la jornada.</p>@endif
            </article>
        @empty
            <div class="cv-panel cv-empty"><i class="bi bi-person-exclamation" aria-hidden="true"></i><h3>No encontramos una asignación</h3><p>Revisa el RUT y la jornada seleccionada. Si la información es correcta, consulta con la organización de la votación.</p></div>
        @endforelse
    </section>
@endif
@endsection
