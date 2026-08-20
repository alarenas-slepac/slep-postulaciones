@extends('layouts.app')

@section('content')
@php($descuentoCgr = $documento->descuentoCgr)
<div class="container py-5">
    <div class="card border-0 shadow-sm mx-auto" style="max-width: 900px;">
        <div class="card-header bg-white border-0 px-4 pt-4">
            <div class="text-uppercase small fw-semibold text-primary mb-1">Remuneraciones · Descuentos CGR</div>
            <h1 class="h4 mb-0">Verificación de descuento mensual</h1>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="alert {{ $verificacion['integro'] ? 'alert-success' : 'alert-danger' }} d-flex align-items-start gap-2">
                <i class="bi {{ $verificacion['integro'] ? 'bi-shield-check' : 'bi-shield-exclamation' }} fs-5"></i>
                <div>
                    <strong>{{ $verificacion['integro'] ? 'Documento mensual válido e íntegro.' : 'La información vigente no coincide con el documento mensual emitido.' }}</strong>
                    <div class="small">La comprobación considera el registro CGR, la cuota, la UTM del período y el archivo de resolución.</div>
                </div>
            </div>

            <dl class="row mb-0">
                <dt class="col-md-4">Tipo de documento</dt><dd class="col-md-8">Detalle mensual de descuento CGR</dd>
                <dt class="col-md-4">Funcionario/a</dt><dd class="col-md-8">{{ $descuentoCgr?->nombre }}</dd>
                <dt class="col-md-4">RUT</dt><dd class="col-md-8">{{ $descuentoCgr ? \App\Support\Rut::format($descuentoCgr->rut) : 'No disponible' }}</dd>
                <dt class="col-md-4">Resolución</dt><dd class="col-md-8">{{ $descuentoCgr?->numero_resolucion }}</dd>
                <dt class="col-md-4">Período</dt><dd class="col-md-8">{{ $documento->periodo?->format('m-Y') }}</dd>
                <dt class="col-md-4">Cuota</dt><dd class="col-md-8">N° {{ $documento->numero_cuota }} de {{ $descuentoCgr?->numero_cuotas }}</dd>
                <dt class="col-md-4">Código de verificación</dt><dd class="col-md-8"><code>{{ $documento->codigo_verificacion }}</code></dd>
                <dt class="col-md-4">Fecha de emisión</dt><dd class="col-md-8">{{ $documento->documento_emitido_en?->format('d-m-Y H:i:s') }}</dd>
                <dt class="col-md-4">Huella documental SHA-256</dt><dd class="col-md-8"><code class="text-break">{{ $documento->documento_hash }}</code></dd>
            </dl>
        </div>
    </div>
</div>
@endsection
