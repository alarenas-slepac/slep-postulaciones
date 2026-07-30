<p>Estimadas/os,</p>
<p>Se ha registrado una nueva solicitud de sala/recurso pendiente de revisión.</p>
<ul>
    <li><strong>Recurso:</strong> {{ $agendamiento->tipo_recurso_label }}</li>
    <li><strong>Fecha:</strong> {{ $agendamiento->fecha?->format('d-m-Y') }}</li>
    <li><strong>Horario:</strong> {{ $agendamiento->horario }}</li>
    <li><strong>Solicitante:</strong> {{ $agendamiento->solicitante_nombre }}</li>
    <li><strong>Unidad:</strong> {{ $agendamiento->unidad ?: 'No informada' }}</li>
    <li><strong>Actividad:</strong> {{ $agendamiento->titulo }}</li>
</ul>
<p>Ingrese a la plataforma para aprobar o rechazar la solicitud.</p>
@if(Route::has('gestion.agendamientos-recursos.show'))
<p><a href="{{ route('gestion.agendamientos-recursos.show', $agendamiento) }}">Ver solicitud</a></p>
@endif
<p>Saludos cordiales.</p>
