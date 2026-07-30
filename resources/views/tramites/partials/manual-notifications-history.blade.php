@php
    $manualApplicantNotifications = collect($manualApplicantNotifications ?? []);
    $displayTimezone = $displayTimezone ?? config('app.display_timezone', 'America/Santiago');
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">Historial de notificaciones al solicitante</span>
        <span class="badge text-bg-light">{{ $manualApplicantNotifications->count() }} registro(s)</span>
    </div>
    <div class="card-body">
        @if ($manualApplicantNotifications->isEmpty())
            <div class="text-muted">Aún no se han enviado notificaciones manuales al solicitante.</div>
        @else
            <div class="vstack gap-3">
                @foreach ($manualApplicantNotifications as $notificationLog)
                    <div class="border rounded p-3">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                            <div>
                                <div class="fw-semibold">{{ $notificationLog->subject ?: 'Notificación al solicitante' }}</div>
                                <div class="small text-muted">
                                    {{ optional($notificationLog->created_at)->timezone($displayTimezone)->format('d-m-Y H:i') ?: '—' }}
                                    · {{ $notificationLog->triggeredBy?->nombre_completo ?: $notificationLog->triggeredBy?->email ?: 'Sistema' }}
                                </div>
                            </div>
                            <span class="badge text-bg-{{ $notificationLog->status_badge_class }}">{{ match($notificationLog->status){'sent'=>'Enviado','queued'=>'En cola','failed'=>'Fallido',default=>ucfirst((string)$notificationLog->status)} }}</span>
                        </div>
                        <div class="small text-muted mb-2">{{ $notificationLog->recipient_name ?: 'Solicitante' }} · {{ $notificationLog->recipient_email ?: 'sin correo' }}</div>
                        <div class="bg-light border rounded p-3 small" style="white-space: pre-line;">{{ data_get($notificationLog->context, 'mensaje', 'Sin mensaje registrado.') }}</div>
                        @if ($notificationLog->error_message)
                            <div class="text-danger small mt-2">{{ $notificationLog->error_message }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
