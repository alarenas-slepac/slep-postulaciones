@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $asignatura->nombre) }}" required maxlength="180" placeholder="Ej.: Lengua y Literatura">
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Código <span class="text-danger">*</span></label>
        <input type="text" name="codigo" class="form-control @error('codigo') is-invalid @enderror" value="{{ old('codigo', $asignatura->codigo) }}" required maxlength="80" placeholder="Ej.: LENG_LIT">
        @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Debe ser único.</div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Tipo <span class="text-danger">*</span></label>
        @php($tipoActual = old('tipo_asignatura', $asignatura->tipo_asignatura))
        <select name="tipo_asignatura" class="form-select @error('tipo_asignatura') is-invalid @enderror" required>
            <option value="">Seleccione...</option>
            @foreach ($tipos as $tipoKey => $tipoLabel)
                <option value="{{ $tipoKey }}" @selected($tipoActual === $tipoKey)>{{ $tipoLabel }}</option>
            @endforeach
        </select>
        @error('tipo_asignatura')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Nivel educativo</label>
        @php($nivelActual = old('nivel_educativo', $asignatura->nivel_educativo))
        <select name="nivel_educativo" class="form-select @error('nivel_educativo') is-invalid @enderror">
            <option value="">Transversal / no especificado</option>
            @foreach (['Educación Parvularia', 'Educación Básica', 'Educación Media', 'EPJA', 'Educación Especial', 'Transversal'] as $nivelOption)
                <option value="{{ $nivelOption }}" @selected($nivelActual === $nivelOption)>{{ $nivelOption }}</option>
            @endforeach
        </select>
        @error('nivel_educativo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Área / sector</label>
        <input type="text" name="area" class="form-control @error('area') is-invalid @enderror" value="{{ old('area', $asignatura->area) }}" maxlength="120" placeholder="Ej.: Área B - Ciencias, ADMINISTRACION">
        @error('area')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label class="form-label d-block">Origen</label>
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="es_oficial" value="0">
            <input class="form-check-input" type="checkbox" value="1" name="es_oficial" id="es_oficial" @checked(old('es_oficial', $asignatura->es_oficial) ? true : false)>
            <label class="form-check-label" for="es_oficial">Oficial</label>
        </div>
    </div>

    <div class="col-md-2">
        <label class="form-label d-block">Estado</label>
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="activo" value="0">
            <input class="form-check-input" type="checkbox" value="1" name="activo" id="activo" @checked(old('activo', $asignatura->activo) ? true : false)>
            <label class="form-check-label" for="activo">Activa</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Observación</label>
        <textarea name="observacion" rows="3" class="form-control @error('observacion') is-invalid @enderror" maxlength="4000" placeholder="Uso normativo, criterios o notas internas">{{ old('observacion', $asignatura->observacion) }}</textarea>
        @error('observacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex flex-wrap gap-2">
    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.asignaturas.index') }}">Volver</a>
</div>
