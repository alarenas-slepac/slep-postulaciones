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
        $admisionWhatsAppNumber = '+56 9 2615 9707';
        $admisionWhatsAppUrl = 'https://wa.me/56926159707';
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
            <div class="ae-footer__whatsapp-contact">
                <a class="ae-footer__whatsapp" href="{{ $admisionWhatsAppUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Contactar por WhatsApp al {{ $admisionWhatsAppNumber }}">
                    <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M16.03 3.2A12.73 12.73 0 0 0 5.06 22.36L3.25 29l6.79-1.78A12.73 12.73 0 1 0 16.03 3.2Zm0 2.55a10.18 10.18 0 1 1-5.19 18.93l-.48-.29-4.03 1.06 1.08-3.93-.31-.5a10.18 10.18 0 0 1 8.93-15.27Zm-3.39 4.94c-.2-.46-.41-.47-.61-.48h-.52c-.18 0-.47.07-.72.34-.25.27-.95.93-.95 2.27s.98 2.63 1.11 2.81c.14.18 1.92 2.93 4.65 4.11.65.28 1.16.45 1.56.57.65.21 1.24.18 1.71.11.52-.08 1.61-.66 1.84-1.3.23-.64.23-1.19.16-1.3-.07-.11-.25-.18-.52-.32-.27-.14-1.61-.79-1.86-.88-.25-.09-.43-.14-.61.14-.18.27-.7.88-.86 1.06-.16.18-.32.2-.59.07-.27-.14-1.15-.42-2.19-1.35-.81-.72-1.35-1.61-1.51-1.88-.16-.27-.02-.42.12-.55.12-.12.27-.32.41-.47.14-.16.18-.27.27-.45.09-.18.05-.34-.02-.47-.07-.14-.6-1.48-.83-2.02Z"/>
                    </svg>
                    <span>WhatsApp {{ $admisionWhatsAppNumber }}</span>
                </a>
                <span class="ae-footer__contact-hours">Atención de lunes a viernes, de 08:00 a 17:00.<br><strong>Solo mensajes, no llamadas.</strong></span>
            </div>
            @if (config('admision.contacto_telefono'))
                <span>{{ config('admision.contacto_telefono') }}</span>
            @endif
        </div>
    </div>
    <div class="ae-container ae-footer__bottom">
        <span>© {{ now()->year }} SLEP AC Admisión Escolar. Todos los derechos reservados.</span>
        <span>Vitrina informativa de establecimientos</span>
    </div>
</footer>

@include('public.admision-escolar.partials.scripts')
@stack('scripts')
</body>
</html>
