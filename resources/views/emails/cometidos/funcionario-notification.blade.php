@extends('emails.layouts.institutional')

@section('title', $title)
@section('preheader', $cometido ? 'Cometido funcionario ' . ($cometido->numero_cometido_interno ?? ('#' . $cometido->id)) : 'Notificación de cometido funcionario')

@section('content')
@php
    $numero = $cometido?->numero_cometido_interno ?: ($cometido ? '#' . $cometido->id : null);
    $estado = $cometido?->etiquetaEstado() ?? null;
    $pasaje = $cometido?->pasajeAereo?->sortByDesc('id')->first();
    $estadoPasaje = $pasaje?->estado_pasaje ? str_replace('_', ' ', $pasaje->estado_pasaje) : null;
    $destino = $cometido
        ? ($cometido->es_destino_extranjero
            ? trim(($cometido->ciudad_destino_extranjero ?: '') . ($cometido->pais_destino ? ', ' . $cometido->pais_destino : ''))
            : ($cometido->comuna_destino_nombre ?: $cometido->destino))
        : null;
    $origen = $cometido?->comuna_origen_nombre ?: ($cometido?->establecimiento?->nombre_establecimiento ?? 'Administración Central');
    $fechaDesde = $cometido?->fecha_desde ? $cometido->fecha_desde->format('d-m-Y') : null;
    $fechaHasta = $cometido?->fecha_hasta ? $cometido->fecha_hasta->format('d-m-Y') : null;
    $tieneAdjuntos = ! empty($attachmentPack ?? null);
    $esExpedienteCompleto = in_array((string) ($attachmentPack ?? ''), ['expediente_completo', 'informe_cometido', 'rendicion_lista', 'daf_contable', 'pago_registrado'], true);
    $funcionarioAcCorreo = $cometido?->funcionarioAcAutorizado;
    $telefonoCorreo = $funcionarioAcCorreo?->telefono ?: null;
    $emailCorreo = $funcionarioAcCorreo?->email ?: ($cometido?->solicitante?->email ?? null);
    $fechaNacimientoCorreo = ! empty($funcionarioAcCorreo?->fecha_nacimiento)
        ? \Illuminate\Support\Carbon::parse($funcionarioAcCorreo->fecha_nacimiento)->format('d-m-Y')
        : null;
@endphp

<p style="margin:0 0 8px 0;font-size:18px;font-weight:800;color:#0f172a;">Hola {{ $recipientName ?: 'usuario/a' }}</p>

@if (!empty($badgeText))
    <div style="display:inline-block;margin:0 0 18px 0;padding:7px 12px;border-radius:999px;background:#dbeafe;color:#1e40af;font-size:12px;font-weight:800;letter-spacing:.03em;text-transform:uppercase;">
        {{ $badgeText }}
    </div>
@endif

@foreach ($messageLines as $line)
    @if (trim($line) !== '')
        <p style="margin:0 0 12px 0;color:#334155;font-size:15px;line-height:1.6;">{{ $line }}</p>
    @endif
@endforeach


@if ($tieneAdjuntos)
    <div style="margin:18px 0;padding:14px 16px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:12px;color:#1e3a8a;font-size:14px;line-height:1.55;">
        <strong>Documentos adjuntos:</strong>
        @if($esExpedienteCompleto)
            este correo incorpora el expediente documental consolidado disponible a la fecha, incluyendo solicitud, documentos generados, informe de cometido, respaldos de rendición, documentos contables y comprobantes de pago cuando existan.
        @else
            este correo incorpora el expediente documental correspondiente a la etapa del trámite, incluyendo el cometido firmado, citación o invitación, documentos complementarios y, cuando corresponda, solicitud de pedido de pasaje, reserva, CDP y boleto/respaldo.
        @endif
    </div>
@endif

