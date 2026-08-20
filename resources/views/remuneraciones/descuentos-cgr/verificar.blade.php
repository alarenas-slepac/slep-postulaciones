@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="card border-0 shadow-sm mx-auto" style="max-width: 900px;">
        <div class="card-header bg-white border-0 px-4 pt-4">
            <div class="text-uppercase small fw-semibold text-primary mb-1">Remuneraciones · Descuentos CGR</div>
            <h1 class="h4 mb-0">Verificación documental</h1>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="alert {{ $verificacion['integro'] ? 'alert-success' : 'alert-danger' }} d-flex align-items-start gap-2">
                <i class="bi {{ $verificacion['integro'] ? 'bi-shield-check' : 'bi-shield-exclamation' }} fs-5"></i>
                <div>
                    <strong>{{ $verificacion['integro'] ? 'Documento válido e íntegro.' : 'La información vigente no coincide con el documento emitido.' }}</strong>
                    <div class="small">{{ $verificacion['integro'] ? 'La huella del registro, su cronograma y la resolución adjunta coincide con la emisión registrada.' : 'El registro, las UTM del cronograma o el archivo de resolución pudieron haber sido modificados después de la emisión.' }}</div>
                </div>
            </div>

            <dl class="row mb-0">
                <dt class="col-md-4">Tipo de documento</dt><dd class="col-md-8">Informe de descuento CGR</dd>
                <dt class="col-md-4">Registro</dt><dd class="col-md-8">N° {{ $descuentoCgr->id }}</dd>
                <dt class="col-md-4">Resolución</dt><dd class="col-md-8">{{ $descuentoCgr->numero_resolucion }}</dd>
                <dt class="col-md-4">Funcionario/a</dt><dd class="col-md-8">{{ $descuentoCgr->nombre }}</dd>
                <dt class="col-md-4">RUT</dt><dd class="col-md-8">{{ \App\Support\Rut::format($descuentoCgr->rut) }}</dd>
                <dt class="col-md-4">Código de verificación</dt><dd class="col-md-8"><code>{{ $descuentoCgr->codigo_verificacion }}</code></dd>
                <dt class="col-md-4">Fecha de emisión</dt><dd class="col-md-8">{{ $descuentoCgr->documento_emitido_en?->format('d-m-Y H:i:s') ?? 'Sin emisión registrada' }}</dd>
                <dt class="col-md-4">Huella documental SHA-256</dt><dd class="col-md-8"><code class="text-break">{{ $descuentoCgr->documento_hash }}</code></dd>
            </dl>
        </div>
    </div>
</div>
@endsection
