@extends('layouts.app')
@push('styles')@vite(['resources/css/whatsapp-admin.css', 'resources/css/notification-events.css'])@endpush
@section('content')
<div class="wa-admin ne-admin">
    <header class="wa-header p-4 mb-4"><div class="wa-context mb-2">{{ $definition['module'] }} · {{ $channel === 'mail' ? 'Correo' : 'WhatsApp' }}</div><h1 class="mb-2">Vista previa de presentación</h1><p class="text-secondary mb-0">Ejemplo ficticio. No se guardó configuración ni se enviaron mensajes o documentos.</p></header>
    <section class="wa-panel p-4 mb-4"><h2 class="h4 mb-3">{{ $subject }}</h2><div class="ne-preview">{{ $body }}</div></section>
    <p class="text-secondary">Los datos y el diseño del contenido original dependen de la solicitud real. Esta vista muestra únicamente la presentación configurada.</p>
    <button type="button" class="btn btn-outline-secondary" onclick="history.back()">Volver a la edición</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.notification-events.edit', $event) }}">Abrir configuración guardada</a>
</div>
@endsection
