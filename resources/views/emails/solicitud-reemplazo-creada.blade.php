@extends('emails.layouts.institutional')

@section('title', 'Solicitud de reemplazo creada')
@section('preheader', 'Solicitud de reemplazo creada - Plataforma SLEP Andalién Costa')

@section('content')
@php
    $estatutoTitular = strtoupper(trim((string) ($s->funcionarioTitular?->estatuto ?? '')));
    $titularEsDocente = in_array($estatutoTitular, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true) || str_contains($estatutoTitular, 'DOC');
@endphp

<p>Se ha realizado la solicitud <strong>#{{ $s->numero_solicitud }}</strong>.</p>
<p>
    <strong>Funcionario:</strong> {{ optional($s->funcionarioTitular)->rut }} -
    {{ optional($s->funcionarioTitular)->nombre }}<br>
    <strong>Área de desempeño:</strong> {{ optional($s->areaDesempeno)->nombre ?? '—' }}
    <br>
    <strong>Tipo de reemplazo:</strong> {{ $s->tipo_reemplazo }}@if ($s->tipo_reemplazo === 'Otras' && $s->tipo_reemplazo_otro)
        ({{ $s->tipo_reemplazo_otro }})
    @endif
    @if ($titularEsDocente)
        <br>
        <strong>Horas aula titular:</strong> C {{ $s->horas_aula_cronologicas_titular ?? 0 }} / P {{ $s->horas_aula_pedagogicas_titular ?? 0 }}<br>
        <strong>Horas aula reemplazo:</strong> C {{ $s->horas_aula_cronologicas_reemplazo ?? 0 }} / P {{ $s->horas_aula_pedagogicas_reemplazo ?? 0 }}
    @endif
</p>

<p>Esta solicitud se encuentra <strong>pendiente de aprobación por parte de UATP</strong>.</p>
<p>Se adjunta un resumen en PDF.</p>
@endsection
