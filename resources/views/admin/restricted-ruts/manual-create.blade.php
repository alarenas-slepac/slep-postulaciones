@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Bloqueo manual</h1>
            <p class="text-muted mb-0">Crea o actualiza la restricción manual de un RUT.</p>
        </div>
        <a href="{{ route('admin.restricted-ruts.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.restricted-ruts.manual.store') }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">RUT</label>
                    <input type="text" name="rut" value="{{ old('rut') }}" class="form-control @error('rut') is-invalid @enderror" placeholder="12345678-9" required>
                    @error('rut')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha inicio prohibición</label>
                    <input type="date" name="fecha_inicio_prohibicion" value="{{ old('fecha_inicio_prohibicion') }}" class="form-control @error('fecha_inicio_prohibicion') is-invalid @enderror" required>
                    @error('fecha_inicio_prohibicion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha término prohibición</label>
                    <input type="date" name="fecha_termino_prohibicion" value="{{ old('fecha_termino_prohibicion') }}" class="form-control @error('fecha_termino_prohibicion') is-invalid @enderror" required>
                    @error('fecha_termino_prohibicion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Comentario</label>
                    <textarea name="comentario" rows="4" class="form-control @error('comentario') is-invalid @enderror">{{ old('comentario') }}</textarea>
                    @error('comentario')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar bloqueo manual</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
