<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Constancia de Incumplimiento Laboral</title>
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 11px;
            line-height: 1.45;
        }
        .header {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { height: 48px; max-width: 210px; object-fit: contain; }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 4px 0;
        }
        .subtitle {
            color: #4b5563;
            font-size: 11px;
            margin: 0;
        }
        .section {
            margin-bottom: 16px;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .4px;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        table.meta {
            width: 100%;
            border-collapse: collapse;
        }
        table.meta td {
            vertical-align: top;
            padding: 5px 6px;
            border-bottom: 1px solid #e5e7eb;
        }
        td.label {
            width: 28%;
            color: #6b7280;
            font-weight: bold;
        }
        .summary-box {
            border: 1px solid #d1d5db;
            background: #f9fafb;
            padding: 10px 12px;
            margin-top: 8px;
        }
        .footnote {
            margin-top: 24px;
            font-size: 10px;
            color: #6b7280;
        }
        .text-right {
            text-align: right;
        }
        .mono {
            font-family: DejaVu Sans Mono, monospace;
        }
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
        $funcionarioRut = \App\Support\Rut::format($item->funcionario_rut) ?? $item->funcionario_rut;
        $informadoPor = $item->informadoPor?->nombre_completo ?: ($item->informadoPor?->email ?? '—');
        $actualizadoPor = $item->actualizadoPor?->nombre_completo ?: ($item->actualizadoPor?->email ?? '—');
        $spanDias = ($item->fecha_desde && $item->fecha_hasta) ? $item->fecha_desde->diffInDays($item->fecha_hasta) : 0;
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 220px;">
                    @if ($logoData)
                        <img class="logo" src="{{ $logoData }}" alt="{{ $platformName }}">
                    @endif
                </td>
                <td style="text-align: right;">
                    <div class="title">Constancia de Incumplimiento Laboral</div>
                    <p class="subtitle">Documento generado por {{ $platformName }}.</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Identificación del registro</div>
        <table class="meta">
            <tr>
                <td class="label">ID interno</td>
                <td class="mono">#{{ $item->id }}</td>
                <td class="label">Fecha de emisión</td>
                <td>{{ cl_datetime($issuedAt) }}</td>
            </tr>
            <tr>
                <td class="label">Informado por</td>
                <td>{{ $informadoPor }}</td>
                <td class="label">Última actualización</td>
                <td>{{ cl_datetime($item->updated_at) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Funcionario</div>
        <table class="meta">
            <tr>
                <td class="label">RUT</td>
                <td>{{ $funcionarioRut }}</td>
                <td class="label">Nombre</td>
                <td>{{ $item->funcionario_nombre }}</td>
            </tr>
            <tr>
                <td class="label">Establecimiento</td>
                <td>{{ $item->establecimiento?->nombre_establecimiento ?: '—' }}</td>
                <td class="label">Comuna</td>
                <td>{{ $item->establecimiento?->comuna ?: '—' }}</td>
            </tr>
            <tr>
                <td class="label">RBD</td>
                <td>{{ $item->funcionario_rbd ?: '—' }}</td>
                <td class="label">Referencia padrón</td>
                <td>{{ $item->reemplazo_personal_id ? ('#' . $item->reemplazo_personal_id) : '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Período y duración informada</div>
        <table class="meta">
            <tr>
                <td class="label">Fecha desde</td>
                <td>{{ cl_plain_date($item->fecha_desde) }}</td>
                <td class="label">Fecha hasta</td>
                <td>{{ cl_plain_date($item->fecha_hasta) }}</td>
            </tr>
            <tr>
                <td class="label">Tramo calendario</td>
                <td>{{ $spanDias === 0 ? 'Mismo día' : ($spanDias + 1) . ' días calendario' }}</td>
                <td class="label">Duración informada</td>
                <td>{{ $item->dias }} día(s), {{ $item->horas }} hora(s), {{ $item->minutos }} minuto(s)</td>
            </tr>
        </table>

        <div class="summary-box">
            Se deja constancia de que este incumplimiento fue registrado en el sistema respecto del funcionario individualizado
            anteriormente, asociado al establecimiento indicado y con el período informado en este documento.
        </div>
    </div>

    <div class="section">
        <div class="section-title">Auditoría básica</div>
        <table class="meta">
            <tr>
                <td class="label">Creado</td>
                <td>{{ cl_datetime($item->created_at) }}</td>
                <td class="label">Actualizado por</td>
                <td>{{ $actualizadoPor }}</td>
            </tr>
        </table>
    </div>

    <div class="footnote">
        Constancia generada automáticamente por el sistema. Para eventuales correcciones o revisiones administrativas,
        se debe utilizar el flujo interno definido por SLEP Andalién Costa.
    </div>
</body>
</html>
