@extends('emails.layouts.institutional')

@section('title', 'Contrato firmado disponible')
@section('preheader', 'Contrato firmado disponible - Plataforma SLEP Andalién Costa')

@section('content')
@php
    $postUser = $s->postulante?->user;
    $periodo = ($s->fecha_inicio?->format('d/m/Y') ?? '—') . ' - ' . ($s->fecha_termino?->format('d/m/Y') ?? '—');
@endphp

<p>Se adjunta el <strong>contrato de trabajo firmado</strong> correspondiente a la solicitud <strong>#{{ $s->numero_solicitud }}</strong>.</p>

<p>
    <strong>Establecimiento:</strong> {{ optional($s->establecimiento)->rbd }} -
    {{ optional($s->establecimiento)->nombre_establecimiento ?? (optional($s->establecimiento)->nombre ?? '—') }}<br>
    <strong>Funcionario titular:</strong> {{ optional($s->funcionarioTitular)->rut }} -
    {{ optional($s->funcionarioTitular)->nombre }}<br>
    <strong>Área de desempeño:</strong> {{ optional($s->areaDesempeno)->nombre ?? '—' }}<br>
    <strong>Postulante asignado:</strong> {{ $postUser?->rut ?? '—' }} — {{ $postUser?->full_name ?? '—' }}<br>
    <strong>Período de la solicitud:</strong> {{ $periodo }}<br>
    <strong>Horas aula titular:</strong> C {{ $s->horas_aula_cronologicas_titular ?? 0 }} / P {{ $s->horas_aula_pedagogicas_titular ?? 0 }}<br>
    <strong>Horas aula reemplazo:</strong> C {{ $s->horas_aula_cronologicas_reemplazo ?? 0 }} / P {{ $s->horas_aula_pedagogicas_reemplazo ?? 0 }}
</p>

<p>Este correo confirma el cierre administrativo de la solicitud y deja disponible el contrato firmado como respaldo adjunto.</p>
@endsection
