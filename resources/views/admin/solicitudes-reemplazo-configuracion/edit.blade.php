@extends('layouts.app')

@section('content')
    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h4 mb-1">Configuración de solicitudes de reemplazo</h1>
                <p class="text-muted mb-0">Destinatarios y parámetros administrativos del flujo de reemplazos.</p>
            </div>
            <a href="{{ route('gestion.solicitudes-reemplazo.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Autorizaciones docentes</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.solicitudes-reemplazo-configuracion.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="correo_autorizaciones_docentes" class="form-label fw-semibold">
                            Correo destinatario <span class="text-danger">*</span>
                        </label>
                        <input
                            type="email"
                            id="correo_autorizaciones_docentes"
                            name="correo_autorizaciones_docentes"
                            class="form-control @error('correo_autorizaciones_docentes') is-invalid @enderror"
                            value="{{ old('correo_autorizaciones_docentes', $configuracion->valor) }}"
                            maxlength="255"
                            required
                        >
                        @error('correo_autorizaciones_docentes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            UATP enviará a esta casilla los antecedentes especiales, títulos y, cuando corresponda, los certificados de Religión.
                        </div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="activo" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="activo"
                            name="activo"
                            value="1"
                            @checked(old('activo', $configuracion->activo))
                        >
                        <label class="form-check-label" for="activo">Habilitar envío de solicitudes de autorización docente</label>
                    </div>

                    <button type="submit" class="btn btn-primary">Guardar configuración</button>
                </form>
            </div>
        </div>
    </div>
@endsection
