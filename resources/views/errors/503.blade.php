@php
    $platformName = config('brand.platform_name', config('app.name', 'SGA'));
    $organizationName = config('brand.org_name', 'SLEP Andalién Costa');
    $logoPath = config('brand.logo_login', 'branding/06_logo_login.png');
    $retryMessage = is_numeric($retryAfter ?? null) && (int) $retryAfter > 0
        ? 'La página se actualizará automáticamente en aproximadamente '.(int) $retryAfter.' segundos.'
        : 'Vuelve a intentarlo en unos minutos.';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b3d91">
    <meta name="robots" content="noindex, nofollow">
    <title>Mantenimiento | {{ $platformName }}</title>
    <style>
        :root { color-scheme: light; --sga-blue:#0b3d91; --sga-blue-bright:#0d6efd; --sga-ink:#0f172a; --sga-muted:#64748b; --sga-border:#dbe4f0; }
        * { box-sizing:border-box; }
        body { min-height:100vh; margin:0; color:var(--sga-ink); font-family:Inter, Gilmer, Arial, sans-serif; background:radial-gradient(circle at 78% 18%, rgba(13,110,253,.16), transparent 27rem), linear-gradient(135deg,#f8fbff 0%,#eef3f9 100%); }
        .maintenance-shell { min-height:100vh; position:relative; display:grid; place-items:center; overflow:hidden; padding:1.5rem; }
        .maintenance-shell::before, .maintenance-shell::after { content:''; position:absolute; border-radius:999px; pointer-events:none; }
        .maintenance-shell::before { width:32rem; height:32rem; right:-16rem; top:-13rem; background:rgba(13,110,253,.08); }
        .maintenance-shell::after { width:20rem; height:20rem; left:-10rem; bottom:-11rem; background:rgba(11,61,145,.07); }
        .maintenance-card { width:min(100%, 62rem); position:relative; z-index:1; overflow:hidden; display:grid; grid-template-columns:minmax(0,1fr) minmax(20rem,.82fr); border:1px solid var(--sga-border); border-radius:1.6rem; background:#fff; box-shadow:0 1.4rem 3.6rem rgba(15,23,42,.12); }
        .maintenance-brand { position:relative; overflow:hidden; padding:clamp(2rem,5vw,4rem); color:#fff; background:linear-gradient(135deg,var(--sga-blue),var(--sga-blue-bright)); }
        .maintenance-brand::after { content:''; position:absolute; width:20rem; height:20rem; right:-8rem; bottom:-9rem; border:2rem solid rgba(255,255,255,.12); border-radius:50%; }
        .maintenance-eyebrow { position:relative; z-index:1; margin:0 0 .75rem; font-size:.74rem; font-weight:800; letter-spacing:.11em; text-transform:uppercase; opacity:.86; }
        .maintenance-brand h1 { position:relative; z-index:1; max-width:24rem; margin:0; font-size:clamp(2rem,5vw,3.35rem); line-height:1.04; letter-spacing:-.045em; }
        .maintenance-brand p { position:relative; z-index:1; max-width:25rem; margin:1rem 0 0; color:rgba(255,255,255,.87); font-size:1rem; line-height:1.6; }
        .maintenance-brand-note { position:relative; z-index:1; display:flex; align-items:center; gap:.65rem; margin-top:2rem; font-size:.9rem; color:rgba(255,255,255,.9); }
        .maintenance-brand-note svg { flex:0 0 auto; width:1.3rem; height:1.3rem; }
        .maintenance-content { display:flex; flex-direction:column; justify-content:center; padding:clamp(1.75rem,4vw,3.3rem); background:linear-gradient(180deg,#fff 0%,#f8fbff 100%); }
        .maintenance-logo { width:min(100%,15rem); min-height:5.5rem; display:flex; align-items:center; justify-content:center; margin-bottom:1.7rem; padding:.75rem 1rem; border:1px solid #d7e5f8; border-radius:1.1rem; background:#fff; box-shadow:0 .75rem 1.8rem rgba(15,23,42,.08); }
        .maintenance-logo img { display:block; width:100%; max-height:4.2rem; object-fit:contain; }
        .maintenance-logo span { display:none; color:var(--sga-blue); font-size:1.15rem; font-weight:900; letter-spacing:.08em; }
        .maintenance-logo.is-fallback span { display:block; }
        .maintenance-status { display:inline-flex; align-items:center; gap:.45rem; width:max-content; margin-bottom:1rem; padding:.42rem .72rem; border:1px solid #cfe0ff; border-radius:999px; background:#eff6ff; color:#1d4ed8; font-size:.76rem; font-weight:800; }
        .maintenance-status i { width:.55rem; height:.55rem; border-radius:50%; background:#0d6efd; box-shadow:0 0 0 .24rem rgba(13,110,253,.15); }
        .maintenance-content h2 { margin:0; font-size:1.55rem; letter-spacing:-.025em; }
        .maintenance-content p { margin:.85rem 0 0; color:var(--sga-muted); line-height:1.58; }
        .maintenance-retry { display:flex; align-items:flex-start; gap:.7rem; margin-top:1.5rem; padding:1rem; border:1px solid #dceafc; border-radius:1rem; background:#f2f7ff; color:#294b78; font-size:.9rem; line-height:1.45; }
        .maintenance-retry svg { flex:0 0 auto; width:1.2rem; height:1.2rem; color:var(--sga-blue); }
        .maintenance-footer { margin-top:1.5rem; color:#94a3b8; font-size:.78rem; }
        @media (max-width: 768px) { .maintenance-shell { padding:1rem; } .maintenance-card { grid-template-columns:1fr; } .maintenance-brand { padding:2rem; } .maintenance-brand-note { margin-top:1.4rem; } .maintenance-content { padding:2rem; } }
    </style>
</head>
<body>
    <main class="maintenance-shell">
        <section class="maintenance-card" aria-labelledby="maintenance-title">
            <div class="maintenance-brand">
                <p class="maintenance-eyebrow">{{ $organizationName }}</p>
                <h1 id="maintenance-title">Estamos realizando mejoras.</h1>
                <p>El Sistema de Gestión Administrativa se encuentra temporalmente en mantenimiento para mejorar tu experiencia.</p>
                <div class="maintenance-brand-note">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="8" cy="7" r="3"/></svg>
                    Actualización segura en curso
                </div>
            </div>
            <div class="maintenance-content">
                <div class="maintenance-logo">
                    <img src="{{ asset($logoPath) }}" alt="{{ $platformName }}" onerror="this.parentElement.classList.add('is-fallback'); this.remove();">
                    <span>SGA</span>
                </div>
                <div class="maintenance-status"><i></i>Mantenimiento programado</div>
                <h2>Volveremos muy pronto</h2>
                <p>Estamos aplicando una actualización del sistema. Tu información permanece protegida durante este proceso.</p>
                <div class="maintenance-retry">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>
                    <span>{{ $retryMessage }}</span>
                </div>
                <div class="maintenance-footer">{{ $platformName }} · Servicio temporalmente no disponible</div>
            </div>
        </section>
    </main>
</body>
</html>
