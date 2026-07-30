@csrf
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label required" for="nombre">Nombre de la mención</label>
        <input type="text" name="nombre" id="nombre" class="form-control @error('nombre') is-invalid @enderror"
            value="{{ old('nombre', $mencion->nombre) }}" required>
        @error('nombre')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label" for="universidad">Universidad</label>
        <input type="text" name="universidad" id="universidad"
            class="form-control @error('universidad') is-invalid @enderror"
            value="{{ old('universidad', $mencion->universidad) }}">
        @error('universidad')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="anio">Año</label>
        <input type="number" name="anio" id="anio" min="1900" max="2100"
            class="form-control @error('anio') is-invalid @enderror" value="{{ old('anio', $mencion->anio) }}">
        @error('anio')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-8">
        <label class="form-label required" for="subsector_id">Subsector</label>
        <select name="subsector_id" id="subsector_id" class="form-select @error('subsector_id') is-invalid @enderror"
            required>
            <option value="">Seleccione…</option>
            @foreach ($subsectores as $s)
                <option value="{{ $s->id }}" @selected(old('subsector_id', $mencion->subsector_id) == $s->id)>
                    {{ $s->subsector }}
                </option>
            @endforeach
        </select>
        @error('subsector_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-save"></i> Guardar
    </button>
    <a href="{{ route('admin.menciones.index') }}" class="btn btn-outline-secondary">Volver</a>
</div>
