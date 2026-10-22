<div class="modal fade va-modal" id="modal-crear-grupo" tabindex="-1" aria-labelledby="titulo-crear-grupo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><div><h2 class="modal-title h5" id="titulo-crear-grupo">Crear grupo operativo</h2><p class="text-muted small mb-0">Se asociará a {{ $jornada->nombre }}.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <form method="POST" action="{{ route('votaciones.admin.grupos.store', $jornada) }}" data-disable-on-submit>
            @csrf
            <input type="hidden" name="modo" value="nuevo">
            <div class="modal-body"><div class="row g-3">
                <div class="col-sm-3"><label class="form-label" for="nuevo-numero">Número *</label><input class="form-control" type="number" min="1" max="999" id="nuevo-numero" name="numero" value="{{ old('modo') === 'nuevo' ? old('numero') : $jornada->grupos->max('numero') + 1 }}" required></div>
                <div class="col-sm-9"><label class="form-label" for="nuevo-nombre">Nombre *</label><input class="form-control" id="nuevo-nombre" name="nombre" value="{{ old('nombre') }}" placeholder="Grupo 1" required maxlength="255"></div>
                <div class="col-12"><label class="form-label" for="nuevo-encargado">Encargado *</label><select class="form-select" id="nuevo-encargado" name="encargado_id" required><option value="">Seleccione una persona</option>@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected(old('encargado_id') == $usuario->id)>{{ $usuario->display_name }} · {{ $usuario->email }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label" for="nuevo-miembros">Integrantes / Ministros de Fe</label><select class="form-select" id="nuevo-miembros" name="miembros[]" multiple size="5">@foreach($usuarios as $usuario)<option value="{{ $usuario->id }}" @selected(in_array($usuario->id, old('miembros', [])))>{{ $usuario->display_name }} · {{ $usuario->email }}</option>@endforeach</select></div>
                @include('votaciones.partials.periodo-grupo', ['prefijo' => 'nuevo', 'grupoPeriodo' => null])
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Crear grupo</button></div>
        </form>
    </div></div>
</div>
