@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Correos por rol</h1>
            <p class="text-muted mb-0">Envía una comunicación institucional a usuarios que tengan uno de los roles seleccionados y su correo verificado.</p>
        </div>
        <a href="{{ route('admin.notification-logs.index') }}" class="btn btn-outline-secondary">Historial de notificaciones</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
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

    <form method="POST" action="{{ route('admin.bulk-role-mail.send') }}" class="card shadow-sm">
        @csrf
        <div class="card-body p-4">
            <div class="mb-4">
                <label class="form-label fw-semibold">Roles destinatarios</label>
                <div class="row g-2">
                    @foreach($roles as $role)
                        <div class="col-md-6 col-xl-4">
                            <label class="border rounded p-3 d-flex gap-3 align-items-start h-100 w-100">
                                <input class="form-check-input mt-1" type="checkbox" name="roles[]" value="{{ $role['name'] }}" @checked(in_array($role['name'], old('roles', []), true))>
                                <span>
                                    <span class="fw-semibold d-block">{{ $role['label'] }}</span>
                                    <span class="small text-muted">{{ $role['recipients_count'] }} usuario(s) con correo verificado</span>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

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

            <button type="submit" class="btn btn-primary">Programar envío</button>
        </div>
    </form>
</div>
@endsection
