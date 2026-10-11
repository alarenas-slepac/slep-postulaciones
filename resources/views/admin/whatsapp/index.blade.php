@extends('layouts.app')

@push('styles')
    @vite('resources/css/whatsapp-admin.css')
@endpush

@section('content')
<div class="wa-admin" data-wa-admin data-status-url="{{ route('admin.whatsapp.status') }}"
     data-qr-url="{{ route('admin.whatsapp.qr') }}" data-unlink-url="{{ route('admin.whatsapp.unlink') }}"
     data-enabled="{{ $enabled && $ready ? '1' : '0' }}">
    <header class="wa-header mb-4 p-4 d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
            <div class="wa-context mb-2"><i class="bi bi-whatsapp me-2" aria-hidden="true"></i>Administración · Notificaciones</div>
            <h1 class="mb-2">WhatsApp del SGA</h1>
            <p class="mb-0 text-secondary">Vincula el número de notificaciones y comprueba un envío antes de habilitar eventos.</p>
        </div>
        <button class="btn btn-outline-secondary" type="button" data-wa-refresh><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Actualizar estado</button>
    </header>

    @unless($enabled)
        <div class="alert alert-info" role="status"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>La integración está desactivada. El administrador del servidor debe completar su configuración antes de vincular el teléfono.</div>
    @endunless
    @unless($ready)
        <div class="alert alert-warning" role="status">La instalación de este módulo está pendiente. Completa la migración indicada en la guía de instalación.</div>
    @endunless
    <div class="alert d-none" data-wa-feedback role="status" aria-live="polite"></div>

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            <section class="wa-panel p-4 mb-4" aria-labelledby="wa-session-title">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 id="wa-session-title" class="h4 mb-0"><i class="bi bi-phone me-2" aria-hidden="true"></i>Dispositivo vinculado</h2>
                    <span class="badge rounded-pill text-bg-secondary" data-wa-state aria-live="polite">{{ $enabled ? 'Consultando estado' : 'Desactivado' }}</span>
                </div>
                <p data-wa-state-help class="text-secondary">El estado se actualiza automáticamente mientras esta pantalla está abierta.</p>
                <div class="wa-qr-area my-3">
                    <div data-wa-qr-empty class="text-secondary text-center p-4"><i class="bi bi-qr-code display-5 d-block mb-3" aria-hidden="true"></i><span data-wa-qr-placeholder>Genera el QR cuando estés listo para vincular el número de notificaciones.</span></div>
                    <img data-wa-qr class="d-none" alt="Código QR para vincular el dispositivo WhatsApp del SGA" width="320" height="320">
                </div>
                <p class="small text-secondary text-center" data-wa-qr-expiry></p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" data-wa-generate @disabled(!$enabled || !$ready)><i class="bi bi-qr-code me-1" aria-hidden="true"></i>Generar / renovar QR</button>
                    <button type="button" class="btn btn-outline-danger" data-wa-unlink @disabled(!$enabled || !$ready)><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>Desvincular sesión</button>
                </div>
            </section>
            <section class="wa-panel p-4" aria-labelledby="wa-history-title">
                <h2 id="wa-history-title" class="h4 mb-3"><i class="bi bi-clock-history me-2" aria-hidden="true"></i>Últimos envíos</h2>
                <p class="small text-secondary">“Enviado” indica aceptación por WhatsApp, sin confirmar lectura. Un resultado incierto requiere revisión antes de repetirlo.</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th scope="col">Solicitud</th><th scope="col">Estado</th><th scope="col">Fecha</th></tr></thead>
                        <tbody data-wa-history><tr><td colspan="3" class="text-secondary">Aún no hay envíos para mostrar.</td></tr></tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-12 col-lg-5">
            <section class="wa-panel p-4 mb-4" aria-labelledby="wa-link-title">
                <h2 id="wa-link-title" class="h4 mb-3">Vincular desde tu iPhone</h2>
                <ol class="wa-steps ps-3 mb-3">
                    <li>Abre <strong>WhatsApp Messenger</strong> con el número destinado a notificaciones.</li>
                    <li>Ve a <strong>Configuración → Dispositivos vinculados → Vincular un dispositivo</strong>.</li>
                    <li>Escanea el QR y espera el estado <strong>Conectado</strong>.</li>
                </ol>
                <p class="small text-secondary mb-0">Usa Messenger para esta vinculación. Tu cuenta personal en WhatsApp Business conserva sus dispositivos y configuración. Si el servicio está desconectado al desvincular, elimina también el dispositivo del SGA desde Messenger.</p>
            </section>
            <section class="wa-panel p-4" aria-labelledby="wa-test-title">
                <h2 id="wa-test-title" class="h4 mb-2"><i class="bi bi-send me-2" aria-hidden="true"></i>Mensaje de prueba</h2>
                <p class="text-secondary small">Ingresa expresamente el destinatario. Sólo este mensaje se pondrá en la cola de WhatsApp.</p>
                <form data-wa-test action="{{ route('admin.whatsapp.test') }}" method="post" autocomplete="off">
                    @csrf
                    <input type="hidden" name="request_id" value="{{ $requestId }}">
                    <div class="mb-3">
                        <label for="wa-to" class="form-label">Número destinatario <span aria-hidden="true">*</span></label>
                        <input type="tel" id="wa-to" name="to" class="form-control" required maxlength="16" pattern="\+[1-9][0-9]{7,14}" aria-describedby="wa-to-help wa-to-error" @disabled(!$enabled || !$ready)>
                        <div id="wa-to-help" class="form-text">Incluye + y código de país, sin espacios. Confirma que el destinatario espera la prueba.</div>
                        <div id="wa-to-error" class="invalid-feedback" data-wa-error="to"></div>
                    </div>
                    <div class="mb-3">
                        <label for="wa-text" class="form-label">Mensaje <span aria-hidden="true">*</span></label>
                        <textarea id="wa-text" name="text" rows="3" maxlength="1000" class="form-control" required aria-describedby="wa-text-error" @disabled(!$enabled || !$ready)>Prueba de notificaciones del SGA SLEP Andalién Costa.</textarea>
                        <div id="wa-text-error" class="invalid-feedback" data-wa-error="text"></div>
                    </div>
                    <button type="submit" class="btn btn-primary" data-wa-send disabled><i class="bi bi-send me-1" aria-hidden="true"></i>Enviar prueba</button>
                    <p class="form-text mt-2 mb-0">Disponible con la sesión conectada. El resultado aparecerá en “Últimos envíos”.</p>
                </form>
            </section>
        </div>
    </div>
    <div class="alert alert-info mt-4 mb-0"><i class="bi bi-bell me-2" aria-hidden="true"></i>Los eventos automáticos están pendientes de definición: evento, destinatario y preferencia para recibir WhatsApp. Esta pantalla permite comprobar la conexión y un envío dirigido.</div>
</div>
@endsection

@push('scripts')
    @vite('resources/js/whatsapp-admin.js')
@endpush
