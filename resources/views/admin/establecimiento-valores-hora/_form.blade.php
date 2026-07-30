@csrf

<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Establecimiento</label>
        <select class="form-select @error('establecimiento_id') is-invalid @enderror" name="establecimiento_id" required>
            <option value="">— Seleccione —</option>
            @foreach ($establecimientos as $e)
                @php $name = $e->nombre_establecimiento ?? $e->nombre ?? '—'; @endphp
                <option value="{{ $e->id }}" @selected((string) old('establecimiento_id', $item->establecimiento_id) === (string) $e->id)>
                    {{ $e->rbd }} - {{ $name }}
                </option>
            @endforeach
        </select>
        @error('establecimiento_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Rol</label>
        <select class="form-select @error('rol') is-invalid @enderror" name="rol" required>
            <option value="">— Seleccione —</option>
            @foreach ($roles as $k => $label)
                <option value="{{ $k }}" @selected(old('rol', $item->rol) === $k)>{{ $label }}</option>
            @endforeach
        </select>
        @error('rol')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Activo</label>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="activo" value="1" @checked((bool) old('activo', $item->activo))>
            <label class="form-check-label">Sí</label>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label">Valor hora ($)</label>
        <input type="number" min="0" step="0.01"
            class="form-control @error('valor_hora') is-invalid @enderror"
            name="valor_hora" value="{{ old('valor_hora', $item->valor_hora) }}" required>
        @error('valor_hora')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.establecimiento-valores-hora.index') }}">Volver</a>
</div>
