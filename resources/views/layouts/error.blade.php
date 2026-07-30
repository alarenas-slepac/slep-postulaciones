<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'Ha ocurrido un error') · SLEP Postulaciones</title>
    @vite(['resources/js/app.js', 'resources/scss/app.scss'])
    <style id="ui-contrast-fix-2026-03-22">
        .text-success,
        .link-success,
        a.text-success:hover,
        a.text-success:focus {
            color: #146c43 !important;
        }

        .text-warning,
        .link-warning,
        .text-warning-emphasis,
        a.text-warning:hover,
        a.text-warning:focus,
        a.link-warning:hover,
        a.link-warning:focus {
            color: #7a5d00 !important;
        }

        .text-info,
        .link-info,
        .text-info-emphasis,
        a.text-info:hover,
        a.text-info:focus,
        a.link-info:hover,
        a.link-info:focus {
            color: #055160 !important;
        }

        .btn-warning {
            --bs-btn-color: #1f2937;
            --bs-btn-bg: #d9b300;
            --bs-btn-border-color: #d9b300;
            --bs-btn-hover-color: #111827;
            --bs-btn-hover-bg: #c29f00;
            --bs-btn-hover-border-color: #c29f00;
            --bs-btn-active-color: #111827;
            --bs-btn-active-bg: #a88900;
            --bs-btn-active-border-color: #a88900;
        }

        .btn-outline-warning {
            --bs-btn-color: #7a5d00;
            --bs-btn-border-color: #7a5d00;
            --bs-btn-hover-color: #ffffff;
            --bs-btn-hover-bg: #7a5d00;
            --bs-btn-hover-border-color: #7a5d00;
            --bs-btn-active-color: #ffffff;
            --bs-btn-active-bg: #614900;
            --bs-btn-active-border-color: #614900;
        }

        .btn-info {
            --bs-btn-color: #ffffff;
            --bs-btn-bg: #0b8faa;
            --bs-btn-border-color: #0b8faa;
            --bs-btn-hover-color: #ffffff;
            --bs-btn-hover-bg: #087990;
            --bs-btn-hover-border-color: #087990;
            --bs-btn-active-color: #ffffff;
            --bs-btn-active-bg: #06657a;
            --bs-btn-active-border-color: #06657a;
        }

        .btn-outline-info {
            --bs-btn-color: #055160;
            --bs-btn-border-color: #055160;
            --bs-btn-hover-color: #ffffff;
            --bs-btn-hover-bg: #055160;
            --bs-btn-hover-border-color: #055160;
            --bs-btn-active-color: #ffffff;
            --bs-btn-active-bg: #043b47;
            --bs-btn-active-border-color: #043b47;
        }

        .badge.text-bg-warning,
        .badge.bg-warning {
            background-color: #d9b300 !important;
            color: #1f2937 !important;
        }

        .badge.text-bg-info,
        .badge.bg-info {
            background-color: #0b8faa !important;
            color: #ffffff !important;
        }

        .badge.bg-info-subtle {
            background-color: #d2eef3 !important;
        }

        .badge.bg-info-subtle,
        .badge.bg-info-subtle *,
        .badge.bg-info-subtle.text-info-emphasis,
        .badge.bg-info-subtle.text-info-emphasis * {
            color: #055160 !important;
        }

        .badge.text-bg-danger,
        .badge.text-bg-danger *,
        .badge.bg-danger,
        .badge.bg-danger * {
            color: #ffffff !important;
        }

        .badge.text-bg-secondary,
        .badge.text-bg-secondary *,
        .badge.bg-secondary,
        .badge.bg-secondary * {
            color: #ffffff !important;
        }

        .error-hero {
            padding: 2.5rem 0
        }

        .error-code {
            font-weight: 800;
            font-size: 3rem;
            line-height: 1
        }
    </style>
</head>

<body class="bg-light">
    <main class="error-hero">
        <div class="container">
            <div class="d-flex align-items-center gap-3 mb-4">
                <img src="{{ asset(config('brand.logo_lockup_horizontal', 'branding/04_lockup_horizontal.png')) }}" alt="{{ config('brand.platform_name', config('app.name')) }}" height="36"
                    onerror="this.onerror=null;this.src='{{ asset(config('brand.logo_principal', 'branding/01_logo_principal.png')) }}'">
                <h1 class="h5 m-0">{{ config('brand.platform_name', config('app.name')) }}</h1>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-4">
                        <div class="error-code text-primary">@yield('code', '—')</div>
                        <div>
                            <h2 class="h4">@yield('headline', 'Algo salió mal')</h2>
                            <p class="text-muted mb-3">@yield('message', 'Intenta nuevamente en unos segundos.')</p>

                            @hasSection('extra')
                                @yield('extra')
                            @endif

                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <a href="{{ url('/') }}" class="btn btn-primary">Ir al inicio</a>
                                @if (url()->previous())
                                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Volver atrás</a>
                                @endif
                                @php $support = config('mail.from.address'); @endphp
                                @if ($support)
                                    <a class="btn btn-outline-secondary" href="mailto:{{ $support }}">Contactar
                                        soporte</a>
                                @endif
                            </div>

                            @if (app()->hasDebugModeEnabled() && config('app.debug'))
                                @if (!empty($exceptionId))
                                    <p class="small text-muted mt-3">ID de incidente: <code>{{ $exceptionId }}</code>
                                    </p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <p class="small text-muted mt-3">Si el problema persiste, por favor contacta a soporte indicando el ID de
                incidente (si aparece).</p>
        </div>
    </main>
</body>

</html>
