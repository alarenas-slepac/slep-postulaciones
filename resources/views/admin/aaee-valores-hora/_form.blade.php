@csrf

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Área de desempeño</label>
        <select class="form-select @error('area_desempeno_id') is-invalid @enderror" name="area_desempeno_id" required>
            <option value="">— Seleccione —</option>
            @foreach ($areas as $a)
                <option value="{{ $a->id }}" @selected((string) old('area_desempeno_id', $item->area_desempeno_id) === (string) $a->id)>{{ $a->nombre }}</option>
            @endforeach
        </select>
        @error('area_desempeno_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Categoría</label>
        <select class="form-select @error('categoria') is-invalid @enderror" name="categoria" required>
            <option value="">— Seleccione —</option>
            @foreach ($categorias as $c)
                <option value="{{ $c }}" @selected(old('categoria', $item->categoria) === $c)>{{ ucfirst($c) }}</option>
            @endforeach
        </select>
        @error('categoria')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
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
    <a class="btn btn-outline-secondary" href="{{ route('admin.aaee-valores-hora.index') }}">Volver</a>
</div>
