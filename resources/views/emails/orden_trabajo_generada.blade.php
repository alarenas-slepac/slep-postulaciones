@extends('emails.layouts.institutional')

@section('title', 'Orden de trabajo generada')
@section('preheader', 'Orden de trabajo generada - Plataforma SLEP Andalién Costa')

@section('content')
@php
    $postUser = $s->postulante?->user;
    $postName = $postUser?->display_name ?? ($postUser?->full_name ?? ($postUser?->name ?? ''));
    $inicio = $s->fecha_inicio_trabajo
        ? \Illuminate\Support\Carbon::parse($s->fecha_inicio_trabajo)->format('d/m/Y')
        : '';
    $fin = $s->fecha_termino ? \Illuminate\Support\Carbon::parse($s->fecha_termino)->format('d/m/Y') : '';
@endphp

<p>Se ha generado una <strong>Orden de Trabajo</strong> para la solicitud N°
    <strong>{{ $s->numero_solicitud ?? $s->id }}</strong>.
</p>

<ul>
    <li><strong>Postulante:</strong> {{ $postName }}</li>
    <li><strong>Puede comenzar a trabajar desde:</strong> {{ $inicio }}</li>
    <li><strong>Fecha de término:</strong> {{ $fin }}</li>
	<li><strong>Tipo de reemplazo:</strong> {{ $s->tipo_reemplazo }}@if ($s->tipo_reemplazo === 'Otras' && $s->tipo_reemplazo_otro)
	        ({{ $s->tipo_reemplazo_otro }})
	    @endif
	</li>
    <li><strong>Establecimiento:</strong> {{ $s->establecimiento?->rbd }} -
        {{ mb_strtoupper($s->establecimiento?->nombre_establecimiento ?? '', 'UTF-8') }}</li>
</ul>

<p>Se adjunta el PDF de la Orden de Trabajo.</p>

@if (!empty($s->horario_titular_pdf_path))
    <p>También se adjunta el <strong>Horario del titular</strong> para referencia del reemplazo asignado.</p>
@endif

@if (!empty($s->contrato_trabajo_docx_path))
    <p>
        Además, se adjunta el <strong>Contrato de Trabajo (Word)</strong>.
        <br>
        <strong>Instrucción:</strong> imprimir y firmar <strong>2 copias</strong> del contrato (una del trabajador y
        otra para el SLEP), luego dirigirse con encargada de Reemplazos Jocelyn Bobadilla en segundo piso del SLEP
        (Gestión de Personas) y entregar copias firmadas por trabajador.
    </p>
@endif

<p>Atte.<br>
    Plataforma SLEP Andalién Costa</p>
@endsection
