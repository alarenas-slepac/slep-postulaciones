@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card sga-auth-card">
                <div class="card-body">
                    <h1 class="h4 mb-2">Acceso Funcionarios Administración Central</h1>
                    <p class="text-muted mb-4">Ingresa con tu RUT o correo y la contraseña ya creada en el sistema.</p>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="js-validate" novalidate>
                        @csrf
                        <input type="hidden" name="active_role" value="funcionario_ac">

                        <div class="mb-3">
                            <label class="form-label">RUT o Email <span class="text-danger">*</span></label>
                            <input type="text" name="login" value="{{ old('login') }}" class="form-control @error('login') is-invalid @enderror" placeholder="12.345.678-K o correo" required data-validate="login-email-or-rut" autocomplete="username">
                            <div class="form-text">Si ya tenias usuario con otro rol, debes usar esas mismas credenciales.</div>
                            @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @unless ($errors->has('login'))<div class="invalid-feedback"></div>@endunless
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Tu contraseña" required autocomplete="current-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @unless ($errors->has('password'))<div class="invalid-feedback"></div>@endunless
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                <label class="form-check-label" for="remember">Recordarme</label>
                            </div>
                            <a href="{{ route('password.request') }}">Olvidé mi contraseña</a>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit">Ingresar</button>
                            <a class="btn btn-outline-secondary" href="{{ route('funcionario-ac.register') }}">Registro funcionario AC</a>
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
