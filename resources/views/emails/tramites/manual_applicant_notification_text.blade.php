Hola {{ $tramite->nombre_completo_snapshot ?: ($tramite->user->nombre_completo ?? $tramite->user->email ?? 'usuario') }},

Se registró una observación para tu trámite #{{ $tramite->id }} ({{ $tramite->tipo_label }}).
Estado actual: {{ $tramite->estado_label }}

Mensaje:
{{ $messageBody }}

Puedes revisar tu trámite en:
{{ $ctaUrl }}

Mensaje automático de {{ $appName }}.
