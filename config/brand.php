<?php

return [
    // Nombre de la institución u organismo (usado en correos, pie de página, etc.)
    'org_name' => env('ORG_NAME', 'SLEP Andalién Costa'),

    // Datos de soporte
    'support_email' => env('SUPPORT_EMAIL', env('MAIL_FROM_ADDRESS')),
    'support_phone' => env('SUPPORT_PHONE', ''),
    'support_hours' => env('SUPPORT_HOURS', 'Lunes a Viernes 09:00–17:00'),

    // Sitio web institucional
    'org_url' => env('ORG_URL', 'https://slepandaliencosta.gob.cl/'),

    // Dirección física (opcional)
    'org_address' => env('ORG_ADDRESS', ''),

    // Enlaces legales (opcionales)
    'legal_privacy_url' => env('LEGAL_PRIVACY_URL', ''),
    'legal_terms_url'   => env('LEGAL_TERMS_URL', ''),

    // Identidad visual institucional (preferentemente PNG para correo y PDF)
    'platform_name' => env('PLATFORM_NAME', 'Plataforma SLEP Andalién Costa'),
    'period_name' => env('PLATFORM_PERIOD_NAME', 'SLEP Andalién Costa 2026'),
    'logo_principal' => env('BRAND_LOGO_PRINCIPAL', 'branding/01_logo_principal.png'),
    'logo_icono_app' => env('BRAND_LOGO_ICONO_APP', 'branding/02_icono_app.png'),
    'logo_monocromatico' => env('BRAND_LOGO_MONOCROMATICO', 'branding/03_version_monocromatica.png'),
    'logo_lockup_horizontal' => env('BRAND_LOGO_LOCKUP_HORIZONTAL', 'branding/04_lockup_horizontal.png'),
    'logo_sidebar' => env('BRAND_LOGO_SIDEBAR', 'branding/05_logo_sidebar.png'),
    'logo_login' => env('BRAND_LOGO_LOGIN', 'branding/06_logo_login.png'),
    'logo_email' => env('BRAND_LOGO_EMAIL', 'branding/04_lockup_horizontal.png'),
    'logo_pdf' => env('BRAND_LOGO_PDF', 'branding/01_logo_principal.png'),
    'logo_slep' => env('BRAND_LOGO_SLEP', 'branding/logo-andaliencosta.png'),

];
