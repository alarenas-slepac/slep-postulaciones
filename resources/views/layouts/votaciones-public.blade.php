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
<header class="vp-header"><div class="vp-container vp-header__inner"><a href="{{ route('public.votaciones.index') }}" class="vp-brand"><img src="{{ asset(config('brand.logo_slep', config('brand.logo_principal', 'branding/01_logo_principal.png'))) }}" alt="SLEP Andalién Costa"><span>Votaciones CCAF y Mutualidades</span></a><a href="https://slepandaliencosta.gob.cl/" target="_blank" rel="noopener">Sitio institucional</a></div></header>
<main id="contenido">@yield('content')</main>
<footer class="vp-footer"><div class="vp-container">SLEP Andalién Costa · Seguimiento informativo de recorridos</div></footer>
</body>
</html>
