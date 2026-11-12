@extends('layouts.app')
@push('styles')
    @vite('resources/js/account-verification.js')
@endpush

@section('content')
@php
    $wa = $whatsapp ?? ['available' => false, 'verified' => false, 'phone' => '', 'challenge_id' => null, 'expires_at' => null, 'resend_at' => null, 'server_now' => now()->timestamp, 'delivery' => 'none', 'delivery_error' => null, 'attempts_exhausted' => false];
    $phone = old('whatsapp_phone', $wa['phone']);
    $phone = is_string($phone) ? $phone : '';
    $wait = max(0, ($wa['resend_at'] ?? 0) - $wa['server_now']);
    $remaining = max(0, ($wa['expires_at'] ?? 0) - $wa['server_now']);
    $showWhatsApp = request('channel') === 'whatsapp' || $wa['challenge_id'] || $errors->any();
@endphp
<div class="row justify-content-center">
    <div class="sga-account-verification col-12 col-lg-9 col-xl-8" data-account-verification data-now="{{ $wa['server_now'] }}" data-resend-at="{{ $wa['resend_at'] }}" data-expires-at="{{ $wa['expires_at'] }}" data-available="{{ $wa['available'] ? '1' : '0' }}" data-challenge="{{ $wa['challenge_id'] }}" data-status-url="{{ route('verification.whatsapp.status') }}" data-dashboard-url="{{ route('dashboard') }}">
        <header class="mb-4">
            <p class="small text-secondary mb-2">Acceso al SGA</p>
            <h1 class="h3 fw-bold mb-2"><i class="bi bi-shield-check text-primary me-2" aria-hidden="true"></i>Verifica tu cuenta</h1>
            <p class="text-secondary mb-0">Confirma tu cuenta por correo electrónico o mediante un código enviado a tu WhatsApp.</p>
        </header>
        @if($wa['verified'])
            <div class="alert alert-success rounded-4" role="status">Tu cuenta ya está habilitada por WhatsApp. Tu correo todavía está pendiente de confirmación.
                <a class="btn btn-primary rounded-pill d-block d-sm-inline-block mt-2 ms-sm-2" href="{{ route('dashboard') }}">Continuar al SGA</a>
            </div>
        @endif
        @if(session('status') === 'verification-link-sent')
            <div class="alert alert-success rounded-4" role="status">El nuevo enlace de verificación quedó en cola para <span class="text-break">{{ auth()->user()->email }}</span>.</div>
        @endif
        @if(session('status') === 'whatsapp-code-requested')
            <div class="alert alert-info rounded-4" role="status">Solicitamos tu código por WhatsApp. Escríbelo a continuación cuando lo recibas; si solicitas otro, utiliza el último.</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger rounded-4" role="alert" tabindex="-1" data-verification-errors>
                <p class="fw-semibold mb-1">Revisa los datos para continuar.</p>
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <section class="card sga-auth-card border rounded-4 mb-4" aria-labelledby="verification-email-title">
            <div class="card-body p-3 p-md-4">
                <h2 class="h5 fw-bold mb-3" id="verification-email-title"><i class="bi bi-envelope text-primary me-2" aria-hidden="true"></i>Correo electrónico</h2>
                <p>Abre el enlace enviado a <strong class="text-break">{{ auth()->user()->email }}</strong>. Revisa también la carpeta de spam.</p>
                <p class="small text-secondary">Si el servicio de correo está interrumpido, tu cuenta y el aviso se conservan. Puedes validar tu cuenta por WhatsApp para continuar.</p>
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary rounded-pill px-3">Reenviar enlace de verificación</button>
                </form>
            </div>
        </section>
        @unless($wa['verified'])
        <section class="card sga-auth-card border rounded-4 mb-4" aria-labelledby="verification-whatsapp-title">
            <div class="card-body p-3 p-md-4">
                <h2 class="h5 fw-bold mb-3" id="verification-whatsapp-title"><i class="bi bi-whatsapp text-primary me-2" aria-hidden="true"></i>WhatsApp</h2>
                @if(!$wa['available'])
                    <p class="text-secondary mb-0">La validación por WhatsApp no está disponible en este momento. Puedes utilizar el correo o contactar a soporte.</p>
                @elseif(!$showWhatsApp)
                    <p>Recibe un código en tu celular y úsalo para habilitar tu cuenta.</p>
                    <a class="btn btn-primary rounded-pill px-3" href="{{ route('verification.notice', ['channel' => 'whatsapp']) }}#verification-whatsapp-title">Validar por WhatsApp</a>
                @else
                    <form method="POST" action="{{ route('verification.whatsapp.send') }}" data-whatsapp-send>
                        @csrf
                        <label class="form-label fw-semibold" for="whatsapp-phone">Número de celular <span class="text-danger">*</span></label>
                        <input class="form-control rounded-3 @error('whatsapp_phone') is-invalid @enderror" type="tel" inputmode="tel" autocomplete="tel" id="whatsapp-phone" name="whatsapp_phone" value="{{ $phone }}" maxlength="32" pattern="(?:9[0-9]{8}|569[0-9]{8}|\+569[0-9]{8})" required aria-describedby="whatsapp-phone-help @error('whatsapp_phone') whatsapp-phone-error @enderror" @error('whatsapp_phone') aria-invalid="true" @enderror>
                        <p class="form-text" id="whatsapp-phone-help">Acepta 9XXXXXXXX, 569XXXXXXXX o +569XXXXXXXX. Se guardará como +569XXXXXXXX en tu teléfono principal. Utiliza un número personal: cada número puede validar una sola cuenta.</p>
                        @error('whatsapp_phone')<div class="invalid-feedback" id="whatsapp-phone-error">{{ $message }}</div>@enderror
                        <button class="btn btn-primary rounded-pill px-3 mt-2" type="submit" data-whatsapp-send-button @disabled($wait > 0)>Enviar código a WhatsApp</button>
                        <p class="small text-secondary mt-2 mb-0" data-resend-timer>{{ $wait > 0 ? 'Podrás solicitar otro código en '.$wait.' segundos.' : 'Puedes solicitar un código nuevo.' }}</p>
                    </form>
                    <div class="alert alert-info rounded-4 mt-3 mb-0 {{ $wa['delivery_error'] ? '' : 'd-none' }}" role="status" data-whatsapp-delivery>{{ $wa['delivery_error'] }}</div>
                    @if($wa['challenge_id'])
                    <form method="POST" action="{{ route('verification.whatsapp.confirm') }}" class="border-top pt-4 mt-4" data-whatsapp-confirm>
                        @csrf
                        <input type="hidden" name="challenge_id" value="{{ $wa['challenge_id'] }}">
                        <label class="form-label fw-semibold" for="whatsapp-code">Código recibido por WhatsApp <span class="text-danger">*</span></label>
                        <input class="form-control rounded-3 @error('whatsapp_code') is-invalid @enderror" id="whatsapp-code" name="whatsapp_code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="12" placeholder="000000" required aria-describedby="whatsapp-code-help @error('whatsapp_code') whatsapp-code-error @enderror" @error('whatsapp_code') aria-invalid="true" @enderror @if(session('status') === 'whatsapp-code-requested') data-focus-code @endif>
                        <p class="form-text" id="whatsapp-code-help">Introduce los 6 dígitos. El código es personal, de un solo uso y vence a los 5 minutos de solicitarlo.</p>
                        @error('whatsapp_code')<div class="invalid-feedback" id="whatsapp-code-error">{{ $message }}</div>@enderror
                        <p class="small text-secondary" data-expiry-timer>El código vence en {{ $remaining }} segundos.</p>
                        <button class="btn btn-primary rounded-pill px-3" type="submit" data-whatsapp-confirm-button>Validar cuenta</button>
                    </form>
                    @elseif($wa['attempts_exhausted'])
                        <p class="text-danger mt-3 mb-0">Se agotaron los 5 intentos de este código. Solicita uno nuevo cuando termine la espera.</p>
                    @elseif($wa['resend_at'])
                        <p class="text-secondary mt-3 mb-0">El código anterior ya no es válido. Solicita uno nuevo para continuar.</p>
                    @endif
                    <noscript><p class="small text-secondary mt-3 mb-0">Actualiza esta página para consultar el tiempo restante y habilitar el reenvío al terminar el minuto.</p><a class="btn btn-outline-secondary rounded-pill mt-2" href="{{ route('verification.notice', ['channel' => 'whatsapp']) }}">Actualizar estado</a></noscript>
                    <p class="visually-hidden" aria-live="polite" data-whatsapp-announcement></p>
                @endif
            </div>
        </section>
        @endunless
        <p class="small text-secondary">La validación por WhatsApp confirma tu celular y habilita la cuenta. El correo conserva su verificación independiente.</p>
        <form method="POST" action="{{ route('logout') }}" class="mb-3">@csrf<button class="btn btn-outline-secondary rounded-pill px-3" type="submit">Cerrar sesión</button></form>
        <p class="small text-secondary">¿Ingresaste un correo incorrecto? Contacta a soporte para revisar tu cuenta.</p>
    </div>
</div>
@endsection
