@php
    $esAnulacion = ($tipo ?? '') === 'anulacion';
    $fechaActual = $agendamiento->fecha?->format('d-m-Y');
    $horaActual = $agendamiento->horario;
    $fechaAnterior = !empty($datosOriginales['fecha']) ? \Carbon\Carbon::parse($datosOriginales['fecha'])->format('d-m-Y') : null;
    $horaAnterior = trim(($datosOriginales['hora_inicio'] ?? '') . ' - ' . ($datosOriginales['hora_termino'] ?? ''));
@endphp

<p>Estimadas/os,</p>

@if($esAnulacion)
    <p>Se informa que fue anulada una reserva asociada a la sala/recurso administrado, por lo que el horario indicado queda disponible para nuevas solicitudes.</p>
@else
    <p>Se informa que una reserva asociada a la sala/recurso administrado tuvo modificación de fecha u horario.</p>
@endif

<table style="border-collapse: collapse; width: 100%; max-width: 680px;" cellpadding="6" cellspacing="0" border="1">
    <tr>
        <th align="left">Sala/Recurso</th>
        <td>{{ $agendamiento->tipo_recurso_label }}</td>
    </tr>
    <tr>
        <th align="left">Actividad</th>
        <td>{{ $agendamiento->titulo }}</td>
    </tr>
    <tr>
        <th align="left">Solicitante</th>
        <td>{{ $agendamiento->solicitante_nombre ?: optional($agendamiento->solicitante)->nombre_completo ?: optional($agendamiento->solicitante)->email ?: 'No informado' }}</td>
    </tr>
    @if($fechaAnterior || $horaAnterior !== ' -')
        <tr>
            <th align="left">Fecha/Horario anterior</th>
            <td>{{ $fechaAnterior ?: 'No informado' }} {{ $horaAnterior !== ' -' ? $horaAnterior : '' }}</td>
        </tr>
    @endif
    <tr>
        <th align="left">Fecha/Horario actual</th>
        <td>{{ $fechaActual }} {{ $horaActual }}</td>
    </tr>
    <tr>
        <th align="left">Estado</th>
        <td>{{ $agendamiento->estado_label }}</td>
    </tr>
    @if($esAnulacion && $agendamiento->motivo_anulacion)
        <tr>
            <th align="left">Motivo anulación</th>
            <td>{{ $agendamiento->motivo_anulacion }}</td>
        </tr>
    @endif
</table>

<p>Este aviso se genera automáticamente para mantener informado al administrador de sala respecto de la disponibilidad y cambios de agenda.</p>

<p>Saludos cordiales.</p>
