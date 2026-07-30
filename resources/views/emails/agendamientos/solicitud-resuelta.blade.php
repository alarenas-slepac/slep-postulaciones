<p>Estimado/a,</p>
<p>Su solicitud de sala/recurso fue <strong>{{ $aprobada ? 'aprobada' : 'rechazada' }}</strong>.</p>
<ul>
    <li><strong>Recurso:</strong> {{ $agendamiento->tipo_recurso_label }}</li>
    <li><strong>Fecha:</strong> {{ $agendamiento->fecha?->format('d-m-Y') }}</li>
    <li><strong>Horario:</strong> {{ $agendamiento->horario }}</li>
    <li><strong>Actividad:</strong> {{ $agendamiento->titulo }}</li>
</ul>
@if(! $aprobada)
<p><strong>Motivo de rechazo:</strong><br>{!! nl2br(e($agendamiento->motivo_rechazo ?: 'No informado')) !!}</p>
@endif
@if(Route::has('gestion.agendamientos-recursos.show'))
<p><a href="{{ route('gestion.agendamientos-recursos.show', $agendamiento) }}">Ver solicitud</a></p>
@endif
<p>Saludos cordiales.</p>
