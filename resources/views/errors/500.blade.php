@extends('layouts.error')

@section('title', 'Error del servidor')
@section('code', '500')
@section('headline', 'Ocurrió un error inesperado')
@section('message', 'Estamos trabajando para solucionarlo. Intenta nuevamente en unos segundos.')
@section('extra')
    @if (!empty($exceptionId))
        <p class="small text-muted mb-0">ID de incidente: <code>{{ $exceptionId }}</code></p>
    @endif
@endsection
