<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Votaciones CCAF y Mutualidades' }} · {{ config('brand.org_name', 'SLEP Andalién Costa') }}</title>
    <meta name="description" content="Estado público de los recorridos de votación por establecimientos.">
    <meta name="theme-color" content="#084682">
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}">
    @vite(['resources/css/votaciones-publicas.css','resources/js/votaciones-publicas.js'])
</head>
<body>
<a class="vp-skip" href="#contenido">Saltar al contenido</a>
<header class="vp-header">
    <div class="vp-container vp-header__inner">
        <a href="{{ route('public.votaciones.index') }}" class="vp-brand">
            <span class="vp-brand__logo"><img src="{{ asset(config('brand.logo_slep', config('brand.logo_principal', 'branding/01_logo_principal.png'))) }}" alt="SLEP Andalién Costa"></span>
            <span class="vp-brand__copy"><strong>Votaciones institucionales</strong><small>Seguimiento territorial público</small></span>
        </a>
        <a class="vp-institutional-link" href="{{ config('brand.org_url', 'https://slepandaliencosta.gob.cl/') }}" target="_blank" rel="noopener">
            <span>Sitio institucional</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 5h5v5M19 5l-9 9M18 13v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
        </a>
    </div>
</header>
<main id="contenido">@yield('content')</main>
<footer class="vp-footer">
    <div class="vp-container vp-footer__inner">
        <div class="vp-footer__brand"><img src="{{ asset(config('brand.logo_slep', 'branding/logo-andaliencosta.png')) }}" alt="SLEP Andalién Costa"><p>Centro público de seguimiento territorial para jornadas institucionales.</p></div>
        <div class="vp-footer__notice"><strong>Información pública y operativa</strong><p>Esta plataforma muestra el avance de los grupos y sus recorridos planificados. No realiza seguimiento GPS de funcionarios.</p></div>
    </div>
    <div class="vp-container vp-footer__bottom"><span>© {{ now()->year }} SLEP Andalién Costa</span><span>Servicio público, información clara y oportuna</span></div>
</footer>
</body>
</html>
