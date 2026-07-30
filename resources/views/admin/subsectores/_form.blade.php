@csrf
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label required" for="subsector">Nombre del subsector</label>
        <input type="text" name="subsector" id="subsector" class="form-control @error('subsector') is-invalid @enderror"
            value="{{ old('subsector', $subsector->subsector) }}" required>
        @error('subsector')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-save"></i> Guardar
    </button>
    <a href="{{ route('admin.subsectores.index') }}" class="btn btn-outline-secondary">Volver</a>
</div>
