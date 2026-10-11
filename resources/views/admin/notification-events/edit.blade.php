@extends('layouts.app')
@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">
    @vite(['resources/css/whatsapp-admin.css', 'resources/css/notification-events.css'])
@endpush
@section('content')
<div class="wa-admin ne-admin">
    <header class="wa-header p-4 mb-4 d-flex flex-wrap justify-content-between gap-3">
        <div><div class="wa-context mb-2">{{ $definition['module'] }} · Configuración de evento</div><h1 class="mb-2">{{ $definition['name'] }}</h1><p class="ne-key text-secondary mb-0">{{ $event }}</p></div>
        <a class="btn btn-outline-secondary align-self-start" href="{{ route('admin.notification-events.index') }}">Volver al catálogo</a>
    </header>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><p class="fw-bold">Revisa la configuración.</p><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @unless($ready)<div class="alert alert-warning" role="status">Completa la migración de eventos antes de guardar.</div>@endunless
    @if($definition['discovered'] ?? false)<div class="alert alert-warning" role="status">Esta clase nueva apareció automáticamente. Completa su nombre, módulo y clave estable en el catálogo antes de activar avisos operativos.</div>@endif
    <section class="wa-panel p-4 mb-4"><h2 class="h4">Destinatarios del proceso</h2>
        @if(in_array('mail', $definition['channels']))<p class="mb-2"><strong>Correo:</strong> {{ $definition['recipients'] }}</p>@endif
        @if(in_array('whatsapp', $definition['channels']))<p class="mb-2"><strong>WhatsApp:</strong> {{ $definition['whatsapp_recipients'] }}</p>@endif
        <p class="small text-secondary mb-0">El trámite calcula el destinatario principal. La configuración del evento conserva sus permisos y los documentos que le corresponden.</p>
        @if(isset($definition['cometido_key']))<a class="btn btn-outline-secondary mt-3" href="{{ route('admin.cometidos-notificaciones.index', ['q' => $definition['cometido_key']]) }}">Editar roles y correos de Cometidos</a>@endif
    </section>
    @if($definition['protected'] ?? false)
        <div class="alert alert-info" role="status"><i class="bi bi-shield-lock me-2" aria-hidden="true"></i>Este aviso conserva su envío, plantilla y destinatario para proteger la verificación de correo y la recuperación de contraseña.</div>
    @else
    <div class="row g-4">
    @foreach($definition['channels'] as $channel)
        @php($setting = $settings->get($channel))
        @php($useOld = old('editing_channel') === $channel)
        @php($value = fn($field, $default = null) => $useOld ? old($field, $default) : ($setting?->$field ?? $default))
        <div class="col-12 {{ count($definition['channels']) > 1 ? 'col-xl-6' : '' }}">
            <section class="wa-panel p-4 mb-4" aria-labelledby="channel-{{ $channel }}">
                <h2 id="channel-{{ $channel }}" class="h4 mb-3"><i class="bi {{ $channel === 'mail' ? 'bi-envelope' : 'bi-whatsapp' }} me-2" aria-hidden="true"></i>{{ $channel === 'mail' ? 'Correo electrónico' : 'WhatsApp' }}</h2>
                @if($channel === 'whatsapp')<div class="alert alert-info small">El servicio debe estar habilitado en el servidor. Cada teléfono mantiene la validación y condiciones de su ficha o perfil. @if($definition['whatsapp_from_mail'] ?? false)Este canal nuevo comienza desactivado y envía un resumen con enlace al SGA; los adjuntos se consultan en la página.@endif</div>@endif
                <form method="POST" action="{{ route('admin.notification-events.update', [$event, $channel]) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="editing_channel" value="{{ $channel }}"><input type="hidden" name="revision" value="{{ $useOld ? old('revision', $setting?->revision ?? 0) : ($setting?->revision ?? 0) }}">
                    <input type="hidden" name="enabled" value="0">
                    <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled-{{ $channel }}" @checked($value('enabled', $channel === 'whatsapp' ? ($definition['whatsapp_default'] ?? true) : true))><label class="form-check-label fw-bold" for="enabled-{{ $channel }}">Habilitar este evento por {{ $channel === 'mail' ? 'correo' : 'WhatsApp' }}</label></div>
                    @if($channel === 'mail')
                        <input type="hidden" name="recipient_mode" value="all">
                        <div class="mb-4"><label class="form-label fw-bold" for="copies-{{ $channel }}">Correos adicionales (copia oculta)</label><textarea class="form-control" name="additional_emails" id="copies-{{ $channel }}" rows="3" maxlength="4000">{{ $useOld ? old('additional_emails') : implode("\n", $setting?->additional_emails ?? []) }}</textarea><div class="form-text">Hasta 20 correos. Recibirán el mismo contenido y adjuntos del evento; sólo agrega destinatarios autorizados para esos datos.</div>@error('additional_emails')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                        <div class="mb-4"><label class="form-label fw-bold" for="subject-{{ $channel }}">Plantilla de asunto</label><input class="form-control" id="subject-{{ $channel }}" name="subject_template" value="{{ $value('subject_template') }}" maxlength="200" placeholder="Conservar el asunto original"><div class="form-text">Variables: @{{asunto_original}} y @{{evento}}.</div>@error('subject_template')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                    @elseif(($definition['whatsapp_roles'] ?? []) || ($definition['whatsapp_from_mail'] ?? false))
                        <div class="mb-4"><label class="form-label fw-bold" for="mode-{{ $channel }}">Destinatarios WhatsApp</label><select class="form-select" name="recipient_mode" id="mode-{{ $channel }}"><option value="all" @selected($value('recipient_mode', 'all') === 'all')>Todos los destinatarios válidos del evento</option><option value="selected" @selected($value('recipient_mode') === 'selected')>Sólo usuarios seleccionados</option></select><div class="form-text">Seleccionar restringe el grupo. @if($definition['whatsapp_from_mail'] ?? false)Cada usuario debe ser también destinatario original del correo de ese evento; elegirlo aquí no agrega una copia.@else Conserva al responsable asignado en las etapas SLEP.@endif</div></div>
                        <div class="mb-4"><label class="form-label fw-bold" for="recipients-{{ $channel }}">Usuarios seleccionados</label>
                        <select class="form-select js-ne-recipients" id="recipients-{{ $channel }}" name="recipient_ids[]" multiple size="5" aria-describedby="recipients-help-{{ $channel }}">
                        @foreach($recipients as $recipient)<option value="{{ $recipient['user_id'] }}" @selected(in_array($recipient['user_id'], array_map('intval', $value('recipient_ids', [])), true))>{{ $recipient['label'] }}</option>@endforeach</select>
                        <div class="form-text" id="recipients-help-{{ $channel }}">Busca por nombre. Sin el buscador, usa Ctrl/Cmd para seleccionar varios usuarios. @if($recipients->isEmpty())No hay destinatarios vigentes con teléfono válido.@endif</div>@error('recipient_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                    @else<input type="hidden" name="recipient_mode" value="all"><p class="small text-secondary">El destinatario se determina en cada solicitud o prueba manual.</p>@endif
                    <div class="mb-4"><label class="form-label fw-bold" for="body-{{ $channel }}">Plantilla de presentación</label><textarea class="form-control" name="body_template" id="body-{{ $channel }}" rows="7" maxlength="1500" placeholder="Conservar la plantilla original">{{ $value('body_template') }}</textarea><div class="form-text">Añade texto antes o después de @{{contenido_original}}. Esta variable debe aparecer una sola vez: conserva los datos y enlaces del aviso de este canal. También admite @{{evento}} y @{{asunto_original}}. Vacío conserva la plantilla vigente.</div>@if($channel === 'whatsapp')<div class="form-text">Máximo 1000 caracteres después de incorporar los datos. Los avisos nuevos y la aceptación al reemplazante conservan el pie automático sin respuesta al final.</div>@endif @error('body_template')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                    <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" @disabled(!$ready)>Guardar {{ $channel === 'mail' ? 'correo' : 'WhatsApp' }}</button><button class="btn btn-outline-secondary" formaction="{{ route('admin.notification-events.preview', [$event, $channel]) }}">Vista previa sin envío</button></div>
                </form>
                <form method="POST" action="{{ route('admin.notification-events.reset', [$event, $channel]) }}" class="mt-3">@csrf<input type="hidden" name="revision" value="{{ $setting?->revision ?? 0 }}"><button class="btn btn-outline-secondary" @disabled(!$ready)>Restablecer configuración original</button></form>
                @if($setting)<p class="small text-secondary mt-3 mb-0">Última actualización: {{ $setting->updated_at->format('d-m-Y H:i') }} · revisión {{ $setting->revision }}.</p>@endif
            </section>
        </div>
    @endforeach
    </div>
    @endif
    @if($changes->isNotEmpty())<section class="wa-panel p-4"><h2 class="h4 mb-3">Últimos cambios de configuración</h2><ul class="mb-0">@foreach($changes as $change)<li>{{ $change->created_at }} · {{ $change->channel === 'mail' ? 'Correo' : 'WhatsApp' }} · administrador #{{ $change->actor_id }}</li>@endforeach</ul></section>@endif
</div>
@endsection
@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.jQuery?.fn?.select2) return;
            window.jQuery('.js-ne-recipients').select2({
                theme: 'bootstrap-5', width: '100%', placeholder: 'Buscar y seleccionar usuarios',
                dropdownCssClass: 'ne-select-dropdown', maximumSelectionLength: 100,
                language: { noResults: () => 'Sin resultados', searching: () => 'Buscando…', maximumSelected: () => 'Selecciona hasta 100 usuarios' },
            });
        });
    </script>
@endpush
