@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card sga-auth-card">
                <div class="card-body">
                    <h1 class="h4 mb-3">Verifica tu correo electrónico</h1>

                    @if (session('status') === 'verification-link-sent')
                        <div class="alert alert-success">
                            El nuevo correo de verificación quedó en cola para <strong>{{ auth()->user()->email }}</strong>.
                        </div>
                    @endif

                    <p class="mb-3">
                        El enlace de verificación para <strong>{{ auth()->user()->email }}</strong> se envía cuando el servicio de correo está disponible.
                        Si hay una interrupción, tu cuenta y el aviso se conservan. Cuando recibas el correo, abre el enlace para verificar tu cuenta.
                    </p>

                    <p class="text-muted">
                        Revisa también la carpeta <em>Spam/Correo no deseado</em>. Si necesitas otro enlace, puedes solicitarlo a continuación.
                    </p>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        {{-- Reenviar verificación --}}
                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                Reenviar enlace de verificación
                            </button>
                        </form>

                        {{-- Cerrar sesión --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">
                                Cerrar sesión
                            </button>
                        </form>
                    </div>

                    <hr class="my-4">

                    <p class="mb-0 text-muted">
                        ¿Ingresaste un correo incorrecto? Cierra sesión y vuelve a registrarte con tu email correcto.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
