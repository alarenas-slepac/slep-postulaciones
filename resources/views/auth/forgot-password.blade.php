@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card sga-auth-card">
                <div class="card-body">
                    <h1 class="h4 mb-3">Recuperar contraseña</h1>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="js-validate" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror" placeholder="tu.correo@ejemplo.cl"
                                required autocomplete="email">
                            <div class="form-text">Te enviaremos un enlace para restablecer tu contraseña.</div>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @unless ($errors->has('email'))
                                <div class="invalid-feedback"></div>
                            @endunless
                        </div>

                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Enviar enlace</button>
                            <a class="btn btn-outline-secondary" href="{{ route('login') }}">Volver a iniciar sesión</a>
                        </div>

                        <p class="text-muted mt-3 mb-0">* Campo obligatorio</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.form-validation')
@endpush
