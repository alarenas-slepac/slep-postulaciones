@php
    $platformName = config('brand.platform_name', 'Plataforma SLEP Andalién Costa');
    $orgName = config('brand.org_name', 'SLEP Andalién Costa');
    $periodName = config('brand.period_name', 'SLEP Andalién Costa 2026');
    $logoPath = config('brand.logo_email', 'branding/04_lockup_horizontal.png');
    $logoUrl = asset($logoPath);
    $emailTitle = trim($__env->yieldContent('title')) ?: $platformName;
    $preheader = trim($__env->yieldContent('preheader')) ?: 'Notificación de la ' . $platformName;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $emailTitle }}</title>
</head>
<body style="margin:0;padding:0;background:#eef4fb;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
    <span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">
        {{ $preheader }}
    </span>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef4fb;margin:0;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:720px;background:#ffffff;border:1px solid #d9e4f2;border-radius:18px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,0.08);">
                    <tr>
                        <td style="background:#0b5ed7;background:linear-gradient(135deg,#0b4fb3,#0d6efd);padding:22px 28px;color:#ffffff;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <div style="background:#ffffff;border-radius:14px;padding:10px 14px;display:inline-block;line-height:0;">
                                            <img src="{{ $logoUrl }}" alt="{{ $platformName }}" width="220" style="display:block;width:220px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;">
                                        </div>
                                    </td>
                                    <td align="right" style="vertical-align:middle;color:#dbeafe;font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">
                                        {{ $periodName }}
                                    </td>
                                </tr>
                            </table>
                            <div style="margin-top:18px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#bfdbfe;">{{ $orgName }}</div>
                            <h1 style="margin:6px 0 0 0;color:#ffffff;font-size:26px;line-height:1.18;font-weight:800;">{{ $emailTitle }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;color:#1e293b;font-size:15px;line-height:1.58;">
                            <div style="font-size:15px;line-height:1.58;color:#1e293b;">
                                @yield('content')
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;line-height:1.5;">
                            <p style="margin:0 0 6px 0;"><strong style="color:#334155;">{{ $platformName }}</strong></p>
                            <p style="margin:0;">Este mensaje fue generado automáticamente. No respondas directamente a este correo.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
