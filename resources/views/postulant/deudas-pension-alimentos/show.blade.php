@extends('layouts.app')

@include('deudas-pension-alimentos._styles')

@section('content')
    @php
        $estado = $deuda->estadoFlujo($declaracion);
        $solicitud = $deuda->solicitud;
    @endphp
    <div class="container py-4">
        <div class="dpa-header mb-4">
            <div class="dpa-header__top"><div><div class="dpa-eyebrow"><span class="dpa-eyebrow__icon"><i class="bi bi-file-earmark-lock"></i></span> Mi expediente</div><h1 class="dpa-title">Solicitud {{ $solicitud?->numero_solicitud ?? $deuda->solicitud_reemplazo_id }}</h1><p class="dpa-subtitle">Carga una resolución o dictamen actualizado. El valor de la cuota alimentaria será consultado directamente en el documento.</p></div><a class="btn btn-outline-secondary" href="{{ route('postulant.deudas-pension-alimentos.index') }}">Volver</a></div>
            <div class="dpa-summary"><div class="dpa-summary__item"><div class="dpa-summary__label">Estado solicitud</div><div class="dpa-summary__value fs-6">{{ ucfirst(str_replace('_', ' ', (string) $solicitud?->estado)) }}</div></div><div class="dpa-summary__item"><div class="dpa-summary__label">Estado expediente</div><div class="dpa-summary__value fs-6">{{ \App\Models\SolicitudReemplazoDeudaPension::estados()[$estado] ?? $estado }}</div></div><div class="dpa-summary__item"><div class="dpa-summary__label">Resolución o dictamen</div><div class="dpa-summary__value fs-6">{{ $deuda->resolucion_path ? 'Documento cargado' : 'Pendiente' }}</div></div></div>
        </div>

        @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="dpa-panel">
                    <div class="dpa-panel__header"><div class="dpa-panel__title">Documentos del expediente</div><p class="dpa-panel__subtitle">Puedes consultar y descargar cada documento disponible.</p></div>
                    <div class="dpa-panel__body">
                        <div class="dpa-document"><div><div class="dpa-document__title">Certificado de deuda</div><div class="dpa-document__meta">{{ $deuda->certificado_deuda_nombre_original ?: 'Pendiente de carga por SLEP' }} @if($deuda->certificado_subido_at) · {{ $deuda->certificado_subido_at->format('d/m/Y H:i') }} @endif</div></div>@if($deuda->certificado_deuda_path)<div class="btn-group"><a target="_blank" class="btn btn-sm btn-outline-primary" href="{{ route('postulant.deudas-pension-alimentos.certificado', $deuda) }}">Ver</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('postulant.deudas-pension-alimentos.certificado', [$deuda, 'download' => 1]) }}">Descargar</a></div>@else<span class="dpa-status dpa-status--warning">Pendiente</span>@endif</div>
                        <div class="dpa-document"><div><div class="dpa-document__title">Resolución o dictamen actualizado</div><div class="dpa-document__meta">{{ $deuda->resolucion_nombre_original ?: 'Debes cargar este documento' }} @if($deuda->resolucion_subida_at) · {{ $deuda->resolucion_subida_at->format('d/m/Y H:i') }} @endif</div></div>@if($deuda->resolucion_path)<div class="btn-group"><a target="_blank" class="btn btn-sm btn-outline-primary" href="{{ route('postulant.deudas-pension-alimentos.resolucion', $deuda) }}">Ver</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('postulant.deudas-pension-alimentos.resolucion', [$deuda, 'download' => 1]) }}">Descargar</a></div>@else<span class="dpa-status dpa-status--danger">Requerido</span>@endif</div>
                        <div class="dpa-document"><div><div class="dpa-document__title">Declaración jurada para ejercer cargo público</div><div class="dpa-document__meta">{{ $declaracion?->original_name ?: 'Pendiente en Mis documentos' }} @if($declaracion?->updated_at) · Cargada el {{ $declaracion->updated_at->format('d/m/Y H:i') }} @endif</div></div>@if($declaracion?->path)<div class="btn-group"><a target="_blank" class="btn btn-sm btn-outline-primary" href="{{ route('postulant.deudas-pension-alimentos.declaracion', $deuda) }}">Ver</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('postulant.deudas-pension-alimentos.declaracion', [$deuda, 'download' => 1]) }}">Descargar</a></div>@else<a class="btn btn-sm btn-outline-danger" href="{{ route('postulant.documents.index') }}">Ir a Mis documentos</a>@endif</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="dpa-panel"><div class="dpa-panel__header"><div class="dpa-panel__title">Cargar resolución o dictamen</div><p class="dpa-panel__subtitle">Sólo debes cargar el PDF. SLEP consultará en el documento el valor de la cuota alimentaria y verá inmediatamente la nueva versión y su fecha de carga.</p></div><div class="dpa-panel__body">
                    <form method="POST" action="{{ route('postulant.deudas-pension-alimentos.resolucion.store', $deuda) }}" enctype="multipart/form-data">@csrf
                        <div class="mb-3"><label for="resolucion" class="form-label fw-semibold">Resolución o dictamen PDF <span class="text-danger">*</span></label><input id="resolucion" name="resolucion" type="file" class="form-control" accept="application/pdf,.pdf" required><div class="form-text">Máximo 10 MB.</div></div>
                        <button class="btn btn-danger w-100" type="submit">{{ $deuda->resolucion_path ? 'Actualizar resolución o dictamen' : 'Cargar resolución o dictamen' }}</button>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
@endsection
