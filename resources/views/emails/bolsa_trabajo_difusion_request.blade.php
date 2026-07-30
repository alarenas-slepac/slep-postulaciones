@extends('emails.layouts.institutional')

@section('title', 'Solicitud de difusión de oferta laboral')
@section('preheader', 'Solicitud de difusión de oferta laboral - Plataforma SLEP Andalién Costa')

@section('content')
@php
    $establecimiento = $oferta->establecimiento;
    $accionTexto = $accion === 'actualizada' ? 'actualización' : 'difusión';
@endphp

<p>Estimada/o equipo de Comunicaciones,</p>

<p>
    Junto con saludar, solicito gestionar la <strong>{{ $accionTexto }}</strong> de la siguiente oferta laboral publicada en el módulo <strong>Bolsa de Trabajo</strong>.
</p>

<p>
    <strong>Establecimiento(s):</strong> {{ $oferta->establecimientos_display ?: (optional($establecimiento)->rbd . ' - ' . (optional($establecimiento)->nombre_establecimiento ?? '—')) }}<br>
    <strong>Comuna:</strong> {{ $oferta->comuna ?: '—' }}<br>
    <strong>Estamento:</strong> {{ $oferta->estamento_label }}<br>
    <strong>Área de desempeño:</strong> {{ optional($oferta->areaDesempeno)->nombre ?? '—' }}<br>
    <strong>Calidad contractual:</strong> {{ $oferta->calidad_contractual_label }}<br>
    <strong>Horas:</strong> {{ $oferta->cantidad_horas }}<br>
    <strong>Remuneración bruta:</strong> {{ $oferta->remuneracion_bruta_formatted }}<br>
    <strong>Inicio de trabajo aproximado:</strong> {{ optional($oferta->inicio_trabajo_aproximado)->format('d/m/Y') ?? '—' }}<br>
    <strong>Inicio de postulaciones:</strong> {{ optional($oferta->fecha_inicio_postulaciones)->format('d/m/Y') ?? '—' }} {{ $oferta->hora_inicio_postulaciones ?: '' }}<br>
    <strong>Término de postulaciones:</strong> {{ optional($oferta->fecha_termino_postulaciones)->format('d/m/Y') ?? '—' }} {{ $oferta->hora_termino_postulaciones ?: '' }}<br>
    <strong>Correo de contacto:</strong> {{ $oferta->correo_contacto }}
</p>

<p>
    Esta oferta será accesible para los usuarios al ingresar a la portal de la Plataforma SLEP Andalién Costa:<br>
    <strong>{{ $portalUrl }}</strong>
</p>

<p>
    Agradeceré su apoyo con la difusión correspondiente.
</p>

<p>Saludos cordiales.</p>
@endsection
