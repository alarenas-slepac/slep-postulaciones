@csrf
@php
    $selectedAdmins = collect(old('administradores', $recurso->administradores?->pluck('id')->all() ?? []))->map(fn($v) => (int) $v)->all();
    $soloGestionAdmin = $soloGestionAdmin ?? false;
@endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="nombre" value="{{ old('nombre', $recurso->nombre) }}" class="form-control @error('nombre') is-invalid @enderror" required @disabled($soloGestionAdmin)>
        @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Slug</label>
        <input type="text" name="slug" value="{{ old('slug', $recurso->slug) }}" class="form-control @error('slug') is-invalid @enderror" placeholder="Se genera automáticamente si queda vacío" @disabled($soloGestionAdmin)>
        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
        <select name="tipo" class="form-select @error('tipo') is-invalid @enderror" required @disabled($soloGestionAdmin)>
            @foreach($tipos as $value => $label)
                <option value="{{ $value }}" @selected(old('tipo', $recurso->tipo) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('tipo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-8">
        <label class="form-label fw-semibold">Ubicación</label>
        <input type="text" name="ubicacion" value="{{ old('ubicacion', $recurso->ubicacion) }}" class="form-control @error('ubicacion') is-invalid @enderror" @disabled($soloGestionAdmin)>
        @error('ubicacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="requiere_aprobacion" value="1" id="requiere_aprobacion" @checked(old('requiere_aprobacion', $recurso->requiere_aprobacion))>
            <label class="form-check-label fw-semibold" for="requiere_aprobacion">Requiere aprobación del administrador de sala</label>
        </div>
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $recurso->activo ?? true)) @disabled($soloGestionAdmin)>
            <label class="form-check-label fw-semibold" for="activo">Activo</label>
        </div>
    </div>
    @unless($soloGestionAdmin)
    <div class="col-12">
        <label class="form-label fw-semibold">Administrador(es) responsable(s) <span class="text-danger">*</span></label>
        <select name="administradores[]" class="form-select @error('administradores') is-invalid @enderror @error('administradores.*') is-invalid @enderror" multiple size="8" required>
            @foreach($usuariosAdministrables as $usuario)
                <option value="{{ $usuario->id }}" @selected(in_array((int) $usuario->id, $selectedAdmins, true))>{{ $usuario->nombre_completo ?: $usuario->email }} — {{ $usuario->email }}</option>
            @endforeach
        </select>
        @error('administradores') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        @error('administradores.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">Cada sala o recurso debe tener al menos un administrador explícito. Las notificaciones se enviarán solo a estos administradores asignados, no a grupos de usuarios por rol.</div>
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Descripción</label>
        <textarea name="descripcion" rows="3" class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $recurso->descripcion) }}</textarea>
        @error('descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @endunless
</div>

<div class="d-flex justify-content-between mt-4">
    <a href="{{ route('gestion.agendamientos-recursos.recursos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Volver</a>
    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Guardar</button>
</div>
