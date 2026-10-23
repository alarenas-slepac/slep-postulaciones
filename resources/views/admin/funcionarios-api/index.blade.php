@extends('layouts.app')
@push('styles')
    @vite('resources/css/funcionario-consulta.css')
@endpush
@section('content')
<div class="fc-page">
    <header class="fc-header p-4 mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div><div class="fc-context mb-2">Administración · Acceso a información</div><h1 class="mb-2">Integraciones API de funcionarios</h1><p class="text-secondary mb-0">Administra los sistemas autorizados para consultar funcionarios por RUT.</p></div>
        @can('consultar_funcionarios')<a class="btn btn-outline-primary" href="{{ route('funcionarios.consulta.index') }}">Consultar funcionario</a>@endcan
    </header>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Revisa los datos del formulario.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="fc-panel p-4 mb-4" aria-labelledby="fc-create-title">
        <h2 class="h4 mb-3" id="fc-create-title">Nueva integración</h2>
        <form method="post" action="{{ route('admin.funcionarios-api.store') }}" class="row g-3 align-items-start">
            @csrf
            <div class="col-12 col-lg-5"><label for="fc-name" class="form-label">Nombre del sistema *</label><input id="fc-name" name="name" class="form-control" maxlength="120" required value="{{ old('name') }}"></div>
            <div class="col-6 col-lg-2"><label for="fc-limit" class="form-label">Consultas por minuto *</label><input id="fc-limit" name="requests_per_minute" type="number" min="1" max="{{ config('funcionario_consulta.max_requests_per_minute') }}" required class="form-control" value="{{ old('requests_per_minute', config('funcionario_consulta.requests_per_minute')) }}"></div>
            <div class="col-6 col-lg-2"><label for="fc-days" class="form-label">Vigencia del token (días) *</label><input id="fc-days" name="token_days" type="number" min="1" max="{{ config('funcionario_consulta.max_token_days') }}" required class="form-control" value="{{ old('token_days', config('funcionario_consulta.token_days')) }}"></div>
            <div class="col-12 col-lg-3"><button class="btn btn-primary mt-lg-4" type="submit">Crear y generar token</button></div>
        </form>
        <p class="form-text mb-0 mt-3">Cada sistema necesita su propio token. Al crearlo se mostrará una sola vez; guárdalo en el servidor del sistema consumidor.</p>
    </section>
    <section class="fc-panel p-4 mb-4" aria-labelledby="fc-integrations-title">
        <h2 class="h4 mb-3" id="fc-integrations-title">Sistemas registrados</h2>
        @forelse($integrations as $integration)
            @php($token = $integration->tokens->first())
            <article class="fc-data p-3 mb-3">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3"><h3 class="h5 mb-0">{{ $integration->name }}</h3><span class="badge rounded-pill {{ $integration->enabled ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $integration->enabled ? 'Habilitada' : 'Suspendida' }}</span><span class="badge rounded-pill text-bg-light border">{{ !$token ? 'Sin token' : ($token->expires_at?->isPast() ? 'Token vencido' : 'Token vigente') }}</span></div>
                <form method="post" action="{{ route('admin.funcionarios-api.update', $integration) }}" class="row g-3">
                    @csrf @method('PATCH')
                    <div class="col-12 col-md-4"><label class="form-label" for="fc-name-{{ $integration->id }}">Nombre del sistema</label><input class="form-control" id="fc-name-{{ $integration->id }}" name="name" required maxlength="120" value="{{ $integration->name }}"></div>
                    <div class="col-12 col-sm-6 col-md-3"><label class="form-label" for="fc-enabled-{{ $integration->id }}">Acceso</label><select class="form-select" id="fc-enabled-{{ $integration->id }}" name="enabled"><option value="1" @selected($integration->enabled)>Habilitado</option><option value="0" @selected(!$integration->enabled)>Suspendido</option></select></div>
                    <div class="col-12 col-sm-6 col-md-3"><label class="form-label" for="fc-limit-{{ $integration->id }}">Consultas por minuto</label><input class="form-control" id="fc-limit-{{ $integration->id }}" name="requests_per_minute" type="number" min="1" max="{{ config('funcionario_consulta.max_requests_per_minute') }}" required value="{{ $integration->requests_per_minute }}"></div>
                    <div class="col-12 col-md-2"><button class="btn btn-outline-primary mt-md-4" type="submit">Guardar</button></div>
                </form>
                <p class="small text-secondary my-3">Vencimiento: {{ $token?->expires_at?->format('d/m/Y H:i') ?? 'Sin token' }} · Último uso: {{ $token?->last_used_at?->format('d/m/Y H:i') ?? 'Sin uso registrado' }}</p>
                <div class="d-flex flex-wrap gap-3 align-items-end">
                    <form method="post" action="{{ route('admin.funcionarios-api.token', $integration) }}" class="d-flex flex-wrap align-items-end gap-2" data-fc-confirm="Se invalidará el token anterior. Deberás actualizarlo en el sistema consumidor. ¿Continuar?">
                        @csrf
                        <div><label class="form-label small" for="fc-renew-{{ $integration->id }}">Nueva vigencia (días)</label><input class="form-control" id="fc-renew-{{ $integration->id }}" name="token_days" type="number" min="1" max="{{ config('funcionario_consulta.max_token_days') }}" value="{{ config('funcionario_consulta.token_days') }}" required></div>
                        <button class="btn btn-outline-primary" type="submit">{{ $token ? 'Renovar token' : 'Generar token' }}</button>
                    </form>
                    @if($token)<form method="post" action="{{ route('admin.funcionarios-api.revoke', $integration) }}" data-fc-confirm="El token dejará de permitir consultas. ¿Revocarlo?">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit">Revocar token</button></form>@endif
                </div>
            </article>
        @empty
            <p class="text-secondary py-3 mb-0">Aún no hay sistemas registrados. Crea una integración para habilitar su acceso.</p>
        @endforelse
    </section>
    <section class="fc-panel p-4" aria-labelledby="fc-audit-title">
        <h2 class="h4 mb-2" id="fc-audit-title">Actividad de consultas</h2>
        <p class="small text-secondary">Se registra el actor, la fecha y el resultado. El historial no guarda RUT, nombres consultados ni tokens en texto legible.</p>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th scope="col">Fecha</th><th scope="col">Origen</th><th scope="col">Resultado</th><th scope="col">HTTP</th><th scope="col">Referencia</th></tr></thead><tbody>
        @forelse($audits as $audit)<tr><td>{{ \Illuminate\Support\Carbon::parse($audit->created_at)->format('d/m/Y H:i') }}</td><td>{{ $audit->integration_id ? 'Integración #'.$audit->integration_id : 'Usuario #'.$audit->user_id }}</td><td>{{ str_replace('_', ' ', $audit->outcome) }}</td><td>{{ $audit->http_status }}</td><td class="small text-secondary">{{ $audit->request_id }}</td></tr>@empty<tr><td colspan="5" class="text-secondary">No hay consultas registradas.</td></tr>@endforelse
        </tbody></table></div>
        {{ $audits->links('pagination::bootstrap-5') }}
    </section>
</div>
@endsection
@push('scripts')
    @vite('resources/js/funcionario-consulta.js')
@endpush
