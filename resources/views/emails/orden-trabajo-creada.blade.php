@extends('emails.layouts.institutional')

@section('title', 'Orden de trabajo creada')
@section('preheader', 'Orden de trabajo creada - Plataforma SLEP Andalién Costa')

@section('content')
@php
    $postUser = $s->postulante?->user;
    $inicioTrabajo = $s->fecha_inicio_trabajo ? $s->fecha_inicio_trabajo->format('d/m/Y') : '—';
    $periodo = ($s->fecha_inicio?->format('d/m/Y') ?? '—') . ' - ' . ($s->fecha_termino?->format('d/m/Y') ?? '—');
@endphp

<p>Se ha creado una <strong>Orden de Trabajo</strong> para la solicitud <strong>#{{ $s->numero_solicitud }}</strong>.
</p>

<p>
    <strong>Establecimiento:</strong> {{ optional($s->establecimiento)->rbd }} -
    {{ optional($s->establecimiento)->nombre_establecimiento ?? (optional($s->establecimiento)->nombre ?? '—') }}<br>
    <strong>Funcionario titular:</strong> {{ optional($s->funcionarioTitular)->rut }} -
    {{ optional($s->funcionarioTitular)->nombre }}<br>
    <strong>Área de desempeño:</strong> {{ optional($s->areaDesempeno)->nombre ?? '—' }}<br>
	<strong>Tipo de reemplazo:</strong> {{ $s->tipo_reemplazo }}@if ($s->tipo_reemplazo === 'Otras' && $s->tipo_reemplazo_otro)
	    ({{ $s->tipo_reemplazo_otro }})
	@endif
	<br>
    <strong>Periodo de la solicitud:</strong> {{ $periodo }}<br>
    <strong>Horas aula titular:</strong> C {{ $s->horas_aula_cronologicas_titular ?? 0 }} / P {{ $s->horas_aula_pedagogicas_titular ?? 0 }}<br>
    <strong>Horas aula reemplazo:</strong> C {{ $s->horas_aula_cronologicas_reemplazo ?? 0 }} / P {{ $s->horas_aula_pedagogicas_reemplazo ?? 0 }}
</p>

<p>
    <strong>Postulante:</strong> {{ $postUser?->rut ?? '—' }} — {{ $postUser?->full_name ?? '—' }}<br>
    <strong>Puede comenzar a trabajar desde:</strong> {{ $inicioTrabajo }}
</p>

<p>Se adjunta un resumen en PDF.</p>

@if (!empty($s->contrato_trabajo_docx_path))
    <p>
        También se adjunta el <strong>Contrato de Trabajo (Word)</strong>.
        <br>
        <strong>Instrucción:</strong> imprimir y firmar <strong>2 copias</strong> del contrato (una del trabajador y
        otra para el SLEP), luego dirigirse con encargada de Reemplazos Jocelyn Bobadilla en segundo piso del SLEP
        (Gestión de Personas) y entregar copias firmadas por trabajador.
    </p>
@endif
@endsection
