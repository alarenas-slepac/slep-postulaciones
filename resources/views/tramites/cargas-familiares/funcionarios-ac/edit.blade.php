@extends('layouts.app')

@section('content')
@php
    $modoCrear = (bool) ($modoCrear ?? false);
    $titulo = $modoCrear ? 'Crear funcionario AC autorizado' : 'Editar funcionario AC autorizado';
    $accion = $modoCrear
        ? route('tramites.cargas-familiares.admin.funcionarios-ac.store')
        : route('tramites.cargas-familiares.admin.funcionarios-ac.update', $funcionarioAc);
    $normalizado = old('rut_normalizado', $funcionarioAc->rut_normalizado ?? (($funcionarioAc->run ?? '') . ($funcionarioAc->dv ?? '')));
    $fechaNacimiento = old('fecha_nacimiento', !empty($funcionarioAc->fecha_nacimiento) ? \Illuminate\Support\Carbon::parse($funcionarioAc->fecha_nacimiento)->format('Y-m-d') : '');
@endphp

<div class="container-fluid py-4">
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3 p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="bi bi-pencil-square fs-4"></i>
                </div>
                <div>
                    <div class="text-uppercase text-muted small fw-bold">Administración Central · {{ $modoCrear ? 'nuevo registro' : 'edición de registro' }}</div>
                    <h1 class="h3 fw-bold mb-1">{{ $titulo }}</h1>
                    <p class="text-muted mb-0">Actualiza los antecedentes visibles y administrativos del registro autorizado.</p>
                </div>
            </div>
            <a href="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.import') }}" class="btn btn-outline-primary rounded-3 px-4">
                <i class="bi bi-arrow-left"></i> Volver a la nómina
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success rounded-3">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger rounded-3">
            <strong>Revise los datos ingresados.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $accion }}">
        @csrf
        @unless($modoCrear)
            @method('PATCH')
        @endunless

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 p-4 border-bottom">
                        <div class="text-uppercase text-muted small fw-bold">Datos del funcionario</div>
                        <h2 class="h5 fw-bold mb-0">Identificación y unidad</h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">RUN</label>
                                <input type="text" name="run" value="{{ old('run', $funcionarioAc->run ?? '') }}" class="form-control @error('run') is-invalid @enderror" required>
                                @error('run') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold">DV</label>
                                <input type="text" name="dv" value="{{ old('dv', $funcionarioAc->dv ?? '') }}" class="form-control @error('dv') is-invalid @enderror" required>
                                @error('dv') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">RUT normalizado</label>
                                <input type="text" name="rut_normalizado" value="{{ $normalizado }}" class="form-control" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Nombres</label>
                                <input type="text" name="nombres" value="{{ old('nombres', $funcionarioAc->nombres ?? '') }}" class="form-control @error('nombres') is-invalid @enderror" required>
                                @error('nombres') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Apellido paterno</label>
                                <input type="text" name="apellido_paterno" value="{{ old('apellido_paterno', $funcionarioAc->apellido_paterno ?? '') }}" class="form-control @error('apellido_paterno') is-invalid @enderror" required>
                                @error('apellido_paterno') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Apellido materno</label>
                                <input type="text" name="apellido_materno" value="{{ old('apellido_materno', $funcionarioAc->apellido_materno ?? '') }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Unidad / Departamento</label>
                                <input type="text" name="unidad_departamento" value="{{ old('unidad_departamento', $funcionarioAc->unidad_departamento ?? '') }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Cargo / Función</label>
                                <input type="text" name="cargo_funcion" value="{{ old('cargo_funcion', $funcionarioAc->cargo_funcion ?? '') }}" class="form-control">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Subdirección dependencia</label>
                                <select name="subdireccion_dependencia" class="form-select">
                                    <option value="">Seleccione...</option>
                                    @foreach(($subdireccionesDependencia ?? []) as $subdireccion)
                                        <option value="{{ $subdireccion }}" @selected(old('subdireccion_dependencia', $funcionarioAc->subdireccion_dependencia ?? '') === $subdireccion)>{{ $subdireccion }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Seleccione una opción del listado institucional declarado para Administración Central.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 p-4 border-bottom">
                        <div class="text-uppercase text-muted small fw-bold">Datos administrativos</div>
                        <h2 class="h5 fw-bold mb-0">Subdirección, calidad jurídica, escalafón y grado</h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Calidad jurídica</label>
                                <input type="text" name="calidad_juridica" value="{{ old('calidad_juridica', $funcionarioAc->calidad_juridica ?? '') }}" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Escalafón</label>
                                <input type="text" name="escalafon" value="{{ old('escalafon', $funcionarioAc->escalafon ?? '') }}" class="form-control">
                                <div class="form-text">Se actualiza dentro del campo observaciones.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Grado</label>
                                <input type="text" name="grado" value="{{ old('grado', $funcionarioAc->grado ?? '') }}" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Observaciones</label>
                                <textarea name="observaciones" class="form-control" rows="4">{{ old('observaciones', $funcionarioAc->observaciones ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 p-4 border-bottom">
                        <div class="text-uppercase text-muted small fw-bold">Estado del registro</div>
                        <h2 class="h5 fw-bold mb-0">Autorización y contacto</h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Periodo nómina</label>
                            <input type="text" name="periodo_nomina" value="{{ old('periodo_nomina', $funcionarioAc->periodo_nomina ?? '') }}" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Acción sistema</label>
                            <input type="text" name="accion_sistema" value="{{ old('accion_sistema', $funcionarioAc->accion_sistema ?? '') }}" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Estado autorización</label>
                            <select name="estado_autorizacion" class="form-select">
                                @foreach(['activo' => 'Activo', 'inactivo' => 'Inactivo', 'pendiente' => 'Pendiente'] as $valor => $label)
                                    <option value="{{ $valor }}" @selected(old('estado_autorizacion', $funcionarioAc->estado_autorizacion ?? 'activo') === $valor)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Jefatura institucional</label>
                            <select name="jefatura" class="form-select">
                                <option value="0" @selected(!old('jefatura', (bool) ($funcionarioAc->jefatura ?? false)))>No es jefatura</option>
                                <option value="1" @selected(old('jefatura', (bool) ($funcionarioAc->jefatura ?? false)))>Es jefatura</option>
                            </select>
                            <div class="form-text">Este campo permite usar el registro como jefatura titular o subrogante en la matriz de autorizaciones de Administración Central.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" name="telefono" value="{{ old('telefono', $funcionarioAc->telefono ?? '') }}" class="form-control @error('telefono') is-invalid @enderror">
                            @error('telefono') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" value="{{ old('email', $funcionarioAc->email ?? '') }}" class="form-control @error('email') is-invalid @enderror">
                            <div class="form-text">Campo interno de vinculación de usuario.</div>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento" value="{{ $fechaNacimiento }}" class="form-control @error('fecha_nacimiento') is-invalid @enderror">
                            <div class="form-text">Dato requerido para cometidos con compra de pasajes aéreos.</div>
                            @error('fecha_nacimiento') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4 d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg rounded-3 fw-bold">
                            <i class="bi bi-save"></i> Guardar cambios
                        </button>
                        <a href="{{ route('tramites.cargas-familiares.admin.funcionarios-ac.import') }}" class="btn btn-outline-secondary rounded-3">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <div class="small text-muted mt-2">Los cambios se aplican directamente sobre el registro autorizado de Administración Central.</div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
