@extends('layouts.app')

@section('content')
    @include('remuneraciones.descuentos-cgr._styles')
    <div class="cgr-page">
        <div class="cgr-page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div><div class="cgr-page-header__eyebrow"><span class="cgr-page-header__icon"><i class="bi bi-bell" aria-hidden="true"></i></span> Remuneraciones · Descuentos CGR</div><h1 class="mb-2">Notificaciones CGR</h1><p class="mb-0">Agrega destinatarios por etapa. Los usuarios con el rol correspondiente reciben el aviso automáticamente.</p></div>
            <a href="{{ route('descuentos-cgr.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        </div>
        @if (session('status')) <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('status') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>Revisa los correos ingresados.</div> @endif
        <form method="POST" action="{{ route('descuentos-cgr.notificaciones.update') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><i class="bi bi-envelope me-2 text-primary"></i>Destinatarios adicionales</div>
            <div class="card-body row g-4">
                <div class="col-md-6"><label for="finanzas" class="form-label">Aviso a Finanzas</label><textarea id="finanzas" name="finanzas" rows="6" class="form-control @error('finanzas') is-invalid @enderror" placeholder="Un correo por línea">{{ old('finanzas', $configuraciones['finanzas'] ?? '') }}</textarea><div class="form-text">Se suman a los usuarios con rol Funcionario DAF. Puedes separar correos con líneas, comas o punto y coma.</div>@error('finanzas')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label for="auditoria" class="form-label">Aviso a Auditoría Interna</label><textarea id="auditoria" name="auditoria" rows="6" class="form-control @error('auditoria') is-invalid @enderror" placeholder="Un correo por línea">{{ old('auditoria', $configuraciones['auditoria'] ?? '') }}</textarea><div class="form-text">Se suman a los usuarios con rol Auditoria SLEP.</div>@error('auditoria')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <div class="card-footer d-flex justify-content-end gap-2"><a href="{{ route('descuentos-cgr.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Guardar correos</button></div>
        </form>
    </div>
@endsection
