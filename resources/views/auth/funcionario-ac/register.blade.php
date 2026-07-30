@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="card sga-auth-card">
                <div class="card-body">
                    <h1 class="h4 mb-2">Registro Funcionarios Administración Central</h1>
                    <p class="text-muted mb-4">Este registro es exclusivo para RUN autorizados previamente por carga masiva. Si ya tienes una cuenta en el sistema, se agregará el rol y deberas ingresar con tus credenciales existentes.</p>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    @if ($errors->has('register'))
                        <div class="alert alert-danger">{{ $errors->first('register') }}</div>
                    @endif

                    <form method="POST" action="{{ route('funcionario-ac.register.store') }}" class="js-validate" novalidate>
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">RUN <span class="text-danger">*</span></label>
                                <input type="text" name="run" value="{{ old('run') }}" class="form-control @error('run') is-invalid @enderror" placeholder="12345678" required autocomplete="off">
                                <div class="form-text">Solo numeros, sin puntos ni guion.</div>
                                @error('run')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">DV <span class="text-danger">*</span></label>
                                <input type="text" name="dv" value="{{ old('dv') }}" class="form-control @error('dv') is-invalid @enderror" placeholder="K" maxlength="2" required autocomplete="off">
                                @error('dv')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="alert alert-info small">
                            Completa los datos siguientes solo si aún no tienes cuenta creada. Si el RUN ya existe en el sistema, el sistema agregará el rol funcionario_ac y te pedira ingresar con tus credenciales actuales.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombres</label>
                                <input type="text" name="nombres" value="{{ old('nombres') }}" class="form-control @error('nombres') is-invalid @enderror" autocomplete="off">
                                @error('nombres')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellido paterno</label>
                                <input type="text" name="apellido_paterno" value="{{ old('apellido_paterno') }}" class="form-control @error('apellido_paterno') is-invalid @enderror" autocomplete="off">
                                @error('apellido_paterno')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Apellido materno</label>
                                <input type="text" name="apellido_materno" value="{{ old('apellido_materno') }}" class="form-control @error('apellido_materno') is-invalid @enderror" autocomplete="off">
                                @error('apellido_materno')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="off">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirmar contraseña</label>
                                <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <button class="btn btn-primary" type="submit">Registrar / validar RUN</button>
                            <a class="btn btn-outline-secondary" href="{{ route('funcionario-ac.login') }}">Ya tengo cuenta</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.form-validation')
@endpush
