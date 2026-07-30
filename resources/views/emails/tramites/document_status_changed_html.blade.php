@extends('emails.layouts.institutional')

@section('title', 'Estado de documento de trámite')
@section('preheader', 'Tu documento de trámite fue revisado')

@section('content')
    @php
        $displayName = trim(collect([
            $user->nombres ?? null,
            $user->apellido_paterno ?? null,
            $user->apellido_materno ?? null,
        ])->filter()->implode(' ')) ?: ($user->name ?? $user->email ?? 'usuario');
        $isRejected = $estadoRevision === 'rechazado';
    @endphp

    <p style="margin-top:0;">Hola <strong>{{ $displayName }}</strong>,</p>
    <p>
        El documento <strong>{{ $tipoDocumentoLabel }}</strong> del trámite
        <strong>#{{ $tramite->id }}</strong> ({{ $tramite->tipo_label }}) fue revisado.
    </p>

    <div style="border:1px solid #dbe4f0;border-radius:14px;background:#f8fbff;padding:16px 18px;margin:20px 0;">
        <p style="margin:0 0 10px 0;"><strong>Documento:</strong> {{ $tipoDocumentoLabel }}</p>
        <p style="margin:0 0 10px 0;"><strong>Estado:</strong> @include('emails.partials.status', ['text' => $estadoLbl, 'tone' => $isRejected ? 'danger' : 'success'])</p>
        <p style="margin:0;"><strong>Archivo:</strong> {{ $documento->original_name }}</p>
    </div>

    @if (!empty($observacion))
        <div style="border-left:4px solid {{ $isRejected ? '#842029' : '#0f5132' }};background:{{ $isRejected ? '#fff1f2' : '#e8f5ee' }};padding:14px 16px;border-radius:10px;margin:20px 0;">
            <p style="margin:0 0 6px 0;"><strong>Observación del revisor</strong></p>
            <p style="margin:0;white-space:pre-line;">{{ $observacion }}</p>
        </div>
    @endif

    @if ($isRejected)
        <p>Ingresa al trámite para revisar la observación y volver a subir el documento corregido.</p>
    @else
        <p>Puedes ingresar al trámite para revisar el estado actualizado de tu expediente.</p>
    @endif

    @include('emails.partials.cta', ['url' => $ctaUrl, 'text' => 'Ir al trámite'])
@endsection
