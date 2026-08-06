@extends('emails.layouts.institutional')
@section('title', $evento === 'escalamiento' ? 'Ticket de incidencia vencido' : 'Nuevo ticket de incidencia')
@section('preheader', 'Gestión de incidencias · SLEP Andalién Costa')
@section('content')
<p>{{ $evento === 'escalamiento' ? 'El plazo de resolución del siguiente ticket ha vencido y requiere atención de la subdirección.' : 'Se le ha asignado un nuevo ticket de incidencia.' }}</p>
<p><strong>Ticket:</strong> {{ $ticket->numero }}<br><strong>Incidencia:</strong> {{ config("centro_operaciones.incidencias.{$ticket->incidencia->tipo}.label", $ticket->incidencia->tipo) }}<br><strong>Establecimiento:</strong> {{ $ticket->incidencia->establecimiento?->nombre_establecimiento ?? '—' }}<br><strong>Unidad responsable:</strong> {{ $ticket->unidad_departamento }}<br><strong>Fecha límite:</strong> {{ $ticket->vence_en->format('d/m/Y H:i') }}</p>
<p>Se adjunta el detalle del ticket en PDF.</p>
@endsection
