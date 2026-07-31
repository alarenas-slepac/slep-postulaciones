@extends('layouts.app')

@section('content')
@php
    $rut = strtoupper((string) $certificado->rut_normalizado);
    $rutFormateado = number_format((int) mb_substr($rut, 0, -1), 0, ',', '.')
        . '-' . mb_substr($rut, -1);
@endphp
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-lg-5">
                    <div class="text-center mb-4">
                        <div class="display-6 text-{{ $certificado->estado === 'vigente' ? 'success' : 'danger' }}">
                            <i class="bi bi-{{ $certificado->estado === 'vigente' ? 'patch-check-fill' : 'x-octagon-fill' }}"></i>
                        </div>
                        <h1 class="h3 mt-2">Verificación documental</h1>
                        <p class="text-muted mb-0">
                            Certificado de vigencia laboral · {{ $certificado->numero }}
                        </p>
                    </div>

                    @if ($certificado->estado === 'vigente')
                        <div class="alert alert-success">
                            El documento consultado fue emitido por la plataforma y se encuentra vigente.
                        </div>
                    @elseif ($certificado->estado === 'anulado')
                        <div class="alert alert-danger">
                            El documento existe, pero fue anulado el
                            {{ $certificado->anulado_at?->format('d-m-Y H:i') }}.
                        </div>
                    @else
                        <div class="alert alert-warning">
                            El documento existe, pero no se encuentra vigente.
                        </div>
                    @endif

                    <dl class="row mb-0">
                        <dt class="col-sm-4">Funcionario</dt>
                        <dd class="col-sm-8">{{ $certificado->nombre_snapshot }}</dd>
                        <dt class="col-sm-4">RUT</dt>
                        <dd class="col-sm-8">{{ $rutFormateado }}</dd>
                        <dt class="col-sm-4">Fecha de antigüedad</dt>
                        <dd class="col-sm-8">{{ $certificado->fecha_antiguedad?->format('d-m-Y') }}</dd>
                        <dt class="col-sm-4">Fecha de emisión</dt>
                        <dd class="col-sm-8">{{ $certificado->emitido_at?->format('d-m-Y H:i') }}</dd>
                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8 text-capitalize">{{ $certificado->estado }}</dd>
                        <dt class="col-sm-4">Huella SHA-256</dt>
                        <dd class="col-sm-8">
                            <code class="text-break">{{ $certificado->documento_hash ?: 'No disponible' }}</code>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
