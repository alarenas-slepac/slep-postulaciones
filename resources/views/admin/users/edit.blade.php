@extends('layouts.app')

@section('content')
<div class="ur-page">
    <div class="ur-hero">
        <div class="ur-hero-main">
            <span class="ur-hero-icon" aria-hidden="true"><i class="bi bi-person-gear"></i></span>
            <div>
                <div class="ur-eyebrow">Administración · Usuarios</div>
                <h1 class="ur-hero-title">Editar usuario</h1>
                <p class="ur-hero-subtitle">{{ $user->nombre_completo ?: $user->email }}</p>
            </div>
        </div>
        <div class="ur-hero-actions">
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver</a>
            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-primary"><i class="bi bi-eye" aria-hidden="true"></i> Ver ficha</a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card ur-panel">
                <div class="card-header"><div class="ur-panel-kicker">Datos de la cuenta</div><h2 class="ur-panel-title">Información y accesos</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="js-validate" novalidate>
                        @csrf
                        @method('PUT')

                        @include('admin.users._form', ['user' => $user])

                        <div class="ur-actionbar mt-4">
                            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> Guardar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@include('admin.users._select2_assets')

@push('scripts')
    @include('partials.form-validation')
@endpush
