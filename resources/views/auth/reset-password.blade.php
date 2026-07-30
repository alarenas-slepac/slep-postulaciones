@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card sga-auth-card">
                <div class="card-body">
                    <h1 class="h4 mb-3">Restablecer contraseña</h1>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('password.store') }}" class="js-validate" novalidate>
                        @csrf

                        {{-- Token del enlace --}}
                        <input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">

                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" value="{{ old('email', $email ?? request('email')) }}"
                                class="form-control @error('email') is-invalid @enderror" placeholder="tu.correo@ejemplo.cl"
                                required autocomplete="email">
                            <div class="form-text">Tu correo asociado a la cuenta.</div>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @unless ($errors->has('email'))
                                <div class="invalid-feedback"></div>
                            @endunless
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
                            <div class="form-text">Usa mayúsculas, minúsculas, números y símbolos.</div>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @unless ($errors->has('password'))
                                <div class="invalid-feedback"></div>
                            @endunless
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" class="form-control"
                                placeholder="Repite la contraseña" required data-match='[name="password"]'
                                autocomplete="new-password">
                            <div class="form-text">Debe coincidir con la nueva contraseña.</div>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Restablecer</button>
                            <a class="btn btn-outline-secondary" href="{{ route('login') }}">Volver a iniciar sesión</a>
                        </div>

                        <p class="text-muted mt-3 mb-0">* Campos obligatorios</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.form-validation')
@endpush
