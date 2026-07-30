@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Detalle de notificación #{{ $item->id }}</h1>
            <p class="text-muted mb-0">Auditoría del despacho realizado por la aplicación.</p>
        </div>
        <a href="{{ route('admin.notification-logs.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">Resumen</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8"><span class="badge text-bg-{{ $item->status_badge_class }}">{{ match($item->status){'sent'=>'Enviado','queued'=>'En cola','failed'=>'Fallido',default=>ucfirst($item->status)} }}</span></dd>
                        <dt class="col-sm-4">Canal</dt>
                        <dd class="col-sm-8">{{ strtoupper($item->channel) }}</dd>
                        <dt class="col-sm-4">Asunto</dt>
                        <dd class="col-sm-8">{{ $item->subject ?: '—' }}</dd>
                        <dt class="col-sm-4">Evento</dt>
                        <dd class="col-sm-8">{{ $item->event_key ?: '—' }}</dd>
                        <dt class="col-sm-4">Descripción</dt>
                        <dd class="col-sm-8">{{ $item->description ?: '—' }}</dd>
                        <dt class="col-sm-4">Fecha registro</dt>
                        <dd class="col-sm-8">{{ cl_datetime($item->created_at) }}</dd>
                        <dt class="col-sm-4">Fecha envío</dt>
                        <dd class="col-sm-8">{{ cl_datetime($item->sent_at) }}</dd>
                        <dt class="col-sm-4">Fecha fallo</dt>
                        <dd class="col-sm-8">{{ cl_datetime($item->failed_at) }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">Destinatario y clases</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Destinatario</dt>
                        <dd class="col-sm-8">{{ $item->recipient_name ?: '—' }}<div class="small text-muted">{{ $item->recipient_email ?: '—' }}</div></dd>
                        <dt class="col-sm-4">Mailable</dt>
                        <dd class="col-sm-8"><code>{{ $item->mailable_class ?: '—' }}</code></dd>
                        <dt class="col-sm-4">Notification</dt>
                        <dd class="col-sm-8"><code>{{ $item->notification_class ?: '—' }}</code></dd>
                        <dt class="col-sm-4">Notificable</dt>
                        <dd class="col-sm-8">
                            @if($item->notifiable)
                                {{ class_basename($item->notifiable_type) }} #{{ $item->notifiable_id }}
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-sm-4">Relacionado</dt>
                        <dd class="col-sm-8">
                            @if($item->related)
                                {{ class_basename($item->related_type) }} #{{ $item->related_id }}
                            @else
                                —
                            @endif
                        </dd>
                        <dt class="col-sm-4">Disparado por</dt>
                        <dd class="col-sm-8">
                            @if($item->triggeredBy)
                                {{ $item->triggeredBy->nombre_completo ?: $item->triggeredBy->email }}
                                <div class="small text-muted">{{ $item->triggeredBy->email }}</div>
                            @else
                                Sistema
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">Contexto</div>
                <div class="card-body">
                    @if(!empty($item->context))
                        <pre class="small mb-0" style="white-space: pre-wrap;">{{ json_encode($item->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    @else
                        <p class="text-muted mb-0">Sin contexto adicional.</p>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header fw-semibold">Error</div>
                <div class="card-body">
                    @if($item->error_message)
                        <pre class="small text-danger mb-0" style="white-space: pre-wrap;">{{ $item->error_message }}</pre>
                    @else
                        <p class="text-muted mb-0">Sin error registrado.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
