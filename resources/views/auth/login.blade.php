@extends('layouts.app')

@section('content')
    <div class="row justify-content-center align-items-stretch g-4">
        <div class="col-lg-5 col-xl-5">
            <section class="sga-auth-panel h-100">
                <div class="sga-auth-logo">
                    <img src="{{ $layoutLoginLogoUrl ?? asset('branding/06_logo_login.png') }}" alt="Plataforma SLEP Andalién Costa" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('d-none');">
                    <span class="sga-auth-logo-fallback d-none">AC</span>
                </div>
                <div class="sga-auth-eyebrow">Acceso institucional</div>
                <h1 class="sga-auth-title">Plataforma SLEP Andalién Costa</h1>
                <p class="sga-auth-subtitle mb-4">
                    Sistema de Gestión Administrativa para solicitudes, trámites, postulaciones, reemplazos y procesos administrativos internos.
                </p>
                <div class="sga-auth-feature">
                    <i class="bi bi-shield-check"></i>
                    <div>
                        <strong>Ingreso seguro</strong><br>
                        <span>Accede con RUT o correo electrónico y contraseña registrada.</span>
                    </div>
                </div>
                <div class="sga-auth-feature">
                    <i class="bi bi-person-badge"></i>
                    <div>
                        <strong>Vista según rol activo</strong><br>
                        <span>El menú, dashboard y acciones se ajustan al perfil habilitado en sesión.</span>
                    </div>
                </div>
                <div class="sga-auth-feature">
                    <i class="bi bi-briefcase"></i>
                    <div>
                        <strong>Consulta pública</strong><br>
                        <span>Las ofertas laborales vigentes pueden revisarse sin iniciar sesión.</span>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-5 col-xl-5">
            <div class="card sga-auth-card mb-3">
                <div class="card-body">
                    <div class="mb-4">
                        <div class="sga-auth-eyebrow text-primary">Bienvenido/a</div>
                        <h2 class="h3 sga-auth-section-title mb-2">Iniciar sesión</h2>
                        <p class="sga-auth-muted mb-0">Ingresa tus credenciales para acceder al sistema.</p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="js-validate" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-bold">RUT o Email <span class="text-danger">*</span></label>
                            <input type="text" name="login" value="{{ old('login') }}"
                                class="form-control @error('login') is-invalid @enderror"
                                placeholder="12.345.678-K o tu.correo@ejemplo.cl" required
                                data-validate="login-email-or-rut" autocomplete="username">
                            <div class="form-text">Puedes usar tu RUT con dígito verificador o tu correo electrónico.</div>
                            @error('login')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @unless ($errors->has('login'))
                                <div class="invalid-feedback"></div>
                            @endunless
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" name="password"
                                class="form-control @error('password') is-invalid @enderror" placeholder="Tu contraseña"
                                required autocomplete="current-password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @unless ($errors->has('password'))
                                <div class="invalid-feedback"></div>
                            @endunless
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                                <label class="form-check-label" for="remember">Recordarme</label>
                            </div>
                            <a href="{{ route('password.request') }}">Olvidé mi contraseña</a>
                        </div>

                        <div class="d-grid gap-2 sga-auth-actions">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i> Ingresar</button>
                            <a class="btn btn-outline-danger" href="{{ route('register') }}"><i class="bi bi-person-plus me-1"></i> Crear cuenta</a>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <div class="small text-muted mb-2">También puedes revisar las ofertas disponibles sin iniciar sesión.</div>
                            <a class="btn btn-outline-primary w-100" href="{{ route('public.ofertas-laborales.index') }}">
                                <i class="bi bi-briefcase me-1"></i> Ver ofertas laborales vigentes
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card sga-auth-card border-success">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="text-success fs-4 lh-1">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h2 class="h6 mb-1 fw-bold">Acceso funcionarios de Administración Central</h2>
                            <p class="small text-muted mb-3">
                                Si ya tienes una cuenta creada, ingresa con las mismas credenciales.
                            </p>
                            <a class="btn btn-success w-100" href="{{ url('/funcionario-ac/login') }}">
                                Ingresar como Funcionario Administración Central
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.form-validation')
@endpush
