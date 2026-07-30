@extends('emails.layouts.institutional')

@section('title', 'Solicitud de reemplazo validada por Planificación')
@section('preheader', 'Solicitud de reemplazo validada por Planificación - Plataforma SLEP Andalién Costa')

@section('content')
<p>La solicitud <strong>#{{ $s->numero_solicitud }}</strong> fue <strong>validada por la Subdirección de Planificación y Control de Gestión</strong>.</p>

<p>
    La solicitud continuará ahora su tramitación por parte de <strong>GDP</strong>.
</p>

<p>
    <strong>Funcionario:</strong> {{ optional($s->funcionarioTitular)->rut }} - {{ optional($s->funcionarioTitular)->nombre }}<br>
    <strong>Área de desempeño:</strong> {{ optional($s->areaDesempeno)->nombre ?? '—' }}
</p>

<p>Se adjunta un resumen en PDF.</p>
@endsection
