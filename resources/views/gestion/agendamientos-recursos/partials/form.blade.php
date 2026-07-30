@csrf

@php
    $selectedRecurso = (int) old('recurso_catalogo_id', $agendamiento->recurso_catalogo_id ?? 0);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Sala / recurso <span class="text-danger">*</span></label>
        <select name="recurso_catalogo_id" class="form-select @error('recurso_catalogo_id') is-invalid @enderror" required>
            @foreach ($recursosCatalogo as $recurso)
                <option value="{{ $recurso->id }}" @selected($selectedRecurso === (int) $recurso->id)>
                    {{ $recurso->nombre }} @if($recurso->requiere_aprobacion) · requiere aprobación @endif
                </option>
            @endforeach
        </select>
        @error('recurso_catalogo_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Título / actividad <span class="text-danger">*</span></label>
        <input type="text" name="titulo" value="{{ old('titulo', $agendamiento->titulo) }}" class="form-control @error('titulo') is-invalid @enderror" required maxlength="180">
        @error('titulo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Fecha <span class="text-danger">*</span></label>
        <input type="date" name="fecha" value="{{ old('fecha', optional($agendamiento->fecha)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" class="form-control @error('fecha') is-invalid @enderror" required>
        @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Hora inicio <span class="text-danger">*</span></label>
        <input type="time" name="hora_inicio" value="{{ old('hora_inicio', substr((string) $agendamiento->hora_inicio, 0, 5) ?: '09:00') }}" class="form-control @error('hora_inicio') is-invalid @enderror" required>
        @error('hora_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Hora término <span class="text-danger">*</span></label>
        <input type="time" name="hora_termino" value="{{ old('hora_termino', substr((string) $agendamiento->hora_termino, 0, 5) ?: '10:00') }}" class="form-control @error('hora_termino') is-invalid @enderror" required>
        @error('hora_termino') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Solicitante</label>
        <input type="text" name="solicitante_nombre" value="{{ old('solicitante_nombre', $agendamiento->solicitante_nombre) }}" class="form-control @error('solicitante_nombre') is-invalid @enderror" maxlength="180" placeholder="Se completa con el usuario si queda vacío">
        @error('solicitante_nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Correo solicitante</label>
        <input type="email" name="solicitante_email" value="{{ old('solicitante_email', $agendamiento->solicitante_email) }}" class="form-control @error('solicitante_email') is-invalid @enderror" maxlength="180">
        @error('solicitante_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Unidad / área solicitante</label>
        <input type="text" name="unidad" value="{{ old('unidad', $agendamiento->unidad) }}" class="form-control @error('unidad') is-invalid @enderror" maxlength="180" placeholder="Ej.: GDP, UATP, Dirección Ejecutiva">
        @error('unidad') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Lugar de uso / referencia</label>
        <input type="text" name="lugar_uso" value="{{ old('lugar_uso', $agendamiento->lugar_uso) }}" class="form-control @error('lugar_uso') is-invalid @enderror" maxlength="220">
        @error('lugar_uso') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Cantidad participantes</label>
        <input type="number" min="1" max="500" name="cantidad_participantes" value="{{ old('cantidad_participantes', $agendamiento->cantidad_participantes) }}" class="form-control @error('cantidad_participantes') is-invalid @enderror">
        @error('cantidad_participantes') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="requiere_proyector" value="1" id="requiere_proyector" @checked(old('requiere_proyector', $agendamiento->requiere_proyector))>
            <label class="form-check-label fw-semibold" for="requiere_proyector">Requiere proyector</label>
        </div>
    </div>

    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="requiere_apoyo_tecnico" value="1" id="requiere_apoyo_tecnico" @checked(old('requiere_apoyo_tecnico', $agendamiento->requiere_apoyo_tecnico))>
            <label class="form-check-label fw-semibold" for="requiere_apoyo_tecnico">Requiere apoyo técnico</label>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Responsable retiro</label>
        <input type="text" name="responsable_retiro" value="{{ old('responsable_retiro', $agendamiento->responsable_retiro) }}" class="form-control @error('responsable_retiro') is-invalid @enderror" maxlength="180">
        @error('responsable_retiro') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Responsable devolución</label>
        <input type="text" name="responsable_devolucion" value="{{ old('responsable_devolucion', $agendamiento->responsable_devolucion) }}" class="form-control @error('responsable_devolucion') is-invalid @enderror" maxlength="180">
        @error('responsable_devolucion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Motivo / actividad</label>
        <textarea name="motivo" rows="4" class="form-control @error('motivo') is-invalid @enderror">{{ old('motivo', $agendamiento->motivo) }}</textarea>
        @error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Observaciones</label>
        <textarea name="observaciones" rows="3" class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $agendamiento->observaciones) }}</textarea>
        @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<div class="alert alert-info mt-4 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Si la sala seleccionada requiere aprobación, la solicitud quedará pendiente y se notificará al administrador de sala.
</div>

<div class="d-flex justify-content-between mt-4">
    <a href="{{ route('gestion.agendamientos-recursos.index', ['month' => optional($agendamiento->fecha)->format('Y-m') ?: now()->format('Y-m'), 'recurso_id' => $agendamiento->recurso_catalogo_id]) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Volver
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-save me-1"></i> Guardar agendamiento
    </button>
</div>
