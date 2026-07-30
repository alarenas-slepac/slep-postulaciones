@extends('layouts.app')

@push('styles')
<style>
    .fac-create-header {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid #d9e4f3;
        border-radius: 24px;
        box-shadow: 0 18px 44px rgba(15, 23, 42, 0.08);
        padding: 1.5rem;
    }

    .fac-create-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
        color: #fff;
        box-shadow: 0 10px 24px rgba(34, 197, 94, .28);
        font-size: 1.25rem;
    }

    .fac-create-title {
        font-size: clamp(1.5rem, 2vw, 2rem);
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }

    .fac-panel {
        border: 1px solid #d9e4f3;
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .fac-panel__header {
        padding: 1.25rem 1.5rem 1rem;
        border-bottom: 1px solid #e8eef5;
        background: #fff;
    }

    .fac-panel__eyebrow {
        font-size: .76rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: .35rem;
    }

    .fac-panel__title {
        font-size: 1.25rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: .2rem;
    }

    .fac-panel__body { padding: 1.4rem 1.5rem 1.5rem; }
    .fac-form-label { font-weight: 700; color: #0f172a; }
    .fac-help { color: #64748b; font-size: .9rem; }

    .fac-btn-primary,
    .fac-btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        min-height: 44px;
        border-radius: 14px;
        font-weight: 800;
        padding: .75rem 1.15rem;
        text-decoration: none;
    }

    .fac-btn-primary { border: 1px solid #16a34a; background: #16a34a; color: #fff; }
    .fac-btn-primary:hover { color: #fff; background: #15803d; border-color: #15803d; }
    .fac-btn-secondary { border: 1px solid #d9e4f3; background: #fff; color: #0f172a; }
    .fac-btn-secondary:hover { color: #0f172a; background: #f8fafc; }
</style>
@endpush

@section('content')
@php
    $campoExiste = fn (string $campo) => in_array($campo, $campos ?? [], true);
@endphp

<div class="container py-4">
    <div class="fac-create-header mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
            <div class="d-flex gap-3 align-items-start">
                <span class="fac-create-icon"><i class="bi bi-person-plus"></i></span>
                <div>
                    <div class="text-uppercase fw-bold text-muted small mb-1">Administración Central · Nuevo registro</div>
                    <h1 class="fac-create-title">Crear funcionario AC autorizado</h1>
                    <p class="mb-0 text-muted">Registro manual para docentes o asistentes que trabajan en el SLEP. El grado es opcional y puede quedar sin información.</p>
                </div>
            </div>

            <a href="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.import') }}" class="fac-btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Volver a la nómina
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <div class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Revisa los datos ingresados.</div>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.store') }}">
        @csrf

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="fac-panel mb-4">
                    <div class="fac-panel__header">
                        <div class="fac-panel__eyebrow">Datos del funcionario</div>
                        <div class="fac-panel__title">Identificación y dependencia</div>
                    </div>
                    <div class="fac-panel__body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fac-form-label" for="run">RUN <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="run" name="run" value="{{ old('run') }}" placeholder="Ej.: 12345678" required>
                                <div class="form-text">Ingrese sólo números, sin puntos ni guion.</div>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label fac-form-label" for="dv">DV <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="dv" name="dv" value="{{ old('dv') }}" maxlength="2" placeholder="K" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fac-form-label" for="nombres">Nombres <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombres" name="nombres" value="{{ old('nombres') }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fac-form-label" for="apellido_paterno">Apellido paterno</label>
                                <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="{{ old('apellido_paterno') }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fac-form-label" for="apellido_materno">Apellido materno</label>
                                <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="{{ old('apellido_materno') }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fac-form-label" for="unidad_departamento">Unidad / Departamento</label>
                                <input type="text" class="form-control" id="unidad_departamento" name="unidad_departamento" value="{{ old('unidad_departamento') }}" placeholder="Ej.: Unidad de Compras">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fac-form-label" for="cargo_funcion">Cargo / Función</label>
                                <input type="text" class="form-control" id="cargo_funcion" name="cargo_funcion" value="{{ old('cargo_funcion') }}" placeholder="Ej.: Docente, Asistente, Profesional de apoyo">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fac-form-label" for="subdireccion_dependencia">Subdirección dependencia</label>
                                <select class="form-select" id="subdireccion_dependencia" name="subdireccion_dependencia">
                                    <option value="">Seleccione subdirección o dependencia...</option>
                                    @foreach(($subdireccionesDependencia ?? []) as $subdireccion)
                                        <option value="{{ $subdireccion }}" @selected(old('subdireccion_dependencia') === $subdireccion)>
                                            {{ $subdireccion }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fac-form-label" for="email">Email institucional</label>
                                <input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}" placeholder="nombre@slepandaliencosta.gob.cl">
                                <div class="form-text">Si ya existe usuario, luego se podrá asignar el rol correspondiente.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fac-panel">
                    <div class="fac-panel__header">
                        <div class="fac-panel__eyebrow">Datos administrativos</div>
                        <div class="fac-panel__title">Calidad jurídica, escalafón y observaciones</div>
                    </div>
                    <div class="fac-panel__body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fac-form-label" for="calidad_juridica">Calidad jurídica</label>
                                <input type="text" class="form-control" id="calidad_juridica" name="calidad_juridica" value="{{ old('calidad_juridica') }}" placeholder="Ej.: CONTRATA">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fac-form-label" for="escalafon">Escalafón</label>
                                <input type="text" class="form-control" id="escalafon" name="escalafon" value="{{ old('escalafon') }}" placeholder="Ej.: DOCENTE / ASISTENTE">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fac-form-label" for="grado">Grado</label>
                                <input type="text" class="form-control" id="grado" name="grado" value="{{ old('grado') }}" placeholder="No aplica">
                                <div class="form-text">Opcional. Para docentes o asistentes que trabajan en SLEP puede quedar vacío.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fac-form-label" for="observaciones">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="4" placeholder="Observación interna opcional">{{ old('observaciones') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="fac-panel mb-4">
                    <div class="fac-panel__header">
                        <div class="fac-panel__eyebrow">Estado del registro</div>
                        <div class="fac-panel__title">Autorización</div>
                    </div>
                    <div class="fac-panel__body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fac-form-label" for="activo">Activo</label>
                                <select class="form-select" id="activo" name="activo">
                                    <option value="1" @selected(old('activo', '1') === '1')>Sí</option>
                                    <option value="0" @selected(old('activo') === '0')>No</option>
                                </select>
                            </div>

                            @if($campoExiste('jefatura'))
                                <div class="col-12">
                                    <label class="form-label fac-form-label" for="jefatura">Jefatura institucional</label>
                                    <select class="form-select" id="jefatura" name="jefatura">
                                        <option value="0" @selected(old('jefatura', '0') === '0')>No es jefatura</option>
                                        <option value="1" @selected(old('jefatura') === '1')>Sí, es jefatura o subrogante autorizable</option>
                                    </select>
                                </div>
                            @endif

                            <div class="col-12">
                                <label class="form-label fac-form-label" for="telefono">Teléfono</label>
                                <input type="text" class="form-control" id="telefono" name="telefono" value="{{ old('telefono') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fac-panel">
                    <div class="fac-panel__body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="fac-btn-primary">
                                <i class="bi bi-person-plus"></i>
                                Crear funcionario AC autorizado
                            </button>
                            <a href="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.import') }}" class="fac-btn-secondary">
                                <i class="bi bi-x-circle"></i>
                                Cancelar
                            </a>
                        </div>
                        <p class="fac-help mt-3 mb-0">El registro se crea directamente como autorizado. El grado queda opcional para docentes o asistentes que no cuentan con grado administrativo.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
