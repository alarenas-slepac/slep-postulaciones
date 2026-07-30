<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title ?? config('brand.platform_name', config('app.name')) }}</title>
    <meta name="application-name" content="{{ config('brand.platform_name', config('app.name')) }}">
    <meta name="theme-color" content="#0b3d91">
    <meta name="msapplication-TileColor" content="#0b3d91">
    <meta name="apple-mobile-web-app-title" content="{{ config('brand.platform_name', config('app.name')) }}">

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('branding/favicon-16x16.png') }}">
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}">
    <link rel="manifest" href="{{ asset('branding/site.webmanifest') }}">
    <link rel="mask-icon" href="{{ asset('branding/favicon.svg') }}" color="#0b3d91">
    <meta property="og:site_name" content="{{ config('brand.platform_name', config('app.name')) }}">
    <meta property="og:title" content="{{ $title ?? config('brand.platform_name', config('app.name')) }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('branding/android-chrome-512x512.png') }}">

    @vite(['resources/js/app.js', 'resources/scss/app.scss'])
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/font/bootstrap-icons.css') }}">
    <link rel="preload" as="font" href="{{ Vite::asset('resources/fonts/MuseoSans_900.otf') }}" type="font/otf" crossorigin>
    <link rel="preload" as="font" href="{{ Vite::asset('resources/fonts/Gilmer-Regular.otf') }}" type="font/otf" crossorigin>
    <link rel="preload" as="font" href="{{ Vite::asset('resources/fonts/Gilmer-Bold.otf') }}" type="font/otf" crossorigin>
    <link rel="preload" as="font" href="{{ Vite::asset('resources/fonts/gobCL_Heavy.otf') }}" type="font/otf" crossorigin>

    <style>
        :root {
            --slep-blue: #0d6efd;
            --slep-blue-dark: #0b3d91;
            --slep-bg: #f5f7fb;
            --slep-border: #dbe4f0;
            --slep-text: #0f172a;
            --slep-muted: #64748b;
            --slep-sidebar: 292px;
            --slep-topbar: 72px;
            --slep-radius: 18px;
        }

        body { background: var(--slep-bg); color: var(--slep-text); }
        .text-success, .link-success { color: #146c43 !important; }
        .text-warning, .link-warning, .text-warning-emphasis { color: #7a5d00 !important; }
        .text-info, .link-info, .text-info-emphasis { color: #055160 !important; }
        .btn-warning { --bs-btn-color:#1f2937; --bs-btn-bg:#d9b300; --bs-btn-border-color:#d9b300; --bs-btn-hover-bg:#c29f00; --bs-btn-hover-border-color:#c29f00; }
        .btn-outline-success { --bs-btn-color:#0f5132; --bs-btn-border-color:#0f5132; --bs-btn-hover-color:#fff; --bs-btn-hover-bg:#0f5132; --bs-btn-hover-border-color:#0f5132; --bs-btn-active-color:#fff; --bs-btn-active-bg:#0a3622; --bs-btn-active-border-color:#0a3622; --bs-btn-disabled-color:#0f5132; --bs-btn-disabled-border-color:#0f5132; }
        .btn-outline-success-dark { --bs-btn-color:#0b3d25; --bs-btn-border-color:#0b3d25; --bs-btn-hover-color:#fff; --bs-btn-hover-bg:#0b3d25; --bs-btn-hover-border-color:#0b3d25; --bs-btn-active-color:#fff; --bs-btn-active-bg:#072c1b; --bs-btn-active-border-color:#072c1b; --bs-btn-disabled-color:#0b3d25; --bs-btn-disabled-border-color:#0b3d25; color:#0b3d25 !important; border:1px solid #0b3d25 !important; background-color:transparent; }
        .btn-outline-success-dark:hover, .btn-outline-success-dark:focus, .btn-outline-success-dark:active, .btn-outline-success-dark.active { color:#fff !important; background-color:#0b3d25 !important; border-color:#0b3d25 !important; }
        .btn-outline-warning { --bs-btn-color:#7a5d00; --bs-btn-border-color:#7a5d00; --bs-btn-hover-color:#fff; --bs-btn-hover-bg:#7a5d00; --bs-btn-hover-border-color:#7a5d00; --bs-btn-active-color:#fff; --bs-btn-active-bg:#614900; --bs-btn-active-border-color:#614900; --bs-btn-disabled-color:#7a5d00; --bs-btn-disabled-border-color:#7a5d00; }
        .btn-outline-info { --bs-btn-color:#055160; --bs-btn-border-color:#055160; --bs-btn-hover-color:#fff; --bs-btn-hover-bg:#055160; --bs-btn-hover-border-color:#055160; --bs-btn-active-color:#fff; --bs-btn-active-bg:#043f4a; --bs-btn-active-border-color:#043f4a; --bs-btn-disabled-color:#055160; --bs-btn-disabled-border-color:#055160; }
        .btn-info { --bs-btn-color:#fff; --bs-btn-bg:#0b8faa; --bs-btn-border-color:#0b8faa; --bs-btn-hover-bg:#087990; --bs-btn-hover-border-color:#087990; }
        .btn-outline-primary { --bs-btn-color:#0b3d91; --bs-btn-border-color:#0b3d91; --bs-btn-hover-color:#fff; --bs-btn-hover-bg:#0b3d91; --bs-btn-hover-border-color:#0b3d91; --bs-btn-active-color:#fff; --bs-btn-active-bg:#082f70; --bs-btn-active-border-color:#082f70; }
        .btn-outline-danger { --bs-btn-color:#8a1c1c; --bs-btn-border-color:#8a1c1c; --bs-btn-hover-color:#fff; --bs-btn-hover-bg:#8a1c1c; --bs-btn-hover-border-color:#8a1c1c; --bs-btn-active-color:#fff; --bs-btn-active-bg:#661515; --bs-btn-active-border-color:#661515; }
        .badge.text-bg-success, .badge.bg-success { background-color:#0f5132 !important; color:#fff !important; }
        .badge.text-bg-primary, .badge.bg-primary { background-color:#0b3d91 !important; color:#fff !important; }
        .badge.text-bg-danger, .badge.bg-danger { background-color:#8a1c1c !important; color:#fff !important; }
        .badge.text-bg-warning, .badge.bg-warning { background-color:#d9b300 !important; color:#1f2937 !important; }
        .badge.text-bg-info, .badge.bg-info { background-color:#0b8faa !important; color:#fff !important; }

        .slep-shell { min-height: 100vh; }
        .slep-sidebar {
            position: fixed; inset: 0 auto 0 0; width: var(--slep-sidebar); z-index: 1040;
            background: #fff; border-right: 1px solid var(--slep-border); display: flex; flex-direction: column;
            box-shadow: 12px 0 30px rgba(15,23,42,.04);
            transition: transform .22s ease;
        }
        .slep-brand { height: var(--slep-topbar); padding: .82rem 1.1rem; display: flex; align-items: center; gap: .8rem; background: linear-gradient(135deg,#0b3d91,#0d6efd); color:#fff; }
        .slep-brand-logo { width: 66px; height: 50px; flex: 0 0 66px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; background: #ffffff; border: 1px solid rgba(255,255,255,.72); box-shadow: 0 10px 24px rgba(0,0,0,.16); padding: .34rem; overflow: hidden; }
        .slep-brand-logo img { width: 100%; height: 100%; object-fit: contain; display: block; filter: none !important; }
        .slep-brand-logo-fallback { width: 100%; height: 100%; border-radius: 8px; display:inline-flex; align-items:center; justify-content:center; background:#fff; color:#0b3d91; font-size:.62rem; font-weight:900; letter-spacing:.03em; }
        .slep-brand-title { font-size: .9rem; line-height: 1.08; font-weight: 800; color:#ffffff; }
        .slep-brand-subtitle { font-size: .7rem; line-height:1.1; opacity: .94; color:#ffffff; }
        .slep-nav-scroll { flex: 1; overflow-y: auto; padding: 1rem .9rem; }
        .slep-menu-group { margin-bottom: 1.15rem; }
        .slep-menu-title { color: var(--slep-muted); font-size: .73rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; padding: .25rem .75rem; margin-bottom: .3rem; }
        .slep-menu-link { display: flex; align-items: center; gap: .75rem; padding: .72rem .82rem; border-radius: 13px; color: #334155; text-decoration: none; font-weight: 700; margin-bottom: .22rem; transition: .18s ease; }
        .slep-menu-link:hover { background: #f1f6ff; color: #0d47a1; }
        .slep-menu-link.active { background: #eaf2ff; color: #0d47a1; box-shadow: inset 3px 0 0 #0d6efd; }
        .slep-menu-link i { width: 1.35rem; text-align: center; font-size: 1.05rem; }
        .slep-sidebar-help { margin: .85rem; border: 1px solid #cfe0ff; border-radius: 18px; background: #eff6ff; padding: 1rem; color: #1e3a8a; }
        .slep-help-icon { width: 2rem; height: 2rem; border-radius: 999px; display:inline-flex; align-items:center; justify-content:center; background:#0d6efd; color:#fff; margin-bottom:.5rem; }
        .slep-main { padding-left: var(--slep-sidebar); min-height: 100vh; transition: padding-left .22s ease; }
        .slep-topbar { height: var(--slep-topbar); position: sticky; top: 0; z-index: 1030; background: rgba(255,255,255,.92); backdrop-filter: blur(12px); border-bottom: 1px solid var(--slep-border); display: flex; align-items: center; gap: 1rem; padding: 0 1.5rem; }
        .slep-sidebar-toggle { border: 1px solid var(--slep-border); background: #fff; color:#334155; width: 42px; height: 42px; border-radius: 13px; display: inline-flex; align-items: center; justify-content: center; transition: .18s ease; }
        .slep-sidebar-toggle:hover { background:#eff6ff; color:#0d47a1; border-color:#cfe0ff; }
        .slep-global-search { flex: 1; max-width: 640px; margin: 0 auto; position: relative; }
        .slep-global-search > i { position: absolute; left: 1rem; top: 22px; transform: translateY(-50%); color: var(--slep-muted); z-index:2; }
        .slep-global-search input { height: 44px; border-radius: 14px; border: 1px solid var(--slep-border); padding-left: 2.75rem; background:#fff; }
        .slep-global-search-results { position:absolute; z-index:1080; left:0; right:0; top: calc(100% + .55rem); background:#fff; border:1px solid var(--slep-border); border-radius:18px; box-shadow:0 18px 46px rgba(15,23,42,.14); overflow:hidden; display:none; }
        .slep-global-search-results.is-open { display:block; }
        .slep-global-search-header { padding:.7rem .95rem; font-size:.74rem; font-weight:900; letter-spacing:.08em; color:#64748b; text-transform:uppercase; border-bottom:1px solid #edf2f7; background:#f8fafc; }
        .slep-global-search-list { max-height:380px; overflow:auto; }
        .slep-global-search-item { display:flex; gap:.75rem; align-items:flex-start; padding:.85rem .95rem; text-decoration:none; color:#0f172a; border-bottom:1px solid #f1f5f9; }
        .slep-global-search-item:hover, .slep-global-search-item:focus { background:#f8fafc; color:#0d6efd; outline:none; }
        .slep-global-search-icon { width:2.35rem; height:2.35rem; border-radius:12px; background:#eef4ff; color:#0d6efd; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; }
        .slep-global-search-title { font-weight:850; line-height:1.2; }
        .slep-global-search-subtitle { color:#64748b; font-size:.82rem; margin-top:.15rem; }
        .slep-global-search-badge { font-size:.7rem; font-weight:850; color:#475569; text-transform:uppercase; letter-spacing:.04em; }
        .slep-global-search-empty { padding:1rem; color:#64748b; font-size:.9rem; text-align:center; }
        .slep-top-actions { display: flex; align-items: center; gap: .7rem; }
        .slep-icon-btn { width: 42px; height: 42px; border-radius: 13px; display:inline-flex; align-items:center; justify-content:center; border: 1px solid var(--slep-border); background:#fff; color:#334155; text-decoration:none; position:relative; transition: all .18s ease; }
        .slep-icon-btn:hover { color:#0b3d91; border-color:#bfd6f6; background:#f8fbff; box-shadow: 0 .4rem .9rem rgba(13,110,253,.08); }
        .slep-message-shortcut { color:#1d4ed8; background:#eff6ff; border-color:#cfe0ff; }
        .slep-message-shortcut:hover { color:#0b3d91; border-color:#b7d3ff; background:#e7f0ff; }
        .slep-message-shortcut.has-unread { box-shadow: 0 .5rem 1rem rgba(13,110,253,.15); }
        .slep-notification-count { position:absolute; top:-.32rem; right:-.35rem; min-width:1.3rem; height:1.3rem; padding:0 .26rem; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:#dc2626; color:#fff; border:2px solid #fff; font-size:.68rem; font-weight:900; line-height:1; box-shadow: 0 .25rem .75rem rgba(220,38,38,.28); }
        .slep-notification-count.is-hidden { display:none !important; }
        .slep-notification-dot { position:absolute; top: .25rem; right:.25rem; width: .65rem; height:.65rem; border-radius:999px; background:#dc2626; border:2px solid #fff; }
        .slep-role-chip { display:inline-flex; align-items:center; gap:.45rem; border:1px solid #cfe0ff; background:#eff6ff; color:#1d4ed8; border-radius:999px; padding:.55rem .8rem; font-weight:800; font-size:.82rem; white-space:nowrap; }
        .slep-user-menu { display:flex; align-items:center; gap:.65rem; border: 1px solid var(--slep-border); border-radius: 16px; background:#fff; padding:.4rem .6rem; color: var(--slep-text); text-decoration:none; }
        .slep-avatar { width:36px; height:36px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#0b3d91,#0d6efd); color:#fff; font-weight:800; font-size:.82rem; }
        .slep-user-name { font-weight:800; line-height:1.1; font-size:.9rem; }
        .slep-user-role { color: var(--slep-muted); font-size:.76rem; line-height:1.1; }
        .slep-content { padding: 1.5rem; }
        .slep-content-inner { max-width: 1560px; margin: 0 auto; }
        .slep-card { background:#fff; border:1px solid var(--slep-border); border-radius: var(--slep-radius); box-shadow: 0 14px 34px rgba(15,23,42,.055); }
        .slep-overlay { display:none; position: fixed; inset: 0; background: rgba(15,23,42,.35); z-index: 1035; }

        .slep-role-primary { background:#eff6ff; color:#1d4ed8; border-color:#cfe0ff; }
        .slep-role-success { background:#ecfdf3; color:#0f5132; border-color:#bcebd0; }
        .slep-role-info { background:#e6f7fb; color:#055160; border-color:#b8e6ef; }
        .slep-role-warning { background:#fff8e1; color:#8a4b00; border-color:#f5d58b; }
        .slep-role-purple { background:#f3e8ff; color:#6b21a8; border-color:#e9d5ff; }
        .slep-role-teal { background:#ccfbf1; color:#0f766e; border-color:#99f6e4; }
        .slep-role-muted { background:#f1f5f9; color:#475569; border-color:#dbe4f0; }

        @media (min-width: 1200px) {
            .slep-sidebar-collapsed .slep-sidebar { transform: translateX(-100%); }
            .slep-sidebar-collapsed .slep-main { padding-left: 0; }
        }
        @media (max-width: 1199.98px) {
            .slep-sidebar { transform: translateX(-100%); }
            .slep-sidebar-open .slep-sidebar { transform: translateX(0); }
            .slep-sidebar-open .slep-overlay { display:block; }
            .slep-main { padding-left: 0; }
            .slep-sidebar-toggle { display:inline-flex; }
        }
        @media (max-width: 767.98px) {
            .slep-topbar { padding: 0 .85rem; }
            .slep-global-search { display:none; }
            .slep-user-meta, .slep-role-chip { display:none; }
            .slep-content { padding: 1rem; }
        }


        .slep-icon-btn.is-changelog { color:#1d4ed8; background:#eff6ff; border-color:#cfe0ff; }
        .slep-icon-btn.is-changelog:hover { background:#dbeafe; color:#0d47a1; }
        .slep-sidebar-help .btn[data-bs-toggle="modal"] { font-weight:800; }

        .sga-guest-shell { min-height: 100vh; position: relative; overflow: hidden; background: radial-gradient(circle at 78% 22%, rgba(13,110,253,.14), transparent 28rem), linear-gradient(135deg, #f8fbff 0%, #eef3f9 100%); }
        .sga-guest-shell::before { content:''; position:absolute; inset:0; background: linear-gradient(120deg, rgba(255,255,255,.85) 0%, rgba(255,255,255,.72) 48%, rgba(239,246,255,.8) 100%); pointer-events:none; }
        .sga-guest-watermark { position:absolute; right: clamp(.75rem, 2.5vw, 3rem); bottom: clamp(2rem, 7vw, 7rem); width: min(24vw, 330px); max-height: 22vh; opacity:.12; pointer-events:none; object-fit: contain; filter: drop-shadow(0 18px 40px rgba(15,23,42,.18)); }
        .sga-guest-main { position:relative; z-index:1; min-height:100vh; display:flex; align-items:center; padding: clamp(1.25rem, 3vw, 3rem) 0; }
        .sga-auth-card { border:1px solid var(--slep-border); border-radius:24px; box-shadow: 0 18px 46px rgba(15,23,42,.09); overflow:hidden; }
        .sga-auth-card .card-body { padding: clamp(1.3rem, 2vw, 2rem); }
        .sga-auth-panel { border:1px solid #cfe0ff; border-radius:26px; background: linear-gradient(135deg,#0b3d91,#0d6efd); color:#fff; min-height:100%; padding: clamp(1.5rem, 3vw, 2.5rem); box-shadow: 0 18px 46px rgba(13,110,253,.22); position:relative; overflow:hidden; }
        .sga-auth-panel::after { content:''; position:absolute; width: 19rem; height:19rem; right:-7rem; bottom:-7rem; border-radius:50%; background:rgba(255,255,255,.12); }
        .sga-auth-logo { width:min(100%, 330px); min-height:128px; border-radius:24px; background:#fff; padding:1rem 1.25rem; display:inline-flex; align-items:center; justify-content:center; box-shadow:0 18px 34px rgba(15,23,42,.18); margin-bottom:1.25rem; overflow:hidden; }
        .sga-auth-logo img { width:100%; max-height:106px; object-fit:contain; display:block; }
        .sga-auth-logo-fallback { width:100%; min-height:62px; border-radius:18px; display:inline-flex; align-items:center; justify-content:center; background:#f8fafc; color:#0b3d91; font-weight:900; letter-spacing:.04em; }
        .sga-auth-eyebrow { font-weight:800; letter-spacing:.08em; text-transform:uppercase; font-size:.76rem; opacity:.86; margin-bottom:.45rem; }
        .sga-auth-title { font-size: clamp(1.7rem, 3vw, 2.55rem); font-weight:900; line-height:1.05; margin-bottom:.8rem; color:#f8fbff !important; text-shadow:0 2px 14px rgba(0,0,0,.18); }
        .sga-auth-subtitle { color: rgba(255,255,255,.84); font-size:1rem; line-height:1.55; max-width:28rem; }
        .sga-auth-feature { display:flex; gap:.75rem; align-items:flex-start; margin-top:1rem; color:rgba(255,255,255,.9); }
        .sga-auth-feature i { width:2rem; height:2rem; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; background:rgba(255,255,255,.16); flex:0 0 auto; }
        .sga-auth-section-title { font-weight:900; color:#0f172a; }
        .sga-auth-muted { color:#64748b; }
        .sga-auth-actions .btn { min-height:46px; border-radius:14px; font-weight:800; }

        @media (max-width: 991.98px) {
            .sga-guest-main { align-items:flex-start; }
            .sga-guest-watermark { display:none; }
            .sga-auth-panel { min-height:auto; }
        }
    </style>

    @stack('styles')
</head>

<body>
<script>
    try {
        if (window.innerWidth >= 1200 && window.localStorage && window.localStorage.getItem('slepSidebarCollapsed') === '1') {
            document.body.classList.add('slep-sidebar-collapsed');
        }
    } catch (error) {}
</script>
@php
    $layoutUser = auth()->user();
    $layoutActiveRole = $layoutUser && method_exists($layoutUser, 'activeRoleName') ? $layoutUser->activeRoleName() : null;
    $layoutRoleLabel = $layoutUser ? \App\Support\SlepUiRegistry::roleLabel($layoutActiveRole) : null;
    $layoutRoleTone = \App\Support\SlepUiRegistry::roleTone($layoutActiveRole);
    $layoutMenuGroups = $layoutUser ? \App\Support\SlepUiRegistry::menuGroups($layoutUser, $layoutActiveRole) : [];
    $layoutTutorialImpersonation = session('tutorial_postulante_impersonation');
    $layoutTutorialStopUrl = Route::has('gestion.postulante-tutorial.stop')
        ? route('gestion.postulante-tutorial.stop')
        : url('/gestion/postulantes/tutoriales/finalizar');
    $layoutRoles = $layoutUser && method_exists($layoutUser, 'availableRoleContexts') ? $layoutUser->availableRoleContexts() : collect();
    $layoutSidebarLogoPath = file_exists(public_path('branding/05_logo_sidebar.png')) ? 'branding/05_logo_sidebar.png' : (file_exists(public_path('branding/05_logo_sidebar.svg')) ? 'branding/05_logo_sidebar.svg' : (file_exists(public_path('branding/02_icono_app.png')) ? 'branding/02_icono_app.png' : (file_exists(public_path('branding/02_icono_app.svg')) ? 'branding/02_icono_app.svg' : (file_exists(public_path('branding/01_logo_principal.png')) ? 'branding/01_logo_principal.png' : 'branding/01_logo_principal.svg'))));
    $layoutLoginLogoPath = file_exists(public_path('branding/06_logo_login.png')) ? 'branding/06_logo_login.png' : (file_exists(public_path('branding/06_logo_login.svg')) ? 'branding/06_logo_login.svg' : (file_exists(public_path('branding/04_lockup_horizontal.png')) ? 'branding/04_lockup_horizontal.png' : (file_exists(public_path('branding/04_lockup_horizontal.svg')) ? 'branding/04_lockup_horizontal.svg' : $layoutSidebarLogoPath)));
    $layoutLoginWatermarkPath = file_exists(public_path('branding/04_lockup_horizontal.png')) ? 'branding/04_lockup_horizontal.png' : (file_exists(public_path('branding/04_lockup_horizontal.svg')) ? 'branding/04_lockup_horizontal.svg' : (file_exists(public_path('branding/01_logo_principal.png')) ? 'branding/01_logo_principal.png' : (file_exists(public_path('branding/01_logo_principal.svg')) ? 'branding/01_logo_principal.svg' : $layoutLoginLogoPath)));
    $layoutLogoVersion = config('changelog.current_version', '2026');
    $layoutSidebarLogoUrl = asset($layoutSidebarLogoPath) . '?v=' . rawurlencode((string) $layoutLogoVersion);
    $layoutLoginLogoUrl = asset($layoutLoginLogoPath) . '?v=' . rawurlencode((string) $layoutLogoVersion);
    $layoutLoginWatermarkUrl = asset($layoutLoginWatermarkPath) . '?v=' . rawurlencode((string) $layoutLogoVersion);
    $layoutLogoUrl = $layoutSidebarLogoUrl;
@endphp

@auth
    <div class="slep-shell">
        <aside class="slep-sidebar" aria-label="Menú principal">
            <a class="slep-brand text-decoration-none" href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}">
                <span class="slep-brand-logo" aria-hidden="true">
                    <img src="{{ $layoutSidebarLogoUrl }}" alt="" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                    <span class="slep-brand-logo-fallback d-none">AC</span>
                </span>
                <span>
                    <span class="slep-brand-title d-block">Plataforma SLEP Andalién Costa</span>
                    <span class="slep-brand-subtitle d-block">Gestión Administrativa 2026</span>
                </span>
            </a>

            <div class="slep-nav-scroll">
                @foreach ($layoutMenuGroups as $group => $items)
                    <div class="slep-menu-group">
                        <div class="slep-menu-title">{{ $group }}</div>
                        @foreach ($items as $item)
                            @php
                                $layoutCurrentRouteName = request()->route()?->getName();
                                $layoutItemActive = request()->routeIs($item['route']);

                                if (! $layoutItemActive && $item['route'] === 'gestion.solicitudes-reemplazo.index') {
                                    $layoutItemActive = request()->routeIs('gestion.solicitudes-reemplazo.*')
                                        && ! request()->routeIs('gestion.solicitudes-reemplazo.finiquitos.*');
                                } elseif (! $layoutItemActive && $item['route'] === 'gestion.solicitudes-reemplazo.finiquitos.index') {
                                    $layoutItemActive = request()->routeIs('gestion.solicitudes-reemplazo.finiquitos.*');
                                } elseif (! $layoutItemActive && ! in_array($item['route'], ['gestion.solicitudes-reemplazo.index', 'gestion.solicitudes-reemplazo.finiquitos.index'], true)) {
                                    $layoutRoutePrefix = \Illuminate\Support\Str::beforeLast($item['route'], '.');
                                    $layoutItemActive = $layoutRoutePrefix !== $item['route'] && request()->routeIs($layoutRoutePrefix . '.*');
                                }
                            @endphp
                            <a class="slep-menu-link {{ $layoutItemActive ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                <i class="bi {{ $item['icon'] }}"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="slep-sidebar-help">
                <span class="slep-help-icon"><i class="bi bi-question-lg"></i></span>
                <div class="fw-bold mb-1">¿Necesitas ayuda?</div>
                <div class="small mb-3">Consulta manuales, soporte interno o revisa el historial del sistema.</div>
                @if (!empty($hasVisibleChangeLogEntries ?? false))
                    <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#changeLogModal" data-changelog-open-history="1">Ver changelog</button>
                @else
                    <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}" class="btn btn-sm btn-outline-primary w-100">Centro de ayuda</a>
                @endif
            </div>
        </aside>
        <div class="slep-overlay" data-slep-close></div>

        <div class="slep-main">
            <header class="slep-topbar">
                <button type="button" class="slep-sidebar-toggle" data-slep-toggle aria-label="Ocultar menú" title="Ocultar menú"><i class="bi bi-layout-sidebar-inset fs-5" data-slep-toggle-icon></i></button>

                <div class="slep-global-search" data-global-search data-search-url="{{ Route::has('global-search') ? route('global-search') : '' }}">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" placeholder="Buscar funcionarios, establecimientos, solicitudes..." aria-label="Búsqueda global" autocomplete="off" data-global-search-input>
                    <div class="slep-global-search-results" data-global-search-results role="listbox" aria-label="Resultados de búsqueda global">
                        <div class="slep-global-search-header">Búsqueda global</div>
                        <div class="slep-global-search-list" data-global-search-list></div>
                    </div>
                </div>

                <div class="slep-top-actions">
                    <span class="slep-role-chip slep-role-{{ $layoutRoleTone }}"><i class="bi bi-person-badge"></i>{{ $layoutRoleLabel }}</span>
                    @if (!empty($hasVisibleChangeLogEntries ?? false))
                        <button type="button" class="slep-icon-btn is-changelog" data-bs-toggle="modal" data-bs-target="#changeLogModal" title="Ver changelog del sistema">
                            <i class="bi bi-journal-text"></i>
                        </button>
                    @endif
                    <a class="slep-icon-btn slep-message-shortcut" href="{{ Route::has('messages.index') ? route('messages.index') : '#' }}" title="Mensajes internos" aria-label="Mensajes internos" data-message-unread-button data-unread-url="{{ Route::has('messages.unread-summary') ? route('messages.unread-summary') : '' }}" data-unread-interval="10000">
                        <i class="bi bi-chat-left-text"></i>
                        <span class="slep-notification-count is-hidden" data-message-unread-badge aria-live="polite">0</span>
                    </a>
                    @if(is_array($layoutTutorialImpersonation))
                        <form method="POST" action="{{ $layoutTutorialStopUrl }}" class="d-none d-lg-inline-flex m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-danger rounded-pill fw-bold px-3" title="Finalizar vista temporal y volver a tu cuenta original">
                                <i class="bi bi-box-arrow-left me-1"></i> Finalizar vista
                            </button>
                        </form>
                    @endif
                    <div class="dropdown">
                        <a class="slep-user-menu dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="slep-avatar">{{ \App\Support\SlepUiRegistry::initials($layoutUser) }}</span>
                            <span class="slep-user-meta">
                                <span class="slep-user-name d-block">{{ $layoutUser->nombre_completo ?? $layoutUser->name ?? 'Usuario' }}</span>
                                <span class="slep-user-role d-block">{{ $layoutRoleLabel }}</span>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                            @if ($layoutRoles->count() > 1 && Route::has('role-context.update'))
                                <li class="px-3 py-2">
                                    <form method="POST" action="{{ route('role-context.update') }}">
                                        @csrf
                                        <label class="form-label small text-muted mb-1" for="topbar-active-role">Rol activo</label>
                                        <select id="topbar-active-role" name="active_role" class="form-select form-select-sm" onchange="this.form.submit()">
                                            @foreach ($layoutRoles as $roleName)
                                                <option value="{{ $roleName }}" @selected($layoutActiveRole === $roleName)>{{ $layoutUser->roleContextLabel($roleName) }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                            @if (!empty($hasVisibleChangeLogEntries ?? false))
                                <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#changeLogModal"><i class="bi bi-journal-text me-2"></i>Changelog del sistema</button></li>
                                @if (!empty($hasPreviousChangeLogEntries ?? false))
                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#changeLogModal" data-changelog-open-history="1"><i class="bi bi-clock-history me-2"></i>Historial de cambios</button></li>
                                @endif
                            @endif
                            @if (Route::has('messages.index'))
                                <li><a class="dropdown-item" href="{{ route('messages.index') }}"><i class="bi bi-chat-dots me-2"></i>Mensajes</a></li>
                            @endif
                            @if(is_array($layoutTutorialImpersonation))
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ $layoutTutorialStopUrl }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger fw-bold"><i class="bi bi-box-arrow-left me-2"></i>Finalizar vista temporal</button>
                                    </form>
                                </li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            @if (Route::has('logout'))
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
                                    </form>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </header>

            @if(is_array($layoutTutorialImpersonation))
                <div class="px-3 px-lg-4 pt-3">
                    <div class="alert alert-warning border-0 shadow-sm rounded-4 d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3 mb-0">
                        <div>
                            <div class="fw-bold"><i class="bi bi-person-video2 me-1"></i> Vista temporal de usuario activa</div>
                            <div class="small">Estás viendo el sistema como <strong>{{ $layoutTutorialImpersonation['target_name'] ?? 'usuario' }}</strong>. Cuenta original: {{ $layoutTutorialImpersonation['impersonator_name'] ?? 'usuario' }}.</div>
                        </div>
                        <form method="POST" action="{{ $layoutTutorialStopUrl }}" class="m-0">
                            @csrf
                            <button type="submit" class="btn btn-danger rounded-pill fw-bold"><i class="bi bi-box-arrow-left me-1"></i> Finalizar vista</button>
                        </form>
                    </div>
                </div>
            @endif

            <main class="slep-content">
                <div class="slep-content-inner">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
@else
    <div class="sga-guest-shell">
        <img class="sga-guest-watermark" src="{{ $layoutLoginWatermarkUrl }}" alt="Plataforma SLEP Andalién Costa" aria-hidden="true">
        <main class="sga-guest-main">
            <div class="container">
                @yield('content')
            </div>
        </main>
    </div>
@endauth

@auth
    @include('partials.changelog-modal')
@endauth
@stack('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        function isDesktopSidebar() {
            return window.matchMedia('(min-width: 1200px)').matches;
        }

        function updateSidebarToggleState() {
            var isCollapsed = document.body.classList.contains('slep-sidebar-collapsed');
            document.querySelectorAll('[data-slep-toggle]').forEach(function (button) {
                var icon = button.querySelector('[data-slep-toggle-icon]');
                if (isDesktopSidebar()) {
                    button.setAttribute('aria-label', isCollapsed ? 'Mostrar menú' : 'Ocultar menú');
                    button.setAttribute('title', isCollapsed ? 'Mostrar menú' : 'Ocultar menú');
                    if (icon) icon.className = 'bi ' + (isCollapsed ? 'bi-layout-sidebar-inset-reverse' : 'bi-layout-sidebar-inset') + ' fs-5';
                } else {
                    button.setAttribute('aria-label', 'Abrir menú');
                    button.setAttribute('title', 'Abrir menú');
                    if (icon) icon.className = 'bi bi-list fs-5';
                }
            });
        }

        document.querySelectorAll('[data-slep-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (isDesktopSidebar()) {
                    document.body.classList.toggle('slep-sidebar-collapsed');
                    try {
                        window.localStorage.setItem('slepSidebarCollapsed', document.body.classList.contains('slep-sidebar-collapsed') ? '1' : '0');
                    } catch (error) {}
                    updateSidebarToggleState();
                    return;
                }

                document.body.classList.toggle('slep-sidebar-open');
                updateSidebarToggleState();
            });
        });
        document.querySelectorAll('[data-slep-close]').forEach(function (button) {
            button.addEventListener('click', function () { document.body.classList.remove('slep-sidebar-open'); });
        });
        window.addEventListener('resize', function () {
            if (isDesktopSidebar()) {
                document.body.classList.remove('slep-sidebar-open');
                try {
                    if (window.localStorage && window.localStorage.getItem('slepSidebarCollapsed') === '1') {
                        document.body.classList.add('slep-sidebar-collapsed');
                    }
                } catch (error) {}
            }
            updateSidebarToggleState();
        });
        updateSidebarToggleState();


        document.querySelectorAll('[data-message-unread-button]').forEach(function (button) {
            var badge = button.querySelector('[data-message-unread-badge]');
            var url = button.getAttribute('data-unread-url') || '';
            var intervalMs = parseInt(button.getAttribute('data-unread-interval') || '10000', 10);
            var timer = null;
            var controller = null;

            if (!badge || !url) return;

            function applyCount(count) {
                count = parseInt(count || 0, 10);
                if (!Number.isFinite(count) || count < 0) count = 0;

                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.toggle('is-hidden', count === 0);
                button.classList.toggle('has-unread', count > 0);

                var label = count > 0
                    ? 'Mensajes internos, ' + count + ' mensaje' + (count === 1 ? '' : 's') + ' no leído' + (count === 1 ? '' : 's')
                    : 'Mensajes internos, sin mensajes no leídos';

                button.setAttribute('title', label);
                button.setAttribute('aria-label', label);
            }

            function loadUnreadCount() {
                if (document.hidden) return;
                if (controller) controller.abort();
                controller = new AbortController();

                fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal,
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                    .then(function (payload) {
                        applyCount(payload && payload.unread_total ? payload.unread_total : 0);
                    })
                    .catch(function (error) {
                        if (error && error.name === 'AbortError') return;
                    });
            }

            loadUnreadCount();
            timer = window.setInterval(loadUnreadCount, Number.isFinite(intervalMs) && intervalMs >= 10000 ? intervalMs : 10000);

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    loadUnreadCount();
                }
            });
        });

        document.querySelectorAll('[data-global-search]').forEach(function (root) {
            var input = root.querySelector('[data-global-search-input]');
            var panel = root.querySelector('[data-global-search-results]');
            var list = root.querySelector('[data-global-search-list]');
            var url = root.getAttribute('data-search-url') || '';
            var timer = null;
            var controller = null;

            if (!input || !panel || !list || !url) return;

            function closePanel() {
                panel.classList.remove('is-open');
            }

            function openPanel() {
                panel.classList.add('is-open');
            }

            function renderEmpty(message) {
                list.innerHTML = '';
                var empty = document.createElement('div');
                empty.className = 'slep-global-search-empty';
                empty.textContent = message || 'Sin resultados para el rol activo.';
                list.appendChild(empty);
                openPanel();
            }

            function renderResults(results, message) {
                list.innerHTML = '';
                if (!results || results.length === 0) {
                    renderEmpty(message);
                    return;
                }

                results.forEach(function (item) {
                    var link = document.createElement('a');
                    link.className = 'slep-global-search-item';
                    link.href = item.url || '#';
                    link.setAttribute('role', 'option');

                    var icon = document.createElement('span');
                    icon.className = 'slep-global-search-icon';
                    var iconEl = document.createElement('i');
                    iconEl.className = 'bi ' + (item.icon || 'bi-search');
                    icon.appendChild(iconEl);

                    var body = document.createElement('span');
                    body.className = 'flex-grow-1';

                    var badge = document.createElement('span');
                    badge.className = 'slep-global-search-badge d-block';
                    badge.textContent = item.badge || 'Resultado';

                    var title = document.createElement('span');
                    title.className = 'slep-global-search-title d-block';
                    title.textContent = item.title || 'Resultado';

                    var subtitle = document.createElement('span');
                    subtitle.className = 'slep-global-search-subtitle d-block';
                    subtitle.textContent = item.subtitle || '';

                    body.appendChild(badge);
                    body.appendChild(title);
                    if (item.subtitle) body.appendChild(subtitle);
                    link.appendChild(icon);
                    link.appendChild(body);
                    list.appendChild(link);
                });
                openPanel();
            }

            function search(term) {
                if (controller) controller.abort();
                controller = new AbortController();
                fetch(url + '?q=' + encodeURIComponent(term), {
                    headers: { 'Accept': 'application/json' },
                    signal: controller.signal
                })
                    .then(function (response) { return response.ok ? response.json() : Promise.reject(response); })
                    .then(function (payload) { renderResults(payload.results || [], payload.message); })
                    .catch(function (error) {
                        if (error && error.name === 'AbortError') return;
                        renderEmpty('No fue posible consultar la búsqueda global.');
                    });
            }

            input.addEventListener('input', function () {
                var term = input.value.trim();
                clearTimeout(timer);
                if (term.length < 2) {
                    closePanel();
                    list.innerHTML = '';
                    return;
                }
                timer = setTimeout(function () { search(term); }, 260);
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closePanel();
                if (event.key === 'Enter') {
                    var first = list.querySelector('a.slep-global-search-item');
                    if (first) {
                        event.preventDefault();
                        window.location.href = first.href;
                    }
                }
            });

            document.addEventListener('click', function (event) {
                if (!root.contains(event.target)) closePanel();
            });
        });
    });
</script>
</body>
</html>
