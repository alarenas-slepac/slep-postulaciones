@extends('layouts.app')

@section('content')
<div class="va-shell" data-votaciones-admin>
    <header class="va-topbar"><div><p class="va-topbar__eyebrow">Configuración</p><h1>Procesos de votación</h1><p>Catálogo utilizado al crear jornadas CCAF, Mutualidades u otros procesos futuros.</p></div><div class="va-topbar__actions"><button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#crear-proceso"><i class="bi bi-plus-circle"></i> Nuevo proceso</button></div></header>
    @include('votaciones.partials.admin-nav')
    @include('votaciones.partials.alertas')

    <section class="va-card"><div class="va-card__header"><div><h2>Catálogo vigente</h2><p>Los procesos inactivos se conservan para mantener compatibilidad histórica.</p></div></div><div class="table-responsive"><table class="table va-table va-table-responsive-cards"><thead><tr><th>Proceso</th><th>Código</th><th>Jornadas asociadas</th><th>Estado</th><th></th></tr></thead><tbody>
        @foreach($procesos as $proceso)
            <tr><td><span class="va-table__title">{{ $proceso->nombre }}</span></td><td data-label="Código"><code>{{ $proceso->codigo }}</code></td><td data-label="Jornadas">{{ $proceso->jornadas_count }}</td><td data-label="Estado"><span class="va-status va-status--{{ $proceso->activo ? 'en_curso' : 'finalizada' }}">{{ $proceso->activo ? 'Activo' : 'Inactivo' }}</span></td><td data-label="Acciones"><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editar-proceso-{{ $proceso->id }}">Editar</button></td></tr>
        @endforeach
    </tbody></table></div></section>

    <div class="modal fade va-modal" id="crear-proceso" tabindex="-1" aria-labelledby="titulo-crear-proceso" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="titulo-crear-proceso">Nuevo proceso</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><form method="POST" action="{{ route('votaciones.admin.procesos.store') }}" data-disable-on-submit>@csrf<div class="modal-body"><div class="mb-3"><label class="form-label">Código</label><input class="form-control" name="codigo" required maxlength="50" placeholder="EJEMPLO_PROCESO"></div><div><label class="form-label">Nombre</label><input class="form-control" name="nombre" required maxlength="255"></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Crear proceso</button></div></form></div></div></div>

    @foreach($procesos as $proceso)
        <div class="modal fade va-modal" id="editar-proceso-{{ $proceso->id }}" tabindex="-1" aria-labelledby="titulo-proceso-{{ $proceso->id }}" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="titulo-proceso-{{ $proceso->id }}">Editar proceso</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><form method="POST" action="{{ route('votaciones.admin.procesos.update', $proceso) }}" data-disable-on-submit>@csrf @method('PUT')<div class="modal-body"><div class="mb-3"><label class="form-label">Código</label><input class="form-control" name="codigo" value="{{ $proceso->codigo }}" required maxlength="50"></div><div class="mb-3"><label class="form-label">Nombre</label><input class="form-control" name="nombre" value="{{ $proceso->nombre }}" required maxlength="255"></div><label class="d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="activo" value="1" @checked($proceso->activo)><span>Proceso disponible para nuevas jornadas</span></label></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar cambios</button></div></form></div></div></div>
    @endforeach
</div>
@endsection

@push('styles')
    @vite('resources/css/votaciones-admin.css')
@endpush
@push('scripts')
    @vite('resources/js/votaciones-admin.js')
@endpush
