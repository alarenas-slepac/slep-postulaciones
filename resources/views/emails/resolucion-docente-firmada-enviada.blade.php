@extends('emails.layouts.institutional')
@section('title', 'Resolución docente firmada')
@section('preheader', 'Resolución docente firmada disponible')
@section('content')
<p>Se adjunta la resolución docente firmada correspondiente a la solicitud <strong>#{{ $s->numero_solicitud }}</strong>.</p>
<p><strong>Establecimiento:</strong> {{ $s->establecimiento?->nombre_establecimiento ?? $s->establecimiento?->nombre ?? '—' }}<br>
<strong>Período:</strong> {{ $s->fecha_inicio?->format('d/m/Y') }} - {{ $s->fecha_termino?->format('d/m/Y') }}</p>
@endsection
