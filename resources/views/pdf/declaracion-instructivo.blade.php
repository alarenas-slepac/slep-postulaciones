<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $instructivo['titulo'] ?? 'Instructivo Declaración Establecimiento 2026' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; margin: 32px; }
        .header-table { width: 100%; border-collapse: collapse; border-bottom: 2px solid #1d4ed8; padding-bottom: 8px; margin-bottom: 16px; }
        .header-table td { vertical-align: middle; padding-bottom: 8px; }
        .logo { height: 48px; max-width: 210px; object-fit: contain; }
        .title { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .subtitle { font-size: 11px; color: #475569; margin-bottom: 18px; }
        .meta { margin-bottom: 16px; font-size: 10px; color: #64748b; }
        .note { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 10px 12px; margin-bottom: 16px; }
        .section { margin-bottom: 16px; }
        .section-title { font-size: 14px; font-weight: bold; color: #1d4ed8; margin-bottom: 6px; }
        ul { margin: 0; padding-left: 18px; }
        li { margin-bottom: 6px; line-height: 1.4; }
        .footer { margin-top: 18px; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
    @php
        $logoData = null;
        foreach ([
            public_path(config('brand.logo_pdf', 'branding/01_logo_principal.png')),
            public_path(config('brand.logo_lockup_horizontal', 'branding/04_lockup_horizontal.png')),
        ] as $logoFile) {
            if (is_file($logoFile)) {
                $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
                $mime = $ext === 'svg' ? 'image/svg+xml' : 'image/png';
                $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoFile));
                break;
            }
        }
        $platformName = config('brand.platform_name', 'Plataforma SLEP Andalién Costa');
    @endphp
    <table class="header-table">
        <tr>
            <td style="width: 220px;">
                @if ($logoData)
                    <img class="logo" src="{{ $logoData }}" alt="{{ $platformName }}">
                @endif
            </td>
            <td style="text-align: right;">
                <div class="title">{{ $tituloVista ?? 'Declaración Establecimiento 2026' }}</div>
                <div class="subtitle">{{ $instructivo['titulo'] ?? '' }}</div>
                <div class="meta">{{ $platformName }} · Generado el {{ now()->format('d-m-Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <div class="note">
        <strong>Regla general de archivos:</strong> solo se aceptan archivos PDF y cada documento puede pesar como máximo 10 MB.
    </div>

    @foreach(($instructivo['secciones'] ?? []) as $seccion)
        <div class="section">
            <div class="section-title">{{ $seccion['titulo'] ?? '' }}</div>
            <ul>
                @foreach(($seccion['items'] ?? []) as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    @endforeach

    <div class="footer">
        Instructivo para la pestaña {{ ucfirst($tab ?? 'docentes') }} del módulo Declaración Establecimiento 2026 · {{ config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }}.
    </div>
</body>
</html>
