@extends('emails.layouts.institutional')

@section('title', 'Solicitud de reemplazo aprobada por UATP')
@section('preheader', 'Solicitud de reemplazo aprobada por UATP - Plataforma SLEP Andalién Costa')

@section('content')
<p>La solicitud <strong>#{{ $s->numero_solicitud }}</strong> fue <strong>aprobada por UATP</strong>.</p>

<p>
    Ahora pasará a <strong>validación de la Subdirección de Planificación y Control de Gestión</strong>, por lo que debe esperar la autorización de dicha unidad para que la solicitud continúe su tramitación por parte de GDP.
</p>

<p>
    <strong>Funcionario:</strong> {{ optional($s->funcionarioTitular)->rut }} - {{ optional($s->funcionarioTitular)->nombre }}<br>
    <strong>Área de desempeño:</strong> {{ optional($s->areaDesempeno)->nombre ?? '—' }}<br>
    <strong>Justificación técnica UATP:</strong><br>
    <span style="white-space: pre-line;">{{ $s->justificacion_tecnica_uatp }}</span>
</p>

<p>Se adjunta un resumen en PDF.</p>
@endsection
