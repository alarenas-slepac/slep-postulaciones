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

        <form method="POST" action="{{ route('admin.solicitudes-reemplazo-configuracion.update') }}">
            @csrf
            @method('PUT')

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Autorizaciones docentes</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="correo_autorizaciones_docentes" class="form-label fw-semibold">
                            Correo destinatario <span class="text-danger">*</span>
                        </label>
                        <input
                            type="email"
                            id="correo_autorizaciones_docentes"
                            name="correo_autorizaciones_docentes"
                            class="form-control @error('correo_autorizaciones_docentes') is-invalid @enderror"
                            value="{{ old('correo_autorizaciones_docentes', $correoAutorizaciones->valor) }}"
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
                        <input type="hidden" name="autorizaciones_docentes_activo" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="autorizaciones_docentes_activo"
                            name="autorizaciones_docentes_activo"
                            value="1"
                            @checked(old('autorizaciones_docentes_activo', $correoAutorizaciones->activo))
                        >
                        <label class="form-check-label" for="autorizaciones_docentes_activo">Habilitar envío de solicitudes de autorización docente</label>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold">Deuda de pensión de alimentos</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="correo_encargada_remuneraciones" class="form-label fw-semibold">
                            Correo de la encargada de remuneraciones <span class="text-danger">*</span>
                        </label>
                        <input
                            type="email"
                            id="correo_encargada_remuneraciones"
                            name="correo_encargada_remuneraciones"
                            class="form-control @error('correo_encargada_remuneraciones') is-invalid @enderror"
                            value="{{ old('correo_encargada_remuneraciones', $correoRemuneraciones->valor) }}"
                            maxlength="255"
                        >
                        @error('correo_encargada_remuneraciones')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">
                            Obligatorio al habilitar el envío. Recibirá el certificado de deuda, la resolución o dictamen actualizado y la declaración jurada vigente para ejercer cargo público.
                        </div>
                    </div>

                    <div class="form-check form-switch">
                        <input type="hidden" name="deuda_pension_activo" value="0">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="deuda_pension_activo"
                            name="deuda_pension_activo"
                            value="1"
                            @checked(old('deuda_pension_activo', $correoRemuneraciones->activo))
                        >
                        <label class="form-check-label" for="deuda_pension_activo">Habilitar envío de expedientes de deuda de pensión de alimentos</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Guardar configuración</button>
        </form>
    </div>
@endsection
