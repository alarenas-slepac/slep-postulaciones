@extends('emails.layouts.institutional')

@section('title', 'Notificación sobre tu trámite')
@section('preheader', 'Tienes una actualización de trámite')

@section('content')
    @php
        $displayName = $tramite->nombre_completo_snapshot ?: ($tramite->user->nombre_completo ?? $tramite->user->email ?? 'usuario');
    @endphp

    <p style="margin-top:0;">Hola <strong>{{ $displayName }}</strong>,</p>
    <p>Se registró una actualización para tu trámite <strong>#{{ $tramite->id }}</strong> ({{ $tramite->tipo_label }}).</p>

    <div style="border:1px solid #dbe4f0;border-radius:14px;background:#f8fbff;padding:16px 18px;margin:20px 0;">
        <p style="margin:0 0 10px 0;"><strong>Trámite:</strong> #{{ $tramite->id }} · {{ $tramite->tipo_label }}</p>
        <p style="margin:0;"><strong>Estado actual:</strong> {{ $tramite->estado_label }}</p>
    </div>

    <div style="border-left:4px solid #0b5ed7;background:#eff6ff;padding:14px 16px;border-radius:10px;margin:20px 0;">
        <p style="margin:0 0 6px 0;"><strong>Mensaje</strong></p>
        <p style="margin:0;white-space:pre-line;">{{ $messageBody }}</p>
    </div>

    <p>Puedes ingresar al trámite para revisar el expediente y continuar con la gestión que corresponda.</p>
    @include('emails.partials.cta', ['url' => $ctaUrl, 'text' => 'Ir al trámite'])
@endsection
