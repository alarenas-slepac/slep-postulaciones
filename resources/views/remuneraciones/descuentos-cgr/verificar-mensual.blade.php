@extends('layouts.app')

@section('content')
@php($descuentoCgr = $documento->descuentoCgr)
@include('remuneraciones.descuentos-cgr._styles')
<div class="cgr-page py-4">
    <div class="card cgr-verification-card mx-auto">
        <div class="card-header cgr-verification-header">
            <div class="cgr-page-header__eyebrow"><span class="cgr-page-header__icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span> Remuneraciones · Descuentos CGR</div>
            <h1 class="mb-0">Verificación de descuento mensual</h1>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="alert {{ $verificacion['integro'] ? 'alert-success' : 'alert-danger' }} d-flex align-items-start gap-2">
                <i class="bi {{ $verificacion['integro'] ? 'bi-shield-check' : 'bi-shield-exclamation' }} fs-5"></i>
                <div>
                    <strong>{{ $verificacion['integro'] ? 'Documento mensual válido e íntegro.' : 'La información vigente no coincide con el documento mensual emitido.' }}</strong>
                    <div class="small">La comprobación considera el registro CGR, la cuota, la UTM del período y el archivo de resolución.</div>
                </div>
            </div>

            <dl class="row cgr-data-list mb-0">
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
