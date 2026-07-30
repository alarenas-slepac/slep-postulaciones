<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .header { width: 100%; border-bottom: 2px solid #0f4c81; padding-bottom: 8px; margin-bottom: 10px; }
        .logo-cell { width: 190px; border: 0; padding: 0; vertical-align: middle; }
        .title-cell { border: 0; padding: 0; vertical-align: middle; text-align: right; }
        .logo { width: 168px; height: auto; }
        .title { font-size: 16px; font-weight: 700; color: #0f4c81; }
        .subtitle { font-size: 10px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 5px; vertical-align: top; }
        th { background: #eff6ff; text-align: left; color: #1f2937; }
        .section { background: #0f4c81; color: white; font-weight: 700; padding: 5px; margin: 8px 0 0; }
        .muted { color: #6b7280; }
        .signature { height: 58px; border: 1px solid #d1d5db; padding: 6px; }
        .validation { border: 1px dashed #6b7280; padding: 6px; font-size: 9px; }
        .validation-table { width: 100%; border-collapse: collapse; margin: 0; }
        .validation-table td { border: 0; padding: 0; vertical-align: middle; }
        .qr { width: 96px; height: 96px; text-align: right; }
        .small { font-size: 9px; }

        .viatico-layout { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .viatico-layout td { border: 0; padding: 0; vertical-align: top; }
        .viatico-left { width: 41%; padding-right: 8px !important; }
        .viatico-right { width: 59%; }
        .viatico-table th { background: #f3f4f6; text-align: center; font-weight: 700; }
        .viatico-table td { text-align: center; }
        .viatico-table td:first-child { text-align: left; }
        .money { text-align: right !important; white-space: nowrap; }
        .viatico-ref { background: #dbeafe; color: #0f172a; font-weight: 700; }
        .viatico-ref td { border-color: #2563eb; }
        .viatico-note { font-size: 9px; color: #374151; margin: 4px 0 6px; }
    </style>
</head>
<body>
@php
    $funcionarioAc = $cometido->funcionarioAcAutorizado;
    $extraerDatoAcPdf = function (?string $observaciones, string $campo): ?string {
        $observaciones = trim((string) $observaciones);
        if ($observaciones === '') {
            return null;
        }

        $patrones = [
            'unidad' => '/Unidad:\s*(.*?)(?:\s+Subdirecci[oó]n dependencia:|\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            'subdireccion_dependencia' => '/Subdirecci[oó]n dependencia:\s*(.*?)(?:\s+Unidad:|\s+Escalaf[oó]n:|\s+Calidad jur[ií]dica:|$)/iu',
            'escalafon' => '/Escalaf[oó]n:\s*(.*?)(?:\s+Unidad:|\s+Subdirecci[oó]n dependencia:|\s+Calidad jur[ií]dica:|$)/iu',
            'calidad_juridica' => '/Calidad jur[ií]dica:\s*(.*?)(?:\s+Unidad:|\s+Subdirecci[oó]n dependencia:|\s+Escalaf[oó]n:|$)/iu',
        ];

        if (! isset($patrones[$campo])) {
            return null;
        }

        if (preg_match($patrones[$campo], $observaciones, $coincidencias)) {
            $valor = trim(preg_replace('/\s+/', ' ', $coincidencias[1] ?? ''));
            return $valor !== '' ? $valor : null;
        }

        return null;
    };

    $observacionesAc = (string) ($funcionarioAc?->observaciones ?? '');
    $escalafon = $funcionarioAc?->escalafon
        ?: $extraerDatoAcPdf($observacionesAc, 'escalafon')
        ?: ($cometido->estamento ?: '—');
    $calidadJuridica = $cometido->calidad_juridica
        ?: $funcionarioAc?->calidad_juridica
        ?: $extraerDatoAcPdf($observacionesAc, 'calidad_juridica')
        ?: '—';
    $grado = $funcionarioAc?->grado ?: '—';
    $telefonoAcPdf = $funcionarioAc?->telefono ?: '—';
    $emailAcPdf = $funcionarioAc?->email ?: ($cometido->solicitante?->email ?: '—');
    $fechaNacimientoAcPdf = ! empty($funcionarioAc?->fecha_nacimiento)
        ? \Illuminate\Support\Carbon::parse($funcionarioAc->fecha_nacimiento)->format('d-m-Y')
        : '—';
@endphp
<div class="header">
    <table style="margin:0; border:0;">
        <tr>
            <td class="logo-cell">
                @if(!empty($logoDataUri))
                    <img class="logo" src="{{ $logoDataUri }}" alt="SLEP Andalién Costa">
                @endif
            </td>
            <td class="title-cell">
                <div class="title">SOLICITUD DE COMETIDO FUNCIONARIO</div>
                <div class="subtitle">Servicio Local de Educación Pública de Andalién Costa</div>
            </td>
        </tr>
    </table>
</div>

<table>
    <tr>
        <th>Fecha de solicitud</th><td>{{ optional($cometido->fecha_solicitud)->format('d-m-Y') ?? now()->format('d-m-Y') }}</td>
        <th>N° Cometido</th><td>{{ $cometido->numero_cometido_interno ?? ('CF-' . $cometido->id) }}</td>
    </tr>
    <tr>
        <th>Origen</th><td>{{ $cometido->origen_cometido === 'administracion_central' ? 'Administración Central' : 'Establecimiento' }}</td>
        <th>Estado</th><td>{{ method_exists($cometido, 'etiquetaEstado') ? $cometido->etiquetaEstado() : $cometido->estado }}</td>
    </tr>
</table>

<div class="section">1. Detalle del funcionario</div>
<table>
    <tr><th>Nombre</th><td>{{ $cometido->funcionario_nombre }}</td><th>RUT</th><td>{{ $cometido->funcionario_rut }}</td></tr>
    <tr><th>Calidad jurídica</th><td>{{ $calidadJuridica }}</td><th>Estamento / escalafón</th><td>{{ $escalafon }}</td></tr>
    <tr><th>Grado</th><td>{{ $grado }}</td><th>Unidad</th><td>{{ $cometido->unidad_departamento_ac ?: ($cometido->establecimiento->nombre_establecimiento ?? '—') }}</td></tr>
    <tr><th>Subdirección dependencia</th><td colspan="3">{{ $cometido->subdireccion_dependencia_ac ?: '—' }}</td></tr>
    @if($cometido->esAdministracionCentral())
        <tr><th>Teléfono</th><td>{{ $telefonoAcPdf }}</td><th>Correo electrónico</th><td>{{ $emailAcPdf }}</td></tr>
        <tr><th>Fecha de nacimiento</th><td colspan="3">{{ $fechaNacimientoAcPdf }}</td></tr>
    @endif
</table>

<div class="section">2. Detalle del viaje</div>
<table>
    <tr><th>Origen</th><td>{{ $cometido->comuna_origen_nombre ?: '—' }}</td><th>Destino</th><td>{{ $cometido->es_destino_extranjero ? trim(($cometido->pais_destino ?: '') . ' - ' . ($cometido->ciudad_destino_extranjero ?: ''), ' -') : ($cometido->comuna_destino_nombre ?: $cometido->destino) }}</td></tr>
    <tr><th>Institución / lugar</th><td colspan="3">{{ trim(($cometido->institucion_destino ?: '') . ' - ' . ($cometido->destino ?: ''), ' -') }}</td></tr>
    <tr><th>Fecha desde</th><td>{{ optional($cometido->fecha_desde)->format('d-m-Y') }}</td><th>Fecha hasta</th><td>{{ optional($cometido->fecha_hasta)->format('d-m-Y') }}</td></tr>
    <tr><th>Hora salida</th><td>{{ $cometido->hora_salida }}</td><th>Hora regreso</th><td>{{ $cometido->hora_regreso }}</td></tr>
</table>

<div class="section">3. Transporte, viático y devolución de gastos</div>
<table>
    <tr><th>Medios de transporte</th><td colspan="3">{{ implode(', ', (array) $cometido->medios_transporte) }} {{ $cometido->medio_transporte_otro ? ' - ' . $cometido->medio_transporte_otro : '' }}</td></tr>
    <tr><th>Derecho a viático</th><td>{{ $cometido->solicita_viatico ? 'Sí' : 'No' }}</td><th>Alojamiento</th><td>{{ $cometido->contempla_alojamiento ? 'Con alojamiento' : 'Sin alojamiento / No aplica' }}</td></tr>
    <tr><th>Servicio contempla colación</th><td colspan="3">{{ ['si' => 'Sí, aplica valor 60%', 'no' => 'No', 'no_informado' => 'No informado'][$cometido->servicio_contempla_colacion ?? 'no_informado'] ?? 'No informado' }}</td></tr>
    <tr><th>Anticipo de viático</th><td colspan="3">{{ $cometido->solicita_anticipo_viatico ? ('Sí, 60%: $' . number_format((int) ($cometido->monto_anticipo_viatico ?? 0), 0, ',', '.') . ' / Saldo: $' . number_format((int) ($cometido->monto_saldo_viatico ?? 0), 0, ',', '.')) : 'No' }}</td></tr>
    <tr><th>Solicita devolución de gastos</th><td>{{ $cometido->solicita_reembolso ? 'Sí' : 'No' }}</td><th>Pasaje aéreo</th><td>{{ $cometido->requiere_pasaje_aereo ? 'Sí' : 'No' }}</td></tr>
    @if($cometido->requiere_pasaje_aereo)
        @php
            $tiposPasajePdf = [
                'solo_ida' => 'Solo ida',
                'solo_regreso' => 'Solo regreso',
                'ida_y_regreso' => 'Ida y regreso',
            ];
            $tipoPasajePdf = $tiposPasajePdf[$cometido->tipo_pasaje_aereo] ?? 'No informado';
        @endphp
        <tr><th>Tipo de pasaje aéreo requerido</th><td colspan="3">{{ $tipoPasajePdf }}</td></tr>
    @endif
    @if($cometido->requiere_pasaje_aereo && $cometido->dias_habiles_anticipacion !== null)
        <tr><th>Días hábiles de anticipación</th><td>{{ $cometido->dias_habiles_anticipacion }}</td><th>Justificación fuera de plazo</th><td>{{ $cometido->justificacion_menor_7_dias ?: 'No aplica' }}</td></tr>
    @endif
</table>

@if(!empty($viaticoPdfData['mostrar']))
    <div class="section">4. Cuadro de viático y referencia de cálculo</div>
    <div class="viatico-note">{{ $viaticoPdfData['nota'] }}</div>
    @if(!empty($viaticoPdfData['categoria']) && $viaticoPdfData['categoria'] !== '—' && empty($viaticoPdfData['es_reembolso']))
        <div class="viatico-note"><strong>Valor referencial aplicado:</strong> {{ $viaticoPdfData['categoria'] }}</div>
    @endif
    <table class="viatico-layout">
        <tr>
            <td class="viatico-left">
                <table class="viatico-table">
                    <tr>
                        <th>Tipo de Viático</th>
                        <th>Días</th>
                        <th>TOTAL</th>
                    </tr>
                    <tr>
                        <td>Días con pernoctar</td>
                        <td>{{ (int) ($viaticoPdfData['dias_con_pernoctar'] ?? 0) }}</td>
                        <td class="money">${{ number_format((int) ($viaticoPdfData['total_con_pernoctar'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Días sin pernoctar</td>
                        <td>{{ (int) ($viaticoPdfData['dias_sin_pernoctar'] ?? 0) }}</td>
                        <td class="money">${{ number_format((int) ($viaticoPdfData['total_sin_pernoctar'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Servicio contempla colación</td>
                        <td>{{ (int) ($viaticoPdfData['dias_servicio_colacion'] ?? 0) }}</td>
                        <td class="money">${{ number_format((int) ($viaticoPdfData['total_servicio_colacion'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Solo alojamiento</td>
                        <td>{{ (int) ($viaticoPdfData['dias_solo_alojamiento'] ?? 0) }}</td>
                        <td class="money">${{ number_format((int) ($viaticoPdfData['total_solo_alojamiento'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td></td>
                        <td class="money"><strong>${{ number_format((int) ($viaticoPdfData['total'] ?? 0), 0, ',', '.') }}</strong></td>
                    </tr>
                </table>
            </td>
            <td class="viatico-right">
                <table class="viatico-table">
                    <tr>
                        <th>Rango viáticos</th>
                        <th>Con Pernoctar<br>(100%)</th>
                        <th>Servicio con colación<br>(60%)</th>
                        <th>Alimentación<br>(40%)</th>
                    </tr>
                    @foreach(($viaticoPdfData['rangos'] ?? []) as $rango)
                        @php
                            $esFilaReferencial = empty($viaticoPdfData['es_reembolso'])
                                && !empty($viaticoPdfData['cargo_funcion_referencial'])
                                && (string) $viaticoPdfData['cargo_funcion_referencial'] === (string) ($rango['rango'] ?? '');
                        @endphp
                        <tr class="{{ $esFilaReferencial ? 'viatico-ref' : '' }}">
                            <td>{{ $rango['rango'] }}</td>
                            <td class="money">${{ number_format((int) ($rango['valor_100'] ?? 0), 0, ',', '.') }}</td>
                            <td class="money">${{ number_format((int) ($rango['valor_60'] ?? 0), 0, ',', '.') }}</td>
                            <td class="money">${{ number_format((int) ($rango['valor_40'] ?? 0), 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
@endif

<div class="section">4. Propósito / motivo</div>
<table>
    <tr><th>Motivo</th><td>{{ $cometido->motivo }} {{ $cometido->motivo_otro ? ' - ' . $cometido->motivo_otro : '' }}</td></tr>
    <tr><th>Descripción de actividades</th><td>{{ $cometido->descripcion_actividades }}</td></tr>
</table>

<div class="section">6. Declaración y firmas electrónicas internas</div>
<p class="small">Declaro que la información ingresada en la solicitud de cometido funcionario es fidedigna, completa y corresponde a los antecedentes conocidos por el solicitante, y que el cometido responde a una necesidad institucional.</p>
<table>
    <tr>
        @foreach($documento->firmas as $firma)
            <td class="signature">
                <strong>{{ $firma->tipo_firma === 'solicitante' ? 'Firmado electrónicamente por' : 'Autorizado electrónicamente por' }}:</strong><br>
                {{ $firma->nombre_firmante }}<br>
                RUN: {{ $firma->rut_firmante ?: '—' }}<br>
                Dependencia: {{ $firma->dependencia_firmante ?: '—' }}<br>
                {{ $firma->es_subrogante ? 'Autoriza en calidad de subrogante activo' : '' }}<br>
                Fecha: {{ optional($firma->fecha_firma)->format('d-m-Y H:i') }}
            </td>
        @endforeach
        @if($documento->firmas->isEmpty())
            <td class="signature">Documento generado sin firmas registradas.</td>
        @endif
    </tr>
</table>

<div class="validation">
    <table class="validation-table">
        <tr>
            <td>
                <strong>Código de validación:</strong> {{ $documento->codigo_validacion }}<br>
                <strong>Validación documental:</strong> {{ $validacionUrl }}<br>
                <strong>Hash documento:</strong> {{ $documento->documento_hash ?: 'se genera al emitir PDF' }}<br>
                <span class="muted">Escanee el QR o ingrese el código de validación en la plataforma para comprobar autenticidad y vigencia del documento.</span>
            </td>
            <td class="qr">
                @if(!empty($qrDataUri))
                    <img src="{{ $qrDataUri }}" width="96" height="96" alt="QR validación documental">
                @else
                    <strong>QR no disponible</strong><br>Use el código de validación.
                @endif
            </td>
        </tr>
    </table>
</div>
</body>
</html>
