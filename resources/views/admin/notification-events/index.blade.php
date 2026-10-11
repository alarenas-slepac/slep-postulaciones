@extends('layouts.app')
@push('styles')
    @vite(['resources/css/whatsapp-admin.css', 'resources/css/notification-events.css'])
@endpush
@section('content')
<div class="wa-admin ne-admin">
    <header class="wa-header p-4 mb-4 d-flex flex-wrap justify-content-between gap-3">
        <div><div class="wa-context mb-2"><i class="bi bi-bell me-2" aria-hidden="true"></i>Administración · Comunicaciones</div>
            <h1 class="mb-2">Eventos de notificaciones</h1>
            <p class="text-secondary mb-0">Configura canales, destinatarios y presentación de los avisos del SGA.</p></div>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            <a class="btn btn-outline-secondary" href="{{ route('admin.notification-logs.index') }}">Historial de correos</a>
            @can('whatsapp.manage')<a class="btn btn-outline-secondary" href="{{ route('admin.whatsapp.index') }}">Conexión WhatsApp</a>@endcan
        </div>
    </header>
    @unless($ready)<div class="alert alert-warning" role="status">La instalación de la configuración está pendiente. El catálogo se puede consultar; guardar requiere completar la migración.</div>@endunless
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="row g-3 mb-4">
        @foreach([[$total, 'Eventos inventariados'], [$whatsappCount, 'Con canal WhatsApp'], [$protectedCount, 'Avisos de acceso protegidos']] as [$count, $label])
        <div class="col-12 col-md-4"><div class="wa-panel p-4"><div class="small text-secondary">{{ $label }}</div><div class="h2 mt-2 mb-0">{{ $count }}</div></div></div>
        @endforeach
    </div>
    <section class="wa-panel p-4 mb-4" aria-labelledby="filters-title">
        <h2 id="filters-title" class="h4 mb-3">Buscar eventos</h2>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-lg-5"><label for="event-search" class="form-label">Nombre o clave del evento</label><input id="event-search" name="q" class="form-control" value="{{ request('q') }}" maxlength="120" placeholder="Por ejemplo, orden de trabajo"></div>
            <div class="col-12 col-md-5 col-lg-3"><label for="event-module" class="form-label">Módulo</label><select id="event-module" name="module" class="form-select"><option value="">Todos los módulos</option>@foreach($modules as $module)<option @selected(request('module') === $module)>{{ $module }}</option>@endforeach</select></div>
            <div class="col-12 col-md-4 col-lg-2"><label for="event-channel" class="form-label">Canal</label><select id="event-channel" name="channel" class="form-select"><option value="">Todos</option><option value="mail" @selected(request('channel') === 'mail')>Correo</option><option value="whatsapp" @selected(request('channel') === 'whatsapp')>WhatsApp</option></select></div>
            <div class="col-12 col-md-3 col-lg-2 d-flex flex-wrap gap-2"><button class="btn btn-primary">Buscar</button><a class="btn btn-outline-secondary" href="{{ route('admin.notification-events.index') }}">Limpiar</a></div>
        </form>
    </section>
    <section class="wa-panel p-4" aria-labelledby="catalog-title">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><h2 id="catalog-title" class="h4 mb-0">Catálogo de eventos</h2><span class="text-secondary small">{{ $events->total() }} resultados</span></div>
        <p class="small text-secondary">Un canal disponible conserva las condiciones del proceso y las preferencias del destinatario. Los canales sin implementación se muestran como “No disponible”.</p>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th scope="col">Evento</th><th scope="col">Correo</th><th scope="col">WhatsApp</th><th scope="col">Configuración</th></tr></thead>
            <tbody>@forelse($events as $key => $event)
                <tr><td><span class="fw-bold d-block">{{ $event['name'] }}</span><span class="small text-secondary d-block">{{ $event['module'] }}</span><span class="small ne-key">{{ $key }}</span></td>
                    @foreach(['mail', 'whatsapp'] as $channel)<td>
                        @if(!in_array($channel, $event['channels']))<span class="small text-secondary">No disponible</span>
                        @elseif($event['protected'] ?? false)<span class="badge rounded-pill text-bg-info">Protegido</span>
                        @else @php($enabled = $settings->get($key.'|'.$channel)?->enabled ?? ($channel === 'whatsapp' ? ($event['whatsapp_default'] ?? true) : true))
                            <span class="badge rounded-pill text-bg-{{ $enabled ? 'success' : 'secondary' }}">{{ $enabled ? 'Habilitado' : 'Desactivado' }}</span>
                            @if($channel === 'whatsapp' && (!config('whatsapp.enabled') || (!($event['whatsapp_from_mail'] ?? false) && $key !== 'admin.test' && !config($key === 'solicitud_reemplazo.aceptada_reemplazante' ? 'whatsapp.reemplazantes.enabled' : 'whatsapp.reemplazos.enabled'))))
                                <span class="small text-secondary d-block mt-1">Servicio o módulo desactivado</span>
                            @endif
                        @endif
                    </td>@endforeach
                    <td><a class="btn btn-outline-secondary" href="{{ route('admin.notification-events.edit', $key) }}" aria-label="Configurar {{ $event['name'] }}">{{ ($event['protected'] ?? false) ? 'Consultar' : 'Configurar' }}</a></td></tr>
            @empty<tr><td colspan="4" class="text-center text-secondary py-5">No hay eventos que coincidan con los filtros.</td></tr>@endforelse</tbody>
        </table></div><div class="mt-3">{{ $events->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
@endsection
