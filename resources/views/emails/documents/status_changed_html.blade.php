@extends('emails.layouts.institutional')

@section('title', 'Estado de tu documento')
@section('preheader', 'Tu documento fue revisado en la Plataforma SLEP Andalién Costa')

@section('content')
    @php
        $displayName = $user->display_name ?? ($user->name ?? ($user->full_name ?? $user->email));
        $statusTone = $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning');
        $statusText = $status === 'approved' ? 'Aprobado' : ($status === 'rejected' ? 'Rechazado' : 'Pendiente');
    @endphp

    <p style="margin-top:0;">Hola <strong>{{ $displayName }}</strong>,</p>
    <p>Hemos revisado tu documento <strong>{{ $typeLabel }}</strong>.</p>

    <div style="border:1px solid #dbe4f0;border-radius:14px;background:#f8fbff;padding:16px 18px;margin:20px 0;">
        <p style="margin:0 0 10px 0;"><strong>Documento:</strong> {{ $typeLabel }}</p>
        <p style="margin:0;"><strong>Estado:</strong> @include('emails.partials.status', ['text' => $statusText, 'tone' => $statusTone])</p>
    </div>

    @if ($status === 'rejected' && filled($reason))
        <div style="border-left:4px solid #842029;background:#fff1f2;padding:14px 16px;border-radius:10px;margin:20px 0;">
            <p style="margin:0 0 6px 0;"><strong>Motivo del rechazo</strong></p>
            <p style="margin:0;white-space:pre-line;">{{ $reason }}</p>
        </div>
    @endif

    @include('emails.partials.cta', ['url' => $ctaUrl, 'text' => 'Ver mis documentos'])
@endsection
