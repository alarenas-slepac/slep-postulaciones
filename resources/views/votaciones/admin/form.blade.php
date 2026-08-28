@extends('layouts.app')

@section('content')
<div class="va-shell" data-votaciones-admin>
    <header class="va-topbar"><div><p class="va-topbar__eyebrow">Configuración</p><h1>{{ $jornada->exists ? 'Editar jornada' : 'Nueva jornada' }}</h1><p>Define la información general, los procesos asociados y la identidad pública.</p></div><div class="va-topbar__actions"><a class="btn btn-outline-light" href="{{ $jornada->exists ? route('votaciones.admin.jornadas.show', $jornada) : route('votaciones.admin.jornadas.index') }}"><i class="bi bi-arrow-left"></i> Volver</a></div></header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')

    <form method="POST" action="{{ $jornada->exists ? route('votaciones.admin.jornadas.update', $jornada) : route('votaciones.admin.jornadas.store') }}" data-disable-on-submit>
        @csrf
        @if($jornada->exists) @method('PUT') @endif
        <div class="va-section-grid">
            <section class="va-form-section">
                <h2><i class="bi bi-card-text me-1"></i> Información general</h2><p>Datos que identifican esta jornada dentro de la gestión.</p>
                <div class="mb-3"><label class="form-label" for="nombre">Nombre</label><input class="form-control" id="nombre" name="nombre" required maxlength="255" value="{{ old('nombre', $jornada->nombre) }}"></div>
                <div class="mb-3"><label class="form-label" for="fecha">Fecha</label><input class="form-control" id="fecha" type="date" name="fecha" required value="{{ old('fecha', $jornada->fecha?->format('Y-m-d')) }}"></div>
                <div><label class="form-label" for="descripcion">Descripción</label><textarea class="form-control" id="descripcion" name="descripcion" rows="5" maxlength="3000">{{ old('descripcion', $jornada->descripcion) }}</textarea></div>
            </section>
            <section class="va-form-section">
                <h2><i class="bi bi-ui-checks-grid me-1"></i> Procesos asociados</h2><p>Selecciona los procesos que se ejecutarán durante la jornada.</p>
                @foreach($procesos as $proceso)
                    <label class="d-flex align-items-start gap-3 p-3 mb-2 border rounded-3" for="proceso-{{ $proceso->id }}"><input class="form-check-input mt-1" type="checkbox" name="procesos[]" value="{{ $proceso->id }}" id="proceso-{{ $proceso->id }}" @checked(in_array($proceso->id, old('procesos', $jornada->procesos?->pluck('id')->all() ?? [])))><span><strong class="d-block">{{ $proceso->nombre }}</strong><small class="text-muted">Código {{ $proceso->codigo }}</small></span></label>
                @endforeach
            </section>
            <section class="va-form-section">
                <h2><i class="bi bi-globe2 me-1"></i> Identidad pública</h2><p>El slug genera la dirección pública cuando la jornada sea publicada.</p>
                <label class="form-label" for="slug">Slug público</label><div class="input-group"><span class="input-group-text">/votaciones/</span><input class="form-control" id="slug" name="slug" required pattern="[a-z0-9_-]+" value="{{ old('slug', $jornada->slug) }}"></div><div class="form-text">Solo letras minúsculas, números, guiones y guion bajo.</div>
            </section>
            <section class="va-form-section">
                <h2><i class="bi bi-activity me-1"></i> Estado operacional</h2><p>Las fechas representan hitos reales y se registran automáticamente durante la operación.</p>
                <dl class="row mb-0 small"><dt class="col-5 text-muted">Estado</dt><dd class="col-7"><span class="va-status va-status--{{ $jornada->estado ?? 'borrador' }}">{{ str($jornada->estado ?? 'borrador')->replace('_', ' ') }}</span></dd><dt class="col-5 text-muted">Publicada</dt><dd class="col-7">{{ $jornada->publicada_at?->format('d-m-Y H:i') ?? 'Aún no' }}</dd><dt class="col-5 text-muted">Inicio efectivo</dt><dd class="col-7">{{ $jornada->iniciada_at?->format('d-m-Y H:i') ?? 'Pendiente' }}</dd><dt class="col-5 text-muted">Término efectivo</dt><dd class="col-7">{{ $jornada->finalizada_at?->format('d-m-Y H:i') ?? 'Pendiente' }}</dd></dl>
            </section>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-3"><a class="btn btn-outline-secondary" href="{{ $jornada->exists ? route('votaciones.admin.jornadas.show', $jornada) : route('votaciones.admin.jornadas.index') }}">Cancelar</a><button class="btn btn-primary px-4"><i class="bi bi-check2-circle me-1"></i> Guardar jornada</button></div>
    </form>
</div>
@endsection

@push('styles')
    @vite('resources/css/votaciones-admin.css')
@endpush
@push('scripts')
    @vite('resources/js/votaciones-admin.js')
@endpush
