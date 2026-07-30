@extends('emails.layouts.institutional')

@section('title', 'Solicitud de reemplazo rechazada por UATP')
@section('preheader', 'Solicitud de reemplazo rechazada por UATP - Plataforma SLEP Andalién Costa')

@section('content')
<p>La solicitud <strong>#{{ $s->numero_solicitud }}</strong> fue <strong>rechazada por UATP</strong>.</p>

<p>
    <strong>Tipo de reemplazo:</strong> {{ $s->tipo_reemplazo }}@if ($s->tipo_reemplazo === 'Otras' && $s->tipo_reemplazo_otro)
        ({{ $s->tipo_reemplazo_otro }})
    @endif
    <strong>Horas aula titular:</strong> C {{ $s->horas_aula_cronologicas_titular ?? 0 }} / P {{ $s->horas_aula_pedagogicas_titular ?? 0 }}<br>
    <strong>Horas aula reemplazo:</strong> C {{ $s->horas_aula_cronologicas_reemplazo ?? 0 }} / P {{ $s->horas_aula_pedagogicas_reemplazo ?? 0 }}
</p>

<p><strong>Motivo de rechazo:</strong></p>
<p style="white-space: pre-line;">{{ $s->motivo_rechazo }}</p>

<p>Ingresa a la plataforma para revisar el detalle y, si corresponde, volver a ingresar una solicitud corregida.</p>

<p>
    Acceso:
    <a href="{{ route('funcionario.solicitudes-reemplazo.index') }}">
        Ver mis solicitudes de reemplazo
    </a>
</p>
@endsection