@if ($cometido)
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0;border:1px solid #dbe7f3;border-radius:14px;overflow:hidden;background:#ffffff;">
        <tr>
            <td colspan="2" style="background:#0f4c81;color:#ffffff;padding:13px 16px;font-size:15px;font-weight:800;">
                Resumen del cometido {{ $numero }}
            </td>
        </tr>
        <tr>
            <td style="width:34%;padding:11px 14px;background:#f1f6fb;border-bottom:1px solid #e2e8f0;color:#334155;font-weight:700;">Funcionario/a</td>
            <td style="padding:11px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">{{ $cometido->funcionario_nombre ?: 'No informado' }}</td>
        </tr>
        <tr>
            <td style="padding:11px 14px;background:#f1f6fb;border-bottom:1px solid #e2e8f0;color:#334155;font-weight:700;">RUN</td>
            <td style="padding:11px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">{{ $cometido->funcionario_rut ?: 'No informado' }}</td>
        </tr>
        @if ($cometido->esAdministracionCentral() && ($telefonoCorreo || $emailCorreo || $fechaNacimientoCorreo))
            <tr>
                <td style="padding:11px 14px;background:#f1f6fb;border-bottom:1px solid #e2e8f0;color:#334155;font-weight:700;">Contacto funcionario AC</td>
                <td style="padding:11px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">
                    Teléfono: <strong>{{ $telefonoCorreo ?: 'No informado' }}</strong> · Correo: <strong>{{ $emailCorreo ?: 'No informado' }}</strong>@if($fechaNacimientoCorreo) · Fecha nacimiento: <strong>{{ $fechaNacimientoCorreo }}</strong>@endif
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding:11px 14px;background:#f1f6fb;border-bottom:1px solid #e2e8f0;color:#334155;font-weight:700;">Estado actual</td>
            <td style="padding:11px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">{{ $estado ?: 'No informado' }}</td>
        </tr>
        <tr>
            <td style="padding:11px 14px;background:#f1f6fb;border-bottom:1px solid #e2e8f0;color:#334155;font-weight:700;">Fechas</td>
            <td style="padding:11px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">{{ $fechaDesde ?: 'No informada' }} @if ($fechaHasta && $fechaHasta !== $fechaDesde) al {{ $fechaHasta }} @endif</td>
        </tr>
        <tr>
            <td style="padding:11px 14px;background:#f1f6fb;border-bottom:1px solid #e2e8f0;color:#334155;font-weight:700;">Origen / destino</td>
            <td style="padding:11px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">{{ $origen ?: 'No informado' }} → {{ $destino ?: 'No informado' }}</td>
        </tr>
        <tr>
            <td style="padding:11px 14px;background:#f1f6fb;border-bottom:1px solid #e2e8f0;color:#334155;font-weight:700;">Gasto asociado</td>
            <td style="padding:11px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">
                Derecho a viático: <strong>{{ $cometido->solicita_viatico ? 'Sí' : 'No' }}</strong> · Anticipo: <strong>{{ $cometido->solicita_anticipo_viatico ? ('$' . number_format((int) ($cometido->monto_anticipo_viatico ?? 0), 0, ',', '.')) : 'No' }}</strong> · Devolución/reembolso: <strong>{{ $cometido->solicita_reembolso ? 'Sí' : 'No' }}</strong> · Pasaje aéreo: <strong>{{ $cometido->requiere_pasaje_aereo ? 'Sí' : 'No' }}</strong>@if($cometido->requiere_pasaje_aereo) · Tipo pasaje: <strong>{{ ['solo_ida' => 'Solo ida', 'solo_regreso' => 'Solo regreso', 'ida_y_regreso' => 'Ida y regreso'][$cometido->tipo_pasaje_aereo] ?? 'No informado' }}</strong>@endif
            </td>
        </tr>
        @if ($cometido->estado_viatico || $cometido->estado_reembolso)
            <tr>
                <td style="padding:11px 14px;background:#f1f6fb;border-bottom:1px solid #e2e8f0;color:#334155;font-weight:700;">Estados financieros</td>
                <td style="padding:11px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;">
                    Viático: <strong>{{ $cometido->estado_viatico ? \Illuminate\Support\Str::headline(str_replace('_', ' ', $cometido->estado_viatico)) : 'No aplica' }}</strong> ·
                    Reembolso: <strong>{{ $cometido->estado_reembolso ? \Illuminate\Support\Str::headline(str_replace('_', ' ', $cometido->estado_reembolso)) : 'No aplica' }}</strong>
                </td>
            </tr>
        @endif
        @if ($estadoPasaje)
            <tr>
                <td style="padding:11px 14px;background:#f1f6fb;color:#334155;font-weight:700;">Estado pasaje</td>
                <td style="padding:11px 14px;color:#0f172a;text-transform:capitalize;">{{ $estadoPasaje }}</td>
            </tr>
        @endif
    </table>
@endif

@if (!empty($actionUrl))
    @include('emails.partials.cta', ['url' => $actionUrl, 'text' => $actionText ?: 'Ver cometido en la plataforma'])
@endif

@if (!empty($footerNote))
    <div style="margin:22px 0 0 0;padding:14px 16px;border-left:4px solid #0d6efd;background:#eff6ff;color:#1e3a8a;border-radius:10px;font-size:14px;line-height:1.55;">
        {{ $footerNote }}
    </div>
@endif

<p style="margin:24px 0 0 0;color:#475569;">
    Saludos cordiales,<br>
    {{ config('brand.platform_name', 'Plataforma SLEP Andalién Costa') }}
</p>
@endsection
