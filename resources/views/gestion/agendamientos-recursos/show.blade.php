@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif

    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div>
            <div class="text-uppercase text-muted fw-bold small">Agendamiento</div>
            <h1 class="h3 mb-1">{{ $agendamiento->titulo }}</h1>
            <div class="text-muted">{{ $agendamiento->tipo_recurso_label }} · {{ $agendamiento->fecha?->format('d-m-Y') }} · {{ $agendamiento->horario }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="{{ route('gestion.agendamientos-recursos.index', ['month' => $agendamiento->fecha?->format('Y-m'), 'recurso_id' => $agendamiento->recurso_catalogo_id]) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
            @if($puedeEditar)
                <a href="{{ route('gestion.agendamientos-recursos.edit', $agendamiento) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i> Editar</a>
            @endif
            @if($puedeAnular && ! in_array($agendamiento->estado, ['anulado','rechazado'], true))
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAnularAgendamiento"><i class="bi bi-x-circle me-1"></i> Anular</button>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-white border-0 p-4 d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Detalle</h2>
                    <span class="badge text-bg-{{ $agendamiento->badge_class }}">{{ $agendamiento->estado_label }}</span>
                </div>
                <div class="card-body p-4">
                    @if($agendamiento->estado === 'pendiente')
                        <div class="alert alert-warning"><i class="bi bi-hourglass-split me-1"></i> Esta solicitud está pendiente de aprobación por el administrador de sala.</div>
                    @endif
                    <dl class="row mb-0">
                        <dt class="col-md-4">Sala / recurso</dt><dd class="col-md-8">{{ $agendamiento->tipo_recurso_label }}</dd>
                        <dt class="col-md-4">Fecha</dt><dd class="col-md-8">{{ $agendamiento->fecha?->format('d-m-Y') }}</dd>
                        <dt class="col-md-4">Horario</dt><dd class="col-md-8">{{ $agendamiento->horario }}</dd>
                        <dt class="col-md-4">Solicitante</dt><dd class="col-md-8">{{ $agendamiento->solicitante_nombre ?: '—' }}</dd>
                        <dt class="col-md-4">Correo</dt><dd class="col-md-8">{{ $agendamiento->solicitante_email ?: '—' }}</dd>
                        <dt class="col-md-4">Unidad</dt><dd class="col-md-8">{{ $agendamiento->unidad ?: '—' }}</dd>
                        <dt class="col-md-4">Participantes</dt><dd class="col-md-8">{{ $agendamiento->cantidad_participantes ?: '—' }}</dd>
                        <dt class="col-md-4">Requiere proyector</dt><dd class="col-md-8">{{ $agendamiento->requiere_proyector ? 'Sí' : 'No' }}</dd>
                        <dt class="col-md-4">Apoyo técnico</dt><dd class="col-md-8">{{ $agendamiento->requiere_apoyo_tecnico ? 'Sí' : 'No' }}</dd>
                        <dt class="col-md-4">Motivo</dt><dd class="col-md-8">{!! nl2br(e($agendamiento->motivo ?: '—')) !!}</dd>
                        <dt class="col-md-4">Observaciones</dt><dd class="col-md-8">{!! nl2br(e($agendamiento->observaciones ?: '—')) !!}</dd>
                    </dl>
                </div>
            </div>

            @if($puedeResolver && $agendamiento->estado === 'pendiente')
                <div class="card border-0 shadow-sm rounded-4 mb-3">
                    <div class="card-header bg-white border-0 p-4"><h2 class="h5 mb-0">Resolver solicitud</h2></div>
                    <div class="card-body p-4">
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <form method="POST" action="{{ route('gestion.agendamientos-recursos.aprobar', $agendamiento) }}">
                                @csrf
                                <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> Aprobar solicitud</button>
                            </form>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalRechazarAgendamiento"><i class="bi bi-x-octagon me-1"></i> Rechazar solicitud</button>
                        </div>
                        <p class="text-muted mb-0 small">Esta acción sólo está disponible porque la sala tiene habilitado el flujo de aprobación.</p>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-3">
                <div class="card-header bg-white border-0 p-4"><h2 class="h6 mb-0">Trazabilidad</h2></div>
                <div class="card-body p-4 small">
                    <div class="mb-2"><strong>Creado por:</strong><br>{{ $agendamiento->creador?->nombre_completo ?: ($agendamiento->creador?->email ?? '—') }}</div>
                    <div class="mb-2"><strong>Fecha creación:</strong><br>{{ optional($agendamiento->created_at)->format('d-m-Y H:i') ?: '—' }}</div>
                    <div class="mb-2"><strong>Última edición:</strong><br>{{ optional($agendamiento->updated_at)->format('d-m-Y H:i') ?: '—' }}</div>
                    @if($agendamiento->aprobado_at)
                        <hr><div class="mb-2"><strong>Aprobado por:</strong><br>{{ $agendamiento->aprobador?->nombre_completo ?: ($agendamiento->aprobador?->email ?? '—') }}</div>
                        <div class="mb-2"><strong>Fecha aprobación:</strong><br>{{ optional($agendamiento->aprobado_at)->format('d-m-Y H:i') }}</div>
                    @endif
                    @if($agendamiento->rechazado_at)
                        <hr><div class="mb-2"><strong>Rechazado por:</strong><br>{{ $agendamiento->rechazador?->nombre_completo ?: ($agendamiento->rechazador?->email ?? '—') }}</div>
                        <div class="mb-2"><strong>Fecha rechazo:</strong><br>{{ optional($agendamiento->rechazado_at)->format('d-m-Y H:i') }}</div>
                        <div><strong>Motivo rechazo:</strong><br>{!! nl2br(e($agendamiento->motivo_rechazo ?: '—')) !!}</div>
                    @endif
                    @if($agendamiento->estado === 'anulado')
                        <hr>
                        <div class="mb-2"><strong>Anulado por:</strong><br>{{ $agendamiento->anulador?->nombre_completo ?: ($agendamiento->anulador?->email ?? '—') }}</div>
                        <div class="mb-2"><strong>Fecha anulación:</strong><br>{{ optional($agendamiento->anulado_at)->format('d-m-Y H:i') ?: '—' }}</div>
                        <div><strong>Motivo:</strong><br>{!! nl2br(e($agendamiento->motivo_anulacion ?: '—')) !!}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if ($puedeAnular && ! in_array($agendamiento->estado, ['anulado','rechazado'], true))
<div class="modal fade" id="modalAnularAgendamiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('gestion.agendamientos-recursos.anular', $agendamiento) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Anular agendamiento</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><label class="form-label fw-semibold">Motivo de anulación</label><textarea name="motivo_anulacion" class="form-control" rows="4" required></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Anular</button></div>
        </form>
    </div>
</div>
@endif

@if ($puedeResolver && $agendamiento->estado === 'pendiente')
<div class="modal fade" id="modalRechazarAgendamiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('gestion.agendamientos-recursos.rechazar', $agendamiento) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Rechazar solicitud</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><label class="form-label fw-semibold">Motivo de rechazo</label><textarea name="motivo_rechazo" class="form-control" rows="4" required></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Rechazar</button></div>
        </form>
    </div>
</div>
@endif
@endsection
