@extends('emails.layouts.institutional')

@section('title', 'Solicitud de reemplazo rechazada por Planificación')
@section('preheader', 'Solicitud de reemplazo rechazada por Planificación - Plataforma SLEP Andalién Costa')

@section('content')
<p>La solicitud <strong>#{{ $s->numero_solicitud }}</strong> fue <strong>rechazada por la Subdirección de Planificación y Control de Gestión</strong>.</p>

<p><strong>Motivo de rechazo:</strong></p>
<p style="white-space: pre-line;">{{ $s->plani_motivo_rechazo }}</p>

<p>Puede ingresar a la plataforma para revisar el detalle, corregir la solicitud y reenviarla a UATP si corresponde.</p>

<p>
    Acceso:
    <a href="{{ route('funcionario.solicitudes-reemplazo.index') }}">
        Ver mis solicitudes de reemplazo
    </a>
</p>
@endsection
