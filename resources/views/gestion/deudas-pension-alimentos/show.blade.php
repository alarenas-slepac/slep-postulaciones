@extends('layouts.app')

@include('deudas-pension-alimentos._styles')

@section('content')
    @php
        $estado = $deuda->estadoFlujo($declaracion);
        $tone = match ($estado) {
            \App\Models\SolicitudReemplazoDeudaPension::ESTADO_ENVIADO => 'success',
            \App\Models\SolicitudReemplazoDeudaPension::ESTADO_LISTO_ENVIO => 'info',
            \App\Models\SolicitudReemplazoDeudaPension::ESTADO_PENDIENTE_DOCUMENTOS => 'danger',
            default => 'warning',
        };
        $solicitud = $deuda->solicitud;
        $postulante = $deuda->postulante?->user;
    @endphp

    <div class="container py-4">
        <div class="dpa-header mb-4">
            <div class="dpa-header__top">
                <div><div class="dpa-eyebrow"><span class="dpa-eyebrow__icon"><i class="bi bi-file-earmark-lock"></i></span> Expediente de deuda</div><h1 class="dpa-title">Solicitud {{ $solicitud?->numero_solicitud ?? $deuda->solicitud_reemplazo_id }}</h1><p class="dpa-subtitle">{{ $postulante?->full_name ?? 'Postulante' }} · {{ \App\Support\Rut::format($postulante?->rut) ?? 'Sin RUT' }}</p></div>
                <div class="d-flex flex-wrap gap-2"><span class="dpa-status dpa-status--{{ $tone }}">{{ \App\Models\SolicitudReemplazoDeudaPension::estados()[$estado] ?? $estado }}</span><a class="btn btn-outline-secondary" href="{{ route('gestion.deudas-pension-alimentos.index') }}">Volver</a></div>
            </div>
            <div class="dpa-summary">
                <div class="dpa-summary__item"><div class="dpa-summary__label">Estado solicitud</div><div class="dpa-summary__value">{{ ucfirst(str_replace('_', ' ', (string) $solicitud?->estado)) }}</div></div>
                <div class="dpa-summary__item"><div class="dpa-summary__label">Establecimiento</div><div class="dpa-summary__value fs-6">{{ $solicitud?->establecimiento?->nombre_establecimiento ?? '—' }}</div></div>
                <div class="dpa-summary__item"><div class="dpa-summary__label">Resolución o dictamen</div><div class="dpa-summary__value fs-6">{{ $deuda->resolucion_path ? 'Documento cargado' : 'Pendiente' }}</div></div>
            </div>
        </div>

        @if (session('status'))<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger"><div class="fw-semibold">No fue posible completar la acción:</div><ul class="mb-0 mt-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        @if ($estado !== \App\Models\SolicitudReemplazoDeudaPension::ESTADO_ENVIADO)
            <div class="alert alert-danger"><strong><i class="bi bi-lock-fill me-1"></i> Flujo bloqueado:</strong> la solicitud no puede generar Orden de Trabajo ni Contrato hasta enviar estos antecedentes a Remuneraciones.</div>
        @else
            <div class="alert alert-success"><strong><i class="bi bi-unlock-fill me-1"></i> Flujo desbloqueado:</strong> el correo fue enviado a {{ $deuda->correo_destino }} el {{ optional($deuda->enviado_at)->format('d/m/Y H:i') }}.</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="dpa-panel mb-4">
                    <div class="dpa-panel__header"><div class="dpa-panel__title">Documentos del expediente</div><p class="dpa-panel__subtitle">La declaración jurada se consulta siempre desde la versión vigente del postulante.</p></div>
                    <div class="dpa-panel__body">
                        <div class="dpa-document"><div><div class="dpa-document__title">Certificado de deuda de pensión de alimentos</div><div class="dpa-document__meta">{{ $deuda->certificado_deuda_nombre_original ?: 'Pendiente de carga por SLEP' }} @if($deuda->certificado_subido_at) · {{ $deuda->certificado_subido_at->format('d/m/Y H:i') }} @endif</div></div>@if($deuda->certificado_deuda_path)<div class="btn-group"><a target="_blank" class="btn btn-sm btn-outline-primary" href="{{ route('gestion.deudas-pension-alimentos.certificado', $deuda) }}">Ver</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('gestion.deudas-pension-alimentos.certificado', [$deuda, 'download' => 1]) }}">Descargar</a></div>@else<span class="dpa-status dpa-status--warning">Pendiente</span>@endif</div>
                        <div class="dpa-document"><div><div class="dpa-document__title">Resolución o dictamen actualizado</div><div class="dpa-document__meta">{{ $deuda->resolucion_nombre_original ?: 'Pendiente de carga por el postulante' }} @if($deuda->resolucion_subida_at) · {{ $deuda->resolucion_subida_at->format('d/m/Y H:i') }} @endif</div></div>@if($deuda->resolucion_path)<div class="btn-group"><a target="_blank" class="btn btn-sm btn-outline-primary" href="{{ route('gestion.deudas-pension-alimentos.resolucion', $deuda) }}">Ver</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('gestion.deudas-pension-alimentos.resolucion', [$deuda, 'download' => 1]) }}">Descargar</a></div>@else<span class="dpa-status dpa-status--warning">Pendiente</span>@endif</div>
                        <div class="dpa-document"><div><div class="dpa-document__title">Declaración jurada para ejercer cargo público</div><div class="dpa-document__meta">{{ $declaracion?->original_name ?: 'El postulante todavía no registra este documento' }} @if($declaracion?->updated_at) · Cargada el {{ $declaracion->updated_at->format('d/m/Y H:i') }} @endif</div></div>@if($declaracion?->path)<div class="btn-group"><a target="_blank" class="btn btn-sm btn-outline-primary" href="{{ route('gestion.deudas-pension-alimentos.declaracion', $deuda) }}">Ver</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('gestion.deudas-pension-alimentos.declaracion', [$deuda, 'download' => 1]) }}">Descargar</a></div>@else<span class="dpa-status dpa-status--danger">Faltante</span>@endif</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="dpa-panel mb-4">
                    <div class="dpa-panel__header"><div class="dpa-panel__title">Cargar certificado SLEP</div><p class="dpa-panel__subtitle">PDF de hasta 10 MB emitido por la plataforma externa.</p></div>
                    <div class="dpa-panel__body"><form method="POST" action="{{ route('gestion.deudas-pension-alimentos.certificado.store', $deuda) }}" enctype="multipart/form-data">@csrf<label for="certificado_deuda" class="form-label fw-semibold">Certificado PDF</label><input id="certificado_deuda" name="certificado_deuda" type="file" class="form-control" accept="application/pdf,.pdf" required><button type="submit" class="btn btn-outline-danger mt-3">{{ $deuda->certificado_deuda_path ? 'Reemplazar certificado' : 'Cargar certificado' }}</button></form></div>
                </div>
                <div class="dpa-panel">
                    <div class="dpa-panel__header"><div class="dpa-panel__title">Enviar a Remuneraciones</div><p class="dpa-panel__subtitle">El correo adjunta los tres documentos vigentes.</p></div>
                    <div class="dpa-panel__body">
                        @if ($estado === \App\Models\SolicitudReemplazoDeudaPension::ESTADO_LISTO_ENVIO)
                            <form method="POST" action="{{ route('gestion.deudas-pension-alimentos.enviar', $deuda) }}" onsubmit="return confirm('¿Enviar los antecedentes vigentes a la encargada de remuneraciones?');">@csrf<button class="btn btn-danger w-100" type="submit"><i class="bi bi-send"></i> Enviar información y documentos</button></form>
                        @elseif ($estado === \App\Models\SolicitudReemplazoDeudaPension::ESTADO_ENVIADO)
                            <div class="text-success fw-semibold"><i class="bi bi-check-circle-fill"></i> Expediente enviado</div><div class="small text-muted mt-1">Por {{ $deuda->enviadoPor?->full_name ?? '—' }} el {{ optional($deuda->enviado_at)->format('d/m/Y H:i') }}.</div>
                        @else
                            <button class="btn btn-secondary w-100" disabled>Completa los documentos pendientes</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
