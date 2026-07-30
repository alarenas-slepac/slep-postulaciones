@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $curso->nombre) }}" required maxlength="120" placeholder="Ej.: 1° Básico">
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Código <span class="text-danger">*</span></label>
        <input type="text" name="codigo" class="form-control @error('codigo') is-invalid @enderror" value="{{ old('codigo', $curso->codigo) }}" required maxlength="50" placeholder="Ej.: 1B">
        @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Debe ser único.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Orden <span class="text-danger">*</span></label>
        <input type="number" name="orden" class="form-control @error('orden') is-invalid @enderror" value="{{ old('orden', $curso->orden ?: 1) }}" min="1" max="999" required>
        @error('orden')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Nivel educativo <span class="text-danger">*</span></label>
        <select name="nivel_educativo" class="form-select @error('nivel_educativo') is-invalid @enderror" required>
            @php($nivelActual = old('nivel_educativo', $curso->nivel_educativo))
            <option value="">Seleccione...</option>
            @foreach (['Educación Parvularia', 'Educación Básica', 'Educación Media', 'EPJA Básica', 'EPJA Media', 'Educación Especial'] as $nivel)
                <option value="{{ $nivel }}" @selected($nivelActual === $nivel)>{{ $nivel }}</option>
            @endforeach
        </select>
        @error('nivel_educativo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">Modalidad</label>
        @php($modalidadActual = old('modalidad', $curso->modalidad))
        <select name="modalidad" class="form-select @error('modalidad') is-invalid @enderror">
            <option value="">Sin modalidad específica</option>
            @foreach (['Parvularia', 'Básica', 'Común', 'Humanístico-Científica', 'Técnico-Profesional', 'Artística', 'EPJA', 'Laboral'] as $modalidad)
                <option value="{{ $modalidad }}" @selected($modalidadActual === $modalidad)>{{ $modalidad }}</option>
            @endforeach
        </select>
        @error('modalidad')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="activo" value="0">
            <input class="form-check-input" type="checkbox" value="1" name="activo" id="activo" @checked(old('activo', $curso->activo) ? true : false)>
            <label class="form-check-label" for="activo">Curso activo</label>
        </div>
    </div>
</div>

<div class="mt-4 d-flex flex-wrap gap-2">
    <button class="btn btn-primary" type="submit">
        <i class="bi bi-save"></i> Guardar
    </button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.cursos.index') }}">Volver</a>
</div>
