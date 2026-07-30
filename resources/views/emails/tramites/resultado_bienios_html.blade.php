@extends('emails.layouts.institutional')

@section('title', 'Resultado de reconocimiento de bienios')
@section('preheader', 'Resultado de reconocimiento de bienios - Plataforma SLEP Andalién Costa')

@section('content')
@php
    $externalFlow = $externalFlow ?? (bool) $tramite->bienios_flujo_externo;
    $summary = $summary ?? $tramite->calculo_periodos_resumen;
    $periodos = $periodos ?? $tramite->calculo_periodos_flattened_collection;
@endphp
<p>Hola {{ $tramite->nombre_completo_snapshot ?: ($tramite->user->nombre_completo ?? 'usuario') }},</p>

@if ($externalFlow)
    <p>Tu trámite de <strong>{{ $tramite->tipo_label }}</strong> fue resuelto.</p>
    <p>Se adjuntan los documentos oficiales del resultado:</p>
    <ul>
        <li>Resolución firmada de reconocimiento de bienios.</li>
        <li>Detalle del cómputo administrativo realizado por la unidad responsable.</li>
    </ul>
    <p>La plataforma no genera cálculos preliminares. Los períodos y bienios reconocidos corresponden exclusivamente a lo establecido en la resolución y en el detalle adjunto.</p>
@else
    <p>Tu trámite de <strong>{{ $tramite->tipo_label }}</strong> fue resuelto. Se adjunta la resolución en PDF.</p>
    <p><strong>Resumen del reconocimiento</strong></p>
    <ul>
        <li>Total acumulado: {{ data_get($summary, 'duracion.years', 0) }} años, {{ data_get($summary, 'duracion.months', 0) }} meses y {{ data_get($summary, 'duracion.days', 0) }} días.</li>
        <li>Bienios reconocidos: {{ data_get($summary, 'bienios', 0) }}.</li>
        <li>Fecha de reconocimiento: {{ data_get($data, 'fecha_reconocimiento') ? \Illuminate\Support\Carbon::parse(data_get($data, 'fecha_reconocimiento'))->format('d-m-Y') : '—' }}.</li>
        <li>Fecha de antigüedad: {{ data_get($data, 'fecha_antiguedad_corta') ?: '—' }}.</li>
        <li>Tiempo faltante para el siguiente bienio: {{ data_get($summary, 'duracion_para_siguiente_bienio.years', 0) }} años, {{ data_get($summary, 'duracion_para_siguiente_bienio.months', 0) }} meses y {{ data_get($summary, 'duracion_para_siguiente_bienio.days', 0) }} días.</li>
    </ul>
    <p><strong>Períodos considerados</strong></p>
    <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; font-size: 12px;">
        <thead>
            <tr>
                <th align="left">Inicio</th>
                <th align="left">Término</th>
                <th align="left">Días</th>
                <th align="left">Referencia</th>
                <th align="left">Documento origen</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($periodos as $periodo)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($periodo['inicio'])->format('d-m-Y') }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($periodo['termino'])->format('d-m-Y') }}</td>
                    <td>{{ number_format((int) ($periodo['dias'] ?? 0), 0, ',', '.') }}</td>
                    <td>{{ $periodo['referencia'] ?: '—' }}</td>
                    <td>{{ $periodo['documento_label'] ?? 'Documento' }} · {{ $periodo['documento_nombre'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<p>Saludos,<br>Plataforma SLEP Andalién Costa</p>
@endsection
