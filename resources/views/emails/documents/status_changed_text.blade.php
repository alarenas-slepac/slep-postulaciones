Estado de tu documento

Hola {{ $user->display_name ?? ($user->name ?? ($user->full_name ?? $user->email)) }},

Hemos revisado tu documento "{{ $typeLabel }}".

Estado: {{ $estadoLbl }}

@if ($status === 'rejected' && filled($reason))
    Motivo del rechazo:
    {{ $reason }}
@endif

Ver mis documentos: {{ $ctaUrl }}

Gracias,
{{ $appName }}
