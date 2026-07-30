@extends('emails.layouts.institutional')

@section('title', 'Trámite anulado')
@section('preheader', 'Trámite anulado - Plataforma SLEP Andalién Costa')

@section('content')
<p>Hola {{ $tramite->nombre_completo_snapshot ?: ($tramite->user->nombre_completo ?? 'usuario') }},</p>
<p>Tu trámite <strong>#{{ $tramite->id }}</strong> ({{ $tramite->tipo_label }}) fue anulado.</p>
<p><strong>Motivo de anulación:</strong></p>
<p>{{ $tramite->anulado_motivo ?: 'Sin motivo informado.' }}</p>
@if($tramite->anulado_at)
    <p>Fecha: {{ $tramite->anulado_at->timezone(config('app.display_timezone', 'America/Santiago'))->format('d-m-Y H:i') }}</p>
@endif
<p>Saludos,<br>Plataforma SLEP Andalién Costa</p>
@endsection
