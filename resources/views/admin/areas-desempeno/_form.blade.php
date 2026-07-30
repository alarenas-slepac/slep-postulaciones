@php($isEdit = $area->exists)
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Estamento</label>
        <select name="estamento" class="form-select @error('estamento') is-invalid @enderror" required>
            <option value="docente" @selected(old('estamento', $area->estamento) === 'docente')>Docente</option>
            <option value="asistente" @selected(old('estamento', $area->estamento) === 'asistente')>Asistente</option>
        </select>
        @error('estamento')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror"
            value="{{ old('nombre', $area->nombre) }}" required>
        @error('nombre')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" name="activo" id="activo"
                @checked(old('activo', $area->activo) ? true : false)>
            <label class="form-check-label" for="activo">Activo</label>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary">{{ $isEdit ? 'Guardar cambios' : 'Crear' }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.areas-desempeno.index') }}">Volver</a>
</div>
