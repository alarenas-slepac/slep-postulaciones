@extends('emails.layouts.institutional')

@section('title', 'Observación SLEP Andalién Costa')
@section('preheader', 'Observación SLEP Andalién Costa - Plataforma SLEP Andalién Costa')

@section('content')
<p>Se ha registrado una <strong>observación SLEP</strong> para la solicitud <strong>#{{ $s->numero_solicitud }}</strong>.</p>

<p>
    <strong>Establecimiento:</strong>
    {{ optional($s->establecimiento)->rbd }} -
    {{ optional($s->establecimiento)->nombre_establecimiento ?? (optional($s->establecimiento)->nombre ?? '—') }}<br>
    <strong>Funcionario titular:</strong> {{ optional($s->funcionarioTitular)->rut }} - {{ optional($s->funcionarioTitular)->nombre }}<br>
    <strong>Área de desempeño:</strong> {{ optional($s->areaDesempeno)->nombre ?? '—' }}<br>
    <strong>Periodo:</strong> {{ optional($s->fecha_inicio)->format('d/m/Y') }} - {{ optional($s->fecha_termino)->format('d/m/Y') }}<br>
    <strong>Horas aula titular:</strong> C {{ $s->horas_aula_cronologicas_titular ?? 0 }} / P {{ $s->horas_aula_pedagogicas_titular ?? 0 }}<br>
    <strong>Horas aula reemplazo:</strong> C {{ $s->horas_aula_cronologicas_reemplazo ?? 0 }} / P {{ $s->horas_aula_pedagogicas_reemplazo ?? 0 }}
</p>

<p>
    <strong>Informada por:</strong>
    {{ $s->observacionSlepUser?->nombre_completo ?: ($s->observacionSlepUser?->email ?? 'Usuario SLEP') }}
    @if ($s->observacion_slep_at)
        <br><strong>Fecha:</strong> {{ cl_datetime($s->observacion_slep_at, 'd/m/Y H:i') }}
    @endif
</p>

<p><strong>Observación:</strong></p>
<p style="white-space: pre-line;">{{ $s->observacion_slep }}</p>

<p>
    Acceso:
    <a href="{{ route('funcionario.solicitudes-reemplazo.index') }}">
        Ver mis solicitudes de reemplazo
    </a>
</p>
@endsection
