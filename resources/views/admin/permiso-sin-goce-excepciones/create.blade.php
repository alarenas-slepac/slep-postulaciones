@extends('layouts.app')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.permiso-sin-goce-excepciones.index') }}" class="btn btn-sm btn-outline-secondary">Volver</a>
</div>

<h1 class="h4 mb-3">Agregar excepción permiso sin goce</h1>

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
        <form method="POST" action="{{ route('admin.permiso-sin-goce-excepciones.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">RUT titular docente <span class="text-danger">*</span></label>
                    <input type="text" name="rut" class="form-control" value="{{ old('rut') }}" placeholder="12.345.678-9" required>
                    <div class="form-text">El sistema validará que exista en el padrón de titulares y que sea docente.</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Nombre titular</label>
                    <input type="text" name="nombre_titular" class="form-control" value="{{ old('nombre_titular') }}" placeholder="Opcional; si está vacío se usará el nombre del padrón">
                </div>
                <div class="col-12">
                    <label class="form-label">Observación</label>
                    <textarea name="observacion" class="form-control" rows="3" maxlength="1000">{{ old('observacion') }}</textarea>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Guardar excepción</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.permiso-sin-goce-excepciones.index') }}">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection
