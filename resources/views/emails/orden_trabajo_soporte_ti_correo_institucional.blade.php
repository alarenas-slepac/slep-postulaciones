@extends('emails.layouts.institutional')

@section('title', 'Solicitud de correo institucional')
@section('preheader', 'Solicitud de correo institucional - Plataforma SLEP Andalién Costa')

@section('content')
@php
    $postUser = $s->postulante?->user;
    $establecimiento = optional($s->establecimiento)->rbd . ' - ' . (optional($s->establecimiento)->nombre_establecimiento ?? optional($s->establecimiento)->nombre ?? '—');
    $titular = trim((string) (optional($s->funcionarioTitular)->nombre ?? ''));
    $titularRut = optional($s->funcionarioTitular)->rut ?? '—';
    $reemplazante = $postUser?->full_name ?? '—';
    $reemplazanteRut = $postUser?->rut ?? '—';
    $inicioTrabajo = $s->fecha_inicio_trabajo ? $s->fecha_inicio_trabajo->format('d/m/Y') : '—';
    $periodo = ($s->fecha_inicio?->format('d/m/Y') ?? '—') . ' al ' . ($s->fecha_termino?->format('d/m/Y') ?? '—');
@endphp

<p>Estimado equipo de Soporte TI,</p>

<p>Junto con saludar, se solicita gestionar la <strong>creación de correo institucional</strong> para el reemplazante asociado a la <strong>Orden de Trabajo N° {{ $s->numero_solicitud }}</strong>.</p>

<p>
    <strong>Establecimiento:</strong> {{ $establecimiento }}<br>
    <strong>Funcionario titular:</strong> {{ $titularRut }} — {{ $titular !== '' ? $titular : '—' }}<br>
    <strong>Área de desempeño:</strong> {{ optional($s->areaDesempeno)->nombre ?? '—' }}<br>
    <strong>Periodo de la solicitud:</strong> {{ $periodo }}<br>
    <strong>Inicio de funciones:</strong> {{ $inicioTrabajo }}<br>
    <strong>Reemplazante:</strong> {{ $reemplazanteRut }} — {{ $reemplazante }}
</p>

<p>Se adjunta la <strong>Orden de Trabajo</strong> correspondiente como respaldo para la habilitación solicitada.</p>

<p>Agradeceremos gestionar esta solicitud y mantener informado al establecimiento en copia para su seguimiento.</p>

<p>Saludos cordiales.</p>
@endsection
