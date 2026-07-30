Tu trámite #{{ $tramite->id }} ({{ $tramite->tipo_label }}) fue anulado.
Motivo: {{ $tramite->anulado_motivo ?: 'Sin motivo informado.' }}
@if($tramite->anulado_at)
Fecha: {{ $tramite->anulado_at->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') }}
@endif
