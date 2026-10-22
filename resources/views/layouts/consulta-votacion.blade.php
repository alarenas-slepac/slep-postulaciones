<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#0b3d91">
    <title>Consulta tu local de votación · {{ config('brand.org_name', 'SLEP Andalién Costa') }}</title>
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}">
    @vite(['resources/scss/app.scss', 'resources/css/consulta-votacion.css'])
</head>
<body class="cv-public">
<a class="cv-skip" href="#contenido">Saltar al contenido</a>
<header class="cv-masthead">
    <div class="container d-flex align-items-center justify-content-between gap-3 py-3">
        <a href="{{ route('public.consulta-votacion.index') }}" aria-label="Inicio de consulta de local de votación">
            <img class="cv-logo" src="{{ asset(config('brand.logo_slep', 'branding/01_logo_principal.png')) }}" alt="SLEP Andalién Costa">
        </a>
        <span class="cv-institution">Servicio Local de Educación Pública<br><strong>Andalién Costa</strong></span>
    </div>
</header>
<main id="contenido" class="container cv-shell py-4 py-md-5">@yield('content')</main>
<footer class="container cv-footer py-4">
    <span>© {{ now()->year }} {{ config('brand.org_name', 'SLEP Andalién Costa') }}</span>
    <span>Elección de caja de compensación y consulta de mutualidad</span>
</footer>
</body>
</html>
