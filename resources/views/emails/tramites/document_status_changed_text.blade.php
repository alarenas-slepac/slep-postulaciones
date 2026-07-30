@php
    $displayName = trim(collect([
        $user->nombres ?? null,
        $user->apellido_paterno ?? null,
        $user->apellido_materno ?? null,
    ])->filter()->implode(' ')) ?: ($user->name ?? $user->email ?? 'usuario');
    $isRejected = $estadoRevision === 'rechazado';
@endphp
Hola {{ $displayName }},

El documento {{ $tipoDocumentoLabel }} del trámite #{{ $tramite->id }} ({{ $tramite->tipo_label }}) fue {{ mb_strtolower($estadoLbl) }}.

Archivo: {{ $documento->original_name }}
Estado: {{ $estadoLbl }}
@if (!empty($observacion))
Observación: {{ $observacion }}
@endif

@if ($isRejected)
Debes ingresar al trámite para revisar la observación y volver a subir el documento corregido.
@else
Puedes ingresar al trámite para revisar el estado actualizado de tu expediente.
@endif

{{ $ctaUrl }}

Mensaje automático de {{ $appName }}.
