@extends('emails.layouts.institutional')

@section('title', 'Nuevo mensaje interno')
@section('preheader', 'Tienes un nuevo mensaje en la plataforma SGA')

@section('content')
    @php
        $sender = $chatMessage->user;
        $senderName = $senderName ?? (trim(($sender->nombres ?? '') . ' ' . ($sender->apellido_paterno ?? '') . ' ' . ($sender->apellido_materno ?? '')) ?: ($sender->email ?? 'Usuario'));
        $recipientName = isset($recipient) && $recipient
            ? (trim(($recipient->nombres ?? '') . ' ' . ($recipient->apellido_paterno ?? '')) ?: ($recipient->email ?? 'Usuario'))
            : null;
        $conversationUrl = route('messages.show', $chatMessage->conversation_id);
        $plainBody = \App\Support\Messaging\MessageContentSanitizer::plain($chatMessage->body, 600);
        $attachmentsCount = method_exists($chatMessage, 'attachments') ? $chatMessage->attachments()->count() : 0;
    @endphp

    <p style="margin-top:0;">{{ $recipientName ? 'Hola '.$recipientName.',' : 'Hola,' }}</p>
    <p>Tienes un nuevo mensaje interno en la plataforma SGA.</p>

    <div style="border:1px solid #dbe4f0;border-radius:14px;background:#f8fbff;padding:16px 18px;margin:20px 0;">
        <p style="margin:0 0 10px 0;"><strong>De:</strong> {{ $senderName }}</p>
        @if($plainBody !== '')
            <p style="margin:0;white-space:pre-wrap;border-left:4px solid #bfdbfe;padding-left:12px;">{{ $plainBody }}</p>
        @endif
        @if($attachmentsCount > 0)
            <p style="margin:12px 0 0 0;font-size:13px;color:#475569;"><strong>Adjuntos:</strong> {{ $attachmentsCount }} archivo(s). Ingresa a la conversación para revisarlos.</p>
        @endif
    </div>

    @include('emails.partials.cta', ['url' => $conversationUrl, 'text' => 'Abrir conversación'])

    <p style="font-size:12px;color:#64748b;">Enviado {{ cl_datetime($chatMessage->created_at) }}</p>
@endsection
