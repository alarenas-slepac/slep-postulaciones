@extends('emails.layouts.institutional')
@section('title', $evento === 'escalamiento' ? 'Ticket de incidencia vencido' : 'Nuevo ticket de incidencia')
@section('preheader', 'Gestión de incidencias · SLEP Andalién Costa')
@section('content')
<p>{{ $evento === 'escalamiento' ? 'El plazo de resolución del siguiente ticket ha vencido y requiere atención de la subdirección.' : 'Se le ha asignado un nuevo ticket de incidencia.' }}</p>
<p><strong>Ticket:</strong> {{ $ticket->numero }}<br><strong>Incidencia:</strong> {{ $ticket->incidencia->tipo_label }}<br><strong>Establecimiento:</strong> {{ $ticket->incidencia->establecimiento?->nombre_establecimiento ?? '—' }}<br><strong>Unidad responsable:</strong> {{ $ticket->unidad_departamento }}<br><strong>Responsable principal:</strong> {{ $ticket->responsable?->nombre_completo ?? '—' }}<br><strong>Segundo responsable:</strong> {{ $ticket->segundoResponsable?->nombre_completo ?? 'No asignado' }}<br><strong>Fecha límite:</strong> {{ $ticket->vence_en?->format('d/m/Y H:i') ?? 'Pendiente de asignación' }}</p>
<p>Se adjunta el detalle del ticket en PDF.</p>
@endsection
