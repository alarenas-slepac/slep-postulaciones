@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Editar bloqueo manual</h1>
            <p class="text-muted mb-0">RUT {{ $manualRecord->restrictedRut->rut_formatted }}</p>
        </div>
        <a href="{{ route('admin.restricted-ruts.show', $manualRecord->restrictedRut) }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.restricted-ruts.manual.update', $manualRecord) }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-md-4">
                    <label class="form-label">Fecha inicio prohibición</label>
                    <input type="date" name="fecha_inicio_prohibicion" value="{{ old('fecha_inicio_prohibicion', optional($manualRecord->fecha_inicio_prohibicion)->format('Y-m-d')) }}" class="form-control @error('fecha_inicio_prohibicion') is-invalid @enderror" required>
                    @error('fecha_inicio_prohibicion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha término prohibición</label>
                    <input type="date" name="fecha_termino_prohibicion" value="{{ old('fecha_termino_prohibicion', optional($manualRecord->fecha_termino_prohibicion)->format('Y-m-d')) }}" class="form-control @error('fecha_termino_prohibicion') is-invalid @enderror" required>
                    @error('fecha_termino_prohibicion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <span class="badge {{ $manualRecord->activa ? 'bg-success' : 'bg-secondary' }}">{{ $manualRecord->activa ? 'Activa' : 'Inactiva' }}</span>
                </div>
                <div class="col-12">
                    <label class="form-label">Comentario</label>
                    <textarea name="comentario" rows="4" class="form-control @error('comentario') is-invalid @enderror">{{ old('comentario', $manualRecord->comentario) }}</textarea>
                    @error('comentario')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar cambios</button>
                    <a href="{{ route('admin.restricted-ruts.show', $manualRecord->restrictedRut) }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
