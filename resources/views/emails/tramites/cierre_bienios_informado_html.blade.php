@php
    $nombreSolicitante = $tramite->nombre_completo_snapshot ?: ($tramite->user->nombre_completo ?? 'usuario/a');
    $platformName = $platformName ?? config('brand.platform_name', 'Plataforma SLEP Andalién Costa');
    $periodName = config('brand.period_name', 'SLEP Andalién Costa 2026');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Trámite de Reconocimiento de Bienios finalizado</title>
</head>
<body style="margin:0;padding:0;background:#eef4fb;color:#0f172a;font-family:Arial,Helvetica,sans-serif;">
    <span style="display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">
        Trámite de Reconocimiento de Bienios finalizado - {{ $platformName }}
    </span>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef4fb;margin:0;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:760px;background:#ffffff;border:1px solid #d9e4f2;border-radius:18px;overflow:hidden;box-shadow:0 18px 45px rgba(15,23,42,0.08);">
                    <tr>
                        <td style="background:#0b5ed7;background:linear-gradient(135deg,#0b4fb3,#0d6efd);padding:22px 28px;color:#ffffff;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="left" style="vertical-align:middle;width:48%;">
                                        <div style="background:#ffffff;border-radius:14px;padding:10px 14px;display:inline-block;line-height:0;">
                                            <img src="{{ $slepLogoUrl }}" alt="SLEP Andalién Costa" width="210" style="display:block;width:210px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;">
                                        </div>
                                    </td>
                                    <td align="right" style="vertical-align:middle;width:48%;">
                                        <div style="background:#ffffff;border-radius:14px;padding:10px 14px;display:inline-block;line-height:0;">
                                            <img src="{{ $sgaLogoUrl }}" alt="Sistema de Gestión Administrativa" width="220" style="display:block;width:220px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;">
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <div style="margin-top:18px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#bfdbfe;">{{ $periodName }}</div>
                            <h1 style="margin:6px 0 0 0;color:#ffffff;font-size:26px;line-height:1.18;font-weight:800;">Trámite de Reconocimiento de Bienios finalizado</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;color:#1e293b;font-size:15px;line-height:1.58;">
                            <p style="margin:0 0 14px 0;">Estimado/a {{ $nombreSolicitante }}:</p>
                            <p style="margin:0 0 14px 0;">Informamos que su <strong>Trámite de Reconocimiento de Bienios</strong> ha sido finalizado.</p>
                            <div style="border-left:5px solid #0d6efd;background:#eff6ff;border-radius:12px;padding:16px 18px;margin:18px 0;color:#1e3a8a;">
                                Posteriormente será cargada la resolución exenta respectiva y en el próximo pago de remuneraciones se verá reflejado el pago correspondiente de bienios.
                            </div>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;margin:20px 0;border:1px solid #dbeafe;border-radius:12px;overflow:hidden;">
                                <tr>
                                    <td style="padding:12px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:700;width:38%;">Trámite</td>
                                    <td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;">Reconocimiento de Bienios</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:700;">N° de trámite</td>
                                    <td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;">#{{ $tramite->id }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:700;">Fecha de reconocimiento</td>
                                    <td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;">{{ $fechaReconocimiento }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:12px 14px;background:#f8fafc;font-weight:700;">Estado informado</td>
                                    <td style="padding:12px 14px;"><span style="display:inline-block;background:#dcfce7;color:#166534;border-radius:999px;padding:4px 10px;font-weight:700;font-size:12px;">Finalizado informado</span></td>
                                </tr>
                            </table>
                            <p style="margin:16px 0 0 0;">Saludos,<br><strong>{{ $platformName }}</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;line-height:1.5;">
                            <p style="margin:0 0 6px 0;"><strong style="color:#334155;">{{ $platformName }}</strong></p>
                            <p style="margin:0;">Este mensaje fue generado automáticamente. No responda directamente a este correo.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
