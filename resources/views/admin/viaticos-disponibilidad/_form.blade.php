@csrf

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label fw-semibold">Año presupuestario <span class="text-danger">*</span></label>
        <input type="number" name="anio" class="form-control @error('anio') is-invalid @enderror" value="{{ old('anio', $disponibilidad->anio) }}" min="2020" max="2100" required>
        @error('anio')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-5">
        <label class="form-label fw-semibold">Origen <span class="text-danger">*</span></label>
        <select name="origen_tipo" class="form-select @error('origen_tipo') is-invalid @enderror" required>
            <option value="">Seleccione origen</option>
            @foreach($origenes as $key => $label)
                <option value="{{ $key }}" @selected(old('origen_tipo', $disponibilidad->origen_tipo) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('origen_tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Estado</label>
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $disponibilidad->activo ?? true))>
            <label class="form-check-label" for="activo">Registro activo para control presupuestario</label>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Monto inicial disponible <span class="text-danger">*</span></label>
        <input type="number" name="monto_inicial" class="form-control @error('monto_inicial') is-invalid @enderror" value="{{ old('monto_inicial', $disponibilidad->monto_inicial) }}" min="0" required>
        @error('monto_inicial')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Monto comprometido</label>
        <input type="number" name="monto_comprometido" class="form-control @error('monto_comprometido') is-invalid @enderror" value="{{ old('monto_comprometido', $disponibilidad->monto_comprometido ?? 0) }}" min="0">
        <div class="form-text">En esta etapa puede ajustarse manualmente; el descuento automático se conectará al CDP en el siguiente parche.</div>
        @error('monto_comprometido')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Monto ejecutado</label>
        <input type="number" name="monto_ejecutado" class="form-control @error('monto_ejecutado') is-invalid @enderror" value="{{ old('monto_ejecutado', $disponibilidad->monto_ejecutado ?? 0) }}" min="0">
        @error('monto_ejecutado')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Vigente desde <span class="text-danger">*</span></label>
        <input type="date" name="vigente_desde" class="form-control @error('vigente_desde') is-invalid @enderror" value="{{ old('vigente_desde', optional($disponibilidad->vigente_desde)->format('Y-m-d')) }}" required>
        @error('vigente_desde')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Vigente hasta</label>
        <input type="date" name="vigente_hasta" class="form-control @error('vigente_hasta') is-invalid @enderror" value="{{ old('vigente_hasta', optional($disponibilidad->vigente_hasta)->format('Y-m-d')) }}">
        @error('vigente_hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Observaciones</label>
        <textarea name="observaciones" rows="4" class="form-control @error('observaciones') is-invalid @enderror" placeholder="Indique resolución, fuente presupuestaria, alcance o comentario administrativo.">{{ old('observaciones', $disponibilidad->observaciones) }}</textarea>
        @error('observaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mt-4">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-save me-1"></i> Guardar disponibilidad
    </button>
    <a href="{{ route('admin.viaticos-disponibilidad.index') }}" class="btn btn-outline-secondary">
        Cancelar
    </a>
</div>
