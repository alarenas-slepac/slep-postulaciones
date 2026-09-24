@extends('layouts.app')

@section('content')
@php
    $rut = strtoupper((string) $certificado->rut_normalizado);
    $rutFormateado = number_format((int) mb_substr($rut, 0, -1), 0, ',', '.')
        . '-' . mb_substr($rut, -1);
@endphp
<div class="cl-page py-5">
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="card cl-panel">
                <div class="card-body cl-verify-body">
                    <div class="text-center mb-4">
                        <div class="cl-verify-icon {{ $certificado->estado === 'vigente' ? 'is-success' : ($certificado->estado === 'anulado' ? 'is-danger' : 'is-warning') }}" aria-hidden="true">
                            <i class="bi bi-{{ $certificado->estado === 'vigente' ? 'patch-check-fill' : ($certificado->estado === 'anulado' ? 'x-octagon-fill' : 'exclamation-circle-fill') }}"></i>
                        </div>
                        <div class="cl-eyebrow mt-3">Certificados laborales</div>
                        <h1 class="cl-hero-title">Verificación documental</h1>
                        <p class="cl-hero-subtitle mx-auto">
                            Certificado de vigencia laboral · {{ $certificado->numero }}
                        </p>
                    </div>

                    @if ($certificado->estado === 'vigente')
                        <div class="alert alert-success cl-alert" role="status">
                            El documento consultado fue emitido por la plataforma y se encuentra vigente.
                        </div>
                    @elseif ($certificado->estado === 'anulado')
                        <div class="alert alert-danger cl-alert" role="status">
                            El documento existe, pero fue anulado el
                            {{ $certificado->anulado_at?->format('d-m-Y H:i') }}.
                        </div>
                    @else
                        <div class="alert alert-warning cl-alert" role="status">
                            El documento existe, pero no se encuentra vigente.
                        </div>
                    @endif

                    <dl class="row g-2 mb-0">
                        <div class="col-sm-6"><div class="cl-fact"><dt class="cl-fact-label">Funcionario</dt><dd class="cl-fact-value">{{ $certificado->nombre_snapshot }}</dd></div></div>
                        <div class="col-sm-6"><div class="cl-fact"><dt class="cl-fact-label">RUT</dt><dd class="cl-fact-value">{{ $rutFormateado }}</dd></div></div>
                        <div class="col-sm-6"><div class="cl-fact"><dt class="cl-fact-label">Fecha de antigüedad</dt><dd class="cl-fact-value">{{ $certificado->fecha_antiguedad?->format('d-m-Y') }}</dd></div></div>
                        <div class="col-sm-6"><div class="cl-fact"><dt class="cl-fact-label">Fecha de emisión</dt><dd class="cl-fact-value">{{ $certificado->emitido_at?->format('d-m-Y H:i') }}</dd></div></div>
                        <div class="col-12"><div class="cl-fact"><dt class="cl-fact-label">Estado</dt><dd class="cl-fact-value"><span class="cl-chip {{ $certificado->estado === 'vigente' ? 'is-success' : ($certificado->estado === 'anulado' ? 'is-danger' : 'is-warning') }}">{{ ucfirst($certificado->estado) }}</span></dd></div></div>
                        <div class="col-12"><div class="cl-fact"><dt class="cl-fact-label">Huella SHA-256</dt><dd class="cl-fact-value cl-hash"><code>{{ $certificado->documento_hash ?: 'No disponible' }}</code></dd></div></div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
