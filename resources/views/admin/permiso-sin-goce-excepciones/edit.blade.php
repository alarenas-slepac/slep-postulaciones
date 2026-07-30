@extends('layouts.app')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.permiso-sin-goce-excepciones.index') }}" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<h1 class="h4 mb-3">Editar excepción permiso sin goce</h1>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.permiso-sin-goce-excepciones.update', $item) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">RUT normalizado</label>
                    <input type="text" class="form-control" value="{{ $item->rut_normalizado }}" disabled>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Nombre titular <span class="text-danger">*</span></label>
                    <input type="text" name="nombre_titular" class="form-control" value="{{ old('nombre_titular', $item->nombre_titular) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Observación</label>
                    <textarea name="observacion" class="form-control" rows="3" maxlength="1000">{{ old('observacion', $item->observacion) }}</textarea>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $item->activo))>
                        <label class="form-check-label" for="activo">Excepción activa</label>
                    </div>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.permiso-sin-goce-excepciones.index') }}">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
