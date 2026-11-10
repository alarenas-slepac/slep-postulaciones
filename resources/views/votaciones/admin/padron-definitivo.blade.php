@extends('layouts.app')
@push('styles')
    @vite(['resources/css/votaciones-admin.css', 'resources/css/consulta-votacion.css', 'resources/css/votaciones-resultados.css'])
@endpush
@section('content')
<div class="cv-shell vr-shell">
    <header class="cv-panel cv-page-header mb-4"><div><span class="cv-eyebrow">Votaciones · Revisión privada</span><h1>Revisar padrón definitivo</h1><p class="mb-0">{{ $jornada->nombre }} · Carga {{ $version->id }}</p></div><a class="btn cv-secondary" href="{{ route('votaciones.admin.padrones.index', ['jornada' => $jornada->slug]) }}">Volver a padrón y locales</a></header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')
    <section class="cv-panel mb-4" aria-labelledby="revision-definitivo-titulo">
        <span class="cv-badge cv-badge-muted">Vista previa privada</span>
        <h2 class="mt-3" id="revision-definitivo-titulo">Compara antes de publicar</h2>
        <p>Los datos siguientes utilizan los escrutinios ya publicados. Esta carga no cambia el padrón público, sus porcentajes ni el certificado vigente hasta que publiques el padrón definitivo.</p>
        <div class="vr-summary"><div><span>Padrón vigente</span><strong>{{ number_format($actual['padron'], 0, ',', '.') }}</strong><small>Personas únicas por RUT</small></div><div><span>Padrón definitivo preparado</span><strong>{{ number_format($version->personas, 0, ',', '.') }}</strong><small>{{ number_format($version->personas - $actual['padron'], 0, ',', '.') }} personas de diferencia</small></div><div><span>Participación {{ $fecha ? 'del día' : 'acumulada' }}</span><strong>{{ $actual['porcentaje'] === null ? '—' : number_format($actual['porcentaje'], 1, ',', '.').' %' }} → {{ $participacion['porcentaje'] === null ? '—' : number_format($participacion['porcentaje'], 1, ',', '.').' %' }}</strong><small>Vigente → con padrón definitivo</small></div></div>
        <nav class="vr-day-nav mt-4" aria-label="Vista previa por período"><a class="btn {{ !$fecha ? 'cv-primary' : 'cv-secondary' }}" href="{{ route('votaciones.admin.padrones.definitivo.revision', [$jornada, $version]) }}" @if(!$fecha) aria-current="page" @endif>Acumulado</a>@foreach($dias as $dia)<a class="btn {{ $fecha === $dia->fecha->toDateString() ? 'cv-primary' : 'cv-secondary' }}" href="{{ route('votaciones.admin.padrones.definitivo.revision', [$jornada, $version, 'fecha' => $dia->fecha->toDateString()]) }}" @if($fecha === $dia->fecha->toDateString()) aria-current="page" @endif>{{ $dia->fecha->format('d-m-Y') }}</a>@endforeach</nav>
    </section>
    @include('votaciones.partials.participacion', ['tituloParticipacion' => $fecha ? 'Vista previa de participación del día' : 'Vista previa de participación acumulada'])
    @if($resumen)
        @include('votaciones.partials.resumen-papeletas', ['prefijo' => 'revision-definitivo'])
        <p class="vr-method mt-3">El porcentaje del padrón distingue respaldo de participación. La distribución de preferencias por sí sola no certifica un acuerdo de afiliación o desafiliación.</p>
    @else
        <section class="cv-panel mb-4"><h2>Aún no hay escrutinios publicados</h2><p class="mb-0">Puedes revisar el padrón y el certificado. Los porcentajes se mostrarán cuando se publiquen resultados.</p></section>
    @endif
    <section class="cv-panel mt-4" aria-labelledby="publicar-definitivo-titulo">
        <h2 id="publicar-definitivo-titulo">Publicar padrón definitivo</h2>
        @if($publicable)
            <p>Esta acción reemplaza el padrón y los locales vigentes por esta carga, habilita la consulta por RUT y activa el mensaje e indicadores definitivos en los resultados públicos. Los escrutinios se conservan.</p>
            @if(!$padronDefinitivo['ver_certificado'])<p class="cv-inline-note">No se adjuntó certificado de dotación. Es opcional y puedes publicar sin él.</p>@endif
            <form method="POST" action="{{ route('votaciones.admin.padrones.definitivo.publicar', [$jornada, $version]) }}">@csrf<div class="form-check mb-3"><input class="form-check-input" id="confirmar-definitivo" type="checkbox" name="confirmar_definitivo" value="1" required><label class="form-check-label" for="confirmar-definitivo">Confirmo que las {{ number_format($version->personas, 0, ',', '.') }} personas de esta carga conforman el padrón definitivo habilitado para esta jornada.</label></div><div class="d-flex flex-wrap gap-2"><a class="btn cv-secondary" href="{{ route('votaciones.admin.padrones.index', ['jornada' => $jornada->slug]) }}">Volver sin publicar</a><button class="btn cv-primary" type="submit">Publicar padrón definitivo</button></div></form>
        @else
            <p class="cv-inline-note mb-0">Esta carga ya fue publicada o fue reemplazada por otra revisión. Vuelve a Padrón y locales para consultar la versión vigente.</p>
        @endif
    </section>
</div>
@endsection
