@csrf

<div class="row g-3">
    <div class="col-md-7">
        <label class="form-label">Establecimiento <span class="text-danger">*</span></label>
        <select class="form-select @error('establecimiento_id') is-invalid @enderror" name="establecimiento_id" required>
            <option value="">Seleccione establecimiento...</option>
            @foreach ($establecimientosPorComuna as $comuna => $establecimientos)
                <optgroup label="{{ $comuna }}">
                    @foreach ($establecimientos as $establecimiento)
                        <option value="{{ $establecimiento->id }}" @selected((string) old('establecimiento_id', $item->establecimiento_id) === (string) $establecimiento->id)>
                            {{ $establecimiento->rbd }} — {{ $establecimiento->nombre_establecimiento }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('establecimiento_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">El listado se agrupa por comuna para facilitar la búsqueda del establecimiento.</div>
    </div>

    <div class="col-md-2">
        <label class="form-label">Año <span class="text-danger">*</span></label>
        <input type="number" class="form-control @error('anio') is-invalid @enderror" name="anio" min="2020" max="2100" value="{{ old('anio', $item->anio ?: now()->year) }}" required>
        @error('anio')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">% alumnos prioritarios <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="number" class="form-control @error('porcentaje') is-invalid @enderror" name="porcentaje" min="0" max="100" step="0.01" value="{{ old('porcentaje', $item->porcentaje) }}" required>
            <span class="input-group-text">%</span>
            @error('porcentaje')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-text">Ingrese un valor entre 0 y 100.</div>
    </div>

    <div class="col-12">
        <label class="form-label">Observación</label>
        <textarea class="form-control @error('observacion') is-invalid @enderror" name="observacion" rows="3" maxlength="2000" placeholder="Opcional: fuente, comentario o antecedente del registro.">{{ old('observacion', $item->observacion) }}</textarea>
        @error('observacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex flex-wrap gap-2">
    <button class="btn btn-primary" type="submit">
        <i class="bi bi-save"></i> Guardar
    </button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.alumnos-prioritarios.index') }}">Volver</a>
</div>
