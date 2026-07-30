@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <h1 class="h4 mb-0">Validación documental</h1>
        </div>
        <div class="card-body">
            @if($documento)
                <div class="alert alert-success">Documento válido y registrado en plataforma.</div>
                <dl class="row">
                    <dt class="col-md-3">Tipo</dt><dd class="col-md-9">{{ $documento->tipo_documento }}</dd>
                    <dt class="col-md-3">Número</dt><dd class="col-md-9">{{ $documento->numero_documento }}</dd>
                    <dt class="col-md-3">Código</dt><dd class="col-md-9">{{ $documento->codigo_validacion }}</dd>
                    <dt class="col-md-3">Estado</dt><dd class="col-md-9">{{ $documento->estado }}</dd>
                    <dt class="col-md-3">Fecha emisión</dt><dd class="col-md-9">{{ optional($documento->emitido_at)->format('d-m-Y H:i') }}</dd>
                    <dt class="col-md-3">Hash</dt><dd class="col-md-9"><code>{{ $documento->documento_hash }}</code></dd>
                </dl>
                <h2 class="h6">Firmas registradas</h2>
                <ul>
                    @foreach($documento->firmas as $firma)
                        <li>{{ $firma->nombre_firmante }} — {{ $firma->tipo_firma }} — {{ optional($firma->fecha_firma)->format('d-m-Y H:i') }}</li>
                    @endforeach
                </ul>
            @else
                <div class="alert alert-danger">No se encontró un documento vigente con el código ingresado.</div>
            @endif
        </div>
    </div>
</div>
@endsection
