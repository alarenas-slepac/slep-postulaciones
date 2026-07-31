<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $admisionPageTitle = $title ?? (config('admision.titulo', 'Admisión Escolar') . ' ' . config('admision.anio', 2027));
        $admisionDescription = $metaDescription ?? config('admision.descripcion');
        $admisionLogo = asset(config('brand.logo_slep', config('brand.logo_principal', 'branding/01_logo_principal.png')));
        $admisionLogoFallback = asset(config('brand.logo_principal', 'branding/01_logo_principal.png'));
        $admisionInstitutionalUrl = 'https://slepandaliencosta.gob.cl/';
        $admisionCommunicationsEmail = 'comunicaciones@slepandaliencosta.gob.cl';
    @endphp
    <title>{{ $admisionPageTitle }} · {{ config('brand.org_name', 'SLEP Andalién Costa') }}</title>
    <meta name="description" content="{{ $admisionDescription }}">
    <meta name="theme-color" content="#084682">
    @if (! empty($isPreview))
        <meta name="robots" content="noindex,nofollow,noarchive">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('brand.org_name', 'SLEP Andalién Costa') }}">
    <meta property="og:title" content="{{ $admisionPageTitle }}">
    <meta property="og:description" content="{{ $admisionDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $ogImage ?? $admisionLogo }}">
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}">
    @include('public.admision-escolar.partials.styles')
    @stack('head')
</head>
<body>
<a class="ae-skip-link" href="#contenido-principal">Saltar al contenido principal</a>

<header class="ae-header" data-ae-header>
    <div class="ae-container ae-header__inner">
        <a class="ae-brand" href="{{ route('public.admision-escolar.index') }}" aria-label="Inicio de Admisión Escolar">
            <img src="{{ $admisionLogo }}" alt="SLEP Andalién Costa" onerror="this.onerror=null;this.src='{{ $admisionLogoFallback }}'">
            <span>
                <strong>Admisión Escolar</strong>
            </span>
        </a>

        <button class="ae-nav-toggle" type="button" aria-expanded="false" aria-controls="ae-main-nav" data-ae-nav-toggle>
            <span class="ae-sr-only">Abrir menú</span>
            <span></span><span></span><span></span>
        </button>

        <nav class="ae-nav" id="ae-main-nav" aria-label="Navegación principal" data-ae-nav>
            <a href="{{ route('public.admision-escolar.index') }}">Establecimientos</a>
            <a href="{{ route('public.admision-escolar.index') }}#como-explorar">Cómo explorar</a>
            <a href="{{ $admisionInstitutionalUrl }}" target="_blank" rel="noopener noreferrer">Sitio institucional</a>
            <a class="ae-button ae-button--primary ae-button--small" href="{{ config('admision.sae_url') }}" target="_blank" rel="noopener noreferrer">Ir al SAE <span aria-hidden="true">↗</span></a>
        </nav>
    </div>
</header>

@if (! empty($isPreview))
    <div class="ae-preview-banner" role="status">
        <div class="ae-container">
            <strong>Previsualización privada.</strong> Esta ficha puede contener información aún no publicada.
            <a href="{{ route('admin.admision-escolar.edit', $establecimiento) }}">Volver a editar</a>
        </div>
    </div>
@endif

<main id="contenido-principal">
    @yield('content')
</main>

<footer class="ae-footer">
    <div class="ae-container ae-footer__grid">
        <div class="ae-footer__brand">
            <img src="{{ $admisionLogo }}" alt="SLEP Andalién Costa" onerror="this.onerror=null;this.src='{{ $admisionLogoFallback }}'">
            <p>Educación pública para Coronel, Lota, San Pedro de la Paz y Santa Juana.</p>
        </div>
        <div>
            <h2>Admisión Escolar</h2>
            <a href="{{ route('public.admision-escolar.index') }}">Explorar establecimientos</a>
            <a href="{{ config('admision.sae_url') }}" target="_blank" rel="noopener noreferrer">Sistema de Admisión Escolar</a>
        </div>
        <div>
            <h2>Enlaces útiles</h2>
            <a href="https://www.mineduc.cl/" target="_blank" rel="noopener noreferrer">Ministerio de Educación</a>
            <a href="{{ $admisionInstitutionalUrl }}" target="_blank" rel="noopener noreferrer">SLEP Andalién Costa</a>
        </div>
        <div>
            <h2>Contacto</h2>
            <a href="mailto:{{ $admisionCommunicationsEmail }}">{{ $admisionCommunicationsEmail }}</a>
            @if (config('admision.contacto_email') && strcasecmp(config('admision.contacto_email'), $admisionCommunicationsEmail) !== 0)
                <a href="mailto:{{ config('admision.contacto_email') }}">{{ config('admision.contacto_email') }}</a>
            @endif
            @if (config('admision.contacto_telefono'))
                <span>{{ config('admision.contacto_telefono') }}</span>
            @endif
        </div>
    </div>
    <div class="ae-container ae-footer__bottom">
        <span>© {{ now()->year }} {{ config('brand.org_name', 'SLEP Andalién Costa') }}</span>
        <span>Vitrina informativa de establecimientos</span>
    </div>
</footer>

@include('public.admision-escolar.partials.scripts')
@stack('scripts')
</body>
</html>
