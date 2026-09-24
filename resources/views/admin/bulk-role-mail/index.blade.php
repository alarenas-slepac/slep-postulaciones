@extends('layouts.app')

@section('content')
<div class="ur-page">
    <div class="ur-hero">
        <div class="ur-hero-main">
            <span class="ur-hero-icon" aria-hidden="true"><i class="bi bi-envelope-at"></i></span>
            <div>
                <div class="ur-eyebrow">Administración · Usuarios y roles</div>
                <h1 class="ur-hero-title">Correos por rol</h1>
                <p class="ur-hero-subtitle">Envía una comunicación institucional a usuarios que tengan uno de los roles seleccionados y su correo verificado.</p>
            </div>
        </div>
        <div class="ur-hero-actions"><a href="{{ route('admin.notification-logs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-clock-history" aria-hidden="true"></i> Historial de notificaciones</a></div>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bulk-role-mail.send') }}" class="card ur-panel">
        @csrf
        <div class="card-header"><div class="ur-panel-kicker">Comunicación</div><h2 class="ur-panel-title">Preparar envío</h2></div>
        <div class="card-body">
            <fieldset class="mb-4">
                <legend class="form-label">Roles destinatarios</legend>
                <div class="row g-2">
                    @foreach($roles as $role)
                        <div class="col-md-6 col-xl-4">
                            <label class="ur-option w-100">
                                <input class="form-check-input mt-1" type="checkbox" name="roles[]" value="{{ $role['name'] }}" @checked(in_array($role['name'], old('roles', []), true))>
                                <span>
                                    <span class="fw-semibold d-block">{{ $role['label'] }}</span>
                                    <span class="small text-muted">{{ $role['recipients_count'] }} usuario(s) con correo verificado</span>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="mb-3">
                <label for="subject" class="form-label fw-semibold">Asunto</label>
                <input id="subject" name="subject" type="text" maxlength="180" class="form-control" value="{{ old('subject') }}" required>
            </div>

            <div class="mb-4">
                <label for="body" class="form-label fw-semibold">Mensaje</label>
                <textarea id="body" name="body" rows="10" maxlength="20000" class="form-control" required>{{ old('body') }}</textarea>
                <div class="form-text">Cada usuario recibirá un único correo aunque tenga más de uno de los roles seleccionados.</div>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" value="1" id="confirm" name="confirm" required>
                <label class="form-check-label" for="confirm">Confirmo que revisé los roles, el asunto y el contenido antes de programar el envío.</label>
            </div>

        </div>
        <div class="ur-actionbar"><button type="submit" class="btn btn-primary"><i class="bi bi-send" aria-hidden="true"></i> Programar envío</button></div>
    </form>
</div>
@endsection
