@csrf
<div class="mb-3">
    <label class="form-label">Nombre de la función</label>
    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $item->nombre) }}" maxlength="255" required>
    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="d-flex gap-2">
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.funciones-catalogo.index') }}">Volver</a>
</div>
