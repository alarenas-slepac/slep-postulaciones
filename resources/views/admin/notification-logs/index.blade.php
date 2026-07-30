@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Historial de notificaciones</h1>
            <p class="text-muted mb-0">Registro de correos y notificaciones despachadas por la aplicación.</p>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.notification-logs.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Correo, asunto, descripción o clase">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="sent" @selected($status==='sent')>Enviado</option>
                        <option value="queued" @selected($status==='queued')>En cola</option>
                        <option value="failed" @selected($status==='failed')>Fallido</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Canal</label>
                    <select name="channel" class="form-select">
                        <option value="">Todos</option>
                        <option value="mail" @selected($channel==='mail')>Mail</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Evento</label>
                    <select name="event" class="form-select">
                        <option value="">Todos</option>
                        @foreach($events as $eventKey)
                            <option value="{{ $eventKey }}" @selected($event===$eventKey)>{{ $eventKey }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Desde</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control">
                </div>
                <div class="col-md-8 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('admin.notification-logs.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Destinatario</th>
                        <th>Asunto / evento</th>
                        <th>Relacionado</th>
                        <th>Disparado por</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td class="text-nowrap">{{ cl_datetime($item->created_at) }}</td>
                        <td><span class="badge text-bg-{{ $item->status_badge_class }}">{{ match($item->status){'sent'=>'Enviado','queued'=>'En cola','failed'=>'Fallido',default=>ucfirst($item->status)} }}</span></td>
                        <td>
                            <div class="fw-semibold">{{ $item->recipient_name ?: '—' }}</div>
                            <div class="small text-muted">{{ $item->recipient_email ?: '—' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $item->subject ?: 'Sin asunto registrado' }}</div>
                            <div class="small text-muted">{{ $item->event_key ?: ($item->mailable_class ?: $item->notification_class ?: '—') }}</div>
                        </td>
                        <td>
                            @if($item->related)
                                <div class="fw-semibold">{{ class_basename($item->related_type) }}</div>
                                <div class="small text-muted">ID {{ $item->related_id }}</div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($item->triggeredBy)
                                <div class="fw-semibold">{{ $item->triggeredBy->nombre_completo ?: $item->triggeredBy->email }}</div>
                                <div class="small text-muted">{{ $item->triggeredBy->email }}</div>
                            @else
                                <span class="text-muted">Sistema</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.notification-logs.show', $item) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No hay notificaciones registradas con los filtros aplicados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
            <div class="card-footer">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
