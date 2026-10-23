@extends('layouts.app')
@push('styles')
    @vite('resources/css/funcionario-consulta.css')
@endpush
@section('content')
<div class="fc-page">
    <header class="fc-header p-4 mb-4"><div class="fc-context mb-2">Administración · Integraciones API</div><h1 class="mb-2">Token de {{ $integration->name }}</h1><p class="text-secondary mb-0">Guarda este token ahora. Por seguridad, no podrás volver a consultarlo.</p></header>
    <section class="fc-panel p-4">
        <h2 class="h4 mb-3">Credencial de acceso</h2>
        <p class="small text-secondary">Vence el {{ $expiresAt->format('d/m/Y H:i') }}. Permite consultar funcionarios por RUT.</p>
        <pre class="fc-data fc-token p-3" data-fc-token>{{ $plainToken }}</pre>
        <button class="btn btn-primary" type="button" data-fc-copy>Copiar token</button><span class="ms-2 small" data-fc-copy-status role="status" aria-live="polite"></span>
        <p class="text-secondary mt-3">Guárdalo en una herramienta de secretos o configuración privada del servidor consumidor. Usa la cabecera <code>Authorization: Bearer</code> sobre HTTPS.</p>
        <a href="{{ route('admin.funcionarios-api.index') }}" class="btn btn-outline-secondary">Volver a integraciones</a>
    </section>
</div>
@endsection
@push('scripts')
    @vite('resources/js/funcionario-consulta.js')
@endpush
